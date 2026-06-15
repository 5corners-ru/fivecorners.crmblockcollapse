<?php
namespace FiveCorners\CrmBlockCollapse;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

// Канон: раздел «5 УГЛОВ» + per-module иконки через пару OnBuildGlobalMenu + OnProlog.
class AdminMenu
{
    private const SECTION_ID      = 'global_menu_fivecorners';
    private const SECTION_MENU_ID = 'fivecorners';

    // Section-иконка («5 УГЛОВ») встроена data-URI прямо в onProlog (sectionIconUri()),
    // а НЕ грузится файлом с диска. Причина (TD-94): файловая доставка
    // (/local/images/fivecorners/logo.svg через InstallFiles) — best-effort с @-глушением
    // (@mkdir/@copy + guard !is_file), который на ЧИСТОМ клиентском портале молча
    // проваливается (упаковка вырезала install/images / нет прав / guard пропустил при
    // чужом файле) → раздел без иконки. На наших порталах баг маскировался: файл уже лежал
    // от других модулей семьи. data-URI едет В КОДЕ → не зависит от копирования/прав/упаковки.
    // SECTION_ICON_URL оставлен только для InstallFiles backward-compat; onProlog его НЕ юзает.
    private const SECTION_ICON_URL = '/local/images/fivecorners/logo.svg';
    private const MODULE_ICON_URL  = '/local/images/fivecorners.crmblockcollapse/admin_module_icon.png';

    /**
     * Инлайн-артворк SECTION-глифа раздела «5 УГЛОВ» — БЕЛАЯ звезда (общий для всех модулей
     * семьи /local/images/fivecorners/logo.svg, 42×41 SVG, fill=white). Это глиф МЕНЮ: обязан
     * выглядеть как СИСТЕМНЫЕ пункты админ-меню (светлый монохром, НЕ цветной). Красная звезда
     * (admin_module_icon.png) — это per-MODULE иконка контент-области, здесь НЕ она.
     * Вшит data-URI'ем (TD-94): едет в коде → не зависит от копирования файла на чистом
     * портале. base64 SVG безопасен для CSS url() и JS-строки (нет кавычек/скобок).
     */
    private const SECTION_ICON_SVG_B64 = 'PHN2ZyB3aWR0aD0iNDIiIGhlaWdodD0iNDEiIHZpZXdCb3g9IjAgMCA0MiA0MSIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik0xNi44Mjk4IDMzLjcwMUwxNi43NzI0IDMzLjU4ODVMMCAyMy4xOEw1Ljk3MzczIDE4LjkwNDFMMTAuOTEzNSAwTDE2Ljg4NzMgNC4yNzU5MkwzNi44MTg4IDMuMDM4MTVMMzQuNTIxMiA5LjkwMjEzTDQxLjg3MzUgMjguMDc0OEgzNC40NjM4TDM0LjQwNjQgMjguMTMxMUwxOS4wNyA0MC41NjVMMTYuODI5OCAzMy43MDFaTTE2LjgyOTcgMzMuNTMxNFYzMy42NDRMMjguNDMyNSAyOC4wMTc4TDMwLjYxNTIgMjguMDc0SDM0LjQwNjNIMzQuNDYzN0wzNC4yMzM5IDI2LjM4NjFMMzIuNjI1NiAxNS41Mjc2TDM0LjUyMTEgOS45NTc2MkgzNC40NjM3TDM0LjI5MTQgOS45MDEzNkwyMS43Njk1IDcuNzYzNEwxNi44ODcyIDQuMjc1MTVWNC4zMzE0MUwxNS4zOTM3IDcuMDMxOTlMMTAuODU2IDE1LjQ3MTNMNi4yMDMzOSAxOC43OTA4TDUuOTczNjMgMTguOTU5NkwxNC45OTE3IDI4LjAxNzhMMTYuODI5NyAzMy41MzE0WiIgZmlsbD0id2hpdGUiLz4KPC9zdmc+Cg==';

    private const CRM_WIDGET_URL = 'https://portal.5corners.ru/upload/crm/site_button/loader_5_l7a35t.js';

    public static function onBuildGlobalMenu(array &$globalMenu, array &$moduleMenu): void
    {
        if (!Loader::includeModule(Settings::getModuleId())) {
            return;
        }

        // Лейблы подпунктов — в общем admin-lang (FCO_CBC_NAV_*).
        Loc::loadMessages(__DIR__ . '/../install/admin/fc_crmblockcollapse_common.php');

        // Создаём раздел «5 УГЛОВ», если другой модуль семьи ещё не создал.
        if (!isset($globalMenu[self::SECTION_ID])) {
            $globalMenu[self::SECTION_ID] = array(
                'menu_id'  => self::SECTION_MENU_ID,
                'text'     => Loc::getMessage('FCO_CBC_MENU_SECTION'),
                'title'    => Loc::getMessage('FCO_CBC_MENU_SECTION_TITLE'),
                'sort'     => 510,
                'items_id' => self::SECTION_ID,
                'icon'     => 'fco-global-menu-icon',
                'items'    => array(),
            );
        }

        // Зонтик модуля — Bitrix линкует к parent_menu автоматически.
        $moduleMenu[] = array(
            'parent_menu' => self::SECTION_ID,
            'sort'        => 700,
            'text'        => Loc::getMessage('FCO_CBC_MENU_ITEM'),
            'title'       => Loc::getMessage('FCO_CBC_MENU_ITEM_TITLE'),
            'url'         => '/local/admin/fc_crmblockcollapse_settings.php',
            'icon'        => 'fco-cbc-menu-icon',
            'page_icon'   => 'fco-cbc-page-icon',
            'items_id'    => 'menu_fco_cbc',
            'items'       => array(
                array(
                    'parent_menu' => 'menu_fco_cbc',
                    'sort'        => 100,
                    'text'        => Loc::getMessage('FCO_CBC_NAV_SETTINGS'),
                    'url'         => '/local/admin/fc_crmblockcollapse_settings.php',
                    'items_id'    => 'menu_fco_cbc_settings',
                    'items'       => array(),
                ),
                array(
                    'parent_menu' => 'menu_fco_cbc',
                    'sort'        => 110,
                    'text'        => Loc::getMessage('FCO_CBC_NAV_RULES'),
                    'url'         => '/local/admin/fc_crmblockcollapse_rules.php',
                    'items_id'    => 'menu_fco_cbc_rules',
                    'items'       => array(),
                ),
                array(
                    'parent_menu' => 'menu_fco_cbc',
                    'sort'        => 120,
                    'text'        => Loc::getMessage('FCO_CBC_NAV_HELP'),
                    'url'         => '/local/admin/fc_crmblockcollapse_help.php',
                    'items_id'    => 'menu_fco_cbc_help',
                    'items'       => array(),
                ),
            ),
        );
    }

    public static function onProlog(): void
    {
        if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) {
            return;
        }

        global $APPLICATION;

        $docRoot     = rtrim((string)\Bitrix\Main\Application::getDocumentRoot(), '/\\');
        // Section icon — data-URI (TD-94): не зависит от файла на диске, cache-buster не нужен.
        $sectionIcon = self::sectionIconUri();
        // Per-module PNG остаётся файловым с cache-buster'ом ?v=<mtime> (data-URI раздул бы <head>).
        $moduleIcon  = self::MODULE_ICON_URL . self::iconVer($docRoot . self::MODULE_ICON_URL);

        // CSS: section icon через класс .adm-fivecorners (Bitrix генерит class="adm-{menu_id}"),
        // per-module иконки — через явные CSS-классы из moduleMenu.
        // ⚠️ background-position + background-repeat ОБЯЗАТЕЛЬНЫ (TD-94 s104): ядро вешает
        // adm-default на каждый section-анкор → admin.css даёт
        // .adm-default .adm-main-menu-item-icon {background-position: center -1582px} (offset
        // системного спрайта). Перебивая только image+size, 22px-иконка рисуется на -1582px —
        // вне видимой области. Соло-модуль семьи на чистом портале → иконки нет.
        $APPLICATION->AddHeadString('<style id="fc-cbc-admin-icons">
.adm-fivecorners .adm-main-menu-item-icon {
    background-image: url("' . $sectionIcon . '") !important;
    background-size: 22px 22px !important;
    background-repeat: no-repeat !important;
    background-position: center !important;
}
.fco-cbc-menu-icon,
.fco-cbc-page-icon {
    background-image: url(' . $moduleIcon . ') !important;
    background-size: contain !important;
    background-repeat: no-repeat !important;
    background-position: center !important;
}
</style>');

        // JS fallback: Bitrix ставит inline style на section-иконку, !important его не бьёт.
        // Пишем наш URL прямо в inline style. Должно идти после готовности DOM.
        $APPLICATION->AddHeadString('<script>
(function() {
    function setFcoSectionIcon() {
        var icon = document.getElementById("' . self::SECTION_ID . '");
        if (icon) {
            var el = icon.querySelector(".adm-main-menu-item-icon");
            if (el) {
                el.style.backgroundImage    = "url(' . $sectionIcon . ')";
                el.style.backgroundSize     = "22px 22px";
                el.style.backgroundRepeat   = "no-repeat";
                el.style.backgroundPosition = "center";
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

        // CRM-виджет 5 УГЛОВ (чат открытой линии за иконкой-наушники) — только на admin-
        // страницах нашего модуля (по префиксу имени файла).
        $curFile = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (strpos($curFile, 'fc_crmblockcollapse_') === 0) {
            $APPLICATION->AddHeadString(
                '<script>(function(w,d,u){var s=d.createElement("script");s.async=true;' .
                's.src=u+"?"+(Date.now()/60000|0);' .
                'var h=d.getElementsByTagName("script")[0];h.parentNode.insertBefore(s,h);' .
                '})(window,document,"' . self::CRM_WIDGET_URL . '");</script>'
            );
        }
    }

    /**
     * Version-суффикс для URL иконки по mtime обслуживаемого файла.
     * Пустая строка, если файла нет (URL не ломается).
     */
    private static function iconVer(string $absPath): string
    {
        $mt = @filemtime($absPath);
        return $mt ? '?v=' . $mt : '';
    }

    /**
     * data-URI раздела «5 УГЛОВ» для CSS background-image (TD-94).
     * base64 — alphabet безопасен для CSS url() и JS-строки (нет кавычек/скобок).
     */
    private static function sectionIconUri(): string
    {
        return 'data:image/svg+xml;base64,' . self::SECTION_ICON_SVG_B64;
    }
}
