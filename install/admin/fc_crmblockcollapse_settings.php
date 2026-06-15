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

$saved   = false;
$message = '';

// --- Сохранение: только типы сущностей + смарт-процессы (правила по стадиям — на своей странице) ---
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
        if (empty($_POST['smart_all'])) {
            $rawIds = $_POST['smart_type_ids'] ?? array();
            if (is_array($rawIds)) {
                $smartIds = array_filter(array_map('intval', $rawIds));
            }
        }
        // smart_all отмечен → пустой массив = все смарт-процессы включены.
    }
    Settings::setEnabledSmartProcessTypeIds(array_values($smartIds));

    Settings::setCollapseAllFirstVisit(!empty($_POST['collapse_all_first_visit']));

    $saved   = true;
    $message = Loc::getMessage('FCO_CBC_SET_SAVED');
}

$enabledEntityTypes   = Settings::getEnabledEntityTypes();
$enabledSmartTypeIds  = Settings::getEnabledSmartProcessTypeIds();
$collapseAllFirstVisit = Settings::isCollapseAllFirstVisit();

// Список доступных смарт-процессов для чекбоксов
$smartProcesses = array();
if (Loader::includeModule('crm')) {
    try {
        $result = \Bitrix\Crm\Model\Dynamic\TypeTable::getList(array(
            'select' => array('ID', 'ENTITY_TYPE_ID', 'TITLE'),
            'order'  => array('TITLE' => 'ASC'),
        ));
        while ($row = $result->fetch()) {
            $smartProcesses[] = array(
                'ENTITY_TYPE_ID' => (int)$row['ENTITY_TYPE_ID'],
                'TITLE'          => (string)$row['TITLE'],
            );
        }
    } catch (\Throwable $e) {
        // CRM есть, но смарт-процессы недоступны (старая версия) — список пуст.
    }
}

$moduleVersion = ModuleManager::getVersion('fivecorners.crmblockcollapse');

AdminActiveSection::markCrmBlockCollapse();

$APPLICATION->AddHeadString('<base href="/bitrix/admin/">');
$APPLICATION->SetTitle(Loc::getMessage('FCO_CBC_SET_PAGE_TITLE'));
PageHeader::addStyles($APPLICATION);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

PageHeader::renderOpen($moduleVersion, 'fivecorners.crmblockcollapse', 'settings');
?>
<style>
.fc-cbc-info { background:#f0f7ff; border:1px solid #c0d9f0; border-radius:4px; padding:12px 16px; margin-bottom:16px; font-size:13px; color:#2d4f6e; }
.fc-cbc-info b { display:block; margin-bottom:4px; }
.fc-cbc-save { padding:18px 0 8px; text-align:center; }
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

    <div class="adm-detail-content-wrap" style="max-width:1000px;">

        <!-- Типы сущностей -->
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
                            <label for="<?= $idAttr ?>"><b><?= Loc::getMessage($langKey) ?></b></label>
                        </td>
                        <td>
                            <input type="checkbox" id="<?= $idAttr ?>" name="entity_<?= $type ?>" value="Y"<?= $checked ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Смарт-процессы (видны только когда включён тип «Смарт-процессы») -->
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
                                <input type="checkbox" id="fc_cbc_smart_all" name="smart_all" value="Y"
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
                                    <input type="checkbox" id="fc_cbc_sp_<?= $sp['ENTITY_TYPE_ID'] ?>"
                                           name="smart_type_ids[]" value="<?= (int)$sp['ENTITY_TYPE_ID'] ?>"<?= $checked ?>>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Поведение -->
        <div class="adm-detail-content-item-block">
            <div class="adm-detail-content-item-title"><?= Loc::getMessage('FCO_CBC_SET_SECTION_BEHAVIOR') ?></div>
            <div class="adm-detail-content-item-content">
                <table class="edit-table" width="100%">
                    <tr>
                        <td class="adm-detail-valign-top" width="40%">
                            <label for="fc_cbc_collapse_first"><b><?= Loc::getMessage('FCO_CBC_SET_COLLAPSE_FIRST') ?></b></label>
                        </td>
                        <td>
                            <input type="checkbox" id="fc_cbc_collapse_first" name="collapse_all_first_visit" value="Y"<?= $collapseAllFirstVisit ? ' checked' : '' ?>>
                        </td>
                    </tr>
                </table>
                <p style="font-size:12px;color:#888;margin-top:6px;"><?= htmlspecialcharsbx(Loc::getMessage('FCO_CBC_SET_COLLAPSE_FIRST_HINT')) ?></p>
            </div>
        </div>

        <div class="fc-cbc-save">
            <input type="submit" class="adm-btn-save" value="<?= Loc::getMessage('FCO_CBC_SET_SAVE') ?>">
        </div>

    </div>
</form>

<script>
BX.ready(function() {
    var smartCb   = document.getElementById('fc_cbc_smart_checkbox') || document.querySelector('[name="entity_SMART_PROCESS"]');
    var smartSect = document.getElementById('fc-cbc-smart-section');
    if (smartCb && smartSect) {
        smartCb.addEventListener('change', function() {
            smartSect.style.display = this.checked ? '' : 'none';
        });
    }
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
