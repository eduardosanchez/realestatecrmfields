<?php
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) { echo ''; exit; }
if (!isset($user) || empty($user->login)) { echo ''; exit; }

$socid = (int)GETPOST('socid', 'int');
if (!$socid) { echo ''; exit; }

// Solo para RE_ACT
$sqlTipo = "SELECT t.code FROM " . MAIN_DB_PREFIX . "societe s
            INNER JOIN " . MAIN_DB_PREFIX . "c_typent t ON t.id = s.fk_typent
            WHERE s.rowid = $socid LIMIT 1";
$resTipo = $db->query($sqlTipo);
$oTipo   = ($resTipo) ? $db->fetch_object($resTipo) : null;
if (!$oTipo || $oTipo->code !== 'RE_ACT') { echo ''; exit; }

include dol_buildpath('/custom/realestatecrmfields/tpl/propietario_section.tpl.php', 0);
