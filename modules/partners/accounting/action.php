<?php
require_once '../../../includes/config.php';
require_once '../../../includes/partnership_functions.php';
require_once '../../../includes/partner_accounting_v2.php';
require_once '../../../includes/account_functions.php';

require_permission($db, 'm-acc');
require_post();
csrf_verify();
pav2_ensure_schema($db);

$action = preg_replace('/[^a-z_]/', '', (string)($_POST['action'] ?? ''));
$ym = preg_match('/^\d{4}-\d{2}$/', (string)($_POST['month'] ?? ''))
    ? (string)$_POST['month']
    : date('Y-m');
$back = '/modules/partners/accounting/index.php?month=' . rawurlencode($ym);

function pav2_redirect(string $url, string $key, bool $ok = true): never
{
    header('Location: ' . $url . (str_contains($url, '?') ? '&' : '?') . ($ok ? 'saved=' : 'error=') . rawurlencode($key));
    exit;
}

function pav2_positive($value): float
{
    if (!is_numeric($value) || (float)$value <= 0 || (float)$value >= 1000000000) {
        throw new InvalidArgumentException('সঠিক টাকার পরিমাণ দিন।');
    }
    return round((float)$value, 2);
}

function pav2_assert_open_date(mysqli $db, string $date): string
{
    $date = pav2_date($date);
    if ($date < pav2_cutoff($db)) {
        throw new RuntimeException('নতুন হিসাব শুরুর তারিখের আগের এন্ট্রি দেওয়া যাবে না।');
    }
    if (pav2_month_locked($db, substr($date, 0, 7))) {
        throw new RuntimeException('এই মাস বন্ধ করা হয়েছে। আগে অনুমোদিতভাবে মাস খুলুন।');
    }
    return $date;
}

function pav2_reference(string $raw, string $prefix): string
{
    $raw = preg_replace('/[^A-Za-z0-9_.\/-]/', '-', trim($raw));
    return $raw !== '' ? substr($raw, 0, 90) : ($prefix . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))));
}

try {
    if ($action === 'save_rule') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $p1 = (int)($_POST['partner_one_id'] ?? 0);
        $p2 = (int)($_POST['partner_two_id'] ?? 0);
        $r1 = round((float)($_POST['partner_one_ratio'] ?? 50), 4);
        $r2 = round((float)($_POST['partner_two_ratio'] ?? 50), 4);
        $from = pav2_assert_open_date($db, (string)($_POST['effective_from'] ?? date('Y-m-d')));
        $toRaw = trim((string)($_POST['effective_to'] ?? ''));
        $to = $toRaw === '' ? null : pav2_date($toRaw);
        $terms = mb_substr(trim((string)($_POST['terms'] ?? '')), 0, 500, 'UTF-8');
        if ($productId < 1 || $p1 < 1 || $p2 < 1 || $p1 === $p2 || $r1 <= 0 || $r2 <= 0 || abs(($r1 + $r2) - 100) > 0.001) {
            throw new InvalidArgumentException('দুইজন আলাদা পার্টনার এবং মোট ১০০% অনুপাত দিন।');
        }
        if ($to !== null && $to < $from) {
            throw new InvalidArgumentException('শেষ তারিখ শুরুর তারিখের আগে হতে পারে না।');
        }
        $db->begin_transaction();
        $overlap = $db->prepare(
            "SELECT id FROM partner_v2_product_rules
             WHERE product_id=? AND status='active'
               AND effective_from<=COALESCE(?, '9999-12-31')
               AND COALESCE(effective_to,'9999-12-31')>=?
             LIMIT 1 FOR UPDATE"
        );
        $overlap->bind_param('iss', $productId, $to, $from);
        $overlap->execute();
        if ($overlap->get_result()->num_rows > 0) {
            $overlap->close();
            throw new RuntimeException('এই পণ্যের একই সময়ে আরেকটি সক্রিয় নিয়ম আছে। আগেরটির শেষ তারিখ দিন।');
        }
        $overlap->close();
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $db->prepare(
            'INSERT INTO partner_v2_product_rules
             (product_id,effective_from,effective_to,partner_one_id,partner_one_ratio,partner_two_id,partner_two_ratio,terms,created_by)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param('issididsi', $productId, $from, $to, $p1, $r1, $p2, $r2, $terms, $uid);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        $id = (int)$db->insert_id;
        $stmt->close();
        pav2_audit($db, 'create', 'product_rule', (string)$id, null, $_POST, substr($from, 0, 7));
        $db->commit();
        pav2_redirect($back, 'rule');
    }

    if ($action === 'end_rule') {
        $id = (int)($_POST['id'] ?? 0);
        $end = pav2_assert_open_date($db, (string)($_POST['effective_to'] ?? date('Y-m-d')));
        $db->begin_transaction();
        $stmt = $db->prepare('SELECT * FROM partner_v2_product_rules WHERE id=? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $before = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$before || $end < (string)$before['effective_from']) {
            throw new RuntimeException('নিয়ম বা শেষ তারিখ সঠিক নয়।');
        }
        $stmt = $db->prepare("UPDATE partner_v2_product_rules SET effective_to=?,status='ended',updated_at=NOW() WHERE id=?");
        $stmt->bind_param('si', $end, $id);
        $stmt->execute();
        $stmt->close();
        pav2_audit($db, 'end', 'product_rule', (string)$id, $before, ['effective_to' => $end], substr($end, 0, 7));
        $db->commit();
        pav2_redirect($back, 'rule_end');
    }

    if ($action === 'save_direct_cost') {
        $date = pav2_assert_open_date($db, (string)($_POST['cost_date'] ?? date('Y-m-d')));
        $saleId = (int)($_POST['sale_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $amount = pav2_positive($_POST['amount'] ?? 0);
        $reference = pav2_reference((string)($_POST['reference'] ?? ''), 'DC');
        $description = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 255, 'UTF-8');
        $check = $db->prepare('SELECT id FROM sale_items WHERE sale_id=? AND product_id=? LIMIT 1');
        $check->bind_param('ii', $saleId, $productId);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        if (!$exists) {
            throw new InvalidArgumentException('এই বিক্রয় ইনভয়েসে পণ্যটি নেই।');
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $db->begin_transaction();
        $stmt = $db->prepare(
            'INSERT INTO partner_v2_direct_costs
             (sale_id,product_id,cost_date,amount,description,reference,created_by)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->bind_param('iisdssi', $saleId, $productId, $date, $amount, $description, $reference, $uid);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        $id = (int)$db->insert_id;
        $stmt->close();
        pav2_audit($db, 'create', 'direct_cost', (string)$id, null, [
            'sale_id' => $saleId, 'product_id' => $productId, 'amount' => $amount, 'reference' => $reference,
        ], substr($date, 0, 7));
        $db->commit();
        pav2_redirect('/modules/partners/accounting/index.php?month=' . substr($date, 0, 7), 'direct_cost');
    }

    if ($action === 'save_expense') {
        $date = pav2_assert_open_date($db, (string)($_POST['expense_date'] ?? date('Y-m-d')));
        $amount = pav2_positive($_POST['amount'] ?? 0);
        $categories = pav2_expense_categories();
        $category = (string)($_POST['category'] ?? 'other');
        if (!isset($categories[$category])) {
            $category = 'other';
        }
        $voucher = pav2_reference((string)($_POST['voucher_no'] ?? ''), 'JV');
        $description = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 500, 'UTF-8');
        $payerType = (string)($_POST['payer_type'] ?? 'business') === 'partner' ? 'partner' : 'business';
        $payerId = $payerType === 'partner' ? (int)($_POST['payer_partner_id'] ?? 0) : null;
        $accountId = $payerType === 'business' ? (int)($_POST['account_id'] ?? 0) : null;
        $method = 'personal';
        $idempotencyToken = substr(trim((string)($_POST['idempotency_token'] ?? '')), 0, 80);
        if ($payerType === 'partner' && (!$payerId || !pa_find_partner($db, $payerId))) {
            throw new InvalidArgumentException('টাকা প্রদানকারী পার্টনার নির্বাচন করুন।');
        }
        if ($payerType === 'business') {
            $method = ame_account_payment_method(ame_require_active_account($db, (int)$accountId));
        }
        $receiptPath = null;
        $uploadedAbsolute = null;
        if (!empty($_FILES['receipt']['tmp_name']) && ($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if ((int)($_FILES['receipt']['size'] ?? 0) > 5 * 1024 * 1024) {
                throw new InvalidArgumentException('রসিদ সর্বোচ্চ ৫ MB হতে পারবে।');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['receipt']['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
            if (!isset($extensions[$mime])) {
                throw new InvalidArgumentException('রসিদ JPG, PNG, WEBP বা PDF দিন।');
            }
            $dir = dirname(__DIR__, 3) . '/uploads/partner_expenses';
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('রসিদ ফোল্ডার তৈরি হয়নি।');
            }
            $name = 'receipt_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
            $uploadedAbsolute = $dir . '/' . $name;
            if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $uploadedAbsolute)) {
                throw new RuntimeException('রসিদ সেভ হয়নি।');
            }
            $receiptPath = '/uploads/partner_expenses/' . $name;
        }
        try {
            $db->begin_transaction();
            $existingExpenseId = 0;
            if ($idempotencyToken !== ''
                && !claim_idempotency_token($db, 'partner_v2_expense', $idempotencyToken, $existingExpenseId)) {
                throw new RuntimeException('এই যৌথ খরচ আগেই সংরক্ষিত হয়েছে—ডাবল এন্ট্রি আটকানো হয়েছে।');
            }
            $uid = (int)($_SESSION['user_id'] ?? 0);
            $stmt = $db->prepare(
                "INSERT INTO partner_v2_expenses
                 (expense_date,voucher_no,category,description,amount,payment_method,payer_type,payer_partner_id,account_id,receipt_path,status,approved_by,approved_at,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,'approved',?,NOW(),?)"
            );
            $stmt->bind_param('ssssdssiisii', $date, $voucher, $category, $description, $amount, $method, $payerType, $payerId, $accountId, $receiptPath, $uid, $uid);
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }
            $id = (int)$db->insert_id;
            $stmt->close();
            $ledgerId = null;
            if ($payerType === 'business') {
                $ledgerId = pav2_debit_expense_account($db, $id, (int)$accountId, $amount, $voucher);
            }
            if ($idempotencyToken !== '') {
                finish_idempotency_token($db, 'partner_v2_expense', $idempotencyToken, $id);
            }
            pav2_audit($db, 'create', 'expense', (string)$id, null, [
                'voucher' => $voucher, 'amount' => $amount, 'payer_type' => $payerType,
                'partner_id' => $payerId, 'account_id' => $accountId, 'ledger_id' => $ledgerId,
            ], substr($date, 0, 7));
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            if ($uploadedAbsolute && is_file($uploadedAbsolute)) {
                @unlink($uploadedAbsolute);
            }
            throw $e;
        }
        pav2_redirect('/modules/partners/accounting/index.php?month=' . substr($date, 0, 7), 'expense');
    }

    if ($action === 'void_expense') {
        $id = (int)($_POST['id'] ?? 0);
        $reason = mb_substr(trim((string)($_POST['reason'] ?? 'ভুল এন্ট্রি')), 0, 255, 'UTF-8');
        $db->begin_transaction();
        $stmt = $db->prepare('SELECT * FROM partner_v2_expenses WHERE id=? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $before = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$before) {
            throw new RuntimeException('খরচ পাওয়া যায়নি।');
        }
        if (($before['status'] ?? '') !== 'approved') {
            throw new RuntimeException('খরচটি ইতিমধ্যে void করা হয়েছে।');
        }
        pav2_assert_open_date($db, (string)$before['expense_date']);
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $db->prepare("UPDATE partner_v2_expenses SET status='void',voided_by=?,voided_at=NOW(),void_reason=? WHERE id=? AND status='approved'");
        $stmt->bind_param('isi', $uid, $reason, $id);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('খরচটি আর void করার অবস্থায় নেই।');
        }
        $stmt->close();
        $reversalLedgerId = pav2_reverse_expense_account($db, $before, $reason);
        pav2_audit($db, 'void', 'expense', (string)$id, $before, [
            'reason' => $reason, 'account_id' => $before['account_id'] ?? null,
            'reversal_ledger_id' => $reversalLedgerId,
        ], substr((string)$before['expense_date'], 0, 7));
        $db->commit();
        pav2_redirect($back, 'expense_void');
    }

    if ($action === 'save_movement') {
        $date = pav2_assert_open_date($db, (string)($_POST['movement_date'] ?? date('Y-m-d')));
        $amount = pav2_positive($_POST['amount'] ?? 0);
        $partnerId = (int)($_POST['partner_id'] ?? 0);
        $types = pav2_movement_types();
        $type = (string)($_POST['movement_type'] ?? '');
        if (!$partnerId || !isset($types[$type]) || !pa_find_partner($db, $partnerId)) {
            throw new InvalidArgumentException('পার্টনার ও লেনদেনের ধরন নির্বাচন করুন।');
        }
        $reference = pav2_reference((string)($_POST['reference'] ?? ''), 'PM');
        $accountId = (int)($_POST['account_id'] ?? 0);
        $account = ame_require_active_account($db, $accountId);
        $method = ame_account_payment_method($account);
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 500, 'UTF-8');
        $idempotencyToken = substr(trim((string)($_POST['idempotency_token'] ?? '')), 0, 80);
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $db->begin_transaction();
        $existingMovementId = 0;
        if ($idempotencyToken !== ''
            && !claim_idempotency_token($db, 'partner_v2_movement', $idempotencyToken, $existingMovementId)) {
            throw new RuntimeException('এই পার্টনার লেনদেন আগেই পোস্ট হয়েছে—ডাবল এন্ট্রি আটকানো হয়েছে।');
        }
        $stmt = $db->prepare(
            'INSERT INTO partner_v2_movements
             (partner_id,movement_date,movement_type,amount,payment_method,account_id,reference,note,created_by)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param('issdsissi', $partnerId, $date, $type, $amount, $method, $accountId, $reference, $note, $uid);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        $id = (int)$db->insert_id;
        $stmt->close();
        $ledgerId = pav2_post_movement_account($db, $id, $type, $accountId, $amount, $reference);
        if ($idempotencyToken !== '') {
            finish_idempotency_token($db, 'partner_v2_movement', $idempotencyToken, $id);
        }
        pav2_audit($db, 'create', 'movement', (string)$id, null, [
            'partner_id' => $partnerId, 'type' => $type, 'amount' => $amount,
            'reference' => $reference, 'account_id' => $accountId, 'ledger_id' => $ledgerId,
        ], substr($date, 0, 7));
        $db->commit();
        pav2_redirect('/modules/partners/accounting/index.php?month=' . substr($date, 0, 7), 'movement');
    }

    if ($action === 'reverse_movement') {
        $id = (int)($_POST['id'] ?? 0);
        $reason = mb_substr(trim((string)($_POST['reason'] ?? 'ভুল এন্ট্রি')), 0, 255, 'UTF-8');
        $db->begin_transaction();
        $stmt = $db->prepare('SELECT * FROM partner_v2_movements WHERE id=? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $before = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$before) {
            throw new RuntimeException('লেনদেন পাওয়া যায়নি।');
        }
        if (($before['status'] ?? '') !== 'posted') {
            throw new RuntimeException('লেনদেনটি ইতিমধ্যে রিভার্স করা হয়েছে।');
        }
        pav2_assert_open_date($db, (string)$before['movement_date']);
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $db->prepare("UPDATE partner_v2_movements SET status='reversed',reversed_by=?,reversed_at=NOW(),reversal_reason=? WHERE id=? AND status='posted'");
        $stmt->bind_param('isi', $uid, $reason, $id);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('লেনদেনটি আর রিভার্স করার অবস্থায় নেই।');
        }
        $stmt->close();
        $originalLedgerId = pav2_reverse_movement_account($db, $id, $reason);
        pav2_audit($db, 'reverse', 'movement', (string)$id, $before, [
            'reason' => $reason, 'account_id' => $before['account_id'] ?? null,
            'original_ledger_id' => $originalLedgerId,
        ], substr((string)$before['movement_date'], 0, 7));
        $db->commit();
        pav2_redirect($back, 'movement_reverse');
    }

    if ($action === 'close_month') {
        admin_only();
        pav2_close_month($db, $ym, mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255, 'UTF-8'));
        pav2_redirect($back, 'closed');
    }

    if ($action === 'reopen_month') {
        admin_only();
        $row = pav2_month_row($db, $ym);
        if (!$row || ($row['status'] ?? '') !== 'closed') {
            throw new RuntimeException('বন্ধ মাস পাওয়া যায়নি।');
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $db->prepare("UPDATE partner_v2_months SET status='open',snapshot_json=NULL,snapshot_hash=NULL,reopened_by=?,reopened_at=NOW() WHERE period_ym=?");
        $stmt->bind_param('is', $uid, $ym);
        $stmt->execute();
        $stmt->close();
        pav2_audit($db, 'reopen', 'month', $ym, ['snapshot_hash' => $row['snapshot_hash']], null, $ym);
        pav2_redirect($back, 'reopened');
    }

    throw new InvalidArgumentException('অজানা কাজ।');
} catch (Throwable $e) {
    if ($db->errno === 0) {
        try {
            $db->rollback();
        } catch (Throwable $ignored) {
        }
    } else {
        try {
            $db->rollback();
        } catch (Throwable $ignored) {
        }
    }
    error_log('partner v2 action: ' . $e->getMessage());
    $_SESSION['pav2_error'] = $e->getMessage();
    pav2_redirect($back, 'save', false);
}
