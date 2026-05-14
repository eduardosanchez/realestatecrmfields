<?php
/* ============================================================
 * Admin: Matriz de visibilidad de campos por tipo/subtipo
 * ============================================================ */

$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) { print "Include failed"; exit; }

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once dol_buildpath('/custom/realestatecrmfields/class/realestatecrmfields.class.php', 0);
require_once dol_buildpath('/custom/realestatecrmfields/helpers.php', 0);

$langs->loadLangs(['admin', 'realestatecrmfields']);

if (!$user->admin) accessforbidden();

$service = new RealEstateCrmFields($db);

$extrafields    = $service->getExtrafields('societe');
$subtypesByType = $service->getSubtypes();
$allVisibility  = $service->getAllVisibility();
$types          = ['RE_ACT' => 'Activos', 'RE_AOR' => 'Actores', 'RE_SRV' => 'Servicios'];

// ── Encabezado ────────────────────────────────────────────
llxHeader('', $langs->trans('VisibilidadDeCampos'));

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
          . $langs->trans('BackToModuleList') . '</a>';

print load_fiche_titre($langs->trans('VisibilidadDeCampos'), $linkback, 'building');

$head = [
    [DOL_URL_ROOT . '/custom/realestatecrmfields/admin/setup.php',      $langs->trans('General'),              'tab0'],
    [DOL_URL_ROOT . '/custom/realestatecrmfields/admin/visibility.php', $langs->trans('VisibilidadDeCampos'), 'tab1'],
];
print dol_get_fiche_head($head, 'tab1', '', -1, 'building');
?>

<div id="re-vis-feedback" style="display:none;padding:8px 12px;margin-bottom:10px;border-radius:4px;"></div>

<p style="color:#555;">
    <?= $langs->trans('InstruccionesVisibilidad') ?>
</p>

<?php if (empty($extrafields)): ?>
<div class="info"><?= $langs->trans('NoExtrafieldsFound') ?></div>
<?php else: ?>

<div class="div-table-responsive">
<table class="noborder centpercent" id="re-visibility-table">
    <thead>
        <!-- Fila 1: Tipos -->
        <tr class="liste_titre">
            <th rowspan="2" style="min-width:180px;vertical-align:middle;position:sticky;left:0;background:#f0f0f0;z-index:2;">
                Campo personalizado
            </th>
            <?php foreach ($types as $typeCode => $typeLabel):
                $subsCount = isset($subtypesByType[$typeCode]) ? count($subtypesByType[$typeCode]) : 0;
                if ($subsCount === 0) continue;
            ?>
            <th colspan="<?= $subsCount + 1 ?>"
                style="text-align:center;border-left:3px solid #7986cb;background:#e8eaf6;color:#3949ab;">
                <?= htmlspecialchars($typeLabel) ?>
                <br>
                <span style="font-size:10px;font-weight:normal;">
                    <a href="#" class="re-check-all"
                       data-type="<?= htmlspecialchars($typeCode) ?>"
                       data-action="check">✓ todos</a>
                    |
                    <a href="#" class="re-check-all"
                       data-type="<?= htmlspecialchars($typeCode) ?>"
                       data-action="uncheck">✗ ninguno</a>
                </span>
            </th>
            <?php endforeach; ?>
        </tr>
        <!-- Fila 2: Subtipos + check de tipo completo -->
        <tr class="liste_titre">
            <?php foreach ($types as $typeCode => $typeLabel):
                if (!isset($subtypesByType[$typeCode])) continue;
            ?>
            <!-- Check "todo el tipo" (subtipo NULL) -->
            <th style="text-align:center;border-left:3px solid #7986cb;font-size:11px;background:#ede7f6;color:#6a1b9a;">
                Todos<br>
                <small>(tipo)</small>
            </th>
            <?php foreach ($subtypesByType[$typeCode] as $sub): ?>
            <th style="text-align:center;font-size:11px;white-space:nowrap;max-width:80px;overflow:hidden;text-overflow:ellipsis;"
                title="<?= htmlspecialchars($sub['libelle']) ?>">
                <?= htmlspecialchars($sub['libelle']) ?>
            </th>
            <?php endforeach; endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($extrafields as $ef):
            $visFor = $allVisibility[$ef['rowid']] ?? [];
            $noRules = empty($visFor);
        ?>
        <tr class="oddeven" data-field-id="<?= (int)$ef['rowid'] ?>">
            <td style="position:sticky;left:0;background:white;z-index:1;">
                <strong><?= htmlspecialchars($ef['label'] ?: $ef['name']) ?></strong>
                <br>
                <small style="color:#999;"><?= htmlspecialchars($ef['name']) ?></small>
                <?php if ($noRules): ?>
                <br><span class="badge badge-success" style="font-size:10px;color:#999;">● oculto (sin reglas)</span>
                <?php endif; ?>
            </td>

            <?php foreach ($types as $typeCode => $typeLabel):
                if (!isset($subtypesByType[$typeCode])) continue;

                // Check para "todo el tipo" (subtypent_code = NULL, key = TYPE:ACTIVO)
                $typeKey   = 'TYPE:' . $typeCode;
                $typeCheck = in_array($typeKey, $visFor);
            ?>
            <!-- Check de tipo completo -->
            <td style="text-align:center;border-left:3px solid #7986cb;background:#faf7ff;">
                <input type="checkbox"
                       class="re-vis-check"
                       data-field-id="<?= (int)$ef['rowid'] ?>"
                       data-type="<?= htmlspecialchars($typeCode) ?>"
                       data-subtype=""
                       <?= $typeCheck ? 'checked' : '' ?>
                       title="Visible para todos los <?= htmlspecialchars($typeLabel) ?>"
                />
            </td>

            <?php foreach ($subtypesByType[$typeCode] as $sub):
                $isChecked = in_array($sub['code'], $visFor);
            ?>
            <td style="text-align:center;">
                <input type="checkbox"
                       class="re-vis-check"
                       data-field-id="<?= (int)$ef['rowid'] ?>"
                       data-type="<?= htmlspecialchars($typeCode) ?>"
                       data-subtype="<?= htmlspecialchars($sub['code']) ?>"
                       <?= $isChecked ? 'checked' : '' ?>
                       title="<?= htmlspecialchars($ef['label'] ?: $ef['name']) ?> → <?= htmlspecialchars($sub['libelle']) ?>"
                />
            </td>
            <?php endforeach; endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<p style="color:#666;font-size:12px;margin-top:10px;">
    <strong>Columna "Todos (tipo)"</strong>: el campo será visible para cualquier subtipo de ese tipo.<br>
    <strong>Sin ninguna marca</strong>: el campo está oculto para todos.<br>
    <strong>Con al menos una marca</strong>: el campo solo se muestra donde está marcado.
</p>

<?php endif; ?>

<script>
jQuery(document).ready(function($) {

    var TOKEN = '<?= dol_escape_js(generateToken()) ?>';
    var AJAX_URL = '<?= DOL_URL_ROOT ?>/custom/realestatecrmfields/ajax/update_visibility.php';

    function showFeedback(msg, isOk) {
        var $fb = $('#re-vis-feedback');
        var bg  = isOk ? '#d4edda' : '#f8d7da';
        var cl  = isOk ? '#155724' : '#721c24';
        var br  = isOk ? '#c3e6cb' : '#f5c6cb';
        $fb.css({background: bg, color: cl, border: '1px solid ' + br})
           .text(msg).show().delay(2500).fadeOut();
    }

    // Cambio de un checkbox individual
    $(document).on('change', '.re-vis-check', function() {
        var $cb       = $(this);
        var fieldId   = $cb.data('field-id');
        var typeCode  = $cb.data('type');
        var subCode   = $cb.data('subtype') || null;
        var visible   = $cb.prop('checked') ? 1 : 0;

        $cb.prop('disabled', true);

        var formData = new URLSearchParams();
        formData.append('field_id',  fieldId);
        formData.append('typent',    typeCode);
        formData.append('subtypent', subCode || '');
        formData.append('visible',   visible);
        formData.append('token',     TOKEN);

        fetch(AJAX_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData.toString()
        })
        .then(r => r.json())
        .then(data => {
            $cb.prop('disabled', false);
            if (data.success) {
                showFeedback('✓ Guardado', true);
                // Si sin ningún check, mostrar badge "siempre visible"
                updateAlwaysVisibleBadge($cb.closest('tr'));
            } else {
                $cb.prop('checked', !$cb.prop('checked'));
                showFeedback('✗ Error: ' + (data.error || 'desconocido'), false);
            }
        })
        .catch(() => {
            $cb.prop('disabled', false);
            $cb.prop('checked', !$cb.prop('checked'));
            showFeedback('✗ Error de red', false);
        });
    });

    // Botones "todos / ninguno" por tipo
    $(document).on('click', '.re-check-all', function(e) {
        e.preventDefault();
        var typeCode = $(this).data('type');
        var action   = $(this).data('action'); // 'check' o 'uncheck'
        var checked  = (action === 'check');

        $('[data-type="' + typeCode + '"].re-vis-check').each(function() {
            var $cb = $(this);
            if ($cb.prop('checked') !== checked) {
                $cb.prop('checked', checked).trigger('change');
            }
        });
    });

    // Badge "oculto cuando no hay ningún check en la fila
    function updateAlwaysVisibleBadge($row) {
        var anyChecked = $row.find('.re-vis-check:checked').length > 0;
        var $badge     = $row.find('.badge');
        if (!anyChecked) {
            if (!$badge.length) {
                $row.find('td:first small').after(
                    '<br><span class="badge badge-success" style="font-size:10px;color:#999;">● oculto (sin reglas)</span>'
                );
            }
        } else {
            $badge.closest('br').addBack().remove();
            $badge.remove();
        }
    }

});
</script>

<?php
print dol_get_fiche_end();
llxFooter();
$db->close();
