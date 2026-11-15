<?php
require_once '../../includes/config.php';
require_once '../../includes/purchase_functions.php';
require_once '../../includes/purchase_draft.php';

header('Content-Type: application/json; charset=UTF-8');

$respond = static function ($status, $message, $extra = []) {
    echo json_encode(
        array_merge(['status' => $status, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit();
};

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    $respond('error', 'লগইন করুন।');
}

if (!ame_purchase_can_access($db)) {
    http_response_code(403);
    $respond('error', 'অনুমতি নেই।');
}

ensure_purchase_schema($db);
ame_purchase_draft_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $respond('error', 'অনুমোদিত অনুরোধ নয়।');
}

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    $respond('error', 'ড্রাফটের তথ্য সঠিক নয়।');
}

$csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrf === '') {
    $csrf = (string)($data['csrf_token'] ?? '');
}
if ($csrf === '' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    $respond('error', 'নিরাপত্তা যাচাই ব্যর্থ। পেজ রিফ্রেশ করুন।');
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = strtolower((string)($_SESSION['role'] ?? '')) === 'admin';
$action = (string)($data['action'] ?? 'save');

try {
    if ($action === 'cancel') {
        $draft_id = (int)($data['draft_id'] ?? 0);
        $draft = ame_purchase_draft_find($db, $draft_id, $user_id, $is_admin, true);
        if (!$draft) {
            http_response_code(404);
            $respond('error', 'ড্রাফট পাওয়া যায়নি।');
        }
        $id = (int)$draft['id'];
        $stmt = $db->prepare("UPDATE purchase_drafts SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status = 'draft'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        log_activity('cancel', 'purchase_draft', 'Draft ID: ' . $id);
        $respond('success', 'ড্রাফট বাতিল হয়েছে।', ['draft_id' => $id]);
    }

    if ($action !== 'save') {
        http_response_code(400);
        $respond('error', 'অজানা অ্যাকশন।');
    }

    [$items, $items_total] = ame_purchase_draft_normalize_items($data['cart'] ?? $data['items'] ?? []);
    if ($items === []) {
        http_response_code(400);
        $respond('error', 'ড্রাফট সেভ করতে অন্তত একটি পণ্য যোগ করুন।');
    }

    [$expenses, $expense_total] = ame_purchase_draft_normalize_expenses($data['expenses'] ?? []);
    $grand_total = round($items_total + $expense_total, 2);

    $supplier_id = (int)($data['supplier_id'] ?? 0);
    $memo_no = substr(trim((string)($data['memo'] ?? $data['memo_no'] ?? '')), 0, 50);
    $notes = substr(trim((string)($data['notes'] ?? '')), 0, 500);
    $paid_amount = round((float)($data['paid_amount'] ?? 0), 2);
    if ($paid_amount < 0) {
        $paid_amount = 0.0;
    }
    $account_id = (int)($data['account_id'] ?? 0);
    $purchase_date = trim((string)($data['date'] ?? $data['purchase_date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchase_date)) {
        $purchase_date = date('Y-m-d');
    }
    $bill_due = round(max(0, $grand_total - $paid_amount), 2);
    $payment_type = $bill_due > 0.009 ? 'credit' : 'cash';
    $items_json = json_encode($items, JSON_UNESCAPED_UNICODE);
    $expenses_json = json_encode($expenses, JSON_UNESCAPED_UNICODE);
    $store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
    $draft_id = (int)($data['draft_id'] ?? 0);

    $db->begin_transaction();

    if ($draft_id > 0) {
        $existing = ame_purchase_draft_find($db, $draft_id, $user_id, $is_admin, true);
        if (!$existing) {
            $db->rollback();
            http_response_code(404);
            $respond('error', 'ড্রাফট পাওয়া যায়নি অথবা সম্পাদনার অনুমতি নেই।');
        }
        $stmt = $db->prepare(
            "UPDATE purchase_drafts
             SET supplier_id = ?, memo_no = ?, purchase_date = ?, notes = ?,
                 items_json = ?, expenses_json = ?, paid_amount = ?, account_id = ?,
                 payment_type = ?, grand_total = ?, store_id = ?, updated_at = NOW()
             WHERE id = ? AND status = 'draft'"
        );
        $stmt->bind_param(
            'isssssdisdii',
            $supplier_id,
            $memo_no,
            $purchase_date,
            $notes,
            $items_json,
            $expenses_json,
            $paid_amount,
            $account_id,
            $payment_type,
            $grand_total,
            $store_id,
            $draft_id
        );
        $stmt->execute();
        $stmt->close();
        $draft_number = (string)$existing['draft_number'];
    } else {
        $placeholder = 'PDRF-NEW';
        $stmt = $db->prepare(
            "INSERT INTO purchase_drafts
                (draft_number, supplier_id, memo_no, purchase_date, notes, items_json, expenses_json,
                 paid_amount, account_id, payment_type, grand_total, created_by, store_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')"
        );
        $stmt->bind_param(
            'sisssssdisdii',
            $placeholder,
            $supplier_id,
            $memo_no,
            $purchase_date,
            $notes,
            $items_json,
            $expenses_json,
            $paid_amount,
            $account_id,
            $payment_type,
            $grand_total,
            $user_id,
            $store_id
        );
        $stmt->execute();
        $draft_id = (int)$stmt->insert_id;
        $stmt->close();
        if ($draft_id <= 0) {
            $db->rollback();
            $respond('error', 'ড্রাফট সেভ হয়নি।');
        }
        $draft_number = 'PDRF-' . str_pad((string)$draft_id, 4, '0', STR_PAD_LEFT);
        $upd = $db->prepare('UPDATE purchase_drafts SET draft_number = ? WHERE id = ?');
        $upd->bind_param('si', $draft_number, $draft_id);
        $upd->execute();
        $upd->close();
    }

    $db->commit();
    log_activity('save', 'purchase_draft', $draft_number . ' #' . $draft_id);
    $respond('success', 'ড্রাফট সফলভাবে সংরক্ষণ হয়েছে।', [
        'draft_id' => $draft_id,
        'draft_number' => $draft_number,
        'grand_total' => $grand_total,
    ]);
} catch (Throwable $e) {
    if ($db instanceof mysqli) {
        @$db->rollback();
    }
    error_log('Purchase draft failed: ' . $e->getMessage());
    http_response_code(500);
    $respond('error', 'হোল্ড মেমো সেভ হয়নি। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।');
}
