<?php
/** স্পেক সেকশন ১৭ — জেনেরিক প্যারামিটারাইজড রসিদ পেজ (disbursement|payment|savings_deposit|savings_withdrawal) */
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

$type = clean_input($_GET['type'] ?? '');
$id = (int)($_GET['id'] ?? 0);
$data = null;
$owner = null;
$title_bn = '';
$title_en = '';

if ($type === 'disbursement') {
    $stmt = $db->prepare("SELECT d.*, so.owner_name, so.shop_name, so.mobile FROM owner_loan_disbursements d JOIN shop_owners so ON so.id = d.owner_id WHERE d.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $title_bn = 'ঋণ বিতরণ রসিদ'; $title_en = 'Loan Disbursement Receipt';
} elseif ($type === 'payment') {
    $stmt = $db->prepare("SELECT p.*, so.owner_name, so.shop_name, so.mobile FROM owner_loan_payments p JOIN shop_owners so ON so.id = p.owner_id WHERE p.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $title_bn = 'ঋণ কিস্তি পেমেন্ট রসিদ'; $title_en = 'Loan Payment Receipt';
} elseif ($type === 'savings_deposit') {
    $stmt = $db->prepare("SELECT t.*, so.owner_name, so.shop_name, so.mobile FROM owner_savings_transactions t JOIN shop_owners so ON so.id = t.owner_id WHERE t.id = ? AND t.txn_type = 'deposit'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $title_bn = 'সঞ্চয় জমা রসিদ'; $title_en = 'Savings Deposit Receipt';
} elseif ($type === 'savings_withdrawal') {
    $stmt = $db->prepare("SELECT w.*, so.owner_name, so.shop_name, so.mobile FROM owner_savings_withdrawals w JOIN shop_owners so ON so.id = w.owner_id WHERE w.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $title_bn = 'সঞ্চয় উত্তোলন রসিদ'; $title_en = 'Savings Withdrawal Receipt';
}

if (!$data) {
    die('❌ ' . ame_l('রসিদ পাওয়া যায়নি।', 'Receipt not found.'));
}

$collector_name = '';
if (!empty($data['collected_by'])) {
    $u = $db->prepare("SELECT username FROM users WHERE id = ?");
    $u->bind_param('i', $data['collected_by']);
    $u->execute();
    $ur = $u->get_result()->fetch_assoc();
    $collector_name = $ur['username'] ?? '';
    $u->close();
}

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'oloan-receipt-' . $type . '-' . $id,
    'title' => $shop . ' · ' . ame_l($title_bn, $title_en),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l($title_bn, $title_en),
        (string)($data['owner_name'] ?? ''),
    ])),
    'shop' => $shop,
];

include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-receipt-wrap { padding: 15px; max-width: 560px; margin: 0 auto; }
.oln-receipt-card { background: var(--brand-white); border:1px solid var(--brand-border); border-radius:12px; padding:22px; }
.oln-receipt-card h3 { text-align:center; color: var(--brand-navy); margin-top:0; }
.oln-receipt-row { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px dashed var(--brand-border); font-size:14px; }
.oln-receipt-row b { color: var(--brand-navy); }
.oln-receipt-amount { text-align:center; font-size:24px; font-weight:800; color: var(--brand-success); margin:16px 0; }
.oln-sign-line { margin-top:40px; display:flex; justify-content:space-between; }
.oln-sign-line div { border-top:1px solid var(--brand-black); width:150px; text-align:center; padding-top:6px; font-size:12px; color: var(--brand-muted); }
.oln-receipt-wrap .ame-wait-spin i {
    width: 28px !important; height: 28px !important; border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="oln-receipt-wrap">
    <?php include '../../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="oln-receipt-card">
        <h3><?php echo esc_html($shop); ?><br><small><?php echo ame_l($title_bn, $title_en); ?></small></h3>
        <div class="oln-receipt-row"><span><?php echo ame_l('রসিদ নং', 'Receipt No.'); ?></span><b>#<?php echo (int)$id; ?></b></div>
        <div class="oln-receipt-row"><span><?php echo ame_l('তারিখ', 'Date'); ?></span><b><?php echo esc_html($data['disbursement_date'] ?? $data['payment_date'] ?? $data['txn_date'] ?? $data['withdrawal_date'] ?? ''); ?></b></div>
        <div class="oln-receipt-row"><span><?php echo ame_l('মালিক', 'Owner'); ?></span><b><?php echo esc_html($data['owner_name']); ?></b></div>
        <div class="oln-receipt-row"><span><?php echo ame_l('দোকান', 'Shop'); ?></span><b><?php echo esc_html($data['shop_name']); ?></b></div>
        <?php if (!empty($data['loan_id'])): ?>
        <div class="oln-receipt-row"><span><?php echo ame_l('ঋণ নং', 'Loan No.'); ?></span><b>#<?php echo (int)$data['loan_id']; ?></b></div>
        <?php endif; ?>
        <?php if ($type === 'disbursement'): ?>
        <div class="oln-receipt-row"><span><?php echo ame_l('বিতরণকৃত', 'Disbursed'); ?></span><b>৳<?php echo number_format((float)$data['disbursed_amount'], 2); ?></b></div>
        <div class="oln-receipt-row"><span><?php echo ame_l('চার্জ', 'Fees'); ?></span><b>৳<?php echo number_format((float)$data['processing_fee'] + (float)$data['other_charges'], 2); ?></b></div>
        <div class="oln-receipt-amount">৳<?php echo number_format((float)$data['net_amount_received'], 2); ?></div>
        <?php elseif ($type === 'payment'): ?>
        <div class="oln-receipt-row"><span><?php echo ame_l('আসল', 'Principal'); ?></span><b>৳<?php echo number_format((float)$data['principal_paid'], 2); ?></b></div>
        <div class="oln-receipt-row"><span><?php echo ame_l('সুদ', 'Interest'); ?></span><b>৳<?php echo number_format((float)$data['interest_paid'], 2); ?></b></div>
        <div class="oln-receipt-row"><span><?php echo ame_l('জরিমানা', 'Penalty'); ?></span><b>৳<?php echo number_format((float)$data['penalty_charge'], 2); ?></b></div>
        <div class="oln-receipt-amount">৳<?php echo number_format((float)$data['total_payment'], 2); ?></div>
        <?php else: ?>
        <div class="oln-receipt-amount">৳<?php echo number_format((float)$data['amount'], 2); ?></div>
        <?php endif; ?>
        <div class="oln-receipt-row"><span><?php echo ame_l('পদ্ধতি', 'Method'); ?></span><b><?php echo esc_html($data['payment_method'] ?? ''); ?></b></div>
        <div class="oln-receipt-row"><span><?php echo ame_l('রেফারেন্স', 'Reference'); ?></span><b><?php echo esc_html($data['reference_no'] ?? $data['bank_ref_no'] ?? ''); ?></b></div>
        <div class="oln-receipt-row"><span><?php echo ame_l('আদায়কারী', 'Collector'); ?></span><b><?php echo esc_html($collector_name); ?></b></div>
        <div class="oln-sign-line">
            <div><?php echo ame_l('মালিকের স্বাক্ষর', "Owner's signature"); ?></div>
            <div><?php echo ame_l('আদায়কারীর স্বাক্ষর', "Collector's signature"); ?></div>
        </div>
    </div>
    </div>
</div>
<?php include '../../../partials/footer.php'; ?>
