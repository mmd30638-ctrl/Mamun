<?php
require_once '../../../includes/config.php';
require_once '../../../includes/partnership_functions.php';
require_once '../../../includes/account_functions.php';
require_once '../../../includes/partner_accounting_v2.php';
require_permission($db, 'm-acc');
pav2_ensure_schema($db);

$ym = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? (string)$_GET['month'] : date('Y-m');
$report = pav2_report($db, $ym);
$period = $report['period'];
$partners = pav2_partners($db);
$accounts = pav2_accounts($db);
$rules = pav2_rules($db);
$categories = pav2_expense_categories();
$movementTypes = pav2_movement_types();
$locked = !empty($report['closed']);
$isAdmin = strtolower((string)($_SESSION['role'] ?? '')) === 'admin';

$filterProduct = max(0, (int)($_GET['product_id'] ?? 0));
$filterPartner = max(0, (int)($_GET['partner_id'] ?? 0));
$filterCategory = preg_replace('/[^a-z_]/', '', (string)($_GET['category'] ?? ''));
$filterFrom = pav2_date((string)($_GET['from'] ?? ''), $period['from']);
$filterTo = pav2_date((string)($_GET['to'] ?? ''), $period['to']);
$visibleRows = array_values(array_filter($report['rows'], static function ($row) use ($filterProduct, $filterPartner, $filterFrom, $filterTo) {
    if ($filterProduct && (int)$row['product_id'] !== $filterProduct) {
        return false;
    }
    if ($filterPartner && (int)$row['partner_one_id'] !== $filterPartner && (int)$row['partner_two_id'] !== $filterPartner) {
        return false;
    }
    return (string)$row['date'] >= $filterFrom && (string)$row['date'] <= $filterTo;
}));
$visibleExpenses = array_values(array_filter($report['expenses'], static function ($row) use ($filterCategory, $filterFrom, $filterTo) {
    if ($filterCategory !== '' && (string)$row['category'] !== $filterCategory) {
        return false;
    }
    return (string)$row['expense_date'] >= $filterFrom && (string)$row['expense_date'] <= $filterTo;
}));

$cardSales = 0.0;
$cardCost = 0.0;
$cardCharge = 0.0;
$cardProfit = 0.0;
foreach ($visibleRows as $row) {
    $cardSales += (float)$row['sales'];
    $cardCost += (float)$row['cost'];
    $cardCharge += (float)$row['direct_cost'];
    $cardProfit += (float)$row['profit'];
}
$cardSales = round($cardSales, 2);
$cardCost = round($cardCost, 2);
$cardCharge = round($cardCharge, 2);
$cardProfit = round($cardProfit, 2);
$checkProfit = round($cardSales - $cardCost - $cardCharge, 2);
if (abs($checkProfit - $cardProfit) > 0.009) {
    $cardProfit = $checkProfit;
}
$cardExpense = 0.0;
foreach ($visibleExpenses as $expense) {
    $cardExpense += (float)$expense['amount'];
}
$cardExpense = round($cardExpense, 2);
$narrowFilter = $filterProduct > 0 || $filterPartner > 0;
$cardNet = $narrowFilter
    ? round((float)$report['net_profit'], 2)
    : round($cardProfit - $cardExpense, 2);
$formulaHolds = abs(round($cardSales - $cardCost - $cardCharge, 2) - $cardProfit) < 0.01;

$filterProductLabel = '';
if ($filterProduct > 0) {
    $lookup = $db->prepare('SELECT name, sku FROM products WHERE id=? LIMIT 1');
    if ($lookup) {
        $lookup->bind_param('i', $filterProduct);
        $lookup->execute();
        $found = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if ($found) {
            $sku = trim((string)($found['sku'] ?? ''));
            $filterProductLabel = (string)$found['name'] . ($sku !== '' ? ' · ' . $sku : '');
        }
    }
}

$error = (string)($_SESSION['pav2_error'] ?? '');
unset($_SESSION['pav2_error']);

function pav2_search_box(string $kind, string $name, int $value, string $label, string $placeholder, bool $required = false): void
{
    ?>
    <div class="pv2-search" data-pv2-kind="<?php echo htmlspecialchars($kind, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="search" class="pv2-search-q" value="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off"<?php echo $required ? ' data-required="1"' : ''; ?>>
        <input type="hidden" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo (int)$value; ?>"<?php echo $required ? ' data-pv2-required="1"' : ''; ?>>
        <div class="pv2-suggest" hidden></div>
    </div>
    <?php
}

include '../../../partials/header.php';
?>
<style>
html,body,#main-content{background:#ffffff!important}
.pv2{max-width:1500px;margin:0 auto;padding:14px;color:#10233f}
.pv2-hero,.pv2-card,.pv2-panel,.pv2 details,.pv2-cutoff,.pv2-alert,.pv2-badge,.pv2-search,.pv2-suggest,.pv2-formula{
    background:#ffffff!important;background-image:none!important
}
.pv2-hero{border:1px solid #bfdbfe;border-top:8px solid #0b5cad;border-radius:18px;padding:18px;box-shadow:0 8px 24px rgba(15,23,42,.08)}
.pv2-hero-top,.pv2-head,.pv2-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.pv2 h1{margin:0;color:#0b5cad;font-size:clamp(22px,3vw,32px)}
.pv2-hero p{margin:7px 0 0;color:#475569;line-height:1.6}
.pv2-cutoff{margin-top:12px;padding:10px 12px;border:1px solid #93c5fd;border-left:5px solid #1565c0;border-radius:9px;font-weight:700;color:#0b5cad}
.pv2-filter,.pv2-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:9px;margin-top:12px}
.pv2 input,.pv2 select,.pv2 textarea,.pv2 .pv2-search-q{
    width:100%;box-sizing:border-box;border:1.5px solid #bfdbfe;border-radius:10px;padding:10px;
    background:#ffffff!important;color:#0b5cad;font:inherit
}
.pv2 textarea{min-height:72px;resize:vertical;color:#7c3aed}.pv2 .wide{grid-column:1/-1}
.pv2-btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:10px;padding:10px 14px;background:#1565c0;color:#fff;font-weight:900;text-decoration:none;cursor:pointer}
.pv2-btn.green{background:#0e8f52}.pv2-btn.red{background:#c62828}.pv2-btn.gold{background:#d97706}.pv2-btn.dark{background:#0b1e4d}
.pv2-btn.soft{background:#ffffff!important;color:#075985;border:1.5px solid #7dd3fc}
.pv2-alert{margin:12px 0;padding:11px 13px;border-radius:11px;border:1.5px solid #86efac;color:#166534}
.pv2-alert.bad{border-color:#fca5a5;color:#991b1b}
.pv2-cards{display:grid;grid-template-columns:repeat(6,minmax(150px,1fr));gap:10px;margin:14px 0}
.pv2-card{border:1px solid #dbeafe;border-top:6px solid var(--c,#1565c0);border-radius:14px;padding:13px;box-shadow:0 5px 16px rgba(15,23,42,.06)}
.pv2-card small{display:block;color:#64748b;font-weight:700}
.pv2-card strong{display:block;margin-top:6px;color:var(--c,#0b1e4d);font-size:clamp(18px,2vw,25px)}
.pv2-formula{border:1px solid #bbf7d0;border-radius:12px;padding:10px 12px;margin:0 0 14px;color:#0e8f52;font-weight:800;line-height:1.55}
.pv2-formula b{color:#c62828}.pv2-formula .warn{color:#c62828}
.pv2-panel{border:1px solid #e2e8f0;border-radius:16px;padding:15px;margin-bottom:13px;box-shadow:0 5px 18px rgba(15,23,42,.06)}
.pv2-panel h2{margin:0;font-size:19px}.pv2-sub{color:#64748b;font-size:13px;line-height:1.5;margin-top:4px}
.pv2-h-blue{color:#0b5cad}.pv2-h-green{color:#0e8f52}.pv2-h-red{color:#c62828}.pv2-h-gold{color:#d97706}.pv2-h-purple{color:#7c3aed}
.pv2-scroll{overflow:auto;-webkit-overflow-scrolling:touch;margin-top:10px}
.pv2-table{width:100%;border-collapse:collapse;min-width:850px;font-size:13px}
.pv2-table th{background:#ffffff!important;color:#0b5cad;text-align:left;padding:9px;white-space:nowrap;border-bottom:2px solid #0b5cad}
.pv2-table td{padding:9px;border-bottom:1px solid #e2e8f0;vertical-align:top;background:#ffffff!important;color:#334155}
.pv2-table tr:nth-child(even) td{background:#ffffff!important}
.pv2-table .num{text-align:right;white-space:nowrap;color:#0b5cad;font-weight:700}
.pv2-table .num.green{color:#0e8f52}.pv2-table .num.red{color:#c62828}.pv2-table .num.gold{color:#d97706}.pv2-table .num.purple{color:#7c3aed}
.pv2-table tfoot td{font-weight:900;background:#ffffff!important;color:#0e8f52;border-top:2px solid #86efac}
.pv2-badge{display:inline-block;border-radius:999px;padding:3px 8px;font-size:11px;font-weight:900;border:1.5px solid #86efac;color:#166534}
.pv2-badge.closed{border-color:#fca5a5;color:#991b1b}
.pv2-details{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.pv2 details{border:1px solid #bfdbfe;border-radius:14px;padding:12px}
.pv2 summary{cursor:pointer;font-weight:900}
.pv2-empty{text-align:center;padding:22px;color:#64748b}
.pv2-inline{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.pv2-inline input{min-width:120px}
.pv2-search{position:relative}
.pv2-suggest{position:absolute;z-index:50;left:0;right:0;top:calc(100% + 4px);border:1px solid #bfdbfe;border-radius:10px;max-height:240px;overflow:auto;box-shadow:0 10px 24px rgba(15,23,42,.12)}
.pv2-suggest button{display:block;width:100%;text-align:left;background:#ffffff!important;border:0;border-bottom:1px solid #e2e8f0;padding:9px 10px;color:#0b5cad;cursor:pointer;font:inherit}
.pv2-suggest button:hover{color:#c62828}
.pv2-ratio-hint{font-weight:800;font-size:13px}
.pv2-note{font-size:12px;color:#c62828;font-weight:700;margin-top:4px}
@media(max-width:1100px){.pv2-cards{grid-template-columns:repeat(3,1fr)}.pv2-details{grid-template-columns:1fr}}
@media(max-width:620px){.pv2{padding:9px}.pv2-cards{grid-template-columns:repeat(2,1fr)}.pv2-card{padding:10px}.pv2-form,.pv2-filter{grid-template-columns:1fr}}
@media print{.no-print,.ame-page-nav,.master-header-fixed,.custom-sidebar{display:none!important}.pv2{padding:0}.pv2-panel,.pv2-card,.pv2-hero{box-shadow:none;break-inside:avoid}.pv2-scroll{overflow:visible}.pv2-table{min-width:0;font-size:10px}}
</style>
<main class="pv2">
    <section class="pv2-hero">
        <div class="pv2-hero-top">
            <div>
                <h1>পার্টনার লাভ ও যৌথ খরচ</h1>
                <p>পণ্যভিত্তিক লাভ, রিটার্ন ও ডিসকাউন্ট সমন্বয়, যৌথ খরচ, বিনিয়োগ এবং পার্টনার পাওনা—একটি audit-যোগ্য মাসিক খাতায়।</p>
            </div>
            <span class="pv2-badge <?php echo $locked ? 'closed' : ''; ?>"><?php echo $locked ? 'মাস বন্ধ · Snapshot' : 'মাস খোলা · Live'; ?></span>
        </div>
        <div class="pv2-cutoff">নতুন হিসাবের স্থায়ী কার্যকর তারিখ: <?php echo htmlspecialchars(date('d-m-Y', strtotime($period['cutoff'])), ENT_QUOTES, 'UTF-8'); ?>। এর আগের partner report সংরক্ষিত archive; এই হিসাবে মেশে না।</div>
        <form class="pv2-filter no-print" method="get">
            <input type="month" name="month" value="<?php echo htmlspecialchars($ym); ?>">
            <input type="date" name="from" value="<?php echo htmlspecialchars($filterFrom); ?>">
            <input type="date" name="to" value="<?php echo htmlspecialchars($filterTo); ?>">
            <?php pav2_search_box('product', 'product_id', $filterProduct, $filterProductLabel, 'পণ্য খুঁজুন — নাম লিখুন'); ?>
            <select name="partner_id"><option value="0">সব পার্টনার</option><?php foreach ($partners as $partner): ?><option value="<?php echo (int)$partner['id']; ?>"<?php echo $filterPartner === (int)$partner['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($partner['name']); ?></option><?php endforeach; ?></select>
            <select name="category"><option value="">সব খরচের খাত</option><?php foreach ($categories as $key=>$label): ?><option value="<?php echo $key; ?>"<?php echo $filterCategory === $key ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select>
            <button class="pv2-btn" type="submit">রিপোর্ট দেখুন</button>
        </form>
        <div class="pv2-actions no-print" style="margin-top:10px">
            <a class="pv2-btn soft" href="export.php?format=csv&amp;month=<?php echo rawurlencode($ym); ?>">Excel/CSV</a>
            <button class="pv2-btn dark" type="button" onclick="window.print()">PDF / প্রিন্ট</button>
        </div>
    </section>

    <?php if ($error !== ''): ?><div class="pv2-alert bad"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div class="pv2-alert">তথ্য নিরাপদে সংরক্ষিত হয়েছে এবং রিপোর্ট আপডেট হয়েছে।</div><?php endif; ?>
    <?php if (!$period['eligible']): ?><div class="pv2-alert bad">এই মাস নতুন হিসাবের কার্যকর তারিখের আগে—কোনো পুরোনো লাভ এখানে দেখানো হবে না।</div><?php endif; ?>

    <section class="pv2-cards">
        <div class="pv2-card" style="--c:#1565c0"><small>যোগ্য পণ্য বিক্রয়</small><strong><?php echo pav2_taka($cardSales); ?></strong></div>
        <div class="pv2-card" style="--c:#7c3aed"><small>বিক্রীত পণ্যের খরচ</small><strong><?php echo pav2_taka($cardCost); ?></strong></div>
        <div class="pv2-card" style="--c:#c62828"><small>পণ্য চার্জ</small><strong><?php echo pav2_taka($cardCharge); ?></strong></div>
        <div class="pv2-card" style="--c:#0e8f52"><small>পণ্যভিত্তিক লাভ</small><strong><?php echo pav2_taka($cardProfit); ?></strong></div>
        <div class="pv2-card" style="--c:#d97706"><small>যৌথ দোকান খরচ</small><strong><?php echo pav2_taka($cardExpense); ?></strong></div>
        <div class="pv2-card" style="--c:#0891b2"><small><?php echo $narrowFilter ? 'মাসের নিট লাভ' : 'চূড়ান্ত নিট লাভ'; ?></small><strong><?php echo pav2_taka($cardNet); ?></strong></div>
    </section>
    <div class="pv2-formula">
        পণ্য লাভ = বিক্রয় − ক্রয়মূল্য − পণ্য চার্জ
        · <?php echo pav2_taka($cardProfit); ?> = <?php echo pav2_taka($cardSales); ?> − <?php echo pav2_taka($cardCost); ?> − <b><?php echo pav2_taka($cardCharge); ?></b>
        <?php if (!$narrowFilter): ?>
        <br>নিট লাভ = পণ্য লাভ − যৌথ খরচ · <?php echo pav2_taka($cardNet); ?> = <?php echo pav2_taka($cardProfit); ?> − <?php echo pav2_taka($cardExpense); ?>
        <?php else: ?>
        <br><span class="warn">পণ্য/পার্টনার ফিল্টারে যৌথ খরচ দোকানের; নিট লাভ পুরো মাসের হিসাব।</span>
        <?php endif; ?>
        <?php if ((float)$report['returns'] > 0): ?>
        <br>রিটার্ন <?php echo pav2_taka((float)$report['returns']); ?> ইতিমধ্যে বিক্রয় ও খরচ থেকে বাদ। আলাদা করে আর কাটা হয়নি।
        <?php endif; ?>
        <?php if (!$formulaHolds): ?>
        <br class="warn"><span class="warn">সারির লাভ মিলছে না — পাতা রিফ্রেশ করে আবার দেখুন।</span>
        <?php endif; ?>
    </div>

    <section class="pv2-panel">
        <div class="pv2-head"><div><h2 class="pv2-h-blue">পার্টনারভিত্তিক নিট হিসাব</h2><div class="pv2-sub">লাভের অংশ − যৌথ খরচের অংশ + ব্যক্তিগত টাকায় খরচ − পরিশোধ = বর্তমান পাওনা। মূলধন আলাদা।</div></div></div>
        <div class="pv2-scroll"><table class="pv2-table">
            <thead><tr><th>পার্টনার</th><th class="num">পণ্য লাভ</th><th class="num">যৌথ খরচ</th><th class="num">নিট লাভ</th><th class="num">ফেরতযোগ্য খরচ</th><th class="num">লাভ পরিশোধ</th><th class="num">খরচ ফেরত</th><th class="num">মোট পাওনা</th><th class="num">মূলধন/বিনিয়োগ</th><th class="num">উত্তোলন</th></tr></thead>
            <tbody><?php if (!$report['partners']): ?><tr><td colspan="10" class="pv2-empty">এই মাসে যোগ্য হিসাব নেই।</td></tr><?php else: foreach ($report['partners'] as $partner):
                $recv = (float)($partner['receivable'] ?? 0);
            ?>
                <tr>
                    <td><b class="pv2-h-blue"><?php echo htmlspecialchars((string)($partner['name'] ?? '')); ?></b></td>
                    <td class="num green"><?php echo pav2_taka((float)($partner['gross_share'] ?? 0)); ?></td>
                    <td class="num red"><?php echo pav2_taka((float)($partner['expense_share'] ?? 0)); ?></td>
                    <td class="num"><?php echo pav2_taka((float)($partner['net_profit_share'] ?? 0)); ?></td>
                    <td class="num gold"><?php echo pav2_taka((float)($partner['reimbursable'] ?? 0)); ?></td>
                    <td class="num purple"><?php echo pav2_taka((float)($partner['profit_payment'] ?? 0)); ?></td>
                    <td class="num"><?php echo pav2_taka((float)($partner['expense_reimbursement'] ?? 0)); ?></td>
                    <td class="num <?php echo $recv < 0 ? 'red' : 'green'; ?>"><b><?php echo pav2_taka($recv); ?></b></td>
                    <td class="num"><?php echo pav2_taka((float)($partner['capital'] ?? 0)); ?></td>
                    <td class="num red"><?php echo pav2_taka((float)($partner['withdrawal'] ?? 0)); ?></td>
                </tr>
            <?php endforeach; endif; ?></tbody>
        </table></div>
    </section>

    <section class="pv2-panel" id="pv2-product">
        <div class="pv2-head"><div><h2 class="pv2-h-green">পণ্য ও ইনভয়েসভিত্তিক লাভ</h2><div class="pv2-sub">Invoice discount অনুপাতে ভাগ হয়; frozen purchase price, ম্যানুয়াল পণ্য চার্জ ও completed/approved return সমন্বয় হয়। লাভ = বিক্রি − ক্রয়মূল্য − পণ্য চার্জ। ক্যাটাগরিতে ৫০/৫০ চালু থাকলে সেই খাতের পণ্যের লাভ অটো ভাগ হয় — আগের তারিখের নিয়ম বদলায় না।</div></div></div>
        <div class="pv2-scroll"><table class="pv2-table">
            <thead><tr><th>তারিখ</th><th>ইনভয়েস</th><th>পণ্য</th><th class="num">বিক্রি</th><th class="num">ক্রয়মূল্য</th><th class="num">পণ্য চার্জ</th><th class="num">লাভ</th><th>প্রথম অংশ</th><th>দ্বিতীয় অংশ</th></tr></thead>
            <tbody><?php if (!$visibleRows): ?><tr><td colspan="9" class="pv2-empty">নির্বাচিত ফিল্টারে কোনো যোগ্য পণ্য লাভ নেই।</td></tr><?php else: foreach ($visibleRows as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($row['date']))); ?></td>
                    <td><?php echo htmlspecialchars($row['invoice_no']); ?> <small>#<?php echo (int)$row['sale_id']; ?></small></td>
                    <td><?php echo htmlspecialchars($row['product_name']); ?><br><small>বিক্রি <?php echo number_format((float)$row['sold_qty'], 2); ?> · ফেরত <?php echo number_format((float)$row['returned_qty'], 2); ?><?php if (($row['split_source'] ?? '') === 'category'): ?> · <b class="pv2-h-blue">ক্যাটাগরি ৫০/৫০</b><?php endif; ?></small></td>
                    <td class="num"><?php echo pav2_taka((float)$row['sales']); ?></td>
                    <td class="num purple"><?php echo pav2_taka((float)$row['cost']); ?></td>
                    <td class="num red"><?php echo pav2_taka((float)$row['direct_cost']); ?></td>
                    <td class="num green"><b><?php echo pav2_taka((float)$row['profit']); ?></b></td>
                    <td><?php echo htmlspecialchars($row['partner_one_name']); ?> (<?php echo number_format((float)$row['partner_one_ratio'], 2); ?>%)<br><b class="pv2-h-blue"><?php echo pav2_taka((float)$row['partner_one_share']); ?></b></td>
                    <td><?php echo htmlspecialchars($row['partner_two_name']); ?> (<?php echo number_format((float)$row['partner_two_ratio'], 2); ?>%)<br><b class="pv2-h-gold"><?php echo pav2_taka((float)$row['partner_two_share']); ?></b></td>
                </tr>
            <?php endforeach; endif; ?></tbody>
            <?php if ($visibleRows): ?>
            <tfoot><tr>
                <td colspan="3">মোট</td>
                <td class="num"><?php echo pav2_taka($cardSales); ?></td>
                <td class="num purple"><?php echo pav2_taka($cardCost); ?></td>
                <td class="num red"><?php echo pav2_taka($cardCharge); ?></td>
                <td class="num green"><?php echo pav2_taka($cardProfit); ?></td>
                <td colspan="2"></td>
            </tr></tfoot>
            <?php endif; ?>
        </table></div>
    </section>

    <section class="pv2-panel" id="pv2-expense">
        <div class="pv2-head"><div><h2 class="pv2-h-red">যৌথ দোকান খরচ</h2><div class="pv2-sub">পার্টনার নিজের টাকা দিলে একবার ব্যবসার খরচ এবং একই অংক ফেরতযোগ্য পাওনা—খরচ দুবার নয়।</div></div></div>
        <div class="pv2-scroll"><table class="pv2-table">
            <thead><tr><th>তারিখ</th><th>ভাউচার</th><th>খাত</th><th>বিবরণ</th><th>প্রদানকারী</th><th>প্রকৃত অ্যাকাউন্ট</th><th class="num">টাকা</th><th class="no-print">রসিদ/কাজ</th></tr></thead>
            <tbody><?php if (!$visibleExpenses): ?><tr><td colspan="8" class="pv2-empty">এই ফিল্টারে যৌথ খরচ নেই।</td></tr><?php else: foreach ($visibleExpenses as $expense): ?>
                <tr><td><?php echo htmlspecialchars(date('d-m-Y', strtotime($expense['expense_date']))); ?></td><td><?php echo htmlspecialchars($expense['voucher_no']); ?></td><td><?php echo htmlspecialchars($categories[$expense['category']] ?? $expense['category']); ?></td><td><?php echo htmlspecialchars((string)$expense['description']); ?></td><td><?php echo ($expense['payer_type'] ?? '') === 'partner' ? htmlspecialchars((string)$expense['payer_partner_name']) : 'ব্যবসা'; ?></td><td><?php echo ($expense['payer_type'] ?? '') === 'partner' ? 'প্রযোজ্য নয় · ব্যক্তিগত টাকা' : htmlspecialchars((string)($expense['account_name'] ?? ('Account #' . (int)($expense['account_id'] ?? 0)))); ?></td><td class="num red"><?php echo pav2_taka((float)$expense['amount']); ?></td><td class="no-print"><?php if (!empty($expense['receipt_path'])): ?><a href="<?php echo htmlspecialchars($expense['receipt_path']); ?>" target="_blank">রসিদ</a><?php endif; ?><?php if (!$locked): ?><form method="post" action="action.php" style="display:inline" onsubmit="return confirm('খরচটি void করবেন? নির্বাচিত ব্যবসায়িক অ্যাকাউন্টের টাকা একবার ফেরত যাবে।');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="void_expense"><input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>"><input type="hidden" name="id" value="<?php echo (int)$expense['id']; ?>"><input type="hidden" name="reason" value="ভুল এন্ট্রি"><button class="pv2-btn red" type="submit">Void</button></form><?php endif; ?></td></tr>
            <?php endforeach; endif; ?></tbody>
        </table></div>
    </section>

    <section class="pv2-panel">
        <div class="pv2-head"><div><h2 class="pv2-h-gold">জমা, উত্তোলন ও পরিশোধ</h2><div class="pv2-sub">উত্তোলন, লাভ পরিশোধ ও খরচ ফেরত নির্বাচিত প্রকৃত অ্যাকাউন্ট থেকে একবার কাটা হয়। মূলধন, বিনিয়োগ ও পার্টনার ঋণ সেই অ্যাকাউন্টে একবার জমা হয়।</div></div></div>
        <div class="pv2-scroll"><table class="pv2-table">
            <thead><tr><th>তারিখ</th><th>পার্টনার</th><th>ধরন</th><th>প্রকৃত অ্যাকাউন্ট</th><th>রেফারেন্স</th><th class="num">টাকা</th><th class="no-print">কাজ</th></tr></thead>
            <tbody><?php if (empty($report['movements'])): ?><tr><td colspan="7" class="pv2-empty">এই মাসে পোস্ট করা পার্টনার লেনদেন নেই।</td></tr><?php else: foreach ($report['movements'] as $movement):
                $moveType = (string)($movement['movement_type'] ?? '');
                $moveOut = pav2_movement_is_money_out($moveType);
                $moveAccount = trim((string)($movement['account_name'] ?? ''));
                if ($moveAccount === '') {
                    $moveAccount = (int)($movement['account_id'] ?? 0) > 0
                        ? ('Account #' . (int)$movement['account_id'])
                        : 'পুরোনো এন্ট্রি · অ্যাকাউন্ট পোস্টিং নেই';
                } elseif (!empty($movement['account_type'])) {
                    $moveAccount .= ' · ' . $movement['account_type'];
                }
            ?>
                <tr>
                    <td><?php echo htmlspecialchars(date('d-m-Y', strtotime((string)$movement['movement_date']))); ?></td>
                    <td><?php echo htmlspecialchars((string)($movement['partner_name'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($movementTypes[$moveType] ?? $moveType); ?><br><small><?php echo $moveOut ? 'অ্যাকাউন্ট থেকে কাটা' : 'অ্যাকাউন্টে জমা'; ?></small></td>
                    <td><?php echo htmlspecialchars($moveAccount); ?></td>
                    <td><?php echo htmlspecialchars((string)($movement['reference'] ?? '')); ?></td>
                    <td class="num <?php echo $moveOut ? 'red' : 'green'; ?>"><?php echo pav2_taka((float)$movement['amount']); ?></td>
                    <td class="no-print"><?php if (!$locked): ?><form method="post" action="action.php" onsubmit="return confirm('লেনদেনটি রিভার্স করবেন? নির্বাচিত অ্যাকাউন্টের টাকা একবার ফেরত যাবে।');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="reverse_movement"><input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>"><input type="hidden" name="id" value="<?php echo (int)$movement['id']; ?>"><input type="hidden" name="reason" value="ভুল এন্ট্রি"><button class="pv2-btn red" type="submit">রিভার্স</button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; endif; ?></tbody>
        </table></div>
    </section>

    <?php if (!$locked): ?>
    <section class="pv2-details no-print">
        <details open><summary class="pv2-h-green">পণ্য লাভ-বণ্টনের নিয়ম যোগ</summary>
            <form class="pv2-form" method="post" action="action.php" data-pv2-validate="rule"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save_rule"><input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
                <?php pav2_search_box('product', 'product_id', 0, '', 'পণ্য খুঁজুন — নাম/SKU লিখুন', true); ?>
                <input type="date" name="effective_from" min="<?php echo htmlspecialchars($period['cutoff']); ?>" value="<?php echo htmlspecialchars(max(date('Y-m-d'), $period['cutoff'])); ?>" required>
                <input type="date" name="effective_to" min="<?php echo htmlspecialchars($period['cutoff']); ?>">
                <select name="partner_one_id" required><option value="">প্রথম পার্টনার</option><?php foreach ($partners as $partner): ?><option value="<?php echo (int)$partner['id']; ?>"><?php echo htmlspecialchars($partner['name']); ?></option><?php endforeach; ?></select>
                <input type="number" name="partner_one_ratio" value="50" min="0.0001" max="99.9999" step="0.0001" required>
                <select name="partner_two_id" required><option value="">দ্বিতীয় পার্টনার</option><?php foreach ($partners as $partner): ?><option value="<?php echo (int)$partner['id']; ?>"><?php echo htmlspecialchars($partner['name']); ?></option><?php endforeach; ?></select>
                <input type="number" name="partner_two_ratio" value="50" min="0.0001" max="99.9999" step="0.0001" required>
                <div class="wide pv2-ratio-hint">মোট ১০০% — দুইজন মিলে ঠিক ১০০ হতে হবে।</div>
                <textarea class="wide" name="terms" placeholder="শর্ত ও নোট"></textarea><button class="pv2-btn green wide" type="submit">নিয়ম কার্যকর করুন</button>
            </form>
        </details>
        <details><summary class="pv2-h-red">যৌথ খরচ যোগ</summary>
            <form class="pv2-form" method="post" action="action.php" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save_expense"><input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>"><input type="hidden" name="idempotency_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="date" name="expense_date" min="<?php echo htmlspecialchars($period['cutoff']); ?>" value="<?php echo date('Y-m-d'); ?>" required><input name="voucher_no" placeholder="ভাউচার (ফাঁকা হলে অটো)"><select name="category"><?php foreach ($categories as $key=>$label): ?><option value="<?php echo $key; ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select><input type="number" name="amount" min="0.01" step="0.01" placeholder="টাকা" required><select name="payer_type" onchange="pav2ExpensePayer(this.form)" required><option value="business">ব্যবসার টাকা</option><option value="partner">পার্টনারের ব্যক্তিগত টাকা</option></select><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id" required><?php echo ame_render_account_options($accounts); ?></select><select name="payer_partner_id" disabled><option value="">প্রদানকারী পার্টনার</option><?php foreach ($partners as $partner): ?><option value="<?php echo (int)$partner['id']; ?>"><?php echo htmlspecialchars($partner['name']); ?></option><?php endforeach; ?></select><input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf"><div class="wide pv2-sub">ব্যবসার টাকা হলে নির্বাচিত প্রকৃত অ্যাকাউন্ট একবার ডেবিট হবে এবং পেমেন্ট মাধ্যম অ্যাকাউন্ট থেকে নির্ধারিত হবে। পার্টনারের ব্যক্তিগত টাকা হলে কোনো ব্যবসায়িক অ্যাকাউন্ট ডেবিট হবে না; পুরো অংক ফেরতযোগ্য পাওনা হবে।</div><textarea class="wide" name="description" placeholder="বিবরণ"></textarea><button class="pv2-btn red wide" type="submit">যৌথ খরচ সংরক্ষণ</button>
            </form>
        </details>
        <details><summary class="pv2-h-gold">মূলধন, উত্তোলন বা পরিশোধ</summary>
            <form class="pv2-form" method="post" action="action.php"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save_movement"><input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>"><input type="hidden" name="idempotency_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8'); ?>">
                <select name="partner_id" required><option value="">পার্টনার</option><?php foreach ($partners as $partner): ?><option value="<?php echo (int)$partner['id']; ?>"><?php echo htmlspecialchars($partner['name']); ?></option><?php endforeach; ?></select><select name="movement_type" required><?php foreach ($movementTypes as $key=>$label): ?><option value="<?php echo $key; ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select><input type="number" name="amount" min="0.01" step="0.01" placeholder="টাকা" required><input type="date" name="movement_date" min="<?php echo htmlspecialchars($period['cutoff']); ?>" value="<?php echo date('Y-m-d'); ?>" required><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id" required><?php echo ame_render_account_options($accounts); ?></select><input name="reference" placeholder="স্বতন্ত্র রেফারেন্স (ফাঁকা হলে অটো)"><div class="wide pv2-sub">উত্তোলন, লাভ পরিশোধ ও খরচ ফেরত নির্বাচিত অ্যাকাউন্ট থেকে একবার কাটা হবে। মূলধন, বিনিয়োগ ও পার্টনার ঋণ একই অ্যাকাউন্টে একবার জমা হবে।</div><textarea class="wide" name="note" placeholder="নোট"></textarea><button class="pv2-btn gold wide" type="submit">লেনদেন পোস্ট করুন</button>
            </form>
        </details>
        <details open><summary class="pv2-h-red">পণ্য চার্জ (ম্যানুয়াল)</summary>
            <form class="pv2-form" method="post" action="action.php" data-pv2-validate="charge"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save_direct_cost"><input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
                <?php pav2_search_box('product', 'product_id', 0, '', 'পণ্য খুঁজুন — নাম লিখে বেছে নিন', true); ?>
                <?php pav2_search_box('invoice', 'sale_id', 0, '', 'ইনভয়েস নম্বর লিখে খুঁজুন', true); ?>
                <input type="number" name="amount" min="0.01" step="0.01" placeholder="চার্জের টাকা লিখুন" required>
                <input type="date" name="cost_date" min="<?php echo htmlspecialchars($period['cutoff']); ?>" value="<?php echo date('Y-m-d'); ?>" required>
                <input name="reference" placeholder="স্বতন্ত্র রেফারেন্স">
                <div class="wide pv2-note">চার্জ নিজে লিখবেন — ক্রয়মূল্য বা বিক্রয়মূল্য থেকে অটো আসবে না। পণ্য খুঁজে বেছে নিন, তারপর ইনভয়েস খুঁজুন।</div>
                <div class="wide pv2-sub">এটি ইনভয়েস লাভের non-cash cost allocation; এখানে নতুন করে ব্যবসার অ্যাকাউন্ট ডেবিট হয় না। প্রকৃত টাকা এখন দিলে “যৌথ খরচ” ব্যবহার করুন।</div>
                <textarea class="wide" name="description" placeholder="খরচের বিবরণ"></textarea>
                <button class="pv2-btn dark wide" type="submit">পণ্য চার্জ যোগ</button>
            </form>
        </details>
    </section>
    <?php endif; ?>

    <section class="pv2-panel" style="margin-top:13px">
        <div class="pv2-head"><div><h2 class="pv2-h-purple">কার্যকর পণ্য নিয়ম</h2><div class="pv2-sub">একই পণ্যে overlapping তারিখ সিস্টেম গ্রহণ করবে না।</div></div></div>
        <div class="pv2-scroll"><table class="pv2-table"><thead><tr><th>পণ্য</th><th>কার্যকর সময়</th><th>প্রথম ভাগ</th><th>দ্বিতীয় ভাগ</th><th>শর্ত</th><th class="no-print">অবস্থা</th></tr></thead><tbody>
        <?php if (!$rules): ?><tr><td colspan="6" class="pv2-empty">এখনো কোনো পণ্য লাভ-বণ্টনে যুক্ত হয়নি।</td></tr><?php else: foreach ($rules as $rule): ?><tr><td><?php echo htmlspecialchars($rule['product_name']); ?></td><td><?php echo htmlspecialchars($rule['effective_from']); ?> → <?php echo htmlspecialchars($rule['effective_to'] ?: 'চলমান'); ?></td><td><?php echo htmlspecialchars($rule['partner_one_name']); ?> · <?php echo number_format((float)$rule['partner_one_ratio'],2); ?>%</td><td><?php echo htmlspecialchars($rule['partner_two_name']); ?> · <?php echo number_format((float)$rule['partner_two_ratio'],2); ?>%</td><td><?php echo htmlspecialchars((string)$rule['terms']); ?></td><td class="no-print"><span class="pv2-badge"><?php echo htmlspecialchars($rule['status']); ?></span><?php if (!$locked && $rule['status']==='active'): ?><form class="pv2-inline" method="post" action="action.php" style="margin-top:5px"><?php echo csrf_field(); ?><input type="hidden" name="action" value="end_rule"><input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>"><input type="hidden" name="id" value="<?php echo (int)$rule['id']; ?>"><input type="date" name="effective_to" min="<?php echo htmlspecialchars($rule['effective_from']); ?>" value="<?php echo date('Y-m-d'); ?>" required><button class="pv2-btn red" type="submit">শেষ করুন</button></form><?php endif; ?></td></tr><?php endforeach; endif; ?>
        </tbody></table></div>
    </section>

    <?php if ($isAdmin): ?><section class="pv2-panel no-print"><div class="pv2-head"><div><h2 class="pv2-h-gold">মাস বন্ধ ও Snapshot</h2><div class="pv2-sub">বন্ধ করলে এই মুহূর্তের হিসাব hash-সহ স্থির থাকবে। পরে sale edit হলেও বন্ধ রিপোর্ট বদলাবে না।</div></div></div>
        <form method="post" action="action.php" class="pv2-form"><?php echo csrf_field(); ?><input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>"><?php if ($locked): ?><input type="hidden" name="action" value="reopen_month"><button class="pv2-btn red wide" type="submit" onclick="return confirm('Audit history রেখে মাসটি আবার খুলবেন?');">মাস আবার খুলুন</button><?php else: ?><input type="hidden" name="action" value="close_month"><input class="wide" name="note" placeholder="মাস বন্ধের নোট"><button class="pv2-btn gold wide" type="submit" onclick="return confirm('বর্তমান হিসাব immutable snapshot হিসেবে বন্ধ করবেন?');">মাস বন্ধ ও Snapshot নিন</button><?php endif; ?></form>
        <?php if ($locked && !empty($report['snapshot_hash'])): ?><p class="pv2-sub">Snapshot SHA-256: <code><?php echo htmlspecialchars($report['snapshot_hash']); ?></code></p><?php endif; ?>
    </section><?php endif; ?>
</main>
<script>
function pav2ExpensePayer(form) {
    const partnerPaid = form.elements.payer_type.value === 'partner';
    form.elements.payer_partner_id.disabled = !partnerPaid;
    form.elements.payer_partner_id.required = partnerPaid;
    form.elements.account_id.disabled = partnerPaid;
    form.elements.account_id.required = !partnerPaid;
}
function pav2BindSearch(root) {
    const q = root.querySelector('.pv2-search-q');
    const hid = root.querySelector('input[type="hidden"]');
    const box = root.querySelector('.pv2-suggest');
    const kind = root.getAttribute('data-pv2-kind') || 'product';
    let timer = null;
    if (!q || !hid || !box) return;
    q.addEventListener('input', function () {
        hid.value = '0';
        window.clearTimeout(timer);
        const v = q.value.trim();
        if (v.length < 1) {
            box.hidden = true;
            box.innerHTML = '';
            return;
        }
        timer = window.setTimeout(function () {
            let url = 'search.php?type=' + encodeURIComponent(kind) + '&q=' + encodeURIComponent(v);
            if (kind === 'invoice') {
                const prod = root.closest('form') && root.closest('form').querySelector('input[name="product_id"]');
                url += '&product_id=' + encodeURIComponent((prod && prod.value) || '0');
            }
            fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (rows) {
                box.innerHTML = '';
                (rows || []).forEach(function (row) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = row.label;
                    b.addEventListener('click', function () {
                        hid.value = String(row.id || 0);
                        q.value = row.label || '';
                        box.hidden = true;
                        if (kind === 'invoice' && row.product_id) {
                            const form = root.closest('form');
                            const prodRoot = form && form.querySelector('[data-pv2-kind="product"]');
                            const prodHid = prodRoot && prodRoot.querySelector('input[type="hidden"]');
                            const prodQ = prodRoot && prodRoot.querySelector('.pv2-search-q');
                            if (prodHid && parseInt(prodHid.value || '0', 10) < 1) {
                                prodHid.value = String(row.product_id);
                                if (prodQ && !prodQ.value.trim()) prodQ.value = row.product_label || '';
                            }
                        }
                    });
                    box.appendChild(b);
                });
                box.hidden = !rows || !rows.length;
            }).catch(function () { box.hidden = true; });
        }, 200);
    });
    document.addEventListener('click', function (ev) {
        if (!root.contains(ev.target)) box.hidden = true;
    });
}
document.querySelectorAll('.pv2-search').forEach(pav2BindSearch);
document.querySelectorAll('form[data-pv2-validate]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
        const required = form.querySelectorAll('input[data-pv2-required="1"]');
        for (let i = 0; i < required.length; i += 1) {
            if (parseInt(required[i].value || '0', 10) < 1) {
                ev.preventDefault();
                window.alert(form.getAttribute('data-pv2-validate') === 'charge'
                    ? 'পণ্য খুঁজে তালিকা থেকে বেছে নিন, তারপর ইনভয়েস খুঁজুন। চার্জের টাকা নিজে লিখুন।'
                    : 'পণ্যের নাম লিখে তালিকা থেকে বেছে নিন।');
                return;
            }
        }
        if (form.getAttribute('data-pv2-validate') === 'charge') {
            const amount = parseFloat((form.elements.amount && form.elements.amount.value) || '0');
            if (!(amount > 0)) {
                ev.preventDefault();
                window.alert('পণ্য চার্জ ম্যানুয়ালি লিখুন — অটো আসবে না।');
            }
        }
        if (form.getAttribute('data-pv2-validate') === 'rule') {
            const a = parseFloat(form.elements.partner_one_ratio.value || '0');
            const b = parseFloat(form.elements.partner_two_ratio.value || '0');
            if (Math.abs((a + b) - 100) > 0.001) {
                ev.preventDefault();
                window.alert('দুইজনের অনুপাত মিলে ঠিক ১০০% দিন।');
            }
        }
    });
});
document.querySelectorAll('input[name="partner_one_ratio"], input[name="partner_two_ratio"]').forEach(function (inp) {
    const form = inp.form;
    const hint = form && form.querySelector('.pv2-ratio-hint');
    function sync() {
        if (!form || !hint) return;
        const a = parseFloat(form.elements.partner_one_ratio.value || '0');
        const b = parseFloat(form.elements.partner_two_ratio.value || '0');
        const s = Math.round((a + b) * 10000) / 10000;
        hint.textContent = 'মোট ' + s + '% — দুইজন মিলে ঠিক ১০০ হতে হবে।';
        hint.style.color = Math.abs(s - 100) < 0.001 ? '#0e8f52' : '#c62828';
    }
    inp.addEventListener('input', sync);
    sync();
});
</script>
<?php include '../../../partials/footer.php'; ?>
