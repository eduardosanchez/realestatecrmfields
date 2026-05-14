<?php
/* ============================================================
 * AJAX: Retorna los campos ocultos para un tipo+subtipo dado
 * GET ?typent=ACTIVO&subtypent=GARAJE&token=xxx
 * ============================================================ */

// Bootstrap Dolibarr
$res = @include '../../../main.inc.php';
if (!$res) {
    $res = @include '../../../../main.inc.php';
}
if (!$res) {
    http_response_code(403);
    echo json_encode(['error' => 'Bootstrap failed']);
    exit;
}

// Validar token CSRF — compatible con Dolibarr 23
$sentToken = GETPOST('token', 'alpha');
$tokenOk = false;
if (!empty($_SESSION['newtoken']) && $sentToken && hash_equals($_SESSION['newtoken'], $sentToken)) $tokenOk = true;
if (!$tokenOk && !empty($_SESSION['token']) && $sentToken && hash_equals($_SESSION['token'], $sentToken)) $tokenOk = true;
if (!$tokenOk && function_exists('checkToken')) {
    // checkToken sin argumentos — valida el token del POST/GET
    $tokenOk = @checkToken(0);
}
if (!$tokenOk) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

// Validar usuario logueado
if (!isset($user) || empty($user->login)) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

require_once dol_buildpath('/custom/realestatecrmfields/class/realestatecrmfields.class.php', 0);

header('Content-Type: application/json');

$typentCode    = GETPOST('typent',    'alpha');
$subtypentCode = GETPOST('subtypent', 'alpha');

if (!$typentCode) {
    echo json_encode(['hidden' => []]);
    exit;
}

$service      = new RealEstateCrmFields($db);
$hiddenFields = $service->getHiddenFields($typentCode, $subtypentCode ?: '');

echo json_encode(['hidden' => $hiddenFields]);
