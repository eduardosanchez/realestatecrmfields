<?php
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
header('Content-Type: application/json');
if (!isset($user) || empty($user->login)) { echo json_encode(['error'=>'auth']); exit; }

// Simular exactamente lo que hace log_add
$rowid       = (int)($_GET['fk_consulta'] ?? 1);
$nota        = 'test nota debug';
$estadoNuevo = '';

// Paso 1: leer estado actual
$rCons = $db->query("SELECT estado FROM " . MAIN_DB_PREFIX . "re_consulta WHERE rowid = $rowid LIMIT 1");
if (!$rCons) {
    echo json_encode(['step' => 1, 'error' => 'query estado: ' . $db->lasterror()]);
    exit;
}
$oCons = $db->fetch_object($rCons);
if (!$oCons) {
    echo json_encode(['step' => 1, 'error' => "consulta rowid=$rowid no encontrada"]);
    exit;
}
$estadoAnterior = $oCons->estado;

// Paso 2: preparar INSERT
$now    = dol_now();
$idate  = $db->idate($now);

$sqlLog = "INSERT INTO " . MAIN_DB_PREFIX . "re_consulta_log
            (fk_consulta, date_log, estado_anterior, estado_nuevo, nota, fk_user, date_creation)
           VALUES (
            $rowid,
            '$idate',
            " . ($estadoAnterior ? "'" . $db->escape($estadoAnterior) . "'" : 'NULL') . ",
            NULL,
            '" . $db->escape($nota) . "',
            " . (int)$user->id . ",
            '$idate'
           )";

$resLog = $db->query($sqlLog);
if (!$resLog) {
    echo json_encode(['step' => 2, 'error' => 'insert: ' . $db->lasterror(), 'sql' => $sqlLog]);
    exit;
}

$logId = $db->last_insert_id(MAIN_DB_PREFIX . 're_consulta_log');
// Limpiar el test
$db->query("DELETE FROM " . MAIN_DB_PREFIX . "re_consulta_log WHERE rowid = $logId");

echo json_encode([
    'success'         => true,
    'estado_anterior' => $estadoAnterior,
    'idate_test'      => $idate,
    'user_id'         => $user->id,
    'log_id_test'     => $logId,
]);
