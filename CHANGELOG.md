# Changelog

Все заметные изменения модуля. Формат — [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/),
версионирование — [SemVer](https://semver.org/lang/ru/).

## [1.0.1] - 2026-06-15

Приведение к канону `fivecorners.admintemplate` + закрытие находок ревью.

### Security
- **XSS (inline `<script>`):** `PageHandler` теперь кодирует конфиг с
  `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT` и без `JSON_UNESCAPED_SLASHES` —
  значение опции не может выйти из `<script>` через `</script>`.
- **IDOR / утечка стадии CRM:** `StageHelper::getEntityStageId()` использует
  `CHECK_PERMISSIONS=Y` для сделок/лидов и проверяет `checkReadPermissions()` для
  смарт-процессов. Без доступа стадия не отдаётся (безопасная деградация).
- **CSRF на чтении:** ветка `action=load` AJAX-эндпоинта теперь требует `check_bitrix_sessid()`;
  `collapse.js` шлёт `sessid` в запросе load.

### Changed
- `UnInstallDB()` переведён с legacy `$DB->Query` на D7 `Connection::queryExecute()` +
  `SqlHelper::quote()` — диалект-нейтрально, PG-safe.
- Section-иконка раздела «5 УГЛОВ» рисуется **data-URI**'ем из `AdminMenu::SECTION_ICON_SVG_B64`
  (не зависит от файла на диске); section-CSS и JS-fallback ставят
  `background-position: center` + `background-repeat: no-repeat` (фикс спрайт-offset −1582px).
- Подсветка раздела admin-меню — на сервере через `AdminActiveSection::markCrmBlockCollapse()`,
  JS-активация из admin-страницы убрана.
- Иконка модуля переименована `admin_icon.png` → `admin_module_icon.png` (канон Phase 7.2).
- Установщик: убран deprecated `align="center"` (оставлен inline `text-align`),
  добавлена ASCII-табличка файлов в `InstallFiles()`, `VERSION_DATE` в формате
  `YYYY-MM-DD HH:MM:SS`.

### Added
- `lib/AdminActiveSection.php` — серверная фиксация active section админ-меню.
- Документация: `README.md`, `CHANGELOG.md`, `BACKLOG.md`, `docs/ARCHITECTURE.md`,
  `docs/MIGRATION.md`, `docs/CUSTOM_UI_REGISTER.md`.

## [1.0.0] - 2026-06-04

### Added
- Кнопки сворачивания блоков в Сделках, Лидах, Контактах, Компаниях, Смарт-процессах.
- Сохранение состояния per-user между сессиями.
- Правила раскрытия блоков по стадии сущности.
- Admin-страница настроек, раздел меню «5 УГЛОВ».
