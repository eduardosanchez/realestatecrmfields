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

require_once dol_buildpath('/custom/realestatecrmfields/class/repropietario.class.php', 0);

$action = GETPOST('action', 'alpha');
$obj    = new RePropietario($db);

if ($action === 'create') {
    $obj->fk_societe_activo       = (int)GETPOST('fk_societe_activo', 'int');
    $obj->fk_societe_propietario  = (int)GETPOST('fk_societe_propietario', 'int') ?: null;
    $obj->propietario_nombre      = GETPOST('propietario_nombre',   'alphanohtml') ?: null;
    $obj->propietario_telefono    = GETPOST('propietario_telefono', 'alphanohtml') ?: null;
    $obj->rol                     = GETPOST('rol', 'alpha') ?: 'PROPIETARIO';
    $obj->fecha_desde             = GETPOST('fecha_desde', 'alpha') ?: date('Y-m-d');
    $obj->fecha_hasta             = GETPOST('fecha_hasta', 'alpha') ?: null;
    $obj->nota                    = GETPOST('nota', 'nohtml') ?: null;
    $obj->activo                  = 1;

    if (!$obj->fk_societe_activo) { echo json_encode(['error' => 'fk_societe_activo requerido']); exit; }

    // Crear Actor en Dolibarr si se pidió
    $newPropId = null;
    if (GETPOST('crear_actor', 'int') && ($obj->propietario_nombre || $obj->fk_societe_propietario == null)) {
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
        $soc = new Societe($db);
        $soc->nom    = GETPOST('propietario_nombre', 'alphanohtml');
        $soc->phone  = GETPOST('propietario_telefono', 'alphanohtml');
        $soc->client = 1;
        $sqlTipo = "SELECT id FROM " . MAIN_DB_PREFIX . "c_typent WHERE code = 'RE_AOR' LIMIT 1";
        $rTipo = $db->query($sqlTipo);
        if ($rTipo && ($oTipo = $db->fetch_object($rTipo))) $soc->typent_id = $oTipo->id;
        $newPropId = $soc->create($user);
        if ($newPropId > 0) {
            $db->query("UPDATE " . MAIN_DB_PREFIX . "societe SET fk_re_subtypent = 'PROPIETARIO' WHERE rowid = $newPropId");
            $obj->fk_societe_propietario = $newPropId;
            $obj->propietario_nombre     = null;
            $obj->propietario_telefono   = null;
        }
    }

    $id = $obj->create($user);
    if ($id < 0) { echo json_encode(['error' => $obj->error]); exit; }
    echo json_encode(['success' => true, 'rowid' => $id, 'new_actor_id' => $newPropId]);

} elseif ($action === 'update') {
    $obj->rowid = (int)GETPOST('rowid', 'int');
    $obj->fetch($obj->rowid);
    $obj->fk_societe_propietario = (int)GETPOST('fk_societe_propietario', 'int') ?: null;
    $obj->propietario_nombre     = GETPOST('propietario_nombre',   'alphanohtml') ?: null;
    $obj->propietario_telefono   = GETPOST('propietario_telefono', 'alphanohtml') ?: null;
    $obj->rol                    = GETPOST('rol', 'alpha') ?: $obj->rol;
    $obj->fecha_desde            = GETPOST('fecha_desde', 'alpha') ?: $obj->fecha_desde;
    $obj->fecha_hasta            = GETPOST('fecha_hasta', 'alpha') ?: null;
    $obj->nota                   = GETPOST('nota', 'nohtml') ?: null;
    $obj->activo                 = (int)GETPOST('activo', 'int');
    $res2 = $obj->update($user);
    if ($res2 < 0) { echo json_encode(['error' => $obj->error]); exit; }
    echo json_encode(['success' => true]);

} elseif ($action === 'desvincular') {
    $obj->rowid = (int)GETPOST('rowid', 'int');
    $obj->fetch($obj->rowid);
    $res2 = $obj->desvincular($user);
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
        'rol'                    => $obj->rol,
        'fecha_desde'            => $obj->fecha_desde,
        'fecha_hasta'            => $obj->fecha_hasta,
        'activo'                 => $obj->activo,
        'nota'                   => $obj->nota,
    ]);

} elseif ($action === 'list_by_activo') {
    $socid     = (int)GETPOST('socid', 'int');
    $soloAct   = (int)GETPOST('solo_activos', 'int');
    $rows      = $obj->fetchByActivo($socid, (bool)$soloAct);
    echo json_encode($rows);

} elseif ($action === 'search_actores') {
    $q = GETPOST('q', 'alphanohtml');
    $q = $db->escape(trim($q));
    $sql = "SELECT s.rowid, s.nom, s.phone, s.fk_re_subtypent
            FROM " . MAIN_DB_PREFIX . "societe s
            INNER JOIN " . MAIN_DB_PREFIX . "c_typent t ON t.id = s.fk_typent
            WHERE t.code = 'RE_AOR'
            AND (s.nom LIKE '%$q%' OR s.phone LIKE '%$q%')
            ORDER BY s.nom LIMIT 10";
    $rS = $db->query($sql);
    $rows = [];
    if ($rS) while ($o = $db->fetch_object($rS)) $rows[] = $o;
    echo json_encode($rows);

} else {
    echo json_encode(['error' => 'action desconocida']);
}
