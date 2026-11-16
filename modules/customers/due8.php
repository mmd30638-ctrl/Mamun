<?php
require_once __DIR__ . '/../../includes/config.php';
header('Location: /modules/customers/due-reminder.php', true, 302);
exit;

require_once __DIR__ . '/../../includes/account_functions.php';
require_once __DIR__ . '/customer_schema.php';
ensure_customer_tables($db);
ame_due8_ensure($db);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['due8_toggle'])) {
    csrf_verify();
    $on = (string)($_POST['due8_toggle'] ?? '') === '1';
    ame_due8_set_enabled($db, $on);
    header('Location: /modules/customers/due8.php');
    exit;
}

$enabled = ame_due8_enabled($db);
$search = trim((string)($_GET['q'] ?? ''));
$rows = $enabled ? ame_due8_list($db, $search) : [];
$count = $enabled ? count($rows) : 0;
$sum_due = 0.0;
foreach ($rows as $r) {
    $sum_due += (float)($r['receivable_balance'] ?? 0);
}

$last_pay_map = [];
if ($rows && function_exists('ame_mysqli_rows')) {
    $ids = array_map(static function ($r) {
        return (int)($r['id'] ?? 0);
    }, $rows);
    $ids = array_values(array_filter($ids));
    if ($ids && ame_due8_table_ok($db, 'customer_payments')) {
        $in = implode(',', $ids);
        $sql = "SELECT p.customer_id, p.payment_date, p.amount
                FROM customer_payments p
                INNER JOIN (
                    SELECT customer_id, MAX(id) AS mid
                    FROM customer_payments
                    WHERE customer_id IN ($in)
                    GROUP BY customer_id
                ) t ON t.mid = p.id";
        $res = @$db->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $last_pay_map[(int)$row['customer_id']] = $row;
            }
        }
    }
}

$bn_date = static function ($value) {
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '0000-00-00' || str_starts_with($raw, '1000-')) {
        return '—';
    }
    $t = strtotime($raw);
    return $t ? date('d/m/Y', $t) : $raw;
};

$ame_body_class = 'ame-due8-page';
include __DIR__ . '/../../partials/header.php';
$av = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.487', ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="/assets/css/due8.css?v=<?php echo $av; ?>">
<div class="ame-due8-well">
<div class="d8">
  <header class="d8-hero">
    <div>
      <h1>৮ দিনের বকেয়া নোটিফিকেশন</h1>
      <p>বকেয়া আছে, অথচ ৮ দিনের ভিতরে পেমেন্ট হয়নি — সেই কাস্টমাররা এখানে আসে।</p>
    </div>
    <form method="post" class="d8-switch">
      <?php echo csrf_field(); ?>
      <?php if ($enabled): ?>
        <button type="submit" name="due8_toggle" value="0" class="d8-on">চালু · বন্ধ করুন</button>
      <?php else: ?>
        <button type="submit" name="due8_toggle" value="1" class="d8-off">বন্ধ · চালু করুন</button>
      <?php endif; ?>
    </form>
  </header>

  <div class="d8-kpis">
    <article class="d8-kpi d8-navy"><span>সিস্টেম</span><b><?php echo $enabled ? 'চালু' : 'বন্ধ'; ?></b></article>
    <article class="d8-kpi d8-rose"><span>৮ দিন পার</span><b><?php echo (int)$count; ?></b></article>
    <article class="d8-kpi d8-orange"><span>মোট বাকি</span><b><?php echo number_format($sum_due, 0); ?></b></article>
    <article class="d8-kpi d8-cyan"><span>দিন</span><b><?php echo (int)ame_due8_days(); ?></b></article>
  </div>

  <?php if (!$enabled): ?>
    <div class="d8-empty">নোটিফিকেশন এখন বন্ধ। উপরের বাটন চাপলে চালু হবে — তখন হেডারের আলাদা আইকনে সংখ্যা উঠবে।</div>
  <?php else: ?>
    <form method="get" class="d8-filter">
      <input type="search" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="আইডি / নাম / মোবাইল">
      <button type="submit">সার্চ</button>
    </form>

    <?php if (!$rows): ?>
      <div class="d8-empty">এখন কোনো কাস্টমার ৮ দিন ধরে পেমেন্ট দেননি, অথবা সবাই পরিশোধ করেছেন।</div>
    <?php else: ?>
    <div class="d8-table-wrap">
      <table class="d8-table">
        <thead>
          <tr>
            <th>#</th>
            <th>আইডি</th>
            <th>নাম</th>
            <th>মোবাইল</th>
            <th>বাকি</th>
            <th>সর্বশেষ পেমেন্ট</th>
            <th>সর্বশেষ বিক্রয়</th>
            <th>নিশ্চুপ দিন</th>
            <th>কাজ</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $c):
            $cid = (int)$c['id'];
            $due = (float)($c['receivable_balance'] ?? 0);
            $last_act = (string)($c['last_act'] ?? '');
            $silent = 0;
            if ($last_act !== '' && !str_starts_with($last_act, '1000-')) {
                $t = strtotime($last_act);
                if ($t) {
                    $silent = (int) floor((strtotime('today') - $t) / 86400);
                }
            }
            $pay = $last_pay_map[$cid] ?? null;
            $pay_text = $pay
                ? ($bn_date($pay['payment_date'] ?? '') . ' · ' . number_format((float)($pay['amount'] ?? 0), 0))
                : $bn_date($c['last_pay'] ?? '');
            $code = trim((string)($c['customer_code'] ?? ''));
            if ($code === '') {
                $code = (string)$cid;
            }
        ?>
          <tr>
            <td><?php echo (int)$i + 1; ?></td>
            <td><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="d8-name"><?php echo htmlspecialchars((string)($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)($c['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="d8-due"><?php echo number_format($due, 0); ?></td>
            <td><?php echo htmlspecialchars($pay_text, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($bn_date($c['last_sale'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="d8-days"><?php echo $silent > 0 ? $silent : '—'; ?></td>
            <td><a class="d8-pay" href="/modules/customers/payment-receive.php?id=<?php echo $cid; ?>">পেমেন্ট</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="4">মোট</td>
            <td class="d8-due"><?php echo number_format($sum_due, 0); ?></td>
            <td colspan="4"></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
</div>
<script src="/assets/js/due8-fit.js?v=<?php echo $av; ?>"></script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
