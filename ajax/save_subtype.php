<?php
/* AJAX: Guarda el subtipo de un tercero inmediatamente
 * POST: socid, subtypent, token
 */
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Bootstrap failed']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}
if (!isset($user) || empty($user->login)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Validar token CSRF
$sentToken = trim($_POST['token'] ?? '');
$tokenOk   = false;
if (!empty($sentToken)) {
    if (!empty($_SESSION['newtoken']) && hash_equals($_SESSION['newtoken'], $sentToken)) $tokenOk = true;
    if (!$tokenOk && !empty($_SESSION['token']) && hash_equals($_SESSION['token'], $sentToken))  $tokenOk = true;
    if (!$tokenOk && function_exists('checkToken')) {
        $_POST['token'] = $sentToken;
        if (@checkToken($sentToken, '', false)) $tokenOk = true;
    }
}
if (!$tokenOk) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$socid       = (int)($_POST['socid']      ?? 0);
$subtypent   = trim($_POST['subtypent']   ?? '');

if (!$socid) {
    echo json_encode(['success' => false, 'error' => 'Missing socid']);
    exit;
}

require_once dol_buildpath('/custom/realestatecrmfields/class/realestatecrmfields.class.php', 0);

$service = new RealEstateCrmFields($db);
// subtypent vacío = borrar el subtipo
$result = $service->saveSubtype($socid, $subtypent);

echo json_encode([
    'success'  => (bool)$result,
    'error'    => $result ? null : $db->lasterror(),
    'socid'    => $socid,
    'subtypent'=> $subtypent,
]);
