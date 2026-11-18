<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/schema_sync.php';
admin_only();
require_post();
csrf_verify();

$_SESSION['schema_sync_report'] = ame_schema_sync_run($db);
if (function_exists('log_activity')) {
    $r = $_SESSION['schema_sync_report'];
    log_activity(
        'schema_sync',
        'settings',
        'created=' . count($r['created_tables']) . ' columns=' . count($r['added_columns'])
    );
}
header('Location: /modules/settings/schema_sync.php');
exit;
