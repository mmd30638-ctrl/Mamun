<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/leftover_cleanup.php';
admin_only();
require_post();
csrf_verify();

$removed = [];
$failed = [];
foreach (ame_uploads_legacy_code_scan() as $item) {
    if (ame_uploads_legacy_code_rm($item['path'])) {
        $removed[] = $item['name'];
    } else {
        $failed[] = $item['name'];
    }
}

if ($removed) {
    log_activity('cleanup_uploads_code', 'settings', 'Removed leftover code from uploads/: ' . implode(', ', $removed));
}

$qs = 'ucleaned=1';
if ($removed) {
    $qs .= '&un=' . count($removed);
}
if ($failed) {
    $qs .= '&ufail=1';
}
header('Location: /modules/settings/tools.php?' . $qs);
exit;
