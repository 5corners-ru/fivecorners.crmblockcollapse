<?php
namespace FiveCorners\CrmBlockCollapse;

use Bitrix\Main\Config\Option;

class Settings
{
    const MODULE_ID = 'fivecorners.crmblockcollapse';

    private static $defaultEntityTypes = array(
        'DEAL', 'LEAD', 'CONTACT', 'COMPANY', 'SMART_PROCESS',
    );

    public static function getModuleId(): string
    {
        return self::MODULE_ID;
    }

    public static function getEnabledEntityTypes(): array
    {
        $value = Option::get(self::MODULE_ID, 'enabled_entity_types', '');
        if ($value === '') {
            return self::$defaultEntityTypes;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : self::$defaultEntityTypes;
    }

    public static function setEnabledEntityTypes(array $types): void
    {
        $clean = array_values(array_intersect(
            $types,
            array('DEAL', 'LEAD', 'CONTACT', 'COMPANY', 'SMART_PROCESS')
        ));
        Option::set(self::MODULE_ID, 'enabled_entity_types', json_encode($clean));
    }

    /**
     * Returns array of enabled smart process type IDs.
     * Empty array means ALL smart processes are enabled.
     */
    public static function getEnabledSmartProcessTypeIds(): array
    {
        $value = Option::get(self::MODULE_ID, 'enabled_smart_types', '');
        if ($value === '') {
            return array();
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_map('intval', $decoded) : array();
    }

    public static function setEnabledSmartProcessTypeIds(array $ids): void
    {
        $clean = array_values(array_filter(array_map('intval', $ids)));
        Option::set(self::MODULE_ID, 'enabled_smart_types', json_encode($clean));
    }

    public static function isEntityTypeEnabled(string $entityType): bool
    {
        return in_array($entityType, self::getEnabledEntityTypes(), true);
    }

    public static function isSmartProcessEnabled(int $typeId): bool
    {
        if (!self::isEntityTypeEnabled('SMART_PROCESS')) {
            return false;
        }
        $enabledIds = self::getEnabledSmartProcessTypeIds();
        return empty($enabledIds) || in_array($typeId, $enabledIds, true);
    }

    /**
     * Свернуть все разделы при самом первом открытии карточки пользователем
     * (один раз на тип сущности, маркируется кукой на стороне клиента).
     * По умолчанию включено.
     */
    public static function isCollapseAllFirstVisit(): bool
    {
        return Option::get(self::MODULE_ID, 'collapse_all_first_visit', 'Y') === 'Y';
    }

    public static function setCollapseAllFirstVisit(bool $enabled): void
    {
        Option::set(self::MODULE_ID, 'collapse_all_first_visit', $enabled ? 'Y' : 'N');
    }

    public static function getStageRules(): array
    {
        $value = Option::get(self::MODULE_ID, 'stage_rules', '');
        if ($value === '') {
            return array();
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : array();
    }

    public static function setStageRules(array $rules): void
    {
        Option::set(self::MODULE_ID, 'stage_rules', json_encode($rules, JSON_UNESCAPED_UNICODE));
    }

    // Normalize block name to match the key format used in collapse.js getBlockKey().
    public static function normalizeBlockName(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = preg_replace('/\s+/u', '_', $name);
        $name = mb_substr($name, 0, 80, 'UTF-8');
        return 'title:' . $name;
    }
}
