/**
 * fivecorners.crmblockcollapse
 * Реальные CSS-классы UI EntityEditor (Bitrix24 21+):
 *   .ui-entity-editor-section          — view-mode контейнер секции
 *   .ui-entity-editor-section-header   — заголовок (кликабельный)
 *   .ui-entity-editor-header-title-text — текст заголовка
 *   .ui-entity-editor-section-content-wrapper — тело (коллапсируем)
 */
(function (window, document) {
    'use strict';

    var CONFIG = window.FC_CBC_CONFIG || {
        enabledTypes:          ['DEAL', 'LEAD', 'CONTACT', 'COMPANY', 'SMART_PROCESS'],
        enabledSmTypes:        [],
        ajaxUrl:               '/local/ajax/fivecorners_crmblockcollapse.php',
        collapseAllFirstVisit: true
    };

    var SECTION_SEL  = '.ui-entity-editor-section';
    var HEADER_SEL   = '.ui-entity-editor-section-header';
    var TITLE_SEL    = '.ui-entity-editor-header-title-text';
    var BODY_SEL     = '.ui-entity-editor-section-content-wrapper';

    var entityInfo        = null;
    var blockState        = {};
    var expandedByStage   = [];
    var sectionMap        = {};   // key → {section, body, wrap} for reapply after stage change
    var stateLoaded       = false;
    var navigationPending = false;
    var observer          = null;
    var stageWatcher      = null;
    var saveTimer         = null;
    var stageReloadTimer  = null;

    // «Свернуть всё при первом визите»: активно, пока у пользователя нет куки-маркера
    // для данного типа сущности. Сворачивает все ещё не настроенные блоки и персистит
    // их как свёрнутые на сервер (один bulk-запрос), чтобы поведение было устойчивым.
    var firstTimeCollapse = false;
    var firstTimeQueue    = {};
    var firstTimeSaveTimer = null;

    // ── Entity detection ──────────────────────────────────────────────────────
    function parseEntityInfo() {
        var path = window.location.pathname;
        var m;
        if ((m = path.match(/\/crm\/deal\/(?:details|edit)\/(\d+)/)))
            return { type: 'DEAL', id: +m[1] };
        if ((m = path.match(/\/crm\/lead\/(?:details|edit)\/(\d+)/)))
            return { type: 'LEAD', id: +m[1] };
        if ((m = path.match(/\/crm\/contact\/(?:details|edit)\/(\d+)/)))
            return { type: 'CONTACT', id: +m[1] };
        if ((m = path.match(/\/crm\/company\/(?:details|edit)\/(\d+)/)))
            return { type: 'COMPANY', id: +m[1] };
        if ((m = path.match(/\/type\/(\d+)\/(?:details|edit)\/(\d+)/)))
            return { type: 'SMART_PROCESS', typeId: +m[1], id: +m[2] };
        return null;
    }

    function isEntityEnabled(info) {
        if (CONFIG.enabledTypes.indexOf(info.type) === -1) return false;
        if (info.type === 'SMART_PROCESS' && CONFIG.enabledSmTypes.length > 0) {
            return CONFIG.enabledSmTypes.indexOf(String(info.typeId)) !== -1;
        }
        return true;
    }

    // ── Block key ─────────────────────────────────────────────────────────────
    function getBlockKey(section) {
        var titleEl = section.querySelector(TITLE_SEL);
        if (titleEl && titleEl.textContent.trim()) {
            return 'title:' + titleEl.textContent.trim().toLowerCase().replace(/\s+/g, '_').slice(0, 80);
        }
        // Fallback: position among siblings
        var parent = section.parentNode;
        if (parent) {
            var siblings = parent.querySelectorAll(SECTION_SEL);
            for (var i = 0; i < siblings.length; i++) {
                if (siblings[i] === section) return 'idx:' + i;
            }
        }
        return null;
    }

    // ── AJAX ──────────────────────────────────────────────────────────────────
    function loadState(info, callback) {
        // POST (не GET): sessid не попадает в access-логи/Referer. Эндпоинт читает параметры
        // из $_REQUEST, так что POST-тело подходит. stageWatcher игнорит наш ajaxUrl, поэтому
        // собственный load не примут за CRM-сохранение.
        var sessid = (typeof BX !== 'undefined' && BX.bitrix_sessid) ? BX.bitrix_sessid() : '';
        var fd = new FormData();
        fd.append('action',      'load');
        fd.append('entity_type', info.type);
        fd.append('sessid',      sessid);
        if (info.id)     fd.append('entity_id',     String(info.id));
        if (info.typeId) fd.append('smart_type_id', String(info.typeId));
        var xhr = new XMLHttpRequest();
        xhr.open('POST', CONFIG.ajaxUrl, true);
        xhr.onload = function () {
            try {
                var r = JSON.parse(xhr.responseText);
                callback(r.success ? (r.state || {}) : {}, r.expandedByStage || []);
            }
            catch (e) { callback({}, []); }
        };
        xhr.onerror = function () { callback({}, []); };
        xhr.send(fd);
    }

    function scheduleSave(info, key, collapsed) {
        blockState[key] = collapsed;
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            var sessid = (typeof BX !== 'undefined' && BX.bitrix_sessid) ? BX.bitrix_sessid() : '';
            var fd = new FormData();
            fd.append('action',      'save');
            fd.append('entity_type', info.type);
            if (info.typeId) fd.append('smart_type_id', String(info.typeId));
            fd.append('block_key',   key);
            fd.append('is_collapsed', collapsed ? '1' : '0');
            fd.append('sessid',      sessid);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', CONFIG.ajaxUrl, true);
            xhr.send(fd);
        }, 450);
    }

    // ── First-visit cookie marker ─────────────────────────────────────────────
    function getCookie(name) {
        var re = new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)');
        var m  = document.cookie.match(re);
        return m ? decodeURIComponent(m[1]) : null;
    }

    function setCookie(name, value) {
        // 5 лет, путь /, SameSite=Lax — это просто UI-маркер, не секрет.
        // Secure добавляем на HTTPS-порталах (best practice, без него Chrome ругается).
        var secure = (window.location.protocol === 'https:') ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value)
            + '; path=/; max-age=' + (60 * 60 * 24 * 365 * 5) + '; SameSite=Lax' + secure;
    }

    function initCookieName(info) {
        return 'fc_cbc_init_' + info.type + (info.typeId ? '_' + info.typeId : '');
    }

    // Возвращает true, если это первый визит для данного типа сущности (куки ещё нет),
    // и сразу ставит куку — так интро отрабатывает строго один раз.
    function computeFirstTime(info) {
        if (!CONFIG.collapseAllFirstVisit) return false;
        var name = initCookieName(info);
        if (getCookie(name)) return false;
        setCookie(name, '1');
        return true;
    }

    // Дебаунс-флаш накопленных ключей одним bulk-запросом (merge на сервере).
    function flushFirstTime(info) {
        clearTimeout(firstTimeSaveTimer);
        firstTimeSaveTimer = setTimeout(function () {
            var keys = Object.keys(firstTimeQueue);
            if (!keys.length || !info) return;
            var blocks = {};
            for (var i = 0; i < keys.length; i++) blocks[keys[i]] = true;
            firstTimeQueue = {};
            var sessid = (typeof BX !== 'undefined' && BX.bitrix_sessid) ? BX.bitrix_sessid() : '';
            var fd = new FormData();
            fd.append('action',      'save_bulk');
            fd.append('entity_type', info.type);
            if (info.typeId) fd.append('smart_type_id', String(info.typeId));
            fd.append('blocks', JSON.stringify(blocks));
            fd.append('sessid', sessid);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', CONFIG.ajaxUrl, true);
            xhr.send(fd);
        }, 500);
    }

    // ── Collapse / expand ─────────────────────────────────────────────────────
    // CSS class fc-cbc-section--collapsed on the section is the authoritative collapsed
    // state — it drives the CSS rule that sets max-height:0 on the wrap.
    // Inline styles are only used during the JS animation and cleared afterwards,
    // so Bitrix re-renders inside a slider cannot accidentally undo the collapsed state.
    function applyState(section, body, wrap, collapsed, animate) {
        if (collapsed) {
            if (animate) {
                // Animate first, add class when transition ends (class would override inline)
                wrap.style.overflow   = 'hidden';
                wrap.style.maxHeight  = wrap.scrollHeight + 'px';
                void wrap.offsetHeight;
                wrap.style.transition = 'max-height 0.28s ease-out';
                wrap.style.maxHeight  = '0';
                var onEnd = function () {
                    wrap.removeEventListener('transitionend', onEnd);
                    wrap.style.maxHeight  = '';
                    wrap.style.overflow   = '';
                    wrap.style.transition = '';
                    section.classList.add('fc-cbc-section--collapsed');
                };
                wrap.addEventListener('transitionend', onEnd);
            } else {
                section.classList.add('fc-cbc-section--collapsed');
            }
        } else {
            if (animate) {
                // Lock inline to 0 before removing class so content doesn't flash open
                wrap.style.overflow  = 'hidden';
                wrap.style.maxHeight = '0';
                void wrap.offsetHeight;
                section.classList.remove('fc-cbc-section--collapsed');
                void wrap.offsetHeight;
                wrap.style.transition = 'max-height 0.28s ease-in';
                wrap.style.maxHeight  = wrap.scrollHeight + 'px';
                var onEnd2 = function () {
                    wrap.removeEventListener('transitionend', onEnd2);
                    wrap.style.maxHeight  = '';
                    wrap.style.overflow   = '';
                    wrap.style.transition = '';
                };
                wrap.addEventListener('transitionend', onEnd2);
            } else {
                section.classList.remove('fc-cbc-section--collapsed');
            }
        }
    }

    function createBtn() {
        var btn = document.createElement('span');
        btn.className = 'fc-cbc-toggle-btn';
        btn.setAttribute('role', 'button');
        btn.setAttribute('tabindex', '0');
        btn.setAttribute('title', 'Свернуть / Развернуть');
        btn.innerHTML = '<svg viewBox="0 0 10 8"><path d="M0 0L10 0L5 8Z"/></svg>';
        return btn;
    }

    // ── Init one section ──────────────────────────────────────────────────────
    function initSection(section) {
        if (section.dataset.fcCbcDone === '1') return;

        var key    = getBlockKey(section);
        if (!key) return;

        var header = section.querySelector(HEADER_SEL);
        var body   = section.querySelector(BODY_SEL);
        // Don't mark done yet — body may not be rendered by Bitrix at this point.
        // Observer will retry when body appears.
        if (!header || !body) return;

        section.dataset.fcCbcDone = '1';
        sectionMap[key] = {section: section, body: body, wrap: null}; // wrap set below

        // Wrap body for smooth animation
        var wrap = document.createElement('div');
        wrap.className = 'fc-cbc-body-wrap';
        body.parentNode.insertBefore(wrap, body);
        wrap.appendChild(body);
        sectionMap[key].wrap = wrap;

        // Add toggle button into header
        var btn = createBtn();
        header.appendChild(btn);

        // Приоритеты: правило по стадии > сохранённый выбор юзера > дефолт.
        // Дефолт для нетронутого блока: на первом визите — свёрнут (и персистим),
        // иначе — развёрнут (историческое поведение).
        var forcedExpanded = expandedByStage.indexOf(key) !== -1;
        var collapsed;
        if (forcedExpanded) {
            collapsed = false;
        } else if (blockState[key] === true) {
            collapsed = true;
        } else if (blockState[key] === false) {
            collapsed = false;
        } else if (firstTimeCollapse) {
            collapsed = true;
            blockState[key]    = true;   // локально считаем настроенным
            firstTimeQueue[key] = true;  // и персистим bulk-запросом
            flushFirstTime(entityInfo);
        } else {
            collapsed = false;
        }
        applyState(section, body, wrap, collapsed, false);

        // Click on header toggles
        header.addEventListener('click', function (e) {
            if (e.target.tagName === 'INPUT'
                || e.target.tagName === 'BUTTON'
                || e.target.tagName === 'A'
                || e.target.tagName === 'SELECT') return;
            var nowCollapsed = !section.classList.contains('fc-cbc-section--collapsed');
            applyState(section, body, wrap, nowCollapsed, true);
            scheduleSave(entityInfo, key, nowCollapsed);
        });

        btn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); header.click(); }
        });
    }

    // ── Re-expand forced blocks after stage change (sections already initialized) ──
    // prevExpandedByStage — list from the previous stage; sections that leave it
    // are restored to the user's saved state.
    function reapplyStageRules(prevExpandedByStage) {
        var prev = prevExpandedByStage || [];
        var keys = Object.keys(sectionMap);
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            var s = sectionMap[key];
            // Clean up references to removed DOM nodes
            if (s.section.parentNode === null) { delete sectionMap[key]; continue; }
            if (expandedByStage.indexOf(key) !== -1
                && s.section.classList.contains('fc-cbc-section--collapsed')) {
                // New stage requires this block expanded — open it
                applyState(s.section, s.body, s.wrap, false, true);
            } else if (prev.indexOf(key) !== -1 && expandedByStage.indexOf(key) === -1) {
                // Was forced open by previous stage rule, no longer required — restore user state
                applyState(s.section, s.body, s.wrap, blockState[key] === true, true);
            }
        }
    }

    // ── Reload expandedByStage when entity is saved (stage may have changed) ──
    function reloadExpandedByStage() {
        clearTimeout(stageReloadTimer);
        stageReloadTimer = setTimeout(function () {
            if (!entityInfo || !stateLoaded) return;
            var current = parseEntityInfo();
            if (!current
                || current.type   !== entityInfo.type
                || current.id     !== entityInfo.id
                || current.typeId !== entityInfo.typeId) return;
            loadState(entityInfo, function (state, ebs) {
                var prevEbs = expandedByStage;
                expandedByStage = ebs;
                reapplyStageRules(prevEbs);
            });
        }, 600);
    }

    // ── Process all sections ──────────────────────────────────────────────────
    function processSections() {
        if (!entityInfo || !isEntityEnabled(entityInfo) || !stateLoaded) return;
        // Guard: if URL changed but navigation handler hasn't fired yet, skip —
        // handleNavigation will reload state and call us again with correct entityInfo.
        var current = parseEntityInfo();
        if (!current
            || current.type   !== entityInfo.type
            || current.id     !== entityInfo.id
            || current.typeId !== entityInfo.typeId) return;
        var sections = document.querySelectorAll(SECTION_SEL);
        for (var i = 0; i < sections.length; i++) initSection(sections[i]);
    }

    // ── MutationObserver (dynamic content / SPA) ──────────────────────────────
    function startObserver() {
        if (observer) observer.disconnect();
        observer = new MutationObserver(function (mutations) {
            if (navigationPending) return;

            var needProcess    = false;
            var needFullReload = false;

            for (var m = 0; m < mutations.length; m++) {
                var mut = mutations[m];

                // Removed sections = entity editor re-rendered after save (stage change etc.)
                var removed = mut.removedNodes;
                for (var r = 0; r < removed.length; r++) {
                    var rn = removed[r];
                    if (rn.nodeType !== 1) continue;
                    if ((rn.matches && rn.matches(SECTION_SEL))
                        || (rn.querySelector && rn.querySelector(SECTION_SEL))) {
                        needFullReload = true;
                        break;
                    }
                }
                if (needFullReload) break;

                if (!stateLoaded) continue;

                // Added sections or body elements
                var added = mut.addedNodes;
                for (var n = 0; n < added.length; n++) {
                    var node = added[n];
                    if (node.nodeType !== 1) continue;
                    if ((node.matches && node.matches(SECTION_SEL))
                        || (node.querySelector && node.querySelector(SECTION_SEL))) {
                        needProcess = true; break;
                    }
                    if (node.matches && node.matches(BODY_SEL)) {
                        var ps = node.closest ? node.closest(SECTION_SEL) : null;
                        if (ps && ps.dataset.fcCbcDone !== '1') { needProcess = true; break; }
                    }
                }
            }

            if (needFullReload && entityInfo && stateLoaded) {
                // Same-entity re-render — reload full state (picks up stage change)
                var cur = parseEntityInfo();
                if (cur && cur.type === entityInfo.type && cur.id === entityInfo.id) {
                    stateLoaded = false;
                    sectionMap  = {};
                    loadState(entityInfo, function (state, ebs) {
                        blockState      = state;
                        expandedByStage = ebs;
                        stateLoaded     = true;
                        processSections();
                    });
                }
            } else if (needProcess) {
                setTimeout(processSections, 80);
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // ── POST-request interception: reload expandedByStage after any CRM save ──
    // Bitrix saves stage changes silently (no DOM events, no section re-render).
    // The only reliable trigger is a successful POST request from the page.
    var _xhrPatched = false;
    function startStageWatcher() {
        if (_xhrPatched) return;
        _xhrPatched = true;
        var ajaxUrl = CONFIG.ajaxUrl;

        // XHR interception
        var _open = XMLHttpRequest.prototype.open;
        var _send = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function (method, url) {
            this._fcMethod = typeof method === 'string' ? method.toUpperCase() : '';
            this._fcUrl    = typeof url    === 'string' ? url    : '';
            return _open.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function () {
            if (this._fcMethod === 'POST' && this._fcUrl.indexOf(ajaxUrl) === -1) {
                var self = this;
                this.addEventListener('load', function () {
                    if (self.status >= 200 && self.status < 300 && entityInfo) {
                        reloadExpandedByStage();
                    }
                });
            }
            return _send.apply(this, arguments);
        };

        // fetch interception
        if (typeof window.fetch === 'function') {
            var _fetch = window.fetch;
            window.fetch = function (input, init) {
                var method = init && init.method ? init.method.toUpperCase() : 'GET';
                var url    = typeof input === 'string' ? input : (input && input.url ? input.url : '');
                var prom   = _fetch.apply(this, arguments);
                if (method === 'POST' && url.indexOf(ajaxUrl) === -1 && entityInfo) {
                    prom.then(function (r) { if (r.ok) reloadExpandedByStage(); }).catch(function () {});
                }
                return prom;
            };
        }
    }

    // ── Navigation re-init ────────────────────────────────────────────────────
    function handleNavigation() {
        navigationPending = false;
        var newInfo = parseEntityInfo();
        if (!newInfo || !isEntityEnabled(newInfo)) { entityInfo = null; stateLoaded = false; return; }
        if (entityInfo
            && entityInfo.type   === newInfo.type
            && entityInfo.id     === newInfo.id
            && entityInfo.typeId === newInfo.typeId) {
            // Same entity — stage may have changed, reload expandedByStage
            loadState(newInfo, function (state, ebs) {
                var prevEbs = expandedByStage;
                expandedByStage = ebs;
                stateLoaded = true;
                processSections();
                reapplyStageRules(prevEbs);
            });
            return;
        }
        entityInfo      = newInfo;
        stateLoaded     = false;
        blockState      = {};
        expandedByStage = [];
        sectionMap      = {};
        loadState(entityInfo, function (state, ebs) {
            blockState        = state;
            expandedByStage   = ebs;
            firstTimeCollapse = computeFirstTime(entityInfo);
            stateLoaded       = true;
            processSections();
        });
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────────
    function init() {
        entityInfo = parseEntityInfo();
        if (entityInfo && isEntityEnabled(entityInfo)) {
            loadState(entityInfo, function (state, ebs) {
                blockState        = state;
                expandedByStage   = ebs;
                firstTimeCollapse = computeFirstTime(entityInfo);
                stateLoaded       = true;
                processSections();
            });
        }

        // Always start observer and register SPA listeners — the initial page may be
        // a list/kanban, and the user will navigate into a CRM entity via a slider.
        startObserver();
        startStageWatcher();

        if (typeof BX !== 'undefined' && BX.addCustomEvent) {
            BX.addCustomEvent('SPA:pushState',  function () { navigationPending = true; setTimeout(handleNavigation, 350); });
            BX.addCustomEvent('onPopState',     function () { navigationPending = true; setTimeout(handleNavigation, 350); });
            // Reload expandedByStage when entity is saved (stage change without page reload)
            BX.addCustomEvent('onCrmEntitySave',            reloadExpandedByStage);
            BX.addCustomEvent('BX.Crm.EntityEditor:onSave', reloadExpandedByStage);
        }
        if (typeof BX !== 'undefined' && BX.Event && BX.Event.EventEmitter) {
            try { BX.Event.EventEmitter.subscribe('BX.Crm.EntityDetails:onEntitySave', reloadExpandedByStage); } catch (e) {}
        }
        if (typeof BX !== 'undefined' && BX.ready) {
            BX.ready(function () { setTimeout(processSections, 200); });
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

}(window, document));
