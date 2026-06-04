<?php
namespace FiveCorners\CrmBlockCollapse;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class AdminMenu
{
    public static function onBuildGlobalMenu(array &$globalMenu, array &$moduleMenu): void
    {
        if (!Loader::includeModule(Settings::getModuleId())) {
            return;
        }

        // Create the fivecorners section if no other module has done it yet
        if (!isset($globalMenu['global_menu_fivecorners'])) {
            $globalMenu['global_menu_fivecorners'] = array(
                'menu_id'  => 'fivecorners',
                'text'     => Loc::getMessage('FCO_CBC_MENU_SECTION'),
                'title'    => Loc::getMessage('FCO_CBC_MENU_SECTION_TITLE'),
                'sort'     => 510,
                'items_id' => 'global_menu_fivecorners',
                'icon'     => 'fco-global-menu-icon',
                'items'    => array(),
            );
        }

        // Add our item to the module menu — Bitrix links it to parent_menu automatically
        $moduleMenu[] = array(
            'parent_menu' => 'global_menu_fivecorners',
            'sort'        => 700,
            'text'        => Loc::getMessage('FCO_CBC_MENU_ITEM'),
            'title'       => Loc::getMessage('FCO_CBC_MENU_ITEM_TITLE'),
            'url'         => '/local/admin/fc_crmblockcollapse_settings.php',
            'icon'        => 'fco-cbc-menu-icon',
            'page_icon'   => 'fco-cbc-page-icon',
            'items_id'    => 'menu_fco_cbc',
            'items'       => array(),
        );
    }

    private const CRM_WIDGET_URL = 'https://portal.5corners.ru/upload/crm/site_button/loader_5_l7a35t.js';

    public static function onProlog(): void
    {
        if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) {
            return;
        }

        global $APPLICATION;

        // CRM-виджет 5 УГЛОВ — только на admin-страницах нашего модуля.
        $curFile = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (strpos($curFile, 'fc_crmblockcollapse_') === 0) {
            $APPLICATION->AddHeadString(
                '<script>(function(w,d,u){var s=d.createElement("script");s.async=true;' .
                's.src=u+"?"+(Date.now()/60000|0);' .
                'var h=d.getElementsByTagName("script")[0];h.parentNode.insertBefore(s,h);' .
                '})(window,document,"' . self::CRM_WIDGET_URL . '");</script>'
            );
        }

        $APPLICATION->AddHeadString('<style id="fc-cbc-admin-icons">
.adm-fivecorners .adm-main-menu-item-icon {
    background-image: url(/local/images/fivecorners/logo.svg) !important;
    background-size: 22px 22px !important;
}
.fco-cbc-menu-icon,
.fco-cbc-page-icon {
    background-image: url(/local/images/fivecorners.crmblockcollapse/admin_icon.png) !important;
    background-size: contain !important;
    background-repeat: no-repeat !important;
    background-position: center !important;
}
</style>');

        // JS fallback: Bitrix sets inline style on section icon — CSS !important alone doesn't override it.
        // Must run after DOM is ready (script is in <head>, menu is in <body>).
        $APPLICATION->AddHeadString('<script>
(function() {
    function setFcoSectionIcon() {
        var icon = document.getElementById("global_menu_fivecorners");
        if (icon) {
            var el = icon.querySelector(".adm-main-menu-item-icon");
            if (el) {
                el.style.backgroundImage = "url(/local/images/fivecorners/logo.svg)";
                el.style.backgroundSize  = "22px 22px";
            }
        }
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", setFcoSectionIcon);
    } else {
        setFcoSectionIcon();
    }
})();
</script>');
    }
}
