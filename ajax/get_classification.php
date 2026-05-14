<?php
/* AJAX: Retorna tipo, subtipo actual + mapa id→code de TODOS los tipos */
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) { echo json_encode([]); exit; }

header('Content-Type: application/json');
if (!isset($user) || empty($user->login)) { echo json_encode([]); exit; }

require_once dol_buildpath('/custom/realestatecrmfields/class/realestatecrmfields.class.php', 0);

$socid   = (int)GETPOST('socid', 'int');
$service = new RealEstateCrmFields($db);

// Clasificación del tercero (vacía si es nuevo)
$classif = $socid > 0
    ? $service->getThirdPartyClassification($socid)
    : ['typent_code' => '', 'subtypent_code' => ''];

// Mapa id numérico → code SIEMPRE (necesario para filtrar optgroups en JS)
$sql = "SELECT id, code FROM " . MAIN_DB_PREFIX . "c_typent
        WHERE active = 1 AND code IN ('RE_ACT','RE_AOR','RE_SRV')";
$res2    = $db->query($sql);
$allTypes = [];
if ($res2) {
    while ($obj = $db->fetch_object($res2)) {
        $allTypes[(string)$obj->id] = $obj->code;
    }
}
$classif['all_types'] = $allTypes;

echo json_encode($classif);
