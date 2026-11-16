<?php
/* 10.98.712 */
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/employee_hub.php';
require_once '../../includes/worker_desk.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: worker_pay.php');
    exit;
}
if (!ame_worker_can_manage()) {
    http_response_code(403);
    die('পেমেন্ট দেওয়ার অনুমতি নেই।');
}
csrf_verify();

$empId = (int)($_POST['employee_id'] ?? 0);
$accId = (int)($_POST['account_id'] ?? 0);
$amount = round(max(0, (float)($_POST['amount'] ?? 0)), 2);
$adjust = round(max(0, (float)($_POST['advance_adjust'] ?? 0)), 2);
$extra = round(max(0, (float)($_POST['extra_amount'] ?? 0)), 2);
$oldAsk = round(max(0, (float)($_POST['advance_old'] ?? 0)), 2);
$give = round(max(0, (float)($_POST['advance_give'] ?? 0)), 2);
$notes = trim((string)($_POST['notes'] ?? ''));
$todayDate = function_exists('ame_staff_duty_today') ? ame_staff_duty_today() : date('Y-m-d');
$payDate = $todayDate;
$askDate = trim((string)($_POST['pay_date'] ?? ''));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $askDate) && strtotime($askDate) !== false && $askDate <= $todayDate) {
    $payDate = $askDate;
}
$back = static function (string $msg) use ($empId, $payDate): void {
    header('Location: worker_pay.php?' . http_build_query(['emp' => $empId, 'date' => $payDate, 'err' => $msg]));
    exit;
};
if ($empId <= 0 || ($amount <= 0.009 && $adjust <= 0.009 && $give <= 0.009 && $oldAsk <= 0.009)) {
    $back('আজকের বেতন, অতিরিক্ত বা এডভান্স দিন।');
}
try {
    $account = ame_require_selectable_account($db, $accId);
} catch (Throwable $e) {
    $back($e->getMessage());
}
ame_emp_hub_schema($db);
$emp = ame_emp_hub_row($db, $empId, false);
if (!$emp) {
    $back('কর্মী পাওয়া যায়নি।');
}
$statement = ame_worker_desk_statement($db, $empId);
$month = substr($payDate, 0, 7);
$method = ame_worker_pay_method($account);
$mode = (string)($statement['mode'] ?? 'percentage');
$note = trim('আজকের বেতন · ' . $method . ($notes !== '' ? ' · ' . $notes : ''));
$zero = 0.0;
$zeroI = 0;
$status = 'paid';

if (!function_exists('ame_worker_pay_park_excess_drafts')) {
    /**
     * সমন্বয়ের বেশি খোলা অগ্রিম বাতিল করে না। বাকি অংশ ড্রাফটই থাকে।
     */
    function ame_worker_pay_park_excess_drafts(mysqli $db, int $empId, float $fit, int $accountId, string $date, int $preferId = 0): void
    {
        $pack = ame_employee_draft_rows($db, $empId);
        $rows = $pack['rows'] ?? [];
        if ($preferId > 0 && count($rows) > 1) {
            usort($rows, static function ($a, $b) use ($preferId): int {
                $ap = (int)($a['id'] ?? 0) === $preferId ? 0 : 1;
                $bp = (int)($b['id'] ?? 0) === $preferId ? 0 : 1;
                if ($ap !== $bp) {
                    return $ap <=> $bp;
                }
                return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
            });
        }
        $running = 0.0;
        $park = $db->prepare("UPDATE employee_advances SET status = 'wdhold' WHERE id = ? AND employee_id = ? AND status = 'draft'");
        $shrink = $db->prepare("UPDATE employee_advances SET amount = ? WHERE id = ? AND employee_id = ? AND status = 'draft'");
        if (!$park || !$shrink) {
            throw new RuntimeException('অগ্রিম সমন্বয় খোলা যায়নি।');
        }
        $parkId = 0;
        $parkEmp = $empId;
        $shrinkAmt = 0.0;
        $shrinkId = 0;
        $shrinkEmp = $empId;
        $park->bind_param('ii', $parkId, $parkEmp);
        $shrink->bind_param('dii', $shrinkAmt, $shrinkId, $shrinkEmp);
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $amt = round((float)$row['amount'], 2);
            if ($amt <= 0.009 || $id <= 0) {
                continue;
            }
            if ($running >= $fit - 0.009) {
                $parkId = $id;
                $park->execute();
                continue;
            }
            $room = round($fit - $running, 2);
            if ($room <= 0.009) {
                $parkId = $id;
                $park->execute();
                continue;
            }
            if ($amt <= $room + 0.009) {
                $running = round($running + $amt, 2);
                continue;
            }
            $shrinkAmt = $room;
            $shrinkId = $id;
            $shrink->execute();
            $rest = round($amt - $room, 2);
            if ($rest > 0.009) {
                $held = ame_post_employee_advance($db, [
                    'employee_id' => $empId,
                    'account_id' => (int)($row['account_id'] ?? 0) > 0 ? (int)$row['account_id'] : $accountId,
                    'amount' => $rest,
                    'kind' => 'draft',
                    'as_draft' => true,
                    'advance_date' => (string)($row['advance_date'] ?? $date),
                    'note' => 'এডভান্সের বাকি অংশ',
                    'in_transaction' => true,
                ]);
                $holdId = (int)($held['id'] ?? 0);
                if ($holdId > 0) {
                    $parkId = $holdId;
                    $park->execute();
                }
            }
            $running = round($running + $room, 2);
        }
        $park->close();
        $shrink->close();
    }
}

$db->begin_transaction();
try {
    $cashGive = 0.0;
    $giveId = 0;
    if ($give > 0.009 && function_exists('ame_post_employee_advance')) {
        $given = ame_post_employee_advance($db, [
            'employee_id' => $empId,
            'account_id' => $accId,
            'amount' => $give,
            'kind' => $adjust > 0.009 ? 'draft' : 'give',
            'as_draft' => $adjust > 0.009,
            'advance_date' => $payDate,
            'note' => 'দিনের এডভান্স',
            'in_transaction' => true,
        ]);
        $giveId = (int)($given['id'] ?? 0);
        if ($adjust <= 0.009) {
            $cashGive = $give;
            $note = trim($note . ' · এডভান্স ৳' . number_format($give, 2));
        }
    }

    $settled = 0.0;
    if ($adjust > 0.009 && function_exists('ame_employee_draft_rows') && function_exists('ame_close_employee_advance_drafts')) {
        $pack = ame_employee_draft_rows($db, $empId);
        $draftTotal = round((float)($pack['total'] ?? 0), 2);
        $fit = round(min($adjust, $draftTotal), 2);
        if ($fit > 0.009) {
            ame_worker_pay_park_excess_drafts($db, $empId, $fit, $accId, $payDate, $giveId);
            $leftPack = ame_employee_draft_rows($db, $empId);
            $leftTotal = round((float)($leftPack['total'] ?? 0), 2);
            if ($leftTotal > $fit + 0.05) {
                throw new RuntimeException('অগ্রিম সমন্বয়ের অঙ্ক মিলল না।');
            }
            $closed = ame_close_employee_advance_drafts($db, $empId, 'settle', $accId, [
                'in_transaction' => true,
                'cap' => $fit,
            ]);
            $settled = round((float)($closed['settled'] ?? 0), 2);
            $restore = $db->prepare("UPDATE employee_advances SET status = 'draft' WHERE employee_id = ? AND status = 'wdhold'");
            if ($restore) {
                $restore->bind_param('i', $empId);
                $restore->execute();
                $restore->close();
            }
            if ($settled > 0.009) {
                $note = trim($note . ' · অগ্রিম সমন্বয় ৳' . number_format($settled, 2));
            }
        }
    }

    $oldCut = 0.0;
    if ($oldAsk > 0.009) {
        $balStmt = $db->prepare('SELECT COALESCE(advance_balance, 0) AS b FROM employees WHERE id = ? FOR UPDATE');
        $balStmt->bind_param('i', $empId);
        $balStmt->execute();
        $balNow = round((float)($balStmt->get_result()->fetch_assoc()['b'] ?? 0), 2);
        $balStmt->close();
        $closingBefore = round((float)($statement['closing'] ?? 0), 2);
        $room = round(max(0, $closingBefore + $balNow), 2);
        $oldCut = round(min($oldAsk, $balNow, $room), 2);
        if ($oldCut + 0.009 < $oldAsk) {
            throw new RuntimeException($balNow + 0.009 < $oldAsk
                ? 'সাবেক অগ্রিমের চেয়ে বেশি কাটা যাবে না। সাবেক ৳' . number_format($balNow, 2)
                : 'লেজারে কর্মীর পাওনা কম। সর্বোচ্চ ৳' . number_format($oldCut, 2) . ' কাটা যাবে।');
        }
    }

    $snackId = 0;
    $snackAmt = 0.0;
    $cover = round($amount + $settled, 2);
    if ($extra > 0.009 && $cover > 0.009 && function_exists('ame_emp_hub_add_snack')) {
        $snackAmt = round(min($extra, $cover), 2);
        if ($snackAmt > 0.009) {
            $added = ame_emp_hub_add_snack($db, $empId, $payDate, 'extra', $snackAmt, 'পেমেন্টের অতিরিক্ত');
            $snackId = (int)($added['id'] ?? 0);
            $note = trim($note . ' · অতিরিক্ত ৳' . number_format($snackAmt, 2));
        }
    }

    if ($amount <= 0.009 && $settled <= 0.009 && $oldCut <= 0.009) {
        if ($cashGive <= 0.009) {
            throw new RuntimeException('পেমেন্ট সেভ হয়নি।');
        }
        $db->commit();
        header('Location: worker_pay.php?' . http_build_query([
            'emp' => $empId,
            'date' => $payDate,
            'ok' => 'এডভান্স ৳' . number_format($cashGive, 2) . ' দেওয়া হয়েছে। বেতন থেকে কাটেনি।',
        ]));
        exit;
    }

    $advCutTotal = round($settled + $oldCut, 2);
    if ($oldCut > 0.009) {
        $note = trim($note . ' · সাবেক অগ্রিম কাটা ৳' . number_format($oldCut, 2));
    }
    $stmt = $db->prepare(
        "INSERT INTO salaries (employee_id, amount, month_year, account_id, payment_date, base_amount, bonus_amount, deduction_amount, snacks_amount, commission_amount, payment_method, reference_no, notes, status, advance_cut, pay_mode_snapshot, pay_rule_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException('বেতনের এন্ট্রি খোলা যায়নি।');
    }
    $ref = '';
    $stmt->bind_param(
        'idsisdddddssssdsi',
        $empId,
        $amount,
        $month,
        $accId,
        $payDate,
        $zero,
        $zero,
        $zero,
        $zero,
        $amount,
        $method,
        $ref,
        $note,
        $status,
        $advCutTotal,
        $mode,
        $zeroI
    );
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException($err !== '' ? $err : 'পেমেন্ট সেভ হয়নি।');
    }
    $salaryId = (int)$db->insert_id;
    $stmt->close();
    if ($oldCut > 0.009) {
        ame_post_employee_advance($db, [
            'employee_id' => $empId,
            'account_id' => $accId,
            'amount' => $oldCut,
            'kind' => 'salary_cut',
            'advance_date' => $payDate,
            'note' => 'বেতনে সমন্বয় (সাবেক অগ্রিম) #' . $salaryId,
            'in_transaction' => true,
        ]);
    }
    if ($amount > 0.009) {
        account_debit(
            $accId,
            $amount,
            'salary_payment',
            $salaryId,
            'আজকের বেতন: ' . (string)$emp['name'] . ' · ' . $payDate
        );
    }
    if ($snackId > 0 && function_exists('ame_emp_hub_mark_snack_paid')) {
        ame_emp_hub_mark_snack_paid($db, [$snackId], $salaryId);
    }
    $db->commit();
    header('Location: worker_success.php?' . http_build_query([
        'pay' => $salaryId,
    ]));
    exit;
} catch (Throwable $e) {
    $db->rollback();
    $back($e->getMessage());
}
