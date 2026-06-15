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

$moduleVersion = ModuleManager::getVersion('fivecorners.crmblockcollapse');

AdminActiveSection::markCrmBlockCollapse();

$APPLICATION->AddHeadString('<base href="/bitrix/admin/">');
$APPLICATION->SetTitle(Loc::getMessage('FCO_CBC_HELP_PAGE_TITLE'));
PageHeader::addStyles($APPLICATION);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

PageHeader::renderOpen($moduleVersion, 'fivecorners.crmblockcollapse', 'help');

// Блоки справки: заголовок + текст (plain). Текст — наши же lang-строки, не пользовательский ввод.
$blocks = array(
    array('FCO_CBC_HELP_WHAT_TITLE',  'FCO_CBC_HELP_WHAT_TEXT'),
    array('FCO_CBC_HELP_USER_TITLE',  'FCO_CBC_HELP_USER_TEXT'),
    array('FCO_CBC_HELP_ADMIN_TITLE', 'FCO_CBC_HELP_ADMIN_TEXT'),
    array('FCO_CBC_HELP_RULES_TITLE', 'FCO_CBC_HELP_RULES_TEXT'),
    array('FCO_CBC_HELP_TIPS_TITLE',  'FCO_CBC_HELP_TIPS_TEXT'),
);
?>
<style>
.fc-cbc-help {
    max-width:900px;
    background:#fff;
    border:1px solid #e2e8ee;
    border-radius:12px;
    box-shadow:0 1px 3px rgba(0,0,0,.05), 0 10px 30px rgba(0,0,0,.05);
    padding:30px 36px 32px;
    font-size:14px;
    color:#2b3640;
    line-height:1.62;
}
.fc-cbc-help .lead {
    background:#f0f7ff;
    border:1px solid #c0d9f0;
    border-radius:8px;
    padding:14px 18px;
    color:#2d4f6e;
    margin:0 0 6px;
}
.fc-cbc-help h3 {
    font-size:15px;
    margin:26px 0 9px;
    padding-left:12px;
    color:#1f2a33;
    border-left:3px solid #2d7cc7;
    line-height:1.3;
}
.fc-cbc-help p { margin:0 0 10px; }
.fc-cbc-help p:last-child { margin-bottom:0; }
</style>

<div class="fc-cbc-help">
    <p class="lead"><?= htmlspecialcharsbx(Loc::getMessage('FCO_CBC_HELP_LEAD')) ?></p>
    <?php foreach ($blocks as $b): ?>
        <h3><?= htmlspecialcharsbx(Loc::getMessage($b[0])) ?></h3>
        <?php foreach (explode("\n", (string)Loc::getMessage($b[1])) as $line):
            $line = trim($line);
            if ($line === '') continue;
        ?>
        <p><?= htmlspecialcharsbx($line) ?></p>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>

<script>
BX.ready(function() {
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
