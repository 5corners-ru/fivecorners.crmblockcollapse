# BACKLOG

Технический долг и задачи модуля. `TD-N` — сквозная нумерация.

## Запланировано

- **TD-1. Валидация `smart_type_id` на save.** `action=save` принимает любой целочисленный
  `smart_type_id` и плодит ключи `state_SMART_PROCESS_<id>` в `b_user_option`. Ограничить
  множеством реальных типов (кэш `TypeTable`). Приоритет низкий — это user-scope опции,
  не утечка данных. (Источник: bitrix-api-security-reviewer, SEC-6.)
- **TD-2. `hasField()` guard перед `getStageId()`** в `StageHelper` (PG-suggestion) — сейчас
  защищено `try/catch \Throwable`, работает; канон рекомендует явный guard.
- **TD-3. Самохостинг шрифта Open Sans.** `PageHeader::addStyles()` грузит Google Fonts извне
  (как и эталон `DemoPageHelper`) — ломается на air-gapped порталах. Завести локальный subset.
  См. [docs/CUSTOM_UI_REGISTER.md](docs/CUSTOM_UI_REGISTER.md).

## В работе

—

## Сделано

- **Канон-миграция → admintemplate (v1.0.1, 2026-06-15).** AdminActiveSection, section-иконка
  data-URI + position, переименование иконки, чистка установщика, docs. См. CHANGELOG.
- **Security-фиксы (v1.0.1).** XSS (JSON_HEX_TAG), IDOR (CHECK_PERMISSIONS), CSRF на load,
  PG-safe UnInstallDB.
