<?php
require_once '../../includes/config.php';
require_once '../../includes/damage_functions.php';
require_permission($db, 'm-inv');
header('Content-Type: application/json; charset=UTF-8');
ame_damage_ensure($db);

$q = trim((string) ($_GET['q'] ?? ''));
$digit = $q !== '' && ctype_digit($q);
if ($q === '' || (strlen($q) < 2 && !$digit)) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$rows = ame_damage_find_products($db, $q);
$out = [];
foreach ($rows as $row) {
    $out[] = [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'sku' => (string) ($row['sku'] ?? $row['code'] ?? ''),
        'code' => (string) ($row['code'] ?? ''),
        'stock' => (float) $row['stock'],
        'unit_cost' => (float) $row['unit_cost'],
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
