<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/leftover_cleanup.php';
admin_only();
require_post();
csrf_verify();

$root = ame_public_html_root();
$removed = [];
$failed = [];
foreach (ame_leftover_demo_scan() as $item) {
    if (ame_leftover_rm($item['path'], $root)) {
        $removed[] = $item['name'];
    } else {
        $failed[] = $item['name'];
    }
}

if ($removed) {
    log_activity('cleanup_demo', 'settings', 'Removed leftover Next.js files: ' . implode(', ', $removed));
}

$qs = 'cleaned=1';
if ($removed) {
    $qs .= '&n=' . count($removed);
}
if ($failed) {
    $qs .= '&fail=1';
}
header('Location: /modules/settings/tools.php?' . $qs);
exit;
