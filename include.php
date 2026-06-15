<?php
defined("B_PROLOG_INCLUDED") || die;

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('fivecorners.crmblockcollapse', array(
    'FiveCorners\CrmBlockCollapse\AdminActiveSection'  => 'lib/AdminActiveSection.php',
    'FiveCorners\CrmBlockCollapse\AdminMenu'           => 'lib/AdminMenu.php',
    'FiveCorners\CrmBlockCollapse\PageHeader'          => 'lib/PageHeader.php',
    'FiveCorners\CrmBlockCollapse\Settings'            => 'lib/Settings.php',
    'FiveCorners\CrmBlockCollapse\StageHelper'         => 'lib/StageHelper.php',
    'FiveCorners\CrmBlockCollapse\Handler\PageHandler' => 'lib/Handler/PageHandler.php',
));
?>
