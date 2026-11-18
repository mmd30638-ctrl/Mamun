<?php
require_once __DIR__ . '/secret_store.php';
if (is_file(__DIR__ . '/sms_sim.php')) {
    require_once __DIR__ . '/sms_sim.php';
}

// SMS সাকসেস/ফেইলিওর লগ ট্র্যাকিং

function ensure_sms_log_schema($db) {
    $db->query("CREATE TABLE IF NOT EXISTS sms_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient VARCHAR(20) NOT NULL,
        message TEXT,
        purpose VARCHAR(100) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'sent',
        response_note VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_recipient (recipient),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (function_exists('ensure_sms_sim_schema')) {
        ensure_sms_sim_schema($db);
    }
}

// ⭐ যেকোনো SMS পাঠানোর পর এই ফাংশনটা কল করলেই লগ সেভ হবে
function log_sms_attempt($db, $recipient, $message, $purpose, $status, $response_note = '') {
    ensure_sms_log_schema($db);
    $stmt = $db->prepare("INSERT INTO sms_log (recipient, message, purpose, status, response_note) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $recipient, $message, $purpose, $status, $response_note);
    $stmt->execute();
}
?>
