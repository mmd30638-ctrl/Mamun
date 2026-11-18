<?php
/*
 * ⭐ এই ফাইলটা modules/accounts/-এ রাখা, কিন্তু ব্যবহার হবে হোম ড্যাশবোর্ডে include করার জন্য।
 * হোম ড্যাশবোর্ড ফাইলে (যেমন public_html/index.php) এই লাইনটা বসিয়ে দিন:
 *   <?php include __DIR__ . '/modules/accounts/dashboard_widget.php'; ?>
 * (পাথ আপনার ইনডেক্স ফাইলের লোকেশন অনুযায়ী সামান্য বদলাতে পারে)
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/account_functions.php';

$res = $db->query("SELECT SUM(balance) AS total, COUNT(*) AS cnt FROM accounts WHERE status = 'active'");
$row = $res->fetch_assoc();
$total_balance = (float)($row['total'] ?? 0);
$account_count = (int)($row['cnt'] ?? 0);

$low_res = $db->query("SELECT COUNT(*) AS cnt FROM accounts WHERE status = 'active' AND low_balance_threshold IS NOT NULL AND balance < low_balance_threshold");
$low_row = $low_res->fetch_assoc();
$low_count = (int)($low_row['cnt'] ?? 0);
?>
<a href="modules/accounts/list.php" style="text-decoration:none; display:block; background:var(--dark-blue); color:#fff; border-radius:10px; padding:16px; margin-bottom:12px;">
    <div style="font-size:12px; opacity:0.85;">💼 মোট ক্যাশ ও ব্যাংক ব্যালেন্স (<?php echo $account_count; ?>টি অ্যাকাউন্ট)</div>
    <div style="font-size:24px; font-weight:800;"><?php echo ame_balance_label($total_balance); ?></div>
    <?php if ($low_count > 0): ?>
    <div style="font-size:11px; margin-top:4px; color:#FFCDD2;">⚠️ <?php echo $low_count; ?>টি অ্যাকাউন্টে কম ব্যালেন্স সতর্কতা</div>
    <?php endif; ?>
</a>
