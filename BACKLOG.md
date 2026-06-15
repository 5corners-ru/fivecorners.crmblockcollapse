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
  См. [docs/CUSTOM_UI_REGISTER.md](docs/CUSTOM_UI_REGISTER.md). (Подтверждено security-ревью
  merge-gate как SEC-13 Warning — желательно закрыть до Marketplace.)
- **TD-4. Логирование отказов авторизации на ajax-эндпоинте.** При `!$USER->IsAuthorized()`
  (`install/ajax/fivecorners_crmblockcollapse.php:14`) возвращается JSON, но в `b_event_log`
  ничего не пишется. Не критично (модуль работает только с user-опциями), но `CEventLog::Add()`
  severity SECURITY помог бы аудиту. (Источник: security merge-gate, SEC-11.)
- **TD-5. Расхождение по имени иконки модуля.** `install/admin_module_icon.png` —
  `bitrix-module-reviewer` считает нарушением Правила 13 (канон ждёт `admin_icon.png`),
  но в прошлой сессии переименовали `admin_icon.png` → `admin_module_icon.png` намеренно
  («канон Phase 7.2», CHANGELOG 1.0.1). Сверить: устарело ли правило в скилле или ошиблась
  прошлая сессия. Не функциональный баг.

## В работе

—

## Сделано

- **Канон-миграция → admintemplate (v1.0.1, 2026-06-15).** AdminActiveSection, section-иконка
  data-URI + position, переименование иконки, чистка установщика, docs. См. CHANGELOG.
- **Security-фиксы (v1.0.1).** XSS (JSON_HEX_TAG), IDOR (CHECK_PERMISSIONS), CSRF на load,
  PG-safe UnInstallDB.
