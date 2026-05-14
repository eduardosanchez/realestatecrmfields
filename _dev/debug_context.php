<?php
/* DEBUG: Intercepta todos los hooks que se ejecutan en una página */
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
header('Content-Type: application/json');
if (!isset($user) || empty($user->login)) { echo json_encode(['error'=>'not auth']); exit; }

// Ver qué hooks están registrados para el módulo
$sql = "SELECT name, value FROM " . MAIN_DB_PREFIX . "const WHERE name LIKE 'MAIN_MODULE_REALESTATECRMFIELDS%' ORDER BY name";
$res2 = $db->query($sql);
$consts = [];
if ($res2) {
    while ($obj = $db->fetch_object($res2)) {
        $consts[$obj->name] = $obj->value;
    }
}

// Verificar que la clase del hook tiene el método correcto
$hookFile = DOL_DOCUMENT_ROOT . '/custom/realestatecrmfields/hooks/actions_realestatecrmfields.class.php';
require_once $hookFile;
$methods = get_class_methods('Actions_realestatecrmfields');

echo json_encode([
    'module_consts' => $consts,
    'hook_class_methods' => $methods,
    'dolibarr_version' => DOL_VERSION,
], JSON_PRETTY_PRINT);
