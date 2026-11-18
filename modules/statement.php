<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php';
}
require_once '../../includes/config.php';
require_once '../../includes/purchase_functions.php';
require_once '../../includes/supplier_settlement.php';
require_once '../../includes/supplier_due_ui.php';
require_once '../../includes/supplier_module_guard.php';
ame_supplier_module_view_boot();

ensure_purchase_schema($db);

$sup_id = (int)($_GET['id'] ?? 0);
$ame_body_class = 'ame-supplier-statement';
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = '';
}
if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = '';
}

$ame_stmt_taka = static function (float $n): string {
    $sign = $n < -0.009 ? '−' : '';
    return $sign . '৳' . number_format(abs($n), 2);
};

if ($sup_id <= 0) {
    if (!function_exists('ame_supplier_live_sql')) {
        require_once '../../includes/supplier_functions.php';
    }
    ame_supplier_schema_boot($db);
    $live = ame_supplier_live_sql($db);
    $pick_stmt = $db->query("SELECT id, name, phone, total_due FROM suppliers WHERE {$live} ORDER BY name ASC LIMIT 500");
    $pick_list = $pick_stmt ? $pick_stmt->fetch_all(MYSQLI_ASSOC) : [];
    include '../../partials/header.php';
    ame_supplier_due_page_boot(true, true);
    ?>
    <link rel="stylesheet" href="/assets/css/supplier-statement.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="sst-pick">
        <div class="card">
            <p class="sst-kicker">সাপ্লায়ার</p>
            <h3>হিসাব বিবরণী</h3>
            <p class="sst-lead">নাম বা ফোন দিয়ে খুঁজুন। সাবেক + ক্রয় − পরিশোধ = মোট। তালিকা ও মেমোর সংখ্যা একই থাকে।</p>
            <input type="text" id="searchBox" placeholder="নাম বা ফোন নম্বর দিয়ে খুঁজুন..." autocomplete="off">
            <div id="resultsList" class="sst-pick-list"></div>
            <div id="emptyMsg" class="sst-empty" style="display:none;">কোনো সাপ্লায়ার পাওয়া যায়নি।</div>
            <a href="list.php" class="sst-btn back">তালিকায় ফিরুন</a>
        </div>
    </div>
    <script>
        const AME_ALL_SUPPLIERS = <?php echo json_encode(array_map(static function ($p) {
            return [
                'id' => (int)$p['id'],
                'name' => $p['name'],
                'phone' => $p['phone'] ?? '',
                'balance' => (float)$p['total_due'],
            ];
        }, $pick_list), JSON_UNESCAPED_UNICODE); ?>;
        const searchBox = document.getElementById('searchBox');
        const resultsList = document.getElementById('resultsList');
        const emptyMsg = document.getElementById('emptyMsg');
        function renderRows(list) {
            resultsList.innerHTML = '';
            emptyMsg.style.display = list.length === 0 ? 'block' : 'none';
            list.slice(0, 100).forEach(function (c) {
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'row';
                const who = document.createElement('span');
                const name = document.createElement('b');
                name.textContent = c.name || '';
                const phone = document.createElement('small');
                phone.textContent = c.phone || 'ফোন নেই';
                who.appendChild(name);
                who.appendChild(phone);
                const bal = document.createElement('span');
                const amount = Number(c.balance) || 0;
                const pres = window.ameSupDuePresentation ? window.ameSupDuePresentation(amount) : null;
                if (pres) {
                    bal.className = pres.pickClass;
                    bal.textContent = pres.text;
                } else {
                    bal.className = 'bal owe';
                    bal.textContent = '৳' + Math.abs(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
                row.appendChild(who);
                row.appendChild(bal);
                row.addEventListener('click', function () { window.location.href = 'statement.php?id=' + c.id; });
                resultsList.appendChild(row);
            });
        }
        searchBox.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            if (q === '') { renderRows(AME_ALL_SUPPLIERS); return; }
            renderRows(AME_ALL_SUPPLIERS.filter(function (c) {
                return c.name.toLowerCase().includes(q) || (c.phone || '').includes(q);
            }));
        });
        renderRows(AME_ALL_SUPPLIERS);
    </script>
    <?php
    include '../../partials/footer.php';
    exit();
}

$sup_stmt = $db->prepare('SELECT * FROM suppliers WHERE id = ?');
$sup_stmt->bind_param('i', $sup_id);
$sup_stmt->execute();
$sup_info = $sup_stmt->get_result()->fetch_assoc();
$sup_stmt->close();

if (!$sup_info) {
    die('❌ সাপ্লায়ার খুঁজে পাওয়া যায়নি। <a href="list.php">ফিরে যান</a>');
}

$book = ame_supplier_account_book($db, $sup_id);
$closing = (float)$book['closing'];
$supplier_total = round($closing, 2);
$sheet_all = function_exists('ame_supplier_statement_sheet')
    ? ame_supplier_statement_sheet($book)
    : array_reverse($book['rows']);

$rows = [];
foreach ($sheet_all as $row) {
    $day = substr((string)($row['txn_at'] ?? $row['created_at'] ?? ''), 0, 10);
    if ($from !== '' && $day !== '' && $day < $from) {
        continue;
    }
    if ($to !== '' && $day !== '' && $day > $to) {
        continue;
    }
    $rows[] = $row;
}

$opening_show = $rows !== []
    ? (float)$rows[0]['previous']
    : (float)$book['opening'];
$closing_show = $rows !== [] ? (float)$rows[count($rows) - 1]['total'] : $closing;
$purch_show = 0.0;
$pay_show = 0.0;
$ret_show = 0.0;
foreach ($rows as $row) {
    $row_kind = (string)($row['kind'] ?? '');
    $row_plus = (float)($row['plus'] ?? $row['debit'] ?? 0);
    $row_minus = (float)($row['minus'] ?? $row['credit'] ?? 0);
    if ($row_kind === 'return') {
        $ret_show += $row_minus;
        $purch_show += $row_plus;
    } else {
        $purch_show += $row_plus;
        $pay_show += $row_minus;
    }
}
$purch_show = round($purch_show, 2);
$pay_show = round($pay_show, 2);
$ret_show = round($ret_show, 2);

$shop_name = function_exists('shop_name') ? shop_name() : 'Aponshop';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$acct_no = 'SUP-' . str_pad((string)$sup_id, 6, '0', STR_PAD_LEFT);
$period_label = 'শুরু থেকে আজ';
if ($from !== '' && $to !== '') {
    $period_label = date('d-m-Y', strtotime($from)) . ' — ' . date('d-m-Y', strtotime($to));
} elseif ($from !== '') {
    $period_label = date('d-m-Y', strtotime($from)) . ' থেকে';
} elseif ($to !== '') {
    $period_label = date('d-m-Y', strtotime($to)) . ' পর্যন্ত';
} elseif ($rows) {
    $first_day = substr((string)($rows[0]['txn_at'] ?? ''), 0, 10);
    $last_day = substr((string)($rows[count($rows) - 1]['txn_at'] ?? ''), 0, 10);
    if ($first_day !== '') {
        $period_label = date('d-m-Y', strtotime($first_day)) . ' — ' . date('d-m-Y', strtotime($last_day ?: 'now'));
    }
}

$rows = array_values(array_reverse($rows));

$sst_error = (string) ($_SESSION['spay_error'] ?? '');
$sst_ok = (string) ($_SESSION['spay_ok'] ?? '');
unset($_SESSION['spay_error'], $_SESSION['spay_ok']);
$dup_groups = [];
foreach ($rows as $row) {
    if ((string) ($row['kind'] ?? '') !== 'payment') {
        continue;
    }
    $amt = round((float) ($row['minus'] ?? $row['credit'] ?? 0), 2);
    $day = substr((string) ($row['txn_at'] ?? $row['created_at'] ?? ''), 0, 10);
    if ($amt <= 0.009 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        continue;
    }
    $key = $day . '|' . number_format($amt, 2, '.', '');
    if (!isset($dup_groups[$key])) {
        $dup_groups[$key] = ['day' => $day, 'amount' => $amt, 'n' => 0];
    }
    $dup_groups[$key]['n']++;
}
$dup_groups = array_values(array_filter($dup_groups, static function (array $g): bool {
    return (int) $g['n'] >= 2;
}));

$kind_label = [
    'purchase' => 'ক্রয়',
    'payment' => 'পরিশোধ',
    'opening' => 'প্রারম্ভিক',
    'return' => 'ফেরত',
    'adjustment' => 'সমন্বয়',
    'reversal' => 'বাতিল',
];

$stmt_meta = ame_supplier_due_statement_meta($supplier_total);
$close_class = $stmt_meta['class'];
$supplier_total_word = $stmt_meta['word'];
$stmt_hero_class = $stmt_meta['hero_class'];
$period_differs = abs($supplier_total - $closing_show) > 0.009;

$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'supplier-statement-' . $acct_no,
    'title' => $shop_name . ' · সাপ্লায়ার স্টেটমেন্ট · ' . (string)($sup_info['name'] ?? ''),
    'text' => implode("\n", array_filter([
        $shop_name,
        'সাপ্লায়ার স্টেটমেন্ট',
        (string)($sup_info['name'] ?? ''),
        'অ্যাকাউন্ট: ' . $acct_no,
        'সময়কাল: ' . $period_label,
        'সাবেক: ' . $ame_stmt_taka($opening_show),
        'ক্রয়: ' . $ame_stmt_taka($purch_show),
        'পরিশোধ: ' . $ame_stmt_taka($pay_show),
        'ফেরত: ' . $ame_stmt_taka($ret_show),
        'সাপ্লায়ারের মোট টাকা: ' . $ame_stmt_taka($supplier_total),
    ])),
    'shop' => $shop_name,
];

include '../../partials/header.php';
ame_supplier_due_page_boot(true, true);
?>
<link rel="stylesheet" href="/assets/css/supplier-statement.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8'); ?>">
<div class="sst-page">
    <div class="sst-toolbar no-print">
        <a href="list.php" class="sst-btn back">তালিকায় ফিরুন</a>
        <div class="sst-toolbar-actions">
            <a href="profile.php?id=<?php echo $sup_id; ?>" class="sst-btn profile">প্রোফাইল</a>
            <a href="pay.php?id=<?php echo $sup_id; ?>" class="sst-btn pay">পেমেন্ট</a>
            <a href="ledger.php?id=<?php echo $sup_id; ?>" class="sst-btn ledger">লেজার</a>
        </div>
    </div>

    <form class="sst-filter no-print" method="GET">
        <input type="hidden" name="id" value="<?php echo $sup_id; ?>">
        <div>
            <label for="from">শুরুর তারিখ</label>
            <input type="date" id="from" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div>
            <label for="to">শেষ তারিখ</label>
            <input type="date" id="to" name="to" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <button type="submit">ফিল্টার</button>
        <a class="reset" href="statement.php?id=<?php echo $sup_id; ?>">সব সময়কাল</a>
    </form>

    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>

    <div id="ame-slip" class="sst-paper">
        <div class="sst-bank-bar"></div>
        <div class="<?php echo htmlspecialchars($stmt_hero_class ?? ('sst-total-hero ' . $close_class), ENT_QUOTES, 'UTF-8'); ?>">
            <div>
                <div class="lbl">সাপ্লায়ারের মোট টাকা</div>
                <div class="sub"><?php echo htmlspecialchars($supplier_total_word, ENT_QUOTES, 'UTF-8'); ?> · তালিকার বকেয়ার সাথে একই<?php echo $period_differs ? ' · তারিখ ফিল্টার এই অঙ্ক কাটে না' : ''; ?></div>
            </div>
            <div class="v"><?php echo $ame_stmt_taka($supplier_total); ?></div>
        </div>
        <div class="sst-head">
            <div class="sst-brand">
                <div><?php echo function_exists('shop_print_logo_tag') ? shop_print_logo_tag(52) : ''; ?></div>
                <div>
                    <h1><?php echo htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <?php if ($shop_addr !== ''): ?><p><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    <?php if ($shop_ph !== ''): ?><p>ফোন: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                </div>
            </div>
            <div class="sst-doc">
                <div class="kicker">Account statement</div>
                <h2>হিসাব বিবরণী</h2>
                <table>
                    <tr><td>স্টেটমেন্ট নং</td><td><?php echo htmlspecialchars($acct_no . '-' . date('Ymd'), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                    <tr><td>ইস্যুর তারিখ</td><td><?php echo date('d-m-Y h:i A'); ?></td></tr>
                    <tr><td>সময়কাল</td><td><?php echo htmlspecialchars($period_label, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                    <tr><td>মুদ্রা</td><td>BDT (৳)</td></tr>
                </table>
            </div>
        </div>

        <div class="sst-parties">
            <div class="sst-box">
                <div class="lbl">অ্যাকাউন্টধারী / Account holder</div>
                <h3><?php echo htmlspecialchars((string)($sup_info['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                <p>
                    অ্যাকাউন্ট: <span class="sst-acct"><?php echo htmlspecialchars($acct_no, ENT_QUOTES, 'UTF-8'); ?></span><br>
                    ফোন: <?php echo htmlspecialchars((string)($sup_info['phone'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($sup_info['address'])): ?><br><?php echo htmlspecialchars((string)$sup_info['address'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                </p>
            </div>
            <div class="sst-box">
                <div class="lbl">সারসংক্ষেপ / Summary</div>
                <p><?php echo count($rows); ?>টি লেনদেন · <?php echo htmlspecialchars($period_label, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="sst-hint">সর্বশেষ লেনদেন উপরে। তারিখ ফিল্টার শুধু এই তালিকা ছাঁটে।</p>
            </div>
        </div>

        <div class="sst-kpis">
            <div class="sst-kpi">
                <div class="lbl">সাবেক ব্যালেন্স</div>
                <div class="v"><?php echo $ame_stmt_taka($opening_show); ?></div>
            </div>
            <div class="sst-kpi debit">
                <div class="lbl">ক্রয় (+)</div>
                <div class="v"><?php echo $ame_stmt_taka($purch_show); ?></div>
            </div>
            <div class="sst-kpi credit">
                <div class="lbl">পরিশোধ (−)</div>
                <div class="v"><?php echo $ame_stmt_taka($pay_show); ?></div>
            </div>
            <div class="sst-kpi credit">
                <div class="lbl">ফেরত (−)</div>
                <div class="v"><?php echo $ame_stmt_taka($ret_show); ?></div>
            </div>
            <div class="sst-kpi <?php echo $close_class; ?>">
                <div class="lbl">মোট টাকা</div>
                <div class="v"><?php echo $ame_stmt_taka($supplier_total); ?></div>
            </div>
        </div>
        <p class="sst-formula">সাবেক <?php echo $ame_stmt_taka($opening_show); ?> + ক্রয় <?php echo $ame_stmt_taka($purch_show); ?> − পরিশোধ <?php echo $ame_stmt_taka($pay_show); ?> − ফেরত <?php echo $ame_stmt_taka($ret_show); ?> = <?php echo $period_differs ? 'এই সময়কাল' : 'মোট'; ?> <b><?php echo abs($closing_show) < 0.009 ? '= ' . $ame_stmt_taka(0) : $ame_stmt_taka($closing_show); ?></b><?php if ($period_differs): ?> · সাপ্লায়ারের মোট টাকা <b><?php echo $ame_stmt_taka($supplier_total); ?></b><?php endif; ?></p>
        <?php if ($sst_error !== ''): ?>
        <div class="sst-dup-msg is-err no-print"><?php echo htmlspecialchars($sst_error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($sst_ok !== ''): ?>
        <div class="sst-dup-msg is-ok no-print"><?php echo htmlspecialchars($sst_ok, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php foreach ($dup_groups as $gi => $g):
            $dup_when = date('d-m-Y', strtotime($g['day']));
        ?>
        <form class="sst-dup-void no-print" id="<?php echo $gi === 0 ? 'sstDupVoid' : 'sstDupVoid' . (int) $gi; ?>" method="post" action="settlement_action.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="reverse_day">
            <input type="hidden" name="supplier_id" value="<?php echo (int) $sup_id; ?>">
            <input type="hidden" name="pay_day" value="<?php echo htmlspecialchars($g['day'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="pay_amount" value="<?php echo htmlspecialchars(number_format((float) $g['amount'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
            <p class="sst-dup-title">একই দিনে একই পরিমাণ <?php echo (int) $g['n']; ?>বার</p>
            <p class="sst-dup-copy"><?php echo htmlspecialchars($dup_when, ENT_QUOTES, 'UTF-8'); ?> তারিখে ৳<?php echo number_format((float) $g['amount'], 2); ?> পরিশোধ <?php echo (int) $g['n']; ?>বার হয়েছে। সবগুলো বাতিল করলে টাকা অ্যাকাউন্টে ফিরবে, বকেয়া বেড়ে যাবে।</p>
            <input type="hidden" name="reason" value="একই দিনে একই পরিমাণ একাধিকবার কেটেছে">
            <?php
            if (function_exists('ame_pay_sec_box')) {
                echo ame_pay_sec_box(
                    'supplier_day_reverse',
                    (int) $sup_id,
                    'এই পাঁচবারের মতো ভুল পেমেন্ট একসাথে বাতিল করতে কোড লিখুন।',
                    'Type the code to reverse every same-amount payment from this day.'
                );
            }
            ?>
            <button class="sst-dup-go" type="submit">৳<?php echo number_format((float) $g['amount'], 2); ?> × <?php echo (int) $g['n']; ?> বাতিল করুন</button>
        </form>
        <?php endforeach; ?>

        <?php
        $return_rows = array_values(array_filter($rows, static function ($row): bool {
            return (string)($row['kind'] ?? '') === 'return';
        }));
        if ($return_rows):
        ?>
        <div class="sst-return-report">
            <div class="sst-return-title">ক্রয় ফেরত</div>
            <?php foreach ($return_rows as $ret_row):
                $ret_prev = (float)($ret_row['previous'] ?? 0);
                $ret_amt = (float)($ret_row['minus'] ?? $ret_row['credit'] ?? 0);
                $ret_total = (float)($ret_row['total'] ?? $ret_row['balance'] ?? ($ret_prev - $ret_amt));
                $ret_pct = $ret_prev > 0.009 ? round(($ret_amt / $ret_prev) * 100, 2) : 0.0;
                $ret_id = (int)($ret_row['return_id'] ?? 0);
                $ret_href = $ret_id > 0 ? '/modules/purchase/returns/view.php?id=' . $ret_id : '';
                $ret_equal = abs($ret_total) < 0.009;
            ?>
            <div class="sst-return-line">
                <?php if ($ret_href !== ''): ?><a href="<?php echo htmlspecialchars($ret_href, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                <?php echo htmlspecialchars((string)($ret_row['note'] ?? 'ক্রয় ফেরত'), ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($ret_href !== ''): ?></a><?php endif; ?>
                <div>সাবেক <?php echo $ame_stmt_taka($ret_prev); ?> − ফেরত <?php echo $ame_stmt_taka($ret_amt); ?> = মোট <?php echo $ret_equal ? '= ' . $ame_stmt_taka(0) : $ame_stmt_taka($ret_total); ?><?php echo $ret_pct > 0 ? ' · ' . rtrim(rtrim(number_format($ret_pct, 2), '0'), '.') . '%' : ''; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="sst-table-wrap">
            <table class="sst-table sst-sheet">
                <thead>
                    <tr>
                        <th>মেমো</th>
                        <th>তারিখ</th>
                        <th class="r">সাবেক</th>
                        <th class="r">ক্রয় (+)</th>
                        <th class="r">পরিশোধ (−)</th>
                        <th class="r">মোট</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                    <tr><td colspan="6" class="sst-empty">এই সময়কালে কোনো লেনদেন নেই।</td></tr>
                    <?php else: foreach ($rows as $idx => $row):
                        $kind = (string)($row['kind'] ?? '');
                        $memo = trim((string)($row['memo_no'] ?? $row['ref'] ?? ''));
                        $ref_no = trim((string)($row['ref_no'] ?? ''));
                        $plus = (float)($row['plus'] ?? $row['debit'] ?? 0);
                        $minus = (float)($row['minus'] ?? $row['credit'] ?? 0);
                        $prev = (float)($row['previous'] ?? 0);
                        $total = (float)($row['total'] ?? $row['balance'] ?? 0);
                        $when = (string)($row['txn_at'] ?? $row['created_at'] ?? '');
                        $ts = strtotime($when);
                        $memo_ok = $memo !== '' && $memo !== 'N/A';
                        $pay_id = (int)($row['settlement_id'] ?? 0);
                        $ledger_id = (int)($row['ledger_id'] ?? 0);
                        $label = $memo_ok ? $memo : ($ref_no !== '' ? $ref_no : ($kind_label[$kind] ?? 'লেনদেন'));
                        $print_href = '';
                        $view_href = '';
                        $share_href = '';
                        if ($kind === 'payment' && $pay_id > 0) {
                            $view_href = '/modules/suppliers/receipt.php?id=' . $pay_id;
                            $print_href = $view_href . '&print=1';
                            $share_href = $view_href . '&share=1';
                        } elseif ($kind === 'return' && (int)($row['return_id'] ?? 0) > 0) {
                            $view_href = '/modules/purchase/returns/view.php?id=' . (int)$row['return_id'];
                            $print_href = $view_href;
                            $share_href = $view_href;
                        } elseif ($memo_ok && ($kind === 'purchase' || $kind === 'payment')) {
                            $view_href = '/modules/purchase/memo_digital.php?memo=' . rawurlencode($memo);
                            $print_href = $view_href . '&do=print';
                            $share_href = $view_href . '&do=share';
                        }
                        $kind_bn = $kind_label[$kind] ?? '';
                    ?>
                    <tr class="sst-row<?php echo $view_href !== '' ? ' sst-row-open' : ''; ?><?php echo $idx === 0 ? ' sst-newest' : ''; ?>"<?php if ($view_href !== ''): ?> data-href="<?php echo htmlspecialchars($view_href, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                        <td class="memo">
                            <?php if ($kind_bn !== ''): ?>
                                <span class="sst-kind <?php echo htmlspecialchars($kind, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($kind_bn, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <?php if ($view_href !== ''): ?>
                                <a href="<?php echo htmlspecialchars($view_href, ENT_QUOTES, 'UTF-8'); ?>">#<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
                                <span class="sst-memo-acts no-print">
                                    <a class="sst-memo-share" href="<?php echo htmlspecialchars($share_href, ENT_QUOTES, 'UTF-8'); ?>">শেয়ার</a>
                                    <a class="sst-memo-print" href="<?php echo htmlspecialchars($print_href, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">প্রিন্ট</a>
                                    <?php if ($kind === 'payment' && $pay_id > 0): ?>
                                    <a class="sst-memo-void" href="<?php echo htmlspecialchars($view_href . '#spReverse', ENT_QUOTES, 'UTF-8'); ?>">বাতিল</a>
                                    <?php elseif ($kind === 'payment' && $dup_groups): ?>
                                    <a class="sst-memo-void" href="#sstDupVoid">বাতিল</a>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($label !== '' ? ($memo_ok || $ref_no !== '' ? '#' . $label : $label) : '—', ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($kind === 'payment' && ($pay_id > 0 || $ledger_id > 0 || $dup_groups)): ?>
                                <span class="sst-memo-acts no-print">
                                    <?php if ($pay_id > 0): ?>
                                    <a class="sst-memo-void" href="/modules/suppliers/receipt.php?id=<?php echo $pay_id; ?>#spReverse">বাতিল</a>
                                    <?php else: ?>
                                    <a class="sst-memo-void" href="#sstDupVoid">বাতিল</a>
                                    <?php endif; ?>
                                </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td><?php if ($idx === 0): ?><span class="sst-new-chip">নতুন</span><?php endif; ?><?php echo $ts ? date('d-m-Y', $ts) : '—'; ?></td>
                        <td class="r"><?php echo number_format($prev, 2); ?></td>
                        <td class="r sst-plus"><?php echo ame_statement_signed_amount($plus, '+'); ?></td>
                        <td class="r sst-minus"><?php echo ame_statement_signed_amount($minus, '-'); ?></td>
                        <td class="r sst-total"><?php echo abs($total) < 0.009 ? '= ' . number_format(0, 2) : number_format($total, 2); ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    <tr class="sst-foot-row">
                        <td colspan="3">সাপ্লায়ারের মোট টাকা</td>
                        <td class="r"><?php echo ame_statement_signed_amount($purch_show, '+'); ?></td>
                        <td class="r"><?php echo ame_statement_signed_amount($pay_show, '-'); ?></td>
                        <td class="r"><?php echo number_format($supplier_total, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="sst-legal">
            <div>
                এই বিবরণী কম্পিউটারে তৈরি। সাবেক + ক্রয় − পরিশোধ = মোট। নতুন লেনদেন উপরে।<br>
                সাপ্লায়ারের মোট টাকা = তালিকার বকেয়া। প্রতিটি সারির সাবেক = নিচের সারির মোট।
            </div>
            <div>
                প্রস্তুত: <?php echo htmlspecialchars((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'APONSHOP'), ENT_QUOTES, 'UTF-8'); ?><br>
                <strong><?php echo htmlspecialchars($supplier_total_word, ENT_QUOTES, 'UTF-8'); ?>: <?php echo $ame_stmt_taka($supplier_total); ?></strong>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('tr.sst-row-open[data-href]').forEach(function (tr) {
    tr.addEventListener('click', function (e) {
        if (e.target.closest('a, button')) {
            return;
        }
        var href = tr.getAttribute('data-href');
        if (href) {
            window.location.href = href;
        }
    });
});
</script>
<?php include '../../partials/footer.php'; ?>
