<?php
/* AJAX: Retorna todos los subtipos agrupados por tipo */
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) { echo json_encode([]); exit; }

header('Content-Type: application/json');

if (!isset($user) || empty($user->login)) { echo json_encode([]); exit; }

require_once dol_buildpath('/custom/realestatecrmfields/class/realestatecrmfields.class.php', 0);

$service = new RealEstateCrmFields($db);
echo json_encode($service->getSubtypes());
