<?php
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
header('Content-Type: application/json');

$socid = (int)($_GET['socid'] ?? 0);

// Ver datos del tercero
$sql = "SELECT s.rowid, s.nom, s.fk_typent, s.fk_re_subtypent, t.code AS typent_code
        FROM " . MAIN_DB_PREFIX . "societe s
        LEFT JOIN " . MAIN_DB_PREFIX . "c_typent t ON t.id = s.fk_typent
        WHERE s.rowid = $socid LIMIT 1";
$res2 = $db->query($sql);
$actor = ($res2 && $db->num_rows($res2)) ? $db->fetch_object($res2) : null;

// Ver todos los tipos RE_*
$sql2 = "SELECT id, code, libelle FROM " . MAIN_DB_PREFIX . "c_typent WHERE code IN ('RE_ACT','RE_AOR','RE_SRV') ORDER BY id";
$res3 = $db->query($sql2);
$tipos = [];
while ($res3 && ($o = $db->fetch_object($res3))) $tipos[] = $o;

echo json_encode(['actor' => $actor, 'tipos_re' => $tipos]);
