<?php
/**
 * Lista de Actores CRM Inmobiliario
 * URL: /custom/realestatecrmfields/actores.php
 */

// Bootstrap Dolibarr
$res = @include '../../main.inc.php';
if (!$res) $res = @include '../../../main.inc.php';
if (!$res) die('main.inc.php not found');

require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once dol_buildpath('/custom/realestatecrmfields/class/reconsulta.class.php', 0);
require_once __DIR__ . '/helpers.php';

$langs->load('companies');
$langs->load('realestatecrmfields');

// ── Parámetros de filtro / orden ────────────────────────────
$search_nom       = GETPOST('search_nom',       'alphanohtml');
$search_phone     = GETPOST('search_phone',     'alphanohtml');
$search_subtipo   = GETPOST('search_subtipo',   'alpha');
$search_status = isset($_GET['search_status']) ? (int)$_GET['search_status'] : 1;
$search_usd_min   = GETPOST('search_usd_min',   'int');
$search_usd_max   = GETPOST('search_usd_max',   'int');
$search_busqueda    = GETPOST('search_busqueda',    'alphanohtml');
$search_activo_sub   = GETPOST('search_activo_sub',   'alpha');
$search_pendiente    = GETPOST('search_pendiente',    'alpha');
$search_sin_contacto = (int)GETPOST('search_sin_contacto', 'int');

$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'cq.ultima_consulta';
$sortorder = GETPOST('sortorder', 'aZ09')      ?: 'DESC';
$page      = max(0, (int)GETPOST('page', 'int'));
$limit     = 50;
$offset    = $page * $limit;

$subtipos = ReConsulta::searchActores($db, ''); // solo para el select — reusamos el método vacío
// Cargar subtipos de RE_AOR directamente
$sqlSubs = "SELECT code, libelle FROM " . MAIN_DB_PREFIX . "c_re_subtypent WHERE fk_typent = 'RE_AOR' AND active = 1 ORDER BY position";
$resSubs = $db->query($sqlSubs);
$subtipo_opts = [];
while ($resSubs && ($o = $db->fetch_object($resSubs))) $subtipo_opts[$o->code] = $o->libelle;

$sqlSubsAct = "SELECT code, libelle FROM " . MAIN_DB_PREFIX . "c_re_subtypent WHERE fk_typent = 'RE_ACT' AND active = 1 ORDER BY position";
$resSubsAct = $db->query($sqlSubsAct);
$subtipo_act_opts = [];
while ($resSubsAct && ($o = $db->fetch_object($resSubsAct))) $subtipo_act_opts[$o->code] = $o->libelle;

// ── Query principal ─────────────────────────────────────────
$sqlFrom = "FROM " . MAIN_DB_PREFIX . "societe s
            INNER JOIN " . MAIN_DB_PREFIX . "c_typent t ON t.id = s.fk_typent AND t.code = 'RE_AOR'
            LEFT JOIN " . MAIN_DB_PREFIX . "c_re_subtypent sub ON sub.code = s.fk_re_subtypent
            LEFT JOIN " . MAIN_DB_PREFIX . "societe_extrafields ef_actor ON ef_actor.fk_object = s.rowid
            LEFT JOIN (
                SELECT
                    c.fk_societe_actor,
                    COUNT(*)                                      AS total_consultas,
                    MAX(c.date_consulta)                          AS ultima_consulta,
                    GROUP_CONCAT(DISTINCT c.busqueda ORDER BY c.date_consulta DESC SEPARATOR ' | ') AS busquedas,
                    GROUP_CONCAT(DISTINCT sa.fk_re_subtypent ORDER BY c.date_consulta DESC SEPARATOR ',') AS activos_subtipo,
                    SUBSTRING_INDEX(GROUP_CONCAT(c.canal    ORDER BY c.date_consulta DESC SEPARATOR '§'), '§', 1) AS ult_canal,
                    SUBSTRING_INDEX(GROUP_CONCAT(c.estado   ORDER BY c.date_consulta DESC SEPARATOR '§'), '§', 1) AS ult_estado,
                    SUBSTRING_INDEX(GROUP_CONCAT(LEFT(c.nota, 120) ORDER BY c.date_consulta DESC SEPARATOR '§'), '§', 1) AS ult_nota,
                    SUBSTRING_INDEX(GROUP_CONCAT(c.fecha_recordatorio ORDER BY c.date_consulta DESC SEPARATOR '§'), '§', 1) AS ult_recordatorio,
                    SUBSTRING_INDEX(GROUP_CONCAT(c.rowid    ORDER BY c.date_consulta DESC SEPARATOR '§'), '§', 1) AS ult_rowid,
                    CASE WHEN MAX(c.date_consulta) IS NOT NULL AND MAX(c.date_consulta) > 0
                         THEN DATEDIFF(CURDATE(), DATE(FROM_UNIXTIME(MAX(c.date_consulta))))
                         ELSE NULL END AS dias_sin_contacto,
                    SUM(CASE WHEN c.recordatorio_done = 0 AND c.fecha_recordatorio IS NOT NULL AND c.fecha_recordatorio <= CURDATE() THEN 1 ELSE 0 END) AS pendientes_vencidos,
                    SUBSTRING_INDEX(GROUP_CONCAT(
                        TRIM(CONCAT(COALESCE(efs.calle,''), IF(efs.numero IS NOT NULL AND efs.numero != '', CONCAT(' ', efs.numero), '')))
                        ORDER BY c.date_consulta DESC SEPARATOR '§'
                    ), '§', 1) AS ult_activo_dir
                FROM " . MAIN_DB_PREFIX . "re_consulta c
                LEFT JOIN " . MAIN_DB_PREFIX . "societe sa ON sa.rowid = c.fk_societe_activo
                LEFT JOIN " . MAIN_DB_PREFIX . "societe_extrafields efs ON efs.fk_object = c.fk_societe_activo
                WHERE c.fk_societe_actor IS NOT NULL
                GROUP BY c.fk_societe_actor
            ) cq ON cq.fk_societe_actor = s.rowid";

$sqlWhere = " WHERE 1=1";
if ($search_nom)     $sqlWhere .= " AND s.nom LIKE '%" . $db->escape($search_nom) . "%'";
if ($search_phone)   $sqlWhere .= " AND s.phone LIKE '%" . $db->escape($search_phone) . "%'";
if ($search_subtipo) $sqlWhere .= " AND s.fk_re_subtypent = '" . $db->escape($search_subtipo) . "'";
if ($search_status == 1) $sqlWhere .= " AND s.status = 1";
if ($search_status == 0) $sqlWhere .= " AND s.status = 0";
if ($search_usd_min) $sqlWhere .= " AND CAST(ef_actor.monto_inversion_usd AS UNSIGNED) >= " . (int)$search_usd_min;
if ($search_usd_max) $sqlWhere .= " AND CAST(ef_actor.monto_inversion_usd AS UNSIGNED) <= " . (int)$search_usd_max;
if ($search_busqueda)   $sqlWhere .= " AND cq.busquedas LIKE '%" . $db->escape($search_busqueda) . "%'";
if ($search_activo_sub) $sqlWhere .= " AND FIND_IN_SET('" . $db->escape($search_activo_sub) . "', cq.activos_subtipo)";
if ($search_pendiente === 'hoy') {
    $sqlWhere .= " AND cq.ult_recordatorio IS NOT NULL AND cq.ult_recordatorio <= CURDATE()";
    $sqlWhere .= " AND cq.pendientes_vencidos > 0";
}
if ($search_sin_contacto > 0) $sqlWhere .= " AND COALESCE(cq.dias_sin_contacto, 9999) >= " . (int)$search_sin_contacto;

// Count
$sqlCount = "SELECT COUNT(*) as total $sqlFrom $sqlWhere";
$resCount = $db->query($sqlCount);
$total    = ($resCount) ? $db->fetch_object($resCount)->total : 0;
if ($resCount) $db->free($resCount);

// Badge caliente: actores con recordatorio vencido hoy
$p = MAIN_DB_PREFIX;
$sqlCaliente = "SELECT COUNT(DISTINCT s.rowid) as cnt
    FROM {$p}societe s
    INNER JOIN {$p}c_typent t ON t.id = s.fk_typent AND t.code = 'RE_AOR'
    INNER JOIN {$p}re_consulta c ON c.fk_societe_actor = s.rowid
    WHERE s.status = 1
      AND c.recordatorio_done = 0
      AND c.fecha_recordatorio IS NOT NULL
      AND c.fecha_recordatorio <= CURDATE()";
$resCaliente = $db->query($sqlCaliente);
$totalCaliente = ($resCaliente) ? (int)$db->fetch_object($resCaliente)->cnt : 0;
if ($resCaliente) $db->free($resCaliente);

// Ordenamiento permitido
$allowed_sort = ['s.nom','s.phone','s.status','s.fk_re_subtypent','cq.total_consultas','cq.ultima_consulta','ef_actor.monto_inversion_usd'];
if (!in_array($sortfield, $allowed_sort)) $sortfield = 's.nom';
$sortorder = ($sortorder === 'DESC') ? 'DESC' : 'ASC';

$sqlSelect = "SELECT s.rowid, s.nom, s.phone, s.email, s.status,
                     s.fk_re_subtypent,
                     sub.libelle AS subtipo_label,
                     ef_actor.monto_inversion_usd,
                     ef_actor.busqueda_actor,
                     cq.total_consultas, cq.ultima_consulta,
                     cq.busquedas, cq.activos_subtipo,
                     cq.ult_canal, cq.ult_estado, cq.ult_nota,
                     cq.ult_recordatorio, cq.ult_rowid, cq.ult_activo_dir,
                     cq.dias_sin_contacto, cq.pendientes_vencidos";

$sqlOrder = " ORDER BY $sortfield $sortorder LIMIT $limit OFFSET $offset";

$sql  = $sqlSelect . " " . $sqlFrom . $sqlWhere . $sqlOrder;
// Agregar join de subtipo label
$res2 = $db->query($sql);
$rows = [];
while ($res2 && ($o = $db->fetch_object($res2))) $rows[] = $o;

// Mapa de subtipos de activos para mostrar en "Qué busca"
$sqlSubActivos = "SELECT code, libelle FROM " . MAIN_DB_PREFIX . "c_re_subtypent WHERE fk_typent = 'RE_ACT' AND active = 1";
$resSubActivos = $db->query($sqlSubActivos);
$subActLabels  = [];
while ($resSubActivos && ($o = $db->fetch_object($resSubActivos))) $subActLabels[$o->code] = $o->libelle;



// Helper sort URL

// ── Header ──────────────────────────────────────────────────
llxHeader('', 'Actores - CRM Inmobiliario');

// Tabs — simular la barra de pestañas de societe/list.php
echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">';
echo '<div style="display:flex;gap:6px;flex-wrap:wrap">';
echo '<a href="' . DOL_URL_ROOT . '/societe/list.php?search_type_thirdparty=0" class="butAction">Activos</a>';
echo '<span class="butActionDelete" style="cursor:default;opacity:1">Actores</span>';
echo '<a href="' . DOL_URL_ROOT . '/societe/list.php?search_type_thirdparty=102" class="butAction">Servicios</a>';
echo '<span style="border-left:1px solid #ccc;margin:0 4px"></span>';
echo '<a href="/custom/realestatecrmfields/captacion.php" class="butAction" style="background:#0d6efd;border-color:#0d6efd"><span class="fas fa-building" style="margin-right:4px"></span>Captación</a>';
echo '<a href="/custom/realestatecrmfields/compradores.php" class="butAction" style="background:#6c6aa8;border-color:#6c6aa8"><span class="fas fa-users" style="margin-right:4px"></span>Compradores</a>';
echo '</div>';
// Buscar el id del tipo RE_AOR para pre-seleccionarlo al crear
$sqlTipoAor = "SELECT id FROM " . MAIN_DB_PREFIX . "c_typent WHERE code = 'RE_AOR' LIMIT 1";
$resTipoAor = $db->query($sqlTipoAor);
$idTipoAor  = ($resTipoAor && ($oAor = $db->fetch_object($resTipoAor))) ? (int)$oAor->id : '';
echo '<a href="' . DOL_URL_ROOT . '/societe/card.php?action=create&typent_id=' . $idTipoAor . '" class="butAction" style="background:#6c6aa8;color:#fff;border-color:#6c6aa8">';
echo '<span class="fas fa-plus" style="margin-right:4px"></span>Nuevo Actor</a>';
echo '</div>';

// ── Badges de filtro rápido ──────────────────────────────────
echo '<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:8px">';
// Caliente hoy
if ($totalCaliente > 0) {
    $esFiltrandoHoy = ($search_pendiente === 'hoy');
    $urlHoy = '?' . http_build_query(array_merge($_GET, ['search_pendiente' => $esFiltrandoHoy ? '' : 'hoy', 'page' => 0]));
    echo '<a href="' . $urlHoy . '" style="background:' . ($esFiltrandoHoy ? '#c0392b' : '#fff5f5') . ';color:' . ($esFiltrandoHoy ? '#fff' : '#c0392b') . ';border:1px solid #c0392b;border-radius:16px;padding:3px 12px;font-weight:600;font-size:.85em;text-decoration:none"><span class="fas fa-bell" style="margin-right:4px"></span>🔴 ' . $totalCaliente . ' para contactar hoy' . ($esFiltrandoHoy ? ' ✕' : '') . '</a>';
}
// Sin contacto en X días
foreach ([30 => '30d', 60 => '60d', 90 => '90d'] as $dias => $lbl) {
    $activo = ($search_sin_contacto == $dias);
    echo '<a href="?' . http_build_query(array_merge($_GET, ['search_sin_contacto' => $activo ? 0 : $dias, 'page' => 0])) . '" style="background:' . ($activo ? '#6c6aa8' : '#f0effe') . ';color:' . ($activo ? '#fff' : '#6c6aa8') . ';border:1px solid #6c6aa8;border-radius:16px;padding:3px 10px;font-size:.82em;text-decoration:none">Sin contacto +' . $lbl . ($activo ? ' ✕' : '') . '</a>';
}
// Contador total
$hayFiltro = ($search_pendiente || $search_sin_contacto || $search_nom || $search_subtipo || $search_busqueda || $search_usd_min || $search_usd_max);
echo '<span style="font-size:.88em;color:#6c6aa8;font-weight:600;padding:3px 12px;background:#f0effe;border-radius:12px;border:1px solid #d5d0f5;margin-left:auto">' . $total . ' actor' . ($total != 1 ? 'es' : '') . ($hayFiltro ? ' <span style="font-weight:400;color:#999">(filtrado)</span>' : '') . '</span>';
echo '</div>';

echo '<div class="div-table-responsive">';

// ── Formulario de filtros ────────────────────────────────────
echo '<form method="GET" action="" id="actoresFilterForm">';
echo '<input type="hidden" name="sortfield" value="' . dol_escape_htmltag($sortfield) . '">';
echo '<input type="hidden" name="sortorder" value="' . dol_escape_htmltag($sortorder) . '">';
echo '<input type="hidden" name="page" value="0">';

echo '<table class="noborder centpercent liste">';

// ── Fila de cabeceras con sort ───────────────────────────────
echo '<tr class="liste_titre">';
echo '<th class="liste_titre"><a href="' . reSort('s.nom',$sortfield,$sortorder) . '">Nombre' . reSortIcon('s.nom',$sortfield,$sortorder) . '</a></th>';
echo '<th class="liste_titre"><a href="' . reSort('s.fk_re_subtypent',$sortfield,$sortorder) . '">Subtipo' . reSortIcon('s.fk_re_subtypent',$sortfield,$sortorder) . '</a></th>';
echo '<th class="liste_titre">Teléfono</th>';
echo '<th class="liste_titre center"><a href="' . reSort('s.status',$sortfield,$sortorder) . '">Estado' . reSortIcon('s.status',$sortfield,$sortorder) . '</a></th>';
echo '<th class="liste_titre center"><a href="' . reSort('ef_actor.monto_inversion_usd',$sortfield,$sortorder) . '">Rango USD' . reSortIcon('ef_actor.monto_inversion_usd',$sortfield,$sortorder) . '</a></th>';
echo '<th class="liste_titre center"><a href="' . reSort('cq.total_consultas',$sortfield,$sortorder) . '">Consultas' . reSortIcon('cq.total_consultas',$sortfield,$sortorder) . '</a></th>';
echo '<th class="liste_titre center"><a href="' . reSort('cq.ultima_consulta',$sortfield,$sortorder) . '">Última consulta' . reSortIcon('cq.ultima_consulta',$sortfield,$sortorder) . '</a></th>';
echo '<th class="liste_titre">Qué busca (tipo activo)</th>';
echo '<th class="liste_titre">Nota / Búsqueda</th>';
echo '<th class="liste_titre center">Acciones</th>';
echo '</tr>';

// ── Fila de filtros ──────────────────────────────────────────
echo '<tr class="liste_titre">';

// Nombre + teléfono
echo '<td><input type="text" name="search_nom" value="' . dol_escape_htmltag($search_nom) . '" class="flat maxwidth100" placeholder="Nombre…"></td>';

// Subtipo
echo '<td><select name="search_subtipo" class="flat maxwidth125" onchange="this.form.submit()">';
echo '<option value="">— Subtipo —</option>';
foreach ($subtipo_opts as $code => $label) {
    $sel = ($search_subtipo === $code) ? ' selected' : '';
    echo '<option value="' . dol_escape_htmltag($code) . '"' . $sel . '>' . dol_escape_htmltag($label) . '</option>';
}
echo '</select></td>';

// Teléfono
echo '<td><input type="text" name="search_phone" value="' . dol_escape_htmltag($search_phone) . '" class="flat maxwidth75" placeholder="Tel…"></td>';

// Estado
echo '<td class="center"><select name="search_status" class="flat" onchange="this.form.submit()">';
echo '<option value="3"' . ($search_status == 3 ? ' selected' : '') . '>Todos</option>';
echo '<option value="1"' . ($search_status == 1 ? ' selected' : '') . '>Abierto</option>';
echo '<option value="0"' . ($search_status == 0 ? ' selected' : '') . '>Cerrado</option>';
echo '</select></td>';

// Rango USD
echo '<td class="center nowrap">';
echo '<input type="number" name="search_usd_min" value="' . (int)$search_usd_min . '" class="flat" style="width:80px" placeholder="Min">';
echo ' – ';
echo '<input type="number" name="search_usd_max" value="' . (int)$search_usd_max . '" class="flat" style="width:80px" placeholder="Max">';
echo '</td>';

// Consultas — sin filtro
echo '<td></td>';
// Última consulta — sin filtro
echo '<td></td>';

// Qué busca (tipo activo) — select
echo '<td>';
echo '<select name="search_activo_sub" class="flat maxwidth125" onchange="this.form.submit()">';
echo '<option value="">— Todos —</option>';
foreach ($subtipo_act_opts as $code => $label) {
    $sel = ($search_activo_sub === $code) ? ' selected' : '';
    $labelAbrev = subAbrev($label);
    echo '<option value="' . dol_escape_htmltag($code) . '"' . $sel . '>' . dol_escape_htmltag($labelAbrev) . '</option>';
}
echo '</select>';
echo '</td>';

// Nota/Búsqueda
echo '<td><input type="text" name="search_busqueda" value="' . dol_escape_htmltag($search_busqueda) . '" class="flat maxwidth150" placeholder="Buscar en notas…"></td>';
echo '<td></td>';
echo '</tr>';

// ── Botón buscar (submit al presionar Enter en cualquier input) ──
echo '<tr style="display:none"><td colspan="9"><button type="submit">Buscar</button></td></tr>';

// ── Filas de datos ───────────────────────────────────────────
if (empty($rows)) {
    echo '<tr><td colspan="10" class="opacitymedium" style="padding:16px">Sin resultados.</td></tr>';
} else {
    foreach ($rows as $row) {
        $statusLabel = $row->status ? '<span class="badge badge-status4" style="background:#28a745;color:#fff">Abierto</span>'
                                    : '<span class="badge" style="background:#6c757d;color:#fff">Cerrado</span>';

        // Tipos de activo que busca (de sus consultas)
        $activosSubLabels = '';
        if ($row->activos_subtipo) {
            $codes = array_unique(array_filter(explode(',', $row->activos_subtipo)));
            $labels = [];
            foreach ($codes as $c) {
                if (isset($subActLabels[$c])) {
                    $labels[] = subAbrev($subActLabels[$c]);
                }
            }
            $activosSubLabels = implode(', ', $labels);
        }

        // Monto de inversión desde extrafields del actor
        $rangoUsd = '';
        if ( !empty($row->monto_inversion_usd) ) {
            $rangoUsd = 'USD ' . number_format((float)$row->monto_inversion_usd, 0, ',', '.');
        }

        // Última consulta con detalle de interacción
        $canalesLabel = ['WHATSAPP'=>'WA','TELEFONO'=>'Tel','EMAIL'=>'Email','INSTAGRAM'=>'IG'];
        $estadosLabel = ['CONSULTO'=>'Consultó','VISITO'=>'Visitó','OFRECIO'=>'Ofreció','CERRO'=>'Cerró'];
        // Indicador de temperatura basado en días sin contacto
        $diasSC = ($row->dias_sin_contacto !== null) ? (int)$row->dias_sin_contacto : 999;
        if (!$row->ultima_consulta) $diasSC = 999;
        if ($diasSC <= 7)      { $tempIcon = '🟢'; $tempLabel = 'Activo'; }
        elseif ($diasSC <= 30) { $tempIcon = '🟡'; $tempLabel = $diasSC . 'd'; }
        else                   { $tempIcon = '⚪';  $tempLabel = $diasSC . 'd'; }

        // Contador de pendientes vencidos del actor
        $pendVenc = (int)($row->pendientes_vencidos ?? 0);

        $ultimaCons = '';
        if ($row->ultima_consulta) {
            $ultimaCons = '<span style="font-weight:600;font-size:1.05em">' . date('d/m/Y', strtotime($row->ultima_consulta)) . '</span>';
            if ($row->ult_canal || $row->ult_estado) {
                $cLabel = $canalesLabel[$row->ult_canal] ?? $row->ult_canal;
                $eLabel = $estadosLabel[$row->ult_estado] ?? $row->ult_estado;
                $ultimaCons .= '<br><span style="color:#888;font-size:.9em">' . $cLabel . ' · ' . $eLabel . '</span>';
            }
            // Mostrar dirección del activo vinculado
            if (!empty($row->ult_activo_dir)) {
                $ultimaCons .= '<br><span style="color:#6c6aa8;font-size:.9em"><span class="fas fa-map-marker-alt" style="margin-right:3px"></span>' . dol_htmlentities($row->ult_activo_dir) . '</span>';
            }
            if ($row->ult_nota) {
                // Limpiar prefijo "Lead X — NOMBRE" de la nota
                $notaLimpia = preg_replace('/^Lead\s+\S+\s+[—–-]\s+[^\n]+\n?/u', '', $row->ult_nota);
                $notaLimpia = trim($notaLimpia) ?: $row->ult_nota;
                $notaTrunc  = mb_strlen($notaLimpia) > 70 ? mb_substr($notaLimpia, 0, 70) . '…' : $notaLimpia;
                // Mostrar directamente visible (sin expandir)
                $ultimaCons .= '<span class="act-ult-corta"><span style="font-size:.9em;color:#555">' . dol_htmlentities($notaTrunc) . '</span></span>'
                    . '<span class="act-ult-completa" style="display:none;white-space:pre-wrap;font-size:.9em;color:#555">' . dol_htmlentities($notaLimpia) . '</span>';
            }
            // Recordatorio
            if ($row->ult_recordatorio && $row->ult_recordatorio >= date('Y-m-d')) {
                $ultimaCons .= '<br><span style="color:#0d6efd;font-size:.9em"><span class="fas fa-bell"></span> ' . date('d/m/Y', strtotime($row->ult_recordatorio)) . '</span>';
            } elseif ($row->ult_recordatorio) {
                $ultimaCons .= '<br><span style="color:#c0392b;font-size:.9em"><span class="fas fa-exclamation-circle"></span> ' . date('d/m/Y', strtotime($row->ult_recordatorio)) . '</span>';
            }
        } else {
            $ultimaCons = '<span class="opacitymedium">—</span>';
        }

        // Nota/búsqueda — truncar
        $busquedaCompleta = $row->busqueda_actor ?: '';
        $busquedaTrunc    = $busquedaCompleta ? dol_trunc($busquedaCompleta, 80) : '<span class="opacitymedium">—</span>';

        echo '<tr class="oddeven">';
        $pendBadge = $pendVenc > 0 ? ' <span style="background:#c0392b;color:#fff;border-radius:10px;padding:0 6px;font-size:.75em;font-weight:700">' . $pendVenc . '</span>' : '';
        $tempBadge = '<span title="' . $tempLabel . '" style="font-size:.85em;margin-left:3px">' . $tempIcon . '</span>';
        echo '<td><a href="' . DOL_URL_ROOT . '/societe/card.php?socid=' . $row->rowid . '">' . dol_htmlentities($row->nom) . '</a>' . $tempBadge . $pendBadge . '</td>';
        echo '<td>' . dol_htmlentities($row->subtipo_label ?: '—') . '</td>';
        echo '<td>' . dol_htmlentities($row->phone ?: '—') . '</td>';
        echo '<td class="center">' . $statusLabel . '</td>';
        echo '<td class="center nowrap"><small>' . ($rangoUsd ?: '<span class="opacitymedium">—</span>') . '</small></td>';
        echo '<td class="center">' . (int)$row->total_consultas . '</td>';
        echo '<td class="nowrap act-ult-celda" style="min-width:140px;cursor:pointer" title="Clic para expandir"><small>' . $ultimaCons . '</small></td>';
        echo '<td class="tdoverflowmax150" title="' . dol_escape_htmltag($activosSubLabels) . '">' . dol_htmlentities($activosSubLabels ?: '—') . '</td>';
        echo '<td style="max-width:220px;cursor:pointer" class="act-nota-celda" data-socid="' . $row->rowid . '" data-nom="' . dol_escape_htmltag($row->nom) . '" data-busqueda="' . dol_escape_htmltag($busquedaCompleta) . '">'
            . '<span class="act-nota-corta">' . ($busquedaCompleta ? dol_htmlentities($busquedaTrunc) : '<span class="opacitymedium">—</span>') . '</span>'
            . '<span class="act-nota-completa" style="display:none;white-space:pre-wrap">' . dol_htmlentities($busquedaCompleta) . '</span>'
            . ' <a href="#" class="act-editar-busqueda" data-socid="' . $row->rowid . '" data-nom="' . dol_escape_htmltag($row->nom) . '" data-busqueda="' . dol_escape_htmltag($busquedaCompleta) . '" title="Editar búsqueda" style="font-size:.8em;color:#6c6aa8;margin-left:4px"><span class="fas fa-pencil-alt"></span></a>'
            . '</td>';
        echo '<td class="center nowrap">';
        $hoy = date('Y-m-d');
        $tieneRecordatorio = !empty($row->ult_recordatorio) && $row->ult_recordatorio <= $hoy;
        if ($tieneRecordatorio && !empty($row->ult_rowid)) {
            // Tiene recordatorio vencido → botón Seguimiento (usa modal de pendientes)
            echo '<button type="button" class="re-done-btn butAction"
                    style="padding:2px 8px;font-size:.82em;background:#c0392b;border-color:#c0392b"
                    data-rowid="' . (int)$row->ult_rowid . '"
                    data-action-type="done"
                    data-tipo="comp"
                    data-actor="' . dol_escape_htmltag($row->nom) . '">
                    <span class="fas fa-bell" style="margin-right:3px"></span>Seguimiento</button>';
        } else {
            // Sin recordatorio pendiente → botón Nueva Consulta
            echo '<a href="#" class="act-nueva-consulta butAction"
                    style="padding:2px 8px;font-size:.82em;background:#6c6aa8;border-color:#6c6aa8"
                    data-socid="' . $row->rowid . '"
                    data-nom="' . dol_escape_htmltag($row->nom) . '">
                    <span class="fas fa-clipboard-list" style="margin-right:3px"></span>Consulta</a>';
        }
        echo ' <a href="/custom/realestatecrmfields/actor_timeline.php?socid=' . $row->rowid . '"
                style="color:#6c6aa8;font-size:.9em;margin-left:4px" title="Ver timeline">
                <span class="fas fa-history"></span></a>';
        echo '</td>';
        echo '</tr>';
    }
}

echo '</table>';
echo '</form>';

// ── Paginación ───────────────────────────────────────────────
if ($total > $limit) {
    echo '<div style="margin-top:12px;text-align:center">';
    $pages = ceil($total / $limit);
    for ($i = 0; $i < $pages; $i++) {
        $p = array_merge($_GET, ['page' => $i]);
        $active = ($i === $page) ? ' style="font-weight:bold;text-decoration:underline"' : '';
        echo '<a href="?' . http_build_query($p) . '"' . $active . ' style="margin:0 4px">' . ($i+1) . '</a>';
    }
    echo '</div>';
}

echo '<div class="opacitymedium" style="margin-top:8px;font-size:.9em">';
if ($total > $limit) echo 'Mostrando ' . ($offset+1) . '–' . min($offset+$limit, $total) . ' de ' . $total;
echo '</div>';

echo '</div>'; // div-table-responsive

// Submit al presionar Enter en inputs de texto
echo '<script>
$(function() {
    $("#actoresFilterForm input[type=text], #actoresFilterForm input[type=number]").on("keydown", function(e) {
        if (e.key === "Enter") { e.preventDefault(); $(this).closest("form").submit(); }
    });
});
</script>';

// ── Modal nueva consulta rápida ─────────────────────────────
$token_act = getToken();
$canales_act   = ['TELEFONO'=>'📞 Teléfono','WHATSAPP'=>'💬 WhatsApp','EMAIL'=>'📧 Email','INSTAGRAM'=>'📸 Instagram DM'];
$estados_act   = ['CONSULTO'=>'Consultó','VISITO'=>'Visitó','OFRECIO'=>'Ofreció','CERRO'=>'Cerró'];
?>
<div id="act-consulta-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;overflow-y:auto">
  <div style="background:#fff;max-width:500px;margin:60px auto;border-radius:6px;padding:26px 30px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <button type="button" id="act-modal-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.3em;cursor:pointer">✕</button>
    <h3 style="margin:0 0 16px">
      <span class="fas fa-plus-circle" style="color:#6c6aa8;margin-right:6px"></span>
      Nueva consulta — <span id="act-modal-nom" style="color:#6c6aa8"></span>
    </h3>
    <input type="hidden" id="act-modal-socid">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
      <div>
        <label style="font-weight:600;display:block;margin-bottom:4px">Canal</label>
        <select id="act-canal" class="flat" style="width:100%">
          <?php foreach ($canales_act as $k=>$v): ?>
          <option value="<?= $k ?>"><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label style="font-weight:600;display:block;margin-bottom:4px">Estado</label>
        <select id="act-estado" class="flat" style="width:100%">
          <?php foreach ($estados_act as $k=>$v): ?>
          <option value="<?= $k ?>"><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div style="margin-bottom:12px">
      <label style="font-weight:600;display:block;margin-bottom:4px">Nota</label>
      <textarea id="act-nota" class="flat" rows="2" style="width:100%;box-sizing:border-box" placeholder="Qué dijo, qué busca…"></textarea>
    </div>
    <div style="margin-bottom:12px">
      <label style="font-weight:600;display:block;margin-bottom:4px">Recordatorio en</label>
      <div style="display:flex;align-items:center;gap:6px">
        <input type="number" id="act-rec-dias" class="flat" style="width:60px" placeholder="días" min="1">
        <span id="act-rec-preview" style="font-size:.82em;color:#6c6aa8"></span>
      </div>
    </div>
    <div style="text-align:right">
      <button type="button" id="act-modal-cancel" class="butActionDelete" style="margin-right:8px">Cancelar</button>
      <button type="button" id="act-modal-submit" class="butAction">Guardar consulta</button>
    </div>
    <div id="act-modal-error" style="color:#c0392b;margin-top:8px;font-size:.9em;display:none"></div>
  </div>
</div>

<script>
$(function() {
    var TOKEN = '<?= dol_escape_js($token_act) ?>'
             || $('meta[name="anti-csrf-newtoken"]').attr('content') || '';

    $(document).on('click', '.act-nueva-consulta', function(e) {
        e.preventDefault();
        $('#act-modal-socid').val($(this).data('socid'));
        $('#act-modal-nom').text($(this).data('nom'));
        $('#act-canal').val('WHATSAPP');
        $('#act-estado').val('CONSULTO');
        $('#act-nota').val('');
        // Recordatorio automático +3 días
        var dt = new Date(); dt.setDate(dt.getDate() + 3);
        var yy = dt.getFullYear(), mm = String(dt.getMonth()+1).padStart(2,'0'), dd = String(dt.getDate()).padStart(2,'0');
        $('#act-rec-dias').val('3');
        if ($('#act-rec-fecha').length) $('#act-rec-fecha').val(yy+'-'+mm+'-'+dd);
        if ($('#act-rec-preview').length) $('#act-rec-preview').text('→ '+dd+'/'+mm+'/'+yy);
        $('#act-modal-error').hide();
        $('#act-consulta-modal').css('display','block');
        setTimeout(function(){ $('#act-nota').focus(); }, 150);
    });

    $('#act-modal-close, #act-modal-cancel').on('click', function() {
        $('#act-consulta-modal').css('display','none');
    });
    $('#act-consulta-modal').on('click', function(e) {
        if ($(e.target).is('#act-consulta-modal')) $(this).css('display','none');
    });

    $('#act-rec-dias').on('input', function() {
        var d = parseInt($(this).val());
        if (isNaN(d)||d<1) { $('#act-rec-preview').text(''); return; }
        var dt = new Date(); dt.setDate(dt.getDate()+d);
        $('#act-rec-preview').text('→ '+String(dt.getDate()).padStart(2,'0')+'/'+String(dt.getMonth()+1).padStart(2,'0'));
    });

    $('#act-modal-submit').on('click', function() {
        var socid = $('#act-modal-socid').val();
        var dias  = parseInt($('#act-rec-dias').val());
        var recFecha = '';
        if (!isNaN(dias) && dias > 0) {
            var dt = new Date(); dt.setDate(dt.getDate()+dias);
            recFecha = dt.getFullYear()+'-'+String(dt.getMonth()+1).padStart(2,'0')+'-'+String(dt.getDate()).padStart(2,'0');
        }
        var $btn = $(this).prop('disabled',true).text('Guardando…');
        $.post('/custom/realestatecrmfields/ajax/consulta_save.php', {
            action:             'create',
            token:              TOKEN,
            fk_societe_actor:   socid,
            canal:              $('#act-canal').val(),
            estado:             $('#act-estado').val(),
            nota:               $('#act-nota').val().trim(),
            fecha_recordatorio: recFecha,
            date_consulta:      new Date().toISOString().slice(0,16),
        }, function(r) {
            if (r && r.success) {
                $('#act-consulta-modal').css('display','none');
                location.reload();
            } else {
                $('#act-modal-error').text('Error: '+(r&&r.error?r.error:'desconocido')).show();
            }
        }, 'json').always(function() { $btn.prop('disabled',false).text('Guardar consulta'); });
    });
});
</script>

<!-- Modal editar búsqueda -->
<div id="act-busqueda-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;overflow-y:auto">
  <div style="background:#fff;max-width:500px;margin:80px auto;border-radius:6px;padding:26px 30px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <button type="button" id="act-busqueda-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.3em;cursor:pointer">✕</button>
    <h3 style="margin:0 0 16px">
      <span class="fas fa-search" style="color:#6c6aa8;margin-right:6px"></span>
      Qué busca — <span id="act-busqueda-nom" style="color:#6c6aa8"></span>
    </h3>
    <input type="hidden" id="act-busqueda-socid">
    <div style="margin-bottom:16px">
      <label style="font-weight:600;display:block;margin-bottom:6px">Nota / Búsqueda</label>
      <textarea id="act-busqueda-texto" class="flat" rows="4" style="width:100%;box-sizing:border-box" placeholder="Qué está buscando el actor…"></textarea>
    </div>
    <div style="text-align:right">
      <button type="button" id="act-busqueda-cancel" class="butActionDelete" style="margin-right:8px">Cancelar</button>
      <button type="button" id="act-busqueda-submit" class="butAction">Guardar</button>
    </div>
    <div id="act-busqueda-error" style="color:#c0392b;margin-top:8px;font-size:.9em;display:none"></div>
  </div>
</div>

<script>
$(function() {
    var TOKEN_ACT2 = '<?= dol_escape_js($token_act) ?>'
                  || $('meta[name="anti-csrf-newtoken"]').attr('content') || '';

    // Expandir/colapsar nota al hacer clic en la celda
    $(document).on('click', '.act-nota-celda', function() {
        var $corta    = $(this).find('.act-nota-corta');
        var $completa = $(this).find('.act-nota-completa');
        var expandida = $completa.is(':visible');
        $corta.toggle(expandida);
        $completa.toggle(!expandida);
        $(this).css('max-width', expandida ? '220px' : 'none');
    });

    // Expandir/colapsar última interacción al hacer clic
    $(document).on('click', '.act-ult-celda', function() {
        var $corta    = $(this).find('.act-ult-corta');
        var $completa = $(this).find('.act-ult-completa');
        if (!$corta.length) return;
        var expandida = $completa.is(':visible');
        $corta.toggle(expandida);
        $completa.toggle(!expandida);
        $(this).css('max-width', expandida ? '' : 'none');
        $(this).attr('title', expandida ? 'Clic para expandir' : 'Clic para colapsar');
    });

    // Abrir modal de edición
    $(document).on('click', '.act-editar-busqueda', function(e) {
        e.preventDefault();
        e.stopPropagation(); // evitar que expanda/colapse la celda
        var socid    = $(this).data('socid');
        var nom      = $(this).data('nom');
        var busqueda = $(this).data('busqueda');
        $('#act-busqueda-socid').val(socid);
        $('#act-busqueda-nom').text(nom);
        $('#act-busqueda-texto').val(busqueda);
        $('#act-busqueda-error').hide();
        $('#act-busqueda-modal').css('display','block');
        setTimeout(function(){ $('#act-busqueda-texto').focus(); }, 150);
    });

    $('#act-busqueda-close, #act-busqueda-cancel').on('click', function() {
        $('#act-busqueda-modal').css('display','none');
    });
    $('#act-busqueda-modal').on('click', function(e) {
        if ($(e.target).is('#act-busqueda-modal')) $(this).css('display','none');
    });

    // Guardar
    $('#act-busqueda-submit').on('click', function() {
        var $btn = $(this).prop('disabled', true).text('Guardando…');
        $.post('/custom/realestatecrmfields/ajax/consulta_save.php', {
            action:           'update_busqueda',
            token:            TOKEN_ACT2,
            fk_societe_actor: $('#act-busqueda-socid').val(),
            busqueda:         $('#act-busqueda-texto').val().trim()
        }, function(r) {
            if (r && r.success) {
                $('#act-busqueda-modal').css('display','none');
                location.reload();
            } else {
                $('#act-busqueda-error').text('Error: '+(r&&r.error?r.error:'desconocido')).show();
            }
        }, 'json').always(function() { $btn.prop('disabled',false).text('Guardar'); });
    });
});
</script>

<!-- Modal de Seguimiento (igual al de pendientes.php) -->
<div id="re-done-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;overflow-y:auto">
  <div style="background:#fff;max-width:520px;margin:60px auto;border-radius:8px;padding:28px 32px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <button type="button" id="re-done-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.4em;cursor:pointer">✕</button>
    <h3 style="margin:0 0 18px;color:#6c6aa8"><span class="fas fa-clipboard-list" style="margin-right:8px"></span>Seguimiento — <span id="done-actor-nom"></span></h3>
    <form id="re-done-form">
      <input type="hidden" name="action" value="done">
      <input type="hidden" name="rowid" id="done-rowid">
      <input type="hidden" name="resultado_seguimiento" id="done-resultado-val">
      <div style="margin-bottom:16px">
        <label style="font-weight:600;display:block;margin-bottom:8px">Resultado del contacto</label>
        <div id="done-resultado-comp" style="display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach (['ATENDIO'=>'✅ Atendió','NO_ATENDIO'=>'📵 No contestó','WHATSAPP'=>'💬 WA enviado','OFRECIO'=>'💰 Hizo oferta','SIN_INTERES'=>'❌ Sin interés'] as $val=>$lbl): ?>
          <button type="button" class="done-res-btn" data-val="<?= $val ?>"
            style="padding:4px 12px;border:1px solid #dee2e6;border-radius:20px;background:#fff;cursor:pointer;font-size:.85em">
            <?= $lbl ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="margin-bottom:14px">
        <label style="font-weight:600;display:block;margin-bottom:4px">Nota</label>
        <div style="margin-bottom:5px">
          <span style="font-size:.78em;color:#999;margin-right:5px">Rápido:</span>
          <button type="button" class="done-respuesta-rapida" style="font-size:.76em;padding:2px 7px;border:1px solid #dee2e6;border-radius:4px;background:#f8f9fa;cursor:pointer"
            data-texto="Se consultó si pudo ver la ficha del garaje">📋 Consulta ficha</button>
        </div>
        <textarea name="nota_done" class="flat" rows="2" style="width:100%;box-sizing:border-box" placeholder="Qué pasó, qué dijo…"></textarea>
      </div>
      <div style="background:#f8f9fa;border-radius:6px;padding:14px;margin-bottom:14px">
        <label style="font-weight:600;display:block;margin-bottom:8px;color:#555">
          <span class="fas fa-bell" style="margin-right:5px;color:#6c6aa8"></span>Próximo recordatorio
        </label>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <div>
            <label style="font-size:.82em;color:#666">Días desde hoy</label>
            <input type="number" id="done-rec-dias" min="1" class="flat" style="width:70px" placeholder="días">
          </div>
          <div style="padding-top:18px;color:#6c6aa8;font-weight:600" id="done-rec-preview"></div>
          <div>
            <label style="font-size:.82em;color:#666">O fecha exacta</label>
            <input type="date" name="nueva_fecha_recordatorio" id="done-rec-fecha" class="flat">
          </div>
        </div>
        <div style="margin-top:8px;font-size:.82em;color:#888">
          <span class="fas fa-info-circle"></span>
          Si cargás fecha, se <strong>reprograma</strong>. Si la dejás vacía, se <strong>cierra</strong>.
        </div>
      </div>
      <div style="text-align:right">
        <button type="button" id="re-done-cancel" class="butActionDelete" style="margin-right:8px">Cancelar</button>
        <button type="button" id="re-done-cerrar" class="butAction" style="display:none;background:#c0392b;border-color:#c0392b;margin-right:8px">✕ Cerrar contacto</button>
        <button type="button" id="re-done-guardar" class="butAction">Guardar seguimiento</button>
      </div>
    </form>
  </div>
</div>

<script>
$(function() {
    var TOKEN_DONE = $('meta[name="anti-csrf-newtoken"]').attr('content') || '';

    // Abrir modal de seguimiento
    $(document).on('click', '.re-done-btn', function() {
        $('#done-rowid').val($(this).data('rowid'));
        $('#done-actor-nom').text($(this).data('actor') || '');
        $('.done-res-btn').css({background:'#fff', borderColor:'#dee2e6', color:'inherit', fontWeight:'normal'});
        $('#done-resultado-val').val('');
        $('#re-done-cerrar').hide();
        $('#re-done-guardar').text('Guardar seguimiento');
        $('textarea[name="nota_done"]').val('');
        // Default 2 días
        $('#done-rec-dias').val('2');
        var dtDef = new Date(); dtDef.setDate(dtDef.getDate() + 2);
        var yy = dtDef.getFullYear(), mm = String(dtDef.getMonth()+1).padStart(2,'0'), dd = String(dtDef.getDate()).padStart(2,'0');
        $('#done-rec-fecha').val(yy+'-'+mm+'-'+dd);
        $('#done-rec-preview').text('→ '+dd+'/'+mm+'/'+yy);
        $('#re-done-modal').fadeIn(150);
        setTimeout(function(){ $('textarea[name="nota_done"]').focus(); }, 150);
    });

    $('#re-done-cancel, #re-done-close').on('click', function() { $('#re-done-modal').fadeOut(150); });

    // Resultado
    $(document).on('click', '.done-res-btn', function() {
        $('.done-res-btn').css({background:'#fff', borderColor:'#dee2e6', color:'inherit', fontWeight:'normal'});
        $(this).css({background:'#6c6aa8', borderColor:'#6c6aa8', color:'#fff', fontWeight:'600'});
        var val = $(this).data('val');
        $('#done-resultado-val').val(val);
        if (val === 'NO_ATENDIO') {
            $('#re-done-cerrar').show();
            $('#re-done-guardar').text('Reprogramar');
        } else {
            $('#re-done-cerrar').hide();
            $('#re-done-guardar').text('Guardar seguimiento');
        }
    });

    // Respuestas rápidas
    $(document).on('click', '.done-respuesta-rapida', function() {
        var texto = $(this).data('texto');
        var $nota = $('textarea[name="nota_done"]');
        var actual = $nota.val().trim();
        $nota.val(actual ? actual + ' ' + texto : texto).focus();
    });

    // Días → fecha
    $('#done-rec-dias').on('input', function() {
        var dias = parseInt($(this).val());
        if (!dias || dias < 1) { $('#done-rec-preview').text(''); return; }
        var dt = new Date(); dt.setDate(dt.getDate() + dias);
        var yy = dt.getFullYear(), mm = String(dt.getMonth()+1).padStart(2,'0'), dd = String(dt.getDate()).padStart(2,'0');
        $('#done-rec-fecha').val(yy+'-'+mm+'-'+dd);
        $('#done-rec-preview').text('→ '+dd+'/'+mm+'/'+yy);
    });

    // Guardar via pendiente_done.php
    $('#re-done-guardar, #re-done-cerrar').on('click', function() {
        var esCerrar = $(this).attr('id') === 're-done-cerrar';
        var $btn = $(this).prop('disabled', true).text(esCerrar ? 'Cerrando…' : 'Guardando…');
        var data = {
            action:                   'done',
            token:                    TOKEN_DONE,
            rowid:                    $('#done-rowid').val(),
            resultado_seguimiento:    esCerrar ? 'NO_ATENDIO' : $('#done-resultado-val').val(),
            nota_done:                $('textarea[name="nota_done"]').val(),
            nueva_fecha_recordatorio: esCerrar ? '' : $('#done-rec-fecha').val(),
            nueva_nota_recordatorio:  ''
        };
        $.post('/custom/realestatecrmfields/ajax/pendiente_done.php', data, function(r) {
            if (r && r.success) {
                $('#re-done-modal').fadeOut(150);
                location.reload();
            } else {
                alert('Error: ' + (r ? JSON.stringify(r) : 'sin respuesta'));
                $btn.prop('disabled', false).text(esCerrar ? '✕ Cerrar contacto' : 'Guardar seguimiento');
            }
        }, 'json').fail(function(xhr) {
            alert('Error HTTP ' + xhr.status);
            $btn.prop('disabled', false).text(esCerrar ? '✕ Cerrar contacto' : 'Guardar seguimiento');
        });
    });
});
</script>
<?php
llxFooter();
$db->close();
