<?php
require_once '../../includes/config.php';
require_once '../../includes/unit_engine.php';
ame_unit_ensure($db);
require_permission($db, 'm-inv');
header('Content-Type: application/json; charset=UTF-8');
$id = (int)($_GET['product_id'] ?? 0);
$role = ($_GET['role'] ?? '') === 'purchase' ? 'purchase' : 'sale';
if ($id <= 0) {
    echo json_encode(['units' => [], 'hint' => '', 'base_id' => 0]);
    exit;
}
echo json_encode([
    'base_id' => ame_unit_base_id($db, $id),
    'hint' => ame_unit_hint($db, $id),
    'stock' => ame_unit_stock_base($db, $id),
    'units' => ame_unit_options($db, $id, $role),
], JSON_UNESCAPED_UNICODE);
