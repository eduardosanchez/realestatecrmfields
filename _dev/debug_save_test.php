<?php
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
header('Content-Type: application/json');
if (!isset($user) || empty($user->login)) { echo json_encode(['error'=>'auth']); exit; }

require_once dol_buildpath('/custom/realestatecrmfields/class/reconsulta.class.php', 0);

// Simular un POST de log_add con datos reales
$_POST['action']      = 'log_add';
$_POST['fk_consulta'] = (int)($_GET['fk_consulta'] ?? 0);
$_POST['nota']        = 'test debug';
$_POST['estado_nuevo'] = '';
$_POST['token']       = $_SESSION['newtoken'] ?? '';

// Ejecutar consulta_save con output buffering para capturar cualquier salida/error
ob_start();
$errBuf = '';
set_error_handler(function($no, $str, $file, $line) use (&$errBuf) {
    $errBuf .= "[$no] $str in $file:$line | ";
    return true;
});

try {
    // Incluir el archivo directamente
    $action = 'log_add';
    
    $rowid       = (int)$_POST['fk_consulta'];
    $nota        = $_POST['nota'];
    $estadoNuevo = '';

    if (!$rowid) { 
        ob_end_clean();
        restore_error_handler();
        echo json_encode(['error' => 'fk_consulta=0, pasá ?fk_consulta=ID en la URL']); 
        exit; 
    }

    $rCons = $db->query("SELECT estado FROM " . MAIN_DB_PREFIX . "re_consulta WHERE rowid = $rowid LIMIT 1");
    if (!$rCons) throw new Exception('query estado: ' . $db->lasterror());
    $oCons = $db->fetch_object($rCons);
    if (!$oCons) throw new Exception("consulta $rowid no encontrada");
    $estadoAnterior = $oCons->estado;

    $now   = dol_now();
    $idate = $db->idate($now);

    $sqlLog = "INSERT INTO " . MAIN_DB_PREFIX . "re_consulta_log
                (fk_consulta, date_log, estado_anterior, estado_nuevo, nota, fk_user, date_creation)
               VALUES (
                $rowid, '$idate',
                " . ($estadoAnterior ? "'" . $db->escape($estadoAnterior) . "'" : 'NULL') . ",
                NULL,
                '" . $db->escape($nota) . "',
                " . (int)$user->id . ",
                '$idate'
               )";

    $resLog = $db->query($sqlLog);
    if (!$resLog) throw new Exception('INSERT failed: ' . $db->lasterror());

    $logId = $db->last_insert_id(MAIN_DB_PREFIX . 're_consulta_log');
    $db->query("DELETE FROM " . MAIN_DB_PREFIX . "re_consulta_log WHERE rowid = $logId");

    $output = ob_get_clean();
    restore_error_handler();
    echo json_encode([
        'success'         => true,
        'log_id'          => $logId,
        'estado_anterior' => $estadoAnterior,
        'errors_caught'   => $errBuf ?: 'none',
        'extra_output'    => $output ?: 'none',
    ]);

} catch (Throwable $e) {
    $output = ob_get_clean();
    restore_error_handler();
    echo json_encode([
        'exception' => $e->getMessage(),
        'file'      => $e->getFile(),
        'line'      => $e->getLine(),
        'errors'    => $errBuf ?: 'none',
        'output'    => $output ?: 'none',
    ]);
}
