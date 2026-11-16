<?php
require_once '../../includes/config.php';
admin_only();
include '../../partials/header.php';

$date_from = trim((string)($_GET['from'] ?? date('Y-m-d')));
$date_to = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = date('Y-m-d');
}

function ame_ic_table_exists(mysqli $db, string $name): bool
{
    $esc = $db->real_escape_string($name);
    $r = @$db->query("SHOW TABLES LIKE '{$esc}'");
    return $r && $r->num_rows > 0;
}

function ame_ic_card(string $title, bool $ok, string $detail, string $fix_hint = '', string $fix_href = ''): void
{
    $cls = $ok ? 'ic-ok' : 'ic-bad';
    $badge = $ok ? 'পাস' : 'ফেল';
    echo '<div class="ic-card ' . $cls . '">';
    echo '<div class="ic-top"><b>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</b>';
    echo '<span class="ic-badge">' . $badge . '</span></div>';
    echo '<p>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</p>';
    if (!$ok && $fix_hint !== '') {
        echo '<div class="ic-fix"><span>সংশোধন:</span> ' . htmlspecialchars($fix_hint, ENT_QUOTES, 'UTF-8');
        if ($fix_href !== '') {
            echo ' — <a href="' . htmlspecialchars($fix_href, ENT_QUOTES, 'UTF-8') . '">লিংক</a>';
        }
        echo '</div>';
    }
    echo '</div>';
}

$cards = [];

/* 1) Sales totals vs sale_payments for the whole selected range. Read only. */
require_once '../../includes/money_read.php';
if (ame_ic_table_exists($db, 'sales') && ame_ic_table_exists($db, 'sale_payments')) {
    $gap = ame_money_sales_gap($db, $date_from, $date_to);
    $ok = $gap['mismatch'] === 0;
    $cards[] = [
        'বিক্রয় paid vs sale_payments',
        $ok,
        sprintf(
            'তারিখ %s → %s · ইনভয়েস %d · বিক্রয় paid যোগফল %.2f · পেমেন্ট যোগফল %.2f · অমিল ইনভয়েস %d',
            $date_from,
            $date_to,
            $gap['invoices'],
            $gap['paid'],
            $gap['payments'],
            $gap['mismatch']
        ),
        $ok ? '' : 'ইনভয়েস/পেমেন্ট ম্যানুয়ালি মিলিয়ে দেখুন — অটো কাটা হয় না',
        $ok ? '' : '/modules/sales/history.php',
    ];
} else {
    $cards[] = ['বিক্রয় paid vs sale_payments', true, 'sales / sale_payments টেবিল নেই — স্কিপ', '', ''];
}

/* 2) Negative stock */
if (ame_ic_table_exists($db, 'products')) {
    $neg = 0;
    $r = @$db->query('SELECT COUNT(*) AS c FROM products WHERE stock < 0');
    if ($r) {
        $neg = (int)($r->fetch_assoc()['c'] ?? 0);
    }
    $ok = $neg === 0;
    $cards[] = [
        'নেগেটিভ স্টক',
        $ok,
        $ok ? 'নেগেটিভ স্টক পণ্য নেই' : ("নেগেটিভ স্টক পণ্য: {$neg}টি"),
        $ok ? '' : 'স্টক অ্যাডজাস্টমেন্ট / ইনভেন্টরি থেকে ম্যানুয়ালি ঠিক করুন',
        $ok ? '' : '/modules/inventory/add_product.php',
    ];
} else {
    $cards[] = ['নেগেটিভ স্টক', true, 'products টেবিল নেই — স্কিপ', '', ''];
}

/* 3) Customer ledger vs receivable drift (sample) */
if (ame_ic_table_exists($db, 'customers') && ame_ic_table_exists($db, 'customer_ledger')) {
    $drift = ame_money_customer_drift_count($db);
    $ok = $drift === 0;
    $cards[] = [
        'কাস্টমার লেজার ড্রিফট',
        $ok,
        $ok ? 'কোনো অমিল নেই' : ("অমিল কাস্টমার: {$drift}। ব্যালেন্স এখান থেকে বদলানো হয় না।"),
        $ok ? '' : 'টাকা মিল পেজ দেখুন — এই চেক নিজে থেকে টাকা বদলায় না',
        '/modules/settings/money_guard.php',
    ];
} else {
    $cards[] = ['কাস্টমার লেজার ড্রিফট', true, 'স্কিমা অনুপস্থিত — স্কিপ', '', ''];
}

/* 4) Orphan sale_items */
if (ame_ic_table_exists($db, 'sale_items') && ame_ic_table_exists($db, 'sales')) {
    $orph = 0;
    $r = @$db->query(
        'SELECT COUNT(*) AS c FROM sale_items si
         LEFT JOIN sales s ON s.id = si.sale_id
         WHERE s.id IS NULL'
    );
    if ($r) {
        $orph = (int)($r->fetch_assoc()['c'] ?? 0);
    }
    $ok = $orph === 0;
    $cards[] = [
        'এতিম sale_items',
        $ok,
        $ok ? 'sale ছাড়া আইটেম নেই' : ("এতিম আইটেম সারি: {$orph}"),
        $ok ? '' : 'ডাটাবেজ অডিট / সাপোর্ট — অটো ডিলিট করা হয় না',
        '/modules/reports/audit_trail.php',
    ];
} else {
    $cards[] = ['এতিম sale_items', true, 'টেবিল নেই — স্কিপ', '', ''];
}
?>
<style>
.ic-wrap{padding:15px;max-width:720px;margin:0 auto;}
.ic-head{background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(11,30,77,.08);padding:16px;margin-bottom:12px;}
.ic-head h3{margin:0;color:#0B1E4D;font-size:18px;}
.ic-head p{margin:6px 0 0;color:#64748B;font-size:13px;line-height:1.5;}
.ic-form{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;align-items:end;}
.ic-form label{font-size:12px;color:#64748B;display:grid;gap:4px;}
.ic-form input{padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;min-height:44px;}
.ic-form button{padding:12px 16px;border:0;border-radius:10px;background:#0B1E4D;color:#fff;font-weight:800;min-height:44px;cursor:pointer;}
.ic-card{background:#fff;border-radius:14px;padding:14px 16px;margin-bottom:10px;box-shadow:0 4px 14px rgba(11,30,77,.06);border-left:5px solid #94a3b8;}
.ic-card.ic-ok{border-left-color:#0E8F52;}
.ic-card.ic-bad{border-left-color:#C62828;}
.ic-top{display:flex;justify-content:space-between;gap:10px;align-items:center;}
.ic-top b{color:#0B1E4D;font-size:14px;}
.ic-badge{font-size:12px;font-weight:800;padding:4px 10px;border-radius:999px;background:#F1F5F9;}
.ic-ok .ic-badge{background:#e8f5e9;color:#0E8F52;}
.ic-bad .ic-badge{background:#ffebee;color:#C62828;}
.ic-card p{margin:8px 0 0;color:#475569;font-size:13px;line-height:1.5;}
.ic-fix{margin-top:8px;padding:10px;border-radius:10px;background:#fff8e1;color:#9a5b00;font-size:12.5px;line-height:1.5;}
.ic-fix a{color:#0B1E4D;font-weight:700;}
.ic-note{font-size:12.5px;color:#64748B;margin-top:8px;line-height:1.5;}
.ic-links{display:grid;gap:8px;margin-top:12px;}
.ic-links a{display:block;text-align:center;padding:12px;border-radius:10px;background:#fff;color:#0B1E4D;border:1px solid #90caf9;font-weight:800;text-decoration:none;min-height:44px;box-sizing:border-box;}
</style>
<div class="ic-wrap">
  <div class="ic-head">
    <h3>হিসাব সততা যাচাই</h3>
    <p>শুধু পড়ে দেখায়। টাকা বা স্টক অটো বদলায় না — সংশোধন লিংক ম্যানুয়াল।</p>
    <form class="ic-form" method="get" action="">
      <label>থেকে<input type="date" name="from" value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>"></label>
      <label>পর্যন্ত<input type="date" name="to" value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>"></label>
      <button type="submit">চেক চালান</button>
    </form>
    <p class="ic-note">ডিফল্ট আজকের তারিখ। বিক্রয় মিল ও কাস্টমার লেজার পুরো তালিকা পড়ে। কোনো অ্যামাউন্ট লেখে না।</p>
  </div>
  <?php foreach ($cards as $c) {
      ame_ic_card($c[0], $c[1], $c[2], $c[3], $c[4]);
  } ?>
  <div class="ic-links">
    <a href="/modules/settings/money_guard.php">৳ টাকা মিল</a>
    <a href="/modules/reports/day_closing.php">📅 দৈনিক ক্লোজিং</a>
    <a href="/modules/settings/tools.php">🛠️ টুলস</a>
  </div>
</div>
<?php include '../../partials/footer.php'; ?>
