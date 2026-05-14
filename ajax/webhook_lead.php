<?php
/**
 * Webhook de entrada de leads desde WPForms
 * Conexión directa a DB — no usa main.inc.php ni requiere sesión Dolibarr
 * No afecta actualizaciones de Dolibarr
 *
 * POST params:
 *   nombre, email, telefono, tipo_interesado, comentario
 *   activo_id, origen, webhook_secret
 *
 * Responde JSON: { success, actor_id, consulta_id, action, redirect_url }
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://esanchez.com.ar');

// ── Leer configuración de Dolibarr ───────────────────────────
$confFile = __DIR__ . '/../../../../conf/conf.php';
if (!file_exists($confFile)) $confFile = __DIR__ . '/../../../conf/conf.php';
if (!file_exists($confFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'conf.php no encontrado']);
    exit;
}
include $confFile;

// ── Seguridad: secret compartido ─────────────────────────────
$WEBHOOK_SECRET = defined('RE_WEBHOOK_SECRET') ? RE_WEBHOOK_SECRET
                : ($dolibarr_re_webhook_secret ?? '');
$secret_recibido = $_POST['webhook_secret'] ?? $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
if ($WEBHOOK_SECRET && $secret_recibido !== $WEBHOOK_SECRET) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Conexión directa a MySQL ──────────────────────────────────
$db = new mysqli(
    $dolibarr_main_db_host,
    $dolibarr_main_db_user,
    $dolibarr_main_db_pass,
    $dolibarr_main_db_name
);
if ($db->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}
$db->set_charset('utf8mb4');
$p = $dolibarr_main_db_prefix ?? 'llx_';

function esc($db, $val) { return $db->real_escape_string(trim($val ?? '')); }
function now_sql() { return gmdate('Y-m-d H:i:s'); } // UTC igual que Dolibarr

/**
 * Registra un intento de webhook en re_webhook_log.
 * Permite auditar qué llegó, qué se creó, y qué falló.
 *
 * @param mysqli $db
 * @param string $p          prefijo de tablas
 * @param string $estado     'OK' | 'ERROR' | 'DUPLICADO'
 * @param string $mensaje    descripción del resultado
 * @param array  $contexto   datos extra (actor_id, consulta_id, etc.)
 */
function re_webhook_log(mysqli $db, string $p, string $estado, string $mensaje, array $contexto = []): void {
    $now     = now_sql();
    $estado  = $db->real_escape_string(substr($estado,  0, 20));
    $mensaje = $db->real_escape_string(substr($mensaje, 0, 500));
    $ctx     = $db->real_escape_string(json_encode($contexto, JSON_UNESCAPED_UNICODE));

    // I-C: crear la tabla solo una vez por request, no en cada llamada al log
    static $log_table_ok = false;
    if ( ! $log_table_ok ) {
        $db->query("CREATE TABLE IF NOT EXISTS {$p}re_webhook_log (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            fecha       DATETIME     NOT NULL,
            estado      VARCHAR(20)  NOT NULL,
            mensaje     VARCHAR(500) NOT NULL,
            contexto    TEXT,
            ip          VARCHAR(45)  DEFAULT '',
            INDEX idx_fecha  (fecha),
            INDEX idx_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $log_table_ok = true;
    }

    $ip = substr($_SERVER['HTTP_CF_CONNECTING_IP']
              ?? $_SERVER['HTTP_X_FORWARDED_FOR']
              ?? $_SERVER['REMOTE_ADDR']
              ?? '', 0, 45);
    $ip = $db->real_escape_string(trim(explode(',', $ip)[0]));

    $db->query("INSERT INTO {$p}re_webhook_log (fecha, estado, mensaje, contexto, ip)
                VALUES ('$now', '$estado', '$mensaje', '$ctx', '$ip')");
}

// ── Leer datos del POST ───────────────────────────────────────
$nombre     = esc($db, $_POST['nombre']          ?? '');
$email      = strtolower(esc($db, $_POST['email'] ?? ''));
$telefono   = esc($db, $_POST['telefono']         ?? '');
$tipo       = esc($db, $_POST['tipo_interesado']  ?? '');
$comentario = esc($db, $_POST['comentario']       ?? '');
$motivo     = esc($db, $_POST['motivo_interes']   ?? ''); // "¿Qué buscás?"
$activo_id  = (int)($_POST['activo_id']           ?? 0);
$origen     = esc($db, $_POST['origen']           ?? 'web');

if (!$nombre || (!$email && !$telefono)) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos insuficientes: nombre y email o teléfono requeridos']);
    $db->close(); exit;
}

// ── Fix 2: deduplicar por TELÉFONO primero, luego email ─────────
$actorId = 0;
$accion  = 'created';

if ($telefono) {
    $telLimpio = preg_replace('/\D/', '', $telefono);
    $tel8 = esc($db, substr($telLimpio, -8));
    $r1 = $db->query("SELECT s.rowid FROM {$p}societe s
                      INNER JOIN {$p}c_typent t ON t.id = s.fk_typent AND t.code = 'RE_AOR'
                      WHERE REGEXP_REPLACE(s.phone, '[^0-9]', '') LIKE '%$tel8'
                      LIMIT 1");
    if ($r1 && $r1->num_rows) {
        $actorId = (int)$r1->fetch_object()->rowid;
        $accion  = 'updated';
    }
}

if (!$actorId && $email) {
    $r2 = $db->query("SELECT s.rowid FROM {$p}societe s
                      INNER JOIN {$p}c_typent t ON t.id = s.fk_typent AND t.code = 'RE_AOR'
                      WHERE s.email = '$email' LIMIT 1");
    if ($r2 && $r2->num_rows) {
        $actorId = (int)$r2->fetch_object()->rowid;
        $accion  = 'updated';
    }
}

// ── Obtener id del tipo RE_AOR ────────────────────────────────
$rTipo     = $db->query("SELECT id FROM {$p}c_typent WHERE code = 'RE_AOR' LIMIT 1");
$idTipoAor = ($rTipo && $rTipo->num_rows) ? (int)$rTipo->fetch_object()->id : 0;

// ── Mapeo tipo → subtipo ──────────────────────────────────────
$subtipoMap = ['INVERSOR'=>'INVERSOR','OPERADOR'=>'OPERADOR','CORREDOR'=>'CORREDOR',
               'inversor'=>'INVERSOR','operador'=>'OPERADOR','corredor'=>'CORREDOR'];
$subtipo = $subtipoMap[$tipo] ?? 'INVERSOR';

// ── Crear o actualizar ThirdParty ─────────────────────────────
if (!$actorId) {
    $now = now_sql();
    $db->query("INSERT INTO {$p}societe
                (nom, email, phone, client, fournisseur, fk_typent, fk_re_subtypent,
                 status, datec, tms, entity)
                VALUES
                ('$nombre', '$email', '$telefono', 1, 0,
                 $idTipoAor, '$subtipo', 1, '$now', '$now', 1)");
    if ($db->error) {
        $errMsg = 'Error INSERT societe: ' . $db->error;
        re_webhook_log($db, $p, 'ERROR', $errMsg, [
            'nombre' => $nombre, 'email' => $email, 'telefono' => $telefono,
        ]);
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo crear el tercero', 'detalle' => $db->error]);
        $db->close(); exit;
    }
    $actorId = $db->insert_id;

    $db->query("INSERT IGNORE INTO {$p}societe_extrafields (fk_object, origen_lead)
                VALUES ($actorId, '$origen')");
    if ($db->error) {
        re_webhook_log($db, $p, 'ERROR', 'Error INSERT societe_extrafields: ' . $db->error,
                       ['actor_id' => $actorId]);
    }
} else {
    $rGet = $db->query("SELECT email, phone FROM {$p}societe WHERE rowid = $actorId");
    if ($rGet && ($oGet = $rGet->fetch_object())) {
        $sets = [];
        if (!$oGet->email && $email)    $sets[] = "email = '$email'";
        if (!$oGet->phone && $telefono) $sets[] = "phone = '$telefono'";
        if (!empty($sets)) {
            $db->query("UPDATE {$p}societe SET " . implode(', ', $sets) . " WHERE rowid = $actorId");
            if ($db->error) {
                re_webhook_log($db, $p, 'ERROR', 'Error UPDATE societe: ' . $db->error,
                               ['actor_id' => $actorId]);
            }
        }
    }
    $rSub = $db->query("SELECT fk_re_subtypent FROM {$p}societe WHERE rowid = $actorId");
    if ($rSub && ($oSub = $rSub->fetch_object()) && !$oSub->fk_re_subtypent) {
        $db->query("UPDATE {$p}societe SET fk_re_subtypent = '$subtipo' WHERE rowid = $actorId");
    }
    $db->query("INSERT INTO {$p}societe_extrafields (fk_object, origen_lead)
                VALUES ($actorId, '$origen')
                ON DUPLICATE KEY UPDATE
                origen_lead = IF(origen_lead IS NULL OR origen_lead = '', '$origen', origen_lead)");
    if ($db->error) {
        re_webhook_log($db, $p, 'ERROR', 'Error UPSERT societe_extrafields: ' . $db->error,
                       ['actor_id' => $actorId]);
    }
}

// ── Recuperar datos del activo ────────────────────────────────
$montoActivo = null;
$activoNom   = null;
if ($activo_id) {
    $rAct = $db->query("SELECT s.nom, ef.usdventa
                        FROM {$p}societe s
                        LEFT JOIN {$p}societe_extrafields ef ON ef.fk_object = s.rowid
                        WHERE s.rowid = $activo_id LIMIT 1");
    if ($rAct && $rAct->num_rows) {
        $oAct        = $rAct->fetch_object();
        $activoNom   = $oAct->nom;
        $montoActivo = $oAct->usdventa;
    }
    if ($montoActivo) {
        $montoEsc = esc($db, $montoActivo);
        $db->query("INSERT INTO {$p}societe_extrafields (fk_object, monto_inversion_usd)
                    VALUES ($actorId, '$montoEsc')
                    ON DUPLICATE KEY UPDATE
                    monto_inversion_usd = IF(monto_inversion_usd IS NULL OR monto_inversion_usd = 0,
                                            '$montoEsc', monto_inversion_usd)");
    }
}

// ── Canal según origen ────────────────────────────────────────
$canalMap = ['instagram'=>'INSTAGRAM','whatsapp'=>'WHATSAPP','email'=>'EMAIL','ads'=>'INSTAGRAM'];
$canal = $canalMap[strtolower($origen)] ?? 'WHATSAPP';
$utmSource = esc($db, $_POST['utm_source'] ?? '');
if ($utmSource && !isset($canalMap[strtolower($origen)])) {
    $canal = strtoupper($utmSource);
}
$origenLabel = strtolower($origen);

// ── Deduplicar consulta ───────────────────────────────────────
$consultaId        = 0;
$consultaExistente = 0;
if ($activo_id) {
    $rDupC = $db->query("SELECT rowid FROM {$p}re_consulta
                         WHERE fk_societe_actor = $actorId
                           AND fk_societe_activo = $activo_id
                           AND estado NOT IN ('COMPRA','NO_INTERES','PRECIO','OTRO_INMUEBLE','SIN_RESOLUCION')
                         ORDER BY date_consulta DESC
                         LIMIT 1");
    if ($rDupC && $rDupC->num_rows) {
        $consultaExistente = (int)$rDupC->fetch_object()->rowid;
    }
}

// ── Registrar consulta ────────────────────────────────────────
$nota     = $comentario ?: ('Lead ' . $origenLabel . ($activoNom ? ' — ' . $activoNom : ''));
$partes   = [];
if ($tipo)   $partes[] = 'Tipo: ' . $tipo;
if ($motivo) $partes[] = 'Busca: ' . $motivo;
$busqueda  = implode(' | ', $partes);
$now       = now_sql();
$activoVal = $activo_id ?: 'NULL';

$fecha_rec = null;
$nota_rec  = '';
$rRec = $db->query("SELECT rowid FROM {$p}re_consulta
                    WHERE fk_societe_actor = $actorId
                      AND recordatorio_done = 0
                      AND fecha_recordatorio IS NOT NULL
                      AND fecha_recordatorio >= NOW()
                    LIMIT 1");
if (!$rRec || !$rRec->num_rows) {
    $fecha_rec = date('Y-m-d H:i:s', strtotime('+3 days'));
    $nota_rec  = esc($db, 'Primer contacto — descargó ficha' . ($activoNom ? ': ' . $activoNom : ''));
}

if ($consultaExistente) {
    $consultaId = $consultaExistente;
    $db->query("INSERT INTO {$p}re_consulta_log
                (fk_consulta, date_log, estado_anterior, estado_nuevo, nota, fk_user, date_creation)
                VALUES ($consultaId, '$now', 'CONSULTO', 'CONSULTO',
                        'Recontacto desde $origenLabel — lead duplicado', 0, '$now')");
    if ($db->error) {
        re_webhook_log($db, $p, 'ERROR', 'Error INSERT consulta_log (recontacto): ' . $db->error,
                       ['consulta_id' => $consultaId]);
    }
} else {
    $fechaRecVal = $fecha_rec ? "'" . $fecha_rec . "'" : 'NULL';
    $notaRecVal  = $fecha_rec ? "'" . $nota_rec  . "'" : "''";
    $db->query("INSERT INTO {$p}re_consulta
                (date_consulta, fk_societe_activo, fk_societe_actor,
                 canal, estado, nota, busqueda,
                 fecha_recordatorio, nota_recordatorio, recordatorio_done,
                 date_creation, fk_user_creat)
                VALUES
                ('$now', $activoVal, $actorId,
                 '$canal', 'CONSULTO', '$nota', '$busqueda',
                 $fechaRecVal, $notaRecVal, 0,
                 '$now', 0)");
    if ($db->error) {
        re_webhook_log($db, $p, 'ERROR', 'Error INSERT re_consulta: ' . $db->error, [
            'actor_id'  => $actorId,
            'activo_id' => $activo_id,
            'canal'     => $canal,
        ]);
        // No abortamos — el actor existe, la consulta falló pero el lead está en el sistema
    }
    $consultaId = $db->insert_id;
    if ($consultaId) {
        $db->query("INSERT INTO {$p}re_consulta_log
                    (fk_consulta, date_log, estado_anterior, estado_nuevo, nota, fk_user, date_creation)
                    VALUES ($consultaId, '$now', '', 'CONSULTO',
                            'Lead automático desde $origenLabel', 0, '$now')");
    }
}

// ── C1 FIX: verificar que el lead existe antes de generar token ──
// Si $actorId = 0 a esta altura, algo falló silenciosamente en la creación
// del ThirdParty. En ese caso no generamos token ni redirigimos a /descarga/.
if (!$actorId) {
    re_webhook_log($db, $p, 'ERROR', 'Lead no creado — actorId=0 al generar token', [
        'nombre' => $nombre, 'email' => $email, 'origen' => $origen,
    ]);
    $db->close();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'No se pudo registrar el lead. Por favor contactanos directamente.',
    ]);
    exit;
}

// ── Log de éxito ──────────────────────────────────────────────
re_webhook_log($db, $p, 'OK', $accion === 'created' ? 'Lead creado' : 'Lead actualizado', [
    'actor_id'    => $actorId,
    'consulta_id' => $consultaId,
    'activo_id'   => $activo_id,
    'accion'      => $accion,
    'origen'      => $origen,
]);

$db->close();

// ── Token firmado para acceso seguro al PDF ───────────────────
// El secret se lee desde conf.php de Dolibarr (variable $dolibarr_re_ficha_secret)
// o desde la constante RE_FICHA_SECRET si está definida en el entorno.
// Debe ser idéntico al define RE_FICHA_SECRET en wp-config.php de WordPress.
//
// Para configurarlo en Dolibarr, agregar en conf/conf.php:
//   $dolibarr_re_ficha_secret = 'TU_SECRET_AQUI';  // mismo valor que en wp-config.php
//   $dolibarr_re_token_ttl    = 86400;              // 24 horas
$RE_FICHA_SECRET = defined('RE_FICHA_SECRET') ? RE_FICHA_SECRET
                 : ($dolibarr_re_ficha_secret ?? '');
$RE_TOKEN_TTL    = defined('RE_TOKEN_TTL')    ? RE_TOKEN_TTL
                 : ($dolibarr_re_token_ttl    ?? 86400);

$tok = '';
if ($activo_id && $RE_FICHA_SECRET) {
    $ts   = time();
    $hmac = hash_hmac('sha256', $activo_id . ':' . $ts, $RE_FICHA_SECRET);
    $raw  = $activo_id . ':' . $ts . ':' . $hmac;
    $tok  = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

// URL de redirección con token incluido
// Se agrega el email hasheado (no en claro) para poder reenviar el link si el token expira.
// El hash permite identificar el destinatario sin exponer el email en la URL.
$email_hash = $email ? base64_encode($email) : '';
$redirect_url = 'https://esanchez.com.ar/descarga/'
              . '?activo=' . $activo_id
              . '&origen=' . urlencode($origen)
              . ($tok ? '&tok=' . urlencode($tok) : '')
              . ($email_hash ? '&eh=' . urlencode($email_hash) : '');

// ── Respuesta ─────────────────────────────────────────────────
echo json_encode([
    'success'      => true,
    'action'       => $accion,
    'actor_id'     => $actorId,
    'consulta_id'  => $consultaId,
    'activo_nom'   => $activoNom,
    'monto'        => $montoActivo,
    'redirect_url' => $redirect_url,
]);
