# BITRIX_FINDINGS — fivecorners.crmblockcollapse

Находки про поведение ядра Bitrix24, пойманные при работе над модулем. Формат:
как проявляется (anti-pattern) → корень → как правильно (pattern).

Кандидаты на промоцию в командные `bitrix-*-rules` скиллы через `/sync-kb`
(помечены `→ promote`).

---

## F1. `EventManager::unRegisterEventHandlerCompatible()` не существует → `unRegisterEventHandler()`  → promote (bitrix-module-rules)

> ✅ Промотировано в KB → `docs/bitrix/findings/module_install_findings.md` `[crmblockcollapse #1]` (PR #63, merged 2026-06-15).

**Как проявляется.** `UnInstallEvents()` модуля при удалении/переустановке падает фаталом:
`Call to undefined method Bitrix\Main\EventManager::unRegisterEventHandlerCompatible()`.
Поймано на деплое-переустановке (reinstall оборвался на шаге UnInstallEvents — файлы
распакованы, но InstallFiles/registerModule не отработали).

**Корень.** У `EventManager` есть `registerEventHandler` И `registerEventHandlerCompatible`
(две версии регистрации — v2 и legacy v1), но **парного `unRegisterEventHandlerCompatible`
НЕТ**. Снятие хендлера — всегда один метод `unRegisterEventHandler($fromModuleId,
$eventType, $toModuleId, $toClass, $toMethod, $toPath, $toMethodArg)`, который удаляет запись
из `b_module_to_module` независимо от того, как её регистрировали (по match
module+event+class+method). Разработчик по аналогии с `registerEventHandlerCompatible`
предположил симметричный `unRegister…Compatible` — его не существует.

**❌ Anti-pattern**
```php
// InstallEvents:
$em->registerEventHandlerCompatible("main","OnProlog",$id,$class,"onProlog",10);
// UnInstallEvents:
$em->unRegisterEventHandlerCompatible("main","OnProlog",$id,$class,"onProlog"); // FATAL: метода нет
```

**✅ Pattern**
```php
$em->unRegisterEventHandler("main","OnProlog",$id,$class,"onProlog"); // снимает и compatible-хендлер
```

⚠️ Ловушка тихая: `registerEventHandlerCompatible` существует и работает, поэтому
`InstallEvents` проходит. Ошибка вылезает только при `UnInstallEvents` (удаление/реинсталл) —
легко не заметить до выкатки, а на проде это уже падение админки при удалении модуля.
Статический ревьюер сматчил симметрию вызовов register/unregister, но не проверил, что
метод существует — поймал только реальный deploy-reinstall.

---

## F2. `SqlHelper::quote()` — для ИДЕНТИФИКАТОРОВ, не строковых значений → `convertToDbString()`  → promote (bitrix-d7-orm-rules)

> ✅ Промотировано в KB → `docs/bitrix/findings/d7_orm_findings.md` `[crmblockcollapse #2]` (PR #63, merged 2026-06-15).

**Как проявляется.** `DELETE FROM b_user_option WHERE CATEGORY = ` + `$helper->quote($MODULE_ID)`
на MySQL генерит невалидный SQL: `MODULE_ID = 'fivecorners.crmblockcollapse'` превращается в
`` `fivecorners`.`crmblockcollapse` `` — `quote()` бьёт строку по точке как
schema.identifier. На MySQL strict → SQL-ошибка, uninstall падает (на PG `quote` даёт
`"fivecorners"."crmblockcollapse"` — тоже мусор).

**Корень.** `\Bitrix\Main\DB\SqlHelper::quote($identifier)` квотит **идентификатор**
(имя таблицы/колонки) — обрамляет бэктиками (MySQL) / двойными кавычками (PG) и разбивает по
точке. Для экранирования строкового **значения** — другой метод:
- `forSql($value)` — экранирует строку (без обрамляющих кавычек, ставишь сам в `'...'`);
- `convertToDbString($value)` — `"'" . forSql($value) . "'"` (экранирование + кавычки).

**❌ Anti-pattern**
```php
$conn->queryExecute("DELETE FROM b_user_option WHERE CATEGORY = " . $helper->quote($moduleId));
// quote() для значения → `fivecorners`.`crmblockcollapse` → невалидный SQL
```

**✅ Pattern**
```php
$conn->queryExecute("DELETE FROM b_user_option WHERE CATEGORY = " . $helper->convertToDbString($moduleId));
// → CATEGORY = 'fivecorners.crmblockcollapse' (экранировано + в кавычках)
```

Сверено с ядром: `main/lib/db/sqlhelper.php` — `quote()` (стрипает кавычки-идентификаторы),
`forSql()` (abstract), `convertToDbString()` = `"'".forSql()."'"`. Само ядро для
`b_user_option` исторически использует `$DB->ForSql()` в `CUserOptions` — D7-эквивалент
именно `forSql`/`convertToDbString`, НЕ `quote`.

---

## F3. `TypeTable::getList()` без `IS_INITIALIZED=Y` отдаёт полу-созданные смарт-типы  → promote (bitrix-d7-orm-rules)

> ✅ Промотировано в KB → `docs/bitrix/findings/d7_orm_findings.md` `[crmblockcollapse #3]` (PR #66, merged 2026-06-15).

**Как проявляется.** Перечисление/валидация типов смарт-процессов через
`\Bitrix\Crm\Model\Dynamic\TypeTable::getList(['select' => ['ENTITY_TYPE_ID']])` без фильтра
включает в выборку типы, которые ядро ещё **не дорегистрировало** (DDL физической таблицы не
завершён или завис с `IS_INITIALIZED='N'`). В TD-1 это подрывало смысл самой валидации: «призрачный»
тип проходил как валидный → save плодил ключ `state_SMART_PROCESS_<id>` для несуществующего типа.

**Корень.** Ядро при создании смарт-процесса сначала пишет строку в `b_crm_dynamic_type` с
`IS_INITIALIZED='N'`, и лишь после успешного создания физической таблицы ставит `'Y'`. Сам
`TypeTable::getByEntityTypeId()` в ядре фильтрует `'=IS_INITIALIZED' => true` (см. `crm/lib/model/dynamic/typetable.php`).
Ручной `getList` без этого фильтра видит и неинициализированные.

**❌ Anti-pattern**
```php
$res = \Bitrix\Crm\Model\Dynamic\TypeTable::getList(['select' => ['ENTITY_TYPE_ID']]);
// в выборке — в т.ч. IS_INITIALIZED='N' типы (DDL не завершён)
```

**✅ Pattern**
```php
$res = \Bitrix\Crm\Model\Dynamic\TypeTable::getList([
    'select' => ['ENTITY_TYPE_ID'],
    'filter' => ['=IS_INITIALIZED' => true],   // BooleanField 'N'/'Y' → true маппится в 'Y'
]);
```

Альтернатива для точечной проверки одного типа — готовый `TypeTable::getByEntityTypeId($id)` (фильтр
`IS_INITIALIZED` уже внутри). Поймано на ORM-ревью v1.0.7 (закрытие TD-1).
