# ARCHITECTURE — fivecorners.crmblockcollapse

## Карта классов

| Класс / файл | Namespace | Роль |
|---|---|---|
| `install/index.php` (`CModule`) | — | Установщик: файлы, события, чистка `b_user_option` |
| `Settings` (`lib/Settings.php`) | `FiveCorners\CrmBlockCollapse` | Чтение/запись настроек через `Option` (типы, смарт-процессы, правила стадий), нормализация имени блока |
| `StageHelper` (`lib/StageHelper.php`) | `FiveCorners\CrmBlockCollapse` | Текущая стадия сущности + список всех стадий (Deal/Lead/Smart) через CRM API |
| `Handler\PageHandler` (`lib/Handler/PageHandler.php`) | `FiveCorners\CrmBlockCollapse\Handler` | `OnProlog`: инжект `collapse.js` + CSS + `window.FC_CBC_CONFIG` на публичные страницы |
| `AdminMenu` (`lib/AdminMenu.php`) | `FiveCorners\CrmBlockCollapse` | `OnBuildGlobalMenu` (раздел + пункт) + `OnProlog` (иконки admin, CRM-виджет) |
| `AdminActiveSection` (`lib/AdminActiveSection.php`) | `FiveCorners\CrmBlockCollapse` | Серверная фиксация active section админ-меню |
| `PageHeader` (`lib/PageHeader.php`) | `FiveCorners\CrmBlockCollapse` | Шапка admin-страницы (версия, наушники-чат, бренд) |
| `collapse.js` | — | Фронтенд: детект сущности, кнопки, состояние, SPA-навигация, реакция на смену стадии |
| `ajax/...php` | — | AJAX `load`/`save` состояния |

## Поток данных

### Публичная карточка CRM
```
OnProlog (PageHandler) → <head>: collapse.js + CSS + FC_CBC_CONFIG
   ↓
collapse.js: parseEntityInfo() из URL → isEntityEnabled(CONFIG)
   ↓
GET ajax?action=load&sessid=...  →  CUserOptions(state) + StageHelper(stage→rules→expandedByStage)
   ↓
initSection() на каждую .ui-entity-editor-section: кнопка, applyState (стадия > сохранённое)
   ↓
клик по заголовку → applyState(animate) → debounce 450ms → POST ajax?action=save (sessid)
   ↓
смена стадии (XHR/fetch/BX-события) → reloadExpandedByStage → reapplyStageRules
```

### Хранилище
- **`b_option`** (`Option`, категория `fivecorners.crmblockcollapse`): `enabled_entity_types`,
  `enabled_smart_types`, `stage_rules` — глобальные настройки (пишет админка).
- **`b_user_option`** (`CUserOptions`, категория `fivecorners.crmblockcollapse`):
  `state_<TYPE>[_<smartTypeId>]` = JSON `{blockKey: bool}` — per-user состояние сворачивания.

### Ключ блока
`collapse.js getBlockKey()` и `Settings::normalizeBlockName()` дают **один формат**
`title:<lowercased_underscored_first80>` — чтобы правила стадий (по имени) матчились с
сохранённым состоянием (по DOM-заголовку).

## События
Регистрируются только в `InstallEvents()` (НЕ в `include.php`):
- `main / OnBuildGlobalMenu` → `AdminMenu::onBuildGlobalMenu`
- `main / OnProlog` → `PageHandler::onProlog` (публичные страницы)
- `main / OnProlog` → `AdminMenu::onProlog` (admin-иконки)

## Безопасность
- AJAX: `IsAuthorized` + `check_bitrix_sessid()` на обеих ветках; `entity_type` по whitelist,
  `entity_id`/`smart_type_id` → `(int)`.
- Стадия CRM отдаётся только с `CHECK_PERMISSIONS=Y` / `checkReadPermissions()`.
- Инжект конфига — `json_encode` с `JSON_HEX_TAG|HEX_AMP|HEX_APOS|HEX_QUOT`.
