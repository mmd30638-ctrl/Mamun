<?php
require_once '../../includes/config.php';
require_once '../../includes/store_functions.php';
require_once '../../includes/damage_functions.php';
require_permission($db, 'm-inv');
require_post();
csrf_verify();
ame_damage_ensure($db);

$action = (string)($_POST['action'] ?? 'save');
$back = (string)($_POST['back'] ?? '');
if ($back !== '' && !preg_match('/^[A-Za-z0-9_%=&.\-]*$/', $back)) {
    $back = '';
}
$fail = static function (string $message, string $target) use ($back): void {
    $_SESSION['dmg_flash'] = $message;
    $_SESSION['dmg_ok'] = 0;
    $join = $back !== '' ? (str_contains($target, '?') ? '&' : '?') . $back : '';
    header('Location: ' . $target . $join);
    exit;
};

$reasons = ame_damage_reasons();
$reason = (string)($_POST['reason'] ?? 'other');
if (!isset($reasons[$reason])) {
    $reason = 'other';
}
$note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255);
$damage_date = trim((string)($_POST['damage_date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $damage_date)) {
    $damage_date = date('Y-m-d');
}

if ($action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $fail('ড্যামেজ এন্ট্রি পাওয়া যায়নি।', 'damage.php');
    }
    $upd = $db->prepare("UPDATE inventory_damage SET reason = ?, note = ?, damage_date = ? WHERE id = ? AND status = 'posted'");
    if (!$upd) {
        $fail('সংশোধন সেভ হয়নি।', 'damage-edit.php?id=' . $id);
    }
    $upd->bind_param('sssi', $reason, $note, $damage_date, $id);
    $upd->execute();
    $changed = $upd->affected_rows >= 0 && $upd->errno === 0;
    $upd->close();
    if (!$changed) {
        $fail('সংশোধন সেভ হয়নি।', 'damage-edit.php?id=' . $id);
    }
    $_SESSION['dmg_flash'] = 'ড্যামেজ নোট আপডেট হয়েছে। স্টক আগের মতোই আছে।';
    $_SESSION['dmg_ok'] = 1;
    header('Location: damage.php' . ($back !== '' ? '?' . $back : ''));
    exit;
}

if ($action === 'void') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $fail('ড্যামেজ এন্ট্রি পাওয়া যায়নি।', 'damage.php');
    }
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT * FROM inventory_damage WHERE id = ? FOR UPDATE");
        if (!$stmt) {
            throw new RuntimeException('row');
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || (string)$row['status'] !== 'posted') {
            throw new RuntimeException('এই এন্ট্রি আর বাতিল করা যাবে না।');
        }
        $qty = (float)$row['qty'];
        $productId = (int)$row['product_id'];
        $storeId = (int)($row['store_id'] ?? 0);
        if (!ame_damage_apply_stock($db, $productId, $qty, $storeId, true)) {
            throw new RuntimeException('স্টক ফেরত যায়নি।');
        }
        $void = $db->prepare("UPDATE inventory_damage SET status = 'void' WHERE id = ? AND status = 'posted'");
        if (!$void) {
            throw new RuntimeException('বাতিল সেভ হয়নি।');
        }
        $void->bind_param('i', $id);
        $void->execute();
        $void->close();
        ame_damage_log_movement($db, $productId, $storeId, $qty, (float)$row['unit_cost'], $id, 'ড্যামেজ বাতিল', true);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        $fail($e->getMessage() !== '' ? $e->getMessage() : 'বাতিল হয়নি। স্টক আগের মতোই আছে।', 'damage.php');
    }
    if (function_exists('log_activity')) {
        log_activity('Damage Void', 'Inventory', 'DMG-' . $id, [
            'action_type' => 'damage_void',
            'entity_type' => 'inventory_damage',
            'entity_id' => (string)$id,
        ]);
    }
    $_SESSION['dmg_flash'] = 'ড্যামেজ বাতিল হয়েছে এবং স্টক ফিরিয়ে দেওয়া হয়েছে।';
    $_SESSION['dmg_ok'] = 1;
    $go = ((string) ($_POST['from'] ?? '') === 'report') ? 'damage_report.php' : 'damage.php';
    header('Location: ' . $go . ($back !== '' ? '?' . $back : ''));
    exit;
}

$lines = $_POST['lines'] ?? [];
if (!is_array($lines) || $lines === []) {
    $lines = [[
        'product_id' => (int) ($_POST['product_id'] ?? 0),
        'qty' => $_POST['qty'] ?? 0,
        'reason' => $reason,
        'note' => $note,
    ]];
}
$clean = [];
foreach ($lines as $line) {
    if (!is_array($line)) {
        continue;
    }
    $pid = (int) ($line['product_id'] ?? 0);
    $qty = round((float) ($line['qty'] ?? 0), 3);
    $why = (string) ($line['reason'] ?? $reason);
    if (!isset($reasons[$why])) {
        $why = 'other';
    }
    $memo = mb_substr(trim((string) ($line['note'] ?? $note)), 0, 255);
    if ($pid > 0 && $qty > 0) {
        $clean[] = ['product_id' => $pid, 'qty' => $qty, 'reason' => $why, 'note' => $memo];
    }
}
if ($clean === []) {
    $fail('পণ্য ও পরিমাণ দিন। চার্জ বক্স থেকে পণ্য নিন।', 'damage.php');
}
$store_id = function_exists('current_store_id') ? (int) current_store_id() : 0;
$user_id = (int) ($_SESSION['user_id'] ?? 0);

$db->begin_transaction();
$newIds = [];
try {
    $cols = ame_damage_product_cols($db);
    $costSql = ame_damage_cost_sql($cols);
    $stockSql = isset($cols['stock']) ? 'stock' : '0';
    foreach ($clean as $line) {
        $product_id = $line['product_id'];
        $qty = $line['qty'];
        $why = $line['reason'];
        $lineNote = $line['note'];
        $lock = $db->prepare("SELECT id, name, {$stockSql} AS stock, {$costSql} AS unit_cost FROM products WHERE id = ? FOR UPDATE");
        if (!$lock) {
            throw new RuntimeException('পণ্য খোলা যায়নি।');
        }
        $lock->bind_param('i', $product_id);
        $lock->execute();
        $product = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$product) {
            throw new RuntimeException('পণ্য খুঁজে পাওয়া যায়নি।');
        }
        if ((float) $product['stock'] + 0.0001 < $qty) {
            throw new RuntimeException(($product['name'] ?? 'পণ্য') . ' — স্টকে এই পরিমাণ নেই। এখন আছে ' . ame_damage_fmt_qty((float) $product['stock']));
        }
        if (!ame_damage_apply_stock($db, $product_id, $qty, $store_id, false)) {
            throw new RuntimeException('স্টক কমানো যায়নি। পরিমাণ স্টকের বেশি হতে পারে।');
        }
        $unit = round((float) $product['unit_cost'], 2);
        $loss = round($qty * $unit, 2);
        $ins = $db->prepare(
            'INSERT INTO inventory_damage (product_id, store_id, qty, unit_cost, loss_amount, reason, note, damage_date, status, created_by)
             VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?, \'posted\', NULLIF(?, 0))'
        );
        if (!$ins) {
            throw new RuntimeException('ড্যামেজ খাতা সেভ হয়নি।');
        }
        $ins->bind_param('iidddsssi', $product_id, $store_id, $qty, $unit, $loss, $why, $lineNote, $damage_date, $user_id);
        $ins->execute();
        $newId = (int) $ins->insert_id;
        $ins->close();
        $newIds[] = $newId;
        $memo = $reasons[$why] . ($lineNote !== '' ? ' — ' . $lineNote : '');
        ame_damage_log_movement($db, $product_id, $store_id, $qty, $unit, $newId, $memo, false);
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    $fail($e->getMessage() !== '' ? $e->getMessage() : 'ড্যামেজ সেভ হয়নি। স্টক কাটেনি।', 'damage.php');
}

if (function_exists('log_activity')) {
    foreach ($newIds as $newId) {
        log_activity('Damage Entry', 'Inventory', 'DMG-' . $newId, [
            'action_type' => 'damage',
            'entity_type' => 'inventory_damage',
            'entity_id' => (string) $newId,
            'amount_paid' => 0,
        ]);
    }
}
$_SESSION['dmg_flash'] = count($newIds) . 'টি ড্যামেজ খাতায় যোগ হয়েছে এবং স্টক কমেছে।';
$_SESSION['dmg_ok'] = 1;
header('Location: damage.php' . ($back !== '' ? '?' . $back : ''));
exit;
