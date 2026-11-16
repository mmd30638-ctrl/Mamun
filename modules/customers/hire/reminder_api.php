<?php
require_once __DIR__ . '/_boot.php';

header('Content-Type: application/json; charset=utf-8');

function hire_due_api_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hire_due_api_fail('Invalid request method');
}

$session_token = (string) ($_SESSION['csrf_token'] ?? '');
$posted_token = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($session_token === '' || $posted_token === '' || !hash_equals($session_token, $posted_token)) {
    hire_due_api_fail('নিরাপত্তা যাচাই ব্যর্থ হয়েছে।', 403);
}

$do = trim((string) ($_POST['do'] ?? 'reminder'));
$cid = (int) ($_POST['contract_id'] ?? 0);
if ($cid <= 0) {
    hire_due_api_fail('চুক্তি সিলেক্ট করা নেই');
}

try {
    if ($do === 'reminder' || $do === 'term') {
        hire_require($db, 'edit');
        $date = trim((string) ($_POST['follow_date'] ?? $_POST['next_due_date'] ?? ''));
        $amountRaw = trim((string) ($_POST['follow_amount'] ?? $_POST['next_due_amount'] ?? ''));
        $note = trim((string) ($_POST['follow_note'] ?? $_POST['due_note'] ?? ''));
        $amount = ($amountRaw !== '' && is_numeric($amountRaw)) ? (float) $amountRaw : null;
        $db->begin_transaction();
        $wantTerm = isset($_POST['term_months']) || isset($_POST['term_days']) || isset($_POST['rent_rate']);
        if ($wantTerm && function_exists('hire_save_term')) {
            $c = hire_contract($db, $cid);
            if (!$c) {
                throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
            }
            hire_save_term($db, $cid, [
                'start_date' => trim((string) ($_POST['start_date'] ?? $c['start_date'] ?? date('Y-m-d'))),
                'term_months' => (int) ($_POST['term_months'] ?? $c['term_months'] ?? 0),
                'term_days' => (int) ($_POST['term_days'] ?? $c['term_days'] ?? 0),
                'rent_rate' => (float) ($_POST['rent_rate'] ?? $c['rent_rate'] ?? 0),
            ], 'রিমাইন্ডার থেকে মেয়াদ');
        }
        hire_due_save_follow($db, $cid, $date, $amount, $note);
        $db->commit();
        $fresh = hire_contract($db, $cid);
        $snap = $fresh && function_exists('hire_due_snapshot') ? hire_due_snapshot($db, $fresh) : ['due' => 0, 'unpaid_days' => 0];
        echo json_encode([
            'success' => true,
            'due' => round((float) ($snap['due'] ?? 0), 2),
            'unpaid_days' => (int) ($snap['unpaid_days'] ?? 0),
            'follow_date' => $date,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($do === 'pay') {
        hire_require($db, 'edit');
        $db->begin_transaction();
        $pid = hire_pay(
            $db,
            $cid,
            (float) ($_POST['amount'] ?? 0),
            (int) ($_POST['account_id'] ?? 0),
            strtoupper(trim((string) ($_POST['payment_method'] ?? 'CASH'))),
            trim((string) ($_POST['pay_date'] ?? date('Y-m-d'))),
            trim((string) ($_POST['reference_no'] ?? '')),
            trim((string) ($_POST['note'] ?? 'রিমাইন্ডার থেকে পেমেন্ট'))
        );
        $followDate = trim((string) ($_POST['follow_date'] ?? ''));
        if ($followDate !== '') {
            $amountRaw = trim((string) ($_POST['follow_amount'] ?? ''));
            $amount = ($amountRaw !== '' && is_numeric($amountRaw)) ? (float) $amountRaw : null;
            $note = trim((string) ($_POST['follow_note'] ?? ''));
            hire_due_save_follow($db, $cid, $followDate, $amount, $note);
        }
        $db->commit();
        $fresh = hire_contract($db, $cid);
        $snap = $fresh && function_exists('hire_due_snapshot') ? hire_due_snapshot($db, $fresh) : ['due' => 0, 'unpaid_days' => 0];
        echo json_encode([
            'success' => true,
            'pay_id' => $pid,
            'due' => round((float) ($snap['due'] ?? 0), 2),
            'unpaid_days' => (int) ($snap['unpaid_days'] ?? 0),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    hire_due_api_fail('অজানা কাজ।');
} catch (Throwable $e) {
    if ($db instanceof mysqli) {
        @$db->rollback();
    }
    error_log('hire reminder_api: ' . $e->getMessage());
    hire_due_api_fail($e->getMessage());
}
