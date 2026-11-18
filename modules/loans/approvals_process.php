<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
admin_only();
csrf_verify();
ensure_loan_tables($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: approvals.php");
    exit();
}

$application_id = (int)($_POST['application_id'] ?? 0);
$action = $_POST['action'] ?? '';
$note = clean_input($_POST['decision_note'] ?? '');
$decided_by = $_SESSION['user_id'] ?? null;

if ($application_id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    header("Location: approvals.php?msg=" . urlencode('অবৈধ অনুরোধ'));
    exit();
}

$stmt = $db->prepare("SELECT * FROM loan_applications WHERE id = ? AND status = 'pending'");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    header("Location: approvals.php?msg=" . urlencode('আবেদনটি খুঁজে পাওয়া যায়নি বা ইতিমধ্যে সিদ্ধান্ত হয়ে গেছে'));
    exit();
}

$new_status = $action === 'approve' ? 'approved' : 'rejected';

$upd = $db->prepare("UPDATE loan_applications SET status = ?, decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ?");
$upd->bind_param("sisi", $new_status, $decided_by, $note, $application_id);
$upd->execute();

log_activity('loan_application_' . $new_status, 'loans', 'আবেদন #' . $application_id . ' ' . ($action === 'approve' ? 'অনুমোদন' : 'প্রত্যাখ্যান') . ' করা হয়েছে');

$msg = $action === 'approve' ? 'আবেদন অনুমোদিত হয়েছে — এখন ছাড় (Disburse) করা যাবে' : 'আবেদন প্রত্যাখ্যান করা হয়েছে';
header("Location: approvals.php?msg=" . urlencode($msg));
exit();
