<?php
// Sin incluir main.inc.php para ver si el problema está ahí
error_reporting(E_ALL);
ini_set('display_errors', 1);

$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) { echo json_encode(['error' => 'main.inc.php not found']); exit; }

header('Content-Type: application/json');

// Probar las funciones que usa consulta_save
$tests = [];

// 1. GETPOST existe?
$tests['GETPOST_exists'] = function_exists('GETPOST');

// 2. dol_now existe?
$tests['dol_now_exists'] = function_exists('dol_now');

// 3. dol_print_date existe?
$tests['dol_print_date_exists'] = function_exists('dol_print_date');

// 4. ReConsulta cargable?
try {
    require_once dol_buildpath('/custom/realestatecrmfields/class/reconsulta.class.php', 0);
    $tests['reconsulta_class'] = class_exists('ReConsulta');
} catch (Throwable $e) {
    $tests['reconsulta_class'] = 'ERROR: ' . $e->getMessage();
}

// 5. $db disponible?
$tests['db_available'] = isset($db) && is_object($db);

// 6. $user disponible?
$tests['user_available'] = isset($user) && is_object($user);
$tests['user_login'] = isset($user) ? ($user->login ?? 'empty') : 'not set';

// 7. Versión Dolibarr
$tests['dol_version'] = defined('DOL_VERSION') ? DOL_VERSION : 'not defined';

// 8. Probar consulta simple
if (isset($db)) {
    $r = $db->query("SELECT COUNT(*) as cnt FROM " . MAIN_DB_PREFIX . "re_consulta");
    $tests['re_consulta_count'] = $r ? $db->fetch_object($r)->cnt : 'error: ' . $db->lasterror();
}

echo json_encode($tests, JSON_PRETTY_PRINT);
