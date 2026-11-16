<?php
require_once '../../includes/report_engine.php';
require_once __DIR__ . '/../../includes/pay_mark.php';
require_once __DIR__ . '/../../includes/account_functions.php';

[$start_date, $end_date] = report_get_date_range(30);

/* কোন অ্যাকাউন্টে কত টাকা — শুধু অ্যাডমিন/মালিক দেখবে।
   রোল-কী দোকানভেদে (admin/owner/super_admin...) আলাদা হতে পারে, তাই
   প্রথম ইউজার (মালিক, id=1)-ও সবসময় দেখবে। */
$ame_cb_role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$ame_cb_role_norm = str_replace([' ', '_', '-'], '', $ame_cb_role);
$ame_cb_is_admin = in_array($ame_cb_role_norm, ['admin', 'owner', 'superadmin', 'manager', 'proprietor', 'administrator', 'এডমিন', 'মালিক'], true)
    || (int)($_SESSION['user_id'] ?? 0) === 1;
if (!function_exists('ame_cb_bal')) {
    function ame_cb_bal(float $amount): string
    {
        global $ame_cb_is_admin;
        if (!empty($ame_cb_is_admin)) {
            // সরাসরি ফরম্যাট — ame_balance_label() গ্লোবালি মাস্ক করে।
            return '৳' . number_format($amount, 2);
        }
        return '<span title="' . htmlspecialchars(ame_l('শুধু অ্যাডমিন দেখতে পারবে', 'Admin only'), ENT_QUOTES, 'UTF-8') . '">🔒</span>';
    }
    function ame_cb_num(float $amount): string
    {
        return number_format($amount, 2);
    }
}

if (($_GET['export'] ?? '') === 'csv') {
    if (!$ame_cb_is_admin) {
        echo "<script>alert('" . htmlspecialchars(ame_l('অনুমতি নেই! ব্যালেন্স শুধু অ্যাডমিন দেখতে পারবে।', 'Not allowed!'), ENT_QUOTES, 'UTF-8') . "'); window.location='cash_bank_summary.php';</script>";
        exit();
    }
    $stmt = $db->query('SELECT account_name, account_type, balance FROM accounts ORDER BY account_type ASC, account_name ASC');
    $csv_rows = [];
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $csv_rows[] = [$row['account_name'], $row['account_type'], ame_cb_num((float)$row['balance'])];
        }
    }
    report_export_csv('cash_bank_summary', ['অ্যাকাউন্ট', 'ধরন', 'ব্যালেন্স'], $csv_rows);
}

include '../../partials/header.php';

if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}

ame_pay_mark_ensure($db);
$marked_id = ame_pay_mark_get($db);

$accounts_result = $db->query('SELECT id, account_name, account_type, balance FROM accounts ORDER BY account_type ASC, account_name ASC');
$accounts = $accounts_result ? $accounts_result->fetch_all(MYSQLI_ASSOC) : [];

$type_totals = [];
$grand_total = 0;
$marked_name = '';
foreach ($accounts as $a) {
    $type = $a['account_type'] ?: 'অন্যান্য';
    $type_totals[$type] = ($type_totals[$type] ?? 0) + (float)$a['balance'];
    $grand_total += (float)$a['balance'];
    if ($marked_id && (int)$a['id'] === $marked_id) {
        $marked_name = (string)$a['account_name'];
    }
}

$stmt = $db->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END), 0) as total_credit,
        COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END), 0) as total_debit
     FROM account_ledger WHERE DATE(created_at) BETWEEN ? AND ?"
);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$flow = $stmt->get_result()->fetch_assoc();

$type_icons = ['Cash' => '💵', 'Bank' => '🏦', 'Mobile' => '📱', 'অন্যান্য' => '💰'];
$type_colors = ['Cash' => '#3f6212', 'Bank' => '#1e3a5f', 'Mobile' => '#6b21a8', 'অন্যান্য' => '#57534e'];
$who = htmlspecialchars((string)($_SESSION['user_name'] ?? $_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8');
$redirect = 'cash_bank_summary.php?start_date=' . rawurlencode($start_date) . '&end_date=' . rawurlencode($end_date);

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'cash-bank-summary-' . $start_date . '-to-' . $end_date,
    'title' => $shop . ' · ' . ame_l('ক্যাশ ও ব্যাংক সারাংশ', 'Cash & Bank Summary'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('ক্যাশ ও ব্যাংক সারাংশ', 'Cash & Bank Summary'),
        $start_date . ' — ' . $end_date,
        $ame_cb_is_admin ? (ame_l('সর্বমোট ব্যালেন্স', 'All accounts') . ': ৳' . ame_cb_num($grand_total)) : null,
    ])),
    'shop' => $shop,
];
?>
<link rel="stylesheet" href="/assets/css/treasury.808.css">
<script src="/assets/js/device-display.js?v=8.08"></script>

<div class="ty-wrap">
  <header class="ty-mast">
    <div class="ty-mast-copy">
      <p class="ty-kicker"><?php echo ame_pair('ট্রেজারি ট্রে', 'Treasury tray'); ?></p>
      <h1><?php echo ame_pair('ক্যাশ ও ব্যাংক সারাংশ', 'Cash & Bank Summary'); ?></h1>
      <p><?php echo ame_pair(
          'একটি অ্যাকাউন্টে ★ মার্ক করুন — POS ও সব পেমেন্ট সিলেক্ট সেটাই অটো কানেক্ট থাকবে, যতক্ষণ আপনি ম্যানুয়ালি না বদলান।',
          'Star one account. Every payment select stays wired to it until you change it by hand.'
      ); ?></p>
    </div>
    <div class="ty-mast-total">
      <span><?php echo ame_pair('সর্বমোট ব্যালেন্স', 'All accounts'); ?></span>
      <b><?php echo ame_cb_bal($grand_total); ?></b>
    </div>
  </header>

  <div class="ty-toolbar no-print">
    <div class="ty-meta"><?php echo ame_pair('তৈরি', 'Prepared'); ?> · <?php echo $who; ?> · <?php echo htmlspecialchars(date('d M Y, h:i A'), ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="ty-actions">
      <?php if ($ame_cb_is_admin): ?>
      <a class="ty-btn ghost" href="?export=csv&amp;start_date=<?php echo htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8'); ?>&amp;end_date=<?php echo htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8'); ?>">CSV এক্সপোর্ট</a>
      <?php endif; ?>
      <button class="ty-btn ghost" type="button" onclick="window.print()">প্রিন্ট / Print</button>
    </div>
  </div>

  <form method="GET" class="ty-filter no-print">
    <label><?php echo ame_pair('শুরু তারিখ', 'From'); ?>
      <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label><?php echo ame_pair('শেষ তারিখ', 'To'); ?>
      <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <button class="ty-btn" type="submit"><?php echo ame_pair('ফিল্টার করুন', 'Filter'); ?></button>
  </form>

  <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
  <div id="ame-slip">
  <div class="wdr-shop-head" style="text-align:center;">
    <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  </div>

  <section class="ty-mark-banner<?php echo $marked_name !== '' ? ' on' : ''; ?>">
    <?php if ($marked_name !== ''): ?>
      <strong>★ <?php echo htmlspecialchars($marked_name, ENT_QUOTES, 'UTF-8'); ?></strong>
      <span><?php echo ame_pair('ডিফল্ট পেমেন্ট অ্যাকাউন্ট — সব অপশনে অটো কানেক্ট', 'Default pay account — auto-connected on every payment'); ?></span>
    <?php else: ?>
      <strong><?php echo ame_pair('এখনো মার্ক নেই', 'No account marked'); ?></strong>
      <span><?php echo ame_pair('নিচের সারিতে ★ চাপুন। পরে যেকোনো সিলেক্ট ম্যানুয়ালি বদলানো যাবে।', 'Tap ★ on a row. You can still change any payment select by hand.'); ?></span>
    <?php endif; ?>
  </section>

  <div class="ty-flow">
    <article class="in">
      <span><?php echo ame_pair('মোট জমা (সময়সীমায়)', 'Credits in range'); ?></span>
      <b><?php echo $ame_cb_is_admin ? ('৳' . number_format((float)$flow['total_credit'], 2)) : ame_cb_bal(0); ?></b>
    </article>
    <article class="out">
      <span><?php echo ame_pair('মোট খরচ (সময়সীমায়)', 'Debits in range'); ?></span>
      <b><?php echo $ame_cb_is_admin ? ('৳' . number_format((float)$flow['total_debit'], 2)) : ame_cb_bal(0); ?></b>
    </article>
  </div>

  <div class="ty-types">
    <?php foreach ($type_totals as $type => $amt):
        $icon = $type_icons[$type] ?? '💰';
        $color = $type_colors[$type] ?? '#57534e';
    ?>
      <article style="--ty-chip: <?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>;">
        <span><?php echo $icon . ' ' . htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8'); ?></span>
        <b><?php echo ame_cb_bal((float)$amt); ?></b>
      </article>
    <?php endforeach; ?>
  </div>

  <section class="ty-tray">
    <?php if (empty($accounts)): ?>
      <p class="ty-empty"><?php echo ame_pair('কোনো অ্যাকাউন্ট যোগ করা হয়নি।', 'No accounts have been added.'); ?></p>
    <?php else: ?>
    <div class="ty-table-wrap">
      <table>
        <thead>
          <tr>
            <th><?php echo ame_pair('মার্ক', 'Mark'); ?></th>
            <th><?php echo ame_pair('অ্যাকাউন্টের নাম', 'Account'); ?></th>
            <th><?php echo ame_pair('ধরন', 'Type'); ?></th>
            <th class="num"><?php echo ame_pair('ব্যালেন্স', 'Balance'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accounts as $a):
              $type = $a['account_type'] ?: 'অন্যান্য';
              $color = $type_colors[$type] ?? '#57534e';
              $is_on = $marked_id && (int)$a['id'] === $marked_id;
              $post_id = $is_on ? 0 : (int)$a['id'];
          ?>
          <tr class="<?php echo $is_on ? 'marked' : ''; ?>">
            <td>
              <form method="POST" action="mark_pay_account.php" class="ty-star-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="account_id" value="<?php echo $post_id; ?>">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="ty-star<?php echo $is_on ? ' on' : ''; ?>" aria-pressed="<?php echo $is_on ? 'true' : 'false'; ?>">
                  <?php echo $is_on ? '★' : '☆'; ?>
                  <span><?php echo $is_on ? ame_pair('মার্ক আছে', 'Marked') : ame_pair('মার্ক', 'Mark'); ?></span>
                </button>
              </form>
            </td>
            <td class="name"><?php echo htmlspecialchars((string)$a['account_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><em class="ty-chip" style="--ty-chip: <?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>;"><?php echo htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8'); ?></em></td>
            <td class="num<?php echo ($ame_cb_is_admin && (float)$a['balance'] < 0) ? ' neg' : ''; ?>"><?php echo ame_cb_bal((float)$a['balance']); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
  </div>

  <p class="ty-foot">Report ID: RPT-CASH-BANK-SUMMARY · <?php echo htmlspecialchars((string)($_SERVER['HTTP_HOST'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<?php include '../../partials/footer.php'; ?>
