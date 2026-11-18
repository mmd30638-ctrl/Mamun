<?php
require_once __DIR__ . '/includes/config.php';
if (function_exists('log_activity') && !empty($_SESSION['user_id'])) {
    log_activity('Logout', 'Security', (string)($_SESSION['username'] ?? ''), [
        'action_type' => 'logout',
        'entity_type' => 'user',
        'entity_id' => (string)(int)$_SESSION['user_id'],
        'reference' => (string)($_SESSION['username'] ?? ''),
    ]);
}
$ame_idle_lock = isset($_GET['idle']) && (string) $_GET['idle'] === '1';
ame_clear_session_cookie();
header('Location: /login.php' . ($ame_idle_lock ? '?timeout=1' : ''));
exit;
