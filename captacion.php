<?php
/**
 * Modo Captación — Seguimiento de activos y sus propietarios
 * URL: /custom/realestatecrmfields/captacion.php
 */
$res = @include '../../main.inc.php';
if (!$res) $res = @include '../../../main.inc.php';
if (!$res) die('main.inc.php not found');

require_once dol_buildpath('/custom/realestatecrmfields/class/regestion.class.php', 0);
require_once __DIR__ . '/helpers.php';

$langs->load('realestatecrmfields');

// ── Filtros ─────────────────────────────────────────────────
$search_nom      = GETPOST('search_nom',      'alphanohtml');
$search_subtipo  = GETPOST('search_subtipo',  'alpha');
$search_resultado= GETPOST('search_resultado','alpha');
$search_dias     = (int)GETPOST('search_dias', 'int'); // sin contacto hace más de X días
$search_enficha  = GETPOST('search_enficha', 'alpha'); // '1'=en ficha, '0'=no en ficha, ''=todos
$search_semaforo  = GETPOST('search_semaforo',  'alpha');
$search_prioridad  = GETPOST('search_prioridad', 'alpha');
$search_contel    = (int)GETPOST('search_contel', 'int'); // 1 = solo con teléfono
$search_tasado    = (int)GETPOST('search_tasado',  'int'); // 1 = solo tasados
$search_vendedor  = (int)GETPOST('search_vendedor_capt', 'int');
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'semaforo';
$sortorder = GETPOST('sortorder', 'aZ09') ?: 'ASC';
$page  = max(0, (int)GETPOST('page', 'int'));
$limit = 50;
$offset = $page * $limit;

$hoy = date('Y-m-d');

// Subtipos RE_ACT
// ── Helpers compartidos ──────────────────────────────────────

// Tabla de prioridad por barrio — fuente única de verdad




$sqlSubs = "SELECT code, libelle FROM " . MAIN_DB_PREFIX . "c_re_subtypent WHERE fk_typent = 'RE_ACT' AND active = 1 ORDER BY position";
$resSubs = $db->query($sqlSubs);
$subtipo_opts = [];
while ($resSubs && ($o = $db->fetch_object($resSubs))) $subtipo_opts[$o->code] = $o->libelle;

$resultados = ReGestion::RESULTADOS;
$canales    = ReGestion::CANALES;

// ── Query principal ─────────────────────────────────────────
// Para cada activo: último contacto con propietario + propietario vinculado vigente
$sqlFrom = "FROM " . MAIN_DB_PREFIX . "societe s
            INNER JOIN " . MAIN_DB_PREFIX . "c_typent t ON t.id = s.fk_typent AND t.code = 'RE_ACT'
            LEFT JOIN " . MAIN_DB_PREFIX . "c_re_subtypent sub ON sub.code = s.fk_re_subtypent
            LEFT JOIN " . MAIN_DB_PREFIX . "societe_extrafields ef ON ef.fk_object = s.rowid
            -- Último contacto con propietario
            LEFT JOIN (
                SELECT g1.*,
                    sp.nom AS prop_nom, sp.phone AS prop_phone,
                    DATEDIFF(CURDATE(), DATE(g1.fecha)) AS dias_desde_contacto
                FROM " . MAIN_DB_PREFIX . "re_gestion_propietario g1
                LEFT JOIN " . MAIN_DB_PREFIX . "societe sp ON sp.rowid = g1.fk_societe_propietario
                -- Garantiza una sola fila por activo aunque haya empates de fecha
                INNER JOIN (
                    SELECT fk_societe_activo, MAX(fecha) AS max_fecha,
                           MAX(rowid) AS max_rowid
                    FROM " . MAIN_DB_PREFIX . "re_gestion_propietario
                    GROUP BY fk_societe_activo
                ) lg ON lg.fk_societe_activo = g1.fk_societe_activo
                      AND lg.max_fecha = g1.fecha
                      AND lg.max_rowid  = g1.rowid
            ) ug ON ug.fk_societe_activo = s.rowid
            -- Propietario vigente (tabla nueva)
            LEFT JOIN (
                SELECT p.fk_societe_activo,
                    GROUP_CONCAT(
                        COALESCE(sp2.nom, p.propietario_nombre)
                        ORDER BY p.rowid DESC SEPARATOR ' / '
                    ) AS propietarios_nom,
                    GROUP_CONCAT(
                        COALESCE(sp2.phone, p.propietario_telefono)
                        ORDER BY p.rowid DESC SEPARATOR ' / '
                    ) AS propietarios_tel,
                    GROUP_CONCAT(
                        sp2.email
                        ORDER BY p.rowid DESC SEPARATOR ' / '
                    ) AS propietarios_email
                FROM " . MAIN_DB_PREFIX . "re_propietario_activo p
                LEFT JOIN " . MAIN_DB_PREFIX . "societe sp2 ON sp2.rowid = p.fk_societe_propietario
                WHERE p.activo = 1
                GROUP BY p.fk_societe_activo
            ) pv ON pv.fk_societe_activo = s.rowid
            -- Conteo de consultas activas (interesados)
            LEFT JOIN (
                SELECT fk_societe_activo,
                    COUNT(*) AS total_interesados,
                    SUM(CASE WHEN estado IN ('VISITO','OFRECIO') THEN 1 ELSE 0 END) AS interesados_calientes
                FROM " . MAIN_DB_PREFIX . "re_consulta
                WHERE estado != 'CERRO'
                GROUP BY fk_societe_activo
            ) ci ON ci.fk_societe_activo = s.rowid";

$sqlWhere = " WHERE s.status = 1";
if ($search_nom)       $sqlWhere .= " AND (s.nom LIKE '%" . $db->escape($search_nom) . "%' OR ef.calle LIKE '%" . $db->escape($search_nom) . "%')";
if ($search_subtipo)   $sqlWhere .= " AND s.fk_re_subtypent = '" . $db->escape($search_subtipo) . "'";
if ($search_resultado) $sqlWhere .= " AND ug.resultado = '" . $db->escape($search_resultado) . "'";
if ($search_dias > 0)  $sqlWhere .= " AND (ug.dias_desde_contacto >= " . (int)$search_dias . " OR ug.dias_desde_contacto IS NULL)";
if ($search_enficha === '1') $sqlWhere .= " AND ef.enficha = 1";
if ($search_enficha === '0') $sqlWhere .= " AND (ef.enficha IS NULL OR ef.enficha = 0)";
if ($search_contel) $sqlWhere .= " AND (
    (pv.propietarios_tel IS NOT NULL AND pv.propietarios_tel != '')
    OR (sp.phone IS NOT NULL AND sp.phone != '')
    OR (ug.prop_phone IS NOT NULL AND ug.prop_phone != '')
)";
if ($search_vendedor) $sqlWhere .= " AND ug.fk_user_vendedor = " . (int)$search_vendedor;
if ($search_tasado) $sqlWhere .= " AND ef.usdtasacion IS NOT NULL AND ef.usdtasacion > 0";
// Excluir siempre los activos con contactar = "NO - ..."
$sqlWhere .= " AND (ef.contactar IS NULL OR ef.contactar = '' OR ef.contactar NOT LIKE 'NO -%')";
if ($search_prioridad) {
    $sqlWhere .= " AND " . prio_where_sql($db->escape($search_prioridad));
}// Filtro semáforo — activable via URL: ?search_semaforo=rojo|naranja|verde
// (no hay control visible en la UI — uso interno/debugging)
if ($search_semaforo === 'rojo')    $sqlWhere .= " AND (ug.dias_desde_contacto IS NULL OR ug.dias_desde_contacto >= " . UMBRAL_CALIENTE['verde'] . ")";
if ($search_semaforo === 'naranja') $sqlWhere .= " AND ug.dias_desde_contacto BETWEEN " . UMBRAL_CALIENTE['verde'] . " AND " . (UMBRAL_PRIO_MEDIA['naranja'] - 1) . "";
if ($search_semaforo === 'verde')   $sqlWhere .= " AND ug.dias_desde_contacto IS NOT NULL AND ug.dias_desde_contacto < " . UMBRAL_CALIENTE['verde'] . "";

$sortMap = [
    'semaforo'          => 'semaforo',
    'prioridad'         => 'prioridad_orden',
    'dias_sin_contacto' => 'ug.dias_desde_contacto',
    's.nom'             => 's.nom',
    'ug.resultado'      => 'ug.resultado',
    'ug.fecha'          => 'ug.fecha',
    'ci.interesados_calientes' => 'ci.interesados_calientes',
    'usd_precio'    => 'COALESCE(NULLIF(usdventa,0), usdtasacion, 0)',
    'usd_por_coch'  => 'COALESCE(NULLIF(usdventa,0), usdtasacion, 0) / NULLIF(COALESCE(NULLIF(cocheras_fijas,0), coch_fijas_estimadas, 0), 0)',
];
$sortCol   = $sortMap[$sortfield] ?? 'semaforo';
$sortorder = ($sortorder === 'ASC') ? 'ASC' : 'DESC';
// Para semaforo: usar el alias directamente (disponible en subquery wrapper)
// Para otros campos: referenciar por nombre de columna

$sqlCount = "SELECT COUNT(*) AS total $sqlFrom $sqlWhere";
$resCount = $db->query($sqlCount);
$total    = ($resCount) ? (int)$db->fetch_object($resCount)->total : 0;

// Conteo total por prioridad (sobre todos los filtros, sin LIMIT)
$sqlPrioCount = "SELECT
    " . prio_orden_sql() . " AS pord,
    COUNT(*) AS cnt
    $sqlFrom $sqlWhere
    GROUP BY pord";
$resPrio = $db->query($sqlPrioCount);
$totalAlta = $totalMedia = $totalBaja = 0;
while ($resPrio && ($op = $db->fetch_object($resPrio))) {
    if ($op->pord == 1) $totalAlta  += (int)$op->cnt;
    elseif ($op->pord == 2) $totalMedia += (int)$op->cnt;
    elseif ($op->pord == 3) $totalBaja  += (int)$op->cnt;
    // pord=4 (excluir) se ignora — ya están filtrados por contactar
}

$sqlSelect = "SELECT s.rowid, s.nom, s.fk_re_subtypent, sub.libelle AS subtipo_label,
                     ef.calle, ef.numero, ef.barrio, ef.contactar,
                     COALESCE(ef.cocheras_fijas, 0) AS cocheras_fijas,
                     COALESCE(ef.coch_fijas_estimadas, 0) AS coch_fijas_estimadas,
                     ef.prioridad_de_contacto,
                     ef.usdtasacion,
                     COALESCE(ef.usdventa, 0) AS usdventa,
                     ef.tipodepropiedad,
                     COALESCE(ef.plantas, 1) AS plantas,
                     COALESCE(ef.supparcela, 0) AS supparcela,
                     COALESCE(ef.mtsfrente, 0) AS mtsfrente,
                     COALESCE(ef.mtsfondo, 0) AS mtsfondo,
                     COALESCE(ef.enficha, 0) AS enficha,
                     ug.rowid AS gest_id, ug.fecha AS ult_fecha, ug.canal AS ult_canal,
                     ug.resultado AS ult_resultado, ug.nota AS ult_nota,
                     ug.propietario_nombre AS gest_prop_nombre,
                     ug.propietario_telefono AS gest_prop_tel,
                     ug.dias_desde_contacto,
                     ug.fk_societe_propietario,
                     pv.propietarios_nom, pv.propietarios_tel, pv.propietarios_email,
                     ci.total_interesados, ci.interesados_calientes,
                     "
                     . semaforo_sql() .
                     " AS semaforo,
                     -- Orden por prioridad (generado por prio_orden_sql())
                     "
                     . prio_orden_sql() .
                     " AS prioridad_orden";

// Cuando ordena por semaforo, usar alias de la subquery; si no, usar columna directa
if ($sortfield === 'semaforo' || $sortfield === 'prioridad') {
    $sql = "SELECT * FROM ($sqlSelect $sqlFrom $sqlWhere) AS tbl ORDER BY $sortCol $sortorder, dias_desde_contacto DESC LIMIT $limit OFFSET $offset";
} else {
    $sql = "$sqlSelect $sqlFrom $sqlWhere ORDER BY $sortCol $sortorder LIMIT $limit OFFSET $offset";
}
$res2 = $db->query($sql);
if (!$res2) {
    setEventMessages('Error SQL captacion: ' . $db->lasterror(), null, 'errors');
}
$rows = [];
while ($res2 && ($o = $db->fetch_object($res2))) $rows[] = $o;

// Colores para resultados
// Colores de resultado — definidos en helpers.php::getResColor()

// Token
$token = getToken();

// Usuarios para vendedor
$sqlUsers = "SELECT rowid, CONCAT(firstname,' ',lastname) AS nom FROM ".MAIN_DB_PREFIX."user WHERE statut=1 ORDER BY lastname";
$resUsers = $db->query($sqlUsers);
$usuarios = [];
while ($resUsers && ($u=$db->fetch_object($resUsers))) $usuarios[$u->rowid] = trim($u->nom);



llxHeader('', 'Captación — Gestión de activos');
?>

<!-- Nav modos -->
<div style="display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
  <span class="butActionDelete" style="cursor:default;opacity:1;background:#0d6efd;border-color:#0d6efd">
    <span class="fas fa-building" style="margin-right:5px"></span>Modo Captación
  </span>
  <a href="/custom/realestatecrmfields/compradores.php" class="butAction">
    <span class="fas fa-users" style="margin-right:5px"></span>Modo Compradores
  </a>
  <span style="font-size:.88em;color:#888;padding:0 8px;align-self:center">
    <?= $total ?> activo<?= $total!=1?'s':'' ?>
    <?php if ($search_prioridad || $search_subtipo || $search_nom || $search_enficha || $search_resultado || $search_dias || $search_contel || $search_tasado): ?>
      <span style="color:#0d6efd"> · filtrado</span>
    <?php endif; ?>
  </span>
  <a href="/custom/realestatecrmfields/pendientes.php" class="butAction" style="margin-left:auto">
    <span class="fas fa-bell" style="margin-right:5px"></span>Pendientes
  </a>
</div>

<div class="div-table-responsive">
<form method="GET" id="captForm">
<input type="hidden" name="sortfield" value="<?= dol_escape_htmltag($sortfield) ?>">
<input type="hidden" name="sortorder" value="<?= dol_escape_htmltag($sortorder) ?>">
<input type="hidden" name="page" value="0">

<?php
// Conteo por prioridad para el resumen
$cntAlta = $cntMedia = $cntBaja = $cntSinPrio = 0;
foreach ($rows as $_r) {
    $_c  = (int)($_r->cocheras_fijas ?: ($_r->coch_fijas_estimadas ?? 0));
    $_b  = trim($_r->barrio ?? '');
    $_p  = calc_prioridad_php($_c, $_b, $_r->prioridad_de_contacto ?? null);
    if ($_p === 'alta')         $cntAlta++;
    elseif ($_p === 'media')    $cntMedia++;
    elseif ($_p === 'baja')     $cntBaja++;
    else                        $cntSinPrio++;
}
// Totales globales (todos los filtros, sin LIMIT)
$totalSinPrio = $total - $totalAlta - $totalMedia - $totalBaja;
?>
<div class="re-prio-summary">
  <span style="color:#888">En pantalla:</span>
  <span style="background:#c0392b;color:#fff;padding:1px 10px;border-radius:10px">🔴 Alta: <?= $cntAlta ?><?= $totalAlta > $cntAlta ? ' <span style="opacity:.7">('.$totalAlta.')</span>' : '' ?></span>
  <span style="background:#fd7e14;color:#fff;padding:1px 10px;border-radius:10px">🟠 Media: <?= $cntMedia ?><?= $totalMedia > $cntMedia ? ' <span style="opacity:.7">('.$totalMedia.')</span>' : '' ?></span>
  <span style="background:#6c757d;color:#fff;padding:1px 10px;border-radius:10px">⚪ Baja: <?= $cntBaja ?><?= $totalBaja > $cntBaja ? ' <span style="opacity:.7">('.$totalBaja.')</span>' : '' ?></span>
  <?php if ($cntSinPrio > 0 || $totalSinPrio > 0): ?>
  <span style="background:#adb5bd;color:#fff;padding:1px 10px;border-radius:10px">— Sin prio: <?= $cntSinPrio ?><?= $totalSinPrio > $cntSinPrio ? ' <span style="opacity:.7">('.$totalSinPrio.')</span>' : '' ?></span>
  <?php endif; ?>
</div>
<table class="noborder centpercent liste">
<tr class="liste_titre">
  <th><a href="<?= reSort('s.nom',$sortfield,$sortorder) ?>">Activo<?= reSortIcon('s.nom',$sortfield,$sortorder) ?></a></th>
  <th>Subtipo</th>
  <th>Barrio</th>
  <th>Propietario/s</th>
  <th><a href="<?= reSort('ug.resultado',$sortfield,$sortorder) ?>">Último resultado<?= reSortIcon('ug.resultado',$sortfield,$sortorder) ?></a></th>
  <th>Estado prop.</th>
  <th><a href="<?= reSort('ug.fecha',$sortfield,$sortorder) ?>">Último contacto<?= reSortIcon('ug.fecha',$sortfield,$sortorder) ?></a></th>
  <th class="center"><a href="<?= reSort('dias_sin_contacto',$sortfield,$sortorder) ?>">Días<?= reSortIcon('dias_sin_contacto',$sortfield,$sortorder) ?></a> / <a href="<?= reSort('prioridad',$sortfield,$sortorder) ?>">Prio<?= reSortIcon('prioridad',$sortfield,$sortorder) ?></a></th>
  <th class="center"><a href="<?= reSort('ci.interesados_calientes',$sortfield,$sortorder) ?>">Interesados<?= reSortIcon('ci.interesados_calientes',$sortfield,$sortorder) ?></a></th>
  <th class="center nowrap"><a href="<?= reSort('usd_precio',$sortfield,$sortorder) ?>">USD<?= reSortIcon('usd_precio',$sortfield,$sortorder) ?></a><br><small style="font-weight:normal;color:#888">$/coch · $/m²</small></th>
  <th>Acción</th>
</tr>

<!-- Filtros -->
<tr class="liste_titre">
  <td><input type="text" name="search_nom" value="<?= dol_escape_htmltag($search_nom) ?>" class="flat maxwidth150" placeholder="Nombre o calle…"></td>
  <td>\n    <select name="search_subtipo" class="flat" onchange="this.form.submit()">
      <option value="">— Todos —</option>
      <?php foreach ($subtipo_opts as $k=>$v): ?>
      <option value="<?= $k ?>"<?= $search_subtipo===$k?' selected':'' ?>><?= dol_htmlentities(subAbrev($v)) ?></option>
      <?php endforeach; ?>
    </select>
  </td>
  <td></td>
  <td>
    <select name="search_vendedor_capt" class="flat" onchange="this.form.submit()" style="font-size:.82em;width:100%">
      <option value="">— Vendedor —</option>
      <?php foreach ($usuarios as $uid=>$unom): ?>
      <option value="<?= $uid ?>"<?= $search_vendedor===$uid?' selected':'' ?>><?= dol_htmlentities($unom) ?></option>
      <?php endforeach; ?>
    </select>
  </td>
  <td>
    <select name="search_resultado" class="flat" onchange="this.form.submit()">
      <option value="">— Todos —</option>
      <?php foreach ($resultados as $k=>$v): if (!$k) continue; ?>
      <option value="<?= $k ?>"<?= $search_resultado===$k?' selected':'' ?>><?= dol_htmlentities($v) ?></option>
      <?php endforeach; ?>
    </select>
  </td>
  <td></td>
  <td></td>
  <td class="center">
    <div style="display:flex;align-items:center;gap:3px;margin-bottom:4px" title="Activos sin contacto hace más de X días">
      <span style="font-size:.78em;color:#888">≥</span>
      <input type="number" name="search_dias" value="<?= $search_dias ?: '' ?>" class="flat" style="width:46px" placeholder="días" min="1">
      <span style="font-size:.78em;color:#888">días sin contacto</span>
    </div>
    <select name="search_prioridad" class="flat" onchange="this.form.submit()" style="width:100%;font-size:.8em">
      <option value="">— Prioridad —</option>
      <option value="alta"<?= $search_prioridad==='alta'?' selected':'' ?>>🔴 Alta</option>
      <option value="media"<?= $search_prioridad==='media'?' selected':'' ?>>🟠 Media</option>
      <option value="baja"<?= $search_prioridad==='baja'?' selected':'' ?>>⚪ Baja</option>
    </select>
  </td>
  <td>
    <select name="search_enficha" class="flat" onchange="this.form.submit()" style="width:100%;font-size:.85em">
      <option value="">— Ficha —</option>
      <option value="1"<?= $search_enficha==='1'?' selected':'' ?>>✅ En ficha</option>
      <option value="0"<?= $search_enficha==='0'?' selected':'' ?>>⬜ No en ficha</option>
    </select>
    <button type="submit" class="butAction" style="padding:2px 8px;margin-top:4px;font-size:.82em">Buscar</button>
    <a href="?" style="font-size:.82em;margin-left:4px;color:#888">✕</a>
  </td>
  <td></td>
  <td>
    <label style="font-size:.82em;white-space:nowrap;cursor:pointer;display:block">
      <input type="checkbox" name="search_contel" value="1"<?= $search_contel ? ' checked' : '' ?> onchange="this.form.submit()">
      📞 Con tel.
    </label>
    <label style="font-size:.82em;white-space:nowrap;cursor:pointer;display:block;margin-top:3px">
      <input type="checkbox" name="search_tasado" value="1"<?= $search_tasado ? ' checked' : '' ?> onchange="this.form.submit()">
      💲 Tasados
    </label>
  </td>
</tr>

<?php if (empty($rows)): ?>
<tr><td colspan="11" class="opacitymedium" style="padding:16px">Sin resultados.</td></tr>
<?php else: foreach ($rows as $row):
  $dir = trim(($row->calle ?: '') . ' ' . ($row->numero ?: ''));
  $label = $dir ?: $row->nom;
  $resColor = getResColor($row->ult_resultado ?? '');
  $resLabel = $resultados[$row->ult_resultado ?? ''] ?? ($row->ult_resultado ?: '—');
  $dias      = $row->dias_desde_contacto;
  $enFicha   = (int)($row->enficha ?? 0);
  $calor     = ''; // nivel_calor eliminado
  $interes   = ''; // campo interes_venta no existe en DB

  // Determinar prioridad: campo explícito > cocheras_fijas
  $prioridad = $row->prioridad_de_contacto ?? null;
  $cocheras_real = (int)($row->cocheras_fijas ?? 0);
  $cocheras_est  = (int)($row->coch_fijas_estimadas ?? 0);
  $cocheras      = $cocheras_real > 0 ? $cocheras_real : $cocheras_est;
  $es_estimado   = ($cocheras_real === 0 && $cocheras_est > 0);
  $barrio_row = trim($row->barrio ?? '');

  // Prioridad: campo explícito manda, sino modelo score×barrio
  $prioridad = calc_prioridad_php($cocheras, $barrio_row, $prioridad);

  // Colorear días — lógica centralizada en helpers.php
  $diasColor = colorSemaforo($dias, $enFicha, $calor, $prioridad);

  // Estilo de fila para en_ficha
  $rowStyle = $enFicha ? 're-row-enficha' : '';

  // Propietario: primero el de la tabla nueva, sino el del último contacto
  // Jerarquía de propietario: re_propietario_activo (vinculado) > último contacto > texto libre
  // Fuente canónica: re_propietario_activo — las otras son legado y deben migrarse con el tiempo
  $tieneVinculado = !empty($row->propietarios_nom); // tiene propietario en re_propietario_activo
  // Propietario — fuente única: re_propietario_activo (tabla vinculada)
  // Fallback: nombre/tel del último contacto registrado (datos legacy o sin vincular)
  $propNom   = $row->propietarios_nom ?: ($row->gest_prop_nombre ?: '—');
  $propTel   = $row->propietarios_tel ?: ($row->gest_prop_tel   ?: '');
  $propEmail = trim(explode(' / ', $row->propietarios_email ?? '')[0]);
?>
<tr class="oddeven <?= $rowStyle ?>">
  <td>
    <a href="/societe/card.php?socid=<?= $row->rowid ?>" style="font-weight:600">
      <?= dol_htmlentities(dol_trunc($label, 40)) ?>
    </a>
    <?php if ($enFicha): ?>
    <span style="background:#198754;color:#fff;font-size:.7em;padding:1px 6px;border-radius:8px;margin-left:5px;vertical-align:middle">
      En ficha
    </span>
    <?php endif; ?>
  </td>
  <td><small><?= dol_htmlentities(subAbrev($row->subtipo_label ?: '—')) ?></small></td>
  <td><small><?= dol_htmlentities($row->barrio ?: '—') ?></small></td>
  <td>
    <span style="font-size:.88em"><?= dol_htmlentities(dol_trunc($propNom, 30)) ?></span>
    <?php if (!$tieneVinculado && $propNom !== '—'): ?>
    <span style="color:#aaa;font-size:.72em" title="Propietario no vinculado formalmente — cargado como texto libre">⚠</span>
    <?php endif; ?>
    <?php if ($propTel): ?>
    <br><?= reTelLinks($propTel) ?>
    <?php endif; ?>
    <?php
      $propSocid = (int)($row->fk_societe_propietario ?? 0);
      if ($propEmail && $propSocid > 0): ?>
    <br><a href="/societe/card.php?socid=<?= $propSocid ?>&action=presend&mode=init#formmailbeforetitle"
       style="font-size:.8em;color:#0d6efd;text-decoration:none"
       title="Enviar email desde Dolibarr">✉️ <?= dol_htmlentities($propEmail) ?></a>
    <?php elseif ($propEmail): ?>
    <br><a href="mailto:<?= dol_escape_htmltag($propEmail) ?>"
       style="font-size:.8em;color:#0d6efd;text-decoration:none"
       title="<?= dol_escape_htmltag($propEmail) ?>">✉️ <?= dol_htmlentities($propEmail) ?></a>
    <?php endif; ?>
  </td>
  <td>
    <?php if ($row->ult_resultado): ?>
    <span style="background:<?= $resColor ?>;color:#fff;padding:2px 8px;border-radius:12px;font-size:.8em;white-space:nowrap">
      <?= dol_htmlentities($resLabel) ?>
    </span>
    <?php else: ?>
    <span class="opacitymedium" style="font-size:.85em">Sin contacto</span>
    <?php endif; ?>
    <?php if ($row->ult_nota): ?>
    <br><small class="opacitymedium re-nota-tip"
         style="cursor:help;border-bottom:1px dotted #aaa"
         title="<?= dol_escape_htmltag($row->ult_nota) ?>"><?= dol_htmlentities(dol_trunc($row->ult_nota, 40)) ?><?= strlen($row->ult_nota) > 40 ? '…' : '' ?></small>
    <?php endif; ?>
  </td>
  <?php
    // Estado del propietario — columna separada
    $calorColors  = ['caliente'=>'#c0392b','tibio'=>'#fd7e14','frio'=>'#6c757d'];
    $calorLabels  = ['caliente'=>'🔥 Caliente','tibio'=>'🌡 Tibio','frio'=>'🧊 Frío'];
    $interesLabels = ['SI'=>['✅','Vende'],'TAL_VEZ'=>['🤔','Tal vez'],'NO'=>['❌','No vende']];
  ?>
  <td class="re-estado-prop">
    <?php if ($calor): ?>
    <span style="color:<?= $calorColors[$calor] ?? '#888' ?>;font-weight:600">
      <?= $calorLabels[$calor] ?? $calor ?>
    </span>
    <?php endif; ?>
    <?php if ($interes && isset($interesLabels[$interes])): ?>
    <?php if ($calor): ?><br><?php endif; ?>
    <?php
      $interesColors = ['SI'=>'#198754','TAL_VEZ'=>'#fd7e14','NO'=>'#c0392b'];
      $interesColor  = $interesColors[$interes] ?? '#888';
    ?>
    <span style="color:<?= $interesColor ?>">
      <?= $interesLabels[$interes][0] ?> <?= $interesLabels[$interes][1] ?>
    </span>
    <?php endif; ?>
    <?php
      // Mostrar precio más relevante: usdventa manda, sino usdtasacion
      $precio_estado = (float)($row->usdventa > 0 ? $row->usdventa : ($row->usdtasacion ?? 0));
      $color_estado  = $row->usdventa > 0 ? '#198754' : '#6f42c1';
      $label_estado  = $row->usdventa > 0 ? '💲' : '💲';
    ?>
    <?php if ($precio_estado > 0): ?>
    <?php if ($calor || $interes): ?><br><?php endif; ?>
    <span style="color:<?= $color_estado ?>">💲 <?= number_format($precio_estado, 0, ',', '.') ?></span>
    <?php endif; ?>
    <?php if (!$calor && !$interes && empty($row->usdtasacion)): ?>
    <span class="opacitymedium">—</span>
    <?php endif; ?>
  </td>
  <td class="nowrap">
    <?php if ($row->ult_fecha): ?>
    <small><?= date('d/m/Y', strtotime($row->ult_fecha)) ?></small>
    <?php else: ?><span class="opacitymedium" style="font-size:.85em">Nunca</span><?php endif; ?>
  </td>
  <td class="center">
    <span style="font-weight:700;color:<?= $diasColor ?>">
      <?= $dias !== null ? $dias : '∞' ?>
    </span>
    <br>
    <?php
      $prioBadges = ['alta'=>['#c0392b','Alta'],'media'=>['#fd7e14','Media'],'baja'=>['#6c757d','Baja']];
      [$prioCol,$prioLbl] = $prioBadges[$prioridad] ?? ['#dee2e6','—'];
    ?>
    <span style="font-size:.7em;background:<?= $prioCol ?>;color:#fff;padding:1px 5px;border-radius:8px;opacity:.8">
      <?= $prioLbl ?>
    </span>
    <?php if ($cocheras > 0): ?>
    <br><span class="<?= $es_estimado ? 're-coch-estimada' : 'opacitymedium' ?>" style="font-size:.75em">
      <?= $cocheras ?> coch.<?= $es_estimado ? ' ~' : '' ?>
    </span>
    <?php endif; ?>
  </td>
  <td class="center">
    <?php if ($row->total_interesados): ?>
      <span style="font-size:.85em"><?= (int)$row->total_interesados ?></span>
      <?php if ($row->interesados_calientes > 0): ?>
        <span style="background:#fd7e14;color:#fff;padding:1px 6px;border-radius:10px;font-size:.75em;margin-left:3px">
          <?= (int)$row->interesados_calientes ?> 🔥
        </span>
      <?php endif; ?>
    <?php else: ?>
      <span class="opacitymedium">—</span>
    <?php endif; ?>
  </td>
  <?php
    // Precio: usdventa manda, sino usdtasacion
    $precio_usd   = (float)($row->usdventa > 0 ? $row->usdventa : ($row->usdtasacion ?? 0));
    $coch_calc    = $cocheras > 0 ? $cocheras : 0;
    $supparcela  = (float)($row->supparcela ?? 0);
    $mtsfrente   = (float)($row->mtsfrente ?? 0);
    $mtsfondo    = (float)($row->mtsfondo  ?? 0);
    $plantas_val = (float)($row->plantas   ?? 1);
    $sup_total   = ($supparcela > 0 ? $supparcela : ($mtsfrente * $mtsfondo)) * $plantas_val;
    $usd_x_coch   = ($precio_usd > 0 && $coch_calc > 0) ? round($precio_usd / $coch_calc) : 0;
    $usd_x_m2     = ($precio_usd > 0 && $sup_total > 0) ? round($precio_usd / $sup_total) : 0;
    $tipo_prop    = strtolower(trim($row->tipodepropiedad ?? ''));
    $tipo_label   = ['lotepropio'=>'Lote','ph'=>'PH'][$tipo_prop] ?? '';
    $precio_fuente = ($row->usdventa > 0) ? 'venta' : (($row->usdtasacion > 0) ? 'tasac.' : '');
    $precio_color  = ($row->usdventa > 0) ? '#198754' : '#6f42c1';
  ?>
  <td class="center" style="font-size:.82em;white-space:nowrap">
    <?php if ($precio_usd > 0): ?>
      <span class="<?= $row->usdventa > 0 ? 're-precio-venta' : 're-precio-tasac' ?>">
        USD <?= number_format($precio_usd, 0, ',', '.') ?>
      </span>
      <small style="color:#aaa;font-size:.85em"><?= $precio_fuente ?></small>
      <?php if ($tipo_label): ?>
        <span class="re-tipo-badge"><?= $tipo_label ?></span>
      <?php endif; ?>
      <?php if ($usd_x_coch > 0): ?>
      <br><small class="re-precio-ratio">
        <?= number_format($usd_x_coch, 0, ',', '.') ?>/coch
        <?= $usd_x_m2 > 0 ? ' · ' . number_format($usd_x_m2, 0, ',', '.') . '/m²' : '' ?>
      </small>
      <?php endif; ?>
    <?php else: ?>
      <span class="opacitymedium">—</span>
    <?php endif; ?>
  </td>
  <td>
    <a href="#" class="capt-contacto-btn butAction"
       style="padding:2px 8px;font-size:.82em;white-space:nowrap"
       data-socid="<?= $row->rowid ?>"
       data-nom="<?= dol_escape_htmltag($label) ?>"
       data-prop-id="<?= (int)($row->fk_societe_propietario ?? 0) ?>"
       data-prop-nom="<?= dol_escape_htmltag($propNom === '—' ? '' : $propNom) ?>"
       data-calor="<?= dol_escape_htmltag($calor) ?>"
       data-interes="<?= dol_escape_htmltag($interes) ?>"
       data-enficha="<?= $enFicha ?>"
       data-prioridad="<?= dol_escape_htmltag($prioridad) ?>"
       data-sin-contacto="<?= $row->ult_fecha ? '0' : '1' ?>">
      + Contacto
    </a>
  </td>
</tr>
<?php endforeach; endif; ?>
</table>
</form>
</div>

<!-- Paginación -->
<?php if ($total > $limit): ?>
<?php
  $pages    = (int)ceil($total / $limit);
  $pageBase = array_merge($_GET, ['page' => 0]);
  // Ventana de páginas: máximo 7 números alrededor de la actual
  $window = 3;
  $pStart = max(0, $page - $window);
  $pEnd   = min($pages - 1, $page + $window);
?>
<div style="margin-top:12px;display:flex;gap:4px;align-items:center;justify-content:center;flex-wrap:wrap">
  <?php if ($page > 0): ?>
  <a href="?<?= http_build_query(array_merge($pageBase, ['page' => $page - 1])) ?>"
     class="butAction" style="padding:3px 10px;font-size:.85em">← Ant.</a>
  <?php endif; ?>

  <?php if ($pStart > 0): ?>
  <a href="?<?= http_build_query(array_merge($pageBase, ['page' => 0])) ?>"
     style="margin:0 4px">1</a>
  <?php if ($pStart > 1): ?><span style="color:#aaa">…</span><?php endif; ?>
  <?php endif; ?>

  <?php for ($i = $pStart; $i <= $pEnd; $i++): ?>
  <a href="?<?= http_build_query(array_merge($pageBase, ['page' => $i])) ?>"
     style="margin:0 2px;padding:2px 8px;border-radius:4px;<?= $i === $page ? 'background:#6c6aa8;color:#fff;font-weight:bold;' : 'color:#555;' ?>"><?= $i + 1 ?></a>
  <?php endfor; ?>

  <?php if ($pEnd < $pages - 1): ?>
  <?php if ($pEnd < $pages - 2): ?><span style="color:#aaa">…</span><?php endif; ?>
  <a href="?<?= http_build_query(array_merge($pageBase, ['page' => $pages - 1])) ?>"
     style="margin:0 4px"><?= $pages ?></a>
  <?php endif; ?>

  <?php if ($page < $pages - 1): ?>
  <a href="?<?= http_build_query(array_merge($pageBase, ['page' => $page + 1])) ?>"
     class="butAction" style="padding:3px 10px;font-size:.85em">Sig. →</a>
  <?php endif; ?>
</div>
<?php endif; ?>
<div class="opacitymedium" style="margin-top:8px;font-size:.9em">
  <?= $total ?> activo<?= $total!=1?'s':'' ?> · mostrando <?= $offset+1 ?>–<?= min($offset+$limit,$total) ?>
</div>

<!-- Modal contacto rápido con propietario -->
<div id="capt-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;overflow-y:auto">
  <div style="background:#fff;max-width:540px;margin:60px auto;border-radius:6px;padding:26px 30px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <button type="button" id="capt-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.3em;cursor:pointer">✕</button>
    <h3 style="margin:0 0 16px">
      <span class="fas fa-phone" style="color:#0d6efd;margin-right:6px"></span>
      Registrar contacto — <span id="capt-nom" style="color:#0d6efd"></span>
    </h3>
    <input type="hidden" id="capt-activo-id">
    <input type="hidden" id="capt-prop-id">

    <!-- Propietario — buscar, vincular o crear -->
    <div style="margin-bottom:14px;padding:10px 12px;background:#f0f4ff;border-radius:4px;border-left:3px solid #0d6efd">
      <label style="font-weight:600;display:block;margin-bottom:6px;color:#0d6efd">
        <span class="fas fa-user-tie" style="margin-right:4px"></span>Propietario
      </label>

      <!-- Propietario encontrado/seleccionado -->
      <div id="capt-prop-found" style="display:none;align-items:center;gap:8px;margin-bottom:6px">
        <strong id="capt-prop-found-nom"></strong>
        <small id="capt-prop-found-tel" class="opacitymedium"></small>
        <a href="#" id="capt-prop-clear" style="color:#c0392b;font-size:.85em;margin-left:4px">✕ cambiar</a>
      </div>

      <!-- Búsqueda -->
      <div id="capt-prop-search-wrap">
        <div style="position:relative">
          <input type="text" id="capt-prop-search" class="flat" placeholder="Buscar actor por nombre o tel…"
                 style="width:100%;box-sizing:border-box">
          <div id="capt-prop-results" style="display:none;border:1px solid #ccc;max-height:150px;overflow-y:auto;background:#fff;position:absolute;z-index:10001;width:100%"></div>
        </div>
      </div>

      <!-- Crear nuevo propietario -->
      <div style="margin-top:8px">
        <label style="font-size:.85em;cursor:pointer;color:#555">
          <input type="checkbox" id="capt-prop-nuevo-chk" style="margin-right:5px">
          Crear nuevo propietario (se registrará como Actor en Dolibarr)
        </label>
        <div id="capt-prop-nuevo-fields" style="display:none;margin-top:8px;padding:8px;background:#fff;border-radius:4px;border:1px solid #c8d8ff">
          <input type="text" id="capt-prop-nombre" class="flat" placeholder="Nombre completo *"
                 style="width:100%;box-sizing:border-box;margin-bottom:6px">
          <input type="text" id="capt-prop-telefono" class="flat" placeholder="Teléfono"
                 style="width:100%;box-sizing:border-box">
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
      <div>
        <label style="font-weight:600;display:block;margin-bottom:4px">Canal</label>
        <select id="capt-canal" class="flat" style="width:100%">
          <?php foreach ($canales as $k=>$v): ?>
          <option value="<?= $k ?>"><?= dol_htmlentities($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label style="font-weight:600;display:block;margin-bottom:4px">Resultado</label>
        <select id="capt-resultado" class="flat" style="width:100%">
          <?php foreach ($resultados as $k=>$v): ?>
          <option value="<?= $k ?>"><?= dol_htmlentities($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div style="margin-bottom:12px">
      <label style="font-weight:600;display:block;margin-bottom:4px">Nota</label>
      <textarea id="capt-nota" class="flat" rows="2" style="width:100%;box-sizing:border-box" placeholder="Qué dijo, cómo respondió…"></textarea>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
      <div>
        <label style="font-weight:600;display:block;margin-bottom:4px">Vendedor</label>
        <select id="capt-vendedor" class="flat" style="width:100%">
          <option value="">— Sin asignar —</option>
          <?php foreach ($usuarios as $uid=>$unom): ?>
          <option value="<?= $uid ?>"><?= dol_htmlentities($unom) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label style="font-weight:600;display:block;margin-bottom:4px">Recordatorio en</label>
        <div style="display:flex;align-items:center;gap:6px">
          <input type="number" id="capt-rec-dias" class="flat" style="width:60px" placeholder="días" min="1">
          <span id="capt-rec-preview" style="font-size:.82em;color:#0d6efd"></span>
        </div>
      </div>
    </div>

    <!-- Interés en vender -->
    <div style="margin-bottom:12px">
      <div>
        <label style="font-weight:600;display:block;margin-bottom:4px">Interés en vender</label>
        <select id="capt-interes" class="flat" style="width:100%">
          <option value="">— Sin cambio —</option>
          <option value="SI">✅ Sí, interesado</option>
          <option value="TAL_VEZ">🤔 Tal vez</option>
          <option value="NO">❌ No vende</option>
        </select>
      </div>
    </div>
    <div id="capt-rec-auto" style="display:none;padding:8px 10px;background:#e8f5e9;border-radius:4px;border-left:3px solid #198754;margin-bottom:12px;font-size:.85em">
      <span class="fas fa-magic" style="margin-right:4px;color:#198754"></span>
      <span id="capt-rec-auto-txt"></span>
    </div>
    <div id="capt-tasacion-wrap" style="display:none;margin-bottom:12px;padding:10px 12px;background:#f5f0ff;border-radius:4px;border-left:3px solid #6f42c1">
      <label style="font-weight:600;display:block;margin-bottom:6px;color:#6f42c1">
        <span class="fas fa-dollar-sign" style="margin-right:4px"></span>Tasación USD
      </label>
      <div style="display:flex;align-items:center;gap:8px">
        <span style="color:#888;font-size:.9em">USD</span>
        <input type="number" id="capt-tasacion" class="flat" style="width:140px" placeholder="ej: 1600000" min="0" step="1000">
        <span id="capt-tasacion-preview" style="font-size:.85em;color:#6f42c1;font-weight:600"></span>
      </div>
      <small style="color:#888;margin-top:4px;display:block">Se guardará en el campo Tasación del activo</small>
    </div>
    <div style="text-align:right">
      <button type="button" id="capt-cancel" class="butActionDelete" style="margin-right:8px">Cancelar</button>
      <button type="button" id="capt-submit" class="butAction">Guardar contacto</button>
    </div>
    <div id="capt-error" style="color:#c0392b;margin-top:8px;font-size:.9em;display:none"></div>
  </div>
</div>

<script>
// Configuración inyectada desde PHP — el resto del JS está en captacion.js
window.RE_CAPT = {
    TOKEN:    '<?= dol_escape_js($token) ?>',
    AJAX_URL: '/custom/realestatecrmfields/ajax/gestion_save.php',
    UPDATE_URL: '/custom/realestatecrmfields/ajax/update_extrafields.php',
    PROP_URL: '/custom/realestatecrmfields/ajax/propietario_save.php',
};
</script>
<script src="<?= DOL_URL_ROOT ?>/custom/realestatecrmfields/js/captacion.js"></script>


<?php
llxFooter();
$db->close();
