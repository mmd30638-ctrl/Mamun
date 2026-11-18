<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
ensure_accounts_schema($db);

$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$accounts = $db->query("SELECT * FROM accounts WHERE status = 'active' ORDER BY balance DESC")->fetch_all(MYSQLI_ASSOC);
$total_balance = array_sum(array_column($accounts, 'balance'));

$income_stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) c FROM account_ledger WHERE type='credit' AND created_at BETWEEN ? AND ?");
$income_stmt->bind_param('ss', $month_start, $month_end);
$income_stmt->execute();
$total_income = (float)($income_stmt->get_result()->fetch_assoc()['c'] ?? 0);

$expense_stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) c FROM account_ledger WHERE type='debit' AND created_at BETWEEN ? AND ?");
$expense_stmt->bind_param('ss', $month_start, $month_end);
$expense_stmt->execute();
$total_expense = (float)($expense_stmt->get_result()->fetch_assoc()['c'] ?? 0);

$net_cash_flow = $total_income - $total_expense;

// ================= খরচের ভাঙন (ক্যাটাগরি-চিহ্নিত) =================
$expense_breakdown_stmt = $db->prepare(
    "SELECT c.name, COALESCE(SUM(l.amount),0) as total
     FROM account_ledger l JOIN expense_categories c ON l.category_id = c.id
     WHERE l.type = 'debit' AND l.created_at BETWEEN ? AND ?
     GROUP BY l.category_id ORDER BY total DESC LIMIT 8"
);
$expense_breakdown_stmt->bind_param('ss', $month_start, $month_end);
$expense_breakdown_stmt->execute();
$expense_breakdown = $expense_breakdown_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ================= সাপ্তাহিক আয়-ব্যয় (এই মাসের প্রতি সপ্তাহ) =================
$weekly_stmt = $db->prepare(
    "SELECT FLOOR((DAYOFMONTH(created_at)-1)/7) as week_no,
        COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE 0 END),0) as income,
        COALESCE(SUM(CASE WHEN type='debit' THEN amount ELSE 0 END),0) as expense
     FROM account_ledger WHERE created_at BETWEEN ? AND ? GROUP BY week_no ORDER BY week_no"
);
$weekly_stmt->bind_param('ss', $month_start, $month_end);
$weekly_stmt->execute();
$weekly_rows = $weekly_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$week_labels = []; $week_income = []; $week_expense = [];
foreach ($weekly_rows as $w) {
    $week_labels[] = (((int)$w['week_no']) * 7 + 1) . '-' . min((((int)$w['week_no']) + 1) * 7, 31);
    $week_income[] = (float)$w['income'];
    $week_expense[] = (float)$w['expense'];
}

// ================= সাম্প্রতিক লেনদেন (ব্যবসা + ব্যক্তিগত — সব একসাথে) =================
$recent = $db->query(
    "SELECT l.*, a.account_name, c.name as category_name, m.name as merchant_name
     FROM account_ledger l
     LEFT JOIN accounts a ON l.account_id = a.id
     LEFT JOIN expense_categories c ON l.category_id = c.id
     LEFT JOIN merchants m ON l.merchant_id = m.id
     ORDER BY l.id DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

// ================= শীর্ষ দোকান/মার্চেন্ট (এই মাস) =================
$top_merchants_stmt = $db->prepare(
    "SELECT m.name, COALESCE(SUM(l.amount),0) as total
     FROM account_ledger l JOIN merchants m ON l.merchant_id = m.id
     WHERE l.type='debit' AND l.created_at BETWEEN ? AND ?
     GROUP BY l.merchant_id ORDER BY total DESC LIMIT 5"
);
$top_merchants_stmt->bind_param('ss', $month_start, $month_end);
$top_merchants_stmt->execute();
$top_merchants = $top_merchants_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ================= পরিবারের সদস্যভিত্তিক খরচ (এই মাস) =================
$member_spend_stmt = $db->prepare(
    "SELECT fm.name, COALESCE(SUM(l.amount),0) as total
     FROM account_ledger l JOIN family_members fm ON l.member_id = fm.id
     WHERE l.type='debit' AND l.created_at BETWEEN ? AND ?
     GROUP BY l.member_id ORDER BY total DESC"
);
$member_spend_stmt->bind_param('ss', $month_start, $month_end);
$member_spend_stmt->execute();
$member_spend = $member_spend_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$member_total = array_sum(array_column($member_spend, 'total')) ?: 1;

$ref_type_labels = [
    'loan_disbursement' => '🏦 লোন গ্রহণ', 'loan_installment' => '🏦 লোনের কিস্তি', 'loan_charge' => '🏦 লোনের চার্জ',
    'purchase' => '📦 পণ্য ক্রয়', 'supplier_payment' => '🚚 সাপ্লায়ার পেমেন্ট',
    'manual_income' => '💰 জমা', 'manual_expense' => '💸 খরচ', 'transfer' => '↔️ ট্রান্সফার',
    'customer_advance' => '👤 কাস্টমার অ্যাডভান্স', 'sale_return' => '🔄 বিক্রয় ফেরত',
    'salary_payment' => '👷 বেতন পরিশোধ', 'shared_bill' => '🤝 ভাগের বিল', 'opening_balance' => '🏁 প্রারম্ভিক ব্যালেন্স',
    'reversal' => '↩️ রিভার্সাল',
];

include '../../partials/header.php';
?>
<style>
    .fd-wrap { padding: 14px; background: var(--brand-bg, #F4F7FC); }
    .fd-header { margin-bottom:16px; }
    .fd-header h2 { margin:0; color:var(--dark-blue); font-size:19px; }
    .fd-header p { margin:4px 0 0; color:#64748B; font-size:12px; }
    .fd-add-btn { background: var(--brand-gradient, linear-gradient(135deg,var(--dark-blue),var(--brand-accent))); color:#fff; text-decoration:none; padding:10px 16px; border-radius:10px; font-weight:800; font-size:12.5px; display:inline-block; margin-top:10px; margin-right:6px; }
    .fd-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:16px; }
    .fd-kpi-box { border-radius:14px; padding:14px; color:#fff; box-shadow:0 6px 16px rgba(11,30,77,0.15); }
    .fd-kpi-box .lbl { font-size:11px; opacity:.85; }
    .fd-kpi-box .val { font-size:17px; font-weight:800; margin-top:5px; }
    .fd-section { background:#fff; border-radius:16px; padding:16px; box-shadow:0 4px 18px rgba(11,30,77,0.08); margin-bottom:14px; }
    .fd-section h3 { margin:0 0 12px; color:var(--dark-blue); font-size:14px; }
    .fd-row { display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid #F1F5F9; font-size:12.5px; }
    .fd-row:last-child { border-bottom:none; }
    .fd-member-bar-bg { background:#F1F5F9; border-radius:5px; height:6px; margin-top:4px; overflow:hidden; }
    .fd-member-bar-fill { background:var(--brand-gradient, linear-gradient(90deg,var(--dark-blue),var(--brand-accent))); height:100%; }
</style>

<div class="fd-wrap">
    <?php if (!empty($_SESSION['success'])): ?>
        <div style="background:var(--brand-success-soft,#E8F5E9); color:var(--dark-green,#0B8043); padding:12px 16px; border-radius:10px; margin-bottom:14px; font-size:13px;">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <div class="fd-header">
        <h2>👋 MyFinance ড্যাশবোর্ড</h2>
        <p><?php echo date('d F Y'); ?> — সম্পূর্ণ আর্থিক ওভারভিউ (ব্যবসা + ব্যক্তিগত একসাথে, একই অ্যাকাউন্টে)</p>
        <a href="add_transaction.php" class="fd-add-btn acc-print-hide">➕ নতুন লেনদেন</a>
        <a href="transfer.php" class="fd-add-btn acc-print-hide">🔄 ট্রান্সফার</a>
        <a href="list.php" class="fd-add-btn acc-print-hide" style="background:#EEF2FF; color:var(--dark-blue);">💼 সব অ্যাকাউন্ট</a>
    </div>

    <div class="fd-kpi-grid">
        <div class="fd-kpi-box" style="background:linear-gradient(135deg,var(--brand-success,#0E8F52),#2ECC71);"><div class="lbl">মোট ব্যালেন্স (সব অ্যাকাউন্ট)</div><div class="val"><?php echo ame_balance_label($total_balance); ?></div></div>
        <div class="fd-kpi-box" style="background:linear-gradient(135deg,var(--brand-accent,#0091EA),#4FC3F7);"><div class="lbl">এই মাসের মোট জমা</div><div class="val">৳<?php echo number_format($total_income, 0); ?></div></div>
        <div class="fd-kpi-box" style="background:linear-gradient(135deg,var(--brand-danger,#D32F2F),#E57373);"><div class="lbl">এই মাসের মোট খরচ</div><div class="val">৳<?php echo number_format($total_expense, 0); ?></div></div>
        <div class="fd-kpi-box" style="background:linear-gradient(135deg,#7B1FA2,#AB47BC);"><div class="lbl">নিট ক্যাশ ফ্লো</div><div class="val"><?php echo $net_cash_flow >= 0 ? '+' : ''; ?>৳<?php echo number_format($net_cash_flow, 0); ?></div></div>
    </div>

    <div class="fd-section">
        <h3>🏦 আমার সব অ্যাকাউন্ট</h3>
        <?php if (empty($accounts)): ?>
            <p style="text-align:center; color:#94A3B8; font-size:12.5px; padding:16px 0;">এখনো কোনো অ্যাকাউন্ট নেই। <a href="add.php" style="color:var(--dark-blue);">+ যোগ করুন</a></p>
        <?php else: foreach ($accounts as $a): ?>
        <div class="fd-row">
            <span>💰 <?php echo htmlspecialchars($a['account_name']); ?></span>
            <strong style="color:var(--dark-blue);"><?php echo ame_balance_label((float)$a['balance']); ?></strong>
        </div>
        <?php endforeach; endif; ?>
        <div class="fd-row" style="border-top:2px solid #F1F5F9; font-weight:800; margin-top:4px;">
            <span>মোট ব্যালেন্স</span><span style="color:var(--dark-green,#0B8043);"><?php echo ame_balance_label($total_balance); ?></span>
        </div>
    </div>

    <?php if (!empty($week_labels)): ?>
    <div class="fd-section">
        <h3>📊 জমা বনাম খরচ (এই মাস)</h3>
        <div style="position:relative; height:200px;"><canvas id="incomeExpenseChart"></canvas></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($expense_breakdown)): ?>
    <div class="fd-section">
        <h3>🍩 খরচের ভাঙন (এই মাস)</h3>
        <div style="position:relative; height:220px; max-width:280px; margin:0 auto 12px;"><canvas id="expenseDonut"></canvas></div>
        <?php foreach ($expense_breakdown as $e): ?>
        <div class="fd-row"><span>🏷️ <?php echo htmlspecialchars($e['name']); ?></span><strong>৳<?php echo number_format((float)$e['total'], 0); ?></strong></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="fd-section">
        <h3>🕒 সাম্প্রতিক লেনদেন <span style="font-weight:400; color:#94A3B8; font-size:11px;">(দোকান + ব্যক্তিগত)</span></h3>
        <?php if (empty($recent)): ?>
            <p style="text-align:center; color:#94A3B8; font-size:12.5px; padding:16px 0;">এখনো কোনো লেনদেন নেই।</p>
        <?php else: foreach ($recent as $t):
            $is_credit = $t['type'] === 'credit';
            $label = $t['category_name'] ? ('🏷️ ' . $t['category_name']) : ($ref_type_labels[$t['ref_type']] ?? ucfirst($t['ref_type']));
        ?>
        <div class="fd-row">
            <span><?php echo $label; ?><br><small style="color:#94A3B8;"><?php echo date('d M', strtotime($t['created_at'])); ?> • <?php echo htmlspecialchars($t['account_name'] ?? ''); ?><?php if ($t['merchant_name']): ?> • <?php echo htmlspecialchars($t['merchant_name']); ?><?php endif; ?></small></span>
            <strong style="color:<?php echo $is_credit ? 'var(--dark-green,#0B8043)' : 'var(--red-alert,#C62828)'; ?>;"><?php echo $is_credit ? '+' : '-'; ?>৳<?php echo number_format((float)$t['amount'], 0); ?></strong>
        </div>
        <?php endforeach; endif; ?>
        <a href="list.php" style="display:block; text-align:center; margin-top:10px; color:var(--brand-accent,#0091EA); text-decoration:none; font-size:12px;">সব লেনদেন দেখুন →</a>
    </div>

    <?php if (!empty($top_merchants)): ?>
    <div class="fd-section">
        <h3>🏪 শীর্ষ দোকান/মার্চেন্ট (এই মাস)</h3>
        <?php foreach ($top_merchants as $m): ?>
        <div class="fd-row"><span><?php echo htmlspecialchars($m['name']); ?></span><strong>৳<?php echo number_format((float)$m['total'], 0); ?></strong></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($member_spend)): ?>
    <div class="fd-section">
        <h3>👨‍👩‍👧 সদস্যভিত্তিক খরচ (এই মাস)</h3>
        <?php foreach ($member_spend as $m): $pct = round(($m['total'] / $member_total) * 100); ?>
        <div style="margin-bottom:10px;">
            <div style="display:flex; justify-content:space-between; font-size:12.5px;"><span><?php echo htmlspecialchars($m['name']); ?></span><strong>৳<?php echo number_format((float)$m['total'], 0); ?> (<?php echo $pct; ?>%)</strong></div>
            <div class="fd-member-bar-bg"><div class="fd-member-bar-fill" style="width:<?php echo $pct; ?>%;"></div></div>
        </div>
        <?php endforeach; ?>
        <a href="family_members.php" style="display:block; text-align:center; margin-top:6px; color:var(--brand-accent,#0091EA); text-decoration:none; font-size:12px;">সদস্য ব্যবস্থাপনা →</a>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
<?php if (!empty($week_labels)): ?>
var el1 = document.getElementById('incomeExpenseChart');
if (el1 && typeof Chart !== 'undefined') {
    new Chart(el1, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($week_labels, JSON_UNESCAPED_UNICODE); ?>,
            datasets: [
                { label: 'জমা', data: <?php echo json_encode($week_income); ?>, backgroundColor: '#0E8F52' },
                { label: 'খরচ', data: <?php echo json_encode($week_expense); ?>, backgroundColor: '#D32F2F' }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });
}
<?php endif; ?>
<?php if (!empty($expense_breakdown)): ?>
var el2 = document.getElementById('expenseDonut');
if (el2 && typeof Chart !== 'undefined') {
    new Chart(el2, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($expense_breakdown, 'name'), JSON_UNESCAPED_UNICODE); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($expense_breakdown, 'total')); ?>,
                backgroundColor: ['#0091EA','#D32F2F','#FF9800','#0E8F52','#7B1FA2','#00838F','#455A64','#F57C00']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
}
<?php endif; ?>
</script>
<?php include '../../partials/footer.php'; ?>
