<?php
/* ============================================================
 * Diagnóstico del módulo RealEstateCrmFields
 * URL: /custom/realestatecrmfields/admin/diag.php
 * ============================================================ */

$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) { print "Include failed"; exit; }

if (!$user->admin) accessforbidden();

require_once dol_buildpath('/custom/realestatecrmfields/class/realestatecrmfields.class.php', 0);

$service = new RealEstateCrmFields($db);

llxHeader('', 'Diagnóstico CRM Inmobiliario');
print load_fiche_titre('🔍 Diagnóstico RealEstateCrmFields', '', 'building');

$ok  = '<span style="color:green;font-weight:bold;">✓ OK</span>';
$err = '<span style="color:red;font-weight:bold;">✗ ERROR</span>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Verificación</th><th>Resultado</th><th>Detalle</th></tr>';

// 1. Tabla c_re_subtypent
$res1 = $db->query("SELECT COUNT(*) as cnt FROM " . MAIN_DB_PREFIX . "c_re_subtypent");
$cnt1 = $res1 ? $db->fetch_object($res1)->cnt : 0;
print '<tr class="oddeven"><td>Tabla zu4s_c_re_subtypent</td>';
print '<td>' . ($cnt1 > 0 ? $ok : $err) . '</td>';
print '<td>' . $cnt1 . ' subtipos</td></tr>';

// 2. Tabla re_extrafields_visibility
$res2 = $db->query("SELECT COUNT(*) as cnt FROM " . MAIN_DB_PREFIX . "re_extrafields_visibility");
$cnt2 = $res2 ? $db->fetch_object($res2)->cnt : 0;
print '<tr class="oddeven"><td>Tabla zu4s_re_extrafields_visibility</td>';
print '<td>' . $ok . '</td>';
print '<td>' . $cnt2 . ' reglas de visibilidad</td></tr>';

// 3. Columna fk_re_subtypent en societe
$res3 = $db->query("SHOW COLUMNS FROM " . MAIN_DB_PREFIX . "societe LIKE 'fk_re_subtypent'");
$col  = $res3 ? $db->num_rows($res3) : 0;
print '<tr class="oddeven"><td>Columna fk_re_subtypent en zu4s_societe</td>';
print '<td>' . ($col > 0 ? $ok : $err) . '</td>';
print '<td>' . ($col > 0 ? 'Existe' : 'NO EXISTE — ejecutar ALTER TABLE') . '</td></tr>';

// 4. Tipos cargados
$types = $service->getTypes();
print '<tr class="oddeven"><td>Tipos en zu4s_c_typent</td>';
print '<td>' . (count($types) === 3 ? $ok : $err) . '</td>';
print '<td>';
if ($types) {
    foreach ($types as $t) print '<b>' . htmlspecialchars($t['code']) . '</b> — ' . htmlspecialchars($t['libelle']) . '<br>';
} else {
    print 'Sin tipos — ejecutar fix_typent_codes.sql';
}
print '</td></tr>';

// 5. Subtipos por tipo
$subs = $service->getSubtypes();
print '<tr class="oddeven"><td>Subtipos por tipo</td>';
print '<td>' . (!empty($subs) ? $ok : $err) . '</td>';
print '<td>';
foreach ($subs as $typeCode => $list) {
    print '<b>' . htmlspecialchars($typeCode) . '</b>: ';
    print implode(', ', array_column($list, 'libelle')) . '<br>';
}
print '</td></tr>';

// 5b. Archivo de hooks existe con nombre correcto
$hooksFile   = dol_buildpath('/custom/realestatecrmfields/hooks/actions_realestatecrmfields.class.php', 0);
$hooksExists = file_exists($hooksFile);
print '<tr class="oddeven"><td>Archivo hooks (actions_realestatecrmfields.class.php)</td>';
print '<td>' . ($hooksExists ? $ok : $err) . '</td>';
print '<td>' . ($hooksExists ? '✓ Existe' : 'NO EXISTE — subir el ZIP actualizado') . '</td></tr>';

// 6. Hooks - Dolibarr 22+ los registra dinámicamente desde module_parts, no en tabla SQL
$modActive = getDolGlobalInt('MAIN_MODULE_REALESTATECRMFIELDS');
print '<tr class="oddeven"><td>Módulo activo</td>';
print '<td>' . ($modActive ? $ok : $err) . '</td>';
print '<td>' . ($modActive ? 'MAIN_MODULE_REALESTATECRMFIELDS = ' . $modActive : 'Módulo no activo — activar desde Módulos') . '</td></tr>';

// Verificar contextos configurados leyendo el módulo directamente
$modFile = dol_buildpath('/custom/realestatecrmfields/core/modules/modRealEstateCrmFields.class.php', 0);
if (file_exists($modFile)) {
    include_once $modFile;
    $tmpMod = new modRealEstateCrmFields($db);
    $hookContexts = $tmpMod->module_parts['hooks'] ?? [];
    print '<tr class="oddeven"><td>Contextos de hooks configurados</td>';
    print '<td>' . (count($hookContexts) > 0 ? $ok : $err) . '</td>';
    print '<td>' . implode(', ', $hookContexts) . '</td></tr>';
}

// 7. Extrafields de societe
$efs = $service->getExtrafields('societe');
print '<tr class="oddeven"><td>Campos personalizados (societe)</td>';
print '<td>' . (count($efs) > 0 ? $ok : '<span style="color:orange;">⚠ Sin campos</span>') . '</td>';
print '<td>';
if ($efs) {
    foreach ($efs as $ef) print htmlspecialchars($ef['name']) . ' (' . htmlspecialchars($ef['type']) . ')<br>';
} else {
    print 'Crear campos en Configuración → Atributos personalizados → Terceros';
}
print '</td></tr>';

print '</table>';

// Queries de corrección si hay errores
print '<br><div style="background:#fff3cd;border:1px solid #ffc107;padding:12px;border-radius:4px;">';
print '<b>⚡ Si hay errores, ejecutar estas queries en phpMyAdmin:</b><br><br>';
print '<pre style="background:#f8f8f8;padding:10px;border-radius:3px;">';
print htmlspecialchars(
"-- Agregar columna si no existe
ALTER TABLE zu4s_societe ADD COLUMN IF NOT EXISTS fk_re_subtypent VARCHAR(32) DEFAULT NULL;

-- Reinsertar tipos
INSERT IGNORE INTO zu4s_c_typent (code, libelle, active, module, position) VALUES
('RE_ACT', 'Activo Inmobiliario', 1, 'realestatecrmfields', 10),
('RE_AOR', 'Actor',               1, 'realestatecrmfields', 20),
('RE_SRV', 'Servicio',            1, 'realestatecrmfields', 30);

-- Corregir fk_typent en subtipos
UPDATE zu4s_c_re_subtypent SET fk_typent = 'RE_ACT' WHERE fk_typent IN ('ACTIVO','RE_ACT');
UPDATE zu4s_c_re_subtypent SET fk_typent = 'RE_AOR' WHERE fk_typent IN ('ACTOR','RE_AOR');
UPDATE zu4s_c_re_subtypent SET fk_typent = 'RE_SRV' WHERE fk_typent IN ('SERVICIO','RE_SRV');"
);
print '</pre></div>';

llxFooter();
$db->close();
