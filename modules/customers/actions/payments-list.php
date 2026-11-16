<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php';
}
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);

$today = date('Y-m-d');
$range = (string)($_GET['range'] ?? 'month');
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$search = trim((string)($_GET['q'] ?? $_GET['search'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $range = in_array($range, ['today', 'yesterday', 'week', 'month', 'all'], true) ? $range : 'month';
    if ($range === 'today') {
        $from = $to = $today;
    } elseif ($range === 'yesterday') {
        $from = $to = date('Y-m-d', strtotime('-1 day'));
    } elseif ($range === 'week') {
        $from = date('Y-m-d', strtotime('-6 days'));
        $to = $today;
    } elseif ($range === 'all') {
        $from = '2000-01-01';
        $to = $today;
    } else {
        $range = 'month';
        $from = date('Y-m-01');
        $to = $today;
    }
} else {
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $week_from = date('Y-m-d', strtotime('-6 days'));
    $month_from = date('Y-m-01');
    if ($from === $today && $to === $today) {
        $range = 'today';
    } elseif ($from === $yesterday && $to === $yesterday) {
        $range = 'yesterday';
    } elseif ($from === $week_from && $to === $today) {
        $range = 'week';
    } elseif ($from === $month_from && $to === $today) {
        $range = 'month';
    } elseif ($from === '2000-01-01' && $to === $today) {
        $range = 'all';
    } else {
        $range = 'custom';
    }
}

$where = 'p.payment_date BETWEEN ? AND ?';
$types = 'ss';
$params = [$from, $to];
if ($search !== '') {
    $where .= ' AND (c.name LIKE ? OR c.customer_code LIKE ? OR c.phone LIKE ? OR IFNULL(p.reference_no, "") LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}

$sql = "SELECT p.id, p.customer_id, p.amount, IFNULL(p.discount, 0) AS discount, p.payment_date,
               p.method, p.reference_no, p.note, p.reversed_at, c.name AS customer_name, c.customer_code, c.phone,
               c.receivable_balance
        FROM customer_payments p
        JOIN customers c ON c.id = p.customer_id
        WHERE $where
        ORDER BY p.payment_date DESC, p.id DESC
        LIMIT 300";
$stmt = $db->prepare($sql);
$payments = [];
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$sum_sql = "SELECT COUNT(*) AS n, COALESCE(SUM(p.amount), 0) AS paid, COALESCE(SUM(IFNULL(p.discount, 0)), 0) AS discount
            FROM customer_payments p
            JOIN customers c ON c.id = p.customer_id
            WHERE $where
              AND (p.reversed_at IS NULL OR p.reversed_at = '0000-00-00 00:00:00')";
$sum = ['n' => 0, 'paid' => 0, 'discount' => 0];
$sum_stmt = $db->prepare($sum_sql);
if ($sum_stmt) {
    $sum_stmt->bind_param($types, ...$params);
    $sum_stmt->execute();
    $row = $sum_stmt->get_result()->fetch_assoc();
    if ($row) {
        $sum = $row;
    }
    $sum_stmt->close();
}

$bill = 0.0;
$bill_sql = "SELECT COALESCE(SUM(s.net_payable), 0) AS bill
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE s.status = 'completed' AND DATE(s.completed_at) BETWEEN ? AND ?";
$bill_types = 'ss';
$bill_params = [$from, $to];
if ($search !== '') {
    $bill_sql .= ' AND (c.name LIKE ? OR c.customer_code LIKE ? OR c.phone LIKE ?)';
    $bill_types .= 'sss';
    $like = '%' . $search . '%';
    array_push($bill_params, $like, $like, $like);
}
$bill_stmt = $db->prepare($bill_sql);
if ($bill_stmt) {
    $bill_stmt->bind_param($bill_types, ...$bill_params);
    $bill_stmt->execute();
    $bill_row = $bill_stmt->get_result()->fetch_assoc();
    $bill = (float)($bill_row['bill'] ?? 0);
    $bill_stmt->close();
}

$paid = round((float)$sum['paid'], 2);
$discount = round((float)$sum['discount'], 2);
$customer_ids = [];
foreach ($payments as $p) {
    $customer_ids[(int)$p['customer_id']] = (float)$p['receivable_balance'];
}
$one_customer = count($customer_ids) === 1;
$due = $one_customer ? round((float)reset($customer_ids), 2) : round($bill - $paid, 2);
$due_label = $one_customer ? 'বর্তমান বাকি' : 'এই সময়ে বাকি';
if ($due < -0.009) {
    $due_label = $one_customer ? 'বর্তমান জমা' : 'এই সময়ে জমা';
}

$method_labels = [
    'CASH' => 'নগদ',
    'BKASH' => 'বিকাশ',
    'NAGAD' => 'নগদ (মোবাইল)',
    'BANK' => 'ব্যাংক',
    'CHEQUE' => 'চেক',
];
$keep = ['from' => $from, 'to' => $to, 'q' => $search, 'range' => $range];
$back = http_build_query(array_filter($keep, static fn($v) => $v !== ''));
$chip = static function (string $key, string $label) use ($range, $search): string {
    $q = http_build_query(['range' => $key, 'q' => $search]);
    $on = $range === $key ? ' is-on' : '';
    return '<a class="' . trim($on) . '" href="payments-list.php?' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
};

$ame_body_class = 'ame-pay-list-page';
include __DIR__ . '/../../../partials/header.php';
$ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.355';
?>
<link rel="stylesheet" href="/assets/css/customer-pay-list.css?v=<?php echo htmlspecialchars((string)$ver, ENT_QUOTES, 'UTF-8'); ?>">
<div class="cpl">
    <div class="cpl-head">
        <div>
            <h1>ক্রেতার পেমেন্ট</h1>
            <p><?php echo (int)$sum['n']; ?>টি পেমেন্ট · <?php echo htmlspecialchars($from === '2000-01-01' ? 'সব তারিখ' : ($from . ' — ' . $to), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <a class="cpl-new" href="../payment-receive.php">+ পেমেন্ট নিন</a>
    </div>

    <form class="cpl-filters" method="get" action="payments-list.php" id="cplFilter">
        <input type="hidden" name="range" value="custom">
        <div class="cpl-ranges">
            <?php echo $chip('today', 'আজ'); ?>
            <?php echo $chip('yesterday', 'গতকাল'); ?>
            <?php echo $chip('week', '৭ দিন'); ?>
            <?php echo $chip('month', 'এই মাস'); ?>
            <?php echo $chip('all', 'সব'); ?>
        </div>
        <div class="cpl-grid">
            <label class="cpl-field"><span>শুরুর তারিখ</span><input type="date" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>"></label>
            <label class="cpl-field"><span>শেষ তারিখ</span><input type="date" name="to" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>"></label>
            <label class="cpl-field"><span>ক্রেতা খুঁজুন</span><input type="search" name="q" id="cplQ" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="নাম, কোড, মোবাইল বা রেফারেন্স" autocomplete="off"></label>
            <div class="cpl-actions">
                <button class="cpl-go" type="submit">খুঁজুন</button>
                <a class="cpl-clear" href="payments-list.php?range=month">মুছুন</a>
            </div>
        </div>
    </form>

    <div class="cpl-kpis">
        <article class="cpl-kpi bill"><span>মোট বিল</span><b>৳<?php echo number_format($bill, 2); ?></b><small>এই ফিল্টারের বিক্রয়</small></article>
        <article class="cpl-kpi paid"><span>পরিশোধিত টাকা</span><b>৳<?php echo number_format($paid, 2); ?></b><?php if ($discount > 0.009): ?><small>ডিসকাউন্ট ৳<?php echo number_format($discount, 2); ?></small><?php endif; ?></article>
        <article class="cpl-kpi due<?php echo $due < -0.009 ? ' is-credit' : ''; ?>"><span><?php echo htmlspecialchars($due_label, ENT_QUOTES, 'UTF-8'); ?></span><b>৳<?php echo number_format(abs($due), 2); ?></b></article>
    </div>
    <?php if (count($payments) >= 300): ?>
        <p class="cpl-note">প্রথম ৩০০টি দেখাচ্ছে। তারিখ বা ক্রেতা দিয়ে আরও সরু করুন।</p>
    <?php endif; ?>

    <div class="cpl-sheet" id="cplSheet">
        <div class="cpl-row cpl-h">
            <span>তারিখ</span><span>ক্রেতা</span><span>পরিশোধ</span><span>মাধ্যম</span><span>রেফারেন্স</span><span>কাজ</span>
        </div>
        <?php if (!$payments): ?>
            <div class="cpl-empty">এই খোঁজে কোনো পেমেন্ট নেই।</div>
        <?php endif; ?>
        <?php foreach ($payments as $p):
            $pid = (int)$p['id'];
            $method = $method_labels[$p['method']] ?? (string)$p['method'];
            $who = trim((string)$p['customer_name'] . ' ' . (string)$p['customer_code'] . ' ' . (string)$p['phone'] . ' ' . (string)$p['reference_no']);
            $who_l = function_exists('mb_strtolower') ? mb_strtolower($who, 'UTF-8') : $who;
            $cust_q = trim((string)$p['customer_code']) !== '' ? (string)$p['customer_code'] : (string)$p['customer_name'];
            $cust_href = 'payments-list.php?' . http_build_query(['range' => 'all', 'q' => $cust_q]);
        ?>
        <article class="cpl-row" data-find="<?php echo htmlspecialchars($who_l, ENT_QUOTES, 'UTF-8'); ?>">
            <div data-label="তারিখ"><?php echo htmlspecialchars((string)$p['payment_date'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="cpl-who" data-label="ক্রেতা">
                <strong><?php echo htmlspecialchars((string)$p['customer_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <small><?php echo htmlspecialchars((string)$p['customer_code'], ENT_QUOTES, 'UTF-8'); ?><?php echo $p['phone'] !== '' ? ' · ' . htmlspecialchars((string)$p['phone'], ENT_QUOTES, 'UTF-8') : ''; ?></small>
                <a class="cpl-by" href="<?php echo htmlspecialchars($cust_href, ENT_QUOTES, 'UTF-8'); ?>">এই ক্রেতার সব পেমেন্ট</a>
            </div>
            <div class="cpl-money" data-label="পরিশোধ">৳<?php echo number_format((float)$p['amount'], 2); ?></div>
            <div data-label="মাধ্যম"><?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="cpl-meta" data-label="রেফারেন্স"><?php echo htmlspecialchars((string)($p['reference_no'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="cpl-ops">
                <a class="view" href="../receipt.php?payment_id=<?php echo $pid; ?>">দেখুন</a>
                <?php if (function_exists('ame_customer_payment_is_reversed') && ame_customer_payment_is_reversed($p)): ?>
                    <span>বাতিল</span>
                <?php else: ?>
                    <a href="../payment-edit.php?id=<?php echo $pid; ?>&amp;back=<?php echo rawurlencode($back); ?>">এডিট</a>
                    <a href="../receipt.php?payment_id=<?php echo $pid; ?>#cpayReverse">বাতিল</a>
                <?php endif; ?>
                <a href="../receipt.php?payment_id=<?php echo $pid; ?>&amp;print=1">প্রিন্ট</a>
                <a class="share" href="../receipt.php?payment_id=<?php echo $pid; ?>&amp;share=1">শেয়ার</a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('cplFilter');
    var box = document.getElementById('cplQ');
    if (!form || !box) return;
    var timer = 0;
    box.addEventListener('input', function () {
        var q = box.value.trim().toLowerCase();
        document.querySelectorAll('#cplSheet .cpl-row[data-find]').forEach(function (row) {
            var hay = row.getAttribute('data-find') || '';
            row.hidden = q !== '' && hay.indexOf(q) === -1;
        });
        window.clearTimeout(timer);
        timer = window.setTimeout(function () { form.requestSubmit ? form.requestSubmit() : form.submit(); }, 450);
    });
})();
</script>
<?php $ame_pay_list_white = true; include __DIR__ . '/../../../partials/footer.php'; ?>
