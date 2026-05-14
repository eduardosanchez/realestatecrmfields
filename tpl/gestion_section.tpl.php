<?php
/**
 * Template: sección de gestión con propietarios (solo en fichas de Activos RE_ACT)
 * Variables: $socid, $db
 */
if (!defined('DOL_DOCUMENT_ROOT')) die('Acceso directo no permitido');

require_once dol_buildpath('/custom/realestatecrmfields/class/regestion.class.php', 0);

$gestion  = new ReGestion($db);
$gestiones = $gestion->fetchByActivo($socid);

$canales    = ReGestion::CANALES;
$resultados = ReGestion::RESULTADOS;
$resColors  = ReGestion::RESULTADO_COLORS;

$sqlUsers = "SELECT rowid, CONCAT(firstname, ' ', lastname) AS nom FROM " . MAIN_DB_PREFIX . "user WHERE statut = 1 ORDER BY lastname";
$resUsers = $db->query($sqlUsers);
$usuarios = [];
while ($resUsers && ($u = $db->fetch_object($resUsers))) $usuarios[$u->rowid] = trim($u->nom);

$AJAX_GESTION = '/custom/realestatecrmfields/ajax/gestion_save.php';
?>

<div id="re-gestion-section" class="re-gestion-wrap" data-socid="<?= $socid ?>">
  <div class="div-table-responsive-no-min" style="margin-top:20px">
    <div class="liste_titre liste_titre_barre">
      <table class="noborder centpercent" style="margin:0">
        <tr class="liste_titre">
          <td><strong><span class="fas fa-phone" style="margin-right:6px;color:#6c6aa8"></span>Gestión con propietario</strong></td>
          <td class="right">
            <button type="button" class="butAction re-gestion-nueva-btn" style="margin:0">
              + Registrar contacto
            </button>
          </td>
        </tr>
      </table>
    </div>

    <?php if (empty($gestiones)): ?>
      <div class="opacitymedium" style="padding:12px 8px">Sin contactos registrados.</div>
    <?php else: ?>
    <table class="noborder centpercent">
      <tr class="liste_titre">
        <td>Fecha</td>
        <td>Propietario</td>
        <td>Canal</td>
        <td>Resultado</td>
        <td>Nota</td>
        <td>Recordatorio</td>
        <td class="center" style="width:60px"></td>
      </tr>
      <?php foreach ($gestiones as $g): ?>
      <tr class="oddeven re-gestion-row" data-rowid="<?= $g->rowid ?>">
        <td class="nowrap"><?= $g->fecha ? date('d/m/Y', strtotime($g->fecha)) : '—' ?></td>
        <td>
          <?php if ($g->fk_societe_propietario): ?>
            <a href="/societe/card.php?socid=<?= $g->fk_societe_propietario ?>"><?= dol_htmlentities($g->propietario_nom) ?></a>
            <?php if ($g->propietario_phone): ?><small class="opacitymedium"> · <?= dol_htmlentities($g->propietario_phone) ?></small><?php endif; ?>
          <?php else: ?>
            <?= dol_htmlentities($g->propietario_nombre ?: '—') ?>
            <?php if ($g->propietario_telefono): ?><small class="opacitymedium"> · <?= dol_htmlentities($g->propietario_telefono) ?></small><?php endif; ?>
          <?php endif; ?>
        </td>
        <td><?= ['TELEFONO'=>'📞','WHATSAPP'=>'🟢'][$g->canal] ?? '' ?> <?= dol_htmlentities($canales[$g->canal] ?? $g->canal) ?></td>
        <td>
          <span style="background:<?= $resColors[$g->resultado] ?? '#6c757d' ?>;color:#fff;padding:2px 8px;border-radius:4px;font-size:.82em;white-space:nowrap">
            <?= dol_htmlentities($resultados[$g->resultado] ?? $g->resultado) ?>
          </span>
        </td>
        <td class="tdoverflowmax200" title="<?= dol_escape_htmltag($g->nota) ?>"><?= dol_htmlentities(dol_trunc($g->nota, 60)) ?></td>
        <td class="nowrap">
          <?php if (!empty($g->fecha_recordatorio) && !$g->recordatorio_done):
            $recVenc = $g->fecha_recordatorio <= date('Y-m-d'); ?>
            <span style="color:<?= $recVenc ? '#c0392b' : '#0d6efd' ?>;font-weight:<?= $recVenc ? '600' : 'normal' ?>">
              <span class="fas fa-bell" style="margin-right:3px"></span><?= date('d/m/Y', strtotime($g->fecha_recordatorio)) ?>
            </span>
            <?php if ($g->nota_recordatorio): ?><br><small class="opacitymedium"><?= dol_htmlentities(dol_trunc($g->nota_recordatorio, 35)) ?></small><?php endif; ?>
          <?php else: ?><span class="opacitymedium">—</span><?php endif; ?>
        </td>
        <td class="center nowrap">
          <a href="#" class="re-gestion-edit-btn" data-rowid="<?= $g->rowid ?>" title="Editar" style="margin-right:4px"><span class="fas fa-pen"></span></a>
          <a href="#" class="re-gestion-del-btn"  data-rowid="<?= $g->rowid ?>" title="Eliminar"><span class="fas fa-trash opacitymedium"></span></a>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Modal gestión propietario -->
<div id="re-gestion-modal" class="re-modal-hidden" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;overflow-y:auto">
  <div style="background:#fff;max-width:600px;margin:40px auto;border-radius:6px;padding:28px 32px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <button type="button" id="re-gestion-modal-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.4em;cursor:pointer;color:#555">✕</button>
    <h3 id="re-gestion-modal-title" style="margin-top:0;margin-bottom:20px">
      <span class="fas fa-phone" style="color:#6c6aa8;margin-right:6px"></span>Registrar contacto con propietario
    </h3>

    <input type="hidden" id="rg-rowid">
    <input type="hidden" id="rg-activo-id" value="<?= $socid ?>">
    <input type="hidden" id="rg-prop-id">

    <div style="margin-bottom:14px">
      <label style="display:block;font-weight:600;margin-bottom:4px;color:#555">Propietario</label>
      <div id="rg-prop-found" style="display:none;padding:4px 0">
        <strong id="rg-prop-found-nom"></strong>
        <a href="#" id="rg-prop-clear" style="margin-left:8px;color:#c0392b;font-size:.85em">✕ quitar</a>
      </div>
      <div id="rg-prop-search-wrap" style="position:relative">
        <input type="text" id="rg-prop-search" class="flat" placeholder="Buscar propietario por nombre o tel…" style="width:100%">
        <div id="rg-prop-results" style="display:none;border:1px solid #ccc;max-height:160px;overflow-y:auto;background:#fff;position:absolute;z-index:10001;width:100%"></div>
      </div>
      <div style="margin-top:8px">
        <label style="font-size:.9em;color:#555;cursor:pointer">
          <input type="checkbox" id="rg-prop-nuevo-chk"> Propietario no registrado
        </label>
        <div id="rg-prop-nuevo-fields" style="display:none;margin-top:8px;padding:10px;background:#fafafa;border-radius:4px;border:1px solid #e0e0e0">
          <input type="text" id="rg-prop-nombre"   class="flat" placeholder="Nombre completo" style="width:100%;margin-bottom:6px">
          <input type="text" id="rg-prop-telefono" class="flat" placeholder="Teléfono" style="width:100%;margin-bottom:6px">
          <label style="font-size:.85em;cursor:pointer">
            <input type="checkbox" id="rg-crear-prop-chk"> Crear también como Propietario en Dolibarr
          </label>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
      <div>
        <label style="display:block;font-weight:600;margin-bottom:4px;color:#555">Fecha</label>
        <input type="datetime-local" id="rg-fecha" class="flat" style="width:100%">
      </div>
      <div>
        <label style="display:block;font-weight:600;margin-bottom:4px;color:#555">Canal</label>
        <select id="rg-canal" class="flat" style="width:100%">
          <?php foreach ($canales as $k => $v): ?>
          <option value="<?= $k ?>"><?= dol_htmlentities($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div style="margin-bottom:14px">
      <label style="display:block;font-weight:600;margin-bottom:4px;color:#555">Resultado</label>
      <select id="rg-resultado" class="flat" style="width:100%">
        <?php foreach ($resultados as $k => $v): ?>
        <option value="<?= $k ?>"><?= dol_htmlentities($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="margin-bottom:14px">
      <label style="display:block;font-weight:600;margin-bottom:4px;color:#555">Nota</label>
      <textarea id="rg-nota" class="flat" rows="3" style="width:100%;box-sizing:border-box" placeholder="¿Qué pasó en este contacto?"></textarea>
    </div>

    <div style="margin-bottom:14px">
      <label style="display:block;font-weight:600;margin-bottom:4px;color:#555">Atendió</label>
      <select id="rg-vendedor" class="flat" style="width:100%">
        <option value="">— Sin asignar —</option>
        <?php foreach ($usuarios as $uid => $unom): ?>
        <option value="<?= $uid ?>"><?= dol_htmlentities($unom) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="padding:12px;background:#f8f9fa;border-radius:4px;border-left:3px solid #6c6aa8;margin-bottom:14px">
      <strong style="font-size:.9em;color:#6c6aa8"><span class="fas fa-bell" style="margin-right:4px"></span>Recordatorio</strong>
      <div style="display:flex;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap">
        <span style="font-size:.9em">Recordar en</span>
        <input type="number" id="rg-rec-dias" min="1" max="365" class="flat" style="width:60px" placeholder="días">
        <span style="font-size:.9em">días</span>
        <span class="opacitymedium" id="rg-rec-fecha-preview" style="font-size:.82em"></span>
      </div>
      <input type="hidden" id="rg-rec-fecha">
      <input type="text" id="rg-rec-nota" class="flat" style="width:100%;margin-top:6px;box-sizing:border-box" placeholder="Nota del recordatorio…">
      <select id="rg-rec-user" class="flat" style="width:100%;margin-top:6px">
        <option value="">— Asignar a —</option>
        <?php foreach ($usuarios as $uid => $unom): ?>
        <option value="<?= $uid ?>"><?= dol_htmlentities($unom) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="text-align:right">
      <button type="button" id="re-gestion-modal-cancel" class="butActionDelete" style="margin-right:8px">Cancelar</button>
      <button type="button" id="re-gestion-submit" class="butAction">Guardar contacto</button>
    </div>
    <div id="rg-error" style="color:#c0392b;margin-top:8px;font-size:.9em;display:none"></div>
  </div>
</div>

<script>
// AJAX URL para gestión
var RG_AJAX_URL = '<?= DOL_URL_ROOT ?>/custom/realestatecrmfields/ajax/gestion_save.php';
</script>
