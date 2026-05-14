<?php
/**
 * Matching de activos compatibles con un comprador
 * Criterios: rango de precio, barrio/zona, tipo propiedad
 *
 * Parámetros:
 *   monto      — presupuesto central (usa ±40% si no hay min/max)
 *   monto_min  — presupuesto mínimo explícito (opcional)
 *   monto_max  — presupuesto máximo explícito (opcional)
 *   busqueda   — texto libre: barrio, tipo, etc.
 */
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
header('Content-Type: application/json');
if (!isset($user) || empty($user->login)) { echo json_encode([]); exit; }

require_once dol_buildpath('/custom/realestatecrmfields/helpers.php', 0);

$monto     = (float)GETPOST('monto',     'int');
$montoMin  = (float)GETPOST('monto_min', 'int');
$montoMax  = (float)GETPOST('monto_max', 'int');
$busqueda  = GETPOST('busqueda', 'alphanohtml');

$p = MAIN_DB_PREFIX;

// ── Construir condiciones de matching ───────────────────────
$conds = ["s.status = 1",
          "(ef.contactar IS NULL OR ef.contactar = '' OR ef.contactar NOT LIKE 'NO -%')"];

// ── Criterio de precio ───────────────────────────────────────
// Si hay rango explícito (monto_min / monto_max) → usarlo directamente
// Si solo hay monto central → ±40% (comprador puede negociar ±40%)
// Incluir siempre activos sin precio (precio=0) para que el vendedor los evalúe
$precioExpr = "COALESCE(NULLIF(ef.usdventa,0), ef.usdtasacion, 0)";
if ($montoMin > 0 || $montoMax > 0) {
    $min = $montoMin > 0 ? $montoMin : 0;
    $max = $montoMax > 0 ? $montoMax : ($monto > 0 ? $monto * 1.4 : PHP_INT_MAX);
    $conds[] = "($precioExpr BETWEEN $min AND $max OR $precioExpr = 0)";
    $rangoDesc = 'rango USD ' . number_format($min,0,',','.') . ' – ' . number_format($max,0,',','.');
} elseif ($monto > 0) {
    $min = $monto * 0.6;
    $max = $monto * 1.1;
    $conds[] = "($precioExpr BETWEEN $min AND $max OR $precioExpr = 0)";
    $rangoDesc = 'presupuesto USD ' . number_format($monto,0,',','.');
} else {
    $rangoDesc = '';
}

// ── Criterio de barrio/tipo desde texto de búsqueda ─────────
if ($busqueda) {
    $words = array_filter(array_map('trim', preg_split('/[\s,;|]+/', $busqueda)));
    foreach ($words as $w) {
        $esc = $db->escape($w);
        $conds[] = "(ef.barrio LIKE '%$esc%'
                     OR ef.tipodepropiedad LIKE '%$esc%'
                     OR s.fk_re_subtypent LIKE '%$esc%')";
    }
}

$where = implode(" AND ", $conds);

$sql = "SELECT s.rowid, s.nom,
               ef.barrio, ef.tipodepropiedad,
               COALESCE(NULLIF(ef.cocheras_fijas,0), ef.coch_fijas_estimadas, 0) AS cocheras,
               $precioExpr AS precio,
               ef.prioridad_de_contacto,
               ef.plantas,
               COALESCE(ef.supparcela,0) AS supparcela,
               COALESCE(ef.mtsfrente,0) AS mtsfrente,
               COALESCE(ef.mtsfondo,0) AS mtsfondo
        FROM {$p}societe s
        INNER JOIN {$p}c_typent t ON t.id = s.fk_typent AND t.code = 'RE_ACT'
        LEFT JOIN {$p}societe_extrafields ef ON ef.fk_object = s.rowid
        WHERE $where
        ORDER BY
            CASE LOWER(COALESCE(ef.prioridad_de_contacto,''))
                WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3
            END,
            $precioExpr ASC
        LIMIT 12";

$res2 = $db->query($sql);
$rows = [];

if ($res2) {
    while ($o = $db->fetch_object($res2)) {
        $precio   = (float)$o->precio;
        $cocheras = (int)$o->cocheras;
        $sp = (float)($o->supparcela ?? 0);
        $mf = (float)($o->mtsfrente  ?? 0);
        $fd = (float)($o->mtsfondo   ?? 0);
        $sup_total = ($sp > 0 ? $sp : ($mf * $fd)) * (float)($o->plantas ?? 1);
        $rows[] = [
            'rowid'      => $o->rowid,
            'nom'        => $o->nom,
            'barrio'     => $o->barrio,
            'cocheras'   => $cocheras > 0 ? $cocheras : null,
            'precio'     => $precio > 0 ? $precio : null,
            'sin_precio' => $precio === 0.0,
            'usd_x_coch' => ($precio > 0 && $cocheras > 0) ? round($precio / $cocheras) : null,
            'usd_x_m2'   => ($precio > 0 && $sup_total  > 0) ? round($precio / $sup_total) : null,
            'tipoprop'   => $o->tipodepropiedad ? ucfirst(strtolower($o->tipodepropiedad)) : null,
            'prioridad'  => calc_prioridad_php($cocheras, $o->barrio, $o->prioridad_de_contacto),
            'rango_desc' => $rangoDesc,
        ];
    }
}

echo json_encode($rows);
