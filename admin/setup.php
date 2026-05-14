<?php
/* ============================================================
 * Admin: Configuración general del módulo RealEstateCrmFields
 * ============================================================ */

$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) { print "Include failed"; exit; }

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once dol_buildpath('/custom/realestatecrmfields/class/realestatecrmfields.class.php', 0);
require_once dol_buildpath('/custom/realestatecrmfields/helpers.php', 0);

$langs->loadLangs(['admin', 'realestatecrmfields']);

// Verificar permisos
if (!$user->admin) accessforbidden();

$action  = GETPOST('action', 'alpha');
$service = new RealEstateCrmFields($db);

// Procesar acciones
if ($action === 'setvalue' && GETPOST('token', 'alpha')) {
    $paramName  = GETPOST('param', 'alpha');
    $paramValue = GETPOST('value', 'alpha');
    $allowed    = ['REALESTATE_DEFAULT_TYPE'];
    if (in_array($paramName, $allowed)) {
        dolibarr_set_const($db, $paramName, $paramValue, 'chaine', 0, '', $conf->entity);
        setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
    }
}

// ── Encabezado ────────────────────────────────────────────
llxHeader('', $langs->trans('ConfiguracionCRMInmobiliario'));

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
          . $langs->trans('BackToModuleList') . '</a>';

print load_fiche_titre($langs->trans('ConfiguracionCRMInmobiliario'), $linkback, 'building');

// Tabs
$tabs = [
    ['title' => $langs->trans('General'),              'url' => '/custom/realestatecrmfields/admin/setup.php'],
    ['title' => $langs->trans('VisibilidadDeCampos'), 'url' => '/custom/realestatecrmfields/admin/visibility.php'],
];
$head = [];
foreach ($tabs as $i => $t) {
    $head[$i][0] = DOL_URL_ROOT . $t['url'];
    $head[$i][1] = $t['title'];
    $head[$i][2] = 'tab' . $i;
}
print dol_get_fiche_head($head, 'tab0', '', -1, 'building');

// ── Contenido ─────────────────────────────────────────────
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . $langs->trans('ConfiguracionGeneral') . '</th></tr>';

// Tipo por defecto
print '<tr class="oddeven">';
print '<td>' . $langs->trans('TipoTerceroPorDefecto') . '</td>';
print '<td>';

$types    = $service->getTypes();
$default  = getDolGlobalString('REALESTATE_DEFAULT_TYPE');

print '<form method="POST">';
print '<input type="hidden" name="action" value="setvalue">';
print '<input type="hidden" name="token" value="' . generateToken() . '">';
print '<input type="hidden" name="param" value="REALESTATE_DEFAULT_TYPE">';
print '<select name="value" class="flat">';
print '<option value="">-- ' . $langs->trans('Ninguno') . ' --</option>';
foreach ($types as $code => $type) {
    $sel = ($default === $code) ? 'selected' : '';
    print '<option value="' . htmlspecialchars($code) . '" ' . $sel . '>'
        . htmlspecialchars($type['libelle']) . '</option>';
}
print '</select> ';
print '<input type="submit" class="button" value="' . $langs->trans('Guardar') . '">';
print '</form>';
print '</td></tr>';

print '</table>';

// ── Info de tablas ─────────────────────────────────────────
print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>' . $langs->trans('EstadoTablas') . '</th><th>' . $langs->trans('Registros') . '</th></tr>';

$tables = [
    MAIN_DB_PREFIX . 'c_re_subtypent'          => 'Subtipos',
    MAIN_DB_PREFIX . 're_extrafields_visibility' => 'Reglas de visibilidad',
];

foreach ($tables as $table => $label) {
    $res = $db->query("SELECT COUNT(*) as cnt FROM $table");
    $cnt = $res ? $db->fetch_object($res)->cnt : 'ERROR';
    print '<tr class="oddeven">';
    print '<td>' . $label . ' <small>(' . $table . ')</small></td>';
    print '<td>' . $cnt . '</td>';
    print '</tr>';
}
print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();
