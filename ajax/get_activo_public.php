<?php
/**
 * Endpoint público — devuelve datos básicos de un activo para la página de confirmación
 * Conexión directa a DB sin sesión Dolibarr — no requiere login
 *
 * GET ?id=XXXX
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['error' => 'id requerido']); exit; }

// Leer configuración de Dolibarr directamente
$confFile = __DIR__ . '/../../../../conf/conf.php';
if (!file_exists($confFile)) $confFile = __DIR__ . '/../../../conf/conf.php';
if (!file_exists($confFile)) { echo json_encode(['error' => 'conf no encontrada']); exit; }

include $confFile;  // define $dolibarr_main_db_*, $dolibarr_main_url_root, etc.

// Conexión directa a MySQL
$mysqli = new mysqli(
    $dolibarr_main_db_host,
    $dolibarr_main_db_user,
    $dolibarr_main_db_pass,
    $dolibarr_main_db_name
);
if ($mysqli->connect_error) {
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}
$mysqli->set_charset('utf8mb4');

$prefix = $dolibarr_main_db_prefix ?? 'llx_';

// Verificar que es un RE_ACT activo
$stmt = $mysqli->prepare(
    "SELECT s.rowid, s.nom, s.fk_re_subtypent,
            ef.calle, ef.numero, ef.usdventa, ef.enficha
     FROM {$prefix}societe s
     INNER JOIN {$prefix}c_typent t ON t.id = s.fk_typent AND t.code = 'RE_ACT'
     LEFT JOIN {$prefix}societe_extrafields ef ON ef.fk_object = s.rowid
     WHERE s.rowid = ? AND s.status = 1
     LIMIT 1"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || !$result->num_rows) {
    echo json_encode(['error' => 'activo no encontrado']);
    $mysqli->close();
    exit;
}

$o = $result->fetch_object();

// Buscar PDF en /custom/_fichas/{id}.pdf
$baseUrl = rtrim($dolibarr_main_url_root ?? '', '/');
$pdfPath = __DIR__ . '/../../_fichas/' . $id . '.pdf';
$pdf_url = file_exists($pdfPath)
    ? $baseUrl . '/custom/_fichas/' . $id . '.pdf'
    : '';

$dir = trim(($o->calle ?? '') . ' ' . ($o->numero ?? ''));

echo json_encode([
    'rowid'    => $o->rowid,
    'nom'      => $o->nom,
    'dir'      => $dir ?: null,
    'subtipo'  => $o->fk_re_subtypent,
    'monto'    => $o->usdventa,
    'pdf_url'  => $pdf_url,
    'en_ficha' => (int)($o->enficha ?? 0),
]);

$mysqli->close();
