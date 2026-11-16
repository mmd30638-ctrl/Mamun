<?php
require_once '../../includes/config.php';
require_once '../../includes/workshop_functions.php';
ensure_workshop_schema($db);
require_permission($db, 'm-inv');
require_post();
csrf_verify();

$store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
if ($store_id <= 0 && function_exists('ame_default_store_id')) {
    $store_id = (int)ame_default_store_id($db);
}
if ($store_id <= 0) {
    header('Location: stock_count.php?err=' . rawurlencode('স্টোর পাওয়া যায়নি।'));
    exit;
}

$ids = $_POST['product_id'] ?? [];
$sys = $_POST['system_qty'] ?? [];
$cnt = $_POST['counted_qty'] ?? [];
$note = trim((string)($_POST['note'] ?? ''));
$user_id = (int)($_SESSION['user_id'] ?? 0);

if (!is_array($ids) || count($ids) === 0) {
    header('Location: stock_count.php?err=' . rawurlencode('কোনো পণ্য নেই।'));
    exit;
}

$lines = [];
$n = min(count($ids), 400);
for ($i = 0; $i < $n; $i++) {
    $raw = trim((string)($cnt[$i] ?? ''));
    if ($raw === '') {
        continue;
    }
    $pid = (int)$ids[$i];
    if ($pid <= 0) {
        continue;
    }
    $counted = (float)$raw;
    if ($counted < 0) {
        continue;
    }
    $system = (float)($sys[$i] ?? 0);
    $lines[] = [
        'product_id' => $pid,
        'system' => $system,
        'counted' => $counted,
        'variance' => round($counted - $system, 3),
    ];
}

if (!$lines) {
    header('Location: stock_count.php?err=' . rawurlencode('অন্তত একটি পণ্যের গোনা সংখ্যা লিখুন।'));
    exit;
}

try {
    $db->begin_transaction();
    $no = ame_workshop_next_no($db, 'stock_counts', 'CNT');
    $head = $db->prepare(
        "INSERT INTO stock_counts (count_no, store_id, status, note, created_by, completed_at)
         VALUES (?, ?, 'completed', ?, ?, NOW())"
    );
    $head->bind_param('sisi', $no, $store_id, $note, $user_id);
    $head->execute();
    $cid = (int)$head->insert_id;
    $head->close();

    $ins = $db->prepare(
        'INSERT INTO stock_count_items (count_id, product_id, system_qty, counted_qty, variance)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($lines as $line) {
        $ins->bind_param(
            'iiddd',
            $cid,
            $line['product_id'],
            $line['system'],
            $line['counted'],
            $line['variance']
        );
        $ins->execute();
        if (!ame_store_stock_set($db, $store_id, $line['product_id'], $line['counted'])) {
            throw new RuntimeException('স্টক আপডেট হয়নি।');
        }
    }
    $ins->close();
    $db->commit();
    header('Location: stock_count.php?ok=1');
    exit;
} catch (Throwable $e) {
    try {
        $db->rollback();
    } catch (Throwable $ignored) {
    }
    error_log('stock count: ' . $e->getMessage());
    header('Location: stock_count.php?err=' . rawurlencode('কাউন্ট সেভ হয়নি।'));
    exit;
}
