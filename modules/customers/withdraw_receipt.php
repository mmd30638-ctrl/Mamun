<?php
/**
 * জমা উত্তোলন রসিদ — বিক্রয় ডিজিটাল মেমোর (invoice_digital.php) মতোই সিস্টেম:
 * html2canvas দিয়ে পুরো কার্ডটা ছবি (PNG) হিসেবে ডাউনলোড/শেয়ার হয় — আসল PDF না,
 * কিন্তু বোতামের নাম/আচরণ সেই ইনভয়েস মেমোর মতোই। সাথে সাবেক জমা/বকেয়া − এই
 * উত্তোলন = এখনকার জমা/বকেয়া — এই ফর্মুলা ব্লকও থাকবে (সেল মেমোর "সাবেক + এই
 * ইনভয়েস − পরিশোধ = বর্তমান" প্যাটার্ন অনুসরণ করে)।
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/customer_schema.php';
require_once __DIR__ . '/../../includes/memo_functions.php';
ensure_customer_tables($db);

$wid = (int) ($_GET['id'] ?? 0);
$st = $db->prepare(
    'SELECT w.*, c.name, c.customer_code, c.phone, c.receivable_balance, a.account_name
     FROM customer_withdrawals w
     LEFT JOIN customers c ON c.id = w.customer_id
     LEFT JOIN accounts a ON a.id = w.account_id
     WHERE w.id = ? LIMIT 1'
);
$row = null;
if ($st) {
    $st->bind_param('i', $wid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
}

$shop_name = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.67', ENT_QUOTES, 'UTF-8');

if ($row) {
    $amount = round((float) $row['amount'], 2);
    $post_balance = round((float) ($row['receivable_balance'] ?? 0), 2); // এই উত্তোলনের পরের বর্তমান ব্যালেন্স
    $prev_balance = round($post_balance - $amount, 2); // উত্তোলনের আগে যা ছিল (debit = ব্যালেন্স বাড়ায়, তাই বিয়োগ করলে আগেরটা মেলে)

    $name = function_exists('ame_clean_display_text') ? ame_clean_display_text((string) $row['name']) : (string) $row['name'];
    $ref = (string) ($row['reference_no'] ?: ('WD-' . str_pad((string) $wid, 6, '0', STR_PAD_LEFT)));
    $phone = trim((string) ($row['phone'] ?? ''));
    $code = trim((string) ($row['customer_code'] ?? ''));
    $method = strtoupper(trim((string) ($row['method'] ?? 'CASH')));
    $method_map = [
        'CASH' => ['নগদ', 'Cash'],
        'BKASH' => ['বিকাশ', 'bKash'],
        'NAGAD' => ['নগদ (মোবাইল)', 'Nagad'],
        'BANK' => ['ব্যাংক', 'Bank'],
        'CHEQUE' => ['চেক', 'Cheque'],
    ];
    $method_pair = $method_map[$method] ?? [$method, $method];
    $method_label = is_en() ? $method_pair[1] : $method_pair[0];
    $account = trim((string) ($row['account_name'] ?? ''));
    $note = trim((string) ($row['note'] ?? ''));
    $when = (string) ($row['withdraw_date'] ?? '');

    /** ব্যালেন্সকে "জমা ৳X" বা "বকেয়া ৳X" হিসেবে লেবেল+রঙ দেয় — ভাষা অনুযায়ী একটাই। */
    $balance_line = static function (float $balance): array {
        if ($balance < -0.009) {
            return [ame_l('জমা', 'Held'), round(abs($balance), 2), 'net'];
        }
        if ($balance > 0.009) {
            return [ame_l('বকেয়া', 'Due'), round($balance, 2), 'due'];
        }
        return [ame_l('সমান', 'Settled'), 0.0, ''];
    };
    [$prev_label, $prev_amt, $prev_cls] = $balance_line($prev_balance);
    [$now_label, $now_amt, $now_cls] = $balance_line($post_balance);
}
?>
<!DOCTYPE html>
<html lang="<?php echo is_en() ? 'en' : 'bn'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ame_memo_h(ame_l('উত্তোলন রসিদ', 'Payout receipt')); ?><?php echo $row ? ' ' . ame_memo_h($ref) : ''; ?></title>
    <link rel="stylesheet" href="/assets/css/txn-memo.css?v=<?php echo $ver; ?>">
    <script src="/assets/js/html2canvas.min.js?v=<?php echo $ver; ?>"></script>
</head>
<body class="tm-page">
<?php if (!$row): ?>
    <div class="tm-missing">
        <p><?php echo ame_memo_h(ame_l('উত্তোলন রসিদ পাওয়া যায়নি।', 'Payout receipt not found.')); ?></p>
        <a class="tm-back" href="withdraw.php" style="display:inline-block;margin-top:12px;padding:12px 18px;border-radius:12px;"><?php echo ame_memo_h(ame_l('ফিরে যান', 'Go back')); ?></a>
    </div>
<?php else: ?>
<div id="memo-container" class="tm-card buy" data-ame-capture="clone">
    <div class="tm-head">
        <h1><?php echo ame_memo_h($shop_name); ?></h1>
        <?php if ($shop_addr !== ''): ?><p style="margin-top:2px;"><?php echo ame_memo_h($shop_addr); ?></p><?php endif; ?>
        <?php if ($shop_ph !== ''): ?><p style="margin-top:2px;"><?php echo ame_memo_h(ame_l('ফোন', 'Phone')); ?>: <?php echo ame_memo_h($shop_ph); ?></p><?php endif; ?>
        <p><?php echo ame_memo_h(ame_l('উত্তোলন রসিদ', 'Payout receipt')); ?></p>
    </div>
    <div class="tm-body">
        <div class="tm-title"><?php echo ame_memo_h(ame_l('জমা টাকা তোলা', 'Deposit payout')); ?></div>

        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h(ame_l('রসিদ নম্বর', 'Receipt no.')); ?></span><span class="tm-value"><?php echo ame_memo_h($ref); ?></span></div>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h(ame_l('তারিখ', 'Date')); ?></span><span class="tm-value"><?php echo ame_memo_h($when); ?></span></div>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h(ame_l('কাস্টমার', 'Customer')); ?></span><span class="tm-value"><?php echo ame_memo_h($name); ?></span></div>
        <?php if ($code !== ''): ?>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h(ame_l('কোড', 'Code')); ?></span><span class="tm-value"><?php echo ame_memo_h($code); ?></span></div>
        <?php endif; ?>
        <?php if ($phone !== ''): ?>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h(ame_l('মোবাইল', 'Mobile')); ?></span><span class="tm-value"><?php echo ame_memo_h($phone); ?></span></div>
        <?php endif; ?>
        <?php if ($account !== ''): ?>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h(ame_l('অ্যাকাউন্ট', 'Account')); ?></span><span class="tm-value"><?php echo ame_memo_h($account); ?></span></div>
        <?php endif; ?>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h(ame_l('মাধ্যম', 'Method')); ?></span><span class="tm-value"><?php echo ame_memo_h($method_label); ?></span></div>
        <?php if ($note !== ''): ?>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h(ame_l('নোট', 'Note')); ?></span><span class="tm-value"><?php echo ame_memo_h($note); ?></span></div>
        <?php endif; ?>

        <div class="tm-paid">
            <small><?php echo ame_memo_h(ame_l('এই রসিদে তোলা হয়েছে', 'Withdrawn on this receipt')); ?></small>
            <b>৳ <?php echo ame_memo_money($amount); ?></b>
        </div>

        <div class="tm-due">
            <h3><?php echo ame_memo_h(ame_l('সাবেক − এই উত্তোলন = বর্তমান', 'Previous − this payout = Now')); ?></h3>
            <div class="tm-row">
                <span class="tm-label"><?php echo ame_memo_h(ame_l('সাবেক (উত্তোলনের আগে)', 'Previous (before this payout)')); ?></span>
                <span class="tm-value <?php echo ame_memo_h($prev_cls); ?>"><?php echo ame_memo_h($prev_label); ?> ৳<?php echo ame_memo_money($prev_amt); ?></span>
            </div>
            <div class="tm-row">
                <span class="tm-label"><?php echo ame_memo_h(ame_l('এই উত্তোলন (−)', 'This payout (−)')); ?></span>
                <span class="tm-value">৳<?php echo ame_memo_money($amount); ?></span>
            </div>
            <div class="tm-row total">
                <span><?php echo ame_memo_h(ame_l('এখন', 'Now')); ?></span>
                <span class="<?php echo ame_memo_h($now_cls); ?>"><?php echo ame_memo_h($now_label); ?> ৳<?php echo ame_memo_money($now_amt); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="tm-btns">
    <button class="tm-dl" type="button" onclick="ameMemoCapture('download','withdraw-<?php echo (int)$wid; ?>')"><?php echo ame_memo_h(ame_l('ডাউনলোড', 'Download')); ?></button>
    <button class="tm-sh" type="button" onclick="ameMemoCapture('share','withdraw-<?php echo (int)$wid; ?>')"><?php echo ame_memo_h(ame_l('শেয়ার', 'Share')); ?></button>
    <button class="tm-pr" type="button" onclick="window.print()"><?php echo ame_memo_h(ame_l('প্রিন্ট', 'Print')); ?></button>
    <a class="tm-back" href="withdraw.php"><?php echo ame_memo_h(ame_l('আরেকজন কাস্টমার', 'Another customer')); ?></a>
</div>
<script src="/assets/js/ame-memo-file.js"></script>
<?php endif; ?>
<?php include __DIR__ . '/../../partials/confirm_actions.php'; ?>
</body>
</html>
