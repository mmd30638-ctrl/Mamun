<?php
/* 10.98.720 — কর্মীর লেজার: তারিখ অনুযায়ী দেখা (দ্রুত বাছাই + কাস্টম তারিখ), আগের জের, সময়ের মোট, CSV। */
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/staff_duty.php';
require_once '../../includes/worker_desk.php';

ame_worker_seed($db);
$asked = (int)($_GET['emp'] ?? 0);
$empId = ame_worker_scope_id($db, $asked);
if (!ame_worker_can_manage()) {
    $empId = ame_worker_self_id($db);
}
$people = ame_worker_can_manage() ? ame_worker_people($db) : [];
if ($empId <= 0 && $people) {
    $empId = (int)$people[0]['id'];
}
$statement = $empId > 0 ? ame_worker_desk_statement($db, $empId) : ['employee' => [], 'lines' => [], 'closing' => 0.0, 'mode_label' => '', 'mode' => ''];
$closing = (float)$statement['closing'];
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

/* 10.98.720 — তারিখ অনুযায়ী লেজার: মূল হিসাব (ব্যালেন্স) বদলায় না, শুধু দেখার সীমা ঠিক হয়; সীমার আগের অংশ «আগের জের» হিসেবে আসে। */
$today = function_exists('ame_staff_duty_today') ? ame_staff_duty_today() : date('Y-m-d');
$validDate = static function (string $value): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
};
$todayDt = new DateTime($today);
$rngKeys = ['today', 'yesterday', 'week', 'month', 'lastmonth', 'all', 'custom'];
$rng = (string)($_GET['rng'] ?? '');
$reqFrom = trim((string)($_GET['from'] ?? ''));
$reqTo = trim((string)($_GET['to'] ?? ''));
if (!in_array($rng, $rngKeys, true)) {
    $rng = ($reqFrom !== '' || $reqTo !== '') ? 'custom' : 'month';
}
$from = '';
$to = '';
if ($rng === 'today') {
    $from = $to = $today;
} elseif ($rng === 'yesterday') {
    $from = $to = (clone $todayDt)->modify('-1 day')->format('Y-m-d');
} elseif ($rng === 'week') {
    $from = (clone $todayDt)->modify('monday this week')->format('Y-m-d');
    $to = $today;
} elseif ($rng === 'month') {
    $from = $todayDt->format('Y-m-01');
    $to = $today;
} elseif ($rng === 'lastmonth') {
    $first = new DateTime($todayDt->format('Y-m-01'));
    $to = (clone $first)->modify('-1 day')->format('Y-m-d');
    $from = (clone $first)->modify('-1 month')->format('Y-m-d');
} elseif ($rng === 'custom') {
    $from = $validDate($reqFrom) ? $reqFrom : '';
    $to = $validDate($reqTo) ? $reqTo : '';
    if ($from !== '' && $to !== '' && $from > $to) {
        [$from, $to] = [$to, $from];
    }
    if ($from === '' && $to === '') {
        $rng = 'all';
    }
}

$allLines = $statement['lines'] ?? [];
$opening = 0.0;
$view = [];
$sumCredit = 0.0;
$sumDebit = 0.0;
$periodClosing = null;
foreach ($allLines as $line) {
    $d = (string)($line['date'] ?? '');
    if ($from !== '' && strcmp($d, $from) < 0) {
        $opening = (float)$line['balance'];
        continue;
    }
    if ($to !== '' && strcmp($d, $to) > 0) {
        continue;
    }
    $view[] = $line;
    $sumCredit += (float)$line['credit'];
    $sumDebit += (float)$line['debit'];
    $periodClosing = (float)$line['balance'];
}
if ($periodClosing === null) {
    $periodClosing = $opening;
}
$sumCredit = round($sumCredit, 2);
$sumDebit = round($sumDebit, 2);
$hasOpening = ($from !== '' && abs($opening) > 0.009);
$fmt = static function (float $value): string {
    return ($value < -0.009 ? '−' : '') . '৳' . number_format(abs($value), 2);
};
$rangeText = $rng === 'all'
    ? 'সব সময়'
    : (($from !== '' ? $from : 'শুরু') . ' থেকে ' . ($to !== '' ? $to : 'আজ পর্যন্ত'));
$empName = (string)($statement['employee']['name'] ?? '');
$baseQuery = static function (array $extra = []) use ($empId, $rng, $from, $to): string {
    $q = ['emp' => $empId];
    if ($rng === 'custom') {
        $q['rng'] = 'custom';
        if ($from !== '') {
            $q['from'] = $from;
        }
        if ($to !== '') {
            $q['to'] = $to;
        }
    } else {
        $q['rng'] = $rng;
    }
    return http_build_query($extra + $q);
};

$export = strtolower(trim((string)($_GET['export'] ?? '')));
if ($empId > 0 && ($export === 'excel' || $export === 'csv')) {
    $file = 'worker-ledger-' . $empId . '-' . ($from !== '' ? $from : 'start') . '-' . ($to !== '' ? $to : $today) . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    $csvSafe = static function ($v): string {
        $v = (string)$v;
        return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
    };
    fputcsv($out, ['তারিখ', 'বিবরণ', 'ক্রেডিট (+)', 'ডেবিট (−)', 'ব্যালেন্স']);
    if ($hasOpening) {
        fputcsv($out, [$from, 'আগের জের', '', '', number_format($opening, 2, '.', '')]);
    }
    foreach ($view as $line) {
        fputcsv($out, [
            $line['date'],
            $csvSafe($line['description']),
            $line['credit'] > 0 ? number_format((float)$line['credit'], 2, '.', '') : '',
            $line['debit'] > 0 ? number_format((float)$line['debit'], 2, '.', '') : '',
            number_format((float)$line['balance'], 2, '.', ''),
        ]);
    }
    fputcsv($out, ['', 'সময়ের মোট / শেষ ব্যালেন্স', number_format($sumCredit, 2, '.', ''), number_format($sumDebit, 2, '.', ''), number_format($periodClosing, 2, '.', '')]);
    fclose($out);
    exit;
}

$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8');
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/worker-desk.css?v=<?php echo $ver; ?>">
<style>
/* 10.98.720 — কর্মীর লেজার: তারিখ অনুযায়ী দেখা, বড় পরিষ্কার লেখা, প্রিন্ট। */
.wd-led .wd-led-hero { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
.wd-led .wd-led-chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 12px; }
html body #main-content .wd-led .wd-led-chips a { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 8px 18px; border-radius: 999px; border: 2px solid #0f766e; background: #fff !important; color: #0f766e !important; -webkit-text-fill-color: #0f766e !important; font-size: 17px; font-weight: 800; text-decoration: none; }
html body #main-content .wd-led .wd-led-chips a.on { background: #0f766e !important; color: #fff !important; -webkit-text-fill-color: #fff !important; }
.wd-led .wd-led-dates { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)) auto; gap: 10px; align-items: end; }
.wd-led .wd-led-dates label { display: flex; flex-direction: column; gap: 4px; font-size: 16px; font-weight: 800; color: #334155; }
.wd-led .wd-led-dates input[type="date"], .wd-led .wd-led-emp select { min-height: 48px; font-size: 17px; font-weight: 700; border: 2px solid #cbd5e1; border-radius: 12px; padding: 8px 12px; background: #fff; color: #0f172a; width: 100%; box-sizing: border-box; }
.wd-led .wd-led-emp { display: flex; flex-direction: column; gap: 4px; font-size: 16px; font-weight: 800; color: #334155; margin: 0 0 12px; }
html body #main-content .wd-led .wd-led-go { min-height: 48px; padding: 8px 22px; border-radius: 12px; border: 0; background: #0f766e !important; color: #fff !important; -webkit-text-fill-color: #fff !important; font-size: 17px; font-weight: 800; cursor: pointer; }
.wd-led .wd-led-tools { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
html body #main-content .wd-led .wd-led-tools a, html body #main-content .wd-led .wd-led-tools button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 8px 16px; border-radius: 12px; border: 2px solid #1d4ed8; background: #fff !important; color: #1d4ed8 !important; -webkit-text-fill-color: #1d4ed8 !important; font-size: 16px; font-weight: 800; text-decoration: none; cursor: pointer; }
.wd-led .wd-led-sum { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin: 0 0 12px; }
.wd-led .wd-led-sum > div { background: #fff; border: 2px solid #cbd5e1; border-radius: 14px; padding: 12px 14px; }
.wd-led .wd-led-sum span { display: block; font-size: 15px; font-weight: 800; color: #475569; }
.wd-led .wd-led-sum b { display: block; margin-top: 4px; font-size: 24px; font-weight: 900; color: #0f172a; word-break: break-word; }
.wd-led .wd-led-sum .cr { background: #ecfdf5; border-color: #86efac; }
.wd-led .wd-led-sum .cr b { color: #166534; }
.wd-led .wd-led-sum .dr { background: #fff7ed; border-color: #fdba74; }
.wd-led .wd-led-sum .dr b { color: #9a3412; }
.wd-led .wd-led-sum .cl { background: #eff6ff; border-color: #93c5fd; }
.wd-led .wd-led-sum .cl b { color: #1d4ed8; }
.wd-led .wd-led-badge { display: inline-block; margin: 0 0 10px; padding: 6px 14px; border-radius: 999px; background: #f1f5f9; font-size: 16px; font-weight: 800; color: #0f172a; }
.wd-led table { font-size: 17px; }
.wd-led th { font-size: 15px; font-weight: 900; color: #334155; background: #f1f5f9; }
.wd-led td { font-weight: 600; }
.wd-led td.num { font-weight: 800; }
.wd-led td.cr { color: #166534; }
.wd-led td.dr { color: #9a3412; }
.wd-led tr.open td, .wd-led tr.total td { background: #f8fafc; font-weight: 900; border-top: 2px solid #94a3b8; }
.wd-led .wd-led-note { margin: 4px 0 0; font-size: 14.5px; color: #64748b; font-weight: 700; }
@media (max-width: 900px) { .wd-led .wd-led-sum { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 560px) {
    .wd-led .wd-led-dates { grid-template-columns: 1fr 1fr; }
    .wd-led .wd-led-dates .wd-led-go { grid-column: 1 / -1; }
    .wd-led .wd-led-sum b { font-size: 21px; }
}
@media (max-width: 760px) {
    html body #main-content .wd-led .wd-table-wrap { overflow: visible; padding: 0; border: 0; background: transparent; }
    html body #main-content .wd-led table, html body #main-content .wd-led table.ame-stack-table { min-width: 0 !important; width: 100% !important; font-size: 17px; }
    html body #main-content .wd-led table.ame-stack-table > tbody > tr > td:empty { display: none !important; }
    html body #main-content .wd-led table.ame-stack-table > tbody > tr > td.num { text-align: right; }
    html body #main-content .wd-led table.ame-stack-table > tbody > tr > td::before { font-size: 15px; color: #475569; text-align: left; }
    html body #main-content .wd-led table.ame-stack-table > tbody > tr.open { background: #f1f5f9 !important; border-color: #94a3b8 !important; }
    html body #main-content .wd-led table.ame-stack-table > tbody > tr.total { background: #eff6ff !important; border: 2px solid #93c5fd !important; }
    html body #main-content .wd-led table.ame-stack-table > tbody > tr > td { border-top: 0 !important; background: transparent !important; }
}
@media print {
    body .master-header-fixed, body .custom-sidebar, body .sidebar, body header, body nav, body .no-print, body .wd-nav, body .wd-filters, body .wd-led-chips, body .wd-led-tools { display: none !important; }
    .wd-led .wd-table-wrap { overflow: visible; border: 0; }
    .wd-led table { min-width: 0; font-size: 13px; }
}
</style>
<main class="wd wd-led">
    <h1>কর্মীর লেজার</h1>
    <p class="wd-lead">আয় ক্রেডিট। অগ্রিম, অতিরিক্ত টাকা ও বেতন উত্তোলন ডেবিট। তারিখ বেছে যেকোনো সময়ের হিসাব দেখুন — ব্যালেন্স সবসময় পুরো হিসাব ধরেই চলে।</p>
    <nav class="wd-nav no-print">
        <a class="on" href="worker_ledger.php?emp=<?php echo (int)$empId; ?>">লেজার</a>
        <a href="worker_history.php?emp=<?php echo (int)$empId; ?>">কাজের হিস্ট্রি</a>
        <?php if (ame_worker_can_manage() && $empId > 0): ?>
        <a href="worker_pay.php?emp=<?php echo (int)$empId; ?>">পেমেন্ট</a>
        <?php endif; ?>
        <?php if (ame_worker_can_manage()): ?>
        <a href="employee_list.php">কর্মী তালিকা</a>
        <?php endif; ?>
    </nav>
    <?php if ($empId <= 0): ?>
    <?php if (ame_worker_can_manage()): ?>
    <form class="wd-card wd-filters no-print" method="get">
        <label>কর্মী
            <select name="emp" onchange="this.form.submit()">
                <?php foreach ($people as $person): ?>
                <option value="<?php echo (int)$person['id']; ?>"><?php echo $h($person['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <?php endif; ?>
    <p class="wd-empty">এই লগইনের সাথে কোনো কর্মী প্রোফাইল নেই। অ্যাডমিন মেনু থেকে ডিউটিতে এন্ট্রি দিলে নিজের লেজার এখানে দেখা যাবে।</p>
    <?php else: ?>
    <form class="wd-card no-print" method="get" id="wdLedForm">
        <?php if (ame_worker_can_manage()): ?>
        <label class="wd-led-emp">কর্মী
            <select name="emp" onchange="this.form.submit()">
                <?php foreach ($people as $person): ?>
                <option value="<?php echo (int)$person['id']; ?>"<?php echo (int)$person['id'] === $empId ? ' selected' : ''; ?>><?php echo $h($person['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php else: ?>
        <input type="hidden" name="emp" value="<?php echo (int)$empId; ?>">
        <?php endif; ?>
        <div class="wd-led-chips">
            <?php
            $chips = ['today' => 'আজ', 'yesterday' => 'গতকাল', 'week' => 'এই সপ্তাহ', 'month' => 'এই মাস', 'lastmonth' => 'গত মাস', 'all' => 'সব সময়'];
            foreach ($chips as $key => $label):
                ?>
            <a class="<?php echo $rng === $key ? 'on' : ''; ?>" href="worker_ledger.php?<?php echo $h(http_build_query(['emp' => $empId, 'rng' => $key])); ?>"><?php echo $h($label); ?></a>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="rng" value="custom">
        <div class="wd-led-dates">
            <label>শুরু তারিখ
                <input type="date" name="from" value="<?php echo $h($from); ?>" max="<?php echo $h($today); ?>">
            </label>
            <label>শেষ তারিখ
                <input type="date" name="to" value="<?php echo $h($to); ?>" max="<?php echo $h($today); ?>">
            </label>
            <button type="submit" class="wd-led-go">দেখুন</button>
        </div>
        <div class="wd-led-tools">
            <a href="worker_ledger.php?<?php echo $h($baseQuery(['export' => 'excel'])); ?>">📊 এক্সেল (CSV)</a>
        </div>
    </form>

    <section class="wd-card">
        <div class="wd-meta">
            <span><?php echo $h($empName); ?></span>
            <span><?php echo $h($statement['mode_label'] ?? ''); ?></span>
            <?php if (!empty($statement['employee']['code'])): ?><span><?php echo $h($statement['employee']['code']); ?></span><?php endif; ?>
        </div>
        <div class="wd-balance<?php echo $closing < -0.009 ? ' neg' : ''; ?>"><?php echo $h($fmt($closing)); ?></div>
        <p class="wd-lead" style="margin:0"><?php echo $closing > 0.009 ? 'বর্তমান ব্যালেন্স — দোকান এই কর্মীর কাছে পাওনা।' : ($closing < -0.009 ? 'বর্তমান ব্যালেন্স — কর্মী অগ্রিম বা অতিরিক্ত নিয়েছেন।' : 'বর্তমান ব্যালেন্স — হিসাব সমান।'); ?></p>
    </section>

    <div class="wd-led-sum">
        <div><span>আগের জের</span><b><?php echo $h($rng === 'all' || $from === '' ? '৳0.00' : $fmt($opening)); ?></b></div>
        <div class="cr"><span>এই সময়ের আয় (+)</span><b>৳<?php echo number_format($sumCredit, 2); ?></b></div>
        <div class="dr"><span>এই সময়ের পেমেন্ট/কর্তন (−)</span><b>৳<?php echo number_format($sumDebit, 2); ?></b></div>
        <div class="cl"><span>সময় শেষে ব্যালেন্স</span><b><?php echo $h($fmt($periodClosing)); ?></b></div>
    </div>
    <span class="wd-led-badge"><?php echo $h($empName); ?> · <?php echo $h($rangeText); ?> · <?php echo count($view); ?> টি এন্ট্রি</span>

    <?php if (!$allLines): ?>
    <p class="wd-empty">এই প্রোফাইলে এখনো কোনো আয় বা পেমেন্ট নেই।</p>
    <?php elseif (!$view && !$hasOpening): ?>
    <p class="wd-empty">এই সময়ে কোনো লেনদেন নেই। <a href="worker_ledger.php?<?php echo $h(http_build_query(['emp' => $empId, 'rng' => 'all'])); ?>">সব সময়ের লেজার দেখুন</a></p>
    <?php else: ?>
    <div class="wd-card wd-table-wrap">
        <table>
            <thead>
                <tr><th>তারিখ</th><th>বিবরণ</th><th class="num">ক্রেডিট (+)</th><th class="num">ডেবিট (−)</th><th class="num">ব্যালেন্স</th></tr>
            </thead>
            <tbody>
                <?php if ($hasOpening): ?>
                <tr class="open">
                    <td><?php echo $h($from); ?></td>
                    <td>আগের জের (সীমার আগের মোট)</td>
                    <td class="num"></td>
                    <td class="num"></td>
                    <td class="num"><?php echo number_format($opening, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php foreach ($view as $line): ?>
                <tr>
                    <td><?php echo $h($line['date']); ?></td>
                    <td><?php echo $h($line['description']); ?></td>
                    <td class="num cr"><?php echo $line['credit'] > 0 ? number_format($line['credit'], 2) : ''; ?></td>
                    <td class="num dr"><?php echo $line['debit'] > 0 ? number_format($line['debit'], 2) : ''; ?></td>
                    <td class="num"><?php echo number_format((float)$line['balance'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total">
                    <td></td>
                    <td>এই সময়ের মোট ও শেষ ব্যালেন্স</td>
                    <td class="num cr"><?php echo number_format($sumCredit, 2); ?></td>
                    <td class="num dr"><?php echo number_format($sumDebit, 2); ?></td>
                    <td class="num"><?php echo number_format($periodClosing, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="wd-led-note">ব্যালেন্স কলাম প্রথম এন্ট্রি থেকে ধারাবাহিক চলে, তাই যেকোনো সময়ের লেজারের ব্যালেন্স পুরো লেজারের সাথে মিলে যায়।</p>
    <?php endif; ?>
    <?php endif; ?>
</main>
<script>
(function () {
    var f = document.getElementById('wdLedForm');
    if (!f) { return; }
    f.addEventListener('submit', function () {
        var a = f.elements['from'], b = f.elements['to'];
        if (a && b && a.value && b.value && a.value > b.value) {
            var t = a.value; a.value = b.value; b.value = t;
        }
    });
})();
</script>
<?php include '../../partials/footer.php'; ?>
