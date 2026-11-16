<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/account_functions.php';
require_once __DIR__ . '/customer_schema.php';
ensure_customer_tables($db);

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare(
    'SELECT p.*, c.name AS customer_name, c.customer_code, c.phone
     FROM customer_payments p
     JOIN customers c ON c.id = p.customer_id
     WHERE p.id = ?'
);
$row = null;
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$row) {
    header('Location: actions/payments-list.php');
    exit;
}

$accounts = ame_active_accounts($db);
$error = (string)($_SESSION['cpay_edit_error'] ?? '');
unset($_SESSION['cpay_edit_error']);
$back = (string)($_GET['back'] ?? '');
if ($back !== '' && !preg_match('/^[A-Za-z0-9_%=&.\-]*$/', $back)) {
    $back = '';
}
$methods = ['CASH' => 'নগদ', 'BKASH' => 'বিকাশ', 'NAGAD' => 'নগদ (মোবাইল)', 'BANK' => 'ব্যাংক', 'CHEQUE' => 'চেক'];
$ame_body_class = 'ame-pay-list-page';
include __DIR__ . '/../../partials/header.php';
$ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.355';
?>
<link rel="stylesheet" href="/assets/css/customer-pay-list.css?v=<?php echo htmlspecialchars((string)$ver, ENT_QUOTES, 'UTF-8'); ?>">
<div class="cpl-edit">
    <div class="cpl-head">
        <div>
            <h1>পেমেন্ট এডিট</h1>
            <p><?php echo htmlspecialchars((string)$row['customer_name'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string)$row['customer_code'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <a class="cpl-clear" href="actions/payments-list.php<?php echo $back !== '' ? '?' . htmlspecialchars($back, ENT_QUOTES, 'UTF-8') : ''; ?>">তালিকায় ফিরুন</a>
    </div>
    <?php if ($error !== ''): ?><div class="cpl-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <form class="cpl-form" method="post" action="actions/process_payment_edit.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
        <input type="hidden" name="back" value="<?php echo htmlspecialchars($back, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="idempotency_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="two">
            <label>পরিশোধিত টাকা
                <input type="number" name="amount" min="0" step="0.01" required value="<?php echo htmlspecialchars(number_format((float)$row['amount'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>ডিসকাউন্ট
                <input type="number" name="discount" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format((float)($row['discount'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="two">
            <label>তারিখ
                <input type="date" name="payment_date" required value="<?php echo htmlspecialchars((string)$row['payment_date'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>মাধ্যম
                <select name="method">
                    <?php foreach ($methods as $key => $label): ?>
                        <option value="<?php echo $key; ?>"<?php echo (string)$row['method'] === $key ? ' selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>জমা অ্যাকাউন্ট
            <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id">
                <option value="0">অ্যাকাউন্ট নেই</option>
                <?php foreach ($accounts as $account): ?>
                    <option value="<?php echo (int)$account['id']; ?>"<?php echo (int)($row['account_id'] ?? 0) === (int)$account['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars(ame_account_option_label($account), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>রেফারেন্স
            <input type="text" name="reference_no" maxlength="50" value="<?php echo htmlspecialchars((string)($row['reference_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>নোট
            <textarea name="note" maxlength="255" rows="2"><?php echo htmlspecialchars((string)($row['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>
        <button class="cpl-save" type="submit">সংশোধন সেভ করুন</button>
    </form>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
