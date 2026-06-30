# Changelog

Все заметные изменения модуля. Формат — [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/),
версионирование — [SemVer](https://semver.org/lang/ru/).

## [1.0.9] - 2026-06-30

### Fixed
- **`UnInstallDB()` уважает `$_REQUEST['savedata'|'save_data']`.** Метод безусловно чистил per-user опции модуля (`b_user_option` — состояние свёрнутости разделов карточки) и при headless-реинсталле (`mcp4dev opsReinstallModule mode=full`, зовёт `UnInstallDB()` напрямую с флагом в `$_REQUEST`) затирал пользовательские настройки вопреки флагу «сохранить данные». Теперь `DELETE` выполняется только при снятой галке, `UnRegisterModule` остаётся безусловным. Тот же системный класс уязвимости, что снёс боевую базу qadesk 30.06.2026. Правка: `install/index.php`.

## [1.0.8] - 2026-06-15

### Added
- **Свернуть всё при первом визите.** При самом первом открытии карточки CRM
  пользователь видит все разделы свёрнутыми (один раз на каждый тип сущности —
  маркируется кукой `fc_cbc_init_<TYPE>`). Состояние персистится на сервер
  (`action=save_bulk`, merge — явные прошлые выборы юзера не затираются), поэтому
  свёрнутый вид устойчив: разделы остаются свёрнутыми, пока пользователь их не
  откроет. Дальше — обычное сохранение «как он сделал».
- Опция включается/выключается в админке (вкладка «Настройки» → секция
  «Поведение»), по умолчанию включена. Прокидывается в JS через
  `FC_CBC_CONFIG.collapseAllFirstVisit`.

### Fixed
- **Cache-busting `collapse.js`.** Скрипт подключается с `?v=<версия модуля>` —
  раньше грузился без версии, и после апдейта браузер отдавал старую закэшированную
  копию (новая логика не доезжала до пользователя до сброса кэша/hard-reload).

## [1.0.7] - 2026-06-15

### Security
- **TD-4 (SEC-11):** анонимное обращение к ajax-эндпоинту (`!$USER->IsAuthorized()`)
  пишется в журнал безопасности через `CEventLog::Add()` (severity SECURITY,
  audit-type `FCO_CBC_UNAUTHORIZED_AJAX`).
- **TD-1:** `action=save/load` для смарт-процессов валидирует `smart_type_id` против
  реального множества типов (`StageHelper::isValidSmartTypeId()` — кэш `TypeTable`).
  Раньше любой int плодил мусорные ключи `state_SMART_PROCESS_<id>` в `b_user_option`.

### Changed
- **TD-2:** `StageHelper::getEntityStageId()` для смарт-процессов — явный guard
  `$factory->isStagesEnabled()` перед `getStageId()` (раньше защищено только
  `try/catch`; канон требует явной проверки).

## [1.0.6] - 2026-06-15

### Changed
- Страница «Справка о модуле» — текст на белой карточке-подложке (border-radius,
  тень, отступы), заголовки секций с акцентной полосой. Раньше текст лежал прямо
  на фоне админки.

## [1.0.5] - 2026-06-15

### Changed
- Админка разнесена на три страницы с навигацией в шапке: **Настройки** (типы
  сущностей + смарт-процессы), **Правила по стадиям** (мастер-деталь, широкая
  раскладка), **Справка о модуле**. Пункт меню «Сворачивание разделов» получил
  три подпункта.
- Кнопка «Сохранить» отцентрована (была прижата к левому краю).
- Область контента расширена: Настройки до 1000px, Правила по стадиям до 1280px
  (раньше всё было зажато в 760px).
- Общие admin-строки (типы сущностей, save, версия, nav) вынесены в
  `fc_crmblockcollapse_common.php`.

## [1.0.4] - 2026-06-15

### Changed
- UX блока «Правила по стадиям» — мастер-деталь вместо простыни: внутри вкладки
  сущности слева список воронок с бейджами «правил/стадий» + поиск и фильтр
  «только с правилами», справа стадии выбранной воронки. При куче воронок больше
  не разрастается; бейджи сразу показывают, где правила. Все textarea остаются в
  форме (скрытие CSS) — сохранение шлёт все правила разом. Лиды (одна воронка) —
  без левого списка. Бейдж пересчитывается на лету при вводе.

## [1.0.3] - 2026-06-15

### Changed
- Уточнено имя модуля: «Сворачивание разделов **в** CRM» → «Сворачивание разделов CRM»
  (убран предлог «в»): MODULE_NAME, title пункта меню, заголовок admin-страницы, зонтик.

## [1.0.2] - 2026-06-15

### Fixed
- `UnInstallEvents()` вызывал несуществующий `EventManager::unRegisterEventHandlerCompatible()`
  → fatal при удалении/переустановке модуля (у `registerEventHandlerCompatible` нет парного
  `unRegister…Compatible`; снятие хендлера — всегда `unRegisterEventHandler()`). Поймано на
  деплое-переустановке. Заменено на `unRegisterEventHandler()` (×3).

### Changed
- Отображаемое имя модуля «Сворачивание **блоков** в CRM» → «Сворачивание **разделов** в CRM»
  (RU и EN — `Block Collapse` → `Section Collapse`): MODULE_NAME, пункт меню, заголовок
  admin-страницы, плейсхолдеры/подсказки настроек. `module_id` и технические ключи
  (`block_key`, CSS/JS) не менялись.

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
