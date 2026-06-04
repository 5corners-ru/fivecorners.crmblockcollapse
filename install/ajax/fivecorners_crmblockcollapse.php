<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use FiveCorners\CrmBlockCollapse\Settings;
use FiveCorners\CrmBlockCollapse\StageHelper;

header('Content-Type: application/json; charset=utf-8');

/** @var CUser $USER */
if (!$USER->IsAuthorized()) {
    echo json_encode(array('success' => false, 'error' => 'not_authorized'));
    die();
}

if (!Loader::includeModule('fivecorners.crmblockcollapse')) {
    echo json_encode(array('success' => false, 'error' => 'module_unavailable'));
    die();
}

const OPTION_CATEGORY = 'fivecorners.crmblockcollapse';
const ALLOWED_ENTITY_TYPES = array('DEAL', 'LEAD', 'CONTACT', 'COMPANY', 'SMART_PROCESS');

$action      = (string)($_REQUEST['action'] ?? '');
$entityType  = strtoupper(preg_replace('/[^A-Z_]/', '', (string)($_REQUEST['entity_type'] ?? '')));
$entityId    = (int)($_REQUEST['entity_id'] ?? 0);
$smartTypeId = (int)($_REQUEST['smart_type_id'] ?? 0);

if (!in_array($entityType, ALLOWED_ENTITY_TYPES, true)) {
    echo json_encode(array('success' => false, 'error' => 'invalid_entity_type'));
    die();
}

$optionName = 'state_' . $entityType . ($smartTypeId > 0 ? '_' . $smartTypeId : '');

if ($action === 'load') {
    // read-only: returns current user's data only; no sessid check required
    $stateJson = CUserOptions::GetOption(OPTION_CATEGORY, $optionName, '{}');
    $state     = json_decode($stateJson, true);

    $expandedByStage = array();
    if ($entityId > 0 && in_array($entityType, array('DEAL', 'LEAD', 'SMART_PROCESS'), true)) {
        $stageId = StageHelper::getEntityStageId($entityType, $entityId, $smartTypeId);
        if ($stageId !== null) {
            $rules    = Settings::getStageRules();
            $ruleKey  = ($entityType === 'SMART_PROCESS' && $smartTypeId > 0)
                ? ($smartTypeId . ':' . $stageId)
                : $stageId;
            $names    = $rules[$entityType][$ruleKey] ?? array();
            $expandedByStage = array_values(array_map(
                array('FiveCorners\CrmBlockCollapse\Settings', 'normalizeBlockName'),
                $names
            ));
        }
    }

    echo json_encode(array(
        'success'         => true,
        'state'           => is_array($state) ? $state : array(),
        'expandedByStage' => $expandedByStage,
    ));
    die();
}

if ($action === 'save' && check_bitrix_sessid()) {
    // Sanitize block key: allow unicode text + basic punctuation, strip dangerous chars
    $blockKey = trim((string)($_POST['block_key'] ?? ''));
    $blockKey = mb_substr($blockKey, 0, 200);
    if ($blockKey === '') {
        echo json_encode(array('success' => false, 'error' => 'empty_key'));
        die();
    }
    $isCollapsed = ($_POST['is_collapsed'] ?? '0') === '1';

    // Load existing state
    $stateJson = CUserOptions::GetOption(OPTION_CATEGORY, $optionName, '{}');
    $state     = json_decode($stateJson, true);
    if (!is_array($state)) {
        $state = array();
    }

    // Update only the changed block (optimistic, no full rewrite on every click)
    $state[$blockKey] = $isCollapsed;

    // Keep state size manageable (max 100 blocks per entity)
    if (count($state) > 100) {
        $state = array_slice($state, -100, 100, true);
    }

    CUserOptions::SetOption(OPTION_CATEGORY, $optionName, json_encode($state));

    echo json_encode(array('success' => true));
    die();
}

echo json_encode(array('success' => false, 'error' => 'invalid_action'));
die();
