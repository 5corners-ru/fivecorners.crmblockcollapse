<?php
namespace FiveCorners\CrmBlockCollapse;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class StageHelper
{
    public static function getEntityStageId(string $entityType, int $entityId, int $smartTypeId = 0): ?string
    {
        if (!Loader::includeModule('crm')) {
            return null;
        }

        try {
            switch ($entityType) {
                // CHECK_PERMISSIONS=Y (не 'N'!) — иначе любой авторизованный юзер
                // перебором entity_id вытащит стадию чужой/недоступной сделки (IDOR).
                // Нет доступа → GetListEx вернёт пусто → null → раскрытие по стадии
                // просто не применится (безопасная деградация).
                case 'DEAL':
                    $res = \CCrmDeal::GetListEx(
                        [], ['=ID' => $entityId, 'CHECK_PERMISSIONS' => 'Y'],
                        false, false, ['STAGE_ID']
                    );
                    $row = $res ? $res->Fetch() : null;
                    return ($row['STAGE_ID'] ?? null) ?: null;

                case 'LEAD':
                    $res = \CCrmLead::GetListEx(
                        [], ['=ID' => $entityId, 'CHECK_PERMISSIONS' => 'Y'],
                        false, false, ['STATUS_ID']
                    );
                    $row = $res ? $res->Fetch() : null;
                    return ($row['STATUS_ID'] ?? null) ?: null;

                case 'SMART_PROCESS':
                    if ($smartTypeId <= 0) return null;
                    $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($smartTypeId);
                    if (!$factory) return null;
                    // TD-2: явный guard вместо опоры на try/catch — тип без воронки
                    // не имеет поля STAGE_ID, getStageId() вернул бы мусор. Канон —
                    // проверять isStagesEnabled() перед обращением к стадии.
                    if (!$factory->isStagesEnabled()) return null;
                    // Проверка прав на чтение конкретного элемента смарт-процесса —
                    // getItem() сам по себе ACL не проверяет. Fail closed: при ином
                    // сигнатуре API на старых версиях try/catch ниже вернёт null.
                    $userPerms = \Bitrix\Crm\Service\Container::getInstance()->getUserPermissions();
                    if (!$userPerms->checkReadPermissions($factory->getEntityTypeId(), $entityId)) {
                        return null;
                    }
                    $item = $factory->getItem($entityId);
                    if (!$item) return null;
                    return $item->getStageId() ?: null;
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * TD-1: smart_type_id с фронта — произвольный int. Валидируем против реального
     * множества типов смарт-процессов (b_crm_dynamic_type), чтобы save не плодил
     * мусорные ключи state_SMART_PROCESS_<id> в b_user_option. Кэш на запрос.
     */
    public static function isValidSmartTypeId(int $smartTypeId): bool
    {
        if ($smartTypeId <= 0) {
            return false;
        }
        if (!Loader::includeModule('crm')) {
            return false;
        }

        static $validIds = null;
        if ($validIds === null) {
            $validIds = [];
            try {
                // IS_INITIALIZED=Y — иначе полу-созданный смарт-тип (DDL ещё не дошёл)
                // прошёл бы валидацию как «реальный», что подрывает смысл TD-1.
                $res = \Bitrix\Crm\Model\Dynamic\TypeTable::getList([
                    'select' => ['ENTITY_TYPE_ID'],
                    'filter' => ['=IS_INITIALIZED' => true],
                ]);
                while ($row = $res->fetch()) {
                    $validIds[(int)$row['ENTITY_TYPE_ID']] = true;
                }
            } catch (\Throwable $e) {
            }
        }

        return isset($validIds[$smartTypeId]);
    }

    public static function getAllStages(): array
    {
        if (!Loader::includeModule('crm')) {
            return [];
        }

        $result = ['DEAL' => [], 'LEAD' => [], 'SMART_PROCESS' => []];

        try {
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Deal);
            if ($factory) {
                foreach ($factory->getCategories() as $category) {
                    $groupName = $category->getName() ?: Loc::getMessage('FCO_CBC_DEFAULT_FUNNEL');
                    foreach ($factory->getStages($category->getId()) as $stage) {
                        $result['DEAL'][] = [
                            'id'    => $stage->getStatusId(),
                            'name'  => $stage->getName(),
                            'group' => $groupName,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Lead);
            if ($factory) {
                foreach ($factory->getStages(0) as $stage) {
                    $result['LEAD'][] = [
                        'id'   => $stage->getStatusId(),
                        'name' => $stage->getName(),
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            $typeList = \Bitrix\Crm\Model\Dynamic\TypeTable::getList([
                'select' => ['ID', 'ENTITY_TYPE_ID', 'TITLE'],
                'filter' => ['=IS_INITIALIZED' => true],
                'order'  => ['TITLE' => 'ASC'],
            ]);
            while ($typeRow = $typeList->fetch()) {
                $typeId    = (int)$typeRow['ENTITY_TYPE_ID'];
                $typeTitle = (string)$typeRow['TITLE'];
                try {
                    $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($typeId);
                    if (!$factory) continue;
                    $stages = [];
                    foreach ($factory->getStages(0) as $stage) {
                        $stages[] = [
                            'id'   => $typeId . ':' . $stage->getStatusId(),
                            'name' => $stage->getName(),
                        ];
                    }
                    if ($stages) {
                        $result['SMART_PROCESS'][] = [
                            'typeId'    => $typeId,
                            'typeTitle' => $typeTitle,
                            'stages'    => $stages,
                        ];
                    }
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }

        return $result;
    }
}
