<?php
defined('B_PROLOG_INCLUDED') || define('B_PROLOG_INCLUDED', true);
define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', 'Y');
define('NEED_AUTH', true);

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

/** @var CUser $USER */
if (!$USER->IsAdmin()) {
    // AuthForm не останавливает выполнение сам — обязателен die(), иначе авторизованный
    // не-админ дойдёт до рендера настроек.
    $APPLICATION->AuthForm('');
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    die();
}

if (!Loader::includeModule('fivecorners.crmblockcollapse')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    die();
}

use FiveCorners\CrmBlockCollapse\AdminActiveSection;
use FiveCorners\CrmBlockCollapse\PageHeader;
use FiveCorners\CrmBlockCollapse\Settings;
use FiveCorners\CrmBlockCollapse\StageHelper;

$saved   = false;
$message = '';

// --- Handle save ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $enabledTypes = array();
    foreach (array('DEAL', 'LEAD', 'CONTACT', 'COMPANY', 'SMART_PROCESS') as $t) {
        if (!empty($_POST['entity_' . $t])) {
            $enabledTypes[] = $t;
        }
    }
    Settings::setEnabledEntityTypes($enabledTypes);

    $smartIds = array();
    if (in_array('SMART_PROCESS', $enabledTypes, true)) {
        if (!empty($_POST['smart_all'])) {
            // "All smart processes" selected — store empty array (= all enabled)
            $smartIds = array();
        } else {
            $rawIds = $_POST['smart_type_ids'] ?? array();
            if (is_array($rawIds)) {
                $smartIds = array_filter(array_map('intval', $rawIds));
            }
        }
    }
    Settings::setEnabledSmartProcessTypeIds(array_values($smartIds));

    // Stage rules
    $stageRulesRaw = $_POST['stage_rules'] ?? array();
    $stageRules = array();
    if (is_array($stageRulesRaw)) {
        foreach ($stageRulesRaw as $type => $byStage) {
            if (!is_array($byStage)) continue;
            foreach ($byStage as $stageId => $blockNamesRaw) {
                $lines = array_values(array_filter(array_map('trim', explode("\n", (string)$blockNamesRaw))));
                if (!empty($lines)) {
                    $stageRules[$type][$stageId] = $lines;
                }
            }
        }
    }
    Settings::setStageRules($stageRules);

    $saved   = true;
    $message = Loc::getMessage('FCO_CBC_SET_SAVED');
}

// --- Load current settings ---
$enabledEntityTypes  = Settings::getEnabledEntityTypes();
$enabledSmartTypeIds = Settings::getEnabledSmartProcessTypeIds();
$currentStageRules   = Settings::getStageRules();

// --- Load available smart processes and all stages ---
$smartProcesses = array();
$allStages      = array('DEAL' => array(), 'LEAD' => array(), 'SMART_PROCESS' => array());
if (Loader::includeModule('crm')) {
    try {
        $result = \Bitrix\Crm\Model\Dynamic\TypeTable::getList(array(
            'select' => array('ID', 'ENTITY_TYPE_ID', 'TITLE'),
            'order'  => array('TITLE' => 'ASC'),
        ));
        while ($row = $result->fetch()) {
            $smartProcesses[] = array(
                'ID'             => (int)$row['ID'],
                'ENTITY_TYPE_ID' => (int)$row['ENTITY_TYPE_ID'],
                'TITLE'          => (string)$row['TITLE'],
            );
        }
    } catch (\Throwable $e) {
        // CRM module available but smart processes API unavailable (older version)
    }
    $allStages = StageHelper::getAllStages();
}

$moduleVersion = ModuleManager::getVersion('fivecorners.crmblockcollapse');

// Канон: зафиксировать active section до prolog_admin_after, иначе меню «прыгает»
// с «Рабочего стола» на наш раздел при загрузке (файл в /local/admin/).
AdminActiveSection::markCrmBlockCollapse();

$APPLICATION->AddHeadString('<base href="/bitrix/admin/">');
$APPLICATION->SetTitle(Loc::getMessage('FCO_CBC_SET_PAGE_TITLE'));
PageHeader::addStyles($APPLICATION);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

PageHeader::renderOpen($moduleVersion, 'fivecorners.crmblockcollapse');
?>
<style>
.fc-cbc-info { background:#f0f7ff; border:1px solid #c0d9f0; border-radius:4px; padding:12px 16px; margin-bottom:16px; font-size:13px; color:#2d4f6e; }
.fc-cbc-info b { display:block; margin-bottom:4px; }
.fc-cbc-stage-tabs { display:flex; gap:4px; margin-bottom:16px; flex-wrap:wrap; }
.fc-cbc-stage-tab { padding:5px 14px; border:1px solid #c5d1df; border-radius:3px; cursor:pointer; font-size:13px; background:#f5f7fa; color:#3d4d5d; }
.fc-cbc-stage-tab.active { background:#2d7cc7; color:#fff; border-color:#2065aa; }
.fc-cbc-stage-pane { display:none; }
.fc-cbc-stage-pane.active { display:block; }
.fc-cbc-stage-group { font-size:11px; color:#8a9ab0; margin-top:10px; margin-bottom:2px; padding:2px 0; border-bottom:1px solid #edf0f4; }
.fc-cbc-stage-table td { vertical-align:top; padding:5px 6px; border-bottom:1px solid #f0f2f5; }
.fc-cbc-stage-table td:first-child { width:42%; font-size:13px; }
.fc-cbc-stage-table textarea { width:100%; font-size:12px; box-sizing:border-box; padding:4px 6px; border:1px solid #c5d1df; border-radius:3px; resize:vertical; min-height:38px; }
.fc-cbc-stage-table textarea:focus { border-color:#2d7cc7; outline:none; }
.fc-cbc-stage-id { font-size:10px; color:#bbb; font-family:monospace; display:block; margin-top:2px; }
.fc-cbc-stage-hint { font-size:12px; color:#888; margin-bottom:10px; }

/* Мастер-деталь: воронки слева, стадии справа */
.fc-cbc-md-toolbar { display:flex; gap:10px; align-items:center; margin-bottom:12px; flex-wrap:wrap; }
.fc-cbc-md-search { flex:1; min-width:160px; padding:6px 10px; border:1px solid #c5d1df; border-radius:5px; font-size:13px; box-sizing:border-box; }
.fc-cbc-md-search:focus { border-color:#2d7cc7; outline:none; }
.fc-cbc-md-toolbar label { font-size:12px; color:#5a6b7b; display:inline-flex; align-items:center; gap:5px; cursor:pointer; user-select:none; }
.fc-cbc-md-body { display:flex; gap:14px; border:1px solid #e6ecf0; border-radius:8px; overflow:hidden; }
.fc-cbc-md-list { width:240px; flex-shrink:0; background:#f7f9fb; border-right:1px solid #e6ecf0; max-height:460px; overflow-y:auto; }
.fc-cbc-md-fn { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:9px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid #eef2f5; }
.fc-cbc-md-fn:hover { background:#eef4fa; }
.fc-cbc-md-fn.active { background:#2d7cc7; color:#fff; }
.fc-cbc-md-fn.active .fc-cbc-badge { background:rgba(255,255,255,.25)!important; color:#fff!important; }
.fc-cbc-md-detail { flex:1; padding:4px 14px 14px; max-height:460px; overflow-y:auto; }
.fc-cbc-md-detail h4 { margin:10px 0 8px; font-size:14px; color:#2b3640; }
.fc-cbc-md-pane { display:none; }
.fc-cbc-md-pane.active { display:block; }
.fc-cbc-md-single { border:1px solid #e6ecf0; border-radius:8px; padding:6px 14px 14px; }
.fc-cbc-badge { display:inline-block; min-width:34px; text-align:center; font-size:11px; font-weight:700; border-radius:10px; padding:1px 7px; flex-shrink:0; }
.fc-cbc-badge.has { background:#e3f3ea; color:#1e9e54; }
.fc-cbc-badge.none { background:#eef1f4; color:#aab4be; }
.fc-cbc-strow { display:grid; grid-template-columns:210px 1fr; gap:10px; align-items:start; padding:7px 0; border-bottom:1px solid #f1f4f7; }
.fc-cbc-strow .stname { font-size:13px; }
.fc-cbc-rule { width:100%; font-size:12px; box-sizing:border-box; padding:4px 6px; border:1px solid #c5d1df; border-radius:3px; resize:vertical; min-height:38px; font-family:inherit; }
.fc-cbc-rule:focus { border-color:#2d7cc7; outline:none; }
.fc-cbc-rule.filled { border-color:#9cc5a8; background:#f6fbf7; }
</style>

<?php if ($saved): ?>
<div class="adm-info-message-wrap adm-info-message-green" style="margin-bottom:16px;">
    <div class="adm-info-message">
        <div class="adm-info-message-body"><?= htmlspecialcharsbx($message) ?></div>
    </div>
</div>
<?php endif; ?>

<div class="fc-cbc-info">
    <b><?= Loc::getMessage('FCO_CBC_SET_HOW_TITLE') ?></b>
    <?= htmlspecialcharsbx(Loc::getMessage('FCO_CBC_SET_HOW_TEXT')) ?>
</div>

<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>?lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>

    <div class="adm-detail-content-wrap" style="max-width:760px;">

        <!-- Entity Types -->
        <div class="adm-detail-content-item-block">
            <div class="adm-detail-content-item-title"><?= Loc::getMessage('FCO_CBC_SET_SECTION_ENTITIES') ?></div>
            <div class="adm-detail-content-item-content">
                <table class="edit-table" width="100%">
                    <?php
                    $entityLabels = array(
                        'DEAL'          => 'FCO_CBC_SET_ENTITY_DEAL',
                        'LEAD'          => 'FCO_CBC_SET_ENTITY_LEAD',
                        'CONTACT'       => 'FCO_CBC_SET_ENTITY_CONTACT',
                        'COMPANY'       => 'FCO_CBC_SET_ENTITY_COMPANY',
                        'SMART_PROCESS' => 'FCO_CBC_SET_ENTITY_SMART',
                    );
                    foreach ($entityLabels as $type => $langKey):
                        $checked = in_array($type, $enabledEntityTypes, true) ? ' checked' : '';
                        $idAttr  = ($type === 'SMART_PROCESS')
                            ? 'fc_cbc_smart_checkbox'
                            : 'fc_cbc_entity_' . strtolower($type);
                    ?>
                    <tr>
                        <td class="adm-detail-valign-top" width="40%">
                            <label for="<?= $idAttr ?>">
                                <b><?= Loc::getMessage($langKey) ?></b>
                            </label>
                        </td>
                        <td>
                            <input type="checkbox"
                                   id="<?= $idAttr ?>"
                                   name="entity_<?= $type ?>"
                                   value="Y"
                                   <?= $checked ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Smart Processes section (shown only when SMART_PROCESS is enabled) -->
        <div class="adm-detail-content-item-block" id="fc-cbc-smart-section"
             style="<?= !in_array('SMART_PROCESS', $enabledEntityTypes, true) ? 'display:none;' : '' ?>">
            <div class="adm-detail-content-item-title"><?= Loc::getMessage('FCO_CBC_SET_SECTION_SMART') ?></div>
            <div class="adm-detail-content-item-content">
                <?php if (empty($smartProcesses)): ?>
                    <p style="color:#888;"><?= Loc::getMessage('FCO_CBC_SET_SMART_NONE') ?></p>
                <?php else: ?>
                    <table class="edit-table" width="100%">
                        <tr>
                            <td width="40%"><b><?= Loc::getMessage('FCO_CBC_SET_SMART_ALL') ?></b></td>
                            <td>
                                <input type="checkbox" id="fc_cbc_smart_all"
                                       name="smart_all"
                                       value="Y"
                                       <?= empty($enabledSmartTypeIds) ? ' checked' : '' ?>
                                       onchange="document.getElementById('fc-cbc-smart-list').style.display=this.checked?'none':'';">
                            </td>
                        </tr>
                    </table>
                    <div id="fc-cbc-smart-list" style="margin-top:10px;<?= empty($enabledSmartTypeIds) ? 'display:none;' : '' ?>">
                        <p style="font-size:12px;color:#888;margin-bottom:6px;"><?= Loc::getMessage('FCO_CBC_SET_SMART_SELECT') ?></p>
                        <table class="edit-table" width="100%">
                            <?php foreach ($smartProcesses as $sp):
                                $checked = empty($enabledSmartTypeIds)
                                    || in_array($sp['ENTITY_TYPE_ID'], $enabledSmartTypeIds, true)
                                    ? ' checked' : '';
                            ?>
                            <tr>
                                <td width="40%"><label for="fc_cbc_sp_<?= $sp['ENTITY_TYPE_ID'] ?>"><?= htmlspecialcharsbx($sp['TITLE']) ?></label></td>
                                <td>
                                    <input type="checkbox"
                                           id="fc_cbc_sp_<?= $sp['ENTITY_TYPE_ID'] ?>"
                                           name="smart_type_ids[]"
                                           value="<?= (int)$sp['ENTITY_TYPE_ID'] ?>"
                                           <?= $checked ?>>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stage Rules -->
        <div class="adm-detail-content-item-block">
            <div class="adm-detail-content-item-title"><?= Loc::getMessage('FCO_CBC_SET_STAGE_RULES_TITLE') ?></div>
            <div class="adm-detail-content-item-content">
                <p class="fc-cbc-stage-hint">
                    <?= htmlspecialcharsbx(Loc::getMessage('FCO_CBC_SET_STAGE_RULES_HINT1')) ?><br>
                    <?= htmlspecialcharsbx(Loc::getMessage('FCO_CBC_SET_STAGE_RULES_HINT2')) ?><br>
                    <?= htmlspecialcharsbx(Loc::getMessage('FCO_CBC_SET_STAGE_RULES_HINT3')) ?>
                </p>

                <?php
                // Привести все сущности к единой структуре «воронки → стадии»:
                //   DEAL  — сгруппировать плоский список по 'group' (имя воронки);
                //   LEAD  — одна воронка; SMART — каждый тип смарт-процесса = воронка.
                $funnelsByEntity = array('DEAL' => array(), 'LEAD' => array(), 'SMART_PROCESS' => array());
                $dealMap = array();
                foreach ($allStages['DEAL'] as $st) {
                    $g = (string)($st['group'] ?? '');
                    if (!isset($dealMap[$g])) {
                        $dealMap[$g] = count($funnelsByEntity['DEAL']);
                        $funnelsByEntity['DEAL'][] = array('name' => $g, 'stages' => array());
                    }
                    $funnelsByEntity['DEAL'][$dealMap[$g]]['stages'][] = $st;
                }
                if (!empty($allStages['LEAD'])) {
                    $funnelsByEntity['LEAD'][] = array('name' => '', 'stages' => $allStages['LEAD']);
                }
                foreach ($allStages['SMART_PROCESS'] as $g) {
                    $funnelsByEntity['SMART_PROCESS'][] = array('name' => $g['typeTitle'], 'stages' => $g['stages']);
                }

                $hasDeal  = !empty($funnelsByEntity['DEAL']);
                $hasLead  = !empty($funnelsByEntity['LEAD']);
                $hasSmart = !empty($funnelsByEntity['SMART_PROCESS']);

                // Мастер-деталь: список воронок слева (с бейджами/поиском) + стадии выбранной справа.
                // Все textarea рендерятся сразу (скрыты CSS) — форма отправляет все правила разом.
                $renderMD = function($entity, $funnels) use ($currentStageRules) {
                    $rules = $currentStageRules[$entity] ?? array();
                    $ph    = Loc::getMessage('FCO_CBC_SET_STAGE_PLACEHOLDER');

                    $renderStages = function($funnel) use ($entity, $rules, $ph) {
                        $out = '';
                        foreach ($funnel['stages'] as $st) {
                            $sid    = $st['id'];
                            $val    = implode("\n", $rules[$sid] ?? array());
                            $filled = trim($val) !== '' ? ' filled' : '';
                            $out .= '<div class="fc-cbc-strow"><div class="stname">'
                                  . htmlspecialcharsbx($st['name'])
                                  . '<span class="fc-cbc-stage-id">' . htmlspecialcharsbx($sid) . '</span></div>'
                                  . '<div><textarea class="fc-cbc-rule' . $filled . '" rows="2" name="stage_rules['
                                  . $entity . '][' . htmlspecialcharsbx($sid) . ']" placeholder="'
                                  . htmlspecialcharsbx($ph) . '">' . htmlspecialcharsbx($val) . '</textarea></div></div>';
                        }
                        return $out;
                    };

                    // Одна воронка (Лиды) — без левого списка, просто стадии.
                    if (count($funnels) === 1) {
                        return '<div class="fc-cbc-md-single">' . $renderStages($funnels[0]) . '</div>';
                    }

                    $html = '<div class="fc-cbc-md"><div class="fc-cbc-md-toolbar">'
                          . '<input type="text" class="fc-cbc-md-search" placeholder="'
                          . htmlspecialcharsbx(Loc::getMessage('FCO_CBC_SET_STAGE_SEARCH')) . '">'
                          . '<label><input type="checkbox" class="fc-cbc-md-onlycfg"> '
                          . htmlspecialcharsbx(Loc::getMessage('FCO_CBC_SET_STAGE_ONLYCFG')) . '</label>'
                          . '</div><div class="fc-cbc-md-body"><div class="fc-cbc-md-list">';
                    $panes = '';
                    foreach ($funnels as $i => $funnel) {
                        $total  = count($funnel['stages']);
                        $cnt    = 0;
                        $search = mb_strtolower($funnel['name'], 'UTF-8');
                        foreach ($funnel['stages'] as $st) {
                            if (trim(implode('', $rules[$st['id']] ?? array())) !== '') $cnt++;
                            $search .= ' ' . mb_strtolower($st['name'], 'UTF-8');
                        }
                        $paneId = 'md-' . $entity . '-' . $i;
                        $active = $i === 0 ? ' active' : '';
                        $badge  = $cnt > 0 ? 'has' : 'none';
                        $html  .= '<div class="fc-cbc-md-fn' . $active . '" data-target="' . $paneId
                                . '" data-rules="' . $cnt . '" data-search="' . htmlspecialcharsbx($search) . '">'
                                . '<span>' . htmlspecialcharsbx($funnel['name']) . '</span>'
                                . '<span class="fc-cbc-badge ' . $badge . '">' . $cnt . '/' . $total . '</span></div>';
                        $panes .= '<div class="fc-cbc-md-pane' . $active . '" id="' . $paneId . '"><h4>'
                                . htmlspecialcharsbx($funnel['name']) . '</h4>' . $renderStages($funnel) . '</div>';
                    }
                    return $html . '</div><div class="fc-cbc-md-detail">' . $panes . '</div></div></div>';
                };

                if (!$hasDeal && !$hasLead && !$hasSmart):
                ?>
                    <p style="color:#888;"><?= htmlspecialcharsbx(Loc::getMessage('FCO_CBC_SET_STAGE_NONE')) ?></p>
                <?php else: ?>

                <div class="fc-cbc-stage-tabs">
                    <?php if ($hasDeal): ?><span class="fc-cbc-stage-tab active" data-pane="pane-deal"><?= Loc::getMessage('FCO_CBC_SET_ENTITY_DEAL') ?></span><?php endif; ?>
                    <?php if ($hasLead): ?><span class="fc-cbc-stage-tab<?= !$hasDeal ? ' active' : '' ?>" data-pane="pane-lead"><?= Loc::getMessage('FCO_CBC_SET_ENTITY_LEAD') ?></span><?php endif; ?>
                    <?php if ($hasSmart): ?><span class="fc-cbc-stage-tab<?= !$hasDeal && !$hasLead ? ' active' : '' ?>" data-pane="pane-smart"><?= Loc::getMessage('FCO_CBC_SET_ENTITY_SMART') ?></span><?php endif; ?>
                </div>

                <?php if ($hasDeal): ?>
                <div class="fc-cbc-stage-pane active" id="pane-deal"><?= $renderMD('DEAL', $funnelsByEntity['DEAL']) ?></div>
                <?php endif; ?>

                <?php if ($hasLead): ?>
                <div class="fc-cbc-stage-pane<?= !$hasDeal ? ' active' : '' ?>" id="pane-lead"><?= $renderMD('LEAD', $funnelsByEntity['LEAD']) ?></div>
                <?php endif; ?>

                <?php if ($hasSmart): ?>
                <div class="fc-cbc-stage-pane<?= !$hasDeal && !$hasLead ? ' active' : '' ?>" id="pane-smart"><?= $renderMD('SMART_PROCESS', $funnelsByEntity['SMART_PROCESS']) ?></div>
                <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>

        <!-- Save button -->
        <div style="padding:16px 0 8px;">
            <input type="submit" class="adm-btn-save" value="<?= Loc::getMessage('FCO_CBC_SET_SAVE') ?>">
        </div>

    </div>
</form>

<script>
BX.ready(function() {
    // Toggle smart-process section visibility
    var smartCb   = document.getElementById('fc_cbc_smart_checkbox') || document.querySelector('[name="entity_SMART_PROCESS"]');
    var smartSect = document.getElementById('fc-cbc-smart-section');
    if (smartCb && smartSect) {
        smartCb.addEventListener('change', function() {
            smartSect.style.display = this.checked ? '' : 'none';
        });
    }

    // Stage rules tabs (Сделки / Лиды / Смарт)
    document.querySelectorAll('.fc-cbc-stage-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.fc-cbc-stage-tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.fc-cbc-stage-pane').forEach(function(p) { p.classList.remove('active'); });
            tab.classList.add('active');
            var pane = document.getElementById(tab.dataset.pane);
            if (pane) pane.classList.add('active');
        });
    });

    // Мастер-деталь: переключение воронок + поиск + «только с правилами»
    document.querySelectorAll('.fc-cbc-md').forEach(function(md) {
        var fns     = Array.prototype.slice.call(md.querySelectorAll('.fc-cbc-md-fn'));
        var search  = md.querySelector('.fc-cbc-md-search');
        var onlycfg = md.querySelector('.fc-cbc-md-onlycfg');

        function activate(fn) {
            fns.forEach(function(f) { f.classList.remove('active'); });
            md.querySelectorAll('.fc-cbc-md-pane').forEach(function(p) { p.classList.remove('active'); });
            fn.classList.add('active');
            var pane = document.getElementById(fn.dataset.target);
            if (pane) pane.classList.add('active');
        }
        fns.forEach(function(fn) { fn.addEventListener('click', function() { activate(fn); }); });

        function applyFilter() {
            var q    = (search && search.value ? search.value : '').toLowerCase().trim();
            var only = onlycfg && onlycfg.checked;
            var firstVisible = null, activeVisible = false;
            fns.forEach(function(fn) {
                var show = true;
                if (only && fn.dataset.rules === '0') show = false;
                if (show && q && (fn.dataset.search || '').indexOf(q) === -1) show = false;
                fn.style.display = show ? '' : 'none';
                if (show) { if (!firstVisible) firstVisible = fn; if (fn.classList.contains('active')) activeVisible = true; }
            });
            if (!activeVisible && firstVisible) activate(firstVisible);
        }
        if (search)  search.addEventListener('input', applyFilter);
        if (onlycfg) onlycfg.addEventListener('change', applyFilter);
    });

    // Подсветка заполненных полей + живой пересчёт бейджа воронки
    document.querySelectorAll('.fc-cbc-rule').forEach(function(t) {
        t.addEventListener('input', function() {
            t.classList.toggle('filled', !!t.value.trim());
            var pane = t.closest('.fc-cbc-md-pane');
            var mdEl = t.closest('.fc-cbc-md');
            if (!pane || !mdEl) return;
            var all    = pane.querySelectorAll('.fc-cbc-rule');
            var filled = Array.prototype.filter.call(all, function(x) { return x.value.trim(); }).length;
            var btn = mdEl.querySelector('.fc-cbc-md-fn[data-target="' + pane.id + '"]');
            if (btn) {
                btn.dataset.rules = String(filled);
                var b = btn.querySelector('.fc-cbc-badge');
                if (b) { b.textContent = filled + '/' + all.length; b.className = 'fc-cbc-badge ' + (filled > 0 ? 'has' : 'none'); }
            }
        });
    });

    // Подсветка раздела меню — на сервере через AdminActiveSection::markCrmBlockCollapse()
    // (до prolog_admin_after). Ниже — лишь belt-and-suspenders JS-fallback на случай, если
    // на каком-то билде $adminMenu не успел инициализироваться: раскрыть раздел, если ядро
    // его не подсветило само.
    var sec = document.getElementById('global_menu_fivecorners');
    if (sec && !sec.classList.contains('adm-main-menu-item-active')
        && window.BX && BX.adminMenu && BX.adminMenu.GlobalMenuClick) {
        BX.adminMenu.GlobalMenuClick('fivecorners');
    }
});
</script>

<?php
PageHeader::renderClose('fivecorners.crmblockcollapse');
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
