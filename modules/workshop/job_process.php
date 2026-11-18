<?php
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../../includes/account_functions.php';
require_once __DIR__ . '/../../includes/hrm_functions.php';
require_once __DIR__ . '/../customers/customer_ledger_functions.php';
ensure_hrm_schema($db);

require_post();
csrf_verify();

$action = trim((string)($_POST['action'] ?? ''));
$user_id = (int)($_SESSION['user_id'] ?? 0);
$store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;

function ws_back_job(int $id, string $err = '', bool $ok = false): void
{
    $url = 'job_view.php?id=' . $id;
    if ($ok) {
        $url .= '&ok=1';
    }
    if ($err !== '') {
        $url .= '&err=' . rawurlencode($err);
    }
    header('Location: ' . $url);
    exit;
}

try {
    if ($action === 'create_job') {
        $name = trim((string)($_POST['customer_name'] ?? ''));
        $item = trim((string)($_POST['item_name'] ?? ''));
        $machine_id = (int)($_POST['machine_id'] ?? 0);
        $mid = $machine_id > 0 ? $machine_id : 0;
        if ($mid > 0 && $item === '') {
            $mach = ame_workshop_machine($db, $mid);
            if ($mach) {
                $item = ame_workshop_machine_label($mach);
            }
        }
        if ($name === '' || $item === '') {
            header('Location: job_new.php?err=' . rawurlencode('ক্রেতার নাম ও মেশিনের নাম লাগবে।'));
            exit;
        }
        $job_no = ame_workshop_next_no($db, 'workshop_jobs', 'JOB');
        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $phone = trim((string)($_POST['phone'] ?? ''));
        $serial = trim((string)($_POST['serial_no'] ?? ''));
        $complaint = trim((string)($_POST['complaint'] ?? ''));
        $engineer_id = (int)($_POST['engineer_id'] ?? 0);
        $labor = max(0, (float)($_POST['labor_charge'] ?? 0));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $quote_id = (int)($_POST['quote_id'] ?? 0);
        $cid = $customer_id > 0 ? $customer_id : 0;
        $eid = $engineer_id > 0 ? $engineer_id : 0;
        $qid = $quote_id > 0 ? $quote_id : 0;
        $store_n = $store_id > 0 ? $store_id : 0;

        $stmt = $db->prepare(
            'INSERT INTO workshop_jobs
                (job_no, customer_id, customer_name, phone, item_name, serial_no, complaint,
                 engineer_id, store_id, machine_id, status, labor_charge, quote_id, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'received\', ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'sisssssiiidisi',
            $job_no,
            $cid,
            $name,
            $phone,
            $item,
            $serial,
            $complaint,
            $eid,
            $store_n,
            $mid,
            $labor,
            $qid,
            $notes,
            $user_id
        );
        $stmt->execute();
        $job_id = (int)$stmt->insert_id;
        $stmt->close();
        ame_workshop_recalc_job($db, $job_id);

        if ($serial !== '') {
            ame_workshop_touch_serial($db, $serial, [
                'status' => 'on_job',
                'customer_id' => $customer_id,
                'job_id' => $job_id,
                'store_id' => $store_id,
                'notes' => 'জব ' . $job_no,
            ]);
        }

        if ($quote_id > 0) {
            $items = $db->prepare('SELECT * FROM workshop_quote_items WHERE quote_id = ?');
            $items->bind_param('i', $quote_id);
            $items->execute();
            $rows = $items->get_result()->fetch_all(MYSQLI_ASSOC);
            $items->close();
            foreach ($rows as $row) {
                $pid = (int)$row['product_id'];
                $qty = (float)$row['qty'];
                $price = (float)$row['unit_price'];
                $line = round($qty * $price, 2);
                $pname = (string)$row['product_name'];
                $ins = $db->prepare(
                    'INSERT INTO workshop_job_parts (job_id, product_id, product_name, qty, unit_price, line_total, reserved, issued, line_kind)
                     VALUES (?, ?, ?, ?, ?, ?, 1, 0, \'repair\')'
                );
                $prod_n = $pid > 0 ? $pid : null;
                $ins->bind_param('iisddd', $job_id, $prod_n, $pname, $qty, $price, $line);
                $ins->execute();
                $ins->close();
            }
            ame_workshop_recalc_job($db, $job_id);
            $cv = $db->prepare("UPDATE workshop_quotes SET status = 'converted', job_id = ? WHERE id = ?");
            $cv->bind_param('ii', $job_id, $quote_id);
            $cv->execute();
            $cv->close();
        }

        if (!empty($_POST['send_sms']) && $phone !== '') {
            $msg = ame_sms_text('workshop_receive', ['job' => $job_no, 'item' => $item]);
            ame_workshop_notify($phone, $msg, 'workshop_receive');
        }

        header('Location: job_view.php?id=' . $job_id . '&ok=1');
        exit;
    }

    $job_id = (int)($_POST['job_id'] ?? 0);
    $job = $job_id > 0 ? ame_workshop_job($db, $job_id) : null;
    if (!$job) {
        header('Location: index.php?err=' . rawurlencode('জব পাওয়া যায়নি।'));
        exit;
    }
    if (in_array($job['status'], ['cancelled'], true)) {
        ws_back_job($job_id, 'বন্ধ জব আর বদলানো যায় না।');
    }
    if ($job['status'] === 'delivered' && !in_array($action, ['deliver', 'take_payment'], true)) {
        ws_back_job($job_id, 'বন্ধ জব আর বদলানো যায় না।');
    }

    if ($action === 'update_job') {
        $status = trim((string)($_POST['status'] ?? $job['status']));
        if (!isset(ame_workshop_statuses()[$status]) || in_array($status, ['delivered', 'cancelled'], true)) {
            $status = $job['status'];
        }
        $engineer_id = (int)($_POST['engineer_id'] ?? 0);
        $eid = $engineer_id > 0 ? $engineer_id : 0;
        $diagnosis = trim((string)($_POST['diagnosis'] ?? ''));
        $labor = max(0, (float)($_POST['labor_charge'] ?? 0));
        $discount = max(0, (float)($_POST['discount'] ?? 0));
        $stmt = $db->prepare(
            'UPDATE workshop_jobs SET status = ?, engineer_id = ?, diagnosis = ?, labor_charge = ?, discount = ? WHERE id = ?'
        );
        $stmt->bind_param('sisddi', $status, $eid, $diagnosis, $labor, $discount, $job_id);
        $stmt->execute();
        $stmt->close();
        ame_workshop_recalc_job($db, $job_id);
        if ($status === 'ready' && $job['status'] !== 'ready' && !empty($job['phone'])) {
            $msg = ame_sms_text('workshop_ready', ['job' => $job['job_no']]);
            ame_workshop_notify($job['phone'], $msg, 'workshop_ready');
        }
        ws_back_job($job_id, '', true);
    }

    if ($action === 'add_part') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $qty = (float)($_POST['qty'] ?? 0);
        $unit = (float)($_POST['unit_price'] ?? 0);
        $kind = trim((string)($_POST['line_kind'] ?? 'repair')) === 'sold' ? 'sold' : 'repair';
        if ($product_id <= 0 || $qty <= 0) {
            ws_back_job($job_id, 'পণ্য ও পরিমাণ দিন।');
        }
        $pst = $db->prepare('SELECT id, name, sale_price FROM products WHERE id = ?');
        $pst->bind_param('i', $product_id);
        $pst->execute();
        $product = $pst->get_result()->fetch_assoc();
        $pst->close();
        if (!$product) {
            ws_back_job($job_id, 'পণ্য পাওয়া যায়নি।');
        }
        if ($unit <= 0) {
            $unit = (float)$product['sale_price'];
        }
        $store = (int)$job['store_id'];
        $avail = ame_workshop_available_qty($db, $product_id, $store);
        if ($kind === 'sold' && $avail + 0.0001 < $qty) {
            ws_back_job($job_id, 'পর্যাপ্ত অবশিষ্ট স্টক নেই (রিজার্ভ বাদে)। অবশিষ্ট: ' . $avail);
        }
        $line = round($qty * $unit, 2);
        $name = (string)$product['name'];
        $reserved = $kind === 'sold' ? 0 : 1;
        $issued = $kind === 'sold' ? 1 : 0;
        $db->begin_transaction();
        if ($kind === 'sold') {
            ame_workshop_change_stock($db, $product_id, $qty, $store, true);
        }
        $ins = $db->prepare(
            'INSERT INTO workshop_job_parts (job_id, product_id, product_name, qty, unit_price, line_total, reserved, issued, line_kind)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->bind_param('iisdddiis', $job_id, $product_id, $name, $qty, $unit, $line, $reserved, $issued, $kind);
        $ins->execute();
        $ins->close();
        ame_workshop_recalc_job($db, $job_id);
        if ($kind === 'repair' && ($job['status'] === 'received' || $job['status'] === 'diagnosing')) {
            $db->query("UPDATE workshop_jobs SET status = 'waiting_parts' WHERE id = " . (int)$job_id);
        }
        $db->commit();
        ws_back_job($job_id, '', true);
    }

    if ($action === 'remove_part') {
        $part_id = (int)($_POST['part_id'] ?? 0);
        $find = $db->prepare('SELECT * FROM workshop_job_parts WHERE id = ? AND job_id = ?');
        $find->bind_param('ii', $part_id, $job_id);
        $find->execute();
        $part = $find->get_result()->fetch_assoc();
        $find->close();
        if (!$part) {
            ws_back_job($job_id, 'লাইন পাওয়া যায়নি।');
        }
        $issued = (int)$part['issued'] === 1;
        $sold = (($part['line_kind'] ?? 'repair') === 'sold');
        if ($issued && !$sold) {
            ws_back_job($job_id, 'ইস্যু হওয়া মেরামত যন্ত্রাংশ সরানো যায় না।');
        }
        $db->begin_transaction();
        if ($issued && $sold) {
            ame_workshop_change_stock(
                $db,
                (int)$part['product_id'],
                (float)$part['qty'],
                (int)$job['store_id'],
                false
            );
        }
        $del = $db->prepare('DELETE FROM workshop_job_parts WHERE id = ? AND job_id = ?');
        $del->bind_param('ii', $part_id, $job_id);
        $del->execute();
        $del->close();
        ame_workshop_recalc_job($db, $job_id);
        $db->commit();
        ws_back_job($job_id, '', true);
    }

    if ($action === 'take_payment') {
        $amount = max(0, (float)($_POST['paid_now'] ?? 0));
        $account_id = (int)($_POST['account_id'] ?? 0);
        if ($amount <= 0) {
            ws_back_job($job_id, 'জমার পরিমাণ দিন।');
        }
        if ($job['status'] === 'cancelled') {
            ws_back_job($job_id, 'বাতিল জবে টাকা নেওয়া যায় না।');
        }
        $db->begin_transaction();
        $fresh = ame_workshop_job($db, $job_id);
        if (!$fresh) {
            throw new RuntimeException('জব পাওয়া যায়নি।');
        }
        $new_paid = round((float)$fresh['paid_amount'] + $amount, 2);
        $pay = $db->prepare('UPDATE workshop_jobs SET paid_amount = ? WHERE id = ?');
        $pay->bind_param('di', $new_paid, $job_id);
        $pay->execute();
        $pay->close();
        ame_workshop_recalc_job($db, $job_id);
        $cid = (int)$fresh['customer_id'];
        if ($cid > 0 && function_exists('add_ledger_entry')) {
            add_ledger_entry(
                $db,
                $cid,
                'ওয়ার্কশপ পেমেন্ট',
                (string)$fresh['job_no'],
                'জব জমা ' . $fresh['job_no'],
                0,
                $amount
            );
        }
        if ($account_id > 0 && function_exists('account_credit')) {
            account_credit(
                $account_id,
                $amount,
                'workshop',
                $job_id,
                'জব ' . $fresh['job_no'] . ' জমা'
            );
        }
        $db->commit();
        ws_back_job($job_id, '', true);
    }

    if ($action === 'cancel') {
        if (($job['status'] ?? '') === 'delivered') {
            throw new RuntimeException('ডেলিভারি হওয়া জব সরাসরি বাতিল করা যাবে না। আগে বেতন, পেমেন্ট ও লেজার রিভার্স করুন।');
        }
        $db->begin_transaction();
        foreach (ame_workshop_job_parts($db, $job_id) as $part) {
            if ((int)$part['issued'] === 1 && (($part['line_kind'] ?? '') === 'sold')) {
                ame_workshop_change_stock(
                    $db,
                    (int)$part['product_id'],
                    (float)$part['qty'],
                    (int)$job['store_id'],
                    false
                );
            }
        }
        $rel = $db->prepare(
            'UPDATE workshop_job_parts SET reserved = 0 WHERE job_id = ? AND issued = 0'
        );
        $rel->bind_param('i', $job_id);
        $rel->execute();
        $rel->close();
        $upd = $db->prepare("UPDATE workshop_jobs SET status = 'cancelled' WHERE id = ?");
        $upd->bind_param('i', $job_id);
        $upd->execute();
        $upd->close();
        if (!empty($job['serial_no'])) {
            ame_workshop_touch_serial($db, (string)$job['serial_no'], [
                'status' => 'in_stock',
                'job_id' => $job_id,
                'notes' => 'জব বাতিল',
            ]);
        }
        $db->commit();
        ws_back_job($job_id, '', true);
    }

    if ($action === 'deliver') {
        $paid_now = max(0, (float)($_POST['paid_now'] ?? 0));
        $account_id = (int)($_POST['account_id'] ?? 0);
        $warranty_months = max(0, (int)($_POST['warranty_months'] ?? 0));
        $db->begin_transaction();
        $job_lock = $db->prepare('SELECT id, status FROM workshop_jobs WHERE id = ? FOR UPDATE');
        $job_lock->bind_param('i', $job_id);
        $job_lock->execute();
        $locked_job = $job_lock->get_result()->fetch_assoc();
        $job_lock->close();
        if (!$locked_job || in_array((string)$locked_job['status'], ['delivered', 'cancelled'], true)) {
            throw new RuntimeException('জব ইতিমধ্যে বন্ধ।');
        }
        $fresh = ame_workshop_job($db, $job_id);
        if (!$fresh || in_array($fresh['status'], ['delivered', 'cancelled'], true)) {
            throw new RuntimeException('জব ইতিমধ্যে বন্ধ।');
        }
        ame_workshop_issue_parts($db, $fresh);
        $already = (float)$fresh['paid_amount'];
        $total_paid = round($already + $paid_now, 2);
        $paid_stmt = $db->prepare(
            'UPDATE workshop_jobs SET paid_amount = ?, warranty_months = ? WHERE id = ?'
        );
        $paid_stmt->bind_param('dii', $total_paid, $warranty_months, $job_id);
        $paid_stmt->execute();
        $paid_stmt->close();
        ame_workshop_recalc_job($db, $job_id);
        $fresh = ame_workshop_job($db, $job_id);
        $grand = (float)$fresh['grand_total'];
        $due = (float)$fresh['due_amount'];
        $now = date('Y-m-d H:i:s');
        $fin = $db->prepare(
            "UPDATE workshop_jobs SET status = 'delivered', delivered_at = ? WHERE id = ?"
        );
        $fin->bind_param('si', $now, $job_id);
        $fin->execute();
        $fin->close();

        if ((int)($fresh['engineer_id'] ?? 0) > 0 && (float)($fresh['labor_charge'] ?? 0) > 0) {
            record_service_commission(
                $db,
                0,
                (int)$fresh['engineer_id'],
                (float)$fresh['labor_charge'],
                'মেরামত · ' . (string)$fresh['item_name'],
                'workshop_job',
                $job_id
            );
        }

        $cid = (int)$fresh['customer_id'];
        if ($cid > 0 && $grand > 0 && function_exists('add_ledger_entry')) {
            add_ledger_entry(
                $db,
                $cid,
                'ওয়ার্কশপ',
                (string)$fresh['job_no'],
                'জব ডেলিভারি ' . $fresh['job_no'] . ' — ' . $fresh['item_name'],
                $grand,
                0
            );
            if ($paid_now > 0) {
                add_ledger_entry(
                    $db,
                    $cid,
                    'ওয়ার্কশপ পেমেন্ট',
                    (string)$fresh['job_no'],
                    'জব পরিশোধ ' . $fresh['job_no'],
                    0,
                    $paid_now
                );
            }
        }
        if ($paid_now > 0 && $account_id > 0 && function_exists('account_credit')) {
            account_credit(
                $account_id,
                $paid_now,
                'workshop',
                $job_id,
                'জব ' . $fresh['job_no'] . ' পরিশোধ'
            );
        }
        if (!empty($fresh['serial_no'])) {
            $expiry = '';
            if ($warranty_months > 0) {
                $expiry = date('Y-m-d', strtotime('+' . $warranty_months . ' months'));
            }
            ame_workshop_touch_serial($db, (string)$fresh['serial_no'], [
                'status' => 'in_stock',
                'customer_id' => $cid,
                'job_id' => $job_id,
                'warranty_months' => $warranty_months,
                'warranty_expiry' => $expiry,
                'notes' => 'মেরামত ডেলিভারি ' . $fresh['job_no'],
            ]);
        }
        $db->commit();

        if (!empty($_POST['send_sms']) && !empty($fresh['phone'])) {
            $msg = ame_sms_text('workshop_deliver', ['job' => $fresh['job_no'], 'total' => $grand, 'due' => $due]);
            ame_workshop_notify($fresh['phone'], $msg, 'workshop_deliver');
        }

        header('Location: slip.php?id=' . $job_id);
        exit;
    }

    header('Location: index.php?err=' . rawurlencode('অজানা কাজ।'));
    exit;
} catch (Throwable $e) {
    if ($db instanceof mysqli) {
        try {
            $db->rollback();
        } catch (Throwable $ignored) {
        }
    }
    error_log('workshop job_process: ' . $e->getMessage());
    $msg = $e instanceof RuntimeException ? $e->getMessage() : 'কাজটি সম্পন্ন হয়নি।';
    if (!empty($job_id)) {
        ws_back_job((int)$job_id, $msg);
    }
    header('Location: index.php?err=' . rawurlencode($msg));
    exit;
}
