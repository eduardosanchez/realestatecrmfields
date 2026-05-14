<?php
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

require_once dol_buildpath('/custom/realestatecrmfields/class/regestion.class.php', 0);

$action = GETPOST('action', 'alpha');
$obj    = new ReGestion($db);

if ($action === 'create') {
    $obj->fk_societe_activo       = (int)GETPOST('fk_societe_activo', 'int');
    $obj->fk_societe_propietario  = (int)GETPOST('fk_societe_propietario', 'int') ?: null;
    $obj->propietario_nombre      = GETPOST('propietario_nombre',    'alphanohtml') ?: null;
    $obj->propietario_telefono    = GETPOST('propietario_telefono',  'alphanohtml') ?: null;
    $obj->canal                   = GETPOST('canal',     'alpha');
    $obj->resultado               = GETPOST('resultado', 'alpha');
    $obj->nota                    = GETPOST('nota',      'nohtml') ?: null;
    $obj->fecha_recordatorio      = GETPOST('fecha_recordatorio',  'alpha') ?: null;
    $obj->nota_recordatorio       = GETPOST('nota_recordatorio',   'alphanohtml') ?: null;
    $obj->fk_user_vendedor        = (int)GETPOST('fk_user_vendedor',    'int') ?: null;
    $obj->fk_user_recordatorio    = (int)GETPOST('fk_user_recordatorio','int') ?: null;
    $fechaStr = GETPOST('fecha', 'alpha');
    $obj->fecha = $fechaStr ? strtotime($fechaStr) : dol_now();

    if (!$obj->fk_societe_activo) { echo json_encode(['error' => 'fk_societe_activo requerido']); exit; }

    // Crear Actor si se pidió — operación compuesta con transacción
    $newPropId = null;
    $db->begin();
    try {
        if (GETPOST('crear_propietario', 'int') && $obj->propietario_nombre) {
            require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
            $soc = new Societe($db);
            $soc->nom    = $obj->propietario_nombre;
            $soc->phone  = $obj->propietario_telefono;
            $soc->client = 1;
            $sqlTipo = "SELECT id FROM " . MAIN_DB_PREFIX . "c_typent WHERE code = 'RE_AOR' LIMIT 1";
            $rTipo = $db->query($sqlTipo);
            if ($rTipo && ($oTipo = $db->fetch_object($rTipo))) $soc->typent_id = $oTipo->id;
            $newPropId = $soc->create($user);
            if ($newPropId <= 0) {
                throw new Exception('Error al crear el propietario: ' . ($soc->error ?: 'desconocido'));
            }
            $resUp = $db->query("UPDATE " . MAIN_DB_PREFIX . "societe SET fk_re_subtypent = 'PROPIETARIO' WHERE rowid = " . (int)$newPropId);
            if (!$resUp) throw new Exception('Error al asignar subtipo al propietario: ' . $db->lasterror());
            $obj->fk_societe_propietario = $newPropId;
            $obj->propietario_nombre     = null;
            $obj->propietario_telefono   = null;
        }

        $id = $obj->create($user);
        if ($id < 0) throw new Exception('Error al crear gestión: ' . ($obj->error ?: 'desconocido'));

        $db->commit();
        echo json_encode(['success' => true, 'rowid' => $id, 'new_prop_id' => $newPropId]);
    } catch (Exception $e) {
        $db->rollback();
        dol_syslog('RealEstateCrmFields::gestion_save create - ' . $e->getMessage(), LOG_ERR);
        echo json_encode(['error' => $e->getMessage()]);
    }

} elseif ($action === 'update') {
    $obj->rowid = (int)GETPOST('rowid', 'int');
    $obj->fetch($obj->rowid);
    $obj->fk_societe_propietario = (int)GETPOST('fk_societe_propietario', 'int') ?: null;
    $obj->propietario_nombre     = GETPOST('propietario_nombre',   'alphanohtml') ?: null;
    $obj->propietario_telefono   = GETPOST('propietario_telefono', 'alphanohtml') ?: null;
    $obj->canal                  = GETPOST('canal',     'alpha');
    $obj->resultado              = GETPOST('resultado', 'alpha');
    $obj->nota                   = GETPOST('nota',      'nohtml') ?: null;
    $obj->fecha_recordatorio     = GETPOST('fecha_recordatorio',  'alpha') ?: null;
    $obj->nota_recordatorio      = GETPOST('nota_recordatorio',   'alphanohtml') ?: null;
    $obj->fk_user_vendedor       = (int)GETPOST('fk_user_vendedor',    'int') ?: null;
    $obj->fk_user_recordatorio   = (int)GETPOST('fk_user_recordatorio','int') ?: null;
    $fechaStr = GETPOST('fecha', 'alpha');
    if ($fechaStr) $obj->fecha = strtotime($fechaStr);
    $res2 = $obj->update($user);
    if ($res2 < 0) { echo json_encode(['error' => $obj->error]); exit; }
    echo json_encode(['success' => true]);

} elseif ($action === 'delete') {
    $obj->rowid = (int)GETPOST('rowid', 'int');
    $obj->fetch($obj->rowid);
    $res2 = $obj->delete($user);
    if ($res2 < 0) { echo json_encode(['error' => $obj->error]); exit; }
    echo json_encode(['success' => true]);

} elseif ($action === 'get') {
    $rowid = (int)GETPOST('rowid', 'int');
    $r = $obj->fetch($rowid);
    if ($r < 0) { echo json_encode(['error' => 'not found']); exit; }
    $propNom = '';
    if ($obj->fk_societe_propietario) {
        $rP = $db->query("SELECT nom FROM " . MAIN_DB_PREFIX . "societe WHERE rowid = " . (int)$obj->fk_societe_propietario);
        if ($rP && ($oP = $db->fetch_object($rP))) $propNom = $oP->nom;
    }
    echo json_encode([
        'rowid'                  => $obj->rowid,
        'fk_societe_activo'      => $obj->fk_societe_activo,
        'fk_societe_propietario' => $obj->fk_societe_propietario,
        'propietario_nom'        => $propNom,
        'propietario_nombre'     => $obj->propietario_nombre,
        'propietario_telefono'   => $obj->propietario_telefono,
        'canal'                  => $obj->canal,
        'resultado'              => $obj->resultado,
        'nota'                   => $obj->nota,
        'fecha_recordatorio'     => $obj->fecha_recordatorio,
        'nota_recordatorio'      => $obj->nota_recordatorio,
        'fk_user_vendedor'       => $obj->fk_user_vendedor,
        'fk_user_recordatorio'   => $obj->fk_user_recordatorio,
        'fecha'                  => $obj->fecha,
    ]);

} elseif ($action === 'list_by_activo') {
    $socid = (int)GETPOST('socid', 'int');
    $rows  = $obj->fetchByActivo($socid);
    echo json_encode($rows);

} elseif ($action === 'search_propietarios') {
    $q    = GETPOST('q', 'alphanohtml');
    $rows = ReGestion::searchPropietarios($db, $q);
    echo json_encode($rows);

} else {
    echo json_encode(['error' => 'action desconocida']);
}
