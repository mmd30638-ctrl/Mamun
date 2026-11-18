<?php
/* 10.98.688 */
/**
 * এক পাতার ব্যবসার ঋণ। আসল ও লাভ আলাদা।
 * ঋণ নিলে অ্যাকাউন্টে জমা, পরিশোধে অ্যাকাউন্ট থেকে কাটা।
 */
declare(strict_types=1);

function ame_biz_loan_ensure(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS biz_loans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_type VARCHAR(20) NOT NULL DEFAULT 'bank',
        lender_name VARCHAR(160) NOT NULL,
        address VARCHAR(255) NULL,
        mobile VARCHAR(40) NULL,
        principal DECIMAL(15,2) NOT NULL DEFAULT 0,
        profit_rate DECIMAL(8,3) NOT NULL DEFAULT 0,
        profit_note VARCHAR(80) NULL,
        repay_type VARCHAR(20) NOT NULL DEFAULT 'monthly',
        installment_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        due_date DATE NULL,
        account_id INT NOT NULL DEFAULT 0,
        loan_date DATE NOT NULL,
        principal_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
        interest_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'running',
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_lender (lender_name),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS biz_loan_txns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_id INT NOT NULL,
        txn_date DATE NOT NULL,
        kind VARCHAR(20) NOT NULL,
        principal_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        interest_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        total DECIMAL(15,2) NOT NULL DEFAULT 0,
        account_id INT NOT NULL DEFAULT 0,
        balance_after DECIMAL(15,2) NOT NULL DEFAULT 0,
        note VARCHAR(255) NULL,
        voided TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_loan (loan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    ame_biz_loan_add_column($db, 'biz_loans', 'savings_amount', 'DECIMAL(15,2) NOT NULL DEFAULT 0');
    ame_biz_loan_add_column($db, 'biz_loans', 'savings_opening', 'DECIMAL(15,2) NOT NULL DEFAULT 0');
    ame_biz_loan_add_column($db, 'biz_loan_txns', 'savings_part', 'DECIMAL(15,2) NOT NULL DEFAULT 0');
}

function ame_biz_loan_add_column(mysqli $db, string $table, string $column, string $def): void
{
    $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    $has = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    if (!$has) {
        $db->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$def}");
    }
}

function ame_biz_loan_taka(float $n): string
{
    return '৳' . number_format($n, 2);
}

function ame_biz_loan_source_label(string $type): string
{
    return [
        'bank' => 'ব্যাংক',
        'ngo' => 'এনজিও',
        'person' => 'ব্যক্তি',
        'other' => 'অন্যান্য',
    ][$type] ?? 'অন্যান্য';
}

function ame_biz_loan_repay_label(string $type): string
{
    return [
        'monthly' => 'মাসিক কিস্তি',
        'profit_only' => 'মাসিক শুধু লাভ',
        'lumpsum' => 'এককালীন',
        'custom' => 'নিজের মতো',
    ][$type] ?? 'নিজের মতো';
}

function ame_biz_loan_row(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM biz_loans WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function ame_biz_loan_recompute(mysqli $db, int $loanId): void
{
    $loan = ame_biz_loan_row($db, $loanId);
    if (!$loan) {
        return;
    }
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(principal_part),0) p, COALESCE(SUM(interest_part),0) i
         FROM biz_loan_txns
         WHERE loan_id = ? AND voided = 0 AND kind <> 'receive'"
    );
    $stmt->bind_param('i', $loanId);
    $stmt->execute();
    $sum = $stmt->get_result()->fetch_assoc() ?: ['p' => 0, 'i' => 0];
    $stmt->close();
    $paidP = round((float)$sum['p'], 2);
    $paidI = round((float)$sum['i'], 2);
    $left = round((float)$loan['principal'] - $paidP, 2);
    if ($left < 0) {
        $left = 0;
    }
    $status = $left <= 0.009 ? 'paid' : 'running';
    $up = $db->prepare('UPDATE biz_loans SET principal_paid = ?, interest_paid = ?, status = ? WHERE id = ?');
    $up->bind_param('ddsi', $paidP, $paidI, $status, $loanId);
    $up->execute();
    $up->close();
    $find = $db->prepare('SELECT id FROM biz_loan_txns WHERE loan_id = ? AND voided = 0 ORDER BY txn_date DESC, id DESC LIMIT 1');
    $find->bind_param('i', $loanId);
    $find->execute();
    $last = $find->get_result()->fetch_assoc();
    $find->close();
    if ($last) {
        $lastId = (int)$last['id'];
        $bal = $db->prepare('UPDATE biz_loan_txns SET balance_after = ? WHERE id = ?');
        $bal->bind_param('di', $left, $lastId);
        $bal->execute();
        $bal->close();
    }
}

function ame_biz_loan_remaining(array $loan): float
{
    return round(max(0, (float)$loan['principal'] - (float)$loan['principal_paid']), 2);
}

function ame_biz_loan_post_cash(mysqli $db, int $accountId, float $amount, string $direction, int $txnId, string $note): void
{
    if (!function_exists('account_credit')) {
        require_once __DIR__ . '/account_functions.php';
    }
    if ($direction === 'in') {
        account_credit($accountId, $amount, 'biz_loan_in', $txnId, $note);
        return;
    }
    account_debit($accountId, $amount, 'biz_loan_out', $txnId, $note);
}

function ame_biz_loan_reverse_txn(mysqli $db, int $txnId, string $why): void
{
    if (!function_exists('reverse_ledger_entry')) {
        require_once __DIR__ . '/account_functions.php';
    }
    $stmt = $db->prepare(
        "SELECT id FROM account_ledger
         WHERE ref_id = ? AND ref_type IN ('biz_loan_in','biz_loan_out')
         ORDER BY id ASC"
    );
    $stmt->bind_param('i', $txnId);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int)$row['id'];
    }
    $stmt->close();
    foreach ($ids as $ledgerId) {
        reverse_ledger_entry($ledgerId, $why);
    }
    $void = $db->prepare('UPDATE biz_loan_txns SET voided = 1 WHERE id = ? AND voided = 0');
    $void->bind_param('i', $txnId);
    $void->execute();
    $void->close();
}

function ame_biz_loan_add_txn(
    mysqli $db,
    int $loanId,
    string $date,
    string $kind,
    float $principalPart,
    float $interestPart,
    float $total,
    int $accountId,
    string $note,
    int $userId,
    float $savingsPart = 0.0
): int {
    $left = 0.0;
    $stmt = $db->prepare(
        'INSERT INTO biz_loan_txns (loan_id, txn_date, kind, principal_part, interest_part, savings_part, total, account_id, balance_after, note, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issddddidsi', $loanId, $date, $kind, $principalPart, $interestPart, $savingsPart, $total, $accountId, $left, $note, $userId);
    $stmt->execute();
    $id = (int)$db->insert_id;
    $stmt->close();
    return $id;
}

function ame_biz_loan_create(mysqli $db, array $in, int $userId): int
{
    $name = trim((string)($in['lender_name'] ?? ''));
    $principal = round((float)($in['principal'] ?? 0), 2);
    $accountId = (int)($in['account_id'] ?? 0);
    $date = (string)($in['loan_date'] ?? '');
    if ($name === '' || $principal <= 0 || $accountId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('ঋণদাতার নাম, আসল, অ্যাকাউন্ট ও তারিখ দিন।');
    }
    $type = (string)($in['source_type'] ?? 'bank');
    if (!in_array($type, ['bank', 'ngo', 'person', 'other'], true)) {
        $type = 'other';
    }
    $repay = (string)($in['repay_type'] ?? 'monthly');
    if (!in_array($repay, ['monthly', 'profit_only', 'lumpsum', 'custom'], true)) {
        $repay = 'custom';
    }
    $address = trim((string)($in['address'] ?? ''));
    $mobile = trim((string)($in['mobile'] ?? ''));
    $rate = round((float)($in['profit_rate'] ?? 0), 3);
    $profitNote = trim((string)($in['profit_note'] ?? ''));
    $installment = round(max(0, (float)($in['installment_amount'] ?? 0)), 2);
    $due = trim((string)($in['due_date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
        $due = null;
    }
    $savingsAmount = round(max(0, (float)($in['savings_amount'] ?? 0)), 2);
    $savingsOpening = round(max(0, (float)($in['savings_opening'] ?? 0)), 2);
    $db->begin_transaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO biz_loans (source_type, lender_name, address, mobile, principal, profit_rate, profit_note, repay_type, installment_amount, due_date, account_id, loan_date, created_by, savings_amount, savings_opening)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'ssssddssdsisidd',
            $type,
            $name,
            $address,
            $mobile,
            $principal,
            $rate,
            $profitNote,
            $repay,
            $installment,
            $due,
            $accountId,
            $date,
            $userId,
            $savingsAmount,
            $savingsOpening
        );
        $stmt->execute();
        $loanId = (int)$db->insert_id;
        $stmt->close();
        $txnId = ame_biz_loan_add_txn($db, $loanId, $date, 'receive', 0, 0, $principal, $accountId, 'ঋণ গ্রহণ · ' . $name, $userId);
        ame_biz_loan_post_cash($db, $accountId, $principal, 'in', $txnId, 'ব্যবসার ঋণ গ্রহণ: ' . $name);
        ame_biz_loan_recompute($db, $loanId);
        $db->commit();
        return $loanId;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function ame_biz_loan_update_meta(mysqli $db, int $loanId, array $in): void
{
    $loan = ame_biz_loan_row($db, $loanId);
    if (!$loan) {
        throw new InvalidArgumentException('ঋণ পাওয়া যায়নি।');
    }
    $name = trim((string)($in['lender_name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('ঋণদাতার নাম দিন।');
    }
    $type = (string)($in['source_type'] ?? $loan['source_type']);
    if (!in_array($type, ['bank', 'ngo', 'person', 'other'], true)) {
        $type = 'other';
    }
    $repay = (string)($in['repay_type'] ?? $loan['repay_type']);
    if (!in_array($repay, ['monthly', 'profit_only', 'lumpsum', 'custom'], true)) {
        $repay = 'custom';
    }
    $address = trim((string)($in['address'] ?? ''));
    $mobile = trim((string)($in['mobile'] ?? ''));
    $rate = round((float)($in['profit_rate'] ?? 0), 3);
    $profitNote = trim((string)($in['profit_note'] ?? ''));
    $installment = round(max(0, (float)($in['installment_amount'] ?? 0)), 2);
    $due = trim((string)($in['due_date'] ?? ''));
    $dueSql = preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null;
    $savingsAmount = round(max(0, (float)($in['savings_amount'] ?? 0)), 2);
    $savingsOpening = round(max(0, (float)($in['savings_opening'] ?? 0)), 2);
    $stmt = $db->prepare(
        'UPDATE biz_loans SET source_type=?, lender_name=?, address=?, mobile=?, profit_rate=?, profit_note=?, repay_type=?, installment_amount=?, due_date=?, savings_amount=?, savings_opening=? WHERE id=?'
    );
    $stmt->bind_param('ssssdssdsddi', $type, $name, $address, $mobile, $rate, $profitNote, $repay, $installment, $dueSql, $savingsAmount, $savingsOpening, $loanId);
    $stmt->execute();
    $stmt->close();
}

function ame_biz_loan_pay(mysqli $db, int $loanId, array $in, int $userId): void
{
    $loan = ame_biz_loan_row($db, $loanId);
    if (!$loan) {
        throw new InvalidArgumentException('ঋণ পাওয়া যায়নি।');
    }
    $kind = (string)($in['kind'] ?? 'principal');
    if (!in_array($kind, ['principal', 'interest', 'installment', 'savings', 'savings_out'], true)) {
        throw new InvalidArgumentException('পেমেন্টের ধরন বাছুন।');
    }
    $isSavingsKind = in_array($kind, ['savings', 'savings_out'], true);
    if ((string)$loan['status'] === 'paid' && !$isSavingsKind) {
        throw new InvalidArgumentException('এই ঋণে আর পেমেন্ট নেই।');
    }
    $date = (string)($in['txn_date'] ?? '');
    $accountId = (int)($in['account_id'] ?? 0);
    $amount = round((float)($in['amount'] ?? 0), 2);
    if ($amount <= 0 || $accountId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('তারিখ, অ্যাকাউন্ট ও টাকা দিন।');
    }
    $left = ame_biz_loan_remaining($loan);
    $principalPart = 0.0;
    $interestPart = 0.0;
    $savingsPart = 0.0;
    $direction = 'out';
    if ($kind === 'principal') {
        if ($amount - $left > 0.009) {
            throw new InvalidArgumentException('আসল বকেয়ার বেশি দেওয়া যাবে না। বাকি ' . ame_biz_loan_taka($left));
        }
        $principalPart = $amount;
    } elseif ($kind === 'interest') {
        $interestPart = $amount;
    } elseif ($kind === 'savings') {
        $savingsPart = $amount;
    } elseif ($kind === 'savings_out') {
        $have = ame_biz_loan_savings_balance($db, $loan);
        if ($amount - $have > 0.009) {
            throw new InvalidArgumentException('জমা সঞ্চয়ের বেশি ফেরত নেওয়া যাবে না। জমা আছে ' . ame_biz_loan_taka($have));
        }
        $direction = 'in';
    } else {
        $askedP = trim((string)($in['principal_part'] ?? ''));
        $askedI = trim((string)($in['interest_part'] ?? ''));
        $askedS = trim((string)($in['savings_part'] ?? ''));
        $defaultS = round((float)($loan['savings_amount'] ?? 0), 2);
        if ($askedS === '' && $defaultS > 0) {
            $askedS = (string)min($defaultS, $amount);
        }
        if ($askedP === '' && $askedI === '' && $askedS === '') {
            $principalPart = 0.0;
            $interestPart = 0.0;
        } else {
            $interestPart = $askedI === '' ? 0.0 : round(max(0, (float)$askedI), 2);
            $savingsPart = $askedS === '' ? 0.0 : round(max(0, (float)$askedS), 2);
            if ($askedP === '') {
                $principalPart = round($amount - $interestPart - $savingsPart, 2);
                if ($principalPart < 0) {
                    throw new InvalidArgumentException('লাভ ও সঞ্চয় মিলিয়ে মোট টাকার বেশি হয়ে গেছে।');
                }
            } else {
                $principalPart = round((float)$askedP, 2);
            }
            if (abs(($principalPart + $interestPart + $savingsPart) - $amount) > 0.05) {
                throw new InvalidArgumentException('কিস্তির আসল, লাভ ও সঞ্চয় মিলিয়ে মোট টাকার সমান হতে হবে।');
            }
            if ($principalPart - $left > 0.009) {
                throw new InvalidArgumentException('কিস্তির আসল বকেয়ার বেশি। বাকি আসল ' . ame_biz_loan_taka($left));
            }
        }
    }
    $note = trim((string)($in['note'] ?? ''));
    $db->begin_transaction();
    try {
        $txnId = ame_biz_loan_add_txn($db, $loanId, $date, $kind, $principalPart, $interestPart, $amount, $accountId, $note, $userId, $savingsPart);
        if ($direction === 'in') {
            ame_biz_loan_post_cash($db, $accountId, $amount, 'in', $txnId, 'ব্যবসার ঋণ সঞ্চয় ফেরত: ' . (string)$loan['lender_name']);
        } elseif ($kind === 'savings') {
            ame_biz_loan_post_cash($db, $accountId, $amount, 'out', $txnId, 'ব্যবসার ঋণ সঞ্চয় জমা: ' . (string)$loan['lender_name']);
        } else {
            ame_biz_loan_post_cash($db, $accountId, $amount, 'out', $txnId, 'ব্যবসার ঋণ পরিশোধ: ' . (string)$loan['lender_name']);
        }
        if (in_array($kind, ['principal', 'installment'], true) && $principalPart > 0 && in_array((string)$loan['repay_type'], ['monthly', 'profit_only'], true) && !empty($loan['due_date'])) {
            $next = date('Y-m-d', strtotime((string)$loan['due_date'] . ' +1 month'));
            $mv = $db->prepare('UPDATE biz_loans SET due_date = ? WHERE id = ?');
            $mv->bind_param('si', $next, $loanId);
            $mv->execute();
            $mv->close();
        }
        ame_biz_loan_recompute($db, $loanId);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * প্রতিষ্ঠানে এখন জমা সঞ্চয় = আগের জমা + কিস্তি/সঞ্চয়ে জমা − ফেরত নেওয়া।
 */
function ame_biz_loan_savings_balance(mysqli $db, array $loan): float
{
    $loanId = (int)$loan['id'];
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(CASE WHEN kind = 'savings_out' THEN -total ELSE savings_part END),0) s
         FROM biz_loan_txns WHERE loan_id = ? AND voided = 0 AND kind <> 'receive'"
    );
    $stmt->bind_param('i', $loanId);
    $stmt->execute();
    $sum = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0);
    $stmt->close();
    return round((float)($loan['savings_opening'] ?? 0) + $sum, 2);
}

function ame_biz_loan_txn(mysqli $db, int $txnId): ?array
{
    $stmt = $db->prepare('SELECT * FROM biz_loan_txns WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $txnId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function ame_biz_loan_void(mysqli $db, int $txnId, int $userId): void
{
    $txn = ame_biz_loan_txn($db, $txnId);
    if (!$txn || (int)$txn['voided'] === 1) {
        throw new InvalidArgumentException('এই এন্ট্রি বাতিল করা যায় না।');
    }
    if ((string)$txn['kind'] === 'receive') {
        $cnt = $db->prepare("SELECT COUNT(*) c FROM biz_loan_txns WHERE loan_id = ? AND voided = 0 AND kind <> 'receive'");
        $loanId = (int)$txn['loan_id'];
        $cnt->bind_param('i', $loanId);
        $cnt->execute();
        $n = (int)($cnt->get_result()->fetch_assoc()['c'] ?? 0);
        $cnt->close();
        if ($n > 0) {
            throw new InvalidArgumentException('পেমেন্ট থাকলে ঋণ গ্রহণ মুছা যায় না। আগে পেমেন্ট বাতিল করুন।');
        }
    }
    $db->begin_transaction();
    try {
        ame_biz_loan_reverse_txn($db, $txnId, 'ঋণ এন্ট্রি বাতিল');
        ame_biz_loan_recompute($db, (int)$txn['loan_id']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    unset($userId);
}

function ame_biz_loan_list(mysqli $db, string $q, string $type): array
{
    $sql = "SELECT l.*, (l.savings_opening + COALESCE((SELECT SUM(CASE WHEN t.kind = 'savings_out' THEN -t.total ELSE t.savings_part END)
            FROM biz_loan_txns t WHERE t.loan_id = l.id AND t.voided = 0 AND t.kind <> 'receive'),0)) AS savings_balance
        FROM biz_loans l WHERE 1=1";
    $types = '';
    $bind = [];
    if ($q !== '') {
        $sql .= ' AND (l.lender_name LIKE ? OR l.mobile LIKE ? OR l.address LIKE ?)';
        $like = '%' . $q . '%';
        $types .= 'sss';
        $bind[] = $like;
        $bind[] = $like;
        $bind[] = $like;
    }
    if (in_array($type, ['bank', 'ngo', 'person', 'other'], true)) {
        $sql .= ' AND l.source_type = ?';
        $types .= 's';
        $bind[] = $type;
    }
    $sql .= ' ORDER BY l.status ASC, l.due_date IS NULL, l.due_date ASC, l.id DESC';
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$bind);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function ame_biz_loan_summary(mysqli $db): array
{
    $row = $db->query(
        "SELECT
            COALESCE(SUM(principal),0) taken,
            COALESCE(SUM(principal - principal_paid),0) left_p,
            COALESCE(SUM(principal_paid),0) paid_p,
            COALESCE(SUM(interest_paid),0) paid_i
         FROM biz_loans"
    )->fetch_assoc() ?: [];
    $sav = $db->query(
        "SELECT COALESCE(SUM(savings_opening),0) o FROM biz_loans"
    )->fetch_assoc() ?: ['o' => 0];
    $savMove = $db->query(
        "SELECT COALESCE(SUM(CASE WHEN kind = 'savings_out' THEN -total ELSE savings_part END),0) s
         FROM biz_loan_txns WHERE voided = 0 AND kind <> 'receive'"
    )->fetch_assoc() ?: ['s' => 0];
    $today = date('Y-m-d');
    $due = $db->query(
        "SELECT COUNT(*) c, COALESCE(SUM(installment_amount),0) s
         FROM biz_loans
         WHERE status = 'running' AND due_date IS NOT NULL AND due_date <= '{$today}'"
    )->fetch_assoc() ?: ['c' => 0, 's' => 0];
    $next = $db->query(
        "SELECT lender_name, due_date, installment_amount
         FROM biz_loans
         WHERE status = 'running' AND due_date IS NOT NULL
         ORDER BY due_date ASC, id ASC LIMIT 1"
    )->fetch_assoc();
    return [
        'taken' => round((float)($row['taken'] ?? 0), 2),
        'left' => round(max(0, (float)($row['left_p'] ?? 0)), 2),
        'paid_p' => round((float)($row['paid_p'] ?? 0), 2),
        'paid_i' => round((float)($row['paid_i'] ?? 0), 2),
        'savings' => round((float)($sav['o'] ?? 0) + (float)($savMove['s'] ?? 0), 2),
        'due_count' => (int)($due['c'] ?? 0),
        'due_sum' => round((float)($due['s'] ?? 0), 2),
        'next' => $next ?: null,
    ];
}

function ame_biz_loan_statement(mysqli $db, int $loanId): array
{
    $stmt = $db->prepare('SELECT * FROM biz_loan_txns WHERE loan_id = ? ORDER BY txn_date ASC, id ASC');
    $stmt->bind_param('i', $loanId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function ame_biz_loan_txn_label(array $t): string
{
    $kind = (string)($t['kind'] ?? '');
    $label = [
        'receive' => 'ঋণ গ্রহণ',
        'principal' => 'আসল পরিশোধ',
        'interest' => 'লাভ/সুদ',
        'installment' => 'কিস্তি',
        'savings' => 'সঞ্চয় জমা',
        'savings_out' => 'সঞ্চয় ফেরত',
    ][$kind] ?? $kind;
    if ($kind === 'installment' && (float)($t['principal_part'] ?? 0) <= 0 && (float)($t['interest_part'] ?? 0) <= 0 && (float)($t['savings_part'] ?? 0) <= 0) {
        $label .= ' (ভাগ হয়নি)';
    }
    if ((int)($t['voided'] ?? 0) === 1) {
        $label .= ' · বাতিল';
    }
    return $label;
}

/**
 * একজন ঋণদাতার খাতা। প্লাস = ঋণ এসেছে, মাইনাস = যা দেওয়া হয়েছে।
 * চলতি = প্লাস − মাইনাস। বাতিল লাইন যোগ-বিয়োগ বদলায় না।
 * বাকি আসল শুধু আসল পরিশোধে কমে; শুধু লাভ দিলে আসল একই থাকে।
 *
 * @param array<int, array<string, mixed>> $txns
 * @return array{lines: array<int, array<string, mixed>>, plus: float, minus: float, net: float}
 */
function ame_biz_loan_book(array $txns, float $savingsOpening = 0.0): array
{
    $plus = 0.0;
    $minus = 0.0;
    $principalIn = 0.0;
    $principalOut = 0.0;
    $savings = round($savingsOpening, 2);
    $lines = [];
    foreach ($txns as $t) {
        $voided = (int)($t['voided'] ?? 0) === 1;
        $kind = (string)($t['kind'] ?? '');
        $total = round((float)($t['total'] ?? 0), 2);
        $linePlus = 0.0;
        $lineMinus = 0.0;
        $savDelta = 0.0;
        if (!$voided) {
            if ($kind === 'receive' || $kind === 'savings_out') {
                $linePlus = $total;
                if ($kind === 'receive') {
                    $principalIn = round($principalIn + $total, 2);
                } else {
                    $savDelta = -$total;
                }
            } else {
                $lineMinus = $total;
                $principalOut = round($principalOut + (float)($t['principal_part'] ?? 0), 2);
                $savDelta = round((float)($t['savings_part'] ?? 0), 2);
            }
        }
        $savings = round($savings + $savDelta, 2);
        $plus = round($plus + $linePlus, 2);
        $minus = round($minus + $lineMinus, 2);
        $lines[] = [
            'txn' => $t,
            'plus' => $linePlus,
            'minus' => $lineMinus,
            'running' => round($plus - $minus, 2),
            'principal_left' => round(max(0, $principalIn - $principalOut), 2),
            'savings_delta' => $savDelta,
            'savings_balance' => $savings,
        ];
    }
    return [
        'lines' => $lines,
        'plus' => round($plus, 2),
        'minus' => round($minus, 2),
        'net' => round($plus - $minus, 2),
        'savings' => $savings,
    ];
}
