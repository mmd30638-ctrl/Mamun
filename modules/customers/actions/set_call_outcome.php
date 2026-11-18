<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);

header('Content-Type: application/json; charset=utf-8');

function ensure_call_log_table($db) {
    $db->query("CREATE TABLE IF NOT EXISTS customer_call_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        log_date DATE NOT NULL,
        outcome VARCHAR(20) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_customer_date (customer_id, log_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
ensure_call_log_table($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$session_token = (string)($_SESSION['csrf_token'] ?? '');
$posted_token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($session_token === '' || $posted_token === '' || !hash_equals($session_token, $posted_token)) {
    echo json_encode(['success' => false, 'message' => 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$customer_id = (int)($_POST['customer_id'] ?? 0);
$outcome = trim($_POST['outcome'] ?? '');
$allowed = ['talked', 'no_answer', 'phone_off'];

if ($customer_id <= 0 || !in_array($outcome, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'ভুল ইনপুট']);
    exit;
}

$today = date('Y-m-d');

try {
    $stmt = $db->prepare("INSERT INTO customer_call_log (customer_id, log_date, outcome) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE outcome = VALUES(outcome)");
    $stmt->bind_param("iss", $customer_id, $today, $outcome);
    $stmt->execute();
    echo json_encode(['success' => true]);
} catch (mysqli_sql_exception $e) {
    error_log('set_call_outcome failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'সার্ভার এরর, পরে আবার চেষ্টা করুন।']);
}
