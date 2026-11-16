<?php
require_once '../../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
$p = ame_pulse_data($db);
$p['script'] = ame_pulse_script($p);
$p['agents'] = array_map(static function ($a) {
    unset($a['items']);
    return $a;
}, ame_shop_agents($db, $p));
$p['cached_at'] = date('c');
$p['shop'] = shop_name();
unset($p['dues'], $p['restock']);
echo json_encode(['ok' => true, 'pulse' => $p], JSON_UNESCAPED_UNICODE);
