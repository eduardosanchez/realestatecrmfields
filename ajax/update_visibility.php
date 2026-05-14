<?php
/* ============================================================
 * AJAX: Guarda o elimina la visibilidad de un extrafield
 * POST: field_id, typent, subtypent, visible, token
 * ============================================================ */

// Capturar errores PHP como JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (headers_sent()) { exit; }
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => "PHP Error [$errno]: $errstr in " . basename($errfile) . ":$errline"
    ]);
    exit;
});

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

if (empty($user->admin)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

// Leer parámetros
$input = $_POST;

// Validar token CSRF — compatible Dolibarr 22
$sentToken = trim($input['token'] ?? '');
$tokenOk   = false;

if (!empty($sentToken)) {
    // Dolibarr 22 guarda el token en $_SESSION['newtoken']
    if (!empty($_SESSION['newtoken']) && hash_equals($_SESSION['newtoken'], $sentToken)) {
        $tokenOk = true;
    }
    if (!$tokenOk && !empty($_SESSION['token']) && hash_equals($_SESSION['token'], $sentToken)) {
        $tokenOk = true;
    }
    // Fallback: función nativa
    if (!$tokenOk && function_exists('checkToken')) {
        $_POST['token'] = $sentToken;
        if (@checkToken($sentToken, '', false)) {
            $tokenOk = true;
        }
    }
}

if (!$tokenOk) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Invalid CSRF token',
        'debug_sent' => substr($sentToken, 0, 8),
        'debug_sess' => isset($_SESSION['newtoken']) ? substr($_SESSION['newtoken'], 0, 8) : 'n/a',
    ]);
    exit;
}

$fieldId       = (int)($input['field_id']  ?? 0);
$typentCode    = trim($input['typent']     ?? '');
$subtypentCode = trim($input['subtypent']  ?? '') ?: null;
$visible       = (int)($input['visible']   ?? 0);

if (!$fieldId || !$typentCode) {
    echo json_encode(['success' => false, 'error' => 'Missing params', 'field_id' => $fieldId, 'typent' => $typentCode]);
    exit;
}

require_once dol_buildpath('/custom/realestatecrmfields/class/realestatecrmfields.class.php', 0);

$service = new RealEstateCrmFields($db);
$result  = $service->setFieldVisibility($fieldId, $typentCode, $subtypentCode, (bool)$visible);

echo json_encode([
    'success'  => (bool)$result,
    'error'    => $result ? null : $db->lasterror(),
    'field_id' => $fieldId,
    'typent'   => $typentCode,
    'subtypent'=> $subtypentCode,
    'visible'  => $visible,
]);
