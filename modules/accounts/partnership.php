<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
require_once '../../includes/config.php';
require_once '../../includes/partnership_functions.php';
admin_only();

$summary = [
    'product_profit' => 0.0,
    'ledger_expenses' => 0.0,
    'unit_expenses' => 0.0,
    'total_expenses' => 0.0,
    'net_profit' => 0.0,
    'partners' => [],
    'shares_equal' => false,
];
$categories = [];
$expense_cats = [];
$adjustments = [];
$openings = [];
$withdrawals = [];
$profit_source = [
    'month_categories' => [],
    'month_lines' => [],
    'month_line_total' => 0,
    'month_product_profit' => 0.0,
    'month_shown_profit' => 0.0,
    'month_expenses' => [],
    'month_expense_total' => 0.0,
    'month_adjustments' => [],
    'month_adjustment_total' => 0.0,
    'all_categories' => [],
    'all_product_profit' => 0.0,
    'all_expenses' => [],
    'all_expense_total' => 0.0,
    'all_adjustment_total' => 0.0,
];
$slip_month = trim((string)($_GET['month'] ?? date('Y-m')));
$slip = [
    'ym' => date('Y-m'),
    'from' => date('Y-m-01'),
    'to' => date('Y-m-t'),
    'label' => date('Y-m'),
    'product_profit' => 0.0,
    'ledger_expenses' => 0.0,
    'unit_expenses' => 0.0,
    'month_profit' => 0.0,
    'sabek_total' => 0.0,
    'grand_total' => 0.0,
    'partners' => [],
];

try {
    ensure_partnership_schema($db);
    $summary = get_partnership_unit_summary($db);
    $slip = get_partnership_month_slip($db, $slip_month);
    $profit_source = get_partnership_profit_source($db, $slip['from'], $slip['to']);
    $categories = get_partnership_category_breakdown($db);
    $expense_cats = get_partnership_expense_breakdown($db);
    $adjustments = get_partnership_adjustments($db, 20);
    $openings = get_partnership_openings($db, 20);
    $withdrawals = get_partnership_recent_withdrawals($db, 12);
} catch (Throwable $e) {
    error_log('partnership page load failed: ' . $e->getMessage());
}

$partners = $summary['partners'] ?? [];
if (!isset($slip['ym'])) {
    $slip['ym'] = date('Y-m');
}
if (!isset($slip['partners']) || !is_array($slip['partners'])) {
    $slip['partners'] = [];
}

$flash_ok = [
    'expense' => 'ইউনিট খরচ সেভ হয়েছে।',
    'withdraw' => 'উত্তোলন সেভ হয়েছে।',
    'opening' => 'সাবেক ব্যালেন্স যোগ হয়েছে।',
];
$flash_err = [
    'expense' => 'খরচের পরিমাণ ও কারণ দুটোই দিন।',
    'withdraw' => 'সঠিক পার্টনার ও উত্তোলনের পরিমাণ দিন।',
    'opening' => 'পার্টনার বেছে সাবেক অঙ্ক দিন।',
    'save' => 'সেভ হয়নি। আবার চেষ্টা করুন।',
];
$saved_key = (string)($_GET['saved'] ?? '');
$err_key = (string)($_GET['err'] ?? '');

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/partnership-units.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">

<div class="pu-page">
    <div class="pu-hero">
        <div class="pu-hero-kicker">হিসাব নিকাশ · ইউনিট সিস্টেম</div>
        <h1>পার্টনারশিপ লাভ-বণ্টন</h1>
        <p>দুজন পার্টনার একটি খাতের পণ্য থেকে সমান ৫০-৫০ লাভ ভাগ করেন। ব্যবসার খরচ ও সাবেক ব্যালেন্স এখানেই যোগ হয়।</p>
        <div class="pu-formula">প্রাপ্য = সাবেক + (নেট লাভ × ৫০%) − উত্তোলন</div>
    </div>

    <?php if (isset($flash_ok[$saved_key])): ?>
        <div class="pu-flash ok"><?php echo htmlspecialchars($flash_ok[$saved_key]); ?></div>
    <?php elseif (isset($flash_err[$err_key])): ?>
        <div class="pu-flash bad"><?php echo htmlspecialchars($flash_err[$err_key]); ?></div>
    <?php endif; ?>

    <?php if (count($partners) < 2): ?>
        <div class="pu-warn">দুজন পার্টনার লাগবে। পার্টনার তালিকা থেকে নাম যোগ করুন, তারপর এখানে ফিরে আসুন।</div>
    <?php elseif (empty($summary['shares_equal'])): ?>
        <div class="pu-warn">শেয়ার এখন সমান ৫০-৫০ নয়। পার্টনার মডিউল থেকে দুজনকে ৫০% করে রাখুন — হিসাব তখনও শেয়ার অনুযায়ী চলবে।</div>
    <?php endif; ?>

    <div class="pu-grid">
        <section class="pu-unit">
            <div class="pu-unit-head">
                <div class="pu-badge">১</div>
                <div>
                    <h2>লাভের খাত</h2>
                    <p>সব ক্যাটাগরির ৫০% লাভ নিষ্ক্রিয়। যে খাত দরকার শুধু সেটা টিক দিয়ে অ্যাক্টিভ করুন। আগের বিক্রয় মুছে যায় না।</p>
                </div>
            </div>
            <div class="pu-unit-body">
                <?php if (!$categories): ?>
                    <div class="pu-empty">কোনো পণ্য খাত নেই। আগে পণ্যের খাত বানান — টিক দিলে সেই খাতের লাভ এখানে আসবে।</div>
                <?php else: ?>
                    <div class="pu-list">
                        <?php foreach ($categories as $c):
                            $on = !empty($c['is_partnership_shared']);
                            ?>
                            <label class="pu-row<?php echo $on ? ' on' : ''; ?>">
                                <input type="checkbox"
                                       onchange="puToggle('toggle_partnership_category.php', <?php echo (int)$c['id']; ?>, this)"
                                    <?php echo $on ? 'checked' : ''; ?>>
                                <span class="name"><?php echo htmlspecialchars($c['name']); ?></span>
                                <span class="pu-chip<?php echo $on ? '' : ' off'; ?>"><?php echo $on ? 'অ্যাক্টিভ' : 'নিষ্ক্রিয়'; ?></span>
                                <span class="amt"><?php echo partnership_taka($c['profit']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="pu-unit">
            <div class="pu-unit-head">
                <div class="pu-badge">২</div>
                <div>
                    <h2>ব্যবসার খরচ</h2>
                    <p>শেয়ার করা খরচের খাত টিক দিন। আলাদা ইউনিট খরচও নিচে যোগ করুন।</p>
                </div>
            </div>
            <div class="pu-unit-body">
                <?php if (!$expense_cats): ?>
                    <div class="pu-empty">খরচের খাত নেই। হিসাব নিকাশ থেকে খরচ খাত বানান, অথবা নিচে ইউনিট খরচ যোগ করুন।</div>
                <?php else: ?>
                    <div class="pu-list">
                        <?php foreach ($expense_cats as $ec):
                            $on = !empty($ec['is_partnership_shared']);
                            ?>
                            <label class="pu-row<?php echo $on ? ' on' : ''; ?>">
                                <input type="checkbox"
                                       onchange="puToggle('toggle_partnership_expense.php', <?php echo (int)$ec['id']; ?>, this)"
                                    <?php echo $on ? 'checked' : ''; ?>>
                                <span class="name"><?php echo htmlspecialchars($ec['name']); ?></span>
                                <?php if ($on): ?><span class="pu-chip">বিয়োগ</span><?php endif; ?>
                                <span class="amt"><?php echo partnership_taka($ec['total']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="actions/add_partnership_adjustment.php" method="POST" class="pu-form acc-print-hide">
                    <?php echo csrf_field(); ?>
                    <input type="number" step="0.01" min="0.01" name="amount" placeholder="ইউনিট খরচ (৳)" required>
                    <input type="text" name="reason" placeholder="কারণ — যেমন: দোকান ভাড়া, বিদ্যুৎ" required>
                    <input type="date" name="adjustment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    <button type="submit">ইউনিট খরচ যোগ করুন</button>
                </form>

                <?php if ($adjustments): ?>
                    <div style="margin-top:12px;">
                        <?php foreach ($adjustments as $a): ?>
                            <div class="pu-mini">
                                <span><?php echo htmlspecialchars($a['reason']); ?><small><?php echo date('d M Y', strtotime($a['adjustment_date'])); ?></small></span>
                                <span class="neg">−<?php echo partnership_taka($a['amount']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="pu-unit pu-unit-full">
        <div class="pu-unit-head">
            <div class="pu-badge">৩</div>
            <div>
                <h2>সাবেক ব্যালেন্স</h2>
                <p>আগের বকেয়া বা জের এখানে যোগ করুন। এটা প্রাপ্যের সাথে যোগ হবে।</p>
            </div>
        </div>
        <div class="pu-unit-body pu-split">
            <div>
                <?php if (count($partners) < 1): ?>
                    <div class="pu-empty">পার্টনার না থাকলে সাবেক ব্যালেন্স যোগ করা যাবে না।</div>
                <?php else: ?>
                    <form action="actions/save_opening_balance.php" method="POST" class="pu-form acc-print-hide">
                        <?php echo csrf_field(); ?>
                        <select name="partner_id" required>
                            <?php foreach ($partners as $p): ?>
                                <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?> — এখন সাবেক <?php echo partnership_taka($p['opening_balance']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" step="0.01" name="amount" placeholder="সাবেক অঙ্ক (৳)" required>
                        <input type="date" name="as_of_date" value="<?php echo date('Y-m-d'); ?>" required>
                        <input type="text" name="note" placeholder="নোট — যেমন: আগের হিসাবের জের">
                        <button type="submit">সাবেক ব্যালেন্স যোগ করুন</button>
                    </form>
                <?php endif; ?>
            </div>
            <div>
                <?php if (!$openings): ?>
                    <div class="pu-empty">এখনো কোনো সাবেক এন্ট্রি নেই। যোগ করলে এখানে দেখাবে।</div>
                <?php else: ?>
                    <?php foreach ($openings as $o): ?>
                        <div class="pu-mini">
                            <span><?php echo htmlspecialchars($o['partner_name']); ?><?php if ($o['note'] !== '' && $o['note'] !== null): ?> — <?php echo htmlspecialchars($o['note']); ?><?php endif; ?><small><?php echo date('d M Y', strtotime($o['as_of_date'])); ?></small></span>
                            <span class="<?php echo ((float)$o['amount'] >= 0) ? 'pos' : 'neg'; ?>"><?php echo partnership_taka($o['amount']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="pu-unit pu-unit-full">
        <div class="pu-unit-head">
            <div class="pu-badge">৪</div>
            <div>
                <h2>হিসাবপত্র</h2>
                <p>উপরের স্লিপ শুধু এই মাসের। নিচের বড় অঙ্ক সব সময়ের। লাভ কোন খাত ও ইনভয়েস থেকে এল, সেটা স্লিপের নিচে খোলা আছে।</p>
            </div>
        </div>
        <div class="pu-unit-body">
            <form method="GET" class="pu-month-form no-print">
                <label>মাস</label>
                <input type="month" name="month" value="<?php echo htmlspecialchars($slip['ym']); ?>">
                <button type="submit">এই মাস দেখুন</button>
            </form>

            <div id="puShareSlip" class="pu-slip">
                <div class="pu-slip-top">
                    <div class="pu-slip-shop"><?php echo htmlspecialchars(shop_name()); ?></div>
                    <div class="pu-slip-title">পার্টনারশিপ হিসাবপত্র</div>
                    <div class="pu-slip-month"><?php echo htmlspecialchars($slip['label']); ?></div>
                </div>
                <div class="pu-slip-body">
                    <?php foreach ($slip['partners'] as $sp): ?>
                        <div class="pu-slip-row">
                            <span>সাবেক — <?php echo htmlspecialchars($sp['name']); ?></span>
                            <span><?php echo partnership_taka($sp['opening_balance']); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="pu-slip-row">
                        <span>এই মাসের লাভ</span>
                        <span><?php echo partnership_taka($slip['month_profit']); ?></span>
                    </div>
                    <div class="pu-slip-row total">
                        <span>মোট</span>
                        <span><?php echo partnership_taka($slip['grand_total']); ?></span>
                    </div>
                    <div class="pu-slip-note">সাবেক + এই মাসের লাভ = মোট। লাভ সমান ৫০-৫০।</div>
                    <div class="pu-slip-each">
                        <?php foreach ($slip['partners'] as $sp): ?>
                            <div class="pu-slip-person">
                                <div class="who"><?php echo htmlspecialchars($sp['name']); ?></div>
                                <div class="tiny">সাবেক <?php echo partnership_taka($sp['opening_balance']); ?> + লাভ <?php echo partnership_taka($sp['month_share']); ?></div>
                                <div class="now"><?php echo partnership_taka($sp['now_amount']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="pu-slip-foot"><?php echo date('d/m/Y h:i A'); ?> · <?php echo htmlspecialchars(shop_name()); ?></div>
            </div>

            <div class="pu-slip-actions no-print">
                <button type="button" class="pu-slip-btn print" onclick="puPrintSlip()">প্রিন্ট / PDF</button>
                <button type="button" class="pu-slip-btn share" id="puShareBtn" onclick="puShareSlip()">PDF শেয়ার</button>
            </div>
            <div class="pu-slip-status no-print" id="puSlipStatus" hidden></div>

            <?php
            $src_month_net = (float)$slip['product_profit'] - (float)$slip['ledger_expenses'] - (float)$slip['unit_expenses'];
            $src_lines_hidden = max(0, (int)$profit_source['month_line_total'] - count($profit_source['month_lines']));
            ?>
            <section class="pu-source" id="puProfitSource">
                <h3>লাভ কোথা থেকে এল</h3>
                <p class="pu-source-lead">স্লিপের <b>এই মাসের লাভ <?php echo partnership_taka($slip['month_profit']); ?></b> শুধু <?php echo htmlspecialchars($slip['label']); ?>। নিচের <b>পণ্যের লাভ <?php echo partnership_taka($summary['product_profit']); ?></b> সব সময়ের — মাস বদলালে এই বড় অঙ্ক বদলায় না। দুটোই শুধু যে ক্যাটাগরিতে এখন টিক আছে।</p>

                <div class="pu-source-block month">
                    <div class="pu-source-h">এই মাস · <?php echo htmlspecialchars($slip['label']); ?></div>
                    <p class="pu-source-how">পণ্যের লাভ = (বিক্রয় দাম − ক্রয় দাম) × পরিমাণ। শুধু টিক-দেওয়া খাতের সম্পন্ন বিক্রয়, <?php echo htmlspecialchars(date('d/m/Y', strtotime($slip['from']))); ?> থেকে <?php echo htmlspecialchars(date('d/m/Y', strtotime($slip['to']))); ?>।</p>
                    <div class="pu-source-formula">
                        পণ্যের লাভ <?php echo partnership_taka($slip['product_profit']); ?>
                        − খাত-খরচ <?php echo partnership_taka($slip['ledger_expenses']); ?>
                        − ইউনিট খরচ <?php echo partnership_taka($slip['unit_expenses']); ?>
                        = এই মাসের লাভ <?php echo partnership_taka($src_month_net); ?>
                    </div>
                    <?php if (!$profit_source['month_categories']): ?>
                        <div class="pu-empty">কোনো ক্যাটাগরিতে টিক নেই, তাই এই মাসের পণ্য লাভে কিছু যোগ হয়নি।</div>
                    <?php else: ?>
                        <?php foreach ($profit_source['month_categories'] as $srcCat): ?>
                            <div class="pu-source-row">
                                <span><?php echo htmlspecialchars((string)$srcCat['name']); ?><small><?php echo (int)$srcCat['line_count']; ?>টি বিক্রয় লাইন</small></span>
                                <span class="<?php echo ((float)$srcCat['profit'] < 0) ? 'neg' : 'pos'; ?>"><?php echo partnership_taka($srcCat['profit']); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <p class="pu-source-sum">খাতগুলো যোগ = পণ্যের লাভ <?php echo partnership_taka($profit_source['month_product_profit']); ?>। তারপর ৫০% করে প্রতিজন পান।</p>
                    <?php endif; ?>

                    <?php if ($profit_source['month_expenses'] || (float)$profit_source['month_expense_total'] != 0.0): ?>
                        <div class="pu-source-sub">এই মাসের খাত-খরচ</div>
                        <?php foreach ($profit_source['month_expenses'] as $srcExp): ?>
                            <div class="pu-source-row">
                                <span><?php echo htmlspecialchars((string)$srcExp['name']); ?><?php if (!empty($srcExp['note'])): ?> — <?php echo htmlspecialchars((string)$srcExp['note']); ?><?php endif; ?><small><?php echo !empty($srcExp['entry_date']) ? date('d/m/Y', strtotime((string)$srcExp['entry_date'])) : ''; ?></small></span>
                                <span class="neg">−<?php echo partnership_taka($srcExp['amount']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($profit_source['month_adjustments'] || (float)$profit_source['month_adjustment_total'] != 0.0): ?>
                        <div class="pu-source-sub">এই মাসের ইউনিট খরচ</div>
                        <?php foreach ($profit_source['month_adjustments'] as $srcAdj): ?>
                            <div class="pu-source-row">
                                <span><?php echo htmlspecialchars((string)$srcAdj['reason']); ?><small><?php echo date('d/m/Y', strtotime((string)$srcAdj['adjustment_date'])); ?></small></span>
                                <span class="neg">−<?php echo partnership_taka($srcAdj['amount']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <details class="pu-source-more" <?php echo $profit_source['month_lines'] ? 'open' : ''; ?>>
                        <summary>এই মাসের বিক্রয় লাইন<?php if ((int)$profit_source['month_line_total'] > 0): ?> (<?php echo (int)$profit_source['month_line_total']; ?>)<?php endif; ?></summary>
                        <?php if (!$profit_source['month_lines']): ?>
                            <div class="pu-empty">এই মাসে টিক-দেওয়া খাতের কোনো সম্পন্ন বিক্রয় নেই।</div>
                        <?php else: ?>
                            <?php foreach ($profit_source['month_lines'] as $srcLine):
                                $srcQty = rtrim(rtrim(number_format((float)$srcLine['qty'], 3, '.', ''), '0'), '.');
                                ?>
                                <div class="pu-inv">
                                    <div class="pu-inv-top">
                                        <span><?php echo !empty($srcLine['sale_date']) ? date('d/m/Y', strtotime((string)$srcLine['sale_date'])) : ''; ?> · <?php echo htmlspecialchars((string)$srcLine['invoice_no']); ?></span>
                                        <span class="<?php echo ((float)$srcLine['profit'] < 0) ? 'neg' : 'pos'; ?>"><?php echo partnership_taka($srcLine['profit']); ?></span>
                                    </div>
                                    <div class="pu-inv-sub"><?php echo htmlspecialchars((string)$srcLine['category_name']); ?> · <?php echo htmlspecialchars((string)$srcLine['product_name']); ?></div>
                                    <div class="pu-inv-math"><?php echo htmlspecialchars($srcQty); ?> × (<?php echo partnership_taka($srcLine['sale_price']); ?> − <?php echo partnership_taka($srcLine['purchase_price']); ?>)</div>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($src_lines_hidden > 0): ?>
                                <p class="pu-source-sum">উপরে <?php echo count($profit_source['month_lines']); ?>টি লাইন দেখানো হয়েছে, যোগ <?php echo partnership_taka($profit_source['month_shown_profit']); ?>। বাকি <?php echo $src_lines_hidden; ?>টিসহ পুরো পণ্য লাভ <?php echo partnership_taka($profit_source['month_product_profit']); ?>।</p>
                            <?php else: ?>
                                <p class="pu-source-sum">এই <?php echo count($profit_source['month_lines']); ?>টি লাইন যোগ = পণ্যের লাভ <?php echo partnership_taka($profit_source['month_shown_profit']); ?>।</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </details>
                </div>

                <div class="pu-source-block all">
                    <div class="pu-source-h">সব সময় · নিচের বড় অঙ্ক</div>
                    <p class="pu-source-how">একই টিক-দেওয়া খাত, শুরু থেকে আজ পর্যন্ত সব বিক্রয়। তারিখ বা মাস এখানে কাটা হয় না।</p>
                    <div class="pu-source-formula">
                        পণ্যের লাভ <?php echo partnership_taka($summary['product_profit']); ?>
                        − খাত-খরচ <?php echo partnership_taka($summary['ledger_expenses']); ?>
                        − ইউনিট খরচ <?php echo partnership_taka($summary['unit_expenses']); ?>
                        = নেট লাভ <?php echo partnership_taka($summary['net_profit']); ?>
                    </div>
                    <?php if (!$profit_source['all_categories']): ?>
                        <div class="pu-empty">টিক-দেওয়া কোনো খাত নেই, তাই সব সময়ের পণ্য লাভ ০।</div>
                    <?php else: ?>
                        <?php foreach ($profit_source['all_categories'] as $srcCat): ?>
                            <div class="pu-source-row">
                                <span><?php echo htmlspecialchars((string)$srcCat['name']); ?><small>সব তারিখ · <?php echo (int)$srcCat['line_count']; ?>টি লাইন</small></span>
                                <span class="<?php echo ((float)$srcCat['profit'] < 0) ? 'neg' : 'pos'; ?>"><?php echo partnership_taka($srcCat['profit']); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <p class="pu-source-sum">খাতগুলো যোগ = পণ্যের লাভ <?php echo partnership_taka($profit_source['all_product_profit']); ?>।</p>
                    <?php endif; ?>
                    <?php foreach ($summary['partners'] as $srcPartner): ?>
                        <div class="pu-source-row">
                            <span><?php echo htmlspecialchars((string)$srcPartner['name']); ?><small>নেট লাভ × <?php echo rtrim(rtrim(number_format((float)$srcPartner['share_percentage'], 2), '0'), '.'); ?>%</small></span>
                            <span class="pos"><?php echo partnership_taka($srcPartner['share_amount']); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($profit_source['all_expenses']): ?>
                        <div class="pu-source-sub">সব সময়ের খাত-খরচ</div>
                        <?php foreach ($profit_source['all_expenses'] as $srcExp): ?>
                            <?php if ((float)$srcExp['amount'] == 0.0) { continue; } ?>
                            <div class="pu-source-row">
                                <span><?php echo htmlspecialchars((string)$srcExp['name']); ?></span>
                                <span class="neg">−<?php echo partnership_taka($srcExp['amount']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <div class="pu-ledger" style="margin-top:16px;">
                <div class="pu-led-row"><span>পণ্যের লাভ (নির্বাচিত খাত · সব সময়)</span><span><?php echo partnership_taka($summary['product_profit']); ?></span></div>
                <div class="pu-led-row"><span>খাত-খরচ (−)</span><span><?php echo partnership_taka($summary['ledger_expenses']); ?></span></div>
                <div class="pu-led-row"><span>ইউনিট খরচ (−)</span><span><?php echo partnership_taka($summary['unit_expenses']); ?></span></div>
                <div class="pu-led-row net"><span>নেট লাভ</span><span><?php echo partnership_taka($summary['net_profit']); ?></span></div>
                <div class="pu-led-row share"><span>প্রতিজনের অংশ (৫০%)</span><span><?php echo partnership_taka($summary['net_profit'] * 0.5); ?></span></div>
            </div>

            <?php if (!$partners): ?>
                <div class="pu-empty" style="margin-top:14px;">পার্টনার না থাকায় হিসাব ভাগ করা যাচ্ছে না।</div>
            <?php else: ?>
                <div class="pu-partners">
                    <?php foreach ($partners as $p):
                        $due = (float)$p['receivable'];
                        $due_class = $due < 0 ? 'neg' : 'pos';
                        ?>
                        <div class="pu-pcard">
                            <h3><?php echo htmlspecialchars($p['name']); ?> · <?php echo rtrim(rtrim(number_format((float)($p['share_percentage'] ?? 0), 2), '0'), '.'); ?>%</h3>
                            <div class="pu-led-row"><span>সাবেক</span><span><?php echo partnership_taka($p['opening_balance']); ?></span></div>
                            <div class="pu-led-row"><span>লাভের অংশ</span><span><?php echo partnership_taka($p['share_amount']); ?></span></div>
                            <div class="pu-led-row"><span>উত্তোলন (−)</span><span><?php echo partnership_taka($p['withdrawn']); ?></span></div>
                            <div class="due <?php echo $due_class; ?>">
                                <span>প্রাপ্য</span>
                                <span><?php echo partnership_taka($due); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="pu-split" style="margin-top:16px;">
                <form action="actions/add_partner_withdrawal.php" method="POST" class="pu-form acc-print-hide">
                    <?php echo csrf_field(); ?>
                    <select name="partner_id" required>
                        <?php foreach ($partners as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" step="0.01" min="0.01" name="amount" placeholder="উত্তোলনের পরিমাণ (৳)" required>
                    <input type="date" name="withdrawal_date" value="<?php echo date('Y-m-d'); ?>" required>
                    <input type="text" name="note" placeholder="নোট (ঐচ্ছিক)">
                    <button type="submit">উত্তোলন সেভ করুন</button>
                </form>
                <div>
                    <?php if (!$withdrawals): ?>
                        <div class="pu-empty">এখনো কোনো উত্তোলন নেই।</div>
                    <?php else: ?>
                        <?php foreach ($withdrawals as $w): ?>
                            <div class="pu-mini">
                                <span><?php echo htmlspecialchars($w['partner_name']); ?><?php if ($w['note'] !== '' && $w['note'] !== null): ?> — <?php echo htmlspecialchars($w['note']); ?><?php endif; ?><small><?php echo date('d M Y', strtotime($w['withdrawal_date'])); ?></small></span>
                                <span class="neg">−<?php echo partnership_taka($w['amount']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <a class="pu-back" href="../../index.php">← ড্যাশবোর্ডে ফিরে যান</a>
</div>

<script src="/assets/js/html2canvas.min.js?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
function puToggle(endpoint, id, el) {
    var token = window.AME_CSRF_TOKEN || '';
    var body = new URLSearchParams();
    body.set('id', String(id));
    body.set('checked', el.checked ? '1' : '0');
    body.set('csrf_token', token);
    fetch('actions/' + endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': token
        },
        body: body.toString()
    }).then(function (res) {
        if (!res.ok) throw new Error('toggle failed');
        location.reload();
    }).catch(function () {
        el.checked = !el.checked;
        alert('সেভ হয়নি। আবার চেষ্টা করুন।');
    });
}

function puSlipStatus(msg) {
    var el = document.getElementById('puSlipStatus');
    if (!el) return;
    el.hidden = !msg;
    el.textContent = msg || '';
}

function puPrintSlip() {
    document.body.classList.add('pu-printing-slip');
    window.print();
    setTimeout(function () { document.body.classList.remove('pu-printing-slip'); }, 400);
}

function puShareSlip() {
    var el = document.getElementById('puShareSlip');
    if (!el) return;
    el.setAttribute('data-ame-capture', 'clone');
    puSlipStatus('PDF তৈরি হচ্ছে...');
    if (typeof window.ameMemoCapture === 'function') {
        window.ameMemoCapture('share', 'partnership-<?php echo htmlspecialchars($slip['ym']); ?>', 'puShareSlip');
        return;
    }
    puSlipStatus('PDF engine লোড হয়নি — আবার চেষ্টা করুন।');
}

function puDownloadSlip(blob, name) {
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    puSlipStatus('ছবি নামানো হয়েছে। এখন শেয়ার করুন।');
}
window.addEventListener('afterprint', function () {
    document.body.classList.remove('pu-printing-slip');
});
</script>
<?php include '../../partials/footer.php'; ?>
