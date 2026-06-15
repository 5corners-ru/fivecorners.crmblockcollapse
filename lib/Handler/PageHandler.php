<?php
namespace FiveCorners\CrmBlockCollapse\Handler;

use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
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

        // Migrate legacy settings that stored TypeTable.ID instead of ENTITY_TYPE_ID.
        // After the fix the admin page saves ENTITY_TYPE_ID, but existing portals may
        // still have old TypeTable.IDs.  We resolve them here transparently so the user
        // does not have to re-save settings manually.
        if (!empty($enabledSmTypes) && Loader::includeModule('crm')) {
            try {
                $res = \Bitrix\Crm\Model\Dynamic\TypeTable::getList(array(
                    'select' => array('ID', 'ENTITY_TYPE_ID'),
                ));
                $tableIdToEntityTypeId = array();
                $knownEntityTypeIds    = array();
                while ($row = $res->fetch()) {
                    $tableIdToEntityTypeId[(int)$row['ID']] = (int)$row['ENTITY_TYPE_ID'];
                    $knownEntityTypeIds[]                   = (int)$row['ENTITY_TYPE_ID'];
                }
                $resolved = array();
                foreach ($enabledSmTypes as $storedId) {
                    if (isset($tableIdToEntityTypeId[$storedId])) {
                        $resolved[] = $tableIdToEntityTypeId[$storedId]; // old TypeTable.ID → ENTITY_TYPE_ID
                    } elseif (in_array($storedId, $knownEntityTypeIds, true)) {
                        $resolved[] = $storedId; // already ENTITY_TYPE_ID
                    }
                }
                $enabledSmTypes = $resolved;
            } catch (\Throwable $e) {
                $enabledSmTypes = array(); // fallback: enable all
            }
        }

        $config = array(
            'enabledTypes'          => $enabledTypes,
            'enabledSmTypes'        => array_map('strval', $enabledSmTypes),
            'ajaxUrl'               => '/local/ajax/fivecorners_crmblockcollapse.php',
            'collapseAllFirstVisit' => Settings::isCollapseAllFirstVisit(),
        );
        // JSON_HEX_TAG/AMP/APOS/QUOT — экранируют < > & ' " как \uXXXX, чтобы значение
        // не могло выйти из inline <script> через </script>. JSON_UNESCAPED_SLASHES
        // НЕ ставим — он оставляет "/" сырым и открывает ровно эту дыру (XSS-context).
        $configJson = json_encode(
            $config,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        $APPLICATION->AddHeadString('<style id="fc-cbc-css">' . self::getCSS() . '</style>');
        $APPLICATION->AddHeadString(
            '<script id="fc-cbc-config">window.FC_CBC_CONFIG = ' . $configJson . ';</script>'
        );
        // ?v=<версия> — cache-busting: collapse.js грузится статикой, без версии браузер
        // держал бы старую копию после апдейта модуля и новая логика не доехала бы до юзера.
        $ver = (string)ModuleManager::getVersion(Settings::getModuleId());
        $APPLICATION->AddHeadString(
            '<script src="/local/js/fivecorners.crmblockcollapse/collapse.js?v='
            . rawurlencode($ver) . '" defer></script>'
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
.fc-cbc-section--collapsed .fc-cbc-body-wrap { max-height: 0 !important; overflow: hidden !important; }
';
    }
}
