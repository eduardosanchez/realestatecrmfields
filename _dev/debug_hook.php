<?php
/* DEBUG: Verifica el estado del filtrado de subtipo */
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
header('Content-Type: application/json');
if (!isset($user) || empty($user->login)) { echo json_encode(['error'=>'not auth']); exit; }

$sub = trim((string)($_GET['search_re_subtypent'] ?? ''));

// 1. ¿Existe el código en c_re_subtypent?
$r1 = $db->query("SELECT code, libelle FROM " . MAIN_DB_PREFIX . "c_re_subtypent WHERE code = '" . $db->escape($sub) . "' LIMIT 1");
$codeRow = ($r1 && $db->num_rows($r1)) ? $db->fetch_object($r1) : null;

// 2. ¿Cuántos registros en societe tienen ese subtipo?
$r2 = $db->query("SELECT COUNT(*) as cnt FROM " . MAIN_DB_PREFIX . "societe WHERE fk_re_subtypent = '" . $db->escape($sub) . "'");
$cnt = ($r2) ? $db->fetch_object($r2)->cnt : 'error';

// 3. ¿El módulo está activo y el hook registrado?
$r3 = $db->query("SELECT value FROM " . MAIN_DB_PREFIX . "const WHERE name = 'MAIN_MODULE_REALESTATECRMFIELDS' LIMIT 1");
$modActive = ($r3 && $db->num_rows($r3)) ? $db->fetch_object($r3)->value : 'not found';

// 4. ¿Qué contextos están registrados?
$r4 = $db->query("SELECT value FROM " . MAIN_DB_PREFIX . "const WHERE name = 'MAIN_MODULE_REALESTATECRMFIELDS_HOOKS' LIMIT 1");
$hooks = ($r4 && $db->num_rows($r4)) ? $db->fetch_object($r4)->value : 'not found';

echo json_encode([
    'search_re_subtypent'     => $sub,
    'code_in_c_re_subtypent'  => $codeRow ? ['code' => $codeRow->code, 'libelle' => $codeRow->libelle] : null,
    'count_in_societe'        => $cnt,
    'module_active'           => $modActive,
    'registered_hooks'        => $hooks,
    'expected_where'          => "AND s.fk_re_subtypent = '$sub'",
], JSON_PRETTY_PRINT);
