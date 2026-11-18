<?php
require_once '../../includes/config.php';
admin_only();
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: recovery.php");
    exit();
}

$loan_id = (int)($_POST['loan_id'] ?? 0);
$action_type = $_POST['action_type'] ?? 'other';
$allowed_types = ['call', 'visit', 'notice', 'legal', 'other'];
if (!in_array($action_type, $allowed_types, true)) { $action_type = 'other'; }
$action_date = $_POST['action_date'] ?? date('Y-m-d');
$notes = clean_input($_POST['notes'] ?? '');
$next_follow_up_date = $_POST['next_follow_up_date'] ?? null;
if ($next_follow_up_date === '') { $next_follow_up_date = null; }
$done_by = $_SESSION['user_id'] ?? null;

if ($loan_id <= 0) {
    header("Location: recovery.php?error=" . urlencode('অবৈধ লোন'));
    exit();
}

$stmt = $db->prepare("INSERT INTO loan_recovery_actions (loan_id, action_type, action_date, notes, done_by, next_follow_up_date) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isssis", $loan_id, $action_type, $action_date, $notes, $done_by, $next_follow_up_date);
$stmt->execute();

log_activity('loan_recovery_action', 'loans', 'রিকভারি অ্যাকশন যোগ (' . $action_type . ') — লোন #' . $loan_id);

header("Location: recovery.php?loan_id=" . $loan_id . "&saved=1");
exit();
