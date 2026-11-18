<?php
require_once '../../includes/config.php';
require_once '../../includes/live_work.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
if (!ame_live_work_allowed($db)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'অনুমতি নেই।'], JSON_UNESCAPED_UNICODE);
    exit;
}
$pack = ame_live_work_payload($db, $_GET);
echo json_encode($pack, JSON_UNESCAPED_UNICODE);
