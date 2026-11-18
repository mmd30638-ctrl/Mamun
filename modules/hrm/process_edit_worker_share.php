<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/account_functions.php';

ensure_hrm_schema($db);
require_post();
csrf_verify();

$action = trim((string)($_POST['action'] ?? ''));
$emp_id = (int)($_POST['employee_id'] ?? 0);
$ym = trim((string)($_POST['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
    $ym = date('Y-m');
}
[, $from, $to] = ame_month_bounds($ym);

$redirect_back = static function (string $status, string $msg = '') use ($emp_id, $ym): void {
    $url = 'edit_worker_share.php?id=' . $emp_id . '&month=' . urlencode($ym) . '&' . $status . '=' . urlencode($msg);
    header('Location: ' . $url);
    exit();
};

if ($emp_id <= 0) {
    $redirect_back('err', 'worker');
}

$db->begin_transaction();
try {
    if ($action === 'recalculate') {
        $pct = max(0, min(100, (float)($_POST['percent'] ?? 0)));
        $stmt = $db->prepare(
            "SELECT id, service_amount, status
             FROM service_commissions
             WHERE employee_id = ? AND created_at >= ? AND created_at < ?"
        );
        $stmt->bind_param("iss", $emp_id, $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        $up = $db->prepare(
            "UPDATE service_commissions
             SET commission_rate = ?, commission_amount = ?, shop_amount = ?
             WHERE id = ?"
        );
        while ($row = $res->fetch_assoc()) {
            $sid = (int)$row['id'];
            $bill = (float)$row['service_amount'];
            $w_share = round($bill * ($pct / 100), 2);
            $s_share = round($bill - $w_share, 2);
            $up->bind_param("dddi", $pct, $w_share, $s_share, $sid);
            $up->execute();

            if (function_exists('ame_earnings_has_table') && ame_earnings_has_table($db, 'worker_earnings')) {
                $pay_st = ($row['status'] === 'paid') ? 'paid' : 'unpaid';
                $p_amt = ($row['status'] === 'paid') ? $w_share : 0;
                $db->query(
                    "UPDATE worker_earnings
                     SET work_amount = {$bill}, percent_rate = {$pct}, rule_label = '{$pct}%',
                         earning_amount = {$w_share}, company_share = {$s_share},
                         pay_status = '{$pay_st}', paid_amount = {$p_amt}
                     WHERE source_type = 'sale_service' AND source_id = {$sid}"
                );
            }
        }
        $up->close();

        if (!empty($_POST['save_default_percent'])) {
            $db->query("UPDATE employees SET service_share_percent = {$pct} WHERE id = {$emp_id}");
        }

        $db->commit();
        if (function_exists('log_activity')) {
            log_activity('Worker Share Recalculated', 'HRM', "Employee #{$emp_id} month {$ym} rate {$pct}%");
        }
        $redirect_back('ok', 'recalc');
    }

    if ($action === 'save_line') {
        $line_id = (int)($_POST['line_id'] ?? 0);
        if ($line_id <= 0) {
            $redirect_back('err', 'notfound');
        }

        $bill = round((float)($_POST['service_amount'] ?? 0), 2);
        $rate = round((float)($_POST['commission_rate'] ?? 0), 2);
        $w_share = round((float)($_POST['commission_amount'] ?? 0), 2);
        $s_share = round((float)($_POST['shop_amount'] ?? ($bill - $w_share)), 2);
        $stype = clean_input($_POST['service_type'] ?? 'সার্ভিস');
        $status = in_array($_POST['status'] ?? '', ['pending', 'paid'], true) ? $_POST['status'] : 'pending';

        $up = $db->prepare(
            "UPDATE service_commissions
             SET service_amount = ?, commission_rate = ?, commission_amount = ?, shop_amount = ?, service_type = ?, status = ?
             WHERE id = ? AND employee_id = ?"
        );
        $up->bind_param("ddddssii", $bill, $rate, $w_share, $s_share, $stype, $status, $line_id, $emp_id);
        $up->execute();
        $up->close();

        if (function_exists('ame_earnings_has_table') && ame_earnings_has_table($db, 'worker_earnings')) {
            $pay_st = ($status === 'paid') ? 'paid' : 'unpaid';
            $p_amt = ($status === 'paid') ? $w_share : 0;
            $db->query(
                "UPDATE worker_earnings
                 SET work_amount = {$bill}, percent_rate = {$rate}, rule_label = '{$rate}%',
                     earning_amount = {$w_share}, company_share = {$s_share},
                     pay_status = '{$pay_st}', paid_amount = {$p_amt}
                 WHERE source_type = 'sale_service' AND source_id = {$line_id}"
            );
        }

        $db->commit();
        if (function_exists('log_activity')) {
            log_activity('Worker Share Line Saved', 'HRM', "Line #{$line_id} Emp #{$emp_id} worker: {$w_share}, shop: {$s_share}");
        }
        $redirect_back('ok', 'saved');
    }

    if ($action === 'delete_line') {
        $line_id = (int)($_POST['line_id'] ?? 0);
        if ($line_id <= 0) {
            $redirect_back('err', 'notfound');
        }

        $db->query("DELETE FROM service_commissions WHERE id = {$line_id} AND employee_id = {$emp_id}");
        if (function_exists('ame_earnings_has_table') && ame_earnings_has_table($db, 'worker_earnings')) {
            $db->query("DELETE FROM worker_earnings WHERE source_type = 'sale_service' AND source_id = {$line_id}");
        }

        $db->commit();
        if (function_exists('log_activity')) {
            log_activity('Worker Share Line Deleted', 'HRM', "Line #{$line_id} Emp #{$emp_id}");
        }
        $redirect_back('ok', 'deleted');
    }

    if ($action === 'direct_override') {
        $wanted_bill = round((float)($_POST['target_bill'] ?? 0), 2);
        $wanted_worker = round((float)($_POST['target_worker'] ?? 0), 2);
        $wanted_shop = round((float)($_POST['target_shop'] ?? ($wanted_bill - $wanted_worker)), 2);
        $stype = clean_input($_POST['note'] ?? 'সরাসরি ব্যালেন্স সমন্বয়');
        $status = in_array($_POST['status'] ?? '', ['pending', 'paid'], true) ? $_POST['status'] : 'pending';

        $rate = ($wanted_bill > 0) ? round(($wanted_worker / $wanted_bill) * 100, 2) : 50.0;
        $created_at = $from . ' 12:00:00';

        // Check existing records in this month
        $stmt = $db->prepare(
            "SELECT id FROM service_commissions
             WHERE employee_id = ? AND created_at >= ? AND created_at < ?
             ORDER BY id ASC"
        );
        $stmt->bind_param("iss", $emp_id, $from, $to);
        $stmt->execute();
        $r_ids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (count($r_ids) === 1) {
            $one_id = (int)$r_ids[0]['id'];
            $up = $db->prepare(
                "UPDATE service_commissions
                 SET service_amount = ?, commission_rate = ?, commission_amount = ?, shop_amount = ?, service_type = ?, status = ?
                 WHERE id = ?"
            );
            $up->bind_param("ddddssi", $wanted_bill, $rate, $wanted_worker, $wanted_shop, $stype, $status, $one_id);
            $up->execute();
            $up->close();
        } elseif (count($r_ids) > 1 && !empty($_POST['consolidate'])) {
            // Consolidate: delete all lines and create single clean entry
            $db->query("DELETE FROM service_commissions WHERE employee_id = {$emp_id} AND created_at >= '{$from}' AND created_at < '{$to}'");
            $ins = $db->prepare(
                "INSERT INTO service_commissions
                 (employee_id, service_amount, commission_rate, commission_amount, shop_amount, service_type, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->bind_param("iddddsss", $emp_id, $wanted_bill, $rate, $wanted_worker, $wanted_shop, $stype, $status, $created_at);
            $ins->execute();
            $ins->close();
        } elseif (count($r_ids) > 1) {
            // Calculate current sum, and insert or adjust difference
            $curr_sum = $db->query(
                "SELECT COALESCE(SUM(service_amount),0) AS sb, COALESCE(SUM(commission_amount),0) AS sw, COALESCE(SUM(shop_amount),0) AS ss
                 FROM service_commissions
                 WHERE employee_id = {$emp_id} AND created_at >= '{$from}' AND created_at < '{$to}'"
            )->fetch_assoc();

            $diff_bill = round($wanted_bill - (float)($curr_sum['sb'] ?? 0), 2);
            $diff_worker = round($wanted_worker - (float)($curr_sum['sw'] ?? 0), 2);
            $diff_shop = round($wanted_shop - (float)($curr_sum['ss'] ?? 0), 2);
            $adj_rate = (abs($diff_bill) > 0.001) ? round(($diff_worker / $diff_bill) * 100, 2) : 0;

            $ins = $db->prepare(
                "INSERT INTO service_commissions
                 (employee_id, service_amount, commission_rate, commission_amount, shop_amount, service_type, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->bind_param("iddddsss", $emp_id, $diff_bill, $adj_rate, $diff_worker, $diff_shop, $stype, $status, $created_at);
            $ins->execute();
            $ins->close();
        } else {
            // 0 existing records
            $ins = $db->prepare(
                "INSERT INTO service_commissions
                 (employee_id, service_amount, commission_rate, commission_amount, shop_amount, service_type, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->bind_param("iddddsss", $emp_id, $wanted_bill, $rate, $wanted_worker, $wanted_shop, $stype, $status, $created_at);
            $ins->execute();
            $ins->close();
        }

        $db->commit();
        if (function_exists('log_activity')) {
            log_activity('Worker Share Direct Override', 'HRM', "Emp #{$emp_id} month {$ym} worker: {$wanted_worker}, shop: {$wanted_shop}");
        }
        $redirect_back('ok', 'override');
    }

    if ($action === 'add_line') {
        $bill = round((float)($_POST['service_amount'] ?? 0), 2);
        $rate = round((float)($_POST['commission_rate'] ?? 50), 2);
        $w_share = round((float)($_POST['commission_amount'] ?? round($bill * $rate / 100, 2)), 2);
        $s_share = round((float)($_POST['shop_amount'] ?? round($bill - $w_share, 2)), 2);
        $stype = clean_input($_POST['service_type'] ?? 'সার্ভিস কাজ');
        $status = in_array($_POST['status'] ?? '', ['pending', 'paid'], true) ? $_POST['status'] : 'pending';
        $entry_date = trim((string)($_POST['entry_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry_date)) {
            $entry_date = date('Y-m-d');
        }
        $created_at = $entry_date . ' 12:00:00';

        $ins = $db->prepare(
            "INSERT INTO service_commissions
             (employee_id, service_amount, commission_rate, commission_amount, shop_amount, service_type, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->bind_param("iddddsss", $emp_id, $bill, $rate, $w_share, $s_share, $stype, $status, $created_at);
        $ins->execute();
        $ins_id = (int)$db->insert_id;
        $ins->close();

        if (function_exists('ame_earnings_lock_from_commission')) {
            ame_earnings_lock_from_commission($db, $ins_id);
        }

        $db->commit();
        if (function_exists('log_activity')) {
            log_activity('Worker Share Line Added', 'HRM', "Line #{$ins_id} Emp #{$emp_id} bill: {$bill}");
        }
        $redirect_back('ok', 'added');
    }

    $db->rollback();
    $redirect_back('err', 'invalid');
} catch (Throwable $e) {
    $db->rollback();
    error_log('Process edit worker share failed: ' . $e->getMessage());
    $redirect_back('err', 'save');
}
