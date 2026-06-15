# MIGRATION.md — переоформление существующего модуля по канону

Чек-лист для приведения уже существующего Bitrix24-модуля к канону `fivecorners.admintemplate`. Используется, когда модуль был написан до текущих правил и его нужно «переодеть» без переписывания бизнес-логики.

**Эталон:** `fivecorners.admintemplate` (этот модуль). Все ссылки на файлы — относительно его корня.

**Обязательно перед началом:** прочитать [README.md](../README.md), пройти главную admin-страницу `admintemplate_structure.php` (после установки модуля) и категорию 14 UI-кита (иконки).

---

## 1. Структура файлов и папок

### 1.1. Корень модуля

| Файл | Что сделать | Откуда взять |
|---|---|---|
| `README.md` | Привести к шаблону: назначение, 2 сценария, quickstart, ссылки на docs/. | [README эталона](../README.md) |
| `CHANGELOG.md` | Keep a Changelog, секции `Added/Changed/Fixed/Removed`. | Эталон в корне эталона |
| `BACKLOG.md` | TD-N в трёх секциях: «Запланировано», «В работе», «Сделано». | Эталон в корне эталона |
| `.gitignore` | См. essentials в `documentation-rules` Г.1. | Эталон в корне эталона |
| `include.php` | Только `Loader::registerAutoLoadClasses(...)`. **БЕЗ event handlers** (правило CLAUDE.md #2). | [include.php эталона](../include.php) |

### 1.2. Что убрать из корня

- `BITRIX_FINDINGS.md` → перенести в `docs/BITRIX_FINDINGS.md`.
- Бинари > 100 KB (zip, jpg, png assets) → в `docs/screenshots/` или `_Market/` (gitignored) или вне модуля.
- `NEXT_SESSION.md` уже на диске — добавить в `.gitignore` и `git rm --cached NEXT_SESSION.md`.
- Пустые/декоративные подпапки без `.gitkeep` — удалить.
- `.bak`, `_old`, `_backup` папки — удалить (Bitrix loader подхватит install/index.php из subfolder = Fatal error duplicate class).

### 1.3. Папка `docs/`

| Файл | Когда |
|---|---|
| `docs/MIGRATION.md` | этот файл — оставить копию у себя с актуализированными ссылками |
| `docs/ARCHITECTURE.md` | обязательно (карта классов, потоки данных) |
| `docs/CUSTOM_UI_REGISTER.md` | если есть отступления от UI-кита (см. `bitrix-onpremise-ui-rules` Правило 2) |
| `docs/TEST_PLAN.md` | если есть ручные QA-сценарии (Given-When-Then через Палыча) |
| `docs/BITRIX_FINDINGS.md` | если в модуле находки про ядро Bitrix |
| `docs/screenshots/` | визуальные референсы (PNG/JPG) |
| `docs/archive/YYYY-MM/` | разовые анализы (`SANITY_CHECK_*`, `DIAGNOSIS_*`, `RUBRIC_*`) — после закрытия темы |

### 1.4. Папка `install/`

Обязательные файлы (минимум):

```
install/
├── index.php           DoInstall/DoUninstall, InstallFiles/UnInstallFiles, InstallEvents/UnInstallEvents
├── version.php         "VERSION" + "VERSION_DATE" (двойные кавычки, array())
├── step.php            страница после установки + ссылка на главную admin-страницу
├── unstep1.php         форма «сохранить настройки?» (method=post + bitrix_sessid_post())
├── unstep.php          страница после удаления
├── images/             4 типа иконок (см. п. 4)
├── admin/              admin-страницы модуля + lang/{ru,en}/
└── lang/{ru,en}/index.php  lang установщика
```

---

## 2. Установщик `install/index.php`

### 2.1. Симметрия Install ↔ UnInstall

Каждый вызов `Install*()` должен иметь зеркальный `UnInstall*()`. Эталонный набор:

| Install | UnInstall | Что делает |
|---|---|---|
| `InstallDB()` | `UnInstallDB($saveData)` | таблицы. **`UnInstallDB()` НИКОГДА не дропает таблицы при `$saveData=Y`.** |
| `InstallFiles()` | `UnInstallFiles()` | копирование admin-страниц в `/local/admin/`, иконок в `/local/images/` |
| `InstallEvents()` | `UnInstallEvents()` | RegisterModuleDependences (только в `InstallEvents`, не в `include.php`) |
| `RegisterModule()` | `UnRegisterModule()` | вызывается из DoInstall/DoUninstall, не из включаемых методов |

### 2.2. Двухшаговое удаление

`unstep1.php` показывает форму с checkbox «Сохранить настройки модуля» (`name="save_data" value="Y" checked`) → POST с `bitrix_sessid_post()` → `DoUninstall()` в `index.php` читает `$saveData = (($_REQUEST['save_data'] ?? '') === 'Y')` и при `step >= 2` выполняет удаление, иначе показывает `unstep1.php`. Финальный экран — `unstep.php`. См. эталон.

### 2.3. Вёрстка диалогов установщика (step / unstep1 / unstep)

Все три экрана установщика выровнены **по центру** и оформлены единообразно:

- Сообщение об успехе (`step.php`, `unstep.php`) — в коробке `adm-info-message-wrap adm-info-message-green` → `adm-info-message` → `adm-info-message-title` + `adm-info-message-body`. Кнопка-переход внутри `body` как `<a class="adm-btn">`.
- **Выравнивание — только явным inline `style="text-align:center"`**, НЕ устаревшим атрибутом `align="center"`. Причина: у `.adm-info-message-title` (ядро `panel/main/login.css:448`) в правиле нет `text-align`, а у `.adm-info-message-body` своего CSS-правила нет вообще → атрибут `align` имеет специфичность 0 и перебивается любым тема-правилом `.edit-table td { text-align:left }`, контент «гуляет». Inline-стиль (специфичность 1-0-0-0) от каскада не зависит.
- `text-align:center` ставится на: `<td>` обёртки, `.adm-info-message-title`, `.adm-info-message-body`; в `unstep1.php` — на `<p>` вопроса, `<p>` с чекбоксом и `<p>` с кнопкой.
- Кнопки: навигация-возврат — `<input type="submit">` или `<a class="adm-btn">`; акцентная save/destructive — `class="adm-btn-save"` и ТОЛЬКО на `<input>/<button>` (на `<a>` рендерится криво).

### 2.4. ASCII-табличка типов файлов в `InstallFiles()`

В начале метода обязательный комментарий-табличка с 4 типами иконок и куда копируется (см. эталон, начало `InstallFiles()`). Это документация для будущего разработчика.

---

## 3. Admin-страницы (`install/admin/*.php`)

> **Интеграционные модули без admin-поверхности.** Если модуль не регистрирует
> собственных admin-страниц (нет `install/admin/`) — например, провайдер кастомного
> UF-типа, набор event-хендлеров, BP-активити — разделы 3, 4 (иконки admin-меню), 5
> (AdminMenu) **неприменимы**: шапку `DemoPageHelper` физически некуда рендерить, пункта
> меню нет. Это законное освобождение, а не нарушение канона. Зафиксировать его в
> `docs/CUSTOM_UI_REGISTER.md` модуля с обоснованием и условием пересмотра (появятся
> глобальные настройки модуля → завести admin-страницу с полным каноном §3).
> Требование §3.3 «`DemoPageHelper` обязателен для всех модулей» относится к модулям,
> у которых admin-страницы есть.

### 3.1. Канон prolog/epilog

Каждая страница строится по шаблону (см. любую страницу эталона — `admintemplate_welcome.php`, `admintemplate_structure.php` и т.д.):

```php
defined('B_PROLOG_INCLUDED') || define('B_PROLOG_INCLUDED', true);
define('NO_KEEP_STATISTIC', 'Y');
define('NEED_AUTH', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

if (!$USER->IsAdmin()) { /* access denied */ die(); }
if (!Loader::includeModule(MODULE_ID)) { /* not installed */ die(); }

AdminActiveSection::markFiveCornersAdminTemplate();  // canonical fix JS-jump

$APPLICATION->AddHeadString('<base href="/bitrix/admin/">');
DemoPageHelper::addStyles($APPLICATION);
$APPLICATION->SetTitle(...);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

DemoPageHelper::renderOpen(...);  // открывает шапку с версией + ID в правом углу
// ... контент ...
DemoPageHelper::renderClose(...);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
```

### 3.2. AdminActiveSection — обязательно

`lib/AdminActiveSection.php` решает визуальный «прыжок» активной секции при загрузке страницы. **Без неё** пользователь видит, как меню сначала подсвечивает не ту секцию, потом скачком на правильную.

Что сделать:
1. Скопировать `lib/AdminActiveSection.php` из эталона.
2. Заменить namespace и `items_id` под свой umbrella-пункт меню.
3. На каждой admin-странице вызвать `<NS>\AdminActiveSection::mark<MyName>()` **до** `prolog_admin_after.php`.

### 3.3. DemoPageHelper — обязательно

`DemoPageHelper` даёт единую шапку admin-страниц (`.fco-custom-header`: навигация по разделам модуля, версия + системный ID в правом верхнем углу, брендовая ссылка 5 УГЛОВ). **Обязателен для всех модулей** — это требование единообразия: пользователь, переходя между admin-страницами разных модулей, должен видеть одинаковую шапку. Опускать нельзя даже для модуля с одной admin-страницей.

Что сделать:
1. Скопировать `lib/DemoPageHelper.php` из эталона.
2. Заменить namespace, константу `NAV_PAGES` (свои admin-страницы и их lang-ключи), дефолтный `moduleId`.
3. На каждой admin-странице: `DemoPageHelper::addStyles($APPLICATION)` до `prolog_admin_after.php`, `renderOpen(...)` / `renderClose(...)` вокруг контента (см. канон prolog/epilog в §3.1).

⚠️ **Ловушка при копировании.** Иконка-наушники в шапке `DemoPageHelper` открывает чат открытой линии 5 УГЛОВ — это **рабочая фича, не «мёртвый код»**. Чтобы она работала в твоём модуле:

- Не срезать обработчик клика наушников в `DemoPageHelper::renderClose` (легко принять за код под отключённую фичу — он рабочий).
- Loader виджета (`CRM_WIDGET_URL`) инжектится в `AdminMenu::onProlog` под guard'ом по префиксу имени admin-страниц (`substr($curFile, 0, N) === '<prefix>_'`). При копировании `AdminMenu` — **сменить этот префикс на имя своих страниц**, иначе loader не подключится и за наушниками не будет чата.
- Клик целить в `.b24-widget-button-openline_livechat` (`<a>`, синтетический `.click()` срабатывает), не в `.b24-widget-button-inner-container` (`<div>` — клик уходит в пустоту). Виджет грузится асинхронно ~15-20 с.

### 3.4. Lang-файлы

- `install/admin/lang/{ru,en}/<page>.php` — имя файла == имя PHP-страницы.
- Общие сообщения для нескольких страниц — в `install/admin/lang/{ru,en}/<module-prefix>_common.php` (только lang, без PHP-файла-пары).
- Префикс ключей единый: `FCO_<ABBR>_*` (где ABBR — короткий код модуля).

⚠️ **Ловушка `Loc::loadMessages()` — путь БЕЗ `/lang/{LANGUAGE_ID}/`.** Ядро (`Loc::includeLangFiles`, `main/lib/localization/loc.php:110`) само ищет папку `lang` вверх по дереву и вставляет `/lang/{LANG}/`. Передавать нужно путь к **виртуальному «соседу»** рядом с папкой `lang/`, как если бы PHP-файл лежал прямо там:

- ✅ Общий lang: `Loc::loadMessages(__DIR__ . '/admintemplate_common.php')` из `install/admin/*.php` → ядро подставит `lang/ru/` → `install/admin/lang/ru/admintemplate_common.php`.
- ✅ Из `lib/AdminMenu.php`: `Loc::loadMessages(__DIR__ . '/../install/admin/admintemplate_common.php')`.
- ❌ `Loc::loadMessages(__DIR__ . '/lang/' . LANGUAGE_ID . '/admintemplate_common.php')` — ядро вставит `/lang/{LANG}/` второй раз → `lang/ru/lang/ru/...` → файла нет, ключи молча не грузятся (видно только если ключ без fallback `?:`).

---

## 4. Иконки модуля (`install/images/`)

4 типа по канону Phase 7.2 эталона. UI-кит категория 14 показывает все четыре с превью + бэйджами used/sample.

| Файл | Назначение | Что делать |
|---|---|---|
| `section_icon.svg` | shared иконка раздела «5 УГЛОВ» | **🔴 Артворк дублируется КОНСТАНТОЙ `AdminMenu::SECTION_ICON_SVG` (nowdoc) и рисуется как `data:`-URI** (см. ниже). Файл всё ещё копируется в `/local/images/fivecorners/logo.svg` (InstallFiles, backward-compat для модулей на старом файловом пути), но `onProlog` его НЕ использует. При смене артворка править ОБА — файл и константу. |
| `admin_module_icon.png` | бренд модуля в admin-меню | копируется в `/local/images/<MODULE_ID>/admin_module_icon.png`. URL прописать в `lib/AdminMenu.php::MODULE_ICON_URL`. |
| `topmenu_public_icon.png` | top-menu иконка публичной части | snippet в InstallFiles закомментирован — раскомментировать если модуль регистрирует public top-menu пункт. |
| `bp_activity_icon.png` | BP-activity иконка | snippet закомментирован — раскомментировать если модуль регистрирует BP-активити (`/local/activities/.../icon.png`). |

UnInstallFiles **симметрично** удаляет первые два, **shared** `section_icon.svg` удаляет только если `md5_file` совпадает (на случай переопределённой версии).

Legacy cleanup в `InstallFiles()` — удаляет старые имена (`admin_icon.svg`, `admin_icon.png`) для модулей, мигрирующих с до-Phase-7.2 канона.

🔴 **Иконка раздела «5 УГЛОВ» — data-URI, НЕ файл (TD-94, session 98).** Раньше `onProlog` подключал section-иконку как `url(/local/images/fivecorners/logo.svg?v=<mtime>)`. Это **best-effort с файлом**: `InstallFiles` копирует `section_icon.svg`→`logo.svg` через `@mkdir`/`@copy` с подавлением ошибок (`@`) и guard `!is_file`/`file_exists`. На **наших** порталах файл уже лежал от других модулей семьи — баг маскировался. На **чистом клиентском портале** (инцидент alkana: установка из архива, не files-mode) любой фактор — упаковщик вырезал `install/images/`, нет прав на `/local/images/`, guard пропустил при пустом/чужом файле — приводил к **тихому отсутствию иконки** (раздел появлялся, картинки нет; см. сверку с ядром: поле `'icon'` в global menu ядро не читает, иконку держит только CSS-селектор `.adm-fivecorners .adm-main-menu-item-icon` → нет файла = нет иконки).

**Решение:** артворк встроен константой `SECTION_ICON_SVG` (nowdoc) и отдаётся как `background-image: url("data:image/svg+xml;base64,…")` через `AdminMenu::sectionIconUri()`. Едет В КОДЕ модуля → не зависит от копирования/прав/упаковки/обфускации. base64-alphabet безопасен для CSS `url()` и JS-строки (нет кавычек/скобок). cache-buster для section-иконки больше не нужен (data-URI меняется вместе с кодом). SVG крошечный (~420 b → ~430 b base64 в `<head>`).

🔴 **Section-CSS ОБЯЗАН ставить `background-position`+`background-repeat`, не только image+size (финальный корень TD-94, session 104).** Ядро вешает на каждый section-анкор класс `adm-default` (`prolog_main_admin.php:177`), а `admin.css:1007` задаёт `.adm-default .adm-main-menu-item-icon {background-position: center -1582px}` — offset системного спрайта. Если инжект перебивает только `background-image`+`background-size`, 22px-иконка рисуется на −1582px — **вне видимой области** → «раздел есть, иконки нет», даже когда data-URI доставлен идеально. На порталах с несколькими модулями семьи баг маскируется чужими инжектами (кто-то ставит position за всех); **соло-модуль на чистом клиентском портале остаётся без иконки** (инцидент defagroup/alkana, ipsd7). Канон: в section-CSS — `background-repeat: no-repeat !important; background-position: center !important;`, в JS-fallback — `el.style.backgroundRepeat`/`el.style.backgroundPosition`. Каждый модуль самодостаточен, на соседей не полагаемся.

⚠️ **Кеш per-module иконки (PNG).** `MODULE_ICON_URL` (бренд модуля в шапке) **остаётся файловым** с cache-buster'ом `?v=<filemtime>` (`iconVer()`) — PNG в data-URI раздул бы `<head>` (17 КБ → 23 КБ на каждой admin-странице). Битрикс вешает far-future expires на `/local/images/`, поэтому после замены PNG-артворка переустанавливать модуль (InstallFiles → новый mtime → `?v=` сменится → браузер перекачает). Симптом без буфера: «новая иконка не применилась», хотя сервер отдаёт новую (проверяется `curl` + md5). Section-иконку (SVG) этот кеш-нюанс больше не касается.

---

## 5. AdminMenu (`lib/AdminMenu.php`)

### 5.1. Два события — OnBuildGlobalMenu + OnProlog

`OnBuildGlobalMenu` строит дерево пунктов меню. `OnProlog` добавляет иконку модуля в шапку admin-страницы (через CSS+JS injection в `<head>`). **Оба** должны быть зарегистрированы через `InstallEvents()`, а handler-методы — статические в `lib/AdminMenu.php`.

Что сделать:
1. Скопировать `lib/AdminMenu.php` из эталона.
2. Заменить namespace, `MODULE_ICON_URL`, `MODULE_ID`, `MODULE_NAME`, пункты меню.
3. В `install/index.php::InstallEvents()` и `UnInstallEvents()` — пары `RegisterModuleDependences` / `UnRegisterModuleDependences` для обоих событий.

### 5.2. JS-активация меню (Правило 5 канона)

При входе на admin-страницу JS должен раскрыть нужную секцию меню и подсветить активный пункт. Шаблон делает это через guard `BX.adminAdminTemplateMenuActivated` (см. `AdminMenu::onProlog`).

---

## 6. version.php и Marketplace

```php
<?php
$arModuleVersion = array(
    "VERSION"      => "1.0.0",
    "VERSION_DATE" => "2026-05-19 00:00:00",
);
```

- **Двойные** кавычки (Marketplace-валидатор парсит как строки, не выполняет PHP).
- `array()` вместо `[]` (та же причина).
- Дата в формате `YYYY-MM-DD HH:MM:SS`.

При публикации в Marketplace — также см. `bitrix-marketplace-rules` (структура `.last_version.zip`, no echo в OnProlog).

---

## 7. Проверка миграции

Перед закрытием миграции:

1. **`bitrix-module-reviewer`** — обязательное автоматическое ревью эталонной структуры.
2. **`bitrix-orm-reviewer`** — если модуль имеет Table-классы.
3. **`bitrix-api-security-reviewer`** — если модуль регистрирует REST-controllers.
4. **Ручная проверка** через UI: установка → удаление с «сохранить настройки=Да» → переустановка → удаление с «сохранить настройки=Нет» → отсутствие follow-up таблиц/файлов.
5. **Чек-лист `admintemplate_structure.php`** — пройтись по разделам «Обязательно/Опционально/Запрещено» и сверить со своим модулем.

---

## 8. Что НЕ делать при миграции

- ❌ Вызывать `UnInstallDB()` в скриптах deploy/reinstall — это **дропает таблицы** (даже когда `$saveData=Y` — зависит от реализации модуля, лучше не рисковать).
- ❌ Регистрировать event handlers в `include.php` — реинсталл с `save_data=Y` дублирует handlers, ловите Fatal на каждом OnAfterUserUpdate.
- ❌ Использовать highload-блоки для своих таблиц — простой ORM `DataManager` через `install.sql` лучше.
- ❌ Оставлять `_op_reset.php` или другие временные скрипты на проде — после deploy убирать руками.
- ❌ Дублировать содержимое CLAUDE.md в скиллы и обратно — короткий якорь в CLAUDE.md, полный текст в скилле.

---

## 9. Где правила живут

| Тема | Источник |
|---|---|
| Структура модуля, install/uninstall, AdminMenu | skill `bitrix-module-rules` |
| UI-компоненты, CUSTOM_UI_REGISTER, AdminActiveSection | skill `bitrix-onpremise-ui-rules` |
| Table-классы, ACID-транзакции, getList | skill `bitrix-d7-orm-rules` |
| BP-активити, properties_dialog, inline PHP-код | skill `bitrix-activity-rules` |
| Marketplace release | skill `bitrix-marketplace-rules` |
| Структура документации (этот файл — её частный случай) | skill `documentation-rules` |
| Обязательное ревью перед закрытием итерации | skill `bitrix-review-cycle` |

При сомнении — `analyze-task` или прямой грep по `~/.claude/5corners-claude-knowledge/`.
