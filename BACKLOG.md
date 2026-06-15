# BACKLOG

Технический долг и задачи модуля. `TD-N` — сквозная нумерация.

## Запланировано

- **TD-3. Самохостинг шрифта Open Sans (условный, не делаем сейчас).** `PageHeader::addStyles()`
  грузит Google Fonts извне — **как и эталон `DemoPageHelper`**, поэтому remote-шрифт сейчас
  канон-консистентен. Самохостинг увёл бы модуль в расхождение с эталоном ради проблемы,
  которой пока нет. **Триггер пересмотра:** запрос клиента на air-gap-совместимость ИЛИ
  публикация в Marketplace (там и remote-JS виджет «наушники» придётся убирать, см.
  `docs/CUSTOM_UI_REGISTER.md §2/§3`). Решение 2026-06-15 (Pavel): оставить, закрыть как условный.

## В работе

—

## Сделано

- **TD-1. Валидация `smart_type_id` (v1.0.7, 2026-06-15).** `StageHelper::isValidSmartTypeId()`
  (кэш `TypeTable`) — ajax `save/load` для смарт-процессов отбивает несуществующие типы,
  не плодит мусорные ключи в `b_user_option`. (SEC-6.)
- **TD-2. Явный `isStagesEnabled()` guard (v1.0.7).** `StageHelper::getEntityStageId()` для
  смарт-процессов проверяет наличие воронки перед `getStageId()` вместо опоры на `try/catch`.
- **TD-4. Логирование отказов авторизации (v1.0.7).** Анонимное обращение к ajax-эндпоинту
  пишется в `CEventLog` (severity SECURITY, `FCO_CBC_UNAUTHORIZED_AJAX`). (SEC-11.)
- **TD-5. Имя иконки `admin_module_icon.png` — корректно (закрыт 2026-06-15).** Сверено с
  эталоном `fivecorners.admintemplate` (`MODULE_ICON_URL = .../admin_module_icon.png`): модуль
  следует актуальному канону Phase 7.2. Замечание `bitrix-module-reviewer` — ложное, т.к.
  team-skill `bitrix-module-rules` Правило 13 **устарел** (всё ещё на до-7.2 `admin_icon.png`).
  Фикс не в модуле, а в скилле → отдельный PR в KB (правка Правила 13 на Phase 7.2 naming).
- **Канон-миграция → admintemplate (v1.0.1, 2026-06-15).** AdminActiveSection, section-иконка
  data-URI + position, переименование иконки, чистка установщика, docs. См. CHANGELOG.
- **Security-фиксы (v1.0.1).** XSS (JSON_HEX_TAG), IDOR (CHECK_PERMISSIONS), CSRF на load,
  PG-safe UnInstallDB.
