<?php
/* AJAX: Retorna subtipo de múltiples socids en una sola query
 * GET: ids=1234,5678,9012,...
 */
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) { echo json_encode([]); exit; }

header('Content-Type: application/json');
if (!isset($user) || empty($user->login)) { echo json_encode([]); exit; }

$rawIds = GETPOST('ids', 'alphanohtml');
$ids = array_filter(array_map('intval', explode(',', $rawIds)));

if (empty($ids)) { echo json_encode([]); exit; }

// Limitar a 500 ids por request
$ids = array_slice($ids, 0, 500);

$sql = "SELECT s.rowid, s.fk_re_subtypent AS subtypent_code
        FROM " . MAIN_DB_PREFIX . "societe s
        WHERE s.rowid IN (" . implode(',', $ids) . ")";

$result = [];
$res2 = $db->query($sql);
if ($res2) {
    while ($obj = $db->fetch_object($res2)) {
        $result[(string)$obj->rowid] = $obj->subtypent_code ?: '';
    }
}

echo json_encode($result);
