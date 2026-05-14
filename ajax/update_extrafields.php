<?php
/**
 * Actualiza extrafields de un tercero (usdtasacion)
 */
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
header('Content-Type: application/json');
if (!isset($user) || empty($user->login)) { echo json_encode(['error'=>'auth']); exit; }

// ── Validación CSRF ──────────────────────────────────────────
$sentToken = GETPOST('token', 'alpha');
$tokenOk = false;
if (function_exists('checkToken')) {
    $tokenOk = checkToken(0);
} elseif (!empty($_SESSION['newtoken']) && $sentToken) {
    $tokenOk = hash_equals($_SESSION['newtoken'], $sentToken);
} elseif (!empty($_SESSION['token']) && $sentToken) {
    $tokenOk = hash_equals($_SESSION['token'], $sentToken);
}
if (!$tokenOk) {
    http_response_code(403);
    echo json_encode(['error' => 'token CSRF inválido']);
    exit;
}

$socid        = (int)GETPOST('socid', 'int');
$usdTasacion  = GETPOST('usdtasacion',   'int');

if (!$socid) { echo json_encode(['error' => 'socid requerido']); exit; }

$sets = [];
if ($usdTasacion  >   0) $sets[] = "usdtasacion   = "  . (int)$usdTasacion;

if (empty($sets)) { echo json_encode(['success' => true, 'msg' => 'nada que actualizar']); exit; }

$sql = "UPDATE " . MAIN_DB_PREFIX . "societe_extrafields SET " . implode(', ', $sets) . " WHERE fk_object = " . $socid;
$res2 = $db->query($sql);
if (!$res2) { echo json_encode(['error' => $db->lasterror()]); exit; }

echo json_encode(['success' => true]);
