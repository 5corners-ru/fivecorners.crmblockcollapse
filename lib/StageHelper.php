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
                case 'DEAL':
                    $res = \CCrmDeal::GetListEx(
                        [], ['=ID' => $entityId, 'CHECK_PERMISSIONS' => 'N'],
                        false, false, ['STAGE_ID']
                    );
                    $row = $res ? $res->Fetch() : null;
                    return ($row['STAGE_ID'] ?? null) ?: null;

                case 'LEAD':
                    $res = \CCrmLead::GetListEx(
                        [], ['=ID' => $entityId, 'CHECK_PERMISSIONS' => 'N'],
                        false, false, ['STATUS_ID']
                    );
                    $row = $res ? $res->Fetch() : null;
                    return ($row['STATUS_ID'] ?? null) ?: null;

                case 'SMART_PROCESS':
                    if ($smartTypeId <= 0) return null;
                    $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($smartTypeId);
                    if (!$factory) return null;
                    $item = $factory->getItem($entityId);
                    if (!$item) return null;
                    return $item->getStageId() ?: null;
            }
        } catch (\Throwable $e) {
        }

        return null;
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
