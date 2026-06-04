<?php
namespace FiveCorners\CrmBlockCollapse\Handler;

use Bitrix\Main\Loader;
use FiveCorners\CrmBlockCollapse\Settings;

class PageHandler
{
    public static function onProlog(): void
    {
        global $APPLICATION;

        // Skip AJAX requests and admin section — inject only on full portal page loads.
        // URL check intentionally omitted: Bitrix24 SPA loads pages via AJAX after the
        // initial HTML, so the JS must be present in the layout from the very first load.
        // Entity detection is handled entirely client-side inside collapse.js.
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || (defined('BX_AJAX_REQUEST') && BX_AJAX_REQUEST)
            || (defined('ADMIN_SECTION') && ADMIN_SECTION === true))
        {
            return;
        }

        // Must be authorized
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return;
        }

        if (!Loader::includeModule(Settings::getModuleId())) {
            return;
        }

        $enabledTypes   = Settings::getEnabledEntityTypes();
        $enabledSmTypes = Settings::getEnabledSmartProcessTypeIds();

        if (empty($enabledTypes)) {
            return;
        }

        $config = array(
            'enabledTypes'   => $enabledTypes,
            'enabledSmTypes' => array_map('strval', $enabledSmTypes),
            'ajaxUrl'        => '/local/ajax/fivecorners_crmblockcollapse.php',
        );
        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $APPLICATION->AddHeadString('<style id="fc-cbc-css">' . self::getCSS() . '</style>');
        $APPLICATION->AddHeadString(
            '<script id="fc-cbc-config">window.FC_CBC_CONFIG = ' . $configJson . ';</script>'
        );
        $APPLICATION->AddHeadString(
            '<script src="/local/js/fivecorners.crmblockcollapse/collapse.js" defer></script>'
        );
    }

    private static function getCSS(): string
    {
        return '
.fc-cbc-toggle-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    margin-left: 6px;
    cursor: pointer;
    border-radius: 3px;
    opacity: 0.5;
    transition: opacity 0.15s, transform 0.25s ease;
    vertical-align: middle;
    flex-shrink: 0;
    user-select: none;
}
.fc-cbc-toggle-btn:hover { opacity: 0.9; }
.fc-cbc-toggle-btn svg { width: 10px; height: 10px; fill: currentColor; }
.fc-cbc-section--collapsed .fc-cbc-toggle-btn { transform: rotate(-90deg); }
.ui-entity-editor-section-header { cursor: pointer; user-select: none; }
.fc-cbc-body-wrap { overflow: hidden; transition: max-height 0.28s ease-out; }
';
    }
}
