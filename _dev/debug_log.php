<?php
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
header('Content-Type: application/json');
if (!isset($user) || empty($user->login)) { echo json_encode(['error'=>'auth']); exit; }

// Test: verificar que la tabla existe y tiene las columnas correctas
$sql = "DESCRIBE " . MAIN_DB_PREFIX . "re_consulta_log";
$res2 = $db->query($sql);
$cols = [];
if ($res2) {
    while ($o = $db->fetch_object($res2)) $cols[] = $o->Field;
} else {
    echo json_encode(['error' => 'tabla no existe: ' . $db->lasterror()]);
    exit;
}

// Test: insert mínimo
$now = dol_now();
$sql2 = "INSERT INTO " . MAIN_DB_PREFIX . "re_consulta_log
         (fk_consulta, date_log, nota, fk_user, date_creation)
         VALUES (1, '" . $db->idate($now) . "', 'test', " . (int)$user->id . ", '" . $db->idate($now) . "')";
$res3 = $db->query($sql2);
$insertOk = (bool)$res3;
$insertErr = $res3 ? '' : $db->lasterror();
if ($res3) {
    $lastId = $db->last_insert_id(MAIN_DB_PREFIX . 're_consulta_log');
    $db->query("DELETE FROM " . MAIN_DB_PREFIX . "re_consulta_log WHERE rowid = $lastId");
}

echo json_encode([
    'tabla_existe'  => true,
    'columnas'      => $cols,
    'insert_ok'     => $insertOk,
    'insert_error'  => $insertErr,
    'dolibarr_ver'  => DOL_VERSION,
]);
