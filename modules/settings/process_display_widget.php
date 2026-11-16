<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/display_widgets.php';
admin_only();
csrf_verify();

$kind = (string)($_POST['kind'] ?? '');
if ($kind !== 'ticker' && $kind !== 'countdown') {
    die('অবৈধ ধরন');
}

$back = $kind === 'countdown' ? 'countdown.php' : 'scrolling_text.php';
$action = (string)($_POST['action'] ?? 'save');
$id = (int)($_POST['id'] ?? 0);

try {
    ensure_display_widgets_schema($db);

    if ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM display_widgets WHERE id=? AND kind=?");
        $stmt->bind_param('is', $id, $kind);
        $stmt->execute();
        $stmt->close();
        try { log_activity('delete', 'display_widgets', $kind . ' #' . $id); } catch (Throwable $e) {}
        header('Location: ' . $back . '?deleted=1');
        exit;
    }

    if ($action === 'toggle' && $id > 0) {
        $stmt = $db->prepare("UPDATE display_widgets SET is_enabled = IF(is_enabled=1,0,1) WHERE id=? AND kind=?");
        $stmt->bind_param('is', $id, $kind);
        $stmt->execute();
        $stmt->close();
        header('Location: ' . $back . '?saved=1');
        exit;
    }

    $data = ame_sanitize_widget_post($_POST, $kind);
    if ($kind === 'ticker' && trim((string)$data['message']) === '') {
        header('Location: ' . $back . '?err=' . rawurlencode('চলমান লেখা খালি রাখা যাবে না'));
        exit;
    }
    if ($kind === 'countdown' && trim((string)$data['title']) === '') {
        header('Location: ' . $back . '?err=' . rawurlencode('কাউন্টডাউনের শিরোনাম লাগবে'));
        exit;
    }
    $saved_id = ame_save_widget($db, $kind, $id, $data);
    try { log_activity('save', 'display_widgets', $kind . ' #' . $saved_id); } catch (Throwable $e) {}
    header('Location: ' . $back . '?saved=1&edit=' . $saved_id);
    exit;
} catch (Throwable $e) {
    error_log('display widget save failed: ' . $e->getMessage());
    header('Location: ' . $back . '?err=' . rawurlencode('সেভ হয়নি। আবার চেষ্টা করুন।'));
    exit;
}
