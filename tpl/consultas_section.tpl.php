<?php
/**
 * Template: sección de consultas para incluir en card.php (activo o actor)
 * Variables esperadas:
 *   $mode       = 'activo' | 'actor'
 *   $socid      = id del tercero
 */
if (!defined('DOL_DOCUMENT_ROOT')) die('Acceso directo no permitido');

require_once dol_buildpath('/custom/realestatecrmfields/class/reconsulta.class.php', 0);

$consulta  = new ReConsulta($db);
$consultas = ($mode === 'activo')
    ? $consulta->fetchByActivo($socid)
    : $consulta->fetchByActor($socid);

$canales   = ReConsulta::CANALES;
$estados   = ReConsulta::ESTADOS;
$estColors = ReConsulta::ESTADO_COLORS;

$sqlUsers = "SELECT rowid, CONCAT(firstname, ' ', lastname) AS nom FROM " . MAIN_DB_PREFIX . "user WHERE statut = 1 ORDER BY lastname";
$resUsers = $db->query($sqlUsers);
$usuarios = [];
while ($resUsers && ($u = $db->fetch_object($resUsers))) $usuarios[$u->rowid] = trim($u->nom);
?>

<div id="re-consultas-section" class="re-consultas-wrap" data-mode="<?= $mode ?>" data-socid="<?= $socid ?>">

  <div class="div-table-responsive-no-min" style="margin-top:20px">
    <div class="liste_titre liste_titre_barre">
      <table class="noborder centpercent" style="margin:0">
        <tr class="liste_titre">
          <td>
            <strong><?= $mode === 'activo' ? 'Consultas recibidas' : 'Propiedades de interés' ?></strong>
            <?php if ($mode === 'actor'): ?>
            <a href="/custom/realestatecrmfields/actor_timeline.php?socid=<?= $socid ?>"
               style="margin-left:10px;font-size:.82em;color:#6c6aa8" title="Ver timeline completo">
              <span class="fas fa-history"></span> Timeline
            </a>
            <?php endif; ?>
          </td>
          <td class="right">
            <button type="button" class="butAction re-consulta-nueva-btn" style="margin:0"
                    data-socid-activo="<?= $mode === 'activo' ? $socid : '' ?>">
              + Registrar consulta
            </button>
          </td>
        </tr>
      </table>
    </div>

    <?php
    // Semáforo de oportunidad — solo en modo activo
    if ($mode === 'activo' && !empty($consultas)) {
        $cnt_ofrecio = count(array_filter($consultas, fn($c) => $c->estado === 'OFRECIO'));
        $cnt_visito  = count(array_filter($consultas, fn($c) => $c->estado === 'VISITO'));
        $cnt_activos = count(array_filter($consultas, fn($c) => !in_array($c->estado, ['CERRO'])));
        // Ver si hay propietario que quiere vender
        $sqlProp = "SELECT COUNT(*) AS cnt FROM " . MAIN_DB_PREFIX . "re_gestion_propietario
                    WHERE fk_societe_activo = " . (int)$socid . "
                    AND resultado IN ('QUIERE_VENDER','QUIERE_TASAR','TASADO')
                    AND recordatorio_done = 0";
        $resProp = $db->query($sqlProp);
        $propVende = ($resProp && ($oProp = $db->fetch_object($resProp))) ? (int)$oProp->cnt > 0 : false;

        if ($cnt_ofrecio > 0 || $cnt_visito > 0 || $propVende) {
    ?>
    <div style="display:flex;gap:10px;padding:8px 10px;background:#fff;border-bottom:1px solid #e9ecef;flex-wrap:wrap;align-items:center">
      <span style="font-size:.82em;color:#888;margin-right:4px"><span class="fas fa-traffic-light"></span> Oportunidad:</span>
      <?php if ($propVende): ?>
        <span style="background:#198754;color:#fff;padding:2px 10px;border-radius:12px;font-size:.82em">
          <span class="fas fa-check-circle"></span> Propietario dispuesto a vender
        </span>
      <?php endif; ?>
      <?php if ($cnt_ofrecio > 0): ?>
        <span style="background:#fd7e14;color:#fff;padding:2px 10px;border-radius:12px;font-size:.82em">
          <span class="fas fa-hand-holding-usd"></span> <?= $cnt_ofrecio ?> oferta<?= $cnt_ofrecio > 1 ? 's' : '' ?> activa<?= $cnt_ofrecio > 1 ? 's' : '' ?>
        </span>
      <?php endif; ?>
      <?php if ($cnt_visito > 0): ?>
        <span style="background:#0d6efd;color:#fff;padding:2px 10px;border-radius:12px;font-size:.82em">
          <span class="fas fa-eye"></span> <?= $cnt_visito ?> visitó
        </span>
      <?php endif; ?>
      <span class="opacitymedium" style="font-size:.78em"><?= $cnt_activos ?> interesado<?= $cnt_activos != 1 ? 's' : '' ?> activo<?= $cnt_activos != 1 ? 's' : '' ?></span>
    </div>
    <?php } } ?>

    <?php if (empty($consultas)): ?>
      <div class="opacitymedium" style="padding:12px 8px">Sin consultas registradas.</div>
    <?php else: ?>
    <table class="noborder centpercent" id="re-consultas-table">
      <tr class="liste_titre">
        <td>Fecha</td>
        <td><?= $mode === 'activo' ? 'Interesado' : 'Propiedad' ?></td>
        <td>Canal</td>
        <td>Estado</td>
        <td>Rango USD</td>
        <td>Nota / Búsqueda</td>
        <td>Recordatorio</td>
        <td class="center" style="width:60px"></td>
      </tr>
      <?php foreach ($consultas as $c): ?>
      <tr class="oddeven re-consulta-row" data-rowid="<?= $c->rowid ?>">
        <td class="nowrap"><?= $c->date_consulta ? date('d/m/Y', $c->date_consulta) : '—' ?></td>
        <?php if ($mode === 'activo'): ?>
          <td>
            <?php if ($c->fk_societe_actor): ?>
              <a href="/societe/card.php?socid=<?= $c->fk_societe_actor ?>"><?= dol_htmlentities($c->actor_nom) ?></a>
            <?php else: ?>
              <?= dol_htmlentities($c->actor_nombre ?: '—') ?>
              <?php if ($c->actor_telefono): ?><small class="opacitymedium"> · <?= dol_htmlentities($c->actor_telefono) ?></small><?php endif; ?>
            <?php endif; ?>
          </td>
        <?php else: ?>
          <td>
            <?php
              $actDir = trim(($c->activo_calle ?: '') . ($c->activo_numero ? ' ' . $c->activo_numero : ''));
              $actLabel = $actDir ?: $c->activo_nom;
            ?>
            <a href="/societe/card.php?socid=<?= $c->fk_societe_activo ?>"
               title="<?= dol_escape_htmltag($c->activo_nom) ?>">
              <?= dol_htmlentities($actLabel) ?>
            </a>
          </td>
        <?php endif; ?>
        <td><?= ['WHATSAPP'=>'🟢','INSTAGRAM'=>'📸','EMAIL'=>'📧','TELEFONO'=>'📞'][$c->canal] ?? '' ?> <?= dol_htmlentities($canales[$c->canal] ?? $c->canal) ?></td>
        <td>
          <span style="background:<?= $estColors[$c->estado] ?? '#6c757d' ?>;color:#fff;padding:2px 8px;border-radius:4px;font-size:.85em;white-space:nowrap">
            <?= dol_htmlentities($estados[$c->estado] ?? $c->estado) ?>
          </span>
          <?php if ($c->estado === 'CERRO' && !empty($c->motivo_cierre)): ?>
          <br><small style="color:<?= $estColors['CERRO'] ?? '#198754' ?>;font-size:.78em">
            <?= dol_htmlentities(ReConsulta::MOTIVOS_CIERRE[$c->motivo_cierre] ?? $c->motivo_cierre) ?>
          </small>
          <?php endif; ?>
        </td>
        <td class="nowrap">
          <?php if ($c->rango_usd_min || $c->rango_usd_max): ?>
            <small>USD <?= $c->rango_usd_min ? number_format($c->rango_usd_min,0,',','.') : '?' ?> – <?= $c->rango_usd_max ? number_format($c->rango_usd_max,0,',','.') : '?' ?></small>
          <?php else: ?><span class="opacitymedium">—</span><?php endif; ?>
        </td>
        <td class="tdoverflowmax200 re-nota-celda" 
            style="cursor:pointer"
            title="Clic para expandir"
            data-nota="<?= dol_escape_htmltag($c->nota . ($c->busqueda ? "\nBusca: ".$c->busqueda : '')) ?>">
          <span class="re-nota-corta">
            <?= dol_htmlentities(dol_trunc($c->nota, 55)) ?>
            <?php if ($c->busqueda): ?><br><small class="opacitymedium"><em><?= dol_htmlentities(dol_trunc($c->busqueda, 45)) ?></em></small><?php endif; ?>
          </span>
          <span class="re-nota-completa" style="display:none;white-space:pre-wrap">
            <?= dol_htmlentities($c->nota . ($c->busqueda ? "\nBusca: ".$c->busqueda : '')) ?>
          </span>
        </td>
        <td class="nowrap">
          <?php if (!empty($c->fecha_recordatorio) && !$c->recordatorio_done):
            $hoy2 = date('Y-m-d');
            $recVenc = $c->fecha_recordatorio <= $hoy2;
          ?>
            <span style="color:<?= $recVenc ? '#c0392b' : '#0d6efd' ?>;font-weight:<?= $recVenc ? '600' : 'normal' ?>">
              <span class="fas fa-bell" style="margin-right:3px"></span><?= date('d/m/Y', strtotime($c->fecha_recordatorio)) ?>
            </span>
            <?php if ($c->nota_recordatorio): ?>
              <br><small class="opacitymedium"><?= dol_htmlentities(dol_trunc($c->nota_recordatorio, 40)) ?></small>
            <?php endif; ?>
          <?php else: ?>
            <span class="opacitymedium">—</span>
          <?php endif; ?>
        </td>
        <td class="center nowrap">
          <?php
            $hoy = date('Y-m-d');
            $tieneRec = !empty($c->fecha_recordatorio) && !$c->recordatorio_done;
            $recVencido = $tieneRec && $c->fecha_recordatorio <= $hoy;
          ?>
          <?php if ($tieneRec): ?>
            <a href="/custom/realestatecrmfields/pendientes.php"
               title="Recordatorio: <?= dol_escape_htmltag(date('d/m/Y', strtotime($c->fecha_recordatorio))) ?><?= $c->nota_recordatorio ? ' — '.$c->nota_recordatorio : '' ?>"
               style="color:<?= $recVencido ? '#c0392b' : '#6c6aa8' ?>;margin-right:4px">
              <span class="fas fa-bell"></span>
            </a>
          <?php endif; ?>
          <a href="#" class="re-log-toggle-btn" data-rowid="<?= $c->rowid ?>" title="Ver historial"
             style="margin-right:4px;color:#6c6aa8">
            <span class="fas fa-comments"></span><?php if ($c->log_count > 0): ?>
            <span class="re-log-count" data-rowid="<?= $c->rowid ?>"
              style="background:#6c6aa8;color:#fff;border-radius:10px;padding:0 5px;font-size:.75em;margin-left:2px;vertical-align:middle"><?= (int)$c->log_count ?></span>
            <?php endif; ?>
          </a>
          <a href="#" class="re-log-add-btn" data-rowid="<?= $c->rowid ?>"
             data-estado="<?= dol_escape_htmltag($c->estado) ?>" title="+ Seguimiento"
             style="margin-right:4px;color:#27ae60"><span class="fas fa-plus-circle"></span></a>
          <a href="#" class="re-consulta-edit-btn" data-rowid="<?= $c->rowid ?>" title="Editar"><span class="fas fa-pen"></span></a>
          &nbsp;
          <a href="#" class="re-consulta-del-btn"  data-rowid="<?= $c->rowid ?>" title="Eliminar"><span class="fas fa-trash opacitymedium"></span></a>
        </td>
      </tr>
      <!-- Panel de historial expandible — inline debajo de cada consulta -->
      <tr id="re-log-panel-<?= $c->rowid ?>" class="re-log-panel" style="display:none;background:#f8f9fa">
        <td colspan="9" style="padding:0;border-top:none">
          <div style="padding:10px 16px;border-left:3px solid #6c6aa8">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
              <strong style="color:#6c6aa8;font-size:.9em">
                <span class="fas fa-history" style="margin-right:4px"></span>Historial de seguimiento
              </strong>
              <button type="button" class="re-log-add-btn butAction"
                      data-rowid="<?= $c->rowid ?>" data-estado="<?= dol_escape_htmltag($c->estado) ?>"
                      style="font-size:.8em;padding:2px 10px;margin:0">+ Seguimiento</button>
            </div>
            <div id="re-log-entries-<?= $c->rowid ?>" class="re-log-entries">
              <span class="opacitymedium" style="font-size:.85em">Cargando…</span>
            </div>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>

    <?php endif; ?>
  </div>
</div>

<!-- Modal seguimiento -->
<div id="re-log-modal" class="re-modal-hidden" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:10000;overflow-y:auto">
  <div style="background:#fff;max-width:520px;margin:60px auto 40px;border-radius:6px;padding:24px 28px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.25);z-index:10001">
    <button type="button" id="re-log-modal-close" style="position:absolute;top:10px;right:14px;background:none;border:none;font-size:1.3em;cursor:pointer;color:#555">✕</button>
    <h3 style="margin-top:0;margin-bottom:16px">
      <span class="fas fa-plus-circle" style="color:#27ae60;margin-right:6px"></span>Agregar seguimiento
    </h3>
    <input type="hidden" id="re-log-fk-consulta">
    <div style="margin-bottom:16px">
      <div style="margin-bottom:12px">
        <label style="display:block;font-weight:600;margin-bottom:4px;color:#555">Nota</label>
        <textarea id="re-log-nota" class="flat" rows="4" style="width:100%;box-sizing:border-box"
                  placeholder="¿Qué pasó? Anotá la conversación, acuerdo, o novedad…"></textarea>
      </div>
      <div>
        <label style="display:block;font-weight:600;margin-bottom:4px;color:#555">Cambiar estado</label>
        <select id="re-log-estado" class="flat" style="width:100%">
          <option value="">— Sin cambio —</option>
          <?php foreach (ReConsulta::ESTADOS as $k => $v): ?>
          <option value="<?= $k ?>"><?= dol_htmlentities($v) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="opacitymedium" id="re-log-estado-actual" style="display:block;margin-top:4px"></small>
      </div>
      <!-- Motivo de cierre — visible solo cuando estado = CERRO -->
      <div id="re-log-cierre-wrap" style="display:none;margin-top:10px;padding:10px;background:#f0fff4;border-radius:4px;border-left:3px solid #198754">
        <label style="display:block;font-weight:600;margin-bottom:4px;color:#198754">Motivo de cierre</label>
        <select id="re-log-motivo-cierre" class="flat" style="width:100%">
          <option value="">— Seleccionar —</option>
          <?php foreach (ReConsulta::MOTIVOS_CIERRE as $k => $v): ?>
          <option value="<?= $k ?>"><?= dol_htmlentities($v) ?></option>
          <?php endforeach; ?>
        </select>
        <div style="margin-top:8px;display:flex;align-items:center;gap:8px">
          <label style="font-size:.9em;color:#555">Fecha de cierre:</label>
          <input type="date" id="re-log-fecha-cierre" class="flat" value="<?= date('Y-m-d') ?>" style="width:160px">
        </div>
      </div>
    </div>
    <div style="text-align:right">
      <button type="button" id="re-log-modal-cancel" class="butActionDelete" style="margin-right:8px">Cancelar</button>
      <button type="button" id="re-log-submit" class="butAction">Guardar seguimiento</button>
    </div>
    <div id="re-log-error" style="color:#c0392b;margin-top:8px;font-size:.9em;display:none"></div>
  </div>
</div>

<!-- Modal -->
<div id="re-consulta-modal" class="re-modal-hidden" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;overflow-y:auto">
  <div style="background:#fff;max-width:660px;margin:40px auto;border-radius:6px;padding:28px 32px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <button type="button" id="re-modal-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.4em;cursor:pointer;color:#555">✕</button>
    <h3 id="re-modal-title" style="margin-top:0;margin-bottom:20px">Registrar consulta</h3>

    <form id="re-consulta-form" autocomplete="off">
      <input type="hidden" id="rc-rowid"      name="rowid">
      <input type="hidden" id="rc-activo-id"  name="fk_societe_activo" value="<?= $mode === 'activo' ? $socid : '' ?>">
      <input type="hidden" id="rc-actor-id"   name="fk_societe_actor">

      <table class="border centpercent" style="border-collapse:collapse">

        <?php if ($mode === 'actor'): ?>
        <tr>
          <td class="titlefield" style="width:34%;padding:6px 10px">Propiedad</td>
          <td style="padding:6px 10px">
            <input type="text" id="rc-activo-search" class="flat" placeholder="Buscar propiedad por nombre…" style="width:100%">
            <div id="rc-activo-results" style="display:none;border:1px solid #ccc;max-height:160px;overflow-y:auto;background:#fff;position:absolute;z-index:10001;width:300px"></div>
            <div id="rc-activo-found" style="display:none;margin-top:4px;padding:4px 8px;background:#f0f7ff;border-radius:4px">
              <span id="rc-activo-found-nom"></span>
              <a href="#" id="rc-activo-clear" style="margin-left:8px;color:#c0392b;font-size:.85em">✕</a>
            </div>
          </td>
        </tr>
        <?php endif; ?>

        <?php if ($mode === 'activo'): ?>
        <tr>
          <td class="titlefield" style="padding:6px 10px">Interesado</td>
          <td style="padding:6px 10px;position:relative">
            <div id="rc-actor-found" style="display:none;padding:4px 0">
              <strong id="rc-actor-found-nom"></strong>
              <a href="#" id="rc-actor-clear" style="margin-left:8px;color:#c0392b;font-size:.85em">✕ quitar</a>
            </div>
            <div id="rc-actor-search-wrap">
              <input type="text" id="rc-actor-search" class="flat" placeholder="Buscar Actor por nombre o tel…" style="width:100%">
              <div id="rc-actor-results" style="display:none;border:1px solid #ccc;max-height:160px;overflow-y:auto;background:#fff;position:absolute;z-index:10001;width:300px;left:10px"></div>
            </div>
            <div style="margin-top:8px">
              <label style="font-size:.9em;color:#555;cursor:pointer">
                <input type="checkbox" id="rc-actor-nuevo-chk"> Interesado no registrado
              </label>
              <div id="rc-actor-nuevo-fields" style="display:none;margin-top:8px;padding:10px;background:#fafafa;border-radius:4px;border:1px solid #e0e0e0">
                <input type="text" id="rc-actor-nombre"   class="flat" placeholder="Nombre completo" style="width:100%;margin-bottom:6px">
                <input type="text" id="rc-actor-telefono" class="flat" placeholder="Teléfono / WhatsApp" style="width:100%;margin-bottom:6px">
                <label style="font-size:.85em;cursor:pointer">
                  <input type="checkbox" id="rc-crear-actor-chk"> Crear también como Actor en Dolibarr
                </label>
              </div>
            </div>
          </td>
        </tr>
        <?php else: ?>
        <!-- Modo actor: el interesado es este actor, no se muestra el campo -->
        <input type="hidden" id="rc-actor-found" name="_actor_hidden">
        <div id="rc-actor-search-wrap" style="display:none"></div>
        <div id="rc-actor-nuevo-fields" style="display:none"></div>
        <?php endif; ?>

        <tr>
          <td style="padding:6px 10px">Fecha</td>
          <td style="padding:6px 10px"><input type="datetime-local" id="rc-fecha" name="date_consulta" class="flat" style="width:220px"></td>
        </tr>
        <tr>
          <td style="padding:6px 10px">Canal</td>
          <td style="padding:6px 10px">
            <select id="rc-canal" name="canal" class="flat">
              <?php foreach ($canales as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr>
          <td style="padding:6px 10px">Estado</td>
          <td style="padding:6px 10px">
            <select id="rc-estado" name="estado" class="flat">
              <?php foreach ($estados as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>
          </td>
        </tr>

        <tr>
          <td style="padding:6px 10px;vertical-align:top">Nota / Consulta</td>
          <td style="padding:6px 10px"><textarea id="rc-nota" rows="3" class="flat" style="width:100%" placeholder="¿Qué preguntó sobre esta propiedad?"></textarea></td>
        </tr>
        <tr>
          <td style="padding:6px 10px;vertical-align:top">Qué busca</td>
          <td style="padding:6px 10px"><textarea id="rc-busqueda" rows="2" class="flat" style="width:100%" placeholder="Descripción general de lo que está buscando…"></textarea></td>
        </tr>
        <tr>
          <td style="padding:6px 10px">Atendió</td>
          <td style="padding:6px 10px">
            <select id="rc-vendedor" name="fk_user_vendedor" class="flat">
              <option value="">— Sin asignar —</option>
              <?php foreach ($usuarios as $uid => $unom): ?>
              <option value="<?= $uid ?>"><?= dol_htmlentities($unom) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr>
          <td style="padding:6px 10px;border-top:2px solid #e8e8e8;padding-top:10px">
            <span class="fas fa-bell" style="color:#6c6aa8;margin-right:4px"></span>Recordatorio
          </td>
          <td style="padding:6px 10px;border-top:2px solid #e8e8e8;padding-top:10px">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <span>Recordar en</span>
              <input type="number" id="rc-rec-dias" min="1" max="365" class="flat" style="width:60px" placeholder="días">
              <span>días</span>
              <span class="opacitymedium" id="rc-rec-fecha-preview" style="font-size:.85em"></span>
            </div>
            <div style="margin-top:6px;display:flex;gap:6px">
              <input type="hidden" id="rc-rec-fecha" name="fecha_recordatorio">
              <input type="text" id="rc-rec-nota" name="nota_recordatorio" class="flat" style="flex:1" placeholder="Nota del recordatorio (ej: llamar para ver si sigue interesado)">
            </div>
            <div style="margin-top:6px">
              <select id="rc-rec-user" name="fk_user_recordatorio" class="flat">
                <option value="">— Asignar a —</option>
                <?php foreach ($usuarios as $uid => $unom): ?>
                <option value="<?= $uid ?>"><?= dol_htmlentities($unom) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </td>
        </tr>
      </table>

      <div style="margin-top:18px;text-align:right">
        <button type="button" id="re-modal-cancel" class="butActionDelete" style="margin-right:8px">Cancelar</button>
        <button type="button" id="re-consulta-submit" class="butAction">Guardar consulta</button>
      </div>
      <div id="rc-error" style="color:#c0392b;margin-top:10px;font-size:.9em;display:none"></div>
    </form>
  </div>
</div>

<script>
// Expandir/colapsar nota al hacer clic en la celda
jQuery(document).on('click', '.re-nota-celda', function() {
    var $corta    = $(this).find('.re-nota-corta');
    var $completa = $(this).find('.re-nota-completa');
    var expandida = $completa.is(':visible');
    $corta.toggle(expandida);
    $completa.toggle(!expandida);
    $(this).css('max-width', expandida ? '' : 'none');
    $(this).attr('title', expandida ? 'Clic para expandir' : 'Clic para colapsar');
});
</script>
