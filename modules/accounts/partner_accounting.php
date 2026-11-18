<?php
/* 10.98.716 · 10.98.715 · 10.98.702 — পার্টনার ভিত্তিক খরচ অংশ */
require_once '../../includes/config.php';
require_once '../../includes/partnership_functions.php';
require_once '../../includes/partner_accounting.php';
pa_guard();
ensure_partner_accounting_schema($db);

$ym = (string)($_GET['month'] ?? date('Y-m'));
$invoice_id = (int)($_GET['invoice'] ?? 0);
$dash = ['month' => pa_month_totals($db, $ym), 'active' => [], 'partners' => [], 'payments' => [], 'capital_total' => 0, 'share_total' => 0, 'profit_total' => 0, 'paid_total' => 0, 'due_total' => 0, 'shares_ok' => false, 'closed' => false, 'locked' => false];
try {
    $dash = pa_dashboard($db, $ym);
} catch (Throwable $e) {
    error_log('partner accounting dashboard: ' . $e->getMessage());
}

$m = $dash['month'];
$bounds = $m['bounds'];
$ym = $bounds['ym'];
$selected = $invoice_id ? pa_find_invoice($db, $invoice_id, $ym) : ($m['invoices'][0] ?? null);
$cats = pa_expense_categories();
$methods = pa_payment_methods();
$locked = !empty($dash['locked']);
$closed = !empty($dash['closed']);

$flash_ok = [
    'partner' => 'পার্টনারের সব তথ্য সেভ হয়েছে।',
    'balance' => 'পার্টনারের ব্যালেন্স বদলেছে। স্টেটমেন্টের মোট এমাউন্ট এখন এই সংখ্যা।',
    'category' => 'ক্যাটাগরি যুক্ত হয়েছে। এই খাতের লাভ এখন নিজে থেকে লাভের অংশে ঢুকবে।',
    'expense' => 'খরচ সেভ হয়েছে। নেট লাভ আপডেট হয়েছে।',
    'payment' => 'পেমেন্ট সেভ হয়েছে। লেজার আপডেট হয়েছে।',
    'distributed' => 'নিট লাভ পার্টনারদের লেজারে জমা হয়েছে।',
    'capital' => 'মূলধন লেজারে সেভ হয়েছে।',
    'deleted' => 'এন্ট্রি বাতিল হয়েছে। হিসাব থেকে বাদ গেছে, রেকর্ড অডিটে আছে।',
    'closed' => 'এই মাস বন্ধ করা হয়েছে।',
    'reopened' => 'এই মাস আবার খোলা হয়েছে।',
    'start' => 'এই তারিখ থেকে খাতা শুরু। আগের সব এমাউন্ট শূন্য। এখন থেকে লাভ যোগ ও খরচ কাটা হবে।',
];
$flash_err = [
    'partner' => 'পার্টনারের নাম ও সঠিক শেয়ার দিন।',
    'email' => 'ইমেইল ঠিকানা সঠিক নয়।',
    'balance' => 'ব্যালেন্সের সংখ্যা দিন।',
    'category' => 'ক্যাটাগরি বদলানো যায়নি।',
    'share' => 'সক্রিয় পার্টনারদের শেয়ার মোট ১০০% ছাড়াতে পারে না।',
    'expense' => 'খরচের পরিমাণ দিন।',
    'future' => 'ভবিষ্যতের তারিখে খরচ লেখা যাবে না।',
    'receipt_size' => 'রসিদ সর্বোচ্চ ৫ MB হতে পারবে।',
    'receipt_type' => 'রসিদ জেপিজি, পিএনজি, ওয়েবপি বা পিডিএফ দিন।',
    'reason' => 'বাতিলের কারণ লিখতে হবে।',
    'payment' => 'পার্টনার ও সঠিক পরিমাণ দিন।',
    'nodue' => 'এই খাতের বণ্টনের বাকি নেই। আগেই জমা হয়েছে।',
    'capital' => 'পার্টনার ও সঠিক মূলধন দিন।',
    'closed' => 'এই মাস বন্ধ। শুধু অ্যাডমিন বদলাতে পারেন।',
    'perm' => 'এই কাজের অনুমতি নেই।',
    'save' => 'সেভ হয়নি। আবার চেষ্টা করুন।',
    'start' => 'শুরুর তারিখ দিন।',
];
$ok = (string)($_GET['saved'] ?? '');
$err = (string)($_GET['err'] ?? '');
$shared_cats = pa_shared_category_names($db);
$pcat = trim((string)($_GET['pcat'] ?? ''));
if ($pcat !== '' && !in_array($pcat, $shared_cats, true)) {
    $pcat = '';
}
$reserve = pa_reserve_accounts($db);
$capital_rows = pa_capital_history($db, $bounds['from'], $bounds['to']);
$pa_month_draw = [];
foreach ($capital_rows as $cr) {
    if ((string)($cr['type'] ?? '') !== 'capital_withdrawal') {
        continue;
    }
    $draw_pid = (int)($cr['partner_id'] ?? 0);
    $pa_month_draw[$draw_pid] = round(($pa_month_draw[$draw_pid] ?? 0) + pa_money($cr['amount'] ?? 0), 2);
}
$tab = (string)($_GET['tab'] ?? 'capital');
if (!in_array($tab, ['capital', 'draw', 'bank', 'stmt'], true)) {
    $tab = 'capital';
}
$stmt_id = (int)($_GET['stmt'] ?? 0);
$stmt_partner = $stmt_id ? pa_find_partner($db, $stmt_id) : ($dash['partners'][0] ?? null);
$stmt_led = $stmt_partner ? pa_ledger_rows($db, $stmt_partner, $ym) : null;
$cap_labels = [
    'investment' => 'মূলধন জমা',
    'capital_withdrawal' => 'মূলধন উত্তোলন',
    'loan_to_business' => 'ব্যবসাকে জমা',
];
$pa_books = [];
foreach ($dash['partners'] as $pa_partner_row) {
    try {
        $pa_books[(int)$pa_partner_row['id']] = pa_partner_book($db, $pa_partner_row, $ym);
    } catch (Throwable $e) {
        error_log('partner books: ' . $e->getMessage());
    }
}
$rf_raw = trim((string)($_GET['rf'] ?? $_GET['lf'] ?? ''));
$rt_raw = trim((string)($_GET['rt'] ?? $_GET['lt'] ?? ''));
$pa_rep = pa_resolve_report_range($db, $ym, $rf_raw, $rt_raw);
$sale_from = $pa_rep['from'];
$sale_to = $pa_rep['to'];
$range_on = !$pa_rep['full_month'];
$pa_period = pa_period_totals($db, $sale_from, $sale_to, $pcat);
$pur_pack = pa_ledger_purchase_lines($db, $sale_from, $sale_to, $range_on ? 500 : 120);
$pur_lines = $pur_pack['lines'] ?? [];
$pa_rep_qs = pa_report_qs($ym, $sale_from, $sale_to, ['tab' => $tab]);
$pa_rep_qs_amp = str_replace('&', '&amp;', $pa_rep_qs);
if ($pcat === '') {
    $nestle = [
        'category' => '',
        'label' => $range_on
            ? (date('d-m-Y', strtotime($sale_from)) . ' – ' . date('d-m-Y', strtotime($sale_to)))
            : ('সব অ্যাক্টিভ খাত · ' . (string)($bounds['label'] ?? $ym)),
        'sales' => (float)$pa_period['sales'],
        'cost' => (float)$pa_period['cost'],
        'profit' => (float)$pa_period['invoice_profit'],
        'expense' => (float)$pa_period['expense_total'],
        'net' => (float)$pa_period['net_profit'],
    ];
} else {
    $nestle = pa_nestle_view($db, $ym, $pcat, $m);
    if ($range_on) {
        $p_rows = pa_month_invoices($db, $sale_from, $sale_to, $pcat);
        $p_sales = $p_profit = $p_cost = 0.0;
        foreach ($p_rows as $inv) {
            $p_sales += pa_money($inv['sales_amount'] ?? 0);
            $p_cost += pa_money($inv['cost_amount'] ?? 0);
            $p_profit += pa_money($inv['profit_amount'] ?? 0);
        }
        $base = pa_money($m['invoice_profit'] ?? 0);
        $p_exp = 0.0;
        if (abs($base) > 0.009) {
            $p_exp = pa_money($pa_period['expense_total'] * ($p_profit / $base));
        } else {
            $p_exp = (float)$pa_period['expense_total'];
        }
        $nestle = [
            'category' => $pcat,
            'label' => $pcat . ' · ' . date('d-m-Y', strtotime($sale_from)) . ' – ' . date('d-m-Y', strtotime($sale_to)),
            'sales' => $p_sales,
            'cost' => $p_cost,
            'profit' => $p_profit,
            'expense' => $p_exp,
            'net' => pa_money($p_profit - $p_exp),
        ];
    }
}
$pa_period_cuts = [];
$pa_period_pcts = [];
foreach ($dash['active'] as $pa_a) {
    $pa_period_pcts[(int)$pa_a['partner']['id']] = (float)$pa_a['share_pct'];
}
if ($pa_period_pcts !== []) {
    $pa_period_cuts = pa_share_cuts((float)$pa_period['invoice_profit'], (float)$pa_period['expense_total'], $pa_period_pcts);
}
$ledger_cats = pa_ledger_categories($db, $sale_from, $sale_to);
$sale_pack = pa_ledger_sale_lines($db, $sale_from, $sale_to, $range_on ? 1000 : 200);
$sale_lines = $sale_pack['lines'] ?? [];
$sale_more = !empty($sale_pack['more']);
$sale_total = 0.0;
foreach ($sale_lines as $sl_row) {
    $sale_total += (float)$sl_row['profit'];
}
$sale_total = round($sale_total, 2);
$qd = static function (string $d): string {
    return date('Y-m-d', strtotime($d));
};
$pa_ymd = static function ($raw): string {
    $raw = trim((string)$raw);
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
        return $m[1];
    }
    return '';
};
$m_start = max($bounds['from'], $pa_rep['month_from']);
$m_end = min($bounds['to'], $qd('today'));
if ($m_end < $m_start) {
    $m_end = $bounds['to'];
}
$range_links = [
    'পুরো মাস' => [$bounds['from'], $bounds['to']],
    'আজ' => [$qd('today'), $qd('today')],
    'গতকাল' => [$qd('yesterday'), $qd('yesterday')],
    '১–আজ' => [$bounds['from'], $m_end],
];
$exp_from = $sale_from;
$exp_to = $sale_to;
$exp_range_on = $range_on;
$exp_pid = (int)($_GET['ep'] ?? 0);
$exp_partners = [];
foreach ($dash['active'] as $exp_a) {
    $exp_partners[(int)$exp_a['partner']['id']] = $exp_a['partner'];
}
if (!isset($exp_partners[$exp_pid])) {
    $exp_pid = 0;
}
$exp_pack = pa_expense_lines($db, $exp_from, $exp_to, $exp_range_on ? 1000 : 200);
$exp_lines = $exp_pack['lines'];
$exp_total_all = (float)$exp_pack['total'];
$exp_total_view = $exp_pid ? (float)($exp_pack['shares'][$exp_pid] ?? 0) : $exp_total_all;
$exp_q = $pa_rep_qs_amp . ($exp_pid ? ('&amp;ep=' . $exp_pid) : '');

require_once '../../includes/partner_expense_ui.php';
unset($ame_slip, $GLOBALS['ame_slip']);
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/partner-accounting.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">

<div class="pa-page pa-dash">
    <div class="pa-hero">
        <div class="pa-hero-top">
            <div>
                <div class="pa-kicker">হিসাব নিকাশ · পার্টনার অ্যাকাউন্টিং</div>
                <h1><?php echo htmlspecialchars(shop_name()); ?></h1>
                <p>একসাথে ব্যবসা, পরিষ্কার হিসাব। ইনভয়েস লাভ আগে ভাগ হয়। পার্টনারশিপ খরচ তারপর দুজনের লাভের অংশ থেকে শেয়ার অনুযায়ী কাটে।</p>
            </div>
            <form method="GET" class="pa-hero-range no-print" id="paHeroRangeForm">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                <div class="pa-hero-range-grid">
                    <label class="pa-hero-field">
                        <span>হিসাব মাস</span>
                        <input type="month" name="month" value="<?php echo htmlspecialchars($ym); ?>" required>
                    </label>
                    <label class="pa-hero-field">
                        <span>শুরু</span>
                        <input type="date" name="rf" min="<?php echo htmlspecialchars($bounds['from']); ?>" max="<?php echo htmlspecialchars($bounds['to']); ?>" value="<?php echo htmlspecialchars($sale_from); ?>" required>
                    </label>
                    <label class="pa-hero-field">
                        <span>শেষ</span>
                        <input type="date" name="rt" min="<?php echo htmlspecialchars($bounds['from']); ?>" max="<?php echo htmlspecialchars($bounds['to']); ?>" value="<?php echo htmlspecialchars($sale_to); ?>" required>
                    </label>
                    <div class="pa-hero-range-actions">
                        <button type="submit" class="pa-btn soft pa-hero-range-btn">রিপোর্ট দেখুন</button>
                        <button type="submit" class="pa-btn primary pa-hero-stmt-btn" formaction="partner_sales_share.php" formmethod="get" name="doc" value="all">স্টেটমেন্ট তৈরি ও শেয়ার</button>
                    </div>
                </div>
                <p class="pa-hero-range-note">শুধু এই মাসের ভিতরে তারিখ — ড্যাশবোর্ডে দ্রুত দেখুন, অথবা <strong>স্টেটমেন্ট তৈরি ও শেয়ার</strong> চাপলে সম্পূর্ণ বিক্রয় লাভ · ক্রয় · খরচ · সাবেক ব্যালেন্স সহ আন্তর্জাতিক মানের স্টেটমেন্ট (প্রিন্ট/PDF/শেয়ার) খুলবে।</p>
                <?php if (!empty($pa_rep['clamped'])): ?>
                    <p class="pa-hero-range-warn">তারিখ মাসের মধ্যে রাখা হয়েছে — বাইরের তারিখ গ্রহণ হয় না।</p>
                <?php endif; ?>
                <div class="pa-hero-range-quick">
                    <?php foreach ($range_links as $rl_label => $rl): ?>
                        <a href="<?php echo htmlspecialchars(pa_report_qs($ym, $rl[0], $rl[1], ['tab' => $tab])); ?>"><?php echo htmlspecialchars($rl_label); ?></a>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
        <div class="pa-foot-links no-print">
            <?php if ($closed): ?>
                <span class="pa-badge closed">মাস বন্ধ</span>
            <?php else: ?>
                <span class="pa-badge on">মাস খোলা</span>
            <?php endif; ?>
            <a class="pa-btn ghost" href="partner_accounting_statement.php?month=<?php echo htmlspecialchars($ym); ?>">স্টেটমেন্ট</a>
            <a class="pa-btn ghost" href="partner_accounting_ledger.php?month=<?php echo htmlspecialchars($ym); ?>">লেজার</a>
        </div>
        <?php if ($dash['partners']): ?>
        <nav class="pa-partner-toolbar no-print" aria-label="পার্টনার প্রোফাইল">
            <span class="pa-partner-toolbar-lbl">পার্টনার</span>
            <div class="pa-partner-chips">
                <?php foreach ($dash['partners'] as $pa_tool_p):
                    $pa_tid = (int)$pa_tool_p['id'];
                    $pa_tnm = trim((string)$pa_tool_p['name']);
                    $pa_tinit = function_exists('mb_substr') ? mb_substr($pa_tnm, 0, 1, 'UTF-8') : substr($pa_tnm, 0, 1);
                    $pa_tcap = pa_partner_capital($db, $pa_tid, $bounds['to']);
                    $pa_tbook = (float)($pa_books[$pa_tid]['total'] ?? 0);
                    $pa_tdraw = (float)($pa_month_draw[$pa_tid] ?? 0);
                    $pa_tst = strtolower((string)($pa_tool_p['status'] ?? 'active'));
                    ?>
                <button type="button" class="pa-partner-chip<?php echo $pa_tst === 'active' ? '' : ' is-muted'; ?>"
                    data-pa-profile="1"
                    data-pid="<?php echo $pa_tid; ?>"
                    data-name="<?php echo htmlspecialchars($pa_tnm, ENT_QUOTES, 'UTF-8'); ?>"
                    data-phone="<?php echo htmlspecialchars(trim((string)($pa_tool_p['phone'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                    data-address="<?php echo htmlspecialchars(trim((string)($pa_tool_p['address'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                    data-share="<?php echo htmlspecialchars(number_format((float)$pa_tool_p['share_percentage'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-capital="<?php echo htmlspecialchars(pa_taka($pa_tcap['total']), ENT_QUOTES, 'UTF-8'); ?>"
                    data-balance="<?php echo htmlspecialchars(pa_taka($pa_tbook), ENT_QUOTES, 'UTF-8'); ?>"
                    data-draw="<?php echo htmlspecialchars(pa_taka($pa_tdraw), ENT_QUOTES, 'UTF-8'); ?>"
                    data-status="<?php echo htmlspecialchars($pa_tst === 'active' ? 'সক্রিয়' : ($pa_tst === 'exited' ? 'প্রস্থান' : 'নিষ্ক্রিয়'), ENT_QUOTES, 'UTF-8'); ?>"
                    data-edit="<?php echo htmlspecialchars(pa_partner_edit_href($ym, $pa_tid), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars($pa_tnm . ' প্রোফাইল', ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="pa-partner-av" aria-hidden="true"><?php echo htmlspecialchars($pa_tinit); ?></span>
                    <span class="pa-partner-chip-name"><?php echo htmlspecialchars($pa_tnm); ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </nav>
        <?php endif; ?>
    </div>
    <dialog class="pa-partner-sheet no-print" id="paPartnerSheet" aria-labelledby="paPartnerSheetTitle">
        <div class="pa-partner-sheet-head">
            <h3 id="paPartnerSheetTitle">পার্টনার প্রোফাইল</h3>
            <button type="button" class="pa-partner-sheet-x" data-pa-sheet-close="1" aria-label="বন্ধ">×</button>
        </div>
        <div class="pa-partner-sheet-body" id="paPartnerSheetBody"></div>
        <div class="pa-partner-sheet-foot">
            <a class="pa-btn primary" id="paPartnerSheetEdit" href="#">এডিট / সব তথ্য</a>
            <button type="button" class="pa-btn soft" data-pa-sheet-close="1">বন্ধ</button>
        </div>
    </dialog>

    <?php if (isset($flash_ok[$ok])): ?>
        <div class="pa-alert ok"><?php echo htmlspecialchars($flash_ok[$ok]); ?></div>
    <?php elseif (isset($flash_err[$err])): ?>
        <div class="pa-alert bad"><?php echo htmlspecialchars($flash_err[$err]); ?></div>
    <?php endif; ?>

    <?php if (empty($dash['shares_ok'])): ?>
        <div class="pa-alert warn">সক্রিয় পার্টনারদের শেয়ার এখন <?php echo number_format((float)$dash['share_total'], 2); ?>%। মোট ১০০% করতে হবে — নাহলে ভাগ সঠিক হবে না।</div>
    <?php endif; ?>
    <?php if ($locked): ?>
        <div class="pa-alert info">এই মাস বন্ধ। সাধারণ ইউজার এন্ট্রি বদলাতে পারবেন না। অ্যাডমিন চাইলে আবার খুলতে পারেন।</div>
    <?php endif; ?>

    <?php
    $pa_start = pa_books_start($db);
    $pa_start_date = (string)($pa_start['date'] ?? '');
    $pa_start_show = preg_match('/^\d{4}-\d{2}-\d{2}$/', $pa_start_date) ? date('d-m-Y', strtotime($pa_start_date)) : '';
    ?>
    <section class="pa-panel no-print pa-start">
        <form method="POST" action="actions/partner_accounting.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_books_start">
            <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
            <p>যে তারিখ বাছবেন, তার আগের সব এমাউন্ট শূন্য। সেই তারিখ থেকে লাভ যোগ হবে এবং খরচ কাটবে।<?php if ($pa_start_show !== ''): ?> এখন শুরু <?php echo htmlspecialchars($pa_start_show); ?>।<?php endif; ?></p>
            <input type="date" name="start_date" required value="<?php echo htmlspecialchars($pa_start_date); ?>">
            <button type="submit" class="pa-btn primary">এই তারিখ থেকে শুরু</button>
        </form>
    </section>

    <?php
    $edit_id = (int)($_GET['edit'] ?? 0);
    $edit = ($edit_id > 0 && !$locked) ? pa_find_partner($db, $edit_id) : null;
    $edit_balance = $edit ? (float)($pa_books[(int)$edit['id']]['total'] ?? 0) : 0.0;
    ?>

    <div class="pa-cards">
        <div class="pa-card blue"><div class="lbl">মোট মূলধন <small>(মাস)</small></div><div class="val"><?php echo pa_taka($dash['capital_total']); ?></div></div>
        <div class="pa-card green"><div class="lbl">বিক্রয় · <?php echo (int)$pa_period['invoice_count']; ?> টি</div><div class="val"><?php echo pa_taka($pa_period['invoice_profit']); ?></div></div>
        <div class="pa-card teal"><div class="lbl">ক্রয় · <?php echo (int)$pa_period['purchase_count']; ?> টি</div><div class="val"><?php echo pa_taka($pa_period['purchase_total']); ?></div></div>
        <div class="pa-card gold"><div class="lbl">নিট লাভ (রেঞ্জ)</div><div class="val"><?php echo pa_taka($nestle['net']); ?></div></div>
        <div class="pa-card red"><div class="lbl"><span class="pa-nowrap">ব্যাংক ও ক্যাশ রিজার্ভ</span></div><div class="val"><?php echo pa_taka($reserve['total']); ?></div></div>
    </div>

    <section class="pa-panel pa-period-report" id="paPeriodReport">
        <div class="pa-head">
            <div>
                <h2>সময়কাল রিপোর্ট · <?php echo htmlspecialchars(date('d-m-Y', strtotime($sale_from))); ?> – <?php echo htmlspecialchars(date('d-m-Y', strtotime($sale_to))); ?></h2>
                <div class="sub">হিসাব সার্ভার থেকে — URL শুধু তারিখ ফিল্টার। টাকার অংক এখানে বদলানো যায় না। নিচে সাবেক ব্যালেন্স, তারপর এই সময়ের অংশ, শেষ কলাম মাসের বই ব্যালেন্স।</div>
            </div>
            <a class="pa-btn primary pa-stmt-open" href="<?php echo htmlspecialchars(pa_statement_share_href($ym, $sale_from, $sale_to, 'all')); ?>">স্টেটমেন্ট তৈরি ও শেয়ার</a>
        </div>
        <div class="pa-period-kpi">
            <div><span>মোট বিক্রি</span><b><?php echo pa_taka($pa_period['sales']); ?></b></div>
            <div><span>পণ্য খরচ</span><b><?php echo pa_taka($pa_period['cost']); ?></b></div>
            <div><span>পার্টনারশিপ খরচ</span><b class="pa-minus"><?php echo pa_taka($pa_period['expense_total']); ?></b></div>
            <div><span>ক্রয় (শেয়ার্ড খাত)</span><b><?php echo pa_taka($pa_period['purchase_total']); ?></b></div>
        </div>
        <?php if (!$dash['active']): ?>
            <div class="pa-empty">সক্রিয় পার্টনার লাগবে।</div>
        <?php else: ?>
        <div class="pa-table-wrap">
            <table class="pa-table pa-period-table">
                <thead>
                    <tr>
                        <th>পার্টনার</th>
                        <th class="num">সাবেক ব্যালেন্স</th>
                        <th class="num">রেঞ্জে লাভের অংশ</th>
                        <th class="num">রেঞ্জে খরচ কাটা</th>
                        <th class="num">রেঞ্জে নিট</th>
                        <th class="num">মাস শেষ ব্যালেন্স</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dash['active'] as $a):
                        $pid = (int)$a['partner']['id'];
                        $slice = $pa_period_cuts[$pid] ?? ['gross' => 0.0, 'expense' => 0.0, 'net' => 0.0];
                        $open_pack = pa_opening($db, $a['partner'], $ym);
                        $open_bal = (float)($open_pack['opening'] ?? 0);
                        $book_total = (float)($pa_books[$pid]['total'] ?? 0);
                        ?>
                    <tr>
                        <td data-label="পার্টনার"><a class="pa-link" href="<?php echo htmlspecialchars(pa_partner_edit_href($ym, $pid)); ?>"><?php echo htmlspecialchars($a['partner']['name']); ?></a></td>
                        <td class="num" data-label="সাবেক ব্যালেন্স"><?php echo pa_taka($open_bal); ?></td>
                        <td class="num" data-label="রেঞ্জে লাভের অংশ"><?php echo pa_taka($slice['gross']); ?></td>
                        <td class="num pa-cut" data-label="রেঞ্জে খরচ কাটা"><?php echo pa_taka($slice['expense']); ?></td>
                        <td class="num" data-label="রেঞ্জে নিট"><?php echo pa_taka($slice['net']); ?></td>
                        <td class="num" data-label="মাস শেষ ব্যালেন্স"><?php echo pa_taka($book_total); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php if ($pur_lines): ?>
        <h3 class="pa-period-sub">ক্রয় লাইন (শেয়ার্ড খাত)</h3>
        <div class="pa-sale-list">
            <?php foreach ($pur_lines as $pl): ?>
                <div class="pa-sale-line">
                    <span class="pa-sale-date"><?php echo htmlspecialchars(date('d-m-Y', strtotime((string)($pl['date'] ?? '')))); ?></span>
                    <span class="pa-sale-who"><?php echo htmlspecialchars(implode(' · ', array_filter([(string)($pl['memo_no'] ?? ''), (string)($pl['product_name'] ?? ''), (string)($pl['category_name'] ?? '')]))); ?></span>
                    <b><?php echo pa_taka($pl['total_cost'] ?? 0); ?></b>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="pa-panel" id="paLedgerBox">
        <div class="pa-head">
            <div>
                <h2>মূল খাতা থেকে পার্টনারের লাভ</h2>
                <div class="sub">ক্যাটাগরি বেছে যুক্ত করুন। যুক্ত খাতের লাভ নিজে থেকে পার্টনারদের লাভের অংশে ঢুকে যায়। নিচে শুধু সেই খাতের বিক্রি — কাস্টমার, ইনভয়েস নম্বর, পণ্যের নাম, ডানে লাভ।</div>
            </div>
        </div>
        <?php
        $added_cats = [];
        $open_cats = [];
        foreach ($ledger_cats as $cat) {
            if ((int)$cat['shared'] === 1) {
                $added_cats[] = $cat;
            } else {
                $open_cats[] = $cat;
            }
        }
        ?>
        <?php if (!$ledger_cats): ?>
            <div class="pa-empty">মূল খাতায় কোনো ক্যাটাগরি নেই। পণ্যের খাত বানালে এখানে যুক্ত করা যাবে।</div>
        <?php elseif (!$locked): ?>
            <form class="pa-form pa-cat-add no-print" method="POST" action="actions/partner_accounting.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="toggle_ledger_category">
                <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
                <input type="hidden" name="shared" value="1">
                <select name="category_id" required>
                    <option value="">ক্যাটাগরি বাছুন</option>
                    <?php foreach ($open_cats as $cat): ?>
                        <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="pa-btn primary"<?php echo $open_cats ? '' : ' disabled'; ?>>যুক্ত করুন</button>
            </form>
        <?php endif; ?>
        <?php if ($added_cats): ?>
            <div class="pa-cat-chips">
                <?php foreach ($added_cats as $cat): ?>
                    <span class="pa-cat-chip">
                        <?php echo htmlspecialchars($cat['name']); ?>
                        <b class="<?php echo ((float)$cat['profit'] < -0.009) ? 'pa-minus' : 'pa-plus'; ?>"><?php echo pa_taka($cat['profit']); ?></b>
                        <?php if (!$locked): ?>
                        <form method="POST" action="actions/partner_accounting.php">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle_ledger_category">
                            <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
                            <input type="hidden" name="category_id" value="<?php echo (int)$cat['id']; ?>">
                            <input type="hidden" name="shared" value="0">
                            <button type="submit" class="pa-chip-x">বাদ</button>
                        </form>
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <p class="pa-range-sync no-print">বিক্রয় তালিকা · উপরের <strong>রিপোর্ট দেখুন</strong> তারিখ অনুযায়ী (<?php echo htmlspecialchars(date('d-m-Y', strtotime($sale_from))); ?> – <?php echo htmlspecialchars(date('d-m-Y', strtotime($sale_to))); ?>)</p>
        <div class="pa-range-sum">
            <span><?php echo $range_on ? (htmlspecialchars(date('d-m-Y', strtotime($sale_from))) . ' থেকে ' . htmlspecialchars(date('d-m-Y', strtotime($sale_to)))) : ('এই মাস · ' . htmlspecialchars(date('d-m-Y', strtotime($sale_from))) . ' থেকে ' . htmlspecialchars(date('d-m-Y', strtotime($sale_to)))); ?></span>
            <span>মোট <b><?php echo (int)count($sale_lines); ?></b>টি বিক্রি</span>
            <span>লাভ <b class="<?php echo $sale_total < -0.009 ? 'pa-minus' : 'pa-plus'; ?>"><?php echo pa_taka($sale_total); ?></b></span>
        </div>
        <?php if (!$sale_lines): ?>
            <div class="pa-empty"><?php echo $range_on ? 'এই তারিখে যুক্ত খাতের কোনো বিক্রি নেই।' : 'ক্যাটাগরি যুক্ত করলে শুধু সেই খাতের বিক্রি এখানে এক লাইনে আসবে।'; ?></div>
        <?php else: ?>
            <div class="pa-sale-list">
                <?php foreach ($sale_lines as $line):
                    $who = (string)$line['customer_name'];
                    $inv_no = (string)$line['invoice_no'];
                    $product = (string)$line['product_name'];
                    $bits = array_values(array_filter([$who, $inv_no, $product], static function ($bit) {
                        return $bit !== '';
                    }));
                    ?>
                    <?php $line_date = preg_match('/^\d{4}-\d{2}-\d{2}/', (string)($line['invoice_date'] ?? '')) ? date('d-m-Y', strtotime(substr((string)$line['invoice_date'], 0, 10))) : ''; /* 10.98.697 */ ?>
                    <div class="pa-sale-line">
                        <?php if ($line_date !== ''): ?><span class="pa-sale-date"><?php echo htmlspecialchars($line_date); ?></span><?php endif; ?>
                        <span class="pa-sale-who"><?php echo htmlspecialchars(implode(' · ', $bits)); ?></span>
                        <b class="<?php echo ((float)$line['profit'] < -0.009) ? 'pa-minus' : 'pa-plus'; ?>"><?php echo pa_taka($line['profit']); ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($sale_more): ?>
                <div class="sub">সর্বশেষ <?php echo $range_on ? '১০০০' : '২০০'; ?>টি বিক্রি উপরে। <?php echo $range_on ? 'তারিখ ছোট করলে সব দেখা যাবে।' : 'পুরো লাভ নিজে থেকে লাভের অংশে ঢুকেছে।'; ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="pa-panel" id="paExpBox">
        <div class="pa-head">
            <div>
                <h2>পার্টনার ভিত্তিক খরচ</h2>
                <div class="sub">পার্টনারশিপের খরচ তারিখ ধরে এক লাইনে — খাত, বিবরণ, ডানে টাকা। পার্টনার বেছে নিলে তাঁর শেয়ারের খরচ দেখাবে।</div>
            </div>
        </div>
        <?php if ($exp_partners): ?>
        <div class="pa-cat-chips pa-exp-who no-print">
            <a class="pa-exp-pick<?php echo $exp_pid === 0 ? ' on' : ''; ?>" href="?month=<?php echo htmlspecialchars($ym); ?>&amp;tab=<?php echo htmlspecialchars($tab); ?><?php echo $exp_range_on ? ('&amp;ef=' . htmlspecialchars($exp_from) . '&amp;et=' . htmlspecialchars($exp_to)) : ''; ?>#paExpBox">সব পার্টনার</a>
            <?php foreach ($exp_partners as $exp_id => $exp_p): ?>
                <a class="pa-exp-pick<?php echo $exp_pid === $exp_id ? ' on' : ''; ?>" href="?month=<?php echo htmlspecialchars($ym); ?>&amp;tab=<?php echo htmlspecialchars($tab); ?>&amp;ep=<?php echo $exp_id; ?><?php echo $exp_range_on ? ('&amp;ef=' . htmlspecialchars($exp_from) . '&amp;et=' . htmlspecialchars($exp_to)) : ''; ?>#paExpBox"><?php echo htmlspecialchars($exp_p['name']); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <p class="pa-range-sync no-print">খরচ তালিকা · একই তারিখ রেঞ্জ</p>
        <div class="pa-range-sum">
            <span><?php echo $exp_range_on ? '' : 'এই মাস · '; ?><?php echo htmlspecialchars(date('d-m-Y', strtotime($exp_from))); ?> থেকে <?php echo htmlspecialchars(date('d-m-Y', strtotime($exp_to))); ?></span>
            <span>মোট <b><?php echo (int)count($exp_lines); ?></b>টি খরচ</span>
            <span><?php echo $exp_pid ? htmlspecialchars((string)$exp_partners[$exp_pid]['name']) . ' এর খরচ' : 'মোট খরচ'; ?> <b class="pa-minus"><?php echo pa_taka($exp_total_view); ?></b></span>
        </div>
        <?php if (!$exp_pid && $exp_partners): ?>
        <div class="pa-cat-chips">
            <?php foreach ($exp_partners as $exp_id => $exp_p): ?>
                <span class="pa-cat-chip"><?php echo htmlspecialchars($exp_p['name']); ?> <b class="pa-minus"><?php echo pa_taka($exp_pack['shares'][$exp_id] ?? 0); ?></b></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php /* 10.98.714 */ if (!$locked): ?>
        <details class="pa-exp-add no-print" id="paExpAddBox">
            <summary class="pa-btn red">+ নতুন খরচ যোগ করুন</summary>
            <?php echo pa_expense_form_html($ym, $cats, 'paExpAdd'); ?>
        </details>
        <?php else: ?>
        <div class="pa-empty no-print">এই মাস বন্ধ আছে, তাই নতুন খরচ যোগ করা যাবে না। মাস আবার খুলে যোগ করুন।</div>
        <?php endif; ?>
        <?php if (!$exp_lines): ?>
            <div class="pa-empty"><?php echo $exp_range_on ? 'এই তারিখে পার্টনারশিপের কোনো খরচ নেই।' : 'এই মাসে পার্টনারশিপের কোনো খরচ নেই।'; ?></div>
        <?php else: ?>
            <div class="pa-sale-list">
                <?php foreach ($exp_lines as $exl):
                    $exl_date = preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$exl['date']) ? date('d-m-Y', strtotime(substr((string)$exl['date'], 0, 10))) : '';
                    $exl_bits = array_values(array_filter([(string)$exl['category'], (string)$exl['description']], static function ($bit) {
                        return $bit !== '';
                    }));
                    $exl_amount = $exp_pid ? (float)($exl['by_partner'][$exp_pid] ?? 0) : (float)$exl['amount'];
                    ?>
                    <div class="pa-sale-line">
                        <?php if ($exl_date !== ''): ?><span class="pa-sale-date"><?php echo htmlspecialchars($exl_date); ?></span><?php endif; ?>
                        <span class="pa-sale-who"><?php echo htmlspecialchars(implode(' · ', $exl_bits)); ?></span>
                        <b class="pa-minus"><?php echo pa_taka($exl_amount); ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($exp_pack['more'])): ?>
                <div class="sub">সর্বশেষ <?php echo $exp_range_on ? '১০০০' : '২০০'; ?>টি খরচ উপরে।</div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php
    $pview = (int)($_GET['pview'] ?? 0);
    $src_invoices = $pcat !== ''
        ? pa_month_invoices($db, $bounds['from'], $bounds['to'], $pcat)
        : $m['invoices'];
    $src_rows = [];
    $src_profit = 0.0;
    $src_partner_tot = [];
    foreach ($dash['active'] as $a) {
        $src_partner_tot[(int)$a['partner']['id']] = 0.0;
    }
    foreach ($src_invoices as $inv) {
        $shares = pa_invoice_shares($inv, $dash['active']);
        $line_total = 0.0;
        foreach ($shares as $sh) {
            $line_total += pa_money($sh['amount']);
        }
        $src_profit += pa_money($inv['profit_amount'] ?? 0);
        foreach ($dash['active'] as $i => $a) {
            $src_partner_tot[(int)$a['partner']['id']] += pa_money($shares[$i]['amount'] ?? 0);
        }
        $src_rows[] = ['inv' => $inv, 'shares' => $shares, 'line' => $line_total];
    }
    $src_show = $dash['active'];
    if ($pview > 0) {
        $src_show = array_values(array_filter($dash['active'], static function ($a) use ($pview) {
            return (int)$a['partner']['id'] === $pview;
        }));
    }
    $src_grand = 0.0;
    foreach ($src_show as $a) {
        $src_grand += $src_partner_tot[(int)$a['partner']['id']] ?? 0;
    }
    ?>
    <section class="pa-panel" id="paCategoryProfit">
        <div class="pa-head">
            <div>
                <h2>ক্যাটাগরি · নিট লাভ ও ইনভয়েস</h2>
                <div class="sub"><?php echo htmlspecialchars($nestle['label']); ?> · উপরে নিট লাভ ও পার্টনার ভাগ; নিচে খাত অনুযায়ী ইনভয়েস লাভের বিস্তার। নিট লাভের অংশ <strong>স্বয়ংক্রিয়ভাবে</strong> লেজার বইতে যোগ হয়।</div>
            </div>
            <form method="GET" class="pa-month-form no-print pa-head-filters">
                <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                <select name="pcat" aria-label="ক্যাটাগরি">
                    <option value="">সব অ্যাক্টিভ খাত</option>
                    <?php foreach ($shared_cats as $cname): ?>
                        <option value="<?php echo htmlspecialchars($cname); ?>"<?php echo $pcat === $cname ? ' selected' : ''; ?>><?php echo htmlspecialchars($cname); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="pview" aria-label="পার্টনার ফিল্টার">
                    <option value="0">সব পার্টনার</option>
                    <?php foreach ($dash['active'] as $a): ?>
                        <option value="<?php echo (int)$a['partner']['id']; ?>"<?php echo $pview === (int)$a['partner']['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($a['partner']['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="pa-btn ghost">দেখুন</button>
            </form>
        </div>
        <div class="pa-mini pa-mini-combined">
            <div class="row"><span>মোট বিক্রি</span><span><?php echo pa_taka($nestle['sales']); ?></span></div>
            <div class="row"><span>পণ্য খরচ</span><span><?php echo pa_taka($nestle['cost']); ?></span></div>
            <div class="row"><span>পার্টনারশিপ খরচ (−)</span><span><?php echo pa_taka($nestle['expense']); ?></span></div>
            <div class="net">নিট লাভ <?php echo pa_taka($nestle['net']); ?></div>
            <div class="row"><span>ইনভয়েস লাভ (নির্বাচিত খাত)</span><span><?php echo pa_taka($src_profit); ?></span></div>
            <div class="row"><span>পার্টনারদের মোট</span><span><?php echo pa_taka($src_grand); ?></span></div>
        </div>
        <?php if (!$dash['active']): ?>
            <div class="pa-empty">সক্রিয় পার্টনার লাগবে। তারপর শেয়ার অনুযায়ী নিট লাভ ভাগ হবে।</div>
        <?php else: ?>
            <div class="pa-table-wrap">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>পার্টনার</th>
                            <th class="num">শেয়ার %</th>
                            <th class="num">লাভের অংশ</th>
                            <th class="num">খরচ কাটা</th>
                            <th class="num">নিট লাভের অংশ</th>
                            <th class="num">বর্তমান ব্যালেন্স</th>
                            <th class="num">এই সময়ে লেজারে যুক্ত</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $nestle_pcts = [];
                        foreach ($dash['active'] as $a) {
                            $nestle_pcts[(int)$a['partner']['id']] = (float)$a['share_pct'];
                        }
                        $nestle_cuts = pa_share_cuts((float)$nestle['profit'], (float)$nestle['expense'], $nestle_pcts);
                        foreach ($dash['active'] as $a):
                            $pid = (int)$a['partner']['id'];
                            $slice_cut = ($range_on && $pcat === '')
                                ? ($pa_period_cuts[$pid] ?? ['gross' => 0.0, 'expense' => 0.0, 'net' => 0.0])
                                : ($nestle_cuts[$pid] ?? ['gross' => 0.0, 'expense' => 0.0, 'net' => 0.0]);
                            ?>
                            <tr>
                                <td data-label="পার্টনার"><a class="pa-link pa-name" href="<?php echo htmlspecialchars(pa_partner_edit_href($ym, (int)$a['partner']['id'])); ?>"><?php echo htmlspecialchars($a['partner']['name']); ?></a></td>
                                <td class="num" data-label="শেয়ার %"><?php echo number_format((float)$a['share_pct'], 2); ?>%</td>
                                <td class="num" data-label="লাভের অংশ"><?php echo pa_taka($slice_cut['gross']); ?></td>
                                <td class="num pa-cut" data-label="খরচ কাটা"><?php echo pa_taka($slice_cut['expense']); ?></td>
                                <td class="num" data-label="নিট লাভের অংশ"><?php echo pa_taka($slice_cut['net']); ?></td>
                                <td class="num pa-bal-cell" data-label="বর্তমান ব্যালেন্স">
                                    <?php
                                    $book_total = (float)($pa_books[$pid]['total'] ?? 0);
                                    ?>
                                    <b><?php echo pa_taka($book_total); ?></b>
                                    <span class="pa-bal-hint">সাবেক + এই মাসের লাভ/খরচ + উত্তোলন (লেজার)</span>
                                </td>
                                <td class="num pa-plus" data-label="এই সময়ে লেজারে যুক্ত"><?php echo pa_taka($slice_cut['net']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="pa-auto-profit-note no-print">নিট লাভের অংশ লেজার বইতে স্বয়ংক্রিয় যোগ হয় — «লাভ বণ্টন» বা «পরিশোধ» বাটন আর নেই। টাকা তুললে উপরের <strong>উত্তোলন</strong> ট্যাবে লিখুন, ব্যালেন্স কমবে।</p>
        <?php endif; ?>

        <h3 class="pa-subhead">ইনভয়েস অনুযায়ী লাভ</h3>
        <p class="sub pa-subhead-note">কোন খাতের কোন ইনভয়েস থেকে কোন পার্টনারের কত লাভ — শেয়ার % আগের হিসাব।</p>
        <?php if (!$src_rows): ?>
            <div class="pa-empty">এই মাসে এই খাতের কোনো ইনভয়েস নেই। ক্যাটাগরি বদলালে সেই খাতের ইনভয়েস ও মোট এখানে আসবে।</div>
        <?php else: ?>
            <div class="pa-table-wrap">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>ক্যাটাগরি</th>
                            <th>ইনভয়েস</th>
                            <th class="num">লাভ</th>
                            <?php foreach ($src_show as $a): ?>
                                <th class="num"><a class="pa-name" href="<?php echo htmlspecialchars(pa_partner_edit_href($ym, (int)$a['partner']['id'])); ?>"><?php echo htmlspecialchars($a['partner']['name']); ?></a></th>
                            <?php endforeach; ?>
                            <th class="num">মোট</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($src_rows as $src):
                            $inv = $src['inv'];
                            $row_sum = 0.0;
                            ?>
                            <tr>
                                <td data-label="ক্যাটাগরি"><?php echo htmlspecialchars((string)($inv['category_names'] ?? '')); ?></td>
                                <td data-label="ইনভয়েস">
                                    <span class="pa-src-inv"><?php echo htmlspecialchars((string)($inv['invoice_no'] ?? '')); ?></span>
                                    <span class="pa-src-who"><?php echo htmlspecialchars((string)($inv['customer_name'] ?? '')); ?></span>
                                </td>
                                <td class="num" data-label="লাভ"><?php echo pa_taka($inv['profit_amount']); ?></td>
                                <?php foreach ($src_show as $a):
                                    $amt = 0.0;
                                    foreach ($src['shares'] as $sh) {
                                        if ($sh['name'] === (string)$a['partner']['name']) {
                                            $amt = pa_money($sh['amount']);
                                            break;
                                        }
                                    }
                                    $row_sum += $amt;
                                    ?>
                                    <td class="num" data-label="<?php echo htmlspecialchars($a['partner']['name']); ?>"><?php echo pa_taka($amt); ?></td>
                                <?php endforeach; ?>
                                <td class="num" data-label="মোট"><?php echo pa_taka($row_sum); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" data-label="মোট">মোট</td>
                            <td class="num" data-label="লাভ"><?php echo pa_taka($src_profit); ?></td>
                            <?php foreach ($src_show as $a): ?>
                                <td class="num" data-label="<?php echo htmlspecialchars($a['partner']['name']); ?>"><?php echo pa_taka($src_partner_tot[(int)$a['partner']['id']] ?? 0); ?></td>
                            <?php endforeach; ?>
                            <td class="num" data-label="মোট"><?php echo pa_taka($src_grand); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <div class="pa-tabs no-print" role="tablist">
        <button type="button" class="pa-tab<?php echo $tab === 'capital' ? ' is-on' : ''; ?>" data-pa-tab="capital">ক্যাপিটাল ও ইনভেস্টমেন্ট</button>
        <button type="button" class="pa-tab<?php echo $tab === 'draw' ? ' is-on' : ''; ?>" data-pa-tab="draw">পার্টনার খরচ</button>
        <button type="button" class="pa-tab<?php echo $tab === 'bank' ? ' is-on' : ''; ?>" data-pa-tab="bank">ব্যাংক স্টেটমেন্ট</button>
        <button type="button" class="pa-tab<?php echo $tab === 'stmt' ? ' is-on' : ''; ?>" data-pa-tab="stmt">পার্টনার স্টেটমেন্ট</button>
    </div>

    <section class="pa-panel pa-pane<?php echo $tab === 'capital' ? ' is-on' : ''; ?>" data-pa-pane="capital">
        <div class="pa-head">
            <div>
                <h2>পার্টনার তথ্য</h2>
                <div class="sub">নামে চাপলে নাম, নম্বর, ঠিকানা, ইমেইল, শেয়ার ও ব্যালেন্স এডিট হয়। শেয়ার মোট ১০০% হতে হবে।</div>
            </div>
        </div>
        <?php if (!$dash['partners']): ?>
            <div class="pa-empty">এখনো পার্টনার নেই। নিচে নাম, মূলধন ও শেয়ার দিয়ে যোগ করুন।</div>
        <?php else: ?>
            <div class="pa-table-wrap">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>পার্টনার</th>
                            <th class="num">মূলধন</th>
                            <th class="num">শেয়ার %</th>
                            <th class="num">এই মাসের ব্যালেন্স</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($dash['partners'] as $p):
                            $cap = pa_partner_capital($db, (int)$p['id'], $bounds['to']);
                            $row = null;
                            foreach ($dash['active'] as $a) {
                                if ((int)$a['partner']['id'] === (int)$p['id']) { $row = $a; break; }
                            }
                            $st = strtolower((string)($p['status'] ?? 'active'));
                            ?>
                            <tr>
                                <td data-label="#"><?php echo $i++; ?></td>
                                <td data-label="পার্টনার">
                                    <a class="pa-link pa-name" href="<?php echo htmlspecialchars(pa_partner_edit_href($ym, (int)$p['id'])); ?>"><?php echo htmlspecialchars($p['name']); ?></a>
                                    <div style="font-size:11px;"><a class="pa-link" href="partner_accounting_ledger.php?partner_id=<?php echo (int)$p['id']; ?>&month=<?php echo htmlspecialchars($ym); ?>">লেজার</a></div>
                                    <div style="font-size:11px;color:#78909C;">আইডি #<?php echo (int)$p['id']; ?></div>
                                </td>
                                <td class="num" data-label="মূলধন"><?php echo pa_taka($cap['total']); ?></td>
                                <td class="num" data-label="শেয়ার %"><?php echo rtrim(rtrim(number_format((float)$p['share_percentage'], 2), '0'), '.'); ?>%</td>
                                <td class="num pa-bal-cell" data-label="এই মাসের ব্যালেন্স">
                                    <?php
                                    $book_total = (float)($pa_books[(int)$p['id']]['total'] ?? ($row['balance'] ?? $cap['total']));
                                    echo pa_taka($book_total);
                                    if (!$locked):
                                    ?>
                                    <form class="pa-bal-form no-print" method="POST" action="actions/partner_accounting.php">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="save_balance">
                                        <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
                                        <input type="hidden" name="partner_id" value="<?php echo (int)$p['id']; ?>">
                                        <input type="hidden" name="tab" value="capital">
                                        <input type="number" step="0.01" name="balance" value="<?php echo htmlspecialchars(number_format($book_total, 2, '.', '')); ?>" aria-label="এই মাসের ব্যালেন্স" required>
                                        <button type="submit" class="pa-btn primary">ব্যালেন্স সেভ</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                                <td data-label="স্ট্যাটাস">
                                    <span class="pa-badge <?php echo $st === 'active' ? 'on' : 'off'; ?>"><?php echo $st === 'active' ? 'সক্রিয়' : ($st === 'exited' ? 'প্রস্থান' : 'নিষ্ক্রিয়'); ?></span>
                                    <?php if (!$locked): ?>
                                        <a class="pa-btn soft no-print" href="<?php echo htmlspecialchars(pa_partner_edit_href($ym, (int)$p['id'])); ?>">এডিট</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" data-label="মোট">মোট</td>
                            <td class="num" data-label="মূলধন"><?php echo pa_taka($dash['capital_total']); ?></td>
                            <td class="num" data-label="শেয়ার %"><?php echo number_format((float)$dash['share_total'], 2); ?>%</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>

    <?php if ($edit_id > 0 && !$edit): ?>
    <div class="pa-alert <?php echo $locked ? 'info' : 'bad'; ?>" id="paPartnerEdit"><?php echo $locked ? 'এই মাস বন্ধ, তাই পার্টনারের তথ্য এডিট করা যাবে না। উপরে «মাস বন্ধ» খুলে আবার চেষ্টা করুন।' : 'এই পার্টনার পাওয়া যায়নি।'; ?></div>
    <?php endif; ?>
    <?php if ($edit): ?>
    <section class="pa-panel no-print" id="paPartnerEdit">
        <div class="pa-head">
            <div>
                <h2><?php echo htmlspecialchars((string)$edit['name']); ?> · সব তথ্য</h2>
                <div class="sub">নাম, নম্বর, ঠিকানা, ইমেইল, শেয়ার, মূলধন ও ব্যালেন্স। বিক্রয়, ক্যাশ ও কাস্টমারের টাকা বদলায় না।</div>
            </div>
            <div class="pa-edit-switch no-print">
                <?php foreach ($dash['partners'] as $sw_p): ?>
                    <a class="pa-btn <?php echo (int)$sw_p['id'] === (int)$edit['id'] ? 'primary' : 'soft'; ?>" href="<?php echo htmlspecialchars(pa_partner_edit_href($ym, (int)$sw_p['id'])); ?>"><?php echo htmlspecialchars((string)$sw_p['name']); ?></a>
                <?php endforeach; ?>
            </div>
            <a class="pa-btn soft" href="?month=<?php echo htmlspecialchars($ym); ?>&tab=capital">বন্ধ</a>
        </div>
        <form class="pa-edit" method="POST" action="actions/partner_accounting.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_partner">
            <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
            <input type="hidden" name="partner_id" value="<?php echo (int)$edit['id']; ?>">
            <label>নাম
                <input type="text" name="name" required maxlength="100" value="<?php echo htmlspecialchars((string)$edit['name']); ?>">
            </label>
            <label>নম্বর
                <input type="text" name="phone" maxlength="20" inputmode="tel" value="<?php echo htmlspecialchars((string)($edit['phone'] ?? '')); ?>" placeholder="ফোন নম্বর">
            </label>
            <label class="wide">ঠিকানা
                <input type="text" name="address" maxlength="255" value="<?php echo htmlspecialchars((string)($edit['address'] ?? '')); ?>" placeholder="ঠিকানা">
            </label>
            <label>ইমেইল
                <input type="email" name="email" maxlength="100" value="<?php echo htmlspecialchars((string)($edit['email'] ?? '')); ?>" placeholder="ইমেইল">
            </label>
            <label>ধরন
                <select name="partner_type">
                    <?php foreach (['equity' => 'ইক্যুইটি পার্টনার', 'profit_share' => 'শুধু লাভের ভাগ', 'silent' => 'সাইলেন্ট পার্টনার'] as $val => $label): ?>
                        <option value="<?php echo $val; ?>"<?php echo (($edit['partner_type'] ?? 'equity') === $val) ? ' selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>শেয়ার %
                <input type="number" step="0.01" min="0" max="100" name="share_percentage" required value="<?php echo htmlspecialchars((string)$edit['share_percentage']); ?>">
            </label>
            <label>শুরুর মূলধন
                <input type="number" step="0.01" min="0" name="initial_capital" required value="<?php echo htmlspecialchars(number_format((float)($edit['initial_capital'] ?? 0), 2, '.', '')); ?>">
            </label>
            <label>ব্যালেন্স
                <input type="number" step="0.01" name="balance" required value="<?php echo htmlspecialchars(number_format($edit_balance, 2, '.', '')); ?>">
            </label>
            <label>যোগদান
                <input type="date" name="join_date" value="<?php echo htmlspecialchars($pa_ymd($edit['join_date'] ?? '')); ?>">
            </label>
            <label>প্রস্থান
                <input type="date" name="exit_date" value="<?php echo htmlspecialchars($pa_ymd($edit['exit_date'] ?? '')); ?>">
            </label>
            <label>স্ট্যাটাস
                <select name="status">
                    <option value="active"<?php echo (($edit['status'] ?? 'active') === 'active') ? ' selected' : ''; ?>>সক্রিয়</option>
                    <option value="inactive"<?php echo (($edit['status'] ?? '') === 'inactive') ? ' selected' : ''; ?>>নিষ্ক্রিয়</option>
                    <option value="exited"<?php echo (($edit['status'] ?? '') === 'exited') ? ' selected' : ''; ?>>প্রস্থান</option>
                </select>
            </label>
            <label class="wide">নোট
                <textarea name="notes" rows="3" maxlength="500"><?php echo htmlspecialchars((string)($edit['notes'] ?? '')); ?></textarea>
            </label>
            <button type="submit" class="pa-btn primary wide">সব তথ্য সেভ</button>
        </form>
    </section>
    <?php endif; ?>
        <?php if (!$locked): ?>
        <form class="pa-form no-print" method="POST" action="actions/partner_accounting.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_partner">
            <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
            <input type="hidden" name="partner_id" value="0">
            <input type="text" name="name" placeholder="নতুন পার্টনারের নাম" required maxlength="100">
            <input type="number" step="0.01" min="0" name="initial_capital" placeholder="শুরুর মূলধন (৳)" required>
            <input type="number" step="0.01" min="0" max="100" name="share_percentage" placeholder="লাভের শেয়ার %" required>
            <select name="status">
                <option value="active">সক্রিয়</option>
                <option value="inactive">নিষ্ক্রিয়</option>
                <option value="exited">প্রস্থান</option>
            </select>
            <button type="submit" class="pa-btn primary">পার্টনার সেভ</button>
        </form>
        <?php endif; ?>

        <h2 style="margin-top:16px;">মূলধন লেনদেন · <?php echo htmlspecialchars($bounds['label']); ?></h2>
        <div class="sub">জমা মূলধন বাড়ে, উত্তোলন কমে। বিক্রয় বা কাস্টমারের টাকা এখানে বদলায় না।</div>
        <?php
        $invest_rows = array_values(array_filter($capital_rows, static function ($row) {
            return ($row['type'] ?? '') === 'investment';
        }));
        ?>
        <?php if (!$invest_rows): ?>
            <div class="pa-empty">এই মাসে নতুন মূলধন জমা নেই।</div>
        <?php else: ?>
            <div class="pa-table-wrap">
                <table class="pa-table">
                    <thead><tr><th>তারিখ</th><th>পার্টনার</th><th>ধরন</th><th class="num">টাকা</th><th>নোট</th></tr></thead>
                    <tbody>
                        <?php foreach ($invest_rows as $cr): ?>
                            <tr>
                                <td data-label="তারিখ"><?php echo htmlspecialchars(date('d-m-Y', strtotime((string)$cr['transaction_date']))); ?></td>
                                <td data-label="পার্টনার"><?php echo htmlspecialchars((string)($cr['partner_name'] ?? '')); ?></td>
                                <td data-label="ধরন"><?php echo htmlspecialchars($cap_labels[$cr['type']] ?? $cr['type']); ?></td>
                                <td class="num" data-label="টাকা"><?php echo pa_taka($cr['amount']); ?></td>
                                <td data-label="নোট"><?php echo htmlspecialchars((string)($cr['note'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if (!$locked && $dash['partners']): ?>
        <form class="pa-form no-print" method="POST" action="actions/partner_accounting.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_capital">
            <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
            <input type="hidden" name="type" value="investment">
            <select name="partner_id" required>
                <?php foreach ($dash['partners'] as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" step="0.01" min="0.01" name="amount" placeholder="মূলধন (৳)" required>
            <input type="date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
            <input type="text" name="note" placeholder="নোট (ঐচ্ছিক)" maxlength="255">
            <button type="submit" class="pa-btn primary">+ <?php echo ame_l('মূলধন যোগ করুন','Add Capital'); ?></button>
        </form>
        <?php endif; ?>
    </section>

    <section class="pa-panel pa-pane<?php echo $tab === 'stmt' ? ' is-on' : ''; ?>" data-pa-pane="stmt">
        <div class="pa-head">
            <div>
                <h2>মাসিক পার্টনার সারাংশ · <?php echo htmlspecialchars($bounds['label']); ?></h2>
                <div class="sub">অ্যাক্টিভ ক্যাটাগরির লাভ × শেয়ার % = প্রাপ্য। অন্য খাতের বিক্রি যোগ হয় না।</div>
            </div>
        </div>
        <?php if (!$dash['active']): ?>
            <div class="pa-empty">সক্রিয় পার্টনার লাগবে।</div>
        <?php else: ?>
            <div class="pa-table-wrap">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>পার্টনার</th>
                            <th class="num">শেয়ার %</th>
                            <th class="num">লাভের অংশ</th>
                            <th class="num">পরিশোধ</th>
                            <th class="num">বাকি</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dash['active'] as $a): ?>
                            <tr>
                                <td data-label="পার্টনার"><a class="pa-link pa-name" href="<?php echo htmlspecialchars(pa_partner_edit_href($ym, (int)$a['partner']['id'])); ?>"><?php echo htmlspecialchars($a['partner']['name']); ?></a></td>
                                <td class="num" data-label="শেয়ার %"><?php echo number_format((float)($a['share_pct'] ?? 0), 2); ?>%</td>
                                <td class="num" data-label="লাভের অংশ"><?php echo pa_taka($a['profit_share']); ?></td>
                                <td class="num" data-label="পরিশোধ"><?php echo pa_taka($a['paid']); ?></td>
                                <td class="num" data-label="বাকি"><?php echo pa_taka($a['due']); ?></td>
                                <td data-label="স্ট্যাটাস"><span class="pa-badge <?php echo htmlspecialchars($a['status']['key']); ?>"><?php echo htmlspecialchars($a['status']['label']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td data-label="মোট">মোট</td>
                            <td class="num" data-label="শেয়ার %"><?php echo number_format((float)$dash['share_total'], 2); ?>%</td>
                            <td class="num" data-label="লাভের অংশ"><?php echo pa_taka($dash['profit_total']); ?></td>
                            <td class="num" data-label="পরিশোধ"><?php echo pa_taka($dash['paid_total']); ?></td>
                            <td class="num" data-label="বাকি"><?php echo pa_taka($dash['due_total']); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!$locked && $dash['active']): ?>
        <form class="pa-form no-print" method="POST" action="actions/partner_accounting.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_payment">
            <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
            <input type="hidden" name="period_ym" value="<?php echo htmlspecialchars($ym); ?>">
            <select name="partner_id" required>
                <?php foreach ($dash['active'] as $a): ?>
                    <option value="<?php echo (int)$a['partner']['id']; ?>"><?php echo htmlspecialchars($a['partner']['name']); ?> — বাকি <?php echo pa_taka($a['due']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" step="0.01" min="0.01" name="amount" placeholder="পরিশোধ (৳)" required>
            <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
            <?php echo pa_account_box($db); ?>
            <input type="text" name="reference" placeholder="রেফারেন্স (ঐচ্ছিক)" maxlength="80">
            <input type="text" name="note" placeholder="নোট (ঐচ্ছিক)" maxlength="255">
            <button type="submit" class="pa-btn green">পেমেন্ট সেভ</button>
        </form>
        <?php endif; ?>
    </section>

    <section class="pa-panel pa-pane<?php echo $tab === 'stmt' ? ' is-on' : ''; ?>" data-pa-pane="stmt">
        <div class="pa-head">
            <div>
                <h2>ইনভয়েস অনুযায়ী লাভ · <?php echo htmlspecialchars($bounds['label']); ?></h2>
                <?php $shared_cats = pa_shared_category_names($db); ?>
                <div class="sub"><?php if ($shared_cats): ?>অ্যাক্টিভ খাত: <?php echo htmlspecialchars(implode(', ', $shared_cats)); ?>। শুধু এই খাতের পণ্য বিক্রি হলে পার্টনারের অংশ যোগ হয়। এক ইনভয়েসে অন্য পণ্য থাকলে সেই লাভ ধরা হয় না।<?php else: ?>কোনো ক্যাটাগরিতে ৫০% অ্যাক্টিভ নেই, তাই পার্টনার লাভে কোনো ইনভয়েস যোগ হচ্ছে না।<?php endif; ?></div>
            </div>
        </div>
        <?php if (!$m['invoices']): ?>
            <div class="pa-empty">এই মাসে অ্যাক্টিভ খাতের কোনো বিক্রয় নেই। যে ক্যাটাগরিতে ৫০% অ্যাক্টিভ, শুধু সেই পণ্যের ইনভয়েস এখানে আসে।</div>
        <?php else: ?>
            <div class="pa-table-wrap">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ক্রেতা</th>
                            <th>তারিখ</th>
                            <th>খাত</th>
                            <th class="num">বিক্রি</th>
                            <th class="num">খরচ</th>
                            <th class="num">লাভ</th>
                            <?php foreach ($dash['active'] as $a): ?>
                                <th class="num"><?php echo htmlspecialchars(mb_substr($a['partner']['name'], 0, 10, 'UTF-8')); ?> (<?php echo rtrim(rtrim(number_format((float)($a['share_pct'] ?? 0), 2), '0'), '.'); ?>%)</th>
                            <?php endforeach; ?>
                            <th class="no-print"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $n = 1; foreach ($m['invoices'] as $inv): $shares = pa_invoice_shares($inv, $dash['active']); ?>
                            <tr>
                                <td data-label="#"><?php echo $n++; ?></td>
                                <td class="pa-who" data-label="ক্রেতা">
                                    <span class="pa-who-name"><?php echo htmlspecialchars((string)($inv['customer_name'] ?? '')); ?></span>
                                    <span class="pa-who-inv"><?php echo htmlspecialchars((string)($inv['invoice_no'] ?? '')); ?></span>
                                </td>
                                <td class="pa-one" data-label="তারিখ"><?php echo htmlspecialchars(date('d-m-Y', strtotime((string)$inv['invoice_date']))); ?></td>
                                <td class="pa-one" data-label="খাত"><?php echo htmlspecialchars((string)($inv['category_names'] ?? '')); ?></td>
                                <td class="num" data-label="বিক্রি"><?php echo pa_taka($inv['sales_amount']); ?></td>
                                <td class="num" data-label="খরচ"><?php echo pa_taka($inv['cost_amount']); ?></td>
                                <td class="num" data-label="লাভ"><?php echo pa_taka($inv['profit_amount']); ?></td>
                                <?php foreach ($shares as $sh): ?>
                                    <td class="num" data-label="<?php echo htmlspecialchars($sh['name']); ?>"><?php echo pa_taka($sh['amount']); ?></td>
                                <?php endforeach; ?>
                                <td class="no-print" data-label=""><a class="pa-link" href="?month=<?php echo htmlspecialchars($ym); ?>&invoice=<?php echo (int)$inv['id']; ?>">বিস্তারিত</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" data-label="মোট">মোট</td>
                            <td class="num" data-label="বিক্রি"><?php echo pa_taka($m['sales']); ?></td>
                            <td class="num" data-label="খরচ"><?php echo pa_taka($m['cost']); ?></td>
                            <td class="num" data-label="লাভ"><?php echo pa_taka($m['invoice_profit']); ?></td>
                            <?php foreach ($dash['active'] as $a): ?>
                                <td class="num" data-label="<?php echo htmlspecialchars((string)$a['partner']['name']); ?>"><?php echo pa_taka($m['invoice_profit'] * ($a['share_pct'] / 100)); ?></td>
                            <?php endforeach; ?>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="pa-panel pa-pane<?php echo $tab === 'draw' ? ' is-on' : ''; ?>" data-pa-pane="draw">
        <div class="pa-head">
            <div>
                <h2>পার্টনারশিপ খরচ · <?php echo htmlspecialchars($bounds['label']); ?></h2>
                <div class="sub">অফিস, ভাড়া, যাতায়াত — পণ্য খরচ নয়। সেভ হলে এই খরচ দুজনের লাভের অংশ থেকে শেয়ার অনুযায়ী কাটে।</div>
            </div>
            <form method="GET" class="pa-month-form no-print">
                <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
                <select name="ecat" onchange="this.form.submit()">
                    <option value="">সব খাত</option>
                    <?php $ecat = (string)($_GET['ecat'] ?? ''); foreach ($cats as $k => $lab): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>"<?php echo $ecat === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php
        $exp_rows = $m['expenses'];
        if ($ecat !== '' && isset($cats[$ecat])) {
            $exp_rows = array_values(array_filter($exp_rows, static function ($e) use ($ecat) {
                return ($e['category'] ?? '') === $ecat;
            }));
        }
        ?>
        <?php
        $exp_cat_totals = pa_expense_category_totals($exp_rows, $cats);
        $exp_void_rows = pa_month_voided_expenses($db, $ym);
        ?>
        <?php if ($exp_cat_totals): ?>
        <div class="pa-cat-chips pa-exp-cats">
            <?php foreach ($exp_cat_totals as $ct): ?>
                <span class="pa-cat-chip"><?php echo htmlspecialchars($ct['label']); ?> <b class="pa-minus"><?php echo pa_taka($ct['amount']); ?></b> <small><?php echo $ct['pct']; ?>%</small></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="pa-exp-tools no-print">
            <a class="pa-btn soft" href="partner_expense_export.php?month=<?php echo htmlspecialchars($ym); ?><?php echo $ecat !== '' ? '&amp;ecat=' . htmlspecialchars($ecat) : ''; ?>"><?php echo ame_l('এক্সেল / সিএসভি এক্সপোর্ট','Export Excel / CSV'); ?></a>
            <button type="button" class="pa-btn soft" onclick="window.print()"><?php echo ame_l('প্রিন্ট / পিডিএফ','Print / PDF'); ?></button>
        </div>
        <?php if (!$exp_rows): ?>
            <div class="pa-empty">এই মাসে কোনো পার্টনারশিপ খরচ নেই।</div>
        <?php else: ?>
            <?php echo pa_expense_table_html($exp_rows, $cats, $ym, $locked); ?>
        <?php endif; ?>
        <?php echo pa_voided_expense_html($exp_void_rows, $cats); ?>

        <?php if (!$locked): ?>
            <h3 class="pa-exp-h">নতুন খরচ যোগ</h3>
            <?php echo pa_expense_form_html($ym, $cats, 'paExpAdd2'); ?>
        <?php endif; ?>

        <h2 style="margin-top:16px;">উত্তোলন</h2>
        <div class="sub">ব্যক্তিগত খরচ, বেতন খাতের উপরে। মূলধন উত্তোলন আলাদা। প্রতিটি পার্টনার আলাদা দেখায়।</div>
        <?php if ($dash['partners']): ?>
        <div class="pa-draw-people">
            <?php foreach ($dash['partners'] as $p): ?>
                <div class="pa-draw-person">
                    <b><?php echo htmlspecialchars((string)$p['name'], ENT_QUOTES, 'UTF-8'); ?></b>
                    <span>এই মাসে উত্তোলন <?php echo pa_taka($month_draw[(int)$p['id']] ?? 0); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php
        $draw_rows = array_values(array_filter($capital_rows, static function ($row) {
            return in_array(($row['type'] ?? ''), ['capital_withdrawal', 'loan_to_business'], true);
        }));
        ?>
        <?php if (!$draw_rows): ?>
            <div class="pa-empty">এই মাসে উত্তোলন নেই।</div>
        <?php else: ?>
            <div class="pa-table-wrap">
                <table class="pa-table">
                    <thead><tr><th>তারিখ</th><th>পার্টনার</th><th>ধরন</th><th class="num">টাকা</th><th>নোট</th></tr></thead>
                    <tbody>
                        <?php foreach ($draw_rows as $cr): ?>
                            <tr>
                                <td data-label="তারিখ"><?php echo htmlspecialchars(date('d-m-Y', strtotime((string)$cr['transaction_date']))); ?></td>
                                <td data-label="পার্টনার"><?php echo htmlspecialchars((string)($cr['partner_name'] ?? '')); ?></td>
                                <td data-label="ধরন"><?php echo htmlspecialchars($cap_labels[$cr['type']] ?? $cr['type']); ?></td>
                                <td class="num" data-label="টাকা"><?php echo pa_taka($cr['amount']); ?></td>
                                <td data-label="নোট"><?php echo htmlspecialchars((string)($cr['note'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if (!$locked && $dash['partners']): ?>
        <form class="pa-form no-print" method="POST" action="actions/partner_accounting.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_capital">
            <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
            <div class="pa-partner-picks" role="radiogroup" aria-label="উত্তোলনের পার্টনার">
                <?php foreach ($dash['partners'] as $p): ?>
                    <label class="pa-partner-pick">
                        <input type="radio" name="partner_id" value="<?php echo (int)$p['id']; ?>" required>
                        <span>
                            <b><?php echo htmlspecialchars((string)$p['name'], ENT_QUOTES, 'UTF-8'); ?></b>
                            <small>এই মাসে উত্তোলন <?php echo pa_taka($month_draw[(int)$p['id']] ?? 0); ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="type" value="capital_withdrawal">
            <input type="number" step="0.01" min="0.01" name="amount" placeholder="টাকা" required>
            <input type="date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
            <input type="text" name="note" placeholder="নোট (ঐচ্ছিক)" maxlength="255">
            <button type="submit" class="pa-btn gold">উত্তোলন সেভ</button>
        </form>
        <?php endif; ?>
    </section>

    <div class="pa-split pa-pane<?php echo $tab === 'stmt' ? ' is-on' : ''; ?>" data-pa-pane="stmt">
        <section class="pa-panel">
            <h2>ইনভয়েস বিস্তারিত</h2>
            <?php if (!$selected): ?>
                <div class="pa-empty">ইনভয়েস বেছে বিস্তারিত দেখুন।</div>
            <?php else:
                $ishares = pa_invoice_shares($selected, $dash['active']);
                ?>
                <div class="sub"><?php echo htmlspecialchars($selected['invoice_no']); ?> · <?php echo htmlspecialchars($selected['customer_name']); ?> · <?php echo htmlspecialchars((string)($selected['category_names'] ?? '')); ?></div>
                <div class="pa-mini">
                    <div class="row"><span>অ্যাক্টিভ খাতের বিক্রি</span><span><?php echo pa_taka($selected['sales_amount']); ?></span></div>
                    <div class="row"><span>পণ্য খরচ</span><span><?php echo pa_taka($selected['cost_amount']); ?></span></div>
                    <div class="row strong"><span>ইনভয়েস লাভ</span><span><?php echo pa_taka($selected['profit_amount']); ?></span></div>
                    <?php foreach ($ishares as $sh): ?>
                        <div class="row"><span><?php echo htmlspecialchars($sh['name']); ?> (<?php echo number_format((float)($sh['pct'] ?? 0), 2); ?>%)</span><span><?php echo pa_taka($sh['amount']); ?></span></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="pa-panel">
            <h2>মাসিক হিসাব</h2>
            <div class="sub"><?php echo htmlspecialchars($bounds['label']); ?></div>
            <div class="pa-mini">
                <div class="row"><span>মোট বিক্রি</span><span><?php echo pa_taka($m['sales']); ?></span></div>
                <div class="row"><span>মোট ইনভয়েস খরচ</span><span><?php echo pa_taka($m['cost']); ?></span></div>
                <div class="row strong"><span>ইনভয়েস লাভ</span><span><?php echo pa_taka($m['invoice_profit']); ?></span></div>
                <div class="row"><span>পার্টনারশিপ খরচ (−)</span><span><?php echo pa_taka($m['expense_total']); ?></span></div>
                <div class="net">নেট বণ্টনযোগ্য লাভ <?php echo pa_taka($m['net_profit']); ?></div>
            </div>
            <?php if (pa_is_admin()): ?>
                <form method="POST" action="actions/partner_accounting.php" class="no-print" style="margin-top:10px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
                    <?php if ($closed): ?>
                        <input type="hidden" name="action" value="reopen_month">
                        <button type="submit" class="pa-btn soft">মাস আবার খুলুন</button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="close_month">
                        <button type="submit" class="pa-btn gold" onclick="return confirm('মাস বন্ধ করলে সাধারণ ইউজার এন্ট্রি বদলাতে পারবেন না।');">মাস বন্ধ করুন</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <section class="pa-panel pa-pane<?php echo $tab === 'bank' ? ' is-on' : ''; ?>" data-pa-pane="bank">
        <h2><span class="pa-nowrap">সব অ্যাকাউন্ট</span></h2>
        <div class="sub">খোলা সব অ্যাকাউন্ট এখানে। ক্যাশ, ব্যাংক, মোবাইল ও অন্যান্য — কেউ বাদ নেই। টাকার হিসাব শুধু দেখায়, বদলায় না।</div>
        <div class="pa-mini">
            <div class="row"><span class="pa-nowrap">ক্যাশ</span><span><?php echo pa_taka($reserve['cash']); ?></span></div>
            <div class="row"><span class="pa-nowrap">ব্যাংক</span><span><?php echo pa_taka($reserve['bank']); ?></span></div>
            <div class="row"><span class="pa-nowrap">মোবাইল</span><span><?php echo pa_taka($reserve['mobile']); ?></span></div>
            <div class="row"><span class="pa-nowrap">অন্যান্য</span><span><?php echo pa_taka($reserve['other']); ?></span></div>
            <div class="net"><span class="pa-nowrap">সব অ্যাকাউন্ট</span> <?php echo pa_taka($reserve['all']); ?></div>
        </div>
        <?php if (!$reserve['rows']): ?>
            <div class="pa-empty">কোনো খোলা অ্যাকাউন্ট পাওয়া যায়নি।</div>
        <?php else: ?>
            <div class="pa-table-wrap">
                <table class="pa-table">
                    <thead><tr><th>অ্যাকাউন্ট</th><th>ধরন</th><th class="num">ব্যালেন্স</th></tr></thead>
                    <tbody>
                        <?php foreach ($reserve['rows'] as $acc): ?>
                            <tr>
                                <td data-label="অ্যাকাউন্ট"><?php echo htmlspecialchars($acc['name']); ?></td>
                                <td class="pa-kind" data-label="ধরন"><span class="pa-nowrap"><?php echo htmlspecialchars(pa_kind_label($acc['kind'])); ?></span></td>
                                <td class="num" data-label="ব্যালেন্স"><?php echo pa_taka($acc['balance']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <h2 style="margin-top:16px;">এই মাসের পেমেন্ট</h2>
        <div class="pa-table-wrap">
            <table class="pa-table">
                <thead><tr><th>তারিখ</th><th>পার্টনার</th><th>মাধ্যম</th><th class="num">টাকা</th><th>নোট</th><th class="no-print"></th></tr></thead>
                <tbody>
                    <?php foreach ($dash['payments'] as $pay):
                        $pn = pa_find_partner($db, (int)$pay['partner_id']);
                        ?>
                        <tr>
                            <td data-label="তারিখ"><?php echo htmlspecialchars(date('d-m-Y', strtotime((string)$pay['payment_date']))); ?></td>
                            <td data-label="পার্টনার"><?php echo htmlspecialchars($pn['name'] ?? ('#' . $pay['partner_id'])); ?></td>
                            <td data-label="মাধ্যম"><?php echo htmlspecialchars($methods[$pay['payment_method']] ?? $pay['payment_method']); ?></td>
                            <td class="num" data-label="টাকা"><?php echo pa_taka($pay['amount']); ?></td>
                            <td data-label="নোট"><?php echo htmlspecialchars((string)($pay['note'] ?? '')); ?></td>
                            <td class="no-print" data-label="">
                                <?php if (!$locked): ?>
                                <form method="POST" action="actions/partner_accounting.php" onsubmit="return confirm('পেমেন্ট মুছবেন?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_payment">
                                    <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int)$pay['id']; ?>">
                                    <button type="submit" class="pa-btn red">মুছুন</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="pa-panel pa-pane<?php echo $tab === 'stmt' ? ' is-on' : ''; ?>" data-pa-pane="stmt">
        <div class="pa-head">
            <div>
                <h2>পার্টনার স্টেটমেন্ট</h2>
                <div class="sub">প্রথম ব্যালেন্স থেকে প্লাস যোগ, মাইনাস বাদ। শেষে মোট এমাউন্ট। দুজনের প্রথম ব্যালেন্স একসাথে।</div>
            </div>
            <form method="GET" class="pa-month-form no-print">
                <input type="hidden" name="month" value="<?php echo htmlspecialchars($ym); ?>">
                <input type="hidden" name="tab" value="stmt">
                <input type="hidden" name="pcat" value="<?php echo htmlspecialchars($pcat); ?>">
                <select name="stmt" onchange="this.form.submit()">
                    <?php foreach ($dash['partners'] as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>"<?php echo $stmt_partner && (int)$stmt_partner['id'] === (int)$p['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php
        $stmt_books = [];
        foreach ($dash['active'] as $stmt_row) {
            $stmt_pid = (int)$stmt_row['partner']['id'];
            $stmt_books[] = $pa_books[$stmt_pid] ?? pa_partner_book($db, $stmt_row['partner'], $ym);
        }
        ?>
        <?php if (!$stmt_books): ?>
            <div class="pa-empty">স্টেটমেন্টের জন্য একজন পার্টনার লাগবে।</div>
        <?php else: ?>
            <div class="pa-openings">
                <?php foreach ($stmt_books as $book): ?>
                    <div class="pa-opening">
                        <span><a class="pa-name" href="<?php echo htmlspecialchars(pa_partner_edit_href($ym, (int)$book['partner_id'])); ?>"><?php echo htmlspecialchars($book['name']); ?></a> · প্রথম ব্যালেন্স</span>
                        <b><?php echo pa_taka($book['opening']); ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php foreach ($stmt_books as $book):
                $lines = array_reverse($book['lines']);
                ?>
                <h3 id="pa-book-<?php echo (int)$book['partner_id']; ?>" style="margin-top:14px;"><a class="pa-name" href="<?php echo htmlspecialchars(pa_partner_edit_href($ym, (int)$book['partner_id'])); ?>"><?php echo htmlspecialchars($book['name']); ?></a> · <?php echo number_format((float)$book['share_pct'], 2); ?>%</h3>
                <div class="pa-table-wrap">
                    <table class="pa-table pa-book">
                        <thead>
                            <tr><th>তারিখ</th><th>বিবরণ</th><th class="num">প্লাস</th><th class="num">মাইনাস</th><th class="num">চলতি</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!$lines): ?>
                                <tr><td colspan="5" data-label="">এই মাসে প্লাস বা মাইনাস নেই।</td></tr>
                            <?php else: foreach ($lines as $line):
                                $when = (string)$line['date'];
                                $show = preg_match('/^\d{4}-\d{2}-\d{2}$/', $when) ? date('d-m-Y', strtotime($when)) : '';
                                ?>
                                <tr>
                                    <td data-label="তারিখ"><?php echo htmlspecialchars($show); ?></td>
                                    <td data-label="বিবরণ"><?php echo htmlspecialchars($line['description']); ?></td>
                                    <td class="num pa-plus" data-label="প্লাস"><?php echo pa_book_signed((float)$line['plus'], '+'); ?></td>
                                    <td class="num pa-minus" data-label="মাইনাস"><?php echo pa_book_signed((float)$line['minus'], '-'); ?></td>
                                    <td class="num" data-label="চলতি"><?php echo pa_taka($line['running']); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" data-label="মোট">মোট</td>
                                <td class="num pa-plus" data-label="প্লাস"><?php echo pa_book_signed((float)$book['plus'], '+'); ?></td>
                                <td class="num pa-minus" data-label="মাইনাস"><?php echo pa_book_signed((float)$book['minus'], '-'); ?></td>
                                <td class="num" data-label="মোট এমাউন্ট"><?php echo pa_taka($book['total']); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="pa-mini"><div class="net">মোট এমাউন্ট <?php echo pa_taka($book['total']); ?></div></div>
            <?php endforeach; ?>
            <div class="pa-actions no-print" style="margin-top:10px;">
                <button type="button" class="pa-btn primary" onclick="window.print()"><?php echo ame_l('প্রিন্ট / পিডিএফ','Print / PDF'); ?></button>
                <a class="pa-btn soft" href="partner_accounting_statement.php?month=<?php echo htmlspecialchars($ym); ?>">পুরো স্টেটমেন্ট</a>
            </div>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
    var tabs = document.querySelectorAll('[data-pa-tab]');
    var panes = document.querySelectorAll('[data-pa-pane]');
    function show(name) {
        tabs.forEach(function (tab) {
            tab.classList.toggle('is-on', tab.getAttribute('data-pa-tab') === name);
        });
        panes.forEach(function (pane) {
            pane.classList.toggle('is-on', pane.getAttribute('data-pa-pane') === name);
        });
    }
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () { show(tab.getAttribute('data-pa-tab')); });
    });
    document.querySelectorAll('[data-pa-acct]').forEach(function (box) {
        var btn = box.querySelector('.pa-acct-btn');
        var list = box.querySelector('.pa-acct-list');
        var hidden = box.querySelector('input[name="payment_method"]');
        var label = box.querySelector('.pa-acct-txt');
        if (!btn || !list || !hidden || !label) return;
        btn.addEventListener('click', function () {
            var open = list.hasAttribute('hidden');
            document.querySelectorAll('.pa-acct-list').forEach(function (el) { el.setAttribute('hidden', ''); });
            if (open) list.removeAttribute('hidden');
        });
        list.addEventListener('click', function (ev) {
            var item = ev.target.closest('[data-kind]');
            if (!item) return;
            hidden.value = item.getAttribute('data-kind') || 'cash';
            label.textContent = item.textContent || '';
            list.setAttribute('hidden', '');
        });
    });
    document.addEventListener('click', function (ev) {
        if (ev.target.closest && ev.target.closest('[data-pa-acct]')) return;
        document.querySelectorAll('.pa-acct-list').forEach(function (el) { el.setAttribute('hidden', ''); });
    });
})();
(function () {
    var sheet = document.getElementById('paPartnerSheet');
    var body = document.getElementById('paPartnerSheetBody');
    var title = document.getElementById('paPartnerSheetTitle');
    var edit = document.getElementById('paPartnerSheetEdit');
    if (!sheet || !body) return;
    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function closeSheet() {
        if (typeof sheet.close === 'function') sheet.close();
        else sheet.removeAttribute('open');
    }
    function openFrom(btn) {
        var name = btn.getAttribute('data-name') || '';
        var phone = btn.getAttribute('data-phone') || '';
        var addr = btn.getAttribute('data-address') || '';
        var share = btn.getAttribute('data-share') || '';
        title.textContent = name;
        var row = function (label, val) {
            return '<div class="pa-profile-row"><dt>' + esc(label) + '</dt><dd>' + val + '</dd></div>';
        };
        var html = '<dl class="pa-profile-dl">';
        if (phone) html += row('ফোন', esc(phone));
        if (addr) html += row('ঠিকানা', esc(addr));
        html += row('শেয়ার', esc(share) + '%');
        html += row('মূলধন', esc(btn.getAttribute('data-capital')));
        html += row('ব্যালেন্স', '<strong>' + esc(btn.getAttribute('data-balance')) + '</strong>');
        html += row('এই মাসে উত্তোলন', esc(btn.getAttribute('data-draw')));
        html += row('স্ট্যাটাস', esc(btn.getAttribute('data-status')));
        html += '</dl>';
        body.innerHTML = html;
        if (edit) edit.setAttribute('href', btn.getAttribute('data-edit') || '#');
        if (typeof sheet.showModal === 'function') sheet.showModal();
        else sheet.setAttribute('open', 'open');
    }
    document.querySelectorAll('[data-pa-profile]').forEach(function (btn) {
        btn.addEventListener('click', function () { openFrom(btn); });
    });
    document.querySelectorAll('[data-pa-sheet-close]').forEach(function (btn) {
        btn.addEventListener('click', closeSheet);
    });
    sheet.addEventListener('click', function (ev) {
        if (ev.target === sheet) closeSheet();
    });
})();
</script>
<?php include '../../partials/footer.php'; ?>
<style id="pa-acct-618">
html body #main-content .pa-page .pa-nowrap,
html body #main-content .pa-page .pa-acct-txt,
html body #main-content .pa-page .pa-acct-list button,
html body #main-content .pa-page td.pa-kind,
html body #main-content .pa-page .pa-card .lbl,
html body #main-content .pa-page .pa-mini .row > span:first-child {
    white-space: nowrap !important;
    word-break: keep-all !important;
    overflow-wrap: normal !important;
}
html body #main-content .pa-page .pa-head-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
html body #main-content .pa-page .pa-subhead {
    margin: 1.25rem 0 0.35rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f4c81;
}
html body #main-content .pa-page .pa-subhead-note {
    margin: 0 0 0.75rem;
}
html body:not(.ame-sales-pro) #main-content#main-content .pa-page button.pa-acct-btn,
html body:not(.ame-sales-pro) #main-content#main-content .pa-page .pa-acct-list button {
    background: #ffffff !important;
    color: #0b5cad !important;
    -webkit-text-fill-color: #0b5cad !important;
    border: 1.5px solid #bfdbfe !important;
    width: 100% !important;
    text-align: left !important;
}
html body:not(.ame-sales-pro) #main-content#main-content .pa-page .pa-acct-btn .pa-acct-txt,
html body:not(.ame-sales-pro) #main-content#main-content .pa-page .pa-acct-btn .pa-acct-caret {
    color: #0b5cad !important;
    -webkit-text-fill-color: #0b5cad !important;
    background: transparent !important;
}
</style>
