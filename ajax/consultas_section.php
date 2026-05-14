<?php
/**
 * AJAX: retorna el HTML de la sección de consultas para un tercero
 * GET: socid, mode (activo|actor)
 */
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) { echo ''; exit; }
if (!isset($user) || empty($user->login)) { echo ''; exit; }

$socid = (int)GETPOST('socid', 'int');
$mode  = in_array(GETPOST('mode','alpha'), ['activo','actor'])
        ? GETPOST('mode','alpha') : 'activo';

if (!$socid) { echo ''; exit; }

// Verificar que el tercero existe y tiene el tipo correcto
$sqlTipo = "SELECT t.code FROM " . MAIN_DB_PREFIX . "societe s
            INNER JOIN " . MAIN_DB_PREFIX . "c_typent t ON t.id = s.fk_typent
            WHERE s.rowid = $socid LIMIT 1";
$resTipo  = $db->query($sqlTipo);
$oTipo    = ($resTipo) ? $db->fetch_object($resTipo) : null;
$typeCode = $oTipo ? $oTipo->code : '';

if (!in_array($typeCode, ['RE_ACT', 'RE_AOR'])) { echo ''; exit; }

// El mode debe coincidir con el tipo real
$mode = ($typeCode === 'RE_ACT') ? 'activo' : 'actor';

include dol_buildpath('/custom/realestatecrmfields/tpl/consultas_section.tpl.php', 0);
