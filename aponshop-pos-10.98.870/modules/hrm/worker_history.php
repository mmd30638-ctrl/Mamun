<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/staff_duty.php';
require_once '../../includes/worker_desk.php';

ame_worker_seed($db);
$empId = ame_worker_scope_id($db, (int)($_GET['emp'] ?? 0));
if (!ame_worker_can_manage()) {
    $empId = ame_worker_self_id($db);
}
$today = function_exists('ame_staff_duty_today') ? ame_staff_duty_today() : date('Y-m-d');
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = substr($today, 0, 7) . '-01';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = $today;
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$rows = $empId > 0 ? ame_worker_history($db, $empId, $from, $to) : [];
$people = ame_worker_can_manage() ? ame_worker_people($db) : [];
$empName = '';
foreach ($people as $person) {
    if ((int)$person['id'] === $empId) {
        $empName = (string)$person['name'];
    }
}
if ($empName === '' && $empId > 0) {
    $one = @$db->prepare('SELECT name FROM employees WHERE id = ? LIMIT 1');
    if ($one) {
        $one->bind_param('i', $empId);
        $one->execute();
        $empName = (string)($one->get_result()->fetch_assoc()['name'] ?? '');
        $one->close();
    }
}
$export = strtolower(trim((string)($_GET['export'] ?? '')));
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$fmtTime = static function (string $t): string {
    $t = trim($t);
    if ($t === '' || $t === '00:00:00') {
        return '—';
    }
    if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $t)) {
        return $t;
    }
    if (strlen($t) === 5) {
        $t .= ':00';
    }
    try {
        $dt = new DateTime('1970-01-01 ' . $t, new DateTimeZone('Asia/Dhaka'));
        return $dt->format('h:i:s A');
    } catch (Throwable $e) {
        return $t;
    }
};

if ($export === 'excel' || $export === 'csv') {
    $file = 'worker-history-' . $empId . '-' . $from . '-' . $to . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['তারিখ', 'সময়', 'কাস্টমার', 'গাড়ির নম্বর', 'ইনভয়েস', 'কাজ', 'কাজের বিল', 'কর্মীর আয়']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['date'],
            $fmtTime((string)($row['time'] ?? '')),
            $row['customer'],
            $row['vehicle'],
            $row['invoice'],
            $row['job'],
            number_format($row['bill'], 2, '.', ''),
            number_format($row['earn'], 2, '.', ''),
        ]);
    }
    fclose($out);
    exit;
}

$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8');
$print = $export === 'pdf' || $export === 'print';
if (!$print) {
    include '../../partials/header.php';
    echo '<link rel="stylesheet" href="/assets/css/worker-desk.css?v=' . $ver . '">';
}
?>
<?php if ($print): ?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>কাজের হিস্ট্রি</title>
    <style>
        body { font-family: "Noto Sans Bengali", sans-serif; color: #111; margin: 24px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        td.num, th.num { text-align: right; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print"><button type="button" onclick="window.print()">PDF সেভ / প্রিন্ট</button> <a href="worker_history.php?emp=<?php echo (int)$empId; ?>&from=<?php echo $h($from); ?>&to=<?php echo $h($to); ?>">ফিরে যান</a></p>
<?php else: ?>
<main class="wd">
<?php endif; ?>
    <h1>কাজের হিস্ট্রি</h1>
    <p><?php echo $h($empName); ?> · <?php echo $h($from); ?> থেকে <?php echo $h($to); ?></p>
    <?php if (!$print): ?>
    <nav class="wd-nav">
        <a href="worker_ledger.php?emp=<?php echo (int)$empId; ?>">লেজার</a>
        <a class="on" href="worker_history.php?emp=<?php echo (int)$empId; ?>">কাজের হিস্ট্রি</a>
        <?php if (ame_worker_can_manage() && $empId > 0): ?><a href="worker_pay.php?emp=<?php echo (int)$empId; ?>">পেমেন্ট</a><?php endif; ?>
    </nav>
    <form class="wd-card wd-filters" method="get">
        <?php if (!ame_worker_can_manage()): ?>
        <input type="hidden" name="emp" value="<?php echo (int)$empId; ?>">
        <?php endif; ?>
        <?php if (ame_worker_can_manage()): ?>
        <label>কর্মী
            <select name="emp">
                <?php foreach ($people as $person): ?>
                <option value="<?php echo (int)$person['id']; ?>"<?php echo (int)$person['id'] === $empId ? ' selected' : ''; ?>><?php echo $h($person['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label>থেকে<input type="date" name="from" value="<?php echo $h($from); ?>"></label>
        <label>পর্যন্ত<input type="date" name="to" value="<?php echo $h($to); ?>"></label>
        <button class="wd-btn primary" type="submit">খুঁজুন</button>
    </form>
    <?php endif; ?>
    <?php if (!$rows): ?>
    <p class="wd-empty">এই তারিখে কোনো কাজের হিস্ট্রি নেই।</p>
    <?php endif; ?>
    <div class="<?php echo $print ? '' : 'wd-card wd-table-wrap'; ?>">
        <table>
            <thead>
                <tr>
                    <th>তারিখ</th><th>সময়</th><th>কাস্টমার</th><th>গাড়ির নম্বর</th><th>ইনভয়েস</th><th>কাজ</th>
                    <th class="num">কাজের বিল</th><th class="num">কর্মীর আয়</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo $h($row['date']); ?></td>
                    <td><?php echo $h($fmtTime((string)($row['time'] ?? ''))); ?></td>
                    <td><?php echo $h($row['customer']); ?></td>
                    <td><?php echo $h($row['vehicle']); ?></td>
                    <td><?php echo $h($row['invoice']); ?></td>
                    <td><?php echo $h($row['job']); ?></td>
                    <td class="num"><?php echo number_format((float)$row['bill'], 2); ?></td>
                    <td class="num"><?php echo number_format((float)$row['earn'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$print): ?>
    <p class="wd-nav">
        <a href="worker_history.php?emp=<?php echo (int)$empId; ?>&from=<?php echo $h($from); ?>&to=<?php echo $h($to); ?>&export=pdf">Export to PDF</a>
        <a href="worker_history.php?emp=<?php echo (int)$empId; ?>&from=<?php echo $h($from); ?>&to=<?php echo $h($to); ?>&export=excel">Export to Excel</a>
    </p>
    <?php endif; ?>
<?php if ($print): ?>
<?php include __DIR__ . '/../../partials/confirm_actions.php'; ?>
</body></html>
<?php else: ?>
</main>
<?php include '../../partials/footer.php'; ?>
<?php endif; ?>
