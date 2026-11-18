<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/lang_helper.php';
require_once __DIR__ . '/../../includes/sales/sale_common.php';
require_once __DIR__ . '/../../includes/sales/sale_admin_edit.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
if (!ame_sale_admin_is_admin()) {
    http_response_code(403);
    echo "<script>alert('শুধু এডমিন ইনভয়েস বদলাতে পারেন।'); window.location='/modules/reports/sales_report.php';</script>";
    exit;
}
require_permission($db, 'm-sales-report');
require_post();
csrf_verify();
ensure_sales_schema($db);

$is_english = function_exists('current_lang') && current_lang() === 'en';
$sale_id = (int)($_POST['sale_id'] ?? 0);
$back = '/modules/reports/invoice_edit.php?id=' . $sale_id;
$save_action = (string)($_POST['save_action'] ?? 'customer');
$intent_action = strtolower(trim((string)($_POST['intent_save_action'] ?? '')));
if ($intent_action === 'reopen_draft') {
    $save_action = 'reopen_draft';
}
$user_id = (int)$_SESSION['user_id'];
$postItems = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];
/* বাতিল চিহ্ন থাকলে কাস্টমার সেভ → void_lines; ড্রাফটে আনুন অক্ষত থাকবে */
if (
    ame_sale_admin_post_has_voided_lines($postItems)
    && $save_action !== 'reopen_draft'
    && $save_action !== 'void_lines'
) {
    $save_action = 'void_lines';
}

$fail = static function (string $msg) use ($back): void {
    $_SESSION['user_flash'] = $msg;
    $_SESSION['user_flash_ok'] = 0;
    header('Location: ' . $back);
    exit;
};

if ($sale_id <= 0) {
    $fail($is_english ? 'Invoice not found.' : 'ইনভয়েস পাওয়া যায়নি।');
}

$stmt = $db->prepare('SELECT * FROM sales WHERE id = ? LIMIT 1');
if (!$stmt) {
    $fail($is_english ? 'Invoice could not be loaded.' : 'ইনভয়েস লোড করা যায়নি।');
}
$stmt->bind_param('i', $sale_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$sale) {
    $fail($is_english ? 'Invoice not found.' : 'ইনভয়েস পাওয়া যায়নি।');
}
$status = strtolower(trim((string)($sale['status'] ?? '')));
if ($status !== '' && $status !== 'completed') {
    $fail($is_english ? 'Only a completed invoice can be edited.' : 'শুধু সম্পন্ন ইনভয়েস বদলানো যায়।');
}

$customerData = [
    'customer_id' => (int)($_POST['customer_id'] ?? 0),
    'customer_type' => (string)($_POST['customer_type'] ?? ''),
    'sale_date' => (string)($_POST['sale_date'] ?? ''),
    'sale_time' => (string)($_POST['sale_time'] ?? ''),
    'guest_customer_name' => (string)($_POST['guest_customer_name'] ?? ''),
    'guest_customer_phone' => (string)($_POST['guest_customer_phone'] ?? ''),
    'guest_customer_note' => (string)($_POST['guest_customer_note'] ?? ''),
    'vehicle_no' => (string)($_POST['vehicle_no'] ?? ''),
    'adjust_due' => !empty($_POST['adjust_due']),
];

$started = false;
try {
    $db->begin_transaction();
    $started = true;
    if ($save_action === 'reopen_draft') {
        if (!ame_sale_admin_can_reopen()) {
            throw new InvalidArgumentException(
                $is_english
                    ? 'Only Super Admin can reopen a completed invoice to draft.'
                    : 'শুধু সুপার এডমিন সম্পন্ন ইনভয়েস ড্রাফটে আনতে পারেন।'
            );
        }
        $exchangeFile = __DIR__ . '/../../includes/sales/sale_exchange.php';
        if (is_file($exchangeFile)) {
            require_once $exchangeFile;
        }
        $out = ame_sale_admin_reopen_to_draft($db, $sale, $user_id, $is_english);
        $db->commit();
        $started = false;
        $_SESSION['user_flash'] = $is_english
            ? ('Invoice ' . $out['invoice_number'] . ' is draft again. Ledger and stock were reversed — finish sale on POS.')
            : ('ইনভয়েস ' . $out['invoice_number'] . ' ড্রাফটে এসেছে। লেজার ও স্টক রিভার্স হয়েছে — POS থেকে আবার সম্পন্ন করুন।');
        $_SESSION['user_flash_ok'] = 1;
        header(
            'Location: /modules/sales/pos.php?draft='
            . (int)$out['sale_id']
            . '&open_drafts=1&reopened=1'
        );
        exit;
    }
    if ($save_action === 'void_lines') {
        if (!is_file(__DIR__ . '/../../includes/sales/sale_exchange.php')) {
            throw new RuntimeException('Exchange module missing.');
        }
        require_once __DIR__ . '/../../includes/sales/sale_exchange.php';
        $payload = ame_sale_admin_rewrite_payload_line_void(
            $db,
            $sale,
            $customerData,
            $postItems,
            $is_english
        );
        $out = ame_sale_admin_rewrite($db, $sale, $payload, $user_id, $is_english);
        $db->commit();
        $started = false;
        $_SESSION['user_flash'] = $is_english
            ? (
                'Invoice ' . $out['invoice_number']
                . ' updated. Cancelled lines removed; new bill ৳'
                . number_format((float)$out['net_payable'], 2)
                . ', due ৳'
                . number_format((float)$out['due_amount'], 2)
                . '.'
            )
            : (
                'ইনভয়েস ' . $out['invoice_number']
                . ' আপডেট হয়েছে। বাতিল পণ্য বাদ দেওয়া হয়েছে; নতুন বিল ৳'
                . number_format((float)$out['net_payable'], 2)
                . ', বাকি ৳'
                . number_format((float)$out['due_amount'], 2)
                . '।'
            );
        $_SESSION['user_flash_ok'] = 1;
        header('Location: /modules/sales/invoice.php?id=' . (int)$out['sale_id'] . '&edited=1');
        exit;
    }

    if (ame_sale_admin_customer_identity_changed($sale, $customerData)) {
        if (!is_file(__DIR__ . '/../../includes/sales/sale_exchange.php')) {
            throw new RuntimeException('Exchange module missing.');
        }
        require_once __DIR__ . '/../../includes/sales/sale_exchange.php';
        $payload = ame_sale_admin_rewrite_payload_from_sale($db, $sale, $customerData, $is_english);
        $out = ame_sale_admin_rewrite($db, $sale, $payload, $user_id, $is_english);
        $db->commit();
        $started = false;
        $_SESSION['user_flash'] = $is_english
            ? (
                'Invoice ' . $out['invoice_number']
                . ' customer changed. Old ledger reversed; new customer billed. Net ৳'
                . number_format((float)$out['net_payable'], 2)
                . ', due ৳'
                . number_format((float)$out['due_amount'], 2)
                . '.'
            )
            : (
                'ইনভয়েস ' . $out['invoice_number']
                . ' কাস্টমার বদলেছে। পুরনো লেজার রিভার্স, নতুন ক্রেতায় বিল। নেট ৳'
                . number_format((float)$out['net_payable'], 2)
                . ', বাকি ৳'
                . number_format((float)$out['due_amount'], 2)
                . '।'
            );
        $_SESSION['user_flash_ok'] = 1;
        header('Location: /modules/reports/sales_report.php?q=' . rawurlencode((string)$out['invoice_number']));
        exit;
    }

    $out = ame_sale_admin_reassign($db, $sale, $customerData, $is_english);
    $db->commit();
    $started = false;
    $_SESSION['user_flash'] = $is_english
        ? ('Invoice ' . $out['invoice_number'] . ' customer details updated (same account; amounts unchanged).')
        : ('ইনভয়েস ' . $out['invoice_number'] . ' ক্রেতার তথ্য আপডেট (একই অ্যাকাউন্ট; টাকা অপরিবর্তিত)।');
    $_SESSION['user_flash_ok'] = 1;
    header('Location: /modules/reports/sales_report.php?q=' . rawurlencode((string)$out['invoice_number']));
    exit;
} catch (InvalidArgumentException $e) {
    if ($started) {
        $db->rollback();
    }
    $fail($e->getMessage());
} catch (Throwable $e) {
    if ($started) {
        $db->rollback();
    }
    error_log('Invoice edit failed: ' . $e->getMessage());
    $fail($is_english ? 'The invoice could not be updated. Check Error Monitor.' : 'ইনভয়েস আপডেট করা যায়নি। Error Monitor দেখুন।');
}