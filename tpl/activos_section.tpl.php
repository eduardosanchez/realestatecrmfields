<?php
/**
 * Sección de activos (garajes) vinculados a un propietario/actor
 * Vista inversa de propietario_section.tpl.php:
 *   propietario_section → se muestra en la ficha del ACTIVO (garaje)
 *   activos_section     → se muestra en la ficha del PROPIETARIO/ACTOR
 *
 * Variables disponibles en el contexto del hook:
 *   $socid → rowid del ThirdParty propietario que se está viendo
 *   $db    → conexión Dolibarr
 */
if (!defined('DOL_DOCUMENT_ROOT')) die('Acceso directo no permitido');

require_once dol_buildpath('/custom/realestatecrmfields/class/repropietario.class.php', 0);

$propObj    = new RePropietario($db);
$relaciones = $propObj->fetchByPropietario($socid);

$roles     = RePropietario::ROLES;
$rolColors = RePropietario::ROL_COLORS;

$activos    = array_filter($relaciones, fn($r) => $r->activo);
$historicos = array_filter($relaciones, fn($r) => !$r->activo);
?>

<div id="re-activos-section" data-socid="<?= (int)$socid ?>">
  <div class="div-table-responsive-no-min" style="margin-top:20px">

    <div class="liste_titre liste_titre_barre">
      <table class="noborder centpercent" style="margin:0">
        <tr class="liste_titre">
          <td>
            <span class="fas fa-building" style="margin-right:6px;color:#198754"></span>
            <strong>Inmuebles vinculados</strong>
            <?php if ($activos): ?>
              <span style="background:#198754;color:#fff;border-radius:10px;padding:0 7px;font-size:.78em;margin-left:6px"><?= count($activos) ?></span>
            <?php endif; ?>
          </td>
        </tr>
      </table>
    </div>

    <?php if (empty($relaciones)): ?>
      <div class="opacitymedium" style="padding:12px 8px">
        Sin inmuebles vinculados. Para vincular, abrí la ficha del garaje y usá "+ Vincular propietario".
      </div>
    <?php else: ?>
    <table class="noborder centpercent">
      <tr class="liste_titre">
        <td>Inmueble</td>
        <td>Rol</td>
        <td>Desde</td>
        <td>Hasta</td>
        <td>Nota</td>
      </tr>
      <?php foreach ($relaciones as $r): ?>
      <?php
        // Armar dirección del activo desde los extrafields
        $dir = trim(($r->activo_calle ?? '') . ' ' . ($r->activo_numero ?? ''));
        $label = $dir ?: ($r->activo_nom ?? '—');
        $sublabel = '';
        if ($r->activo_barrio) $sublabel .= $r->activo_barrio;
        if (!empty($r->activo_cocheras) && (int)$r->activo_cocheras > 0) {
            $sublabel .= ($sublabel ? ' · ' : '') . (int)$r->activo_cocheras . ' cocheras';
        }
      ?>
      <tr class="oddeven<?= !$r->activo ? ' opacitymedium' : '' ?>">
        <td>
          <a href="<?= DOL_URL_ROOT ?>/societe/card.php?socid=<?= (int)$r->fk_societe_activo ?>">
            <?= dol_htmlentities($label) ?>
          </a>
          <?php if ($sublabel): ?>
            <small class="opacitymedium"> · <?= dol_htmlentities($sublabel) ?></small>
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
        <td class="tdoverflowmax200" title="<?= dol_escape_htmltag($r->nota) ?>">
          <?= dol_htmlentities(dol_trunc($r->nota, 60)) ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>

  </div>
</div>
