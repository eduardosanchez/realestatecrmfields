<?php
/**
 * Timeline completo de un interesado (Actor RE_AOR)
 * URL: /custom/realestatecrmfields/actor_timeline.php?socid=XXX
 */
$res = @include '../../main.inc.php';
if (!$res) $res = @include '../../../main.inc.php';
if (!$res) die('main.inc.php not found');

require_once dol_buildpath('/custom/realestatecrmfields/class/reconsulta.class.php', 0);
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

$socid = (int)GETPOST('socid', 'int');
if (!$socid) accessforbidden();

// Cargar el actor
$soc = new Societe($db);
$soc->fetch($socid);

// Cargar todas las consultas del actor con sus logs
$consultaObj = new ReConsulta($db);
$consultas   = $consultaObj->fetchByActor($socid);

$estados   = ReConsulta::ESTADOS;
$estColors = ReConsulta::ESTADO_COLORS;
$canales   = ReConsulta::CANALES;
$motivos   = ReConsulta::MOTIVOS_CIERRE;

// Cargar logs para cada consulta
$logsSQL = "SELECT l.*, CONCAT(u.firstname, ' ', u.lastname) AS user_nom
            FROM " . MAIN_DB_PREFIX . "re_consulta_log l
            LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = l.fk_user
            WHERE l.fk_consulta IN (" .
            (empty($consultas) ? '0' : implode(',', array_map(fn($c) => (int)$c->rowid, $consultas))) . ")
            ORDER BY l.date_log ASC";
$resLogs = $db->query($logsSQL);
$logsPorConsulta = [];
while ($resLogs && ($l = $db->fetch_object($resLogs))) {
    $logsPorConsulta[$l->fk_consulta][] = $l;
}

// Stats rápidas
$totalConsultas  = count($consultas);
$conVisita       = count(array_filter($consultas, fn($c) => in_array($c->estado, ['VISITO','OFRECIO','CERRO'])));
$conOferta       = count(array_filter($consultas, fn($c) => in_array($c->estado, ['OFRECIO','CERRO'])));
$cerradas        = array_filter($consultas, fn($c) => $c->estado === 'CERRO');
$cierrePositivo  = count(array_filter($cerradas,  fn($c) => $c->motivo_cierre === 'COMPRA'));

llxHeader('', 'Timeline: ' . $soc->nom);
?>

<div class="fiche">
  <!-- Botones volver -->
  <div style="margin-bottom:12px;display:flex;gap:8px">
    <a href="/societe/card.php?socid=<?= $socid ?>" class="butAction" style="text-decoration:none">
      <span class="fas fa-arrow-left" style="margin-right:6px"></span>Volver a la ficha
    </a>
    <a href="/custom/realestatecrmfields/compradores.php" class="butAction" style="text-decoration:none;background:#6c6aa8;border-color:#6c6aa8">
      <span class="fas fa-users" style="margin-right:6px"></span>Compradores
    </a>
  </div>

  <!-- Header del actor -->
  <div class="fichecenter" style="margin-bottom:0">
    <div style="display:flex;align-items:center;gap:16px;padding:16px 0 8px">
      <div>
        <h2 style="margin:0">
          <span class="fas fa-user-circle" style="color:#6c6aa8;margin-right:8px"></span>
          <a href="/societe/card.php?socid=<?= $socid ?>"><?= dol_htmlentities($soc->nom) ?></a>
        </h2>
        <?php if ($soc->phone): ?>
          <div style="color:#555;margin-top:4px"><span class="fas fa-phone" style="margin-right:4px"></span><?= dol_htmlentities($soc->phone) ?></div>
        <?php endif; ?>
      </div>
      <div style="margin-left:auto;display:flex;gap:12px;flex-wrap:wrap">
        <!-- Stats rápidas -->
        <?php foreach ([
            [$totalConsultas, 'Consultas', '#6c757d'],
            [$conVisita,      'Con visita', '#0d6efd'],
            [$conOferta,      'Con oferta', '#fd7e14'],
            [$cierrePositivo, 'Compras',    '#198754'],
        ] as [$val, $lbl, $col]): ?>
        <div style="text-align:center;background:#f8f9fa;border-radius:8px;padding:8px 16px;border-top:3px solid <?= $col ?>">
          <div style="font-size:1.6em;font-weight:700;color:<?= $col ?>"><?= $val ?></div>
          <div style="font-size:.78em;color:#666"><?= $lbl ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if (empty($consultas)): ?>
    <div class="opacitymedium" style="padding:24px 0">Sin consultas registradas para este interesado.</div>
  <?php else: ?>

  <!-- Timeline -->
  <div style="position:relative;padding:20px 0">

    <?php foreach ($consultas as $idx => $c):
      $logs = $logsPorConsulta[$c->rowid] ?? [];
      $isClosed = $c->estado === 'CERRO';
      $color = $estColors[$c->estado] ?? '#6c757d';
    ?>

    <!-- Consulta card -->
    <div style="display:flex;gap:0;margin-bottom:<?= $isClosed ? '32px' : '16px' ?>">

      <!-- Eje vertical -->
      <div style="display:flex;flex-direction:column;align-items:center;margin-right:16px;min-width:32px">
        <div style="width:14px;height:14px;border-radius:50%;background:<?= $color ?>;border:3px solid #fff;box-shadow:0 0 0 2px <?= $color ?>;flex-shrink:0;margin-top:6px"></div>
        <?php if ($idx < $totalConsultas - 1): ?>
        <div style="width:2px;background:#dee2e6;flex:1;min-height:20px;margin-top:4px"></div>
        <?php endif; ?>
      </div>

      <!-- Contenido -->
      <div style="flex:1;background:#fff;border:1px solid #e9ecef;border-radius:6px;padding:14px 16px;<?= $isClosed ? 'border-left:3px solid '.$color : '' ?>">

        <!-- Header de la consulta -->
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px">
          <div>
            <span style="font-size:.82em;color:#888"><?= $c->date_consulta ? date('d/m/Y H:i', strtotime($c->date_consulta)) : '' ?></span>
            <?php if ($c->fk_societe_activo): ?>
              · <a href="/societe/card.php?socid=<?= $c->fk_societe_activo ?>" style="font-weight:600">
                <?= dol_htmlentities(trim(($c->activo_calle ?: '') . ' ' . ($c->activo_numero ?: '')) ?: $c->activo_nom) ?>
              </a>
            <?php else: ?>
              · <span class="opacitymedium">Sin propiedad específica</span>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <span style="background:<?= $color ?>;color:#fff;padding:2px 10px;border-radius:12px;font-size:.82em">
              <?= dol_htmlentities($estados[$c->estado] ?? $c->estado) ?>
            </span>
            <?php if ($isClosed && !empty($c->motivo_cierre)): ?>
            <span style="background:#e8f5e9;color:#198754;padding:2px 10px;border-radius:12px;font-size:.78em;border:1px solid #c8e6c9">
              <?= dol_htmlentities($motivos[$c->motivo_cierre] ?? $c->motivo_cierre) ?>
            </span>
            <?php endif; ?>
            <?php if ($c->canal): ?>
            <span style="background:#f8f9fa;color:#555;padding:2px 8px;border-radius:12px;font-size:.78em;border:1px solid #dee2e6">
              <?= ['WHATSAPP'=>'🟢','INSTAGRAM'=>'📸','EMAIL'=>'📧','TELEFONO'=>'📞'][$c->canal] ?? '' ?>
              <?= dol_htmlentities($canales[$c->canal] ?? $c->canal) ?>
            </span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Nota / búsqueda -->
        <?php if ($c->nota || $c->busqueda): ?>
        <div style="font-size:.9em;color:#444;margin-bottom:10px">
          <?php if ($c->nota): ?><div><?= dol_htmlentities($c->nota) ?></div><?php endif; ?>
          <?php if ($c->busqueda): ?><div style="color:#888;margin-top:2px"><em>Busca: <?= dol_htmlentities($c->busqueda) ?></em></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Fechas de cierre -->
        <?php if ($isClosed && !empty($c->fecha_cierre)): ?>
        <div style="font-size:.82em;color:#198754;margin-bottom:10px">
          <span class="fas fa-calendar-check" style="margin-right:4px"></span>Cerrado el <?= date('d/m/Y', strtotime($c->fecha_cierre)) ?>
        </div>
        <?php endif; ?>

        <!-- Recordatorio vigente -->
        <?php if (!empty($c->fecha_recordatorio) && !$c->recordatorio_done): ?>
        <?php $recVenc = $c->fecha_recordatorio <= date('Y-m-d'); ?>
        <div style="font-size:.82em;color:<?= $recVenc ? '#c0392b' : '#0d6efd' ?>;margin-bottom:10px">
          <span class="fas fa-bell" style="margin-right:4px"></span>
          Recordatorio: <?= date('d/m/Y', strtotime($c->fecha_recordatorio)) ?>
          <?php if ($c->nota_recordatorio): ?> — <?= dol_htmlentities($c->nota_recordatorio) ?><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Log de seguimientos -->
        <?php if (!empty($logs)): ?>
        <div style="border-top:1px solid #f0f0f0;margin-top:8px;padding-top:8px">
          <?php foreach ($logs as $l): ?>
          <div style="display:flex;gap:10px;margin-bottom:8px;font-size:.85em">
            <div style="color:#adb5bd;white-space:nowrap;min-width:110px"><?= dol_htmlentities(substr($l->date_log, 0, 16)) ?></div>
            <div style="flex:1">
              <?php if ($l->estado_anterior && $l->estado_nuevo && $l->estado_anterior !== $l->estado_nuevo): ?>
                <span style="background:<?= $estColors[$l->estado_anterior] ?? '#6c757d' ?>;color:#fff;padding:1px 7px;border-radius:10px;font-size:.78em"><?= dol_htmlentities($estados[$l->estado_anterior] ?? $l->estado_anterior) ?></span>
                <span style="margin:0 4px;color:#adb5bd">→</span>
                <span style="background:<?= $estColors[$l->estado_nuevo] ?? '#6c757d' ?>;color:#fff;padding:1px 7px;border-radius:10px;font-size:.78em"><?= dol_htmlentities($estados[$l->estado_nuevo] ?? $l->estado_nuevo) ?></span>
                <?php if ($l->nota): ?> &nbsp;<span style="color:#555"><?= dol_htmlentities($l->nota) ?></span><?php endif; ?>
              <?php elseif ($l->nota): ?>
                <span style="color:#555"><?= dol_htmlentities($l->nota) ?></span>
              <?php endif; ?>
            </div>
            <div style="color:#adb5bd;white-space:nowrap"><?= dol_htmlentities(trim($l->user_nom)) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<?php llxFooter(); ?>
