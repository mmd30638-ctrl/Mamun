<?php
/**
 * Keeps the database connection warm while someone is still using the shop.
 * Does not start a login session and does not move the idle lock.
 */
require_once __DIR__ . '/includes/env_bootstrap.php';
$envPath = ame_env_path();
ame_apply_env(ame_read_env_file($envPath));
require_once __DIR__ . '/includes/ame_db_connect.php';
if (!isset($db) || !($db instanceof mysqli)) {
    $opened = ame_open_mysqli();
    if ($opened instanceof mysqli) {
        $db = $opened;
    }
}
if (!isset($db) || !($db instanceof mysqli)) {
    require_once __DIR__ . '/includes/db.php';
}

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

if (isset($db) && $db instanceof mysqli) {
    $db->query('SELECT 1');
}
echo 'ok';
