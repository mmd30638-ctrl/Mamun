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
    if ($save_action === 'void_lines') {
        if (!is_file(__DIR__ . '/../../includes/sales/sale_exchange.php')) {
            throw new RuntimeException('Exchange module missing.');
        }
        require_once __DIR__ . '/../../includes/sales/sale_exchange.php';
        $payload = ame_sale_admin_rewrite_payload_line_void(
            $db,
            $sale,
            $customerData,
            is_array($_POST['items'] ?? null) ? $_POST['items'] : [],
            $is_english
        );
        $user_id = (int)$_SESSION['user_id'];
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
        header('Location: /modules/reports/sales_report.php?q=' . rawurlencode((string)$out['invoice_number']));
        exit;
    }

    $out = ame_sale_admin_reassign($db, $sale, $customerData, $is_english);
    $db->commit();
    $started = false;
    $_SESSION['user_flash'] = $is_english
        ? ('Invoice ' . $out['invoice_number'] . ' customer updated. Amounts were not changed.')
        : ('ইনভয়েস ' . $out['invoice_number'] . ' এর কাস্টমার আপডেট হয়েছে। কোনো টাকা বদলেনি।');
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