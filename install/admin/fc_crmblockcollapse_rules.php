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
Loc::loadMessages(__DIR__ . '/fc_crmblockcollapse_common.php');

/** @var CUser $USER */
if (!$USER->IsAdmin()) {
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

// --- Сохранение правил по стадиям ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
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

$currentStageRules = Settings::getStageRules();

$allStages = array('DEAL' => array(), 'LEAD' => array(), 'SMART_PROCESS' => array());
if (Loader::includeModule('crm')) {
    $allStages = StageHelper::getAllStages();
}

$moduleVersion = ModuleManager::getVersion('fivecorners.crmblockcollapse');

AdminActiveSection::markCrmBlockCollapse();

$APPLICATION->AddHeadString('<base href="/bitrix/admin/">');
$APPLICATION->SetTitle(Loc::getMessage('FCO_CBC_RULES_PAGE_TITLE'));
PageHeader::addStyles($APPLICATION);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

PageHeader::renderOpen($moduleVersion, 'fivecorners.crmblockcollapse', 'rules');
?>
<style>
.fc-cbc-stage-tabs { display:flex; gap:4px; margin-bottom:16px; flex-wrap:wrap; }
.fc-cbc-stage-tab { padding:5px 14px; border:1px solid #c5d1df; border-radius:3px; cursor:pointer; font-size:13px; background:#f5f7fa; color:#3d4d5d; }
.fc-cbc-stage-tab.active { background:#2d7cc7; color:#fff; border-color:#2065aa; }
.fc-cbc-stage-pane { display:none; }
.fc-cbc-stage-pane.active { display:block; }
.fc-cbc-stage-id { font-size:10px; color:#bbb; font-family:monospace; display:block; margin-top:2px; }
.fc-cbc-stage-hint { font-size:12px; color:#888; margin-bottom:14px; }
.fc-cbc-save { padding:18px 0 8px; text-align:center; }

/* Мастер-деталь: воронки слева, стадии справа */
.fc-cbc-md-toolbar { display:flex; gap:10px; align-items:center; margin-bottom:12px; flex-wrap:wrap; }
.fc-cbc-md-search { flex:1; min-width:200px; padding:7px 10px; border:1px solid #c5d1df; border-radius:5px; font-size:13px; box-sizing:border-box; }
.fc-cbc-md-search:focus { border-color:#2d7cc7; outline:none; }
.fc-cbc-md-toolbar label { font-size:12px; color:#5a6b7b; display:inline-flex; align-items:center; gap:5px; cursor:pointer; user-select:none; }
.fc-cbc-md-body { display:flex; gap:14px; border:1px solid #e6ecf0; border-radius:8px; overflow:hidden; }
.fc-cbc-md-list { width:270px; flex-shrink:0; background:#f7f9fb; border-right:1px solid #e6ecf0; max-height:520px; overflow-y:auto; }
.fc-cbc-md-fn { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:10px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid #eef2f5; }
.fc-cbc-md-fn:hover { background:#eef4fa; }
.fc-cbc-md-fn.active { background:#2d7cc7; color:#fff; }
.fc-cbc-md-fn.active .fc-cbc-badge { background:rgba(255,255,255,.25)!important; color:#fff!important; }
.fc-cbc-md-detail { flex:1; padding:4px 16px 16px; max-height:520px; overflow-y:auto; }
.fc-cbc-md-detail h4 { margin:10px 0 10px; font-size:14px; color:#2b3640; }
.fc-cbc-md-pane { display:none; }
.fc-cbc-md-pane.active { display:block; }
.fc-cbc-md-single { border:1px solid #e6ecf0; border-radius:8px; padding:6px 16px 16px; }
.fc-cbc-badge { display:inline-block; min-width:42px; text-align:center; font-size:11px; font-weight:700; border-radius:10px; padding:2px 8px; flex-shrink:0; }
.fc-cbc-badge.has { background:#e3f3ea; color:#1e9e54; }
.fc-cbc-badge.none { background:#eef1f4; color:#aab4be; }
.fc-cbc-strow { display:grid; grid-template-columns:minmax(220px,1fr) 2fr; gap:14px; align-items:start; padding:8px 0; border-bottom:1px solid #f1f4f7; }
.fc-cbc-strow .stname { font-size:13px; }
.fc-cbc-rule { width:100%; font-size:12px; box-sizing:border-box; padding:5px 7px; border:1px solid #c5d1df; border-radius:3px; resize:vertical; min-height:38px; font-family:inherit; }
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

<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>?lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>

    <div class="adm-detail-content-wrap" style="max-width:1280px;">
        <div class="adm-detail-content-item-block">
            <div class="adm-detail-content-item-title"><?= Loc::getMessage('FCO_CBC_SET_STAGE_RULES_TITLE') ?></div>
            <div class="adm-detail-content-item-content">
                <p class="fc-cbc-stage-hint">
                    <?= htmlspecialcharsbx(Loc::getMessage('FCO_CBC_SET_STAGE_RULES_HINT1')) ?><br>
                    <?= htmlspecialcharsbx(Loc::getMessage('FCO_CBC_SET_STAGE_RULES_HINT2')) ?><br>
                    <?= htmlspecialcharsbx(Loc::getMessage('FCO_CBC_SET_STAGE_RULES_HINT3')) ?>
                </p>

                <?php
                // Привести сущности к единой структуре «воронки → стадии»:
                //   DEAL — сгруппировать плоский список по 'group'; LEAD — одна воронка;
                //   SMART — каждый тип смарт-процесса = воронка.
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

                // Мастер-деталь: список воронок слева (бейджи/поиск) + стадии выбранной справа.
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

        <div class="fc-cbc-save">
            <input type="submit" class="adm-btn-save" value="<?= Loc::getMessage('FCO_CBC_SET_SAVE') ?>">
        </div>
    </div>
</form>

<script>
BX.ready(function() {
    // Вкладки сущностей (Сделки / Лиды / Смарт)
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
