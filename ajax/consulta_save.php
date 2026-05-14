<?php
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
header('Content-Type: application/json');
if (!isset($user) || empty($user->login)) { echo json_encode(['error'=>'auth']); exit; }

// ── Validación CSRF ──────────────────────────────────────────
$action = GETPOST('action', 'alpha');

// Acciones de solo lectura — no requieren token CSRF
$readonly_actions = ['search_activos', 'search_actores', 'get', 'log_list'];

$sentToken = GETPOST('token', 'alpha');
$tokenOk = in_array($action, $readonly_actions); // lectura: siempre OK
if (!$tokenOk) {
    if (function_exists('checkToken')) {
        $tokenOk = checkToken(0);
    } elseif (!empty($_SESSION['newtoken']) && $sentToken) {
        $tokenOk = hash_equals($_SESSION['newtoken'], $sentToken);
    } elseif (!empty($_SESSION['token']) && $sentToken) {
        $tokenOk = hash_equals($_SESSION['token'], $sentToken);
    }
}
if (!$tokenOk) {
    http_response_code(403);
    echo json_encode(['error' => 'token CSRF inválido']);
    exit;
}

require_once dol_buildpath('/custom/realestatecrmfields/class/reconsulta.class.php', 0);
$obj    = new ReConsulta($db);

if ($action === 'create') {
    $obj->fk_societe_activo  = (int)GETPOST('fk_societe_activo', 'int');
    $obj->fk_societe_actor   = (int)GETPOST('fk_societe_actor',  'int') ?: null;
    $obj->actor_nombre       = GETPOST('actor_nombre',    'alphanohtml') ?: null;
    $obj->actor_telefono     = GETPOST('actor_telefono',  'alphanohtml') ?: null;
    $obj->canal              = GETPOST('canal',           'alpha');
    $obj->estado             = GETPOST('estado',          'alpha');
    $obj->nota               = GETPOST('nota',            'nohtml') ?: null;
    $obj->busqueda           = GETPOST('busqueda',        'nohtml') ?: null;
    $obj->rango_usd_min      = GETPOST('rango_usd_min',   'int') ?: null;
    $obj->rango_usd_max      = GETPOST('rango_usd_max',   'int') ?: null;
    $obj->fk_user_vendedor      = (int)GETPOST('fk_user_vendedor', 'int') ?: null;
    $obj->fecha_recordatorio    = GETPOST('fecha_recordatorio', 'alpha') ?: null;
    $obj->nota_recordatorio     = GETPOST('nota_recordatorio',  'alphanohtml') ?: null;
    $obj->fk_user_recordatorio  = (int)GETPOST('fk_user_recordatorio', 'int') ?: null;
    $fechaStr = GETPOST('date_consulta', 'alpha');
    $obj->date_consulta         = $fechaStr ? strtotime($fechaStr) : dol_now();

    // Propiedad no obligatoria — puede registrarse sin activo específico

    // Crear Actor nuevo si se pidió
    $newActorId = null;
    if (GETPOST('crear_actor', 'int') && $obj->actor_nombre) {
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
        $soc = new Societe($db);
        $soc->nom           = $obj->actor_nombre;
        $soc->phone         = $obj->actor_telefono;
        $soc->client        = 1;
        // Tipo Actor
        $sqlTipo = "SELECT id FROM " . MAIN_DB_PREFIX . "c_typent WHERE code = 'RE_AOR' LIMIT 1";
        $rTipo   = $db->query($sqlTipo);
        if ($rTipo && ($oTipo = $db->fetch_object($rTipo))) $soc->typent_id = $oTipo->id;
        $newActorId = $soc->create($user);
        if ($newActorId > 0) {
            // Asignar subtipo PROPIETARIO por defecto
            $db->query("UPDATE " . MAIN_DB_PREFIX . "societe SET fk_re_subtypent = 'PROPIETARIO' WHERE rowid = $newActorId");
            $obj->fk_societe_actor = $newActorId;
            $obj->actor_nombre     = null;
            $obj->actor_telefono   = null;
        }
    }

    $id = $obj->create($user);
    if ($id < 0) { echo json_encode(['error' => $obj->error]); exit; }
    echo json_encode(['success' => true, 'rowid' => $id, 'new_actor_id' => $newActorId]);

} elseif ($action === 'update') {
    $obj->rowid = (int)GETPOST('rowid', 'int');
    $obj->fetch($obj->rowid);
    $obj->fk_societe_actor  = (int)GETPOST('fk_societe_actor',  'int') ?: null;
    $obj->actor_nombre      = GETPOST('actor_nombre',    'alphanohtml') ?: null;
    $obj->actor_telefono    = GETPOST('actor_telefono',  'alphanohtml') ?: null;
    $obj->canal             = GETPOST('canal',           'alpha');
    $obj->estado            = GETPOST('estado',          'alpha');
    $obj->nota              = GETPOST('nota',            'nohtml') ?: null;
    $obj->busqueda          = GETPOST('busqueda',        'nohtml') ?: null;
    $obj->rango_usd_min     = GETPOST('rango_usd_min',   'int') ?: null;
    $obj->rango_usd_max     = GETPOST('rango_usd_max',   'int') ?: null;
    $obj->fk_user_vendedor      = (int)GETPOST('fk_user_vendedor', 'int') ?: null;
    $obj->fecha_recordatorio    = GETPOST('fecha_recordatorio', 'alpha') ?: null;
    $obj->nota_recordatorio     = GETPOST('nota_recordatorio',  'alphanohtml') ?: null;
    $obj->fk_user_recordatorio  = (int)GETPOST('fk_user_recordatorio', 'int') ?: null;
    $fechaStr = GETPOST('date_consulta', 'alpha');
    if ($fechaStr) $obj->date_consulta = strtotime($fechaStr);
    $res2 = $obj->update($user);
    if ($res2 < 0) { echo json_encode(['error' => $obj->error]); exit; }
    echo json_encode(['success' => true]);

} elseif ($action === 'delete') {
    $obj->rowid = (int)GETPOST('rowid', 'int');
    $obj->fetch($obj->rowid);
    // Solo el creador o admin puede borrar
    if ($obj->fk_user_creat != $user->id && empty($user->admin)) {
        echo json_encode(['error' => 'Sin permisos']); exit;
    }
    $res2 = $obj->delete($user);
    if ($res2 < 0) { echo json_encode(['error' => $obj->error]); exit; }
    echo json_encode(['success' => true]);

} elseif ($action === 'search_actores') {
    $q    = GETPOST('q', 'alphanohtml');
    $rows = ReConsulta::searchActores($db, $q);
    echo json_encode($rows);

} elseif ($action === 'list_by_activo') {
    $socid = (int)GETPOST('socid', 'int');
    $rows  = $obj->fetchByActivo($socid);
    echo json_encode($rows);

} elseif ($action === 'list_by_actor') {
    $socid = (int)GETPOST('socid', 'int');
    $rows  = $obj->fetchByActor($socid);
    echo json_encode($rows);

} elseif ($action === 'get') {
    $rowid = (int)GETPOST('rowid', 'int');
    $r = $obj->fetch($rowid);
    if ($r < 0) { echo json_encode(['error' => 'not found']); exit; }
    // Agregar nom del actor si existe
    $actorNom = '';
    if ($obj->fk_societe_actor) {
        $rA = $db->query("SELECT nom FROM " . MAIN_DB_PREFIX . "societe WHERE rowid = " . (int)$obj->fk_societe_actor);
        if ($rA && ($oA = $db->fetch_object($rA))) $actorNom = $oA->nom;
    }
    echo json_encode([
        'rowid'             => $obj->rowid,
        'fk_societe_activo' => $obj->fk_societe_activo,
        'fk_societe_actor'  => $obj->fk_societe_actor,
        'actor_nom'         => $actorNom,
        'actor_nombre'      => $obj->actor_nombre,
        'actor_telefono'    => $obj->actor_telefono,
        'canal'             => $obj->canal,
        'estado'            => $obj->estado,
        'nota'              => $obj->nota,
        'busqueda'          => $obj->busqueda,
        'rango_usd_min'     => $obj->rango_usd_min,
        'rango_usd_max'     => $obj->rango_usd_max,
        'fk_user_vendedor'      => $obj->fk_user_vendedor,
        'date_consulta'         => $obj->date_consulta,
        'fecha_recordatorio'    => $obj->fecha_recordatorio,
        'nota_recordatorio'     => $obj->nota_recordatorio,
        'fk_user_recordatorio'  => $obj->fk_user_recordatorio,
    ]);

} elseif ($action === 'search_activos') {
    $q = $db->escape(trim((string)GETPOST('q', 'alphanohtml')));
    $sql = "SELECT s.rowid, s.nom,
                   ef.calle, ef.numero
            FROM " . MAIN_DB_PREFIX . "societe s
            INNER JOIN " . MAIN_DB_PREFIX . "c_typent t ON t.id = s.fk_typent
            LEFT JOIN " . MAIN_DB_PREFIX . "societe_extrafields ef ON ef.fk_object = s.rowid
            WHERE t.code = 'RE_ACT'
            AND ef.enficha = 1
            AND (s.nom LIKE '%$q%' OR ef.calle LIKE '%$q%'
                 OR CONCAT(ef.calle, ' ', COALESCE(ef.numero,'')) LIKE '%$q%')
            ORDER BY s.nom
            LIMIT 15";
    $res2 = $db->query($sql);
    $rows = [];
    if ($res2) while ($o = $db->fetch_object($res2)) $rows[] = $o;
    echo json_encode($rows);

} elseif ($action === 'log_add') {
    $rowid       = (int)GETPOST('fk_consulta', 'int');
    $nota        = GETPOST('nota',        'alphanohtml');
    $estadoNuevo = GETPOST('estado_nuevo','alpha');

    if (!$rowid) { echo json_encode(['error' => 'fk_consulta requerido']); exit; }

    // Crear tabla si no existe (por si no se ejecutó el SQL manualmente)
    $db->query("CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "re_consulta_log (
        rowid           INT AUTO_INCREMENT PRIMARY KEY,
        fk_consulta     INT NOT NULL,
        date_log        DATETIME NOT NULL,
        estado_anterior VARCHAR(32) DEFAULT NULL,
        estado_nuevo    VARCHAR(32) DEFAULT NULL,
        nota            TEXT DEFAULT NULL,
        fk_user         INT DEFAULT NULL,
        date_creation   DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Leer estado actual de la consulta
    $rCons = $db->query("SELECT estado FROM " . MAIN_DB_PREFIX . "re_consulta WHERE rowid = " . (int)$rowid . " LIMIT 1");
    $estadoAnterior = ($rCons && ($oCons = $db->fetch_object($rCons))) ? $oCons->estado : null;

    $now = dol_now();

    // Insertar log
    $sqlLog = "INSERT INTO " . MAIN_DB_PREFIX . "re_consulta_log
                (fk_consulta, date_log, estado_anterior, estado_nuevo, nota, fk_user, date_creation)
               VALUES (
                " . (int)$rowid . ",
                '" . $db->idate($now) . "',
                " . ($estadoAnterior ? "'" . $db->escape($estadoAnterior) . "'" : 'NULL') . ",
                " . ($estadoNuevo    ? "'" . $db->escape($estadoNuevo)    . "'" : 'NULL') . ",
                " . ($nota           ? "'" . $db->escape($nota)           . "'" : 'NULL') . ",
                " . (int)$user->id . ",
                '" . $db->idate($now) . "'
               )";
    $resLog = $db->query($sqlLog);
    if (!$resLog) { echo json_encode(['error' => $db->lasterror()]); exit; }
    $logId = $db->last_insert_id(MAIN_DB_PREFIX . 're_consulta_log');

    // Actualizar estado de la consulta si cambió
    if ($estadoNuevo && $estadoNuevo !== $estadoAnterior) {
        $motivoCierre = GETPOST('motivo_cierre', 'alpha');
        $fechaCierre  = GETPOST('fecha_cierre',  'alpha');
        $sqlUpd = "UPDATE " . MAIN_DB_PREFIX . "re_consulta SET estado = '" . $db->escape($estadoNuevo) . "'";
        if ($estadoNuevo === 'CERRO') {
            $sqlUpd .= ", motivo_cierre = " . ($motivoCierre ? "'" . $db->escape($motivoCierre) . "'" : 'NULL');
            $sqlUpd .= ", fecha_cierre  = " . ($fechaCierre  ? "'" . $db->escape($fechaCierre)  . "'" : "'" . date('Y-m-d') . "'");
        }
        $sqlUpd .= " WHERE rowid = " . (int)$rowid;
        $db->query($sqlUpd);
    }

    $userNom = trim($user->firstname . ' ' . $user->lastname);
    if (!$userNom) $userNom = $user->login;

    echo json_encode([
        'success'         => true,
        'rowid'           => (int)$logId,
        'date_log'        => dol_print_date($now, 'dayhour'),
        'estado_anterior' => $estadoAnterior,
        'estado_nuevo'    => $estadoNuevo,
        'nota'            => $nota,
        'user_nom'        => $userNom,
        'estado_changed'  => ($estadoNuevo && $estadoNuevo !== $estadoAnterior) ? true : false,
    ]);

} elseif ($action === 'log_list') {
    $rowid = (int)GETPOST('fk_consulta', 'int');
    $sql   = "SELECT l.*, CONCAT(u.firstname, ' ', u.lastname) AS user_nom
              FROM " . MAIN_DB_PREFIX . "re_consulta_log l
              LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = l.fk_user
              WHERE l.fk_consulta = $rowid
              ORDER BY l.date_log ASC";
    $res2  = $db->query($sql);
    $logs  = [];
    while ($res2 && ($o = $db->fetch_object($res2))) {
        $o->date_log = dol_print_date($db->jdate($o->date_log), 'dayhour');
        $logs[] = $o;
    }
    echo json_encode($logs);

} elseif ($action === 'update_busqueda') {
    // Actualiza busqueda_actor en societe_extrafields (fuente de verdad única)
    $socid    = (int)GETPOST('fk_societe_actor', 'int');
    $busqueda = GETPOST('busqueda', 'nohtml');
    if (!$socid) { echo json_encode(['error' => 'socid requerido']); exit; }

    // Asegurar que existe la fila en extrafields
    $db->query("INSERT IGNORE INTO " . MAIN_DB_PREFIX . "societe_extrafields (fk_object) VALUES ($socid)");

    $r = $db->query("UPDATE " . MAIN_DB_PREFIX . "societe_extrafields
        SET busqueda_actor = '" . $db->escape($busqueda) . "'
        WHERE fk_object = $socid");
    echo json_encode(['success' => (bool)$r, 'error' => $r ? null : $db->lasterror()]);

} elseif ($action === 'log_delete') {
    $rowid = (int)GETPOST('rowid', 'int');
    // Solo admin o creador
    $rLog = $db->query("SELECT fk_user FROM " . MAIN_DB_PREFIX . "re_consulta_log WHERE rowid = $rowid LIMIT 1");
    $oLog = ($rLog) ? $db->fetch_object($rLog) : null;
    if (!$oLog || ($oLog->fk_user != $user->id && empty($user->admin))) {
        echo json_encode(['error' => 'Sin permisos']); exit;
    }
    $db->query("DELETE FROM " . MAIN_DB_PREFIX . "re_consulta_log WHERE rowid = $rowid");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'action desconocida']);
}
