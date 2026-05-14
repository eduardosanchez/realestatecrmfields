<?php
/**
 * Sección de propietarios/vinculados de un activo inmobiliario
 * Muestra historial completo con roles y fechas desde/hasta
 */
if (!defined('DOL_DOCUMENT_ROOT')) die('Acceso directo no permitido');

require_once dol_buildpath('/custom/realestatecrmfields/class/repropietario.class.php', 0);

$propObj    = new RePropietario($db);
$relaciones = $propObj->fetchByActivo($socid);

$roles      = RePropietario::ROLES;
$rolColors  = RePropietario::ROL_COLORS;

$sqlUsers = "SELECT rowid, CONCAT(firstname, ' ', lastname) AS nom FROM " . MAIN_DB_PREFIX . "user WHERE statut = 1 ORDER BY lastname";
$resUsers = $db->query($sqlUsers);
$usuarios = [];
while ($resUsers && ($u = $db->fetch_object($resUsers))) $usuarios[$u->rowid] = trim($u->nom);

$activos    = array_filter($relaciones, fn($r) => $r->activo);
$historicos = array_filter($relaciones, fn($r) => !$r->activo);
?>

<div id="re-propietario-section" data-socid="<?= $socid ?>">
  <div class="div-table-responsive-no-min" style="margin-top:20px">
    <div class="liste_titre liste_titre_barre">
      <table class="noborder centpercent" style="margin:0">
        <tr class="liste_titre">
          <td>
            <span class="fas fa-user-tie" style="margin-right:6px;color:#0d6efd"></span>
            <strong>Propietarios / Vinculados</strong>
            <?php if ($activos): ?>
              <span style="background:#0d6efd;color:#fff;border-radius:10px;padding:0 7px;font-size:.78em;margin-left:6px"><?= count($activos) ?></span>
            <?php endif; ?>
          </td>
          <td class="right">
            <button type="button" class="butAction re-prop-nueva-btn" style="margin:0">
              + Vincular propietario
            </button>
          </td>
        </tr>
      </table>
    </div>

    <?php if (empty($relaciones)): ?>
      <div class="opacitymedium" style="padding:12px 8px">Sin propietarios vinculados.</div>
    <?php else: ?>
    <table class="noborder centpercent">
      <tr class="liste_titre">
        <td>Nombre</td>
        <td>Rol</td>
        <td>Desde</td>
        <td>Hasta</td>
        <td>Nota</td>
        <td class="center" style="width:90px"></td>
      </tr>
      <?php foreach ($relaciones as $r): ?>
      <tr class="oddeven<?= !$r->activo ? ' opacitymedium' : '' ?>" data-rowid="<?= $r->rowid ?>">
        <td>
          <?php if ($r->fk_societe_propietario): ?>
            <a href="/societe/card.php?socid=<?= $r->fk_societe_propietario ?>"><?= dol_htmlentities($r->propietario_nom) ?></a>
            <?php if ($r->propietario_phone): ?><small class="opacitymedium"> · <?= dol_htmlentities($r->propietario_phone) ?></small><?php endif; ?>
          <?php else: ?>
            <?= dol_htmlentities($r->propietario_nombre ?: '—') ?>
            <?php if ($r->propietario_telefono): ?><small class="opacitymedium"> · <?= dol_htmlentities($r->propietario_telefono) ?></small><?php endif; ?>
          <?php endif; ?>
        </td>
        <td>
          <span style="background:<?= $rolColors[$r->rol] ?? '#6c757d' ?>;color:#fff;padding:2px 8px;border-radius:4px;font-size:.82em;white-space:nowrap">
            <?= dol_htmlentities($roles[$r->rol] ?? $r->rol) ?>
          </span>
        </td>
        <td class="nowrap"><?= $r->fecha_desde ? date('d/m/Y', strtotime($r->fecha_desde)) : '—' ?></td>
        <td class="nowrap">
          <?php if ($r->fecha_hasta): ?>
            <span style="color:#adb5bd"><?= date('d/m/Y', strtotime($r->fecha_hasta)) ?></span>
          <?php else: ?>
            <span style="color:#198754;font-weight:600">Vigente</span>
          <?php endif; ?>
        </td>
        <td class="tdoverflowmax150" title="<?= dol_escape_htmltag($r->nota) ?>"><?= dol_htmlentities(dol_trunc($r->nota, 50)) ?></td>
        <td class="center nowrap">
          <?php if ($r->activo): ?>
            <a href="#" class="re-prop-edit-btn" data-rowid="<?= $r->rowid ?>" title="Editar" style="margin-right:6px">
              <span class="fas fa-pen"></span>
            </a>
            <a href="#" class="re-prop-desvincular-btn" data-rowid="<?= $r->rowid ?>" title="Desvincular"
               style="color:#c0392b">
              <span class="fas fa-unlink"></span>
            </a>
          <?php else: ?>
            <a href="#" class="re-prop-delete-btn" data-rowid="<?= $r->rowid ?>" title="Eliminar registro"
               style="color:#adb5bd">
              <span class="fas fa-trash"></span>
            </a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Modal propietario -->
<div id="re-prop-modal" class="re-modal-hidden" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;overflow-y:auto">
  <div style="background:#fff;max-width:580px;margin:40px auto;border-radius:6px;padding:28px 32px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <button type="button" id="re-prop-modal-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.4em;cursor:pointer;color:#555">✕</button>
    <h3 id="re-prop-modal-title" style="margin-top:0;margin-bottom:20px">
      <span class="fas fa-user-tie" style="color:#0d6efd;margin-right:6px"></span>Vincular propietario
    </h3>

    <input type="hidden" id="rp-rowid">
    <input type="hidden" id="rp-activo-id" value="<?= $socid ?>">
    <input type="hidden" id="rp-prop-id">

    <!-- Búsqueda de actor existente -->
    <div style="margin-bottom:14px">
      <label style="display:block;font-weight:600;margin-bottom:4px;color:#555">Actor / Propietario</label>
      <div id="rp-prop-found" style="display:none;padding:6px 0;display:flex;align-items:center;gap:8px">
        <strong id="rp-prop-found-nom"></strong>
        <a href="#" id="rp-prop-clear" style="color:#c0392b;font-size:.85em">✕ quitar</a>
      </div>
      <div id="rp-prop-search-wrap" style="position:relative">
        <input type="text" id="rp-prop-search" class="flat" placeholder="Buscar actor por nombre o tel…" style="width:100%;box-sizing:border-box">
        <div id="rp-prop-results" style="display:none;border:1px solid #ccc;max-height:160px;overflow-y:auto;background:#fff;position:absolute;z-index:10001;width:100%"></div>
      </div>
      <!-- Siempre crear como Actor en Dolibarr -->
      <div id="rp-nuevo-fields" style="margin-top:10px;padding:10px;background:#fafafa;border-radius:4px;border:1px solid #e0e0e0">
        <div style="font-size:.85em;color:#555;margin-bottom:8px">
          <span class="fas fa-plus-circle" style="color:#198754;margin-right:4px"></span>
          <strong>O registrar nuevo (se creará también como Actor en Dolibarr)</strong>
        </div>
        <input type="text" id="rp-nombre"   class="flat" placeholder="Nombre completo" style="width:100%;box-sizing:border-box;margin-bottom:6px">
        <input type="text" id="rp-telefono" class="flat" placeholder="Teléfono" style="width:100%;box-sizing:border-box">
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
      <div>
        <label style="display:block;font-weight:600;margin-bottom:4px;color:#555">Rol</label>
        <select id="rp-rol" class="flat" style="width:100%">
          <?php foreach ($roles as $k => $v): ?>
          <option value="<?= $k ?>"><?= dol_htmlentities($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label style="display:block;font-weight:600;margin-bottom:4px;color:#555">Desde</label>
        <input type="date" id="rp-desde" class="flat" style="width:100%" value="<?= date('Y-m-d') ?>">
      </div>
    </div>

    <div style="margin-bottom:14px">
      <label style="display:block;font-weight:600;margin-bottom:4px;color:#555">Hasta <span class="opacitymedium" style="font-weight:normal">(dejar vacío si sigue vigente)</span></label>
      <input type="date" id="rp-hasta" class="flat" style="width:50%">
    </div>

    <div style="margin-bottom:14px">
      <label style="display:block;font-weight:600;margin-bottom:4px;color:#555">Nota</label>
      <textarea id="rp-nota" class="flat" rows="2" style="width:100%;box-sizing:border-box" placeholder="Observaciones…"></textarea>
    </div>

    <div style="text-align:right">
      <button type="button" id="re-prop-modal-cancel" class="butActionDelete" style="margin-right:8px">Cancelar</button>
      <button type="button" id="re-prop-submit" class="butAction">Guardar</button>
    </div>
    <div id="rp-error" style="color:#c0392b;margin-top:8px;font-size:.9em;display:none"></div>
  </div>
</div>

<script>
var RP_AJAX_URL = '<?= DOL_URL_ROOT ?>/custom/realestatecrmfields/ajax/propietario_save.php';
</script>
