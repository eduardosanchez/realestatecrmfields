<?php
/**
 * Modo Compradores — Seguimiento de interesados activos
 * URL: /custom/realestatecrmfields/compradores.php
 */
$res = @include '../../main.inc.php';
if (!$res) $res = @include '../../../main.inc.php';
if (!$res) die('main.inc.php not found');

require_once dol_buildpath('/custom/realestatecrmfields/class/reconsulta.class.php', 0);
require_once __DIR__ . '/helpers.php';

$langs->load('realestatecrmfields');

// ── Filtros ─────────────────────────────────────────────────
$search_nom      = GETPOST('search_nom',      'alphanohtml');
$search_phone    = GETPOST('search_phone',    'alphanohtml');
$search_usd_min  = GETPOST('search_usd_min',  'int');
$search_usd_max  = GETPOST('search_usd_max',  'int');
$search_estado   = GETPOST('search_estado',   'alpha');
$search_subtipo  = GETPOST('search_subtipo',  'alpha');
$search_rec      = GETPOST('search_rec',      'alpha'); // 'vencido' | 'pendiente' | ''
$search_vendedor = (int)GETPOST('search_vendedor', 'int');
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'ultima_consulta';
$sortorder = GETPOST('sortorder', 'aZ09') ?: 'DESC';
$page  = max(0, (int)GETPOST('page', 'int'));
$limit = 50;
$offset = $page * $limit;

$hoy = date('Y-m-d');

// Subtipos RE_AOR
$sqlSubs = "SELECT code, libelle FROM " . MAIN_DB_PREFIX . "c_re_subtypent WHERE fk_typent = 'RE_AOR' AND active = 1 ORDER BY position";
$resSubs = $db->query($sqlSubs);
$subtipo_opts = [];
while ($resSubs && ($o = $db->fetch_object($resSubs))) $subtipo_opts[$o->code] = $o->libelle;

// ID del tipo RE_AOR — cargado una vez para el botón "Nuevo interesado"
$rTipoAor   = $db->query("SELECT id FROM " . MAIN_DB_PREFIX . "c_typent WHERE code='RE_AOR' LIMIT 1");
$idTipoAor  = ($rTipoAor && ($oTipo = $db->fetch_object($rTipoAor))) ? (int)$oTipo->id : 0;

// ── Query principal ─────────────────────────────────────────
// Para cada actor, traemos el estado de su consulta más reciente
$sqlFrom = "FROM " . MAIN_DB_PREFIX . "societe s
            INNER JOIN " . MAIN_DB_PREFIX . "c_typent t ON t.id = s.fk_typent AND t.code = 'RE_AOR'
            LEFT JOIN " . MAIN_DB_PREFIX . "c_re_subtypent sub ON sub.code = s.fk_re_subtypent
            LEFT JOIN " . MAIN_DB_PREFIX . "societe_extrafields ef_actor ON ef_actor.fk_object = s.rowid
            LEFT JOIN (
                SELECT c1.*,
                    sa.nom AS activo_nom,
                    ef.calle AS activo_calle, ef.numero AS activo_numero
                FROM " . MAIN_DB_PREFIX . "re_consulta c1
                LEFT JOIN " . MAIN_DB_PREFIX . "societe sa ON sa.rowid = c1.fk_societe_activo
                LEFT JOIN " . MAIN_DB_PREFIX . "societe_extrafields ef ON ef.fk_object = c1.fk_societe_activo
                INNER JOIN (
                    SELECT fk_societe_actor, MAX(date_consulta) AS max_fecha
                    FROM " . MAIN_DB_PREFIX . "re_consulta
                    WHERE fk_societe_actor IS NOT NULL
                    GROUP BY fk_societe_actor
                ) lc ON lc.fk_societe_actor = c1.fk_societe_actor AND lc.max_fecha = c1.date_consulta
            ) uc ON uc.fk_societe_actor = s.rowid
            LEFT JOIN (
                SELECT fk_societe_actor,
                    COUNT(*) AS total_consultas,
                    SUM(CASE WHEN estado != 'CERRO' THEN 1 ELSE 0 END) AS consultas_abiertas,
                    MIN(CASE WHEN recordatorio_done = 0 AND fecha_recordatorio IS NOT NULL THEN fecha_recordatorio END) AS prox_recordatorio
                FROM " . MAIN_DB_PREFIX . "re_consulta
                WHERE fk_societe_actor IS NOT NULL
                GROUP BY fk_societe_actor
            ) stats ON stats.fk_societe_actor = s.rowid";

$sqlWhere = " WHERE s.status = 1";
if ($search_nom)    $sqlWhere .= " AND s.nom LIKE '%" . $db->escape($search_nom) . "%'";
if ($search_phone)  $sqlWhere .= " AND s.phone LIKE '%" . $db->escape($search_phone) . "%'";
if ($search_subtipo) {
    $sqlWhere .= " AND s.fk_re_subtypent = '" . $db->escape($search_subtipo) . "'";
} else {
    // Excluir propietarios puros — salvo que también sean inversores
    $sqlWhere .= " AND (s.fk_re_subtypent IS NULL OR s.fk_re_subtypent != 'PROPIETARIO' OR ef_actor.es_inversor = 'SI')";
}
if ($search_usd_min) $sqlWhere .= " AND CAST(ef_actor.monto_inversion_usd AS UNSIGNED) >= " . (int)$search_usd_min;
if ($search_usd_max) $sqlWhere .= " AND CAST(ef_actor.monto_inversion_usd AS UNSIGNED) <= " . (int)$search_usd_max;
if ($search_estado)  $sqlWhere .= " AND uc.estado = '" . $db->escape($search_estado) . "'";
if ($search_rec === 'vencido')   $sqlWhere .= " AND stats.prox_recordatorio IS NOT NULL AND stats.prox_recordatorio <= '$hoy'";
if ($search_rec === 'pendiente') $sqlWhere .= " AND stats.prox_recordatorio IS NOT NULL AND stats.prox_recordatorio > '$hoy'";
if ($search_rec === 'sin')       $sqlWhere .= " AND stats.prox_recordatorio IS NULL";
if ($search_vendedor)            $sqlWhere .= " AND uc.fk_user_vendedor = " . (int)$search_vendedor;

$allowed_sort = ['s.nom', 'uc.estado', 'uc.date_consulta AS ultima_consulta', 'stats.total_consultas', 'stats.prox_recordatorio'];
$sortMap = [
    's.nom'               => 's.nom',
    'ultima_consulta'     => 'uc.date_consulta',
    'stats.total_consultas' => 'stats.total_consultas',
    'uc.estado'           => 'uc.estado',
    'prox_recordatorio'   => 'stats.prox_recordatorio',
];
$sortCol = $sortMap[$sortfield] ?? 'uc.date_consulta';
$sortorder = ($sortorder === 'ASC') ? 'ASC' : 'DESC';

$sqlCount = "SELECT COUNT(*) AS total $sqlFrom $sqlWhere";
$resCount = $db->query($sqlCount);
$total    = ($resCount) ? (int)$db->fetch_object($resCount)->total : 0;

$sqlSelect = "SELECT s.rowid, s.nom, s.phone, s.email, s.fk_re_subtypent, sub.libelle AS subtipo_label,
                     ef_actor.origen_lead, ef_actor.monto_inversion_usd, ef_actor.es_inversor,
                     ef_actor.busqueda_actor,
                     0 AS monto_min_usd,
                     0 AS monto_max_usd,
                     uc.rowid AS consulta_id, uc.estado, uc.date_consulta AS ultima_consulta,
                     uc.nota, uc.busqueda, uc.motivo_cierre,
                     uc.activo_nom, uc.activo_calle, uc.activo_numero,
                     uc.fk_societe_activo,
                     uc.fecha_recordatorio, uc.nota_recordatorio, uc.recordatorio_done,
                     stats.total_consultas, stats.consultas_abiertas, stats.prox_recordatorio";
$sql = "$sqlSelect $sqlFrom $sqlWhere ORDER BY $sortCol $sortorder LIMIT $limit OFFSET $offset";
$res2 = $db->query($sql);
$rows = [];
while ($res2 && ($o = $db->fetch_object($res2))) $rows[] = $o;

$estColors = ReConsulta::ESTADO_COLORS;
$estados   = ReConsulta::ESTADOS;
$motivos   = ReConsulta::MOTIVOS_CIERRE;



// Token CSRF
$token = getToken();
// Usuarios para filtro vendedor
$sqlUsers2 = "SELECT rowid, CONCAT(firstname,' ',lastname) AS nom FROM ".MAIN_DB_PREFIX."user WHERE statut=1 ORDER BY lastname";
$resUsers2 = $db->query($sqlUsers2);
$usuarios2 = [];
while ($resUsers2 && ($u2=$db->fetch_object($resUsers2))) $usuarios2[$u2->rowid] = trim($u2->nom);

llxHeader('', 'Compradores — Seguimiento');
?>

<!-- Nav modos -->
<div style="display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
  <a href="/custom/realestatecrmfields/captacion.php" class="butAction">
    <span class="fas fa-building" style="margin-right:5px"></span>Modo Captación
  </a>
  <span class="butActionDelete" style="cursor:default;opacity:1;background:#6c6aa8;border-color:#6c6aa8">
    <span class="fas fa-users" style="margin-right:5px"></span>Modo Compradores
  </span>
  <a href="/custom/realestatecrmfields/pendientes.php" class="butAction" style="margin-left:auto">
    <span class="fas fa-bell" style="margin-right:5px"></span>Pendientes
  </a>
  <a href="/societe/card.php?action=create&typent_id=<?= $idTipoAor ?>" class="butAction" style="background:#6c6aa8;border-color:#6c6aa8">
    <span class="fas fa-plus" style="margin-right:5px"></span>Nuevo interesado
  </a>
</div>

<div class="div-table-responsive">
<form method="GET" id="compForm">
<input type="hidden" name="sortfield" value="<?= dol_escape_htmltag($sortfield) ?>">
<input type="hidden" name="sortorder" value="<?= dol_escape_htmltag($sortorder) ?>">
<input type="hidden" name="page" value="0">

<table class="noborder centpercent liste">
<tr class="liste_titre">
  <th><a href="<?= reSort('s.nom',$sortfield,$sortorder) ?>">Interesado<?= reSortIcon('s.nom',$sortfield,$sortorder) ?></a></th>
  <th>Subtipo</th>
  <th><a href="<?= reSort('uc.estado',$sortfield,$sortorder) ?>">Estado consulta<?= reSortIcon('uc.estado',$sortfield,$sortorder) ?></a></th>
  <th>Qué busca</th>
  <th>Última propiedad</th>
  <th><a href="<?= reSort('ultima_consulta',$sortfield,$sortorder) ?>">Última consulta<?= reSortIcon('ultima_consulta',$sortfield,$sortorder) ?></a></th>
  <th><a href="<?= reSort('prox_recordatorio',$sortfield,$sortorder) ?>">Recordatorio<?= reSortIcon('prox_recordatorio',$sortfield,$sortorder) ?></a></th>
  <th class="center"><a href="<?= reSort('ef_actor.monto_inversion_usd',$sortfield,$sortorder) ?>">Rango USD<?= reSortIcon('ef_actor.monto_inversion_usd',$sortfield,$sortorder) ?></a></th>
  <th class="center"><a href="<?= reSort('stats.total_consultas',$sortfield,$sortorder) ?>">Consultas<?= reSortIcon('stats.total_consultas',$sortfield,$sortorder) ?></a></th>
  <th>Acciones</th>
</tr>

<!-- Filtros -->
<tr class="liste_titre">
  <td><input type="text" name="search_nom" value="<?= dol_escape_htmltag($search_nom) ?>" class="flat maxwidth125" placeholder="Nombre…"></td>
  <td>
    <select name="search_subtipo" class="flat" onchange="this.form.submit()">
      <option value="">— Todos —</option>
      <?php foreach ($subtipo_opts as $k=>$v): ?>
      <option value="<?= $k ?>"<?= $search_subtipo===$k?' selected':'' ?>><?= dol_htmlentities($v) ?></option>
      <?php endforeach; ?>
    </select>
  </td>
  <td>
    <select name="search_estado" class="flat" onchange="this.form.submit()">
      <option value="">— Estado —</option>
      <?php foreach ($estados as $k=>$v): ?>
      <option value="<?= $k ?>"<?= $search_estado===$k?' selected':'' ?>><?= dol_htmlentities($v) ?></option>
      <?php endforeach; ?>
    </select>
  </td>
  <td></td>
  <td></td>
  <td></td>
  <td>
    <select name="search_rec" class="flat" onchange="this.form.submit()">
      <option value="">— Todos —</option>
      <option value="vencido"<?= $search_rec==='vencido'?' selected':'' ?>>🔴 Vencido</option>
      <option value="pendiente"<?= $search_rec==='pendiente'?' selected':'' ?>>🔵 Pendiente</option>
      <option value="sin"<?= $search_rec==='sin'?' selected':'' ?>>Sin recordatorio</option>
    </select>
  </td>
  <td class="center nowrap">
    <input type="number" name="search_usd_min" value="<?= (int)$search_usd_min ?: '' ?>" class="flat" style="width:70px" placeholder="Min">
    <span style="color:#aaa">–</span>
    <input type="number" name="search_usd_max" value="<?= (int)$search_usd_max ?: '' ?>" class="flat" style="width:70px" placeholder="Max">
  </td>
  <td></td>
  <td>
    <select name="search_vendedor" class="flat" onchange="this.form.submit()" style="font-size:.82em;width:100%;margin-bottom:4px">
      <option value="">— Vendedor —</option>
      <?php foreach ($usuarios2 as $uid=>$unom): ?>
      <option value="<?= $uid ?>"<?= $search_vendedor===$uid?' selected':'' ?>><?= dol_htmlentities($unom) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="butAction" style="padding:2px 10px;margin:0;font-size:.85em">Buscar</button>
    <a href="?" style="font-size:.82em;margin-left:4px;color:#888">✕</a>
  </td>
</tr>

<?php if (empty($rows)): ?>
<tr><td colspan="9" class="opacitymedium" style="padding:16px">Sin resultados.</td></tr>
<?php else: foreach ($rows as $row):
  $color = $estColors[$row->estado] ?? '#6c757d';
  $actDir = trim(($row->activo_calle ?: '') . ' ' . ($row->activo_numero ?: ''));
  $actLabel = $actDir ?: ($row->activo_nom ?: '—');
  $recVenc = !empty($row->prox_recordatorio) && $row->prox_recordatorio <= $hoy;
  $recPend = !empty($row->prox_recordatorio) && $row->prox_recordatorio > $hoy;
?>
<tr class="oddeven" id="comp-row-<?= $row->rowid ?>">
  <td>
    <a href="/societe/card.php?socid=<?= $row->rowid ?>" style="font-weight:600"><?= dol_htmlentities($row->nom) ?></a>
    <?php if ($row->phone): ?>
    <br><?= reTelLinks($row->phone) ?>
    <?php endif; ?>
    <?php if ($row->email): ?>
    <br><a href="/societe/card.php?socid=<?= $row->rowid ?>&action=presend&mode=init#formmailbeforetitle"
       style="font-size:.8em;color:#0d6efd;text-decoration:none"
       title="Enviar email desde Dolibarr">✉️ <?= dol_htmlentities($row->email) ?></a>
    <?php endif; ?>
  </td>
  <td><small>
    <?= dol_htmlentities($row->subtipo_label ?: '—') ?>
    <?php if (($row->fk_re_subtypent ?? '') === 'PROPIETARIO' && ($row->es_inversor ?? '') === 'SI'): ?>
      <br><span style="background:#6f42c1;color:#fff;border-radius:4px;padding:1px 6px;font-size:.72em">+ Inversor</span>
    <?php endif; ?>
  </small></td>
  <?php
  $busq = $row->busqueda_actor ?? '';
  $busqTrunc = mb_strlen($busq) > 60 ? mb_substr($busq, 0, 60) . '…' : $busq;
  ?>
  <td class="comp-busqueda-celda" style="cursor:pointer;max-width:200px" title="<?= $busq ? 'Clic para expandir' : '' ?>">
    <small>
      <?php if ($busq): ?>
        <span class="comp-busq-corta"><?= dol_htmlentities($busqTrunc) ?></span>
        <span class="comp-busq-completa" style="display:none;white-space:pre-wrap"><?= dol_htmlentities($busq) ?></span>
      <?php else: ?>
        <span class="opacitymedium">—</span>
      <?php endif; ?>
    </small>
  </td>
  <td>
    <?php if ($row->estado): ?>
    <span style="background:<?= $color ?>;color:#fff;padding:2px 9px;border-radius:12px;font-size:.82em;white-space:nowrap">
      <?= dol_htmlentities($estados[$row->estado] ?? $row->estado) ?>
    </span>
    <?php if ($row->estado === 'CERRO' && $row->motivo_cierre): ?>
    <br><small style="color:<?= $color ?>;font-size:.75em"><?= dol_htmlentities($motivos[$row->motivo_cierre] ?? $row->motivo_cierre) ?></small>
    <?php endif; ?>
    <?php else: ?><span class="opacitymedium">—</span><?php endif; ?>
  </td>
  <td>
    <?php if ($row->fk_societe_activo): ?>
    <a href="/societe/card.php?socid=<?= $row->fk_societe_activo ?>" style="font-size:.88em">
      <?= dol_htmlentities(dol_trunc($actLabel, 35)) ?>
    </a>
    <?php else: ?><span class="opacitymedium" style="font-size:.85em">Sin propiedad</span><?php endif; ?>
  </td>
  <td class="nowrap">
    <small><?= $row->ultima_consulta ? date('d/m/Y', strtotime($row->ultima_consulta)) : '—' ?></small>
  </td>
  <td class="nowrap">
    <?php if ($recVenc): ?>
      <span style="color:#c0392b;font-weight:600;font-size:.85em">
        <span class="fas fa-bell"></span> <?= date('d/m', strtotime($row->prox_recordatorio)) ?> <small>vencido</small>
      </span>
    <?php elseif ($recPend): ?>
      <span style="color:#0d6efd;font-size:.85em">
        <span class="fas fa-bell"></span> <?= date('d/m', strtotime($row->prox_recordatorio)) ?>
      </span>
    <?php else: ?>
      <span class="opacitymedium" style="font-size:.82em">—</span>
    <?php endif; ?>
  </td>
  <td class="center nowrap">
    <?php if (!empty($row->monto_inversion_usd)): ?>
      <small style="color:#198754;font-weight:600">USD <?= number_format((float)$row->monto_inversion_usd, 0, ',', '.') ?></small>
    <?php else: ?>
      <span class="opacitymedium">—</span>
    <?php endif; ?>
  </td>
  <td class="center">
    <a href="#" class="comp-ver-consultas"
       data-socid="<?= $row->rowid ?>"
       data-nom="<?= dol_escape_htmltag($row->nom) ?>"
       data-monto="<?= (float)($row->monto_inversion_usd ?? 0) ?>"
       data-monto-min="<?= (float)($row->monto_min_usd ?? 0) ?>"
       data-monto-max="<?= (float)($row->monto_max_usd ?? 0) ?>"
       data-busqueda="<?= dol_escape_htmltag($row->busqueda ?? '') ?>"
       style="font-weight:600;color:#0d6efd;text-decoration:none">
      <?= (int)$row->total_consultas ?>
    </a>
    <?php if ($row->consultas_abiertas > 0): ?>
      <br><small style="color:#0d6efd"><?= (int)$row->consultas_abiertas ?> abiert<?= $row->consultas_abiertas==1?'a':'as' ?></small>
    <?php endif; ?>
  </td>
  <td class="nowrap" style="white-space:nowrap">
    <!-- Seguimiento rápido inline -->
    <a href="#" class="comp-seg-btn butAction"
       style="padding:2px 8px;font-size:.82em;margin-right:4px"
       data-socid="<?= $row->rowid ?>"
       data-nom="<?= dol_escape_htmltag($row->nom) ?>"
       data-consulta-id="<?= (int)$row->consulta_id ?>"
       data-estado="<?= dol_escape_htmltag($row->estado) ?>"
       data-rec-fecha="<?= dol_escape_htmltag($row->prox_recordatorio ?? '') ?>">
      + Seguimiento
    </a>
    <!-- Timeline -->
    <a href="/custom/realestatecrmfields/actor_timeline.php?socid=<?= $row->rowid ?>"
       title="Ver timeline completo" style="color:#6c6aa8;font-size:.9em">
      <span class="fas fa-history"></span>
    </a>
  </td>
</tr>
<?php endforeach; endif; ?>
</table>
</form>
</div>

<!-- Paginación -->
<?php if ($total > $limit): ?>
<div style="margin-top:12px;text-align:center">
  <?php $pages = ceil($total/$limit); for ($i=0;$i<$pages;$i++): ?>
  <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"
     style="margin:0 4px<?= $i===$page?';font-weight:bold;text-decoration:underline':'' ?>"><?= $i+1 ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<div class="opacitymedium" style="margin-top:8px;font-size:.9em">
  <?= $total ?> interesado<?= $total!=1?'s':'' ?> · mostrando <?= $offset+1 ?>–<?= min($offset+$limit,$total) ?>
</div>

<!-- Modal de seguimiento rápido -->
<div id="comp-seg-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;overflow-y:auto">
  <div style="background:#fff;max-width:520px;margin:60px auto;border-radius:6px;padding:26px 30px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <button type="button" id="comp-seg-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.3em;cursor:pointer">✕</button>
    <h3 style="margin:0 0 16px">
      <span class="fas fa-plus-circle" style="color:#6c6aa8;margin-right:6px"></span>
      Seguimiento — <span id="comp-seg-nom" style="color:#6c6aa8"></span>
    </h3>
    <input type="hidden" id="comp-seg-consulta-id">
    <input type="hidden" id="comp-seg-actor-id">

    <div style="margin-bottom:12px">
      <label style="font-weight:600;display:block;margin-bottom:4px">Nota</label>
      <textarea id="comp-seg-nota" class="flat" rows="3" style="width:100%;box-sizing:border-box" placeholder="Qué pasó, qué dijo…"></textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
      <div>
        <label style="font-weight:600;display:block;margin-bottom:4px">Nuevo estado</label>
        <select id="comp-seg-estado" class="flat" style="width:100%">
          <option value="">Sin cambio</option>
          <?php foreach ($estados as $k=>$v): ?>
          <option value="<?= $k ?>"><?= dol_htmlentities($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label style="font-weight:600;display:block;margin-bottom:4px">Recordatorio en</label>
        <div style="display:flex;align-items:center;gap:6px">
          <input type="number" id="comp-seg-rec-dias" class="flat" style="width:60px" placeholder="días" min="1">
          <span id="comp-seg-rec-preview" style="font-size:.82em;color:#6c6aa8"></span>
        </div>
      </div>
    </div>
    <!-- Cierre -->
    <div id="comp-seg-cierre-wrap" style="display:none;padding:10px;background:#f0fff4;border-radius:4px;border-left:3px solid #198754;margin-bottom:12px">
      <label style="font-weight:600;display:block;margin-bottom:6px;color:#198754">Motivo de cierre</label>
      <select id="comp-seg-motivo" class="flat" style="width:100%">
        <option value="">— Seleccionar —</option>
        <?php foreach ($motivos as $k=>$v): ?>
        <option value="<?= $k ?>"><?= dol_htmlentities($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="text-align:right">
      <button type="button" id="comp-seg-cancel" class="butActionDelete" style="margin-right:8px">Cancelar</button>
      <button type="button" id="comp-seg-submit" class="butAction">Guardar</button>
    </div>
    <div id="comp-seg-error" style="color:#c0392b;margin-top:8px;font-size:.9em;display:none"></div>
  </div>
</div>

<!-- Modal ver consultas del actor -->
<div id="comp-consultas-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;overflow-y:auto">
  <div style="background:#fff;max-width:720px;margin:40px auto;border-radius:6px;padding:24px 28px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <button type="button" id="comp-consultas-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.3em;cursor:pointer">✕</button>
    <h3 style="margin:0 0 16px">
      <span class="fas fa-history" style="color:#6c6aa8;margin-right:6px"></span>
      Consultas — <span id="comp-consultas-nom" style="color:#6c6aa8"></span>
    </h3>
    <div id="comp-consultas-body">
      <div class="opacitymedium" style="padding:12px 0">Cargando…</div>
    </div>
    <div id="comp-matching-wrap" style="display:none;margin-top:14px;border-top:1px solid #eee;padding-top:12px">
      <div style="font-weight:600;font-size:.9em;color:#198754;margin-bottom:8px">
        <span class="fas fa-search" style="margin-right:5px"></span>Activos compatibles
        <small id="comp-matching-criterios" style="color:#888;font-weight:normal;margin-left:6px"></small>
      </div>
      <div id="comp-matching-body"><div class="opacitymedium" style="font-size:.85em">Buscando…</div></div>
    </div>
    <div style="margin-top:14px;text-align:right">
      <a id="comp-consultas-timeline" href="#" style="font-size:.88em;color:#6c6aa8">
        <span class="fas fa-external-link-alt" style="margin-right:4px"></span>Ver timeline completo
      </a>
    </div>
  </div>
</div>

<script>
$(function() {
    var TOKEN = '<?= dol_escape_js($token) ?>'
             || $('meta[name="anti-csrf-newtoken"]').attr('content')
             || $('input[name="token"]').first().val() || '';
    var AJAX_URL = '/custom/realestatecrmfields/ajax/consulta_save.php';
    var $modal = $('#comp-seg-modal');

    // Abrir modal
    $(document).on('click', '.comp-seg-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        $('#comp-seg-nom').text($btn.data('nom'));
        $('#comp-seg-consulta-id').val($btn.data('consulta-id'));
        $('#comp-seg-actor-id').val($btn.data('socid'));
        $('#comp-seg-nota').val('');
        $('#comp-seg-estado').val('');
        // Pre-cargar recordatorio si existe
        var recFecha = $btn.data('rec-fecha') || '';
        if (recFecha) {
            var parts = recFecha.split('-');
            $('#comp-seg-rec-dias').val('');
            $('#comp-seg-rec-preview').text('→ Actual: ' + parts[2] + '/' + parts[1] + '/' + parts[0]);
        } else {
            $('#comp-seg-rec-dias').val('');
            $('#comp-seg-rec-preview').text('');
        }
        $('#comp-seg-cierre-wrap').hide();
        $('#comp-seg-motivo').val('');
        $('#comp-seg-error').hide();
        // Pre-seleccionar estado actual
        $('#comp-seg-estado').val($btn.data('estado') || '');
        $modal.css('display','block');
        setTimeout(function(){ $('#comp-seg-nota').focus(); }, 150);
    });

    $('#comp-seg-close, #comp-seg-cancel').on('click', function() {
        $modal.css('display','none');
    });
    $modal.on('click', function(e) {
        if ($(e.target).is($modal)) $modal.css('display','none');
    });

    // Calcular fecha recordatorio
    $('#comp-seg-rec-dias').on('input', function() {
        var d = parseInt($(this).val());
        if (isNaN(d) || d < 1) { $('#comp-seg-rec-preview').text(''); return; }
        var dt = new Date(); dt.setDate(dt.getDate() + d);
        var dd = String(dt.getDate()).padStart(2,'0');
        var mm = String(dt.getMonth()+1).padStart(2,'0');
        $('#comp-seg-rec-preview').text('→ ' + dd + '/' + mm);
    });

    // Mostrar/ocultar cierre
    $('#comp-seg-estado').on('change', function() {
        $(this).val() === 'CERRO' ? $('#comp-seg-cierre-wrap').slideDown(150) : $('#comp-seg-cierre-wrap').slideUp(150);
    });

    // Submit
    $('#comp-seg-submit').on('click', function() {
        var consultaId = $('#comp-seg-consulta-id').val();
        var nota       = $('#comp-seg-nota').val().trim();
        var estado     = $('#comp-seg-estado').val();
        var dias       = parseInt($('#comp-seg-rec-dias').val());
        var motivo     = estado === 'CERRO' ? $('#comp-seg-motivo').val() : '';

        if (!nota && !estado) {
            $('#comp-seg-error').text('Ingresá nota o cambiá el estado.').show(); return;
        }
        if (!consultaId) {
            $('#comp-seg-error').text('No se encontró la consulta asociada.').show(); return;
        }

        var recFecha = '';
        if (!isNaN(dias) && dias > 0) {
            var dt = new Date(); dt.setDate(dt.getDate() + dias);
            recFecha = dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2,'0') + '-' + String(dt.getDate()).padStart(2,'0');
        }

        var $btn = $(this).prop('disabled', true).text('Guardando…');
        $.post(AJAX_URL, {
            action:          'log_add',
            token:           TOKEN,
            fk_consulta:     consultaId,
            nota:            nota,
            estado_nuevo:    estado,
            motivo_cierre:   motivo,
            fecha_cierre:    estado === 'CERRO' ? new Date().toISOString().slice(0,10) : '',
            fecha_recordatorio: recFecha,
        }, function(r) {
            if (r && r.success) {
                $modal.css('display','none');
                showToast('Guardado ✓', 'success');
                setTimeout(function() { location.reload(); }, 900);
            } else {
                $('#comp-seg-error').text('Error: ' + (r && r.error ? r.error : 'desconocido')).show();
            }
        }, 'json').always(function() {
            $btn.prop('disabled', false).text('Guardar');
        });
    });

    // Submit form al presionar Enter
    $('#compForm input[type=text]').on('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); $(this).closest('form').submit(); }
    });

    // Modal ver consultas
    var $cmModal = $('#comp-consultas-modal');

    $(document).on('click', '.comp-ver-consultas', function(e) {
        e.preventDefault();
        var socid = $(this).data('socid');
        var nom   = $(this).data('nom');
        $('#comp-consultas-nom').text(nom);
        $('#comp-consultas-timeline').attr('href', '/custom/realestatecrmfields/actor_timeline.php?socid=' + socid);
        $('#comp-consultas-body').html('<div class="opacitymedium" style="padding:12px 0">Cargando…</div>');
        $cmModal.css('display','block');

        // Matching de activos compatibles
        var monto    = $(this).data('monto')     || 0;
        var montoMin = $(this).data('monto-min') || 0;
        var montoMax = $(this).data('monto-max') || 0;
        var busqueda = $(this).data('busqueda') || '';
        $('#comp-matching-wrap').hide();
        $('#comp-matching-body').html('<div class="opacitymedium" style="font-size:.85em">Buscando…</div>');

        if (monto > 0 || montoMin > 0 || montoMax > 0 || busqueda) {
            var criterioTexto = '';
            if (montoMin > 0 && montoMax > 0) {
                criterioTexto = 'rango USD ' + montoMin.toLocaleString('es-AR') + ' – ' + montoMax.toLocaleString('es-AR');
            } else if (montoMax > 0) {
                criterioTexto = 'hasta USD ' + montoMax.toLocaleString('es-AR');
            } else if (montoMin > 0) {
                criterioTexto = 'desde USD ' + montoMin.toLocaleString('es-AR');
            } else if (monto > 0) {
                criterioTexto = 'presupuesto USD ' + monto.toLocaleString('es-AR');
            }
            if (busqueda) criterioTexto += (criterioTexto ? ' · ' : '') + busqueda;
            $('#comp-matching-criterios').text(criterioTexto);
            $.getJSON('/custom/realestatecrmfields/ajax/matching_activos.php',
                { monto: monto, monto_min: montoMin, monto_max: montoMax, busqueda: busqueda },
                function(activos) {
                    if (!activos || !activos.length) {
                        $('#comp-matching-body').html('<div class="opacitymedium" style="font-size:.85em">No se encontraron activos con precio en ese rango. Revisá si hay activos compatibles sin precio cargado.</div>');
                    } else {
                        var html = '<div style="display:flex;flex-wrap:wrap;gap:8px">';
                        activos.forEach(function(a) {
                            var prio = a.prioridad || '';
                            var prioColor = prio === 'alta' ? '#c0392b' : prio === 'media' ? '#fd7e14' : '#6c757d';
                            html += '<div style="border:1px solid #e0e0e0;border-radius:6px;padding:8px 12px;font-size:.82em;min-width:180px;max-width:220px">'
                                  + '<a href="/societe/card.php?socid=' + a.rowid + '" target="_blank" style="font-weight:600;color:#0d6efd">' + a.nom + '</a>'
                                  + '<br><span style="color:#888">' + (a.barrio || '—') + '</span>'
                                  + (a.cocheras ? ' · <strong>' + a.cocheras + '</strong> coch.' : '')
                                  + (a.precio ? '<br><span style="color:#6f42c1">USD ' + parseInt(a.precio).toLocaleString('es-AR') + '</span>' : (a.sin_precio ? '<br><span style="color:#aaa;font-size:.8em">Sin precio cargado</span>' : ''))
                                  + (a.usd_x_coch ? ' <small style="color:#aaa">(' + parseInt(a.usd_x_coch).toLocaleString('es-AR') + '/coch)</small>' : '')
                                  + (prio ? '<br><span style="background:' + prioColor + ';color:#fff;border-radius:4px;padding:0 5px;font-size:.8em">' + prio + '</span>' : '')
                                  + (a.tipoprop ? '<span style="background:#e2d9f3;color:#6f42c1;border-radius:4px;padding:0 4px;font-size:.8em;margin-left:3px">' + a.tipoprop + '</span>' : '')
                                  + '</div>';
                        });
                        html += '</div>';
                        $('#comp-matching-body').html(html);
                    }
                    $('#comp-matching-wrap').show();
                }
            );
        }

        $.getJSON('/custom/realestatecrmfields/ajax/consulta_save.php',
            { action: 'list_by_actor', socid: socid },
            function(rows) {
                if (!rows || !rows.length) {
                    $('#comp-consultas-body').html('<div class="opacitymedium" style="padding:12px 0">Sin consultas registradas.</div>');
                    return;
                }
                var estColors = {
                    'CONSULTO':'#6c757d','VISITO':'#0d6efd',
                    'OFRECIO':'#fd7e14','CERRO':'#198754'
                };
                var estLabels = {
                    'CONSULTO':'Consultó','VISITO':'Visitó',
                    'OFRECIO':'Ofreció','CERRO':'Cerró'
                };
                var motivos = {
                    'COMPRA':'✅ Compró','NO_INTERES':'❌ Sin interés',
                    'PRECIO':'💲 Precio','OTRO_INMUEBLE':'🏠 Otro inmueble',
                    'SIN_RESOLUCION':'⏸ Sin resolución'
                };
                function esc(s) { return $('<span>').text(s || '').html(); }

                var html = '<table class="noborder centpercent" style="font-size:.9em">';
                html += '<tr class="liste_titre"><td>Fecha</td><td>Propiedad</td><td>Estado</td><td>Nota</td></tr>';
                rows.forEach(function(c) {
                    var ts    = parseInt(c.date_consulta);
                    var fecha = ts ? new Date(ts * 1000).toLocaleDateString('es-AR') : '—';
                    var color = estColors[c.estado] || '#6c757d';
                    var label = estLabels[c.estado] || esc(c.estado);

                    // Propiedad
                    var activoTxt = c.activo_calle
                        ? (c.activo_calle + (c.activo_numero ? ' ' + c.activo_numero : ''))
                        : (c.activo_nom || '');
                    var activoHtml = activoTxt
                        ? esc(activoTxt)
                        : '<span class="opacitymedium">Sin propiedad</span>';
                    if (c.fk_societe_activo) {
                        activoHtml = '<a href="/societe/card.php?socid='+c.fk_societe_activo+'" target="_blank">'
                            + activoHtml + '</a>';
                    }

                    // Motivo de cierre
                    var motivo = (c.estado === 'CERRO' && c.motivo_cierre && motivos[c.motivo_cierre])
                        ? '<br><small style="color:'+color+';font-size:.85em">'+motivos[c.motivo_cierre]+'</small>' : '';

                    // Nota
                    var nota = c.nota ? c.nota.substring(0, 70) + (c.nota.length > 70 ? '…' : '') : '';

                    html += '<tr class="oddeven">'
                        + '<td class="nowrap"><small>' + fecha + '</small></td>'
                        + '<td>' + activoHtml + '</td>'
                        + '<td style="white-space:nowrap"><span style="background:'+color+';color:#fff;padding:1px 8px;border-radius:10px;font-size:.82em">'+label+'</span>'+motivo+'</td>'
                        + '<td class="tdoverflowmax200"><small>' + esc(nota) + '</small></td>'
                        + '</tr>';
                });
                html += '</table>';
                $('#comp-consultas-body').html(html);
            }
        ).fail(function() {
            $('#comp-consultas-body').html('<div style="color:#c0392b;padding:12px 0">Error al cargar las consultas.</div>');
        });
    });

    $('#comp-consultas-close').on('click', function() { $cmModal.css('display','none'); });
    $cmModal.on('click', function(e) { if ($(e.target).is($cmModal)) $cmModal.css('display','none'); });

    // Expandir/colapsar columna Qué busca
    $(document).on('click', '.comp-busqueda-celda', function() {
        var $corta    = $(this).find('.comp-busq-corta');
        var $completa = $(this).find('.comp-busq-completa');
        if (!$corta.length) return;
        var expandida = $completa.is(':visible');
        $corta.toggle(expandida);
        $completa.toggle(!expandida);
        $(this).css('max-width', expandida ? '200px' : 'none');
        $(this).attr('title', expandida ? 'Clic para expandir' : 'Clic para colapsar');
    });
});

function showToast(msg, type) {
    var bg = type === 'success' ? '#198754' : '#c0392b';
    var $t = $('<div>').text(msg).css({
        position:'fixed', bottom:'28px', right:'28px', zIndex:99999,
        background:bg, color:'#fff', padding:'10px 22px',
        borderRadius:'6px', fontSize:'.95em', boxShadow:'0 4px 16px rgba(0,0,0,.2)',
        opacity:0, transition:'opacity .2s'
    }).appendTo('body');
    setTimeout(function(){ $t.css('opacity',1); }, 10);
    setTimeout(function(){ $t.css('opacity',0); setTimeout(function(){ $t.remove(); },300); }, 2200);
}
</script>

<?php
llxFooter();
$db->close();
