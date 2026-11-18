<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);
ame_ensure_due_watch_columns($db);

header('Content-Type: application/json; charset=utf-8');

function ensure_next_due_amount_column($db) {
    $check = $db->query("SHOW COLUMNS FROM customers LIKE 'next_due_amount'");
    if ($check && $check->num_rows === 0) {
        $db->query("ALTER TABLE customers ADD COLUMN next_due_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER next_due_date");
    }
}
ensure_next_due_amount_column($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$session_token = (string)($_SESSION['csrf_token'] ?? '');
$posted_token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($session_token === '' || $posted_token === '' || !hash_equals($session_token, $posted_token)) {
    echo json_encode(['success' => false, 'message' => 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$customer_id = (int)($_POST['customer_id'] ?? 0);
$next_due_date_raw = trim($_POST['next_due_date'] ?? '');
$next_due_amount_raw = trim($_POST['next_due_amount'] ?? '');
$due_note_raw = trim($_POST['due_note'] ?? '');

if ($customer_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'কাস্টমার সিলেক্ট করা নেই']);
    exit;
}

if ($next_due_date_raw === '') {
    echo json_encode(['success' => false, 'message' => 'তারিখ দেওয়া আবশ্যক']);
    exit;
}

$dt = DateTime::createFromFormat('Y-m-d', $next_due_date_raw);
if (!$dt || $dt->format('Y-m-d') !== $next_due_date_raw) {
    echo json_encode(['success' => false, 'message' => 'সঠিক তারিখ দিন (YYYY-MM-DD)।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$next_due_date = $next_due_date_raw;
$next_due_amount = ($next_due_amount_raw !== '' && is_numeric($next_due_amount_raw)) ? (float)$next_due_amount_raw : null;
$due_note = $due_note_raw;

$chk = $db->prepare("SELECT id FROM customers WHERE id = ?");
$chk->bind_param("i", $customer_id);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'কাস্টমার খুঁজে পাওয়া যায়নি']);
    exit;
}

try {
    ame_ensure_due_watch_columns($db);
    if ($next_due_amount === null) {
        $stmt = $db->prepare('UPDATE customers SET next_due_date = ?, next_due_amount = NULL, due_note = ?, due_notify_suppressed = 0, due_watch = 0, due_watch_on = NULL WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException($db->error);
        }
        $stmt->bind_param('ssi', $next_due_date, $due_note, $customer_id);
    } else {
        $stmt = $db->prepare('UPDATE customers SET next_due_date = ?, next_due_amount = ?, due_note = ?, due_notify_suppressed = 0, due_watch = 0, due_watch_on = NULL WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException($db->error);
        }
        $stmt->bind_param('sdsi', $next_due_date, $next_due_amount, $due_note, $customer_id);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error ?: $db->error);
    }
    $stmt->close();
    $saved = $db->prepare('SELECT next_due_date FROM customers WHERE id = ? LIMIT 1');
    $kept = $next_due_date;
    if ($saved) {
        $saved->bind_param('i', $customer_id);
        $saved->execute();
        $row = $saved->get_result()->fetch_assoc();
        $saved->close();
        if ($row) {
            $kept = function_exists('ame_due_date_value')
                ? ame_due_date_value($row['next_due_date'] ?? '')
                : trim((string) ($row['next_due_date'] ?? ''));
            if ($kept === '') {
                $kept = $next_due_date;
            }
        }
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['ame_due_badge']);
    }
    $today = date('Y-m-d');
    echo json_encode([
        'success' => true,
        'next_due_date' => $kept,
        'hidden' => $kept > $today,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('set_reminder failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'সার্ভার এরর, পরে আবার চেষ্টা করুন।'], JSON_UNESCAPED_UNICODE);
}
