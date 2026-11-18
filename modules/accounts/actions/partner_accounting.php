<?php
require_once '../../../includes/config.php';
require_once '../../../includes/partnership_functions.php';
require_once '../../../includes/partner_accounting.php';

pa_guard();
require_post();
csrf_verify();
ensure_partner_accounting_schema($db);

$action = preg_replace('/[^a-z_]/', '', (string)($_POST['action'] ?? ''));
$month = pa_month((string)($_POST['month'] ?? date('Y-m')));
$ym = $month['ym'];
$back = '/modules/accounts/partner_accounting.php?month=' . rawurlencode($ym);

function pa_action_back(string $ym, string $tab = 'draw'): string
{
    $tab = in_array($tab, ['draw', 'capital', 'bank', 'stmt'], true) ? $tab : 'draw';
    return '/modules/accounts/partner_accounting.php?month=' . rawurlencode($ym) . '&tab=' . rawurlencode($tab);
}

function pa_go($url, $ok = '', $err = '')
{
    $sep = strpos($url, '?') === false ? '?' : '&';
    if ($ok !== '') {
        $url .= $sep . 'saved=' . rawurlencode($ok);
    } elseif ($err !== '') {
        $url .= $sep . 'err=' . rawurlencode($err);
    }
    header('Location: ' . $url);
    exit;
}

function pa_amount_ok($raw)
{
    if (!is_numeric($raw)) {
        return false;
    }
    $n = (float)$raw;
    return $n > 0 && $n < 1000000000;
}

if (pa_month_locked($db, $ym) && !in_array($action, ['reopen_month', 'save_books_start'], true)) {
    pa_go($back, '', 'closed');
}

try {
    if ($action === 'save_partner') {
        $id = (int)($_POST['partner_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $share = pa_money($_POST['share_percentage'] ?? 0);
        $capital = pa_money($_POST['initial_capital'] ?? 0);
        $status = in_array(($_POST['status'] ?? 'active'), ['active', 'inactive', 'exited'], true)
            ? (string)$_POST['status'] : 'active';
        if ($name === '' || $share < 0 || $share > 100) {
            pa_go($back, '', 'partner');
        }

        $others = 0.0;
        foreach (pa_partners($db, true) as $p) {
            if ((int)$p['id'] === $id) {
                continue;
            }
            $others += (float)$p['share_percentage'];
        }
        if ($status === 'active' && ($others + $share) > 100.05) {
            pa_go($back, '', 'share');
        }

        if ($id > 0) {
            $phone = mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 20);
            $email = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 100);
            $address = mb_substr(trim((string)($_POST['address'] ?? '')), 0, 255);
            $notes = mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 500);
            $types = ['equity', 'profit_share', 'silent'];
            $partner_type = (string)($_POST['partner_type'] ?? 'equity');
            if (!in_array($partner_type, $types, true)) {
                $partner_type = 'equity';
            }
            $join_date = trim((string)($_POST['join_date'] ?? ''));
            $exit_date = trim((string)($_POST['exit_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $join_date)) {
                $join_date = '';
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $exit_date)) {
                $exit_date = '';
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                pa_go($back . '&tab=capital&edit=' . $id, '', 'email');
            }
            $stmt = $db->prepare("UPDATE partners SET name = ?, partner_type = ?, share_percentage = ?, phone = ?, email = ?, address = ?, join_date = NULLIF(?, ''), exit_date = NULLIF(?, ''), status = ?, notes = ?, initial_capital = ?, updated_at = NOW() WHERE id = ?");
            if (!$stmt) {
                pa_go($back, '', 'save');
            }
            $stmt->bind_param('ssdsssssssdi', $name, $partner_type, $share, $phone, $email, $address, $join_date, $exit_date, $status, $notes, $capital, $id);
            $stmt->execute();
            $stmt->close();
            if (isset($_POST['balance']) && is_numeric($_POST['balance']) && abs((float)$_POST['balance']) < 1000000000) {
                $target = pa_money($_POST['balance']);
                $partner = pa_find_partner($db, $id);
                if ($partner) {
                    $current_adj = pa_balance_edit_amount($db, $id, $ym);
                    $book = pa_partner_book($db, $partner, $ym);
                    $base = pa_money((float)$book['total'] - $current_adj);
                    $amount = pa_balance_delta($base, $target);
                    $note = 'ব্যালেন্স সমন্বয়';
                    $uid = (int)($_SESSION['user_id'] ?? 0);
                    $bal = $db->prepare("INSERT INTO partner_acct_balance_edits (partner_id, period_ym, amount, note, created_by) VALUES (?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE amount = VALUES(amount), note = VALUES(note), updated_at = NOW()");
                    if ($bal) {
                        $bal->bind_param('isdsi', $id, $ym, $amount, $note, $uid);
                        $bal->execute();
                        $bal->close();
                    }
                }
            }
            pa_audit($db, 'update', 'partner', $id, $ym, $name . ' ' . $share . '% ' . $phone);
        } else {
            $stmt = $db->prepare("INSERT INTO partners (name, share_percentage, initial_capital, status, partner_type, created_at) VALUES (?,?,?,?, 'equity', NOW())");
            if (!$stmt) {
                pa_go($back, '', 'save');
            }
            $stmt->bind_param('sdds', $name, $share, $capital, $status);
            $stmt->execute();
            $id = (int)$db->insert_id;
            $stmt->close();
            pa_audit($db, 'create', 'partner', $id, $ym, $name);
        }
        log_activity('Partner accounting partner', 'PartnerAccounting', $name);
        if ($id > 0) {
            pa_go($back . '&tab=capital&edit=' . $id, 'partner');
        }
        pa_go($back . '&tab=capital', 'partner');
    }

    if ($action === 'save_balance') {
        $pid = (int)($_POST['partner_id'] ?? 0);
        $partner = pa_find_partner($db, $pid);
        $raw = $_POST['balance'] ?? '';
        $tab = (string)($_POST['tab'] ?? '');
        $tab_q = $tab === 'capital' ? '&tab=capital' : '';
        if (!$partner || !is_numeric($raw) || abs((float)$raw) >= 1000000000) {
            pa_go($back . $tab_q, '', 'balance');
        }
        $target = pa_money($raw);
        $current_adj = pa_balance_edit_amount($db, $pid, $ym);
        $book = pa_partner_book($db, $partner, $ym);
        $base = pa_money((float)$book['total'] - $current_adj);
        $amount = pa_balance_delta($base, $target);
        $note = 'ব্যালেন্স সমন্বয়';
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $db->prepare("INSERT INTO partner_acct_balance_edits (partner_id, period_ym, amount, note, created_by) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE amount = VALUES(amount), note = VALUES(note), updated_at = NOW()");
        if (!$stmt) {
            pa_go($back . $tab_q, '', 'save');
        }
        $stmt->bind_param('isdsi', $pid, $ym, $amount, $note, $uid);
        $stmt->execute();
        $stmt->close();
        pa_audit($db, 'update', 'balance', $pid, $ym, 'target ' . $target . ' adjust ' . $amount);
        log_activity('Partner balance edit', 'PartnerAccounting', (string)$target);
        pa_go($back . $tab_q, 'balance');
    }

    if ($action === 'toggle_ledger_category') {
        $cid = (int)($_POST['category_id'] ?? 0);
        $shared = ((string)($_POST['shared'] ?? '0') === '1') ? 1 : 0;
        if ($cid < 1 || !pa_table_exists($db, 'categories')) {
            pa_go($back, '', 'category');
        }
        if (function_exists('ensure_partnership_schema')) {
            ensure_partnership_schema($db);
        }
        $seen = $db->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
        if (!$seen) {
            pa_go($back, '', 'category');
        }
        $seen->bind_param('i', $cid);
        $seen->execute();
        $exists = $seen->get_result()->fetch_assoc();
        $seen->close();
        if (!$exists) {
            pa_go($back, '', 'category');
        }
        $stmt = $db->prepare('UPDATE categories SET is_partnership_shared = ? WHERE id = ?');
        if (!$stmt) {
            pa_go($back, '', 'category');
        }
        $stmt->bind_param('ii', $shared, $cid);
        $stmt->execute();
        $stmt->close();
        pa_audit($db, 'update', 'category', $cid, $ym, $shared === 1 ? 'পার্টনারদের লাভ' : 'বন্ধ');
        log_activity('Partner ledger category', 'PartnerAccounting', (string)$cid);
        pa_go($back, 'category');
    }

    if ($action === 'add_expense_category') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            pa_go(pa_action_back($ym, 'draw'), '', 'expense_cat');
        }
        try {
            pa_expense_category_create($db, $name);
            pa_audit($db, 'create', 'expense_category', 0, $ym, $name);
            log_activity('Partner expense category', 'PartnerAccounting', $name);
            pa_go(pa_action_back($ym, 'draw'), 'expense_cat');
        } catch (Throwable $e) {
            error_log('add_expense_category: ' . $e->getMessage());
            pa_go(pa_action_back($ym, 'draw'), '', 'expense_cat');
        }
    }

    if ($action === 'remove_expense_category') {
        $cid = (int)($_POST['category_id'] ?? 0);
        if ($cid <= 0) {
            pa_go(pa_action_back($ym, 'draw'), '', 'expense_cat');
        }
        try {
            pa_expense_category_deactivate($db, $cid);
            pa_audit($db, 'update', 'expense_category', $cid, $ym, 'বাতিল');
            pa_go(pa_action_back($ym, 'draw'), 'expense_cat_del');
        } catch (Throwable $e) {
            error_log('remove_expense_category: ' . $e->getMessage());
            pa_go(pa_action_back($ym, 'draw'), '', 'expense_cat');
        }
    }

    if ($action === 'save_expense') {
        $retTab = (string)($_POST['return_tab'] ?? 'draw');
        $expBack = pa_action_back($ym, $retTab);
        if (!pa_amount_ok($_POST['amount'] ?? '')) {
            pa_go($expBack, '', 'expense');
        }
        $amount = pa_money($_POST['amount']);
        $cats = pa_expense_categories($db);
        $cat = trim((string)($_POST['category'] ?? ''));
        if ($cat === '' || !isset($cats[$cat])) {
            pa_go($expBack, '', 'expense_cat_pick');
        }
        $methods = pa_payment_methods();
        $method = (string)($_POST['payment_method'] ?? 'cash');
        if (!isset($methods[$method])) {
            $method = 'cash';
        }
        $cut = static function ($v, $n) {
            $v = trim((string)$v);
            return function_exists('mb_substr') ? mb_substr($v, 0, $n, 'UTF-8') : substr($v, 0, $n);
        };
        $desc = $cut($_POST['description'] ?? '', 255);
        if ($desc === '') {
            $desc = $cats[$cat];
        }
        $payee = $cut($_POST['payee'] ?? '', 120);
        $reference = $cut($_POST['reference'] ?? '', 80);
        $date = function_exists('partnership_valid_date')
            ? partnership_valid_date((string)($_POST['expense_date'] ?? ''))
            : date('Y-m-d');
        if ($date > date('Y-m-d')) {
            pa_go($expBack, '', 'future');
        }
        $note = $cut($_POST['note'] ?? '', 255);
        $token = substr(trim((string)($_POST['idempotency_token'] ?? '')), 0, 80);
        $period = substr($date, 0, 7);
        if (pa_month_locked($db, $period)) {
            pa_go($expBack, '', 'closed');
        }
        $periodBack = pa_action_back($period, $retTab);
        if ($token !== '') {
            $dup = $db->prepare("SELECT id FROM partner_acct_expenses WHERE idempotency_token = ? LIMIT 1");
            if ($dup) {
                $dup->bind_param('s', $token);
                $dup->execute();
                $hit = $dup->get_result()->fetch_assoc();
                $dup->close();
                if ($hit) {
                    pa_go($periodBack, 'expense');
                }
            }
        }
        $receiptPath = null;
        if (!empty($_FILES['receipt']['tmp_name']) && ($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if ((int)($_FILES['receipt']['size'] ?? 0) > 5 * 1024 * 1024) {
                pa_go($expBack, '', 'receipt_size');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['receipt']['tmp_name']);
            $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
            if (!isset($exts[$mime])) {
                pa_go($expBack, '', 'receipt_type');
            }
            $dir = dirname(__DIR__, 3) . '/uploads/partner_expenses';
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                pa_go($expBack, '', 'save');
            }
            $fname = 'pex_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $exts[$mime];
            if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $dir . '/' . $fname)) {
                pa_go($expBack, '', 'save');
            }
            $receiptPath = '/uploads/partner_expenses/' . $fname;
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $voucher = pa_next_expense_voucher($db, $period);
        $stmt = $db->prepare("INSERT INTO partner_acct_expenses (expense_date, category, description, amount, period_ym, reference, note, created_by, voucher_no, payee, payment_method, receipt_path, status, idempotency_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'active',?)");
        if (!$stmt) {
            pa_go($expBack, '', 'save');
        }
        $tokenOrNull = $token !== '' ? $token : null;
        $stmt->bind_param('sssdsssisssss', $date, $cat, $desc, $amount, $period, $reference, $note, $uid, $voucher, $payee, $method, $receiptPath, $tokenOrNull);
        $stmt->execute();
        $eid = (int)$db->insert_id;
        $stmt->close();
        pa_audit($db, 'create', 'expense', $eid, $period, $voucher . ' ' . $desc . ' ' . $amount);
        log_activity('Partner expense', 'PartnerAccounting', $voucher . ' ' . $desc);
        pa_go($periodBack, 'expense');
    }

    if ($action === 'delete_expense' || $action === 'void_expense') {
        $id = (int)($_POST['id'] ?? 0);
        $row = pa_expense_by_id($db, $id);
        if (!$row) {
            pa_go($back, '', 'save');
        }
        $period = (string)($row['period_ym'] ?? $ym);
        if (pa_month_locked($db, $period)) {
            pa_go($back, '', 'closed');
        }
        if (($row['status'] ?? 'active') === 'void') {
            pa_go('/modules/accounts/partner_accounting.php?month=' . rawurlencode($period), 'deleted');
        }
        $reason = trim((string)($_POST['reason'] ?? ''));
        $reason = function_exists('mb_substr') ? mb_substr($reason, 0, 255, 'UTF-8') : substr($reason, 0, 255);
        if ($reason === '') {
            pa_go('/modules/accounts/partner_accounting.php?month=' . rawurlencode($period), '', 'reason');
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $void = $db->prepare("UPDATE partner_acct_expenses SET status = 'void', void_reason = ?, voided_by = ?, voided_at = NOW() WHERE id = ? AND COALESCE(status,'active') <> 'void'");
        $void->bind_param('sii', $reason, $uid, $id);
        $void->execute();
        $void->close();
        pa_audit($db, 'void', 'expense', $id, $period, pa_expense_voucher_label($row) . ' · ' . $reason);
        pa_go('/modules/accounts/partner_accounting.php?month=' . rawurlencode($period), 'deleted');
    }

    if ($action === 'save_payment') {
        if (!pa_amount_ok($_POST['amount'] ?? '')) {
            pa_go($back, '', 'payment');
        }
        $amount = pa_money($_POST['amount']);
        $pid = (int)($_POST['partner_id'] ?? 0);
        if ($pid < 1 || !pa_find_partner($db, $pid)) {
            pa_go($back, '', 'payment');
        }
        $date = function_exists('partnership_valid_date')
            ? partnership_valid_date((string)($_POST['payment_date'] ?? ''))
            : date('Y-m-d');
        $period = preg_match('/^\d{4}-\d{2}$/', (string)($_POST['period_ym'] ?? ''))
            ? (string)$_POST['period_ym'] : substr($date, 0, 7);
        if (pa_month_locked($db, $period)) {
            pa_go($back, '', 'closed');
        }
        $methods = pa_payment_methods();
        $method = (string)($_POST['payment_method'] ?? 'cash');
        if (!isset($methods[$method])) {
            $method = 'cash';
        }
        $ref = trim((string)($_POST['reference'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $db->prepare("INSERT INTO partner_acct_payments (partner_id, payment_date, amount, payment_method, period_ym, reference, note, created_by) VALUES (?,?,?,?,?,?,?,?)");
        if (!$stmt) {
            pa_go($back, '', 'save');
        }
        $stmt->bind_param('isdssssi', $pid, $date, $amount, $method, $period, $ref, $note, $uid);
        $stmt->execute();
        $pay_id = (int)$db->insert_id;
        $stmt->close();
        pa_audit($db, 'create', 'payment', $pay_id, $period, 'partner ' . $pid . ' ' . $amount);
        log_activity('Partner payment', 'PartnerAccounting', (string)$amount);
        pa_go('/modules/accounts/partner_accounting.php?month=' . rawurlencode($period), 'payment');
    }

    if ($action === 'delete_payment') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT period_ym FROM partner_acct_payments WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $period = (string)($row['period_ym'] ?? $ym);
        if (pa_month_locked($db, $period)) {
            pa_go($back, '', 'closed');
        }
        $del = $db->prepare("DELETE FROM partner_acct_payments WHERE id = ?");
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
        pa_audit($db, 'delete', 'payment', $id, $period, 'payment removed');
        pa_go('/modules/accounts/partner_accounting.php?month=' . rawurlencode($period), 'deleted');
    }

    if ($action === 'save_capital') {
        if (!pa_amount_ok($_POST['amount'] ?? '')) {
            pa_go($back . '&tab=capital', '', 'capital');
        }
        $pid = (int)($_POST['partner_id'] ?? 0);
        if ($pid < 1 || !pa_find_partner($db, $pid)) {
            pa_go($back . '&tab=capital', '', 'capital');
        }
        $types = ['investment', 'capital_withdrawal', 'loan_to_business'];
        $type = (string)($_POST['type'] ?? 'investment');
        if (!in_array($type, $types, true)) {
            $type = 'investment';
        }
        $amount = pa_money($_POST['amount']);
        $date = function_exists('partnership_valid_date')
            ? partnership_valid_date((string)($_POST['transaction_date'] ?? ''))
            : date('Y-m-d');
        $note = trim((string)($_POST['note'] ?? ''));
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $db->prepare("INSERT INTO partner_capital_transactions (partner_id, type, amount, transaction_date, note, created_by) VALUES (?,?,?,?,?,?)");
        if (!$stmt) {
            pa_go($back . '&tab=capital', '', 'save');
        }
        $stmt->bind_param('isdssi', $pid, $type, $amount, $date, $note, $uid);
        $stmt->execute();
        $cid = (int)$db->insert_id;
        $stmt->close();
        pa_audit($db, 'create', 'capital', $cid, $ym, $type . ' ' . $amount);
        log_activity('Partner capital', 'PartnerAccounting', $type . ' ' . $amount);
        $tab = $type === 'investment' ? 'capital' : 'draw';
        pa_go($back . '&tab=' . $tab, 'capital');
    }

    if ($action === 'distribute_profit') {
        $cat = trim((string)($_POST['pcat'] ?? ''));
        $names = pa_shared_category_names($db);
        if ($cat !== '' && !in_array($cat, $names, true)) {
            $cat = '';
        }
        $dash = pa_dashboard($db, $ym);
        $view = pa_nestle_view($db, $ym, $cat, $dash['month']);
        $ref = pa_nestle_ref($ym, $cat);
        $methods = pa_payment_methods();
        $method = (string)($_POST['payment_method'] ?? 'cash');
        if (!isset($methods[$method])) {
            $method = 'cash';
        }
        $date = date('Y-m-d');
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $note = 'নিট লাভ বণ্টন' . ($cat !== '' ? ' · ' . $cat : '');
        $posted = 0;
        $cut_pcts = [];
        foreach ($dash['active'] as $row) {
            $cut_pcts[(int)$row['partner']['id']] = pa_money($row['share_pct'] ?? 0);
        }
        $cuts = pa_share_cuts((float)$view['profit'], (float)$view['expense'], $cut_pcts);
        foreach ($dash['active'] as $row) {
            $slice = pa_money($cuts[(int)$row['partner']['id']]['net'] ?? 0);
            $due = pa_money($row['due'] ?? 0);
            $amount = $slice > 0 ? min($slice, $due) : 0.0;
            if ($amount < 0.01) {
                continue;
            }
            $pid = (int)$row['partner']['id'];
            $seen = $db->prepare("SELECT id FROM partner_acct_payments WHERE partner_id = ? AND period_ym = ? AND reference = ? LIMIT 1");
            if (!$seen) {
                continue;
            }
            $seen->bind_param('iss', $pid, $ym, $ref);
            $seen->execute();
            $already = $seen->get_result()->fetch_assoc();
            $seen->close();
            if ($already) {
                continue;
            }
            $stmt = $db->prepare("INSERT INTO partner_acct_payments (partner_id, payment_date, amount, payment_method, period_ym, reference, note, created_by) VALUES (?,?,?,?,?,?,?,?)");
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('isdssssi', $pid, $date, $amount, $method, $ym, $ref, $note, $uid);
            $stmt->execute();
            $pay_id = (int)$db->insert_id;
            $stmt->close();
            pa_audit($db, 'create', 'payment', $pay_id, $ym, 'distribute ' . $pid . ' ' . $amount);
            $posted++;
        }
        log_activity('Partner profit distribute', 'PartnerAccounting', $ref);
        pa_go($back . '&pcat=' . rawurlencode($cat), $posted > 0 ? 'distributed' : '', $posted > 0 ? '' : 'nodue');
    }

    if ($action === 'save_books_start') {
        $date = trim((string)($_POST['start_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            pa_go($back, '', 'start');
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $db->prepare("INSERT INTO partner_acct_books_start (id, start_date, set_at, created_by) VALUES (1, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE start_date = VALUES(start_date), set_at = NOW(), created_by = VALUES(created_by)");
        if (!$stmt) {
            pa_go($back, '', 'start');
        }
        $stmt->bind_param('si', $date, $uid);
        $stmt->execute();
        $stmt->close();
        pa_audit($db, 'start', 'books', 1, $ym, $date);
        $target = (string)($_POST['back'] ?? '') === 'statement'
            ? '/modules/accounts/partner_accounting_statement.php?month=' . rawurlencode($ym)
            : $back;
        pa_go($target, 'start');
    }

    if ($action === 'close_month') {
        if (!pa_is_admin()) {
            pa_go($back, '', 'perm');
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $db->prepare("INSERT INTO partner_acct_months (period_ym, status, closed_by, closed_at) VALUES (?, 'closed', ?, NOW())
            ON DUPLICATE KEY UPDATE status = 'closed', closed_by = VALUES(closed_by), closed_at = NOW()");
        $stmt->bind_param('si', $ym, $uid);
        $stmt->execute();
        $stmt->close();
        pa_audit($db, 'close', 'month', 0, $ym, 'month closed');
        pa_go($back, 'closed');
    }

    if ($action === 'reopen_month') {
        if (!pa_is_admin()) {
            pa_go($back, '', 'perm');
        }
        $stmt = $db->prepare("INSERT INTO partner_acct_months (period_ym, status) VALUES (?, 'open')
            ON DUPLICATE KEY UPDATE status = 'open', closed_by = NULL, closed_at = NULL");
        $stmt->bind_param('s', $ym);
        $stmt->execute();
        $stmt->close();
        pa_audit($db, 'reopen', 'month', 0, $ym, 'month reopened');
        pa_go($back, 'reopened');
    }
} catch (Throwable $e) {
    error_log('partner_accounting action: ' . $e->getMessage());
    pa_go($back, '', 'save');
}

pa_go($back, '', 'save');
