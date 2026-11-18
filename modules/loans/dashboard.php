<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
ensure_loan_tables($db);
include '../../partials/header.php';

if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}

function ld_num($db, string $sql, $fallback = 0)
{
    $r = @$db->query($sql);
    if (!$r) {
        return $fallback;
    }
    $row = $r->fetch_assoc();
    if (!$row) {
        return $fallback;
    }
    $v = reset($row);
    return is_numeric($v) ? 0 + $v : $fallback;
}

function ld_money($n): string
{
    return number_format((float)$n, 0) . ' ৳';
}

function ld_money2($n): string
{
    return number_format((float)$n, 2) . ' ৳';
}

$active_given = (int)ld_num($db, "SELECT COUNT(*) c FROM loans WHERE status='open' AND type='given'");
$active_taken = (int)ld_num($db, "SELECT COUNT(*) c FROM loans WHERE status='open' AND type='taken'");
$out_given = ld_num($db, "SELECT COALESCE(SUM(amount - paid_amount),0) s FROM loans WHERE status='open' AND type='given'");
$out_taken = ld_num($db, "SELECT COALESCE(SUM(amount - paid_amount),0) s FROM loans WHERE status='open' AND type='taken'");
$pending_apps = (int)ld_num($db, "SELECT COUNT(*) c FROM loan_applications WHERE status='pending'");
$approved_apps = (int)ld_num($db, "SELECT COUNT(*) c FROM loan_applications WHERE status='approved'");
$overdue_count = (int)ld_num($db, "SELECT COUNT(*) c FROM loan_repayment_schedule WHERE status IN ('pending','partial') AND due_date < CURDATE()");
$overdue_amt = ld_num($db, "SELECT COALESCE(SUM(due_amount - paid_amount),0) s FROM loan_repayment_schedule WHERE status IN ('pending','partial') AND due_date < CURDATE()");
$disbursed_month = ld_num($db, "SELECT COALESCE(SUM(amount),0) s FROM loans WHERE type='given' AND DATE_FORMAT(disbursed_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
$today_collect = ld_num($db, "SELECT COALESCE(SUM(due_amount - paid_amount),0) s FROM loan_repayment_schedule WHERE status IN ('pending','partial') AND due_date = CURDATE()");
$products = (int)ld_num($db, "SELECT COUNT(*) c FROM loan_products WHERE is_active=1");

$recent = [];
$rq = @$db->query("SELECT id, person_name, type, amount, paid_amount, status FROM loans WHERE status='open' ORDER BY id DESC LIMIT 8");
if ($rq) {
    while ($row = $rq->fetch_assoc()) {
        $recent[] = $row;
    }
}
$overdue_rows = [];
$oq = @$db->query("
    SELECT s.id, s.loan_id, s.due_date, s.due_amount, s.paid_amount, l.person_name,
           DATEDIFF(CURDATE(), s.due_date) AS days_overdue
    FROM loan_repayment_schedule s
    JOIN loans l ON l.id = s.loan_id
    WHERE s.status IN ('pending','partial') AND s.due_date < CURDATE() AND l.status='open'
    ORDER BY days_overdue DESC
    LIMIT 6
");
if ($oq) {
    while ($row = $oq->fetch_assoc()) {
        $overdue_rows[] = $row;
    }
}
$due_soon = [];
$dq = @$db->query("
    SELECT s.id, s.loan_id, s.due_date, s.due_amount, s.paid_amount, l.person_name
    FROM loan_repayment_schedule s
    JOIN loans l ON l.id = s.loan_id
    WHERE s.status IN ('pending','partial') AND s.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND l.status='open'
    ORDER BY s.due_date ASC
    LIMIT 6
");
if ($dq) {
    while ($row = $dq->fetch_assoc()) {
        $due_soon[] = $row;
    }
}

function ld_tile(string $href, string $icon, string $bn, string $en, string $hintBn, string $hintEn, bool $on = false): void
{
    echo '<a class="ld-tile' . ($on ? ' on' : '') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
    echo '<span class="ld-ico" aria-hidden="true">' . $icon . '</span>';
    echo '<strong>' . ame_pair($bn, $en) . '</strong>';
    echo '<em>' . htmlspecialchars($hintBn, ENT_QUOTES, 'UTF-8') . ' · ' . htmlspecialchars($hintEn, ENT_QUOTES, 'UTF-8') . '</em>';
    echo '</a>';
}
?>
<link rel="stylesheet" href="/assets/css/loan-desk.808.css">
<script src="/assets/js/device-display.js?v=8.08"></script>

<div class="ld-wrap">
  <div class="ld-hero">
    <section class="ld-book">
      <p class="ld-kicker">Credit desk · ঋণ ডেস্ক</p>
      <h1><?php echo ame_pair('লোন ব্যবস্থাপনা', 'Loan operations'); ?></h1>
      <p><?php echo ame_pair('ওয়ার্কশপ ও পার্টস ব্যবসার ধার-দেওয়া ও ধার-নেওয়া — এক খাতায়।', 'Given and taken credit for the workshop and parts desk, one ledger.'); ?></p>
      <div class="ld-folio">
        <div>
          <b><?php echo ld_money($out_given); ?></b>
          <span><?php echo ame_pair('প্রদত্ত বকেয়া', 'Given outstanding'); ?></span>
        </div>
        <div>
          <b><?php echo ld_money($out_taken); ?></b>
          <span><?php echo ame_pair('গৃহীত বকেয়া', 'Taken outstanding'); ?></span>
        </div>
      </div>
    </section>
    <nav class="ld-actions" aria-label="Quick actions">
      <a class="ld-act give" href="applications_add.php"><i>＋</i><span><?php echo ame_pair('গ্রাহককে ধার দিন', 'Give credit'); ?></span></a>
      <a class="ld-act take" href="add_taken.php"><i>↙</i><span><?php echo ame_pair('ধার নিন', 'Take a loan'); ?></span></a>
      <a class="ld-act collect" href="collections.php"><i>৳</i><span><?php echo ame_pair('আজকের আদায়', 'Collect today'); ?></span></a>
    </nav>
  </div>

  <div class="ld-kpis">
    <a class="ld-kpi" href="list.php">
      <span class="ld-ico">📒</span>
      <span class="ld-lbl"><?php echo ame_pair('চলমান প্রদত্ত', 'Active given'); ?></span>
      <b><?php echo (int)$active_given; ?></b>
      <em><?php echo ame_pair('খোলা লোন', 'Open lending book'); ?></em>
    </a>
    <a class="ld-kpi gold" href="applications_list.php?status=pending">
      <span class="ld-ico">📝</span>
      <span class="ld-lbl"><?php echo ame_pair('লম্বিত আবেদন', 'Pending apps'); ?></span>
      <b><?php echo (int)$pending_apps; ?></b>
      <em><?php echo ame_pair('অনুমোদনের অপেক্ষা', 'Waiting approval'); ?></em>
    </a>
    <a class="ld-kpi hot" href="due_overdue.php">
      <span class="ld-ico">⏰</span>
      <span class="ld-lbl"><?php echo ame_pair('বিলম্বিত কিস্তি', 'Overdue'); ?></span>
      <b><?php echo (int)$overdue_count; ?></b>
      <em><?php echo ld_money2($overdue_amt); ?> · <?php echo ame_pair('বকেয়া কিস্তি', 'Past due'); ?></em>
    </a>
    <a class="ld-kpi" href="list.php">
      <span class="ld-ico">🏦</span>
      <span class="ld-lbl"><?php echo ame_pair('চলমান গৃহীত', 'Active taken'); ?></span>
      <b><?php echo (int)$active_taken; ?></b>
      <em><?php echo ame_pair('আমরা যে ঋণ নিয়েছি', 'Loans we owe'); ?></em>
    </a>
  </div>

  <p class="ld-sec"><?php echo ame_pair('ওয়ার্কফ্লো', 'Workflow'); ?></p>
  <div class="ld-pipe">
    <a class="ld-step" href="applications_list.php">
      <span class="ld-n">1</span>
      <div>
        <span><?php echo ame_pair('আবেদন', 'Applications'); ?></span>
        <b><?php echo (int)$pending_apps; ?></b>
      </div>
    </a>
    <a class="ld-step" href="approvals.php">
      <span class="ld-n">2</span>
      <div>
        <span><?php echo ame_pair('অনুমোদন', 'Approvals'); ?></span>
        <b><?php echo (int)$approved_apps; ?></b>
      </div>
    </a>
    <a class="ld-step" href="applications_list.php?status=approved">
      <span class="ld-n">3</span>
      <div>
        <span><?php echo ame_pair('ছাড়', 'Disburse'); ?></span>
        <b><?php echo ld_money($disbursed_month); ?></b>
        <em style="font-style:normal;font-size:11px;color:#6b7f74;"><?php echo ame_pair('এই মাসে ছাড়', 'Disbursed this month'); ?></em>
      </div>
    </a>
  </div>

  <div class="ld-split">
    <section class="ld-card">
      <h2><?php echo ame_pair('বিলম্বিত কিস্তি', 'Overdue queue'); ?> <a href="due_overdue.php"><?php echo ame_pair('সব', 'All'); ?></a></h2>
      <?php if (!$overdue_rows): ?>
        <div class="ld-empty"><?php echo ame_pair('কোনো কিস্তি বিলম্বিত নেই।', 'No overdue installments.'); ?></div>
      <?php endif; ?>
      <?php foreach ($overdue_rows as $r): ?>
        <a class="ld-row hot" href="pay.php?id=<?php echo (int)$r['loan_id']; ?>">
          <div>
            <strong><?php echo htmlspecialchars((string)($r['person_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></strong>
            <small><?php echo (int)$r['days_overdue']; ?> <?php echo ame_pair('দিন পেরিয়েছে', 'days late'); ?></small>
          </div>
          <b><?php echo ld_money2((float)$r['due_amount'] - (float)$r['paid_amount']); ?></b>
        </a>
      <?php endforeach; ?>
    </section>
    <section class="ld-card">
      <h2><?php echo ame_pair('৭ দিনের আদায়', 'Next 7 days'); ?> <a href="collections.php"><?php echo ame_pair('কালেকশন', 'Collect'); ?></a></h2>
      <?php if (!$due_soon): ?>
        <div class="ld-empty"><?php echo ame_pair('আগামী ৭ দিনে কিস্তি বাকি নেই। আজ আদায়যোগ্য: ', 'Nothing due in 7 days. Collectable today: '); echo ld_money2($today_collect); ?></div>
      <?php endif; ?>
      <?php foreach ($due_soon as $r): ?>
        <a class="ld-row" href="pay.php?id=<?php echo (int)$r['loan_id']; ?>">
          <div>
            <strong><?php echo htmlspecialchars((string)($r['person_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></strong>
            <small><?php echo htmlspecialchars((string)$r['due_date'], ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <b><?php echo ld_money2((float)$r['due_amount'] - (float)$r['paid_amount']); ?></b>
        </a>
      <?php endforeach; ?>
    </section>
  </div>

  <section class="ld-card" style="margin-bottom:14px;">
    <h2><?php echo ame_pair('খোলা লোন', 'Open book'); ?> <a href="list.php"><?php echo ame_pair('পূর্ণ তালিকা', 'Full list'); ?></a></h2>
    <?php if (!$recent): ?>
      <div class="ld-empty"><?php echo ame_pair('এখন কোনো খোলা লোন নেই — উপর থেকে ধার দিন বা নিন।', 'No open loans yet — give or take credit above.'); ?></div>
    <?php endif; ?>
    <?php foreach ($recent as $l): ?>
      <a class="ld-row" href="pay.php?id=<?php echo (int)$l['id']; ?>">
        <div>
          <strong><?php echo htmlspecialchars((string)($l['person_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></strong>
          <small><?php echo ($l['type'] ?? '') === 'taken' ? ame_pair('গৃহীত ঋণ', 'Taken') : ame_pair('প্রদত্ত ঋণ', 'Given'); ?></small>
        </div>
        <b><?php echo ld_money2((float)$l['amount'] - (float)$l['paid_amount']); ?></b>
      </a>
    <?php endforeach; ?>
  </section>

  <p class="ld-sec"><?php echo ame_pair('ডেস্ক', 'Desk'); ?></p>
  <div class="ld-grid">
    <?php
    ld_tile('dashboard.php', '⌂', 'ড্যাশবোর্ড', 'Dashboard', 'এই পেজ', 'This page', true);
    ld_tile('list.php', '📋', 'সব লোন', 'All loans', 'প্রদত্ত ও গৃহীত', 'Given and taken');
    ld_tile('applications_list.php', '👥', 'আবেদন', 'Borrowers', 'নতুন আবেদন ও তালিকা', 'New and list');
    ld_tile('products_list.php', '🏦', 'প্রোডাক্ট', 'Products', 'সুদ ও মেয়াদ', 'Rate and term');
    ld_tile('approvals.php', '✅', 'অনুমোদন', 'Approvals', 'অপেক্ষমাণ আবেদন', 'Waiting queue');
    ld_tile('applications_list.php?status=approved', '💸', 'ছাড়', 'Disburse', 'অনুমোদিত ছাড় করুন', 'Pay out approved');
    ld_tile('collections.php', '💰', 'কালেকশন', 'Collections', 'আজ + ৭ দিন', 'Today + 7 days');
    ld_tile('due_overdue.php', '⏰', 'বকেয়া', 'Due & overdue', 'বিলম্বিত কিস্তি', 'Late installments');
    ld_tile('recovery.php', '☎', 'রিকভারি', 'Recovery', 'তাগাদা ও ভিজিট', 'Calls and visits');
    ld_tile('reports.php', '📊', 'রিপোর্ট', 'Reports', 'খাতার সারাংশ', 'Book summary');
    ld_tile('calendar.php', '📅', 'ক্যালেন্ডার', 'Calendar', 'কিস্তির তারিখ', 'Installment dates');
    ld_tile('bank_wise.php', '🏛', 'ব্যাংক অনুযায়ী', 'Bank-wise', 'ব্যাংক ঋণ খাতা', 'Bank loan book');
    ld_tile('add.php', '＋', 'ব্যাংক লোন যোগ', 'Add bank loan', 'ব্যাংক থেকে নেওয়া', 'Taken from bank');
    ld_tile('add_taken.php', '↙', 'ধার নিন', 'Take loan', 'ব্যক্তি/দোকান থেকে', 'From a person');
    ?>
  </div>
</div>
<?php include '../../partials/footer.php'; ?>
