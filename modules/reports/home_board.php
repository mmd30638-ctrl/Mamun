<?php
require_once __DIR__ . '/../../includes/config.php';
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'লগইন দরকার।';
    exit;
}
require_once __DIR__ . '/../../includes/live_work.php';
require_once __DIR__ . '/../../includes/home_dash.php';
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
ame_home_dash_render($db, $_GET);
