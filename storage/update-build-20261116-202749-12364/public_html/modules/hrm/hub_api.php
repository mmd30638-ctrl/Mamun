<?php
if (is_file(dirname(__DIR__, 2) . '/includes/engineer_bill_lock.php')) {
    require_once dirname(__DIR__, 2) . '/includes/engineer_bill_lock.php';
}
/* 10.98.694 */
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/attendance_functions.php';
require_once '../../includes/staff_duty.php';
require_once '../../includes/employee_hub.php';
if (is_file('../../includes/account_functions.php')) {
    require_once '../../includes/account_functions.php';
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

ame_emp_hub_schema($db);

$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'list');
$isPost = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');

$out = static function (array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

$needPost = static function () use ($isPost, $out): void {
    if (!$isPost) {
        $out(['ok' => false, 'error' => 'শুধু ফর্ম জমা দিয়ে করা যায়।'], 405);
    }
    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf_token']) || $token === '' || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
        $out(['ok' => false, 'error' => 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজ রিফ্রেশ করুন।'], 403);
    }
};

try {
    if ($action === 'list') {
        $data = ame_emp_hub_list($db, $_GET);
        $data['ok'] = true;
        $data['can_finance'] = ame_emp_hub_can_finance($db);
        $data['can_advance'] = ame_emp_hub_can_advance($db);
        $data['accounts'] = ame_emp_hub_can_finance($db) || ame_emp_hub_can_advance($db) ? ame_emp_hub_accounts($db) : [];
        $out($data);
    }

    if ($action === 'attendance') {
        $needPost();
        $on = (string)($_POST['state'] ?? '') === 'on';
        $res = ame_emp_hub_set_attendance(
            $db,
            (int)($_POST['id'] ?? 0),
            $on,
            (string)($_POST['att_date'] ?? '')
        );
        $out($res);
    }

    if ($action === 'save_person') {
        $needPost();
        if (!ame_emp_hub_can_finance($db) && (int)($_POST['id'] ?? 0) <= 0) {
            $out(['ok' => false, 'error' => 'নতুন কর্মী যোগ করার অনুমতি নেই।'], 403);
        }
        $out(ame_emp_hub_save_person($db, $_POST, $_FILES));
    }

    if ($action === 'check_duplicate') {
        $dup = ame_emp_hub_duplicate($db, (string)($_GET['phone'] ?? ''), (string)($_GET['code'] ?? ''), (int)($_GET['id'] ?? 0));
        $out(['ok' => true, 'duplicate' => $dup]);
    }

    if ($action === 'works') {
        $id = (int)($_GET['id'] ?? 0);
        [$from, $to] = ame_emp_hub_date_range((string)($_GET['range'] ?? 'month'), (string)($_GET['from'] ?? ''), (string)($_GET['to'] ?? ''));
        if (!ame_emp_hub_can_finance($db) && ame_emp_hub_self_id($db) !== $id) {
            $out(['ok' => false, 'error' => 'কাজ দেখার অনুমতি নেই।'], 403);
        }
        $out(['ok' => true, 'from' => $from, 'to' => $to, 'works' => ame_emp_hub_works($db, $id, $from, $to)]);
    }

    if ($action === 'work_item') {
        $row = ame_emp_hub_work_row($db, (int)($_GET['work_id'] ?? $_GET['id'] ?? 0));
        if (!$row) {
            $out(['ok' => false, 'error' => 'কাজ পাওয়া যায়নি।'], 404);
        }
        $out(['ok' => true, 'work' => $row]);
    }

    if ($action === 'work') {
        $needPost();
        $out(ame_emp_hub_save_work($db, $_POST));
    }

    if ($action === 'snack') {
        $needPost();
        if (!ame_emp_hub_can_finance($db) && !ame_emp_hub_can_advance($db)) {
            $out(['ok' => false, 'error' => 'নাস্তা এন্ট্রির অনুমতি নেই।'], 403);
        }
        $kind = (string)($_POST['kind'] ?? 'manual');
        $date = (string)($_POST['snack_date'] ?? ame_emp_hub_today());
        $out(ame_emp_hub_add_snack($db, (int)($_POST['id'] ?? 0), $date, $kind, (float)($_POST['amount'] ?? 0), (string)($_POST['note'] ?? '')));
    }

    if ($action === 'advance') {
        $needPost();
        if (!ame_emp_hub_can_advance($db)) {
            $out(['ok' => false, 'error' => 'অগ্রিমের অনুমতি নেই।'], 403);
        }
        $kind = (string)($_POST['kind'] ?? 'draft');
        if (!in_array($kind, ['give', 'collect', 'salary_cut', 'draft'], true)) {
            $kind = 'draft';
        }
        $res = ame_post_employee_advance($db, [
            'employee_id' => (int)($_POST['id'] ?? 0),
            'account_id' => (int)($_POST['account_id'] ?? 0),
            'amount' => (float)($_POST['amount'] ?? 0),
            'kind' => $kind,
            'advance_date' => (string)($_POST['advance_date'] ?? ame_emp_hub_today()),
            'note' => (string)($_POST['note'] ?? ''),
            'idempotency_token' => (string)($_POST['idempotency_token'] ?? ''),
        ]);
        ame_emp_hub_audit($db, (int)($_POST['id'] ?? 0), 'Advance', 'advance', '0', (string)($_POST['amount'] ?? '0'));
        $out(['ok' => true] + $res);
    }

    if ($action === 'drafts') {
        if (!ame_emp_hub_can_advance($db) && !ame_emp_hub_can_finance($db)) {
            $out(['ok' => false, 'error' => 'অগ্রিমের অনুমতি নেই।'], 403);
        }
        $pack = ame_employee_draft_rows($db, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
        $out(['ok' => true, 'rows' => $pack['rows'], 'total' => $pack['total']]);
    }

    if ($action === 'close_drafts') {
        $needPost();
        if (!ame_emp_hub_can_advance($db)) {
            $out(['ok' => false, 'error' => 'অগ্রিমের অনুমতি নেই।'], 403);
        }
        $mode = (string)($_POST['mode'] ?? 'settle');
        $res = ame_close_employee_advance_drafts(
            $db,
            (int)($_POST['id'] ?? 0),
            $mode === 'post' ? 'post' : 'settle',
            (int)($_POST['account_id'] ?? 0)
        );
        $out(['ok' => true] + $res);
    }

    if ($action === 'deposit') {
        $needPost();
        if (!ame_emp_hub_can_finance($db)) {
            $out(['ok' => false, 'error' => 'জমার অনুমতি নেই।'], 403);
        }
        $kind = (string)($_POST['kind'] ?? 'deposit');
        $res = ame_post_employee_shop_deposit($db, [
            'employee_id' => (int)($_POST['id'] ?? 0),
            'account_id' => (int)($_POST['account_id'] ?? 0),
            'amount' => (float)($_POST['amount'] ?? 0),
            'kind' => $kind === 'withdraw' ? 'withdraw' : 'deposit',
            'deposit_date' => (string)($_POST['deposit_date'] ?? ame_emp_hub_today()),
            'note' => (string)($_POST['note'] ?? ''),
            'idempotency_token' => (string)($_POST['idempotency_token'] ?? ''),
        ]);
        ame_emp_hub_audit($db, (int)($_POST['id'] ?? 0), $kind === 'withdraw' ? 'Deposit withdraw' : 'Deposit', 'deposit', '0', (string)($_POST['amount'] ?? '0'));
        $out(['ok' => true] + $res);
    }

    if ($action === 'pay_salary') {
        $needPost();
        if (!ame_emp_hub_can_finance($db)) {
            $out(['ok' => false, 'error' => 'বেতন দেওয়ার অনুমতি নেই।'], 403);
        }
        $empId = (int)($_POST['id'] ?? 0);
        $_POST['employee_id'] = (string)$empId;
        $_POST['from_hub'] = '1';
        $out(['ok' => false, 'error' => 'use_form', 'redirect' => 'process_salary.php']);
    }

    if ($action === 'set_pay_mode') {
        $needPost();
        if (!ame_emp_hub_can_finance($db)) {
            $out(['ok' => false, 'error' => 'বেতনের ধরন বদলানোর অনুমতি নেই।'], 403);
        }
        $id = (int)($_POST['id'] ?? 0);
        $mode = strtolower((string)($_POST['payment_type'] ?? ''));
        if (!in_array($mode, ['daily', 'monthly', 'percentage', 'work', 'mixed'], true)) {
            $out(['ok' => false, 'error' => 'সঠিক বেতনের ধরন নয়।'], 400);
        }
        $emp = ame_emp_hub_row($db, $id, false);
        if (!$emp) {
            $out(['ok' => false, 'error' => 'কর্মী পাওয়া যায়নি।'], 404);
        }
        $salary = (float)($_POST['salary'] ?? $emp['salary'] ?? 0);
        $percent = (float)($_POST['service_share_percent'] ?? $emp['service_share_percent'] ?? 50);
        $effective = (string)($_POST['effective_from'] ?? ame_emp_hub_today());
        $out(ame_emp_hub_save_pay_rule(
            $db,
            $emp,
            $mode,
            $salary,
            $percent,
            $effective,
            (string)($_POST['note'] ?? '')
        ));
    }

    if ($action === 'ledger') {
        $id = (int)($_GET['id'] ?? 0);
        if (!ame_emp_hub_can_finance($db) && ame_emp_hub_self_id($db) !== $id) {
            $out(['ok' => false, 'error' => 'লেজার দেখার অনুমতি নেই।'], 403);
        }
        [$from, $to] = ame_emp_hub_date_range((string)($_GET['range'] ?? 'month'), (string)($_GET['from'] ?? ''), (string)($_GET['to'] ?? ''));
        $out(['ok' => true] + ame_emp_hub_ledger($db, $id, $from, $to));
    }

    if ($action === 'report') {
        $id = (int)($_GET['id'] ?? 0);
        if (!ame_emp_hub_can_finance($db) && ame_emp_hub_self_id($db) !== $id) {
            $out(['ok' => false, 'error' => 'রিপোর্ট দেখার অনুমতি নেই।'], 403);
        }
        $ym = (string)($_GET['month'] ?? substr(ame_emp_hub_today(), 0, 7));
        $range = (string)($_GET['range'] ?? 'month');
        if (!in_array($range, ['today', 'yesterday', 'week', 'month', 'custom'], true)) {
            $range = 'month';
        }
        $out(['ok' => true] + ame_emp_hub_report(
            $db,
            $id,
            $ym,
            $range,
            (string)($_GET['from'] ?? ''),
            (string)($_GET['to'] ?? '')
        ));
    }

    if ($action === 'pending_pay') {
        $id = (int)($_GET['id'] ?? 0);
        if (!ame_emp_hub_can_finance($db) && ame_emp_hub_self_id($db) !== $id) {
            $out(['ok' => false, 'error' => 'বেতন দেখার অনুমতি নেই।'], 403);
        }
        $date = (string)($_GET['date'] ?? ame_emp_hub_today());
        $out(['ok' => true] + ame_emp_hub_pending_pay_preview($db, $id, $date));
    }

    if ($action === 'person') {
        $id = (int)($_GET['id'] ?? 0);
        $row = ame_emp_hub_row($db, $id, !empty($_GET['deleted']));
        if (!$row) {
            $out(['ok' => false, 'error' => 'কর্মী পাওয়া যায়নি।'], 404);
        }
        $out([
            'ok' => true,
            'person' => ame_emp_hub_enrich_person($db, $row),
            'pay_history' => ame_emp_hub_pay_history($db, $row),
            'accounts' => ame_emp_hub_accounts($db),
        ]);
    }

    if ($action === 'search_party') {
        $out(['ok' => true] + ame_emp_hub_search_party($db, (string)($_GET['q'] ?? '')));
    }

    if ($action === 'box') {
        $id = (int)($_GET['id'] ?? 0);
        $box = (string)($_GET['box'] ?? '');
        if (!ame_emp_hub_can_finance($db) && !ame_emp_hub_can_advance($db) && ame_emp_hub_self_id($db) !== $id) {
            $out(['ok' => false, 'error' => 'এই বক্স দেখার অনুমতি নেই।'], 403);
        }
        $out(ame_emp_hub_box_state($db, $id, $box));
    }

    if ($action === 'adjust_box') {
        $needPost();
        if (!ame_emp_hub_can_finance($db) && !ame_emp_hub_can_advance($db)) {
            $out(['ok' => false, 'error' => 'টাকা সমন্বয়ের অনুমতি নেই।'], 403);
        }
        $out(ame_emp_hub_adjust_box($db, $_POST));
    }

    if ($action === 'money_entries') {
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!ame_emp_hub_can_finance($db) && !ame_emp_hub_can_advance($db) && ame_emp_hub_self_id($db) !== $id) {
            $out(['ok' => false, 'error' => 'এন্ট্রি দেখার অনুমতি নেই।'], 403);
        }
        $filter = (string)($_GET['kind'] ?? $_GET['filter'] ?? '');
        $out(['ok' => true, 'entries' => ame_emp_hub_money_entries($db, $id, $filter)]);
    }

    if ($action === 'delete_money') {
        $needPost();
        if (!ame_emp_hub_can_finance($db) && !ame_emp_hub_can_advance($db)) {
            $out(['ok' => false, 'error' => 'এন্ট্রি মুছার অনুমতি নেই।'], 403);
        }
        $kind = (string)($_POST['kind'] ?? '');
        $entryId = (int)($_POST['entry_id'] ?? $_POST['entry'] ?? 0);
        $out(ame_emp_hub_delete_money($db, $kind, $entryId, (int)($_POST['id'] ?? 0), trim((string)($_POST['reason'] ?? ''))));
    }

    if ($action === 'delete') {
        $needPost();
        if (!ame_emp_hub_can_delete($db)) {
            $out(['ok' => false, 'error' => 'চিরতরে মুছার অনুমতি নেই।'], 403);
        }
        $id = (int)($_POST['id'] ?? 0);
        $emp = ame_emp_hub_row($db, $id, true);
        if (!$emp) {
            $out(['ok' => false, 'error' => 'কর্মী পাওয়া যায়নি।'], 404);
        }
        $err = function_exists('ame_staff_hard_delete') ? ame_staff_hard_delete($db, $id) : 'ডিলিট সিস্টেম পাওয়া যায়নি।';
        if ($err) {
            $out(['ok' => false, 'error' => $err], 400);
        }
        ame_emp_hub_audit($db, $id, 'চিরতরে মুছা', 'profile', (string)($emp['name'] ?? ''), '', (string)($emp['name'] ?? ''));
        $out([
            'ok' => true,
            'message' => 'কর্মী, ইউজার ও ইঞ্জিনিয়ার প্রজেক্ট থেকে চিরতরে মুছে গেছে। বিক্রয় ও বেতনের খাতা আছে।',
        ]);
    }

    $out(['ok' => false, 'error' => 'Unknown action'], 400);
} catch (Throwable $e) {
    $out(['ok' => false, 'error' => $e->getMessage()], 400);
}
