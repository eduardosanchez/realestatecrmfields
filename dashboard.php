<?php
/**
 * Dashboard ejecutivo — CRM Inmobiliario
 * URL: /custom/realestatecrmfields/dashboard.php
 */
$res = @include '../../main.inc.php';
if (!$res) $res = @include '../../../main.inc.php';
if (!$res) { echo 'ERROR: no se pudo cargar main.inc.php'; exit; }

if (!isset($user) || empty($user->login)) {
    accessforbidden();
    exit;
}

llxHeader('', 'Dashboard CRM', '');

$p   = MAIN_DB_PREFIX;
$hoy = date('Y-m-d');
$ini7  = date('Y-m-d', strtotime('-7 days'));
$ini30 = date('Y-m-d', strtotime('-30 days'));
$iniMes = date('Y-m-01');

// ── Consultar métricas ────────────────────────────────────────

// Leads últimos 7 días por origen
$rLeads7 = $db->query("SELECT ef.origen_lead, COUNT(*) AS total
    FROM {$p}re_consulta c
    LEFT JOIN {$p}societe_extrafields ef ON ef.fk_object = c.fk_societe_actor
    WHERE DATE(c.date_creation) >= '$ini7'
    GROUP BY ef.origen_lead ORDER BY total DESC");

$leads7     = 0;
$porOrigen  = [];
while ($rLeads7 && ($o = $db->fetch_object($rLeads7))) {
    $leads7 += $o->total;
    $porOrigen[strtolower($o->origen_lead ?: 'web')] = (int)$o->total;
}

// Consultas por estado (abiertas)
$rEstados = $db->query("SELECT estado, COUNT(*) AS total FROM {$p}re_consulta
    WHERE estado NOT IN ('COMPRA','NO_INTERES','PRECIO','OTRO_INMUEBLE','SIN_RESOLUCION')
    GROUP BY estado ORDER BY total DESC");
$porEstado = [];
while ($rEstados && ($o = $db->fetch_object($rEstados))) $porEstado[$o->estado] = (int)$o->total;

// Consultas cerradas este mes
$rCierres = $db->query("SELECT estado, COUNT(*) AS total FROM {$p}re_consulta
    WHERE estado IN ('COMPRA','NO_INTERES','PRECIO','OTRO_INMUEBLE','SIN_RESOLUCION')
    AND DATE(date_creation) >= '$iniMes'
    GROUP BY estado");
$cierresMes = [];
$totalCierres = 0;
while ($rCierres && ($o = $db->fetch_object($rCierres))) {
    $cierresMes[$o->estado] = (int)$o->total;
    $totalCierres += (int)$o->total;
}

// Pendientes por prioridad
$rPend = $db->query("SELECT
    ef_act.prioridad_de_contacto,
    DATEDIFF('$hoy', DATE(c.fecha_recordatorio)) AS dias_vencido
    FROM {$p}re_consulta c
    LEFT JOIN {$p}societe_extrafields ef_act   ON ef_act.fk_object   = c.fk_societe_activo
    WHERE c.recordatorio_done = 0
      AND c.fecha_recordatorio IS NOT NULL
      AND c.fecha_recordatorio <= '$hoy'");

$pendCritico = $pendAlta = $pendMedia = $pendBaja = 0;
while ($rPend && ($o = $db->fetch_object($rPend))) {
    $prio  = strtolower($o->prioridad_de_contacto ?? '');
    $dias  = (int)($o->dias_vencido ?? 0);
    if ($prio === 'alta' && $dias >= 7) $pendCritico++;
    elseif ($prio === 'alta') $pendAlta++;
    elseif ($prio === 'media' && $dias >= 7) $pendMedia++;
    else $pendBaja++;
}

// Top 5 activos más consultados
$rTop = $db->query("SELECT s.nom, s.rowid, COUNT(*) AS total
    FROM {$p}re_consulta c
    JOIN {$p}societe s ON s.rowid = c.fk_societe_activo
    WHERE c.fk_societe_activo IS NOT NULL
    GROUP BY c.fk_societe_activo
    ORDER BY total DESC LIMIT 5");
$topActivos = [];
while ($rTop && ($o = $db->fetch_object($rTop))) $topActivos[] = $o;

// Leads últimos 30 días (para mini gráfico)
$rLeads30 = $db->query("SELECT DATE(date_creation) AS dia, COUNT(*) AS total
    FROM {$p}re_consulta
    WHERE date_creation >= '$ini30'
    GROUP BY DATE(date_creation) ORDER BY dia ASC");
$leadsChart = [];
while ($rLeads30 && ($o = $db->fetch_object($rLeads30))) $leadsChart[$o->dia] = (int)$o->total;

$origenLabels = ['instagram'=>'📸 Instagram','whatsapp'=>'💬 WhatsApp',
                 'ads'=>'🎯 Ads','web'=>'🌐 Web','test'=>'🧪 Test'];
$estadoLabels = ['CONSULTO'=>'Consultó','EN_SEGUIMIENTO'=>'Seguimiento',
                 'REUNION'=>'Reunión','OFERTA'=>'Oferta'];
$cierreLabels = ['COMPRA'=>'Compra','NO_INTERES'=>'Sin interés',
                 'PRECIO'=>'Precio','OTRO_INMUEBLE'=>'Otro inmueble','SIN_RESOLUCION'=>'Sin resolución'];
?>

<div class="fiche" style="max-width:1200px">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:8px">
    <h1 style="margin:0;font-size:1.4em;color:#143B67">
        <span class="fas fa-chart-bar" style="margin-right:8px"></span>Dashboard CRM
    </h1>
    <div style="display:flex;gap:8px">
        <a href="/custom/realestatecrmfields/compradores.php" class="butAction">👥 Compradores</a>
        <a href="/custom/realestatecrmfields/captacion.php"   class="butAction">🏢 Captación</a>
        <a href="/custom/realestatecrmfields/pendientes.php"  class="butAction">🔔 Pendientes</a>
    </div>
</div>

<!-- FILA 1: KPIs ─────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:20px">

<?php
$kpis = [
    ['Leads últimos 7 días', $leads7, '#143B67', 'fa-user-plus'],
    ['Pendientes críticos',  $pendCritico + $pendAlta, '#dc3545', 'fa-exclamation-circle'],
    ['En seguimiento', array_sum($porEstado), '#0d6efd', 'fa-sync'],
    ['Cerradas este mes', $totalCierres, '#198754', 'fa-check-circle'],
];
foreach ($kpis as $k): ?>
<div style="background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.07);text-align:center;border-top:4px solid <?= $k[2] ?>">
    <div style="font-size:2em;color:<?= $k[2] ?>;margin-bottom:4px">
        <span class="fas <?= $k[3] ?>"></span>
    </div>
    <div style="font-size:2em;font-weight:700;color:<?= $k[2] ?>"><?= $k[1] ?></div>
    <div style="font-size:.82em;color:#888;margin-top:4px"><?= $k[0] ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- FILA 2: Leads por origen + Pendientes ─────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">

<div style="background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.07)">
    <h3 style="margin:0 0 16px;color:#143B67;font-size:1em">📊 Leads últimos 7 días por origen</h3>
    <?php if (empty($porOrigen)): ?>
        <p class="opacitymedium">Sin leads en los últimos 7 días.</p>
    <?php else:
        $maxOrigen = max($porOrigen);
        foreach ($porOrigen as $orig => $cnt):
            $pct = $maxOrigen ? round($cnt / $maxOrigen * 100) : 0;
            $label = $origenLabels[$orig] ?? ucfirst($orig);
    ?>
    <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:.88em;margin-bottom:4px">
            <span><?= dol_htmlentities($label) ?></span>
            <strong><?= $cnt ?></strong>
        </div>
        <div style="background:#f0f0f0;border-radius:4px;height:8px">
            <div style="background:#143B67;width:<?= $pct ?>%;height:8px;border-radius:4px;transition:width .3s"></div>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<div style="background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.07)">
    <h3 style="margin:0 0 16px;color:#143B67;font-size:1em">🔔 Pendientes por prioridad</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <?php
        $pItems = [
            ['🔴 CRÍTICO',  $pendCritico, '#dc3545'],
            ['🟠 ALTA',     $pendAlta,    '#fd7e14'],
            ['🟡 MEDIA',    $pendMedia,   '#ffc107'],
            ['⚪ BAJA',     $pendBaja,    '#adb5bd'],
        ];
        foreach ($pItems as $pi): ?>
        <div style="background:#f8f9fa;border-radius:8px;padding:12px;text-align:center;border-left:4px solid <?= $pi[2] ?>">
            <div style="font-size:1.6em;font-weight:700;color:<?= $pi[2] ?>"><?= $pi[1] ?></div>
            <div style="font-size:.8em;color:#555;margin-top:2px"><?= $pi[0] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="margin-top:12px;text-align:right">
        <a href="/custom/realestatecrmfields/pendientes.php" style="font-size:.85em;color:#143B67">Ver pendientes →</a>
    </div>
</div>
</div>

<!-- FILA 3: Estados + Cierres + Top activos ───────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px">

<div style="background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.07)">
    <h3 style="margin:0 0 16px;color:#143B67;font-size:1em">📋 Consultas abiertas por estado</h3>
    <?php if (empty($porEstado)): ?>
        <p class="opacitymedium">Sin consultas abiertas.</p>
    <?php else: foreach ($porEstado as $est => $cnt):
        $label = $estadoLabels[$est] ?? $est;
    ?>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f0f0f0;font-size:.9em">
        <span><?= dol_htmlentities($label) ?></span>
        <strong><?= $cnt ?></strong>
    </div>
    <?php endforeach; endif; ?>
</div>

<div style="background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.07)">
    <h3 style="margin:0 0 16px;color:#143B67;font-size:1em">✅ Cierres este mes (<?= $totalCierres ?>)</h3>
    <?php if (empty($cierresMes)): ?>
        <p class="opacitymedium">Sin cierres este mes.</p>
    <?php else: foreach ($cierresMes as $est => $cnt):
        $label = $cierreLabels[$est] ?? $est;
        $color = $est === 'COMPRA' ? '#198754' : '#6c757d';
    ?>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f0f0f0;font-size:.9em">
        <span style="color:<?= $color ?>"><?= dol_htmlentities($label) ?></span>
        <strong style="color:<?= $color ?>"><?= $cnt ?></strong>
    </div>
    <?php endforeach; endif; ?>
</div>

<div style="background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.07)">
    <h3 style="margin:0 0 16px;color:#143B67;font-size:1em">🏆 Top activos más consultados</h3>
    <?php if (empty($topActivos)): ?>
        <p class="opacitymedium">Sin datos.</p>
    <?php else:
    $maxTop = $topActivos[0]->total;
    foreach ($topActivos as $i => $act): ?>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
        <span style="font-size:.8em;color:#888;min-width:16px"><?= $i+1 ?>.</span>
        <div style="flex:1;min-width:0">
            <a href="<?= DOL_URL_ROOT ?>/societe/card.php?socid=<?= $act->rowid ?>"
               style="font-size:.85em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;color:#143B67">
               <?= dol_htmlentities($act->nom) ?>
            </a>
            <div style="background:#f0f0f0;border-radius:3px;height:5px;margin-top:3px">
                <div style="background:#143B67;width:<?= round($act->total/$maxTop*100) ?>%;height:5px;border-radius:3px"></div>
            </div>
        </div>
        <span style="font-size:.85em;font-weight:700;color:#143B67;min-width:24px;text-align:right"><?= $act->total ?></span>
    </div>
    <?php endforeach; endif; ?>
</div>
</div>

<!-- Mini gráfico de leads 30 días ────────────────────────── -->
<div style="background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:20px">
    <h3 style="margin:0 0 16px;color:#143B67;font-size:1em">📈 Leads diarios — últimos 30 días</h3>
    <?php
    $maxChart = $leadsChart ? max($leadsChart) : 1;
    $dias = new DatePeriod(new DateTime($ini30), new DateInterval('P1D'), new DateTime($hoy));
    ?>
    <div style="display:flex;align-items:flex-end;gap:3px;height:80px;padding-bottom:4px;overflow-x:auto">
    <?php foreach ($dias as $d):
        $key = $d->format('Y-m-d');
        $val = $leadsChart[$key] ?? 0;
        $h   = $maxChart ? round($val / $maxChart * 72) : 0;
        $color = $val > 0 ? '#143B67' : '#e9ecef';
        $title = $d->format('d/m') . ': ' . $val . ' lead' . ($val !== 1 ? 's' : '');
    ?>
    <div title="<?= $title ?>" style="flex:1;min-width:8px;background:<?= $color ?>;height:<?= max(4,$h) ?>px;border-radius:2px 2px 0 0;cursor:default"></div>
    <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.72em;color:#aaa;margin-top:4px">
        <span><?= date('d/m', strtotime($ini30)) ?></span>
        <span>hoy</span>
    </div>
</div>

</div>

<?php llxFooter(); ?>
