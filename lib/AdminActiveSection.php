<?php

namespace FiveCorners\CrmBlockCollapse;

/**
 * Принудительная установка active section'а админ-меню для страниц в /local/admin/.
 *
 * Канон Bitrix `\CAdminMenu::_SetActiveItems` сравнивает `$APPLICATION->GetCurPage()` с
 * URL пункта меню, к которому ПРЕФИКСИТ `/bitrix/admin/`. Файлы наших модулей лежат в
 * `/local/admin/` — после префикса `/bitrix/admin//local/admin/...` совпадение не находится,
 * и Bitrix фолбэчит на `desktop` (Рабочий стол). При загрузке страницы виден визуальный
 * jump подсветки: сначала "Рабочий стол" → потом наш section через JS-fallback.
 *
 * Решение — вызвать `AdminActiveSection::mark(...)` **между** `prolog_admin_before.php`
 * (где Bitrix инициализирует `$adminMenu`) и `prolog_admin_after.php` (где он рендерит
 * меню в HTML). В этот момент мы перетираем `$adminMenu->aActiveSections` нашими ключами,
 * и Bitrix сразу пишет в head корректный `BX.adminMenu.setActiveSection('fivecorners')`.
 *
 * Подсветка конкретного leaf'а всё ещё идёт через JS в AdminMenu::onProlog — это
 * безобидный post-process без переключения section.
 */
class AdminActiveSection
{
    /**
     * Установить active section для текущей admin-страницы.
     *
     * @param string $sectionMenuId    menu_id раздела ("fivecorners" для нашего раздела).
     * @param string $sectionText      Отображаемый текст раздела ("5 УГЛОВ").
     * @param string $umbrellaItemsId  items_id зонтика модуля ("menu_fco_cbc").
     * @param string $umbrellaText     Текст зонтика.
     * @param string $umbrellaUrl      URL зонтика (главная admin-страница модуля).
     */
    public static function mark(
        string $sectionMenuId,
        string $sectionText,
        string $umbrellaItemsId,
        string $umbrellaText,
        string $umbrellaUrl
    ): void {
        global $adminMenu;
        if (!isset($adminMenu) || !($adminMenu instanceof \CAdminMenu)) {
            return;
        }

        $sectionItemsId = 'global_menu_' . $sectionMenuId;
        $adminMenu->aActiveSections = [
            $sectionItemsId => [
                'menu_id'      => $sectionMenuId,
                'items_id'     => $sectionItemsId,
                'page_icon'    => 'default_page_icon',
                'text'         => $sectionText,
                'url'          => null,
                'skip_chain'   => null,
                'help_section' => null,
            ],
            $umbrellaItemsId => [
                'menu_id'      => null,
                'items_id'     => $umbrellaItemsId,
                'page_icon'    => null,
                'text'         => $umbrellaText,
                'url'          => $umbrellaUrl,
                'skip_chain'   => null,
                'help_section' => null,
            ],
        ];
    }

    /**
     * Shortcut для admin-страниц модуля: раздел "5 УГЛОВ" + зонтик «Сворачивание разделов в CRM».
     */
    public static function markCrmBlockCollapse(): void
    {
        self::mark(
            sectionMenuId:   'fivecorners',
            sectionText:     '5 УГЛОВ',
            umbrellaItemsId: 'menu_fco_cbc',
            umbrellaText:    'Сворачивание разделов в CRM',
            umbrellaUrl:     '/local/admin/fc_crmblockcollapse_settings.php'
        );
    }
}
