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
    $APPLICATION->AuthForm('');
}

if (!Loader::includeModule('fivecorners.crmblockcollapse')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    die();
}

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
.fc-cbc-stage-id { font-size:10px; color:#bbb; font-family:monospace; }
.fc-cbc-stage-hint { font-size:12px; color:#888; margin-bottom:10px; }
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
                $hasDeal  = !empty($allStages['DEAL']);
                $hasLead  = !empty($allStages['LEAD']);
                $hasSmart = !empty($allStages['SMART_PROCESS']);
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
                <div class="fc-cbc-stage-pane active" id="pane-deal">
                    <table class="fc-cbc-stage-table" width="100%">
                        <?php
                        $lastGroup = null;
                        foreach ($allStages['DEAL'] as $stage):
                            if (($stage['group'] ?? '') !== $lastGroup):
                                $lastGroup = $stage['group'] ?? '';
                        ?>
                        <tr><td colspan="2"><div class="fc-cbc-stage-group"><?= htmlspecialcharsbx($lastGroup) ?></div></td></tr>
                        <?php endif;
                            $stageId  = $stage['id'];
                            $curValue = implode("\n", $currentStageRules['DEAL'][$stageId] ?? array());
                        ?>
                        <tr>
                            <td>
                                <?= htmlspecialcharsbx($stage['name']) ?>
                                <br><span class="fc-cbc-stage-id"><?= htmlspecialcharsbx($stageId) ?></span>
                            </td>
                            <td>
                                <textarea name="stage_rules[DEAL][<?= htmlspecialcharsbx($stageId) ?>]"
                                          rows="2"
                                          placeholder="<?= Loc::getMessage('FCO_CBC_SET_STAGE_PLACEHOLDER') ?>"><?= htmlspecialcharsbx($curValue) ?></textarea>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>

                <?php if ($hasLead): ?>
                <div class="fc-cbc-stage-pane<?= !$hasDeal ? ' active' : '' ?>" id="pane-lead">
                    <table class="fc-cbc-stage-table" width="100%">
                        <?php foreach ($allStages['LEAD'] as $stage):
                            $stageId  = $stage['id'];
                            $curValue = implode("\n", $currentStageRules['LEAD'][$stageId] ?? array());
                        ?>
                        <tr>
                            <td>
                                <?= htmlspecialcharsbx($stage['name']) ?>
                                <br><span class="fc-cbc-stage-id"><?= htmlspecialcharsbx($stageId) ?></span>
                            </td>
                            <td>
                                <textarea name="stage_rules[LEAD][<?= htmlspecialcharsbx($stageId) ?>]"
                                          rows="2"
                                          placeholder="<?= Loc::getMessage('FCO_CBC_SET_STAGE_PLACEHOLDER') ?>"><?= htmlspecialcharsbx($curValue) ?></textarea>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>

                <?php if ($hasSmart): ?>
                <div class="fc-cbc-stage-pane<?= !$hasDeal && !$hasLead ? ' active' : '' ?>" id="pane-smart">
                    <?php foreach ($allStages['SMART_PROCESS'] as $spGroup): ?>
                    <div style="margin-bottom:20px;">
                        <div class="fc-cbc-stage-group" style="font-size:12px;font-weight:bold;color:#525c69;">
                            <?= htmlspecialcharsbx($spGroup['typeTitle']) ?>
                        </div>
                        <table class="fc-cbc-stage-table" width="100%">
                            <?php foreach ($spGroup['stages'] as $stage):
                                $stageId  = $stage['id'];
                                $curValue = implode("\n", $currentStageRules['SMART_PROCESS'][$stageId] ?? array());
                            ?>
                            <tr>
                                <td>
                                    <?= htmlspecialcharsbx($stage['name']) ?>
                                    <br><span class="fc-cbc-stage-id"><?= htmlspecialcharsbx($stageId) ?></span>
                                </td>
                                <td>
                                    <textarea name="stage_rules[SMART_PROCESS][<?= htmlspecialcharsbx($stageId) ?>]"
                                              rows="2"
                                              placeholder="<?= Loc::getMessage('FCO_CBC_SET_STAGE_PLACEHOLDER') ?>"><?= htmlspecialcharsbx($curValue) ?></textarea>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <?php endforeach; ?>
                </div>
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

    // Stage rules tabs
    document.querySelectorAll('.fc-cbc-stage-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.fc-cbc-stage-tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.fc-cbc-stage-pane').forEach(function(p) { p.classList.remove('active'); });
            tab.classList.add('active');
            var pane = document.getElementById(tab.dataset.pane);
            if (pane) pane.classList.add('active');
        });
    });

    // Activate admin menu section
    var el = document.getElementById('global_menu_fivecorners');
    if (el && !el.classList.contains('adm-main-menu-item-active')) {
        BX.adminMenu.GlobalMenuClick('fivecorners');
    }
    var link = document.querySelector('a.adm-submenu-item-name-link[href="/local/admin/fc_crmblockcollapse_settings.php"]');
    if (link) {
        var block = link.closest('.adm-sub-submenu-block');
        if (block) { block.classList.add('adm-submenu-item-active'); }
    }
});
</script>

<?php
PageHeader::renderClose('fivecorners.crmblockcollapse');
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
