<?php
/**
 * Endpoint dedicado para marcar seguimiento de pendientes
 */
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';

header('Content-Type: application/json');

if (!isset($user) || empty($user->login)) {
    echo json_encode(['error' => 'auth']); exit;
}

// Validar token
$token  = GETPOST('token', 'alphanohtml');
$tokenOk = false;
if (!empty($_SESSION['newtoken']) && $token && hash_equals($_SESSION['newtoken'], $token)) $tokenOk = true;
if (!$tokenOk && !empty($_SESSION['token']) && $token && hash_equals($_SESSION['token'], $token)) $tokenOk = true;
if (!$tokenOk && function_exists('checkToken')) $tokenOk = checkToken(0);
if (!$tokenOk) {
    echo json_encode(['error' => 'token invalido']); exit;
}

// Iniciales del usuario que registra el seguimiento
$iniciales = '';
$fn = trim($user->firstname ?? '');
$ln = trim($user->lastname  ?? '');
if ($fn) $iniciales .= strtoupper(substr($fn, 0, 1));
// Inicial del segundo nombre si hay
$partesFn = explode(' ', $fn);
if (count($partesFn) > 1) $iniciales .= strtoupper(substr($partesFn[1], 0, 1));
if ($ln) $iniciales .= strtoupper(substr($ln, 0, 1));

$action = GETPOST('action', 'alpha');
$rowid  = (int)GETPOST('rowid', 'int');
$p      = MAIN_DB_PREFIX;

if (!$rowid) { echo json_encode(['error' => 'rowid requerido']); exit; }

if ($action === 'done') {
    $nota_done     = trim(GETPOST('nota_done', 'restricthtml'));
    $resultado_seg = GETPOST('resultado_seguimiento', 'alpha');
    $nueva_fecha   = GETPOST('nueva_fecha_recordatorio', 'alpha');
    $nueva_nota    = GETPOST('nueva_nota_recordatorio', 'alphanohtml');

    $res_labels = ['ATENDIO'=>'Atendió','NO_ATENDIO'=>'No contestó','WHATSAPP'=>'WA enviado','OFRECIO'=>'Hizo oferta','SIN_INTERES'=>'Sin interés'];
    $prefix = '';
    if ($resultado_seg && isset($res_labels[$resultado_seg])) {
        $prefix = '[' . $res_labels[$resultado_seg] . ']';
    }
    // Construir nota con iniciales y resultado
    $nota_completa = '';
    if ($prefix)    $nota_completa .= $prefix . ' ';
    if ($nota_done) $nota_completa .= $nota_done;
    if ($iniciales) $nota_completa = rtrim($nota_completa) . ' (' . $iniciales . ')';
    $nota_completa = trim($nota_completa);

    if ($nueva_fecha) {
        $sql = "UPDATE {$p}re_consulta SET
                    fecha_recordatorio = '" . $db->escape($nueva_fecha) . "',
                    nota_recordatorio  = nota_recordatorio"
               . ($nota_completa ? ", nota = CONCAT(COALESCE(nota,''), CHAR(10), '[Seguimiento " . date('d/m/Y') . "]: " . $db->escape($nota_completa) . "')" : "") .
               " WHERE rowid = $rowid";
    } else {
        $sql = "UPDATE {$p}re_consulta SET
                    recordatorio_done = 1,
                    fecha_recordatorio = NULL"
               . ($nota_completa ? ", nota = CONCAT(COALESCE(nota,''), CHAR(10), '[Seguimiento " . date('d/m/Y') . "]: " . $db->escape($nota_completa) . "')" : "") .
               " WHERE rowid = $rowid";
    }

    $res2 = $db->query($sql);
    echo json_encode(['success' => (bool)$res2, 'error' => $res2 ? null : $db->lasterror(), 'iniciales' => $iniciales]);

} elseif ($action === 'done_gest') {
    $nota_done     = trim(GETPOST('nota_done', 'restricthtml'));
    $resultado_seg = GETPOST('resultado_seguimiento', 'alpha');
    $nueva_fecha   = GETPOST('nueva_fecha_recordatorio', 'alpha');
    $nueva_nota    = GETPOST('nueva_nota_recordatorio', 'alphanohtml');

    $res_labels_g = ['ATENDIO'=>'Llamó/habló','NO_ATENDIO'=>'No atendió','WHATSAPP'=>'WA enviado'];
    $prefix = '';
    if ($resultado_seg && isset($res_labels_g[$resultado_seg])) {
        $prefix = '[' . $res_labels_g[$resultado_seg] . ']';
    }
    $nota_completa = '';
    if ($prefix)    $nota_completa .= $prefix . ' ';
    if ($nota_done) $nota_completa .= $nota_done;
    if ($iniciales) $nota_completa = rtrim($nota_completa) . ' (' . $iniciales . ')';
    $nota_completa = trim($nota_completa);

    if ($nueva_fecha) {
        $sql = "UPDATE {$p}re_gestion_propietario SET
                    fecha_recordatorio = '" . $db->escape($nueva_fecha) . "',
                    nota_recordatorio  = nota_recordatorio"
               . ($nota_completa ? ", nota = CONCAT(COALESCE(nota,''), CHAR(10), '[Seguimiento " . date('d/m/Y') . "]: " . $db->escape($nota_completa) . "')" : "") .
               " WHERE rowid = $rowid";
    } else {
        $sql = "UPDATE {$p}re_gestion_propietario SET
                    recordatorio_done = 1,
                    fecha_recordatorio = NULL"
               . ($nota_completa ? ", nota = CONCAT(COALESCE(nota,''), CHAR(10), '[Seguimiento " . date('d/m/Y') . "]: " . $db->escape($nota_completa) . "')" : "") .
               " WHERE rowid = $rowid";
    }

    $res2 = $db->query($sql);
    echo json_encode(['success' => (bool)$res2, 'error' => $res2 ? null : $db->lasterror()]);

} else {
    echo json_encode(['error' => 'action desconocida']);
}
