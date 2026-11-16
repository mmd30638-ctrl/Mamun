<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_permission($db, 'm-acc');
if (function_exists('ensure_accounts_schema')) {
    ensure_accounts_schema($db);
}

function bs_taka(float $amount): string
{
    return '৳' . number_format($amount, 2);
}

function bs_date(string $value, string $fallback): string
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback;
}

$accounts = function_exists('ame_active_accounts') ? ame_active_accounts($db) : [];
$accountId = (int)($_GET['account_id'] ?? 0);
$known = false;
foreach ($accounts as $row) {
    if ((int)$row['id'] === $accountId) {
        $known = true;
        break;
    }
}
if (!$known) {
    $accountId = (int)($accounts[0]['id'] ?? 0);
}
$account = null;
foreach ($accounts as $row) {
    if ((int)$row['id'] === $accountId) {
        $account = $row;
        break;
    }
}

$mode = (string)($_GET['mode'] ?? 'month');
if (!in_array($mode, ['month', 'year', 'custom'], true)) {
    $mode = 'month';
}
$month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? (string)$_GET['month'] : date('Y-m');
$year = preg_match('/^\d{4}$/', (string)($_GET['year'] ?? '')) ? (string)$_GET['year'] : date('Y');
if ($mode === 'year') {
    $from = $year . '-01-01';
    $to = $year . '-12-31';
} elseif ($mode === 'custom') {
    $from = bs_date((string)($_GET['from'] ?? ''), date('Y-m-01'));
    $to = bs_date((string)($_GET['to'] ?? ''), date('Y-m-d'));
    if ($to < $from) {
        $to = $from;
    }
} else {
    $from = $month . '-01';
    $to = date('Y-m-t', strtotime($from . ' 00:00:00'));
}

$opening = 0.0;
$rows = [];
$credit = 0.0;
$debit = 0.0;
$advance = 0.0;
$months = [];
if ($accountId > 0) {
    $openStmt = $db->prepare(
        "SELECT COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END), 0) AS bal
         FROM account_ledger WHERE account_id = ? AND DATE(created_at) < ?"
    );
    if ($openStmt) {
        $openStmt->bind_param('is', $accountId, $from);
        $openStmt->execute();
        $opening = (float)($openStmt->get_result()->fetch_assoc()['bal'] ?? 0);
        $openStmt->close();
    }
    $lineStmt = $db->prepare(
        "SELECT id, created_at, type, amount, note, ref_type
         FROM account_ledger
         WHERE account_id = ? AND DATE(created_at) BETWEEN ? AND ?
         ORDER BY id ASC"
    );
    if ($lineStmt) {
        $lineStmt->bind_param('iss', $accountId, $from, $to);
        $lineStmt->execute();
        $res = $lineStmt->get_result();
        $run = $opening;
        while ($line = $res->fetch_assoc()) {
            $amount = (float)$line['amount'];
            $isCredit = (string)$line['type'] === 'credit';
            $run += $isCredit ? $amount : -$amount;
            if ($isCredit) {
                $credit += $amount;
            } else {
                $debit += $amount;
            }
            $ref = strtolower((string)($line['ref_type'] ?? ''));
            $note = (string)($line['note'] ?? '');
            $isAdvance = str_contains($ref, 'advance') || str_contains($note, 'অগ্রিম') || stripos($note, 'advance') !== false;
            if ($isAdvance && $isCredit) {
                $advance += $amount;
            }
            $line['running'] = $run;
            $line['advance'] = $isAdvance;
            $rows[] = $line;
        }
        $lineStmt->close();
    }
    if ($mode === 'year') {
        $monthStmt = $db->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym,
                    COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END), 0) AS credit_sum,
                    COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END), 0) AS debit_sum
             FROM account_ledger
             WHERE account_id = ? AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY ym ASC"
        );
        if ($monthStmt) {
            $monthStmt->bind_param('iss', $accountId, $from, $to);
            $monthStmt->execute();
            $got = [];
            $res = $monthStmt->get_result();
            while ($mrow = $res->fetch_assoc()) {
                $got[(string)$mrow['ym']] = $mrow;
            }
            $monthStmt->close();
            $walk = $opening;
            for ($i = 1; $i <= 12; $i++) {
                $key = sprintf('%s-%02d', $year, $i);
                $in = (float)($got[$key]['credit_sum'] ?? 0);
                $out = (float)($got[$key]['debit_sum'] ?? 0);
                $walk += $in - $out;
                $months[] = ['ym' => $key, 'in' => $in, 'out' => $out, 'close' => $walk];
            }
        }
    }
}
$closing = $opening + $credit - $debit;

$partnerCards = [];
$partnerMonth = substr($from, 0, 7);
if (is_file(__DIR__ . '/../../includes/partner_accounting_v2.php')) {
    require_once __DIR__ . '/../../includes/partner_accounting_v2.php';
    if (function_exists('pav2_ensure_schema') && function_exists('pav2_report')) {
        try {
            pav2_ensure_schema($db);
            $preport = pav2_report($db, $partnerMonth);
            foreach (($preport['partners'] ?? []) as $prow) {
                $partnerCards[] = $prow;
            }
        } catch (Throwable $e) {
            error_log('bank statement partner strip: ' . $e->getMessage());
        }
    }
}

$modules = [
    ['ব্যাংক স্টেটমেন্ট', '/modules/partners/bank_statement.php', '#003f8f', 'M4 8h16v10H4z M4 12h16'],
    ['পার্টনার হিসাব', '/modules/accounts/partner_accounting.php', '#04933a', 'M8 10a2.4 2.4 0 1 0 0-4.8A2.4 2.4 0 0 0 8 10z M16 10a2.4 2.4 0 1 0 0-4.8A2.4 2.4 0 0 0 16 10z M4 18c.6-2.2 2.2-3.4 4-3.4s3.4 1.2 4 3.4 M12 18c.5-1.8 1.8-2.8 3.6-2.8S18.7 16.2 19.2 18'],
    ['পণ্যের লাভ', '/modules/partners/accounting/index.php#pv2-product', '#7c3aed', 'M5 18V10 M10 18V6 M15 18v-7 M20 18V8'],
    ['পার্টনার খরচ', '/modules/partners/accounting/index.php#pv2-expense', '#c2410c', 'M6 5h12v14H6z M9 9h6 M9 13h4'],
    ['অগ্রিম জমা', '/modules/partners/bank_statement.php?account_id=' . $accountId . '&mode=' . rawurlencode($mode) . '&month=' . rawurlencode($month) . '&year=' . rawurlencode($year) . '#bs-advance', '#0e7490', 'M12 5v14 M7 9l5-4 5 4'],
    ['মাসিক রিপোর্ট', '/modules/partners/bank_statement.php?mode=month&account_id=' . $accountId . '&month=' . rawurlencode($month), '#1d4ed8', 'M5 6h14v13H5z M5 10h14 M9 4v4 M15 4v4'],
    ['বাৎসরিক রিপোর্ট', '/modules/partners/bank_statement.php?mode=year&account_id=' . $accountId . '&year=' . rawurlencode($year), '#047857', 'M5 19V8 M10 19V5 M15 19v-6 M20 19V11'],
    ['মূলধন', '/modules/partners/capital/index.php', '#b45309', 'M12 4v16 M8 8h8 M8 16h8'],
    ['লাভ-ক্ষতি', '/modules/reports/profit_loss.php', '#be123c', 'M4 16l5-6 4 3 7-8'],
];

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/partners/bank-statement.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8'); ?>">
<main class="bs">
    <header class="bs-hero">
        <p class="bs-kicker">হিসাব নিকাশ</p>
        <h1>ব্যাংকিং স্টেটমেন্ট</h1>
        <p><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?> — সাবেক ব্যালেন্স, জমা, উত্তোলন, অগ্রিম এবং মাসিক ও বাৎসরিক খতিয়ান। শেষ এন্ট্রি নিচে।</p>
    </header>

    <nav class="bs-mods" aria-label="হিসাব নিকাশ">
        <?php foreach ($modules as $mod): ?>
        <a class="bs-mod" href="<?php echo htmlspecialchars($mod[1], ENT_QUOTES, 'UTF-8'); ?>">
            <span class="bs-ico" style="color:<?php echo htmlspecialchars($mod[2], ENT_QUOTES, 'UTF-8'); ?>;border-color:<?php echo htmlspecialchars($mod[2], ENT_QUOTES, 'UTF-8'); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="<?php echo htmlspecialchars($mod[3], ENT_QUOTES, 'UTF-8'); ?>"/></svg>
            </span>
            <span><?php echo htmlspecialchars($mod[0], ENT_QUOTES, 'UTF-8'); ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$account): ?>
    <section class="bs-empty">খোলা হিসাবের অ্যাকাউন্ট নেই। আগে একটি ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্ট খুলুন।</section>
    <?php else: ?>
    <form class="bs-filter" method="get">
        <label>অ্যাকাউন্ট
            <select name="account_id">
                <?php foreach ($accounts as $row): ?>
                <option value="<?php echo (int)$row['id']; ?>"<?php echo (int)$row['id'] === $accountId ? ' selected' : ''; ?>><?php echo htmlspecialchars((string)$row['account_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>ধরন
            <select name="mode" id="bsMode">
                <option value="month"<?php echo $mode === 'month' ? ' selected' : ''; ?>>মাসিক</option>
                <option value="year"<?php echo $mode === 'year' ? ' selected' : ''; ?>>বাৎসরিক</option>
                <option value="custom"<?php echo $mode === 'custom' ? ' selected' : ''; ?>>নিজের তারিখ</option>
            </select>
        </label>
        <label>মাস <input type="month" name="month" value="<?php echo htmlspecialchars($month, ENT_QUOTES, 'UTF-8'); ?>"></label>
        <label>বছর <input type="number" name="year" min="2000" max="2100" value="<?php echo htmlspecialchars($year, ENT_QUOTES, 'UTF-8'); ?>"></label>
        <label>থেকে <input type="date" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>"></label>
        <label>পর্যন্ত <input type="date" name="to" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>"></label>
        <button type="submit">স্টেটমেন্ট দেখুন</button>
        <button type="button" class="bs-ghost" onclick="window.print()">ছাপুন</button>
    </form>

    <section class="bs-cards">
        <article><small>সাবেক ব্যালেন্স</small><strong><?php echo bs_taka($opening); ?></strong></article>
        <article><small>জমা</small><strong class="in"><?php echo bs_taka($credit); ?></strong></article>
        <article><small>উত্তোলন</small><strong class="out"><?php echo bs_taka($debit); ?></strong></article>
        <article id="bs-advance"><small>অগ্রিম জমা</small><strong><?php echo bs_taka($advance); ?></strong></article>
        <article><small>এখনকার ব্যালেন্স</small><strong><?php echo bs_taka($closing); ?></strong></article>
    </section>

    <p class="bs-range"><?php echo htmlspecialchars($account['account_name'] . ' · ' . date('d-m-Y', strtotime($from)) . ' থেকে ' . date('d-m-Y', strtotime($to)), ENT_QUOTES, 'UTF-8'); ?></p>

    <div class="bs-scroll">
        <table class="bs-table">
            <thead>
                <tr>
                    <th>ক্রম</th>
                    <th>তারিখ</th>
                    <th>বিবরণ</th>
                    <th class="num">জমা</th>
                    <th class="num">উত্তোলন</th>
                    <th class="num">ব্যালেন্স</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bs-open">
                    <td></td>
                    <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($from)), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>সাবেক ব্যালেন্স</td>
                    <td class="num"></td>
                    <td class="num"></td>
                    <td class="num"><?php echo bs_taka($opening); ?></td>
                </tr>
                <?php if (!$rows): ?>
                <tr><td colspan="6" class="bs-none">এই সময়ে কোনো লেনদেন নেই।</td></tr>
                <?php else: foreach ($rows as $i => $line): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo htmlspecialchars(date('d-m-Y', strtotime((string)$line['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php echo htmlspecialchars((string)($line['note'] !== '' ? $line['note'] : $line['ref_type']), ENT_QUOTES, 'UTF-8'); ?>
                        <?php if (!empty($line['advance'])): ?><em>অগ্রিম</em><?php endif; ?>
                    </td>
                    <td class="num in"><?php echo (string)$line['type'] === 'credit' ? bs_taka((float)$line['amount']) : ''; ?></td>
                    <td class="num out"><?php echo (string)$line['type'] === 'debit' ? bs_taka((float)$line['amount']) : ''; ?></td>
                    <td class="num"><?php echo bs_taka((float)$line['running']); ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">মোট · এখনকার ব্যালেন্স</td>
                    <td class="num"><?php echo bs_taka($credit); ?></td>
                    <td class="num"><?php echo bs_taka($debit); ?></td>
                    <td class="num"><?php echo bs_taka($closing); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if ($months): ?>
    <h2>বারো মাসের খতিয়ান</h2>
    <div class="bs-scroll">
        <table class="bs-table">
            <thead><tr><th>মাস</th><th class="num">জমা</th><th class="num">উত্তোলন</th><th class="num">মাস শেষে</th></tr></thead>
            <tbody>
                <?php foreach ($months as $mrow): ?>
                <tr>
                    <td><a href="?account_id=<?php echo $accountId; ?>&mode=month&month=<?php echo rawurlencode($mrow['ym']); ?>"><?php echo htmlspecialchars($mrow['ym'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                    <td class="num in"><?php echo bs_taka($mrow['in']); ?></td>
                    <td class="num out"><?php echo bs_taka($mrow['out']); ?></td>
                    <td class="num"><?php echo bs_taka($mrow['close']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($partnerCards): ?>
    <h2><?php echo htmlspecialchars($partnerMonth, ENT_QUOTES, 'UTF-8'); ?> · পার্টনারের অংশ</h2>
    <div class="bs-partners">
        <?php foreach ($partnerCards as $prow): ?>
        <article>
            <strong><?php echo htmlspecialchars((string)($prow['name'] ?? 'পার্টনার'), ENT_QUOTES, 'UTF-8'); ?></strong>
            <span>পণ্য লাভ <?php echo bs_taka((float)($prow['gross_share'] ?? 0)); ?></span>
            <span>খরচের অংশ <?php echo bs_taka((float)($prow['expense_share'] ?? 0)); ?></span>
            <span>পাওনা <?php echo bs_taka((float)($prow['receivable'] ?? 0)); ?></span>
        </article>
        <?php endforeach; ?>
    </div>
    <p class="bs-note">পণ্যের লাভ ভাগ ও পার্টনার খরচের পুরো খাতা <a href="/modules/partners/accounting/index.php?month=<?php echo rawurlencode($partnerMonth); ?>">পার্টনার লাভ</a> পাতায়।</p>
    <?php endif; ?>
    <?php endif; ?>
</main>
<?php include '../../partials/footer.php'; ?>
