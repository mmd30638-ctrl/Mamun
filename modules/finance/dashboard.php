<?php
/**
 * MyFinance dashboard — same accounts / account_ledger as modules/accounts.
 * This is the personal+business overview UI, not a second ledger.
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/account_functions.php';
require_once __DIR__ . '/../../includes/pf_functions.php';
admin_only();
ensure_pf_schema($db);

$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// ⭐ একীভূতকরণ: সব accounts (ব্যবসা + ব্যক্তিগত — এখন আর আলাদা না)
$accounts = $db->query("SELECT * FROM accounts WHERE status = 'active' ORDER BY balance DESC")->fetch_all(MYSQLI_ASSOC);
$total_balance = array_sum(array_column($accounts, 'balance'));

// এই মাসের মোট জমা (credit) ও খরচ (debit) — লোন গ্রহণ, বিক্রয়, ব্যক্তিগত আয় সবকিছুই "জমা" হিসেবে গণ্য
$income_stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) c FROM account_ledger WHERE type='credit' AND created_at BETWEEN ? AND ?");
$income_stmt->bind_param('ss', $month_start, $month_end);
$income_stmt->execute();
$total_income = (float)($income_stmt->get_result()->fetch_assoc()['c'] ?? 0);

$expense_stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) c FROM account_ledger WHERE type='debit' AND created_at BETWEEN ? AND ?");
$expense_stmt->bind_param('ss', $month_start, $month_end);
$expense_stmt->execute();
$total_expense = (float)($expense_stmt->get_result()->fetch_assoc()['c'] ?? 0);

$net_cash_flow = $total_income - $total_expense;

// ================= খরচের ভাঙন (শুধু ক্যাটাগরি-চিহ্নিত ব্যক্তিগত খরচ) =================
$expense_breakdown_stmt = $db->prepare(
    "SELECT c.name, c.icon, COALESCE(SUM(l.amount),0) as total
     FROM account_ledger l JOIN pf_categories c ON l.category_id = c.id
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

// ================= সাম্প্রতিক লেনদেন (ব্যবসা + ব্যক্তিগত — সবকিছু একসাথে) =================
$recent = $db->query(
    "SELECT l.*, a.account_name, c.name as category_name, c.icon as category_icon, m.name as merchant_name
     FROM account_ledger l
     LEFT JOIN accounts a ON l.account_id = a.id
     LEFT JOIN pf_categories c ON l.category_id = c.id
     LEFT JOIN pf_merchants m ON l.merchant_id = m.id
     ORDER BY l.id DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

// ================= শীর্ষ Merchant (এই মাস, শুধু ব্যক্তিগত খরচ) =================
$top_merchants_stmt = $db->prepare(
    "SELECT m.name, COALESCE(SUM(l.amount),0) as total
     FROM account_ledger l JOIN pf_merchants m ON l.merchant_id = m.id
     WHERE l.type='debit' AND l.created_at BETWEEN ? AND ?
     GROUP BY l.merchant_id ORDER BY total DESC LIMIT 5"
);
$top_merchants_stmt->bind_param('ss', $month_start, $month_end);
$top_merchants_stmt->execute();
$top_merchants = $top_merchants_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ================= পরিবারের সদস্যভিত্তিক খরচ (এই মাস) =================
$member_spend_stmt = $db->prepare(
    "SELECT fm.name, COALESCE(SUM(l.amount),0) as total
     FROM account_ledger l JOIN pf_family_members fm ON l.member_id = fm.id
     WHERE l.type='debit' AND l.created_at BETWEEN ? AND ?
     GROUP BY l.member_id ORDER BY total DESC"
);
$member_spend_stmt->bind_param('ss', $month_start, $month_end);
$member_spend_stmt->execute();
$member_spend = $member_spend_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$member_total = array_sum(array_column($member_spend, 'total')) ?: 1;

$account_icons = ['cash' => '💵', 'bank' => '🏦', 'mobile_banking' => '📱', 'card' => '💳', 'savings' => '🐷', 'other' => '📦'];

// ================= ref_type → বন্ধুত্বপূর্ণ বাংলা লেবেল =================
$ref_type_labels = [
    'loan_disbursement' => '🏦 লোন গ্রহণ', 'loan_installment' => '🏦 লোনের কিস্তি', 'loan_charge' => '🏦 লোনের চার্জ',
    'purchase' => '📦 পণ্য ক্রয়', 'supplier_payment' => '🚚 সাপ্লায়ার পেমেন্ট',
    'personal_income' => '💰 ব্যক্তিগত আয়', 'personal_expense' => '💸 ব্যক্তিগত খরচ',
    'personal_transfer' => '↔️ ট্রান্সফার', 'personal_refund' => '↩️ রিফান্ড',
    'transfer' => '↔️ ট্রান্সফার', 'salary_payment' => '👷 বেতন পরিশোধ',
    'manual_income' => '💰 আয়', 'manual_expense' => '💸 খরচ', 'customer_advance' => '👤 কাস্টমার অ্যাডভান্স',
    'sale_return' => '🔄 বিক্রয় ফেরত', 'shared_bill' => '🤝 ভাগের বিল', 'opening_balance' => '🏁 প্রারম্ভিক ব্যালেন্স',
];

include __DIR__ . '/../../partials/header.php';
?>
<style>
    :root { --pf-navy:#0B1E4D; --pf-green:#0B8043; --pf-bg:#F4F7FC; }
    .pf-wrap { padding: 14px; background: var(--pf-bg); }
    .pf-header { margin-bottom:16px; }
    .pf-header h2 { margin:0; color:var(--pf-navy); font-size:19px; }
    .pf-header p { margin:4px 0 0; color:#64748B; font-size:12px; }
    .pf-add-btn { background: linear-gradient(135deg,var(--pf-navy),#0091EA); color:#fff; text-decoration:none; padding:10px 16px; border-radius:10px; font-weight:800; font-size:12.5px; display:inline-block; margin-top:10px; }

    .pf-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:16px; }
    .pf-kpi-box { border-radius:14px; padding:14px; color:#fff; box-shadow:0 6px 16px rgba(11,30,77,0.15); }
    .pf-kpi-box .lbl { font-size:11px; opacity:.85; }
    .pf-kpi-box .val { font-size:17px; font-weight:800; margin-top:5px; }

    .pf-section { background:#fff; border-radius:16px; padding:16px; box-shadow:0 4px 18px rgba(11,30,77,0.08); margin-bottom:14px; }
    .pf-section h3 { margin:0 0 12px; color:var(--pf-navy); font-size:14px; }
    .pf-acc-row { display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid #F1F5F9; font-size:13px; }
    .pf-acc-row:last-child { border-bottom:none; }

    .pf-txn-row { display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid #F1F5F9; font-size:12.5px; }
    .pf-merchant-row { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #F1F5F9; font-size:12.5px; }
    .pf-member-bar-bg { background:#F1F5F9; border-radius:5px; height:6px; margin-top:4px; overflow:hidden; }
    .pf-member-bar-fill { background:linear-gradient(90deg,var(--pf-navy),#0091EA); height:100%; }
</style>

<div class="pf-wrap">
    <?php if (!empty($_SESSION['success'])): ?>
        <div style="background:#E8F5E9; color:#0B8043; padding:12px 16px; border-radius:10px; margin-bottom:14px; font-size:13px;">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <div class="pf-header">
        <h2>👋 MyFinance ড্যাশবোর্ড</h2>
        <p><?php echo date('d F Y'); ?> — আপনার সম্পূর্ণ আর্থিক ওভারভিউ (ব্যবসা + ব্যক্তিগত একসাথে)</p>
        <a href="add_transaction.php" class="pf-add-btn">➕ নতুন লেনদেন</a>
        <a href="accounts.php" class="pf-add-btn" style="background:#EEF2FF; color:var(--pf-navy);">🏦 অ্যাকাউন্ট পরিচালনা</a>
    </div>

    <div class="pf-kpi-grid">
        <div class="pf-kpi-box" style="background:linear-gradient(135deg,#0B8043,#2ECC71);"><div class="lbl">মোট ব্যালেন্স (সব অ্যাকাউন্ট)</div><div class="val"><?php echo ame_balance_label($total_balance); ?></div></div>
        <div class="pf-kpi-box" style="background:linear-gradient(135deg,#0091EA,#4FC3F7);"><div class="lbl">এই মাসের মোট জমা</div><div class="val">৳<?php echo number_format($total_income, 0); ?></div></div>
        <div class="pf-kpi-box" style="background:linear-gradient(135deg,#C62828,#E57373);"><div class="lbl">এই মাসের মোট খরচ</div><div class="val">৳<?php echo number_format($total_expense, 0); ?></div></div>
        <div class="pf-kpi-box" style="background:linear-gradient(135deg,#7B1FA2,#AB47BC);"><div class="lbl">নিট ক্যাশ ফ্লো</div><div class="val"><?php echo $net_cash_flow >= 0 ? '+' : ''; ?>৳<?php echo number_format($net_cash_flow, 0); ?></div></div>
    </div>

    <div class="pf-section">
        <h3>🏦 আমার সব অ্যাকাউন্ট <span style="font-weight:400; color:#94A3B8; font-size:11px;">(দোকান + ব্যক্তিগত — একসাথে)</span></h3>
        <?php if (empty($accounts)): ?>
            <p style="text-align:center; color:#94A3B8; font-size:12.5px; padding:16px 0;">এখনো কোনো অ্যাকাউন্ট নেই। <a href="accounts.php" style="color:var(--pf-navy);">+ যোগ করুন</a></p>
        <?php else: foreach ($accounts as $a): ?>
        <div class="pf-acc-row">
            <span><?php echo $account_icons[$a['account_type']] ?? '💰'; ?> <?php echo htmlspecialchars($a['account_name']); ?></span>
            <strong style="color:var(--pf-navy);"><?php echo ame_balance_label((float)$a['balance']); ?></strong>
        </div>
        <?php endforeach; endif; ?>
        <div class="pf-acc-row" style="border-top:2px solid #F1F5F9; font-weight:800; margin-top:4px;">
            <span>মোট ব্যালেন্স</span><span style="color:var(--pf-green);"><?php echo ame_balance_label($total_balance); ?></span>
        </div>
    </div>

    <?php if (!empty($week_labels)): ?>
    <div class="pf-section">
        <h3>📊 জমা বনাম খরচ (এই মাস)</h3>
        <div style="position:relative; height:200px;"><canvas id="incomeExpenseChart"></canvas></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($expense_breakdown)): ?>
    <div class="pf-section">
        <h3>🍩 ব্যক্তিগত খরচের ভাঙন (এই মাস)</h3>
        <div style="position:relative; height:220px; max-width:280px; margin:0 auto 12px;"><canvas id="expenseDonut"></canvas></div>
        <?php foreach ($expense_breakdown as $e): ?>
        <div class="pf-txn-row"><span><?php echo esc_html($e['icon'] ?? ''); ?> <?php echo htmlspecialchars($e['name']); ?></span><strong>৳<?php echo number_format((float)$e['total'], 0); ?></strong></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="pf-section">
        <h3>🕒 সাম্প্রতিক লেনদেন <span style="font-weight:400; color:#94A3B8; font-size:11px;">(দোকান + ব্যক্তিগত)</span></h3>
        <?php if (empty($recent)): ?>
            <p style="text-align:center; color:#94A3B8; font-size:12.5px; padding:16px 0;">এখনো কোনো লেনদেন নেই।</p>
        <?php else: foreach ($recent as $t):
            $is_credit = $t['type'] === 'credit';
            $label = $t['category_name']
                ? trim((string)($t['category_icon'] ?? '') . ' ' . (string)$t['category_name'])
                : ($ref_type_labels[$t['ref_type']] ?? ucfirst((string)$t['ref_type']));
        ?>
        <div class="pf-txn-row">
            <span><?php echo esc_html($label); ?><br><small style="color:#94A3B8;"><?php echo date('d M', strtotime($t['created_at'])); ?> • <?php echo htmlspecialchars($t['account_name'] ?? ''); ?><?php if ($t['merchant_name']): ?> • <?php echo htmlspecialchars($t['merchant_name']); ?><?php endif; ?></small></span>
            <strong style="color:<?php echo $is_credit ? '#0B8043' : '#C62828'; ?>;"><?php echo $is_credit ? '+' : '-'; ?>৳<?php echo number_format((float)$t['amount'], 0); ?></strong>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <?php if (!empty($top_merchants)): ?>
    <div class="pf-section">
        <h3>🏪 শীর্ষ দোকান/মার্চেন্ট (এই মাস)</h3>
        <?php foreach ($top_merchants as $m): ?>
        <div class="pf-merchant-row"><span><?php echo htmlspecialchars($m['name']); ?></span><strong>৳<?php echo number_format((float)$m['total'], 0); ?></strong></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($member_spend)): ?>
    <div class="pf-section">
        <h3>👨‍👩‍👧 সদস্যভিত্তিক খরচ (এই মাস)</h3>
        <?php foreach ($member_spend as $m): $pct = round(($m['total'] / $member_total) * 100); ?>
        <div style="margin-bottom:10px;">
            <div style="display:flex; justify-content:space-between; font-size:12.5px;"><span><?php echo htmlspecialchars($m['name']); ?></span><strong>৳<?php echo number_format((float)$m['total'], 0); ?> (<?php echo $pct; ?>%)</strong></div>
            <div class="pf-member-bar-bg"><div class="pf-member-bar-fill" style="width:<?php echo $pct; ?>%;"></div></div>
        </div>
        <?php endforeach; ?>
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
                { label: 'জমা', data: <?php echo json_encode($week_income); ?>, backgroundColor: '#0B8043' },
                { label: 'খরচ', data: <?php echo json_encode($week_expense); ?>, backgroundColor: '#C62828' }
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
                backgroundColor: ['#0091EA','#C62828','#FF9800','#0B8043','#7B1FA2','#00838F','#455A64','#F57C00']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
}
<?php endif; ?>
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
