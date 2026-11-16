<?php
require_once __DIR__ . '/_boot.php';
hire_require($db, 'view');
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-LiteSpeed-Cache-Control: no-cache');
    header('X-Hire-List: 10.98.775');
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$cid = (int) ($_GET['customer_id'] ?? 0);
$allowed = ['', 'live', 'ACTIVE', 'OVERDUE', 'PAUSED', 'INACTIVE', 'RETURNED', 'COMPLETED', 'CANCELLED', 'DRAFT'];
if (!in_array($status, $allowed, true)) {
    $status = '';
}

$liveSet = ['ACTIVE' => true, 'OVERDUE' => true, 'PAUSED' => true];
$doneSet = ['COMPLETED' => true, 'RETURNED' => true];

$dbIds = [];
$idRes = @$db->query('SELECT id FROM rental_contracts ORDER BY id DESC LIMIT 500');
if ($idRes) {
    while ($idRow = $idRes->fetch_assoc()) {
        $dbIds[] = (int) ($idRow['id'] ?? 0);
    }
}
$all = [];
foreach ($dbIds as $oneId) {
    if ($oneId <= 0) {
        continue;
    }
    $one = @$db->query('SELECT * FROM rental_contracts WHERE id = ' . $oneId . ' LIMIT 1');
    if ($one) {
        $row = $one->fetch_assoc();
        if (is_array($row)) {
            $all[] = $row;
        }
    }
}
$custIds = [];
foreach ($all as $r) {
    $id = (int) ($r['customer_id'] ?? 0);
    if ($id > 0) {
        $custIds[$id] = $id;
    }
}
$boundMissing = 0;
if ($custIds !== []) {
    $have = [];
    $hq = @$db->query('SELECT id FROM customers WHERE id IN (' . implode(',', array_map('intval', $custIds)) . ')');
    if ($hq) {
        while ($h = $hq->fetch_assoc()) {
            $have[(int) $h['id']] = true;
        }
    }
    foreach ($custIds as $cidNeed) {
        if (isset($have[$cidNeed])) {
            continue;
        }
        $code = 'HIRE-' . $cidNeed;
        $name = 'কাস্টমার #' . $cidNeed;
        $phone = '';
        $ins = @$db->prepare('INSERT INTO customers (id, customer_code, name, phone, is_customer, is_rental, status) VALUES (?, ?, ?, ?, 1, 1, 1)');
        if ($ins) {
            $ins->bind_param('isss', $cidNeed, $code, $name, $phone);
            if ($ins->execute()) {
                $boundMissing++;
            }
            $ins->close();
        }
    }
}
$custMap = [];
if ($custIds !== []) {
    $cq = @$db->query('SELECT id, name, phone FROM customers WHERE id IN (' . implode(',', array_map('intval', $custIds)) . ')');
    if ($cq) {
        while ($c = $cq->fetch_assoc()) {
            $custMap[(int) $c['id']] = $c;
        }
    }
}

$qLow = $q === '' ? '' : (function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q));
$counts = ['all' => 0, 'live' => 0, 'INACTIVE' => 0, 'COMPLETED' => 0];
$rows = [];
foreach ($all as $r) {
    $stRaw = strtoupper(trim((string) ($r['status'] ?? '')));
    $st = preg_replace('/[^A-Z]/', '', $stRaw) ?: $stRaw;
    $isDone = isset($doneSet[$st]) || $st === 'CANCELLED';
    $isInactive = ($st === 'INACTIVE');
    $isLive = isset($liveSet[$st]) || (!$isDone && !$isInactive && $st !== 'DRAFT');
    $counts['all']++;
    if ($isLive) {
        $counts['live']++;
    }
    if ($isInactive) {
        $counts['INACTIVE']++;
    }
    if (isset($doneSet[$st])) {
        $counts['COMPLETED']++;
    }
    $c = $custMap[(int) ($r['customer_id'] ?? 0)] ?? null;
    $r['customer_name'] = (string) ($c['name'] ?? '');
    $r['customer_phone'] = (string) ($c['phone'] ?? '');
    $r['_st'] = $st !== '' ? $st : 'ACTIVE';
    $r['_live'] = $isLive;
    $r['_start'] = substr((string) ($r['start_date'] ?? $r['created_at'] ?? ''), 0, 10);

    if ($cid > 0 && (int) ($r['customer_id'] ?? 0) !== $cid) {
        continue;
    }
    if ($status === 'live' && !$isLive) {
        continue;
    }
    if ($status === 'COMPLETED' && !isset($doneSet[$st])) {
        continue;
    }
    if ($status !== '' && $status !== 'live' && $status !== 'COMPLETED' && $st !== $status) {
        continue;
    }
    if ($from !== '' && $r['_start'] !== '' && $r['_start'] < $from) {
        continue;
    }
    if ($to !== '' && $r['_start'] !== '' && $r['_start'] > $to) {
        continue;
    }
    if ($qLow !== '') {
        $hay = trim(($r['rental_no'] ?? '') . ' ' . ($r['item_name'] ?? '') . ' ' . ($r['customer_name'] ?? '') . ' ' . ($r['customer_phone'] ?? '') . ' ' . ($r['serial_no'] ?? '') . ' ' . ($r['vehicle_no'] ?? ''));
        $hayL = function_exists('mb_strtolower') ? mb_strtolower($hay, 'UTF-8') : strtolower($hay);
        if (!str_contains($hayL, $qLow)) {
            continue;
        }
    }
    $rows[] = $r;
}
usort($rows, static function ($a, $b) {
    $ra = !empty($a['_live']) ? 0 : ((($a['_st'] ?? '') === 'INACTIVE') ? 1 : 2);
    $rb = !empty($b['_live']) ? 0 : ((($b['_st'] ?? '') === 'INACTIVE') ? 1 : 2);
    if ($ra !== $rb) {
        return $ra <=> $rb;
    }
    return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
});

$here = 'now.php';
include __DIR__ . '/../../../partials/header.php';
$chipQs = static function (array $over) use ($q, $status, $from, $to, $cid): string {
    $qs = http_build_query(array_filter([
        'status' => array_key_exists('status', $over) ? $over['status'] : ($status !== '' ? $status : null),
        'q' => $q !== '' ? $q : null,
        'from' => $from !== '' ? $from : null,
        'to' => $to !== '' ? $to : null,
        'customer_id' => $cid > 0 ? $cid : null,
    ], static fn ($v) => $v !== null && $v !== ''));
    return 'now.php' . ($qs !== '' ? ('?' . $qs) : '');
};
?>
<div class="hire-wrap" style="max-width:720px;margin:8px auto 24px;padding:0 12px">
  <h1 style="margin:8px 0 4px;font-size:22px;color:#003f8f">সব চুক্তি</h1>
  <p style="margin:0 0 10px;font-size:13px;font-weight:700;color:#0f766e">তালিকা 10.98.775 · ডাটাবেজ <?php echo count($dbIds); ?>টি<?php if ($boundMissing > 0): ?> · মিসিং কাস্টমার <?php echo (int) $boundMissing; ?>টি বসানো<?php endif; ?></p>
  <?php include __DIR__ . '/_nav.php'; ?>
  <p style="margin:8px 0;font-size:14px;font-weight:700">মোট <?php echo (int) $counts['all']; ?> · চলমান <?php echo (int) $counts['live']; ?> · ইনঅ্যাকটিভ <?php echo (int) $counts['INACTIVE']; ?> · সম্পন্ন <?php echo (int) $counts['COMPLETED']; ?></p>
  <p style="margin:0 0 10px">
    <a href="<?php echo hire_h($chipQs(['status' => ''])); ?>">সব</a>
    · <a href="<?php echo hire_h($chipQs(['status' => 'live'])); ?>">চলমান</a>
    · <a href="<?php echo hire_h($chipQs(['status' => 'INACTIVE'])); ?>">ইনঅ্যাকটিভ</a>
    · <a href="<?php echo hire_h($chipQs(['status' => 'COMPLETED'])); ?>">সম্পন্ন</a>
  </p>
  <?php if ($all === []): ?>
  <p>এখন কোনো ভাড়া চুক্তি নেই।</p>
  <?php elseif ($rows === []): ?>
  <p>এই ফিল্টারে নেই। <a href="now.php">সব দেখুন</a></p>
  <?php else:
        $nameStyle = function_exists('hire_plain_name_style') ? hire_plain_name_style() : 'display:block!important;visibility:visible!important;font-weight:800;margin:8px 0 12px;color:#111;font-size:15px';
        $bits = [];
        foreach ($rows as $bit) {
            $nm = trim((string) ($bit['customer_name'] ?? ''));
            if ($nm === '') {
                $nm = 'কাস্টমার #' . (int) ($bit['customer_id'] ?? 0);
            }
            $bits[] = (string) ($bit['rental_no'] ?? '') . ' · ' . $nm;
        }
  ?>
  <p id="hireAllNames" style="<?php echo $nameStyle; ?>">দেখাচ্ছে <?php echo count($rows); ?>টি: <?php echo hire_h(implode(' | ', $bits)); ?></p>
  <div class="hire-all-ol hire-all-table" id="hirePlainBox" style="display:block!important;visibility:visible!important;opacity:1!important;margin:0;padding:0">
    <?php foreach ($rows as $i => $r):
        $name = trim((string) ($r['customer_name'] ?? ''));
        if ($name === '') {
            $name = 'কাস্টমার #' . (int) ($r['customer_id'] ?? 0);
        }
        $meta = trim((string) ($r['customer_phone'] ?? '') . ' ' . (string) ($r['item_name'] ?? ''));
        $border = !empty($r['_live']) ? '#86efac' : '#d7dee8';
        $label = function_exists('hire_status_label') ? hire_status_label((string) $r['_st']) : (string) $r['_st'];
        $rowStyle = function_exists('hire_plain_box_style') ? hire_plain_box_style($border) : 'display:block!important;visibility:visible!important;margin:0 0 10px;padding:10px 12px;background:#fff;border:1px solid ' . $border . ';border-radius:12px';
    ?>
    <div data-hire-row="<?php echo (int) $r['id']; ?>" style="<?php echo $rowStyle; ?>">
      <div style="font-size:12px;font-weight:800;color:#003f8f"><?php echo (int) $i + 1; ?>. <?php echo hire_h((string) ($r['rental_no'] ?? '')); ?> · <?php echo hire_h((string) ($r['_start'] ?? '')); ?> · <?php echo hire_h($label); ?></div>
      <div style="font-size:17px;font-weight:800;margin-top:2px"><a href="contract.php?id=<?php echo (int) $r['id']; ?>" style="color:#111;text-decoration:none"><?php echo hire_h($name); ?></a></div>
      <?php if (trim($meta) !== ''): ?><div style="font-size:13px;color:#555"><?php echo hire_h(trim($meta)); ?></div><?php endif; ?>
      <a href="contract.php?id=<?php echo (int) $r['id']; ?>" style="display:inline-block;margin-top:8px;background:#003f8f;color:#fff;padding:6px 12px;border-radius:8px;text-decoration:none;font-weight:700">খুলুন</a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
