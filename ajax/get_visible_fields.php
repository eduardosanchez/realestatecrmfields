<?php
/* AJAX: Retorna campos VISIBLES para un tipo+subtipo dado */
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) { echo json_encode(['visible' => []]); exit; }

header('Content-Type: application/json');

if (!isset($user) || empty($user->login)) {
    echo json_encode(['visible' => []]);
    exit;
}

require_once dol_buildpath('/custom/realestatecrmfields/class/realestatecrmfields.class.php', 0);

$typent    = GETPOST('typent',    'alpha');
$subtypent = GETPOST('subtypent', 'alpha');

if (!$typent) {
    echo json_encode(['visible' => []]);
    exit;
}

$service = new RealEstateCrmFields($db);
$visible = $service->getVisibleFields($typent, $subtypent ?: '');

echo json_encode(['visible' => $visible]);
