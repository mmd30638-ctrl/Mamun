<?php
/**
 * Business borrowing only. The shop takes money from a bank, NGO, or institution.
 * Customer lending tables are not used and are not changed.
 */

function ame_bloan_ensure(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('bloan')) {
        $done = true;
        return;
    }
    $done = true;
    $db->query("CREATE TABLE IF NOT EXISTS bloan_providers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider_type VARCHAR(30) NOT NULL DEFAULT 'bank',
        name VARCHAR(160) NOT NULL,
        branch VARCHAR(150) NULL,
        officer_name VARCHAR(120) NULL,
        phone VARCHAR(40) NULL,
        account_ref VARCHAR(80) NULL,
        address VARCHAR(255) NULL,
        notes VARCHAR(500) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_provider (provider_type, name, branch)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS bloan_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        app_no VARCHAR(30) NOT NULL,
        provider_id INT NOT NULL,
        apply_date DATE NOT NULL,
        purpose VARCHAR(255) NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        tenure_months INT NOT NULL DEFAULT 1,
        interest_rate DECIMAL(8,3) NOT NULL DEFAULT 0,
        interest_type VARCHAR(20) NOT NULL DEFAULT 'flat',
        frequency VARCHAR(20) NOT NULL DEFAULT 'monthly',
        processing_fee DECIMAL(15,2) NOT NULL DEFAULT 0,
        other_charge DECIMAL(15,2) NOT NULL DEFAULT 0,
        guarantee_note VARCHAR(500) NULL,
        documents_note VARCHAR(500) NULL,
        notes VARCHAR(500) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_app_no (app_no),
        KEY idx_provider (provider_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS bloan_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_no VARCHAR(30) NOT NULL,
        application_id INT NOT NULL,
        provider_id INT NOT NULL,
        approved_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        disbursed_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        interest_rate DECIMAL(8,3) NOT NULL DEFAULT 0,
        interest_type VARCHAR(20) NOT NULL DEFAULT 'flat',
        tenure_months INT NOT NULL DEFAULT 1,
        installment_count INT NOT NULL DEFAULT 1,
        frequency VARCHAR(20) NOT NULL DEFAULT 'monthly',
        installment_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        principal_outstanding DECIMAL(15,2) NOT NULL DEFAULT 0,
        principal_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
        interest_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
        charges_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        disbursed_on DATE NULL,
        account_id INT NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_loan_no (loan_no),
        UNIQUE KEY uniq_application (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS bloan_installments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_id INT NOT NULL,
        installment_no INT NOT NULL,
        due_date DATE NOT NULL,
        principal_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        interest_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        charge_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_due DECIMAL(15,2) NOT NULL DEFAULT 0,
        paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        remaining_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        paid_on DATE NULL,
        account_id INT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'upcoming',
        KEY idx_loan (loan_id),
        KEY idx_due (due_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS bloan_txns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_id INT NOT NULL,
        installment_id INT NULL,
        txn_date DATE NOT NULL,
        kind VARCHAR(30) NOT NULL,
        principal_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        interest_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        charge_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        total DECIMAL(15,2) NOT NULL DEFAULT 0,
        balance_after DECIMAL(15,2) NOT NULL DEFAULT 0,
        method VARCHAR(30) NOT NULL DEFAULT 'bank',
        account_id INT NOT NULL DEFAULT 0,
        reference_no VARCHAR(80) NULL,
        notes VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_loan (loan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS bloan_facilities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider_id INT NOT NULL,
        monthly_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        account_id INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        started_on DATE NOT NULL,
        stopped_on DATE NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_facility_provider (provider_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS bloan_facility_months (
        id INT AUTO_INCREMENT PRIMARY KEY,
        facility_id INT NOT NULL,
        period_key VARCHAR(16) NOT NULL,
        txn_date DATE NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        balance_after DECIMAL(15,2) NOT NULL DEFAULT 0,
        account_id INT NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_bfm_period (facility_id, period_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS bloan_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NULL,
        loan_id INT NULL,
        title VARCHAR(160) NOT NULL,
        file_name VARCHAR(180) NULL,
        notes VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('bloan', true);
    }
}

function ame_bloan_require(mysqli $db): void
{
    if (isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin') {
        return;
    }
    if (function_exists('has_permission') && has_permission($db, 'm-bloan')) {
        return;
    }
    http_response_code(403);
    die(ame_l('ব্যবসার ঋণ দেখার অনুমতি নেই।', 'You do not have permission to view business loans.'));
}

function ame_bloan_taka(float $n): string
{
    return '৳' . number_format($n, 2);
}

function ame_bloan_next_no(mysqli $db, string $table, string $column, string $prefix): string
{
    $year = date('Y');
    $like = $prefix . $year . '-%';
    $stmt = $db->prepare("SELECT $column AS n FROM $table WHERE $column LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $seq = 1;
    if ($row && preg_match('/(\d+)$/', (string)$row['n'], $m)) {
        $seq = ((int)$m[1]) + 1;
    }
    return $prefix . $year . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

function ame_bloan_calc(float $principal, float $rate, int $count, string $frequency): array
{
    $count = max(1, $count);
    $months = $frequency === 'weekly' ? max(1, (int)round($count / 4)) : $count;
    $interest = round($principal * ($rate / 100) * ($months / 12), 2);
    $total = round($principal + $interest, 2);
    return [
        'interest' => $interest,
        'total' => $total,
        'installment' => round($total / $count, 2),
        'principal_per' => round($principal / $count, 2),
        'interest_per' => round($interest / $count, 2),
    ];
}

function ame_bloan_schedule_rows(float $principal, float $rate, int $count, string $frequency, string $interestType): array
{
    $count = max(1, $count);
    $rows = [];
    if ($interestType === 'reducing' && $rate > 0) {
        $r = $frequency === 'weekly' ? ($rate / 100) / 52 : ($rate / 100) / 12;
        $pow = pow(1 + $r, $count);
        $emi = round($principal * $r * $pow / ($pow - 1), 2);
        $bal = $principal;
        for ($i = 1; $i <= $count; $i++) {
            $interest = round($bal * $r, 2);
            $part = round($emi - $interest, 2);
            if ($i === $count || $part > $bal) {
                $part = round($bal, 2);
            }
            $total = round($part + $interest, 2);
            $bal = round($bal - $part, 2);
            $rows[] = ['principal' => $part, 'interest' => $interest, 'total' => $total];
        }
        return $rows;
    }
    $calc = ame_bloan_calc($principal, $rate, $count, $frequency);
    for ($i = 1; $i <= $count; $i++) {
        $p = $calc['principal_per'];
        $n = $calc['interest_per'];
        $t = $calc['installment'];
        if ($i === $count) {
            $p = round($principal - ($calc['principal_per'] * ($count - 1)), 2);
            $n = round($calc['interest'] - ($calc['interest_per'] * ($count - 1)), 2);
            $t = round($p + $n, 2);
        }
        $rows[] = ['principal' => $p, 'interest' => $n, 'total' => $t];
    }
    return $rows;
}

function ame_bloan_make_schedule(mysqli $db, int $loanId, float $principal, float $rate, int $count, string $frequency, string $start, string $interestType = 'flat'): void
{
    $rows = ame_bloan_schedule_rows($principal, $rate, $count, $frequency, $interestType);
    $due = new DateTime($start !== '' ? $start : date('Y-m-d'));
    $ins = $db->prepare('INSERT INTO bloan_installments (loan_id, installment_no, due_date, principal_part, interest_part, charge_part, total_due, paid_amount, remaining_amount, status) VALUES (?,?,?,?,?,0,?,0,?,"upcoming")');
    foreach ($rows as $i => $row) {
        if ($frequency === 'weekly') {
            $due->modify('+7 day');
        } else {
            $due->modify('+1 month');
        }
        $no = $i + 1;
        $date = $due->format('Y-m-d');
        $p = $row['principal'];
        $n = $row['interest'];
        $t = $row['total'];
        $ins->bind_param('iisdddd', $loanId, $no, $date, $p, $n, $t, $t);
        $ins->execute();
    }
    $ins->close();
}

function ame_bloan_refresh(mysqli $db, int $loanId): void
{
    $today = date('Y-m-d');
    $rows = $db->prepare('SELECT id, due_date, paid_amount, total_due, remaining_amount FROM bloan_installments WHERE loan_id = ?');
    $rows->bind_param('i', $loanId);
    $rows->execute();
    $list = $rows->get_result();
    $upd = $db->prepare('UPDATE bloan_installments SET status = ? WHERE id = ?');
    while ($r = $list->fetch_assoc()) {
        $remaining = (float)$r['remaining_amount'];
        $paid = (float)$r['paid_amount'];
        if ($remaining <= 0.009) {
            $status = 'paid';
        } elseif ($paid > 0.009) {
            $status = 'partial';
        } elseif ($r['due_date'] < $today) {
            $status = 'overdue';
        } elseif ($r['due_date'] === $today) {
            $status = 'due';
        } else {
            $status = 'upcoming';
        }
        $id = (int)$r['id'];
        $upd->bind_param('si', $status, $id);
        $upd->execute();
    }
    $upd->close();
    $rows->close();
}

function ame_bloan_is_admin(): bool
{
    if (function_exists('ame_is_admin_like') && ame_is_admin_like()) {
        return true;
    }
    return isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin';
}

function ame_bloan_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function ame_bloan_label(string $status): string
{
    $map = [
        'draft' => ['খসড়া', 'Draft'],
        'applied' => ['আবেদন', 'Applied'],
        'review' => ['পর্যালোচনা', 'Under Review'],
        'approved' => ['অনুমোদিত', 'Approved'],
        'rejected' => ['বাতিল', 'Rejected'],
        'disbursed' => ['টাকা পাওয়া', 'Disbursed'],
        'closed' => ['শেষ', 'Closed'],
        'active' => ['চালু', 'Active'],
        'stopped' => ['বন্ধ', 'Stopped'],
        'upcoming' => ['আসন্ন', 'Upcoming'],
        'due' => ['আজ', 'Due'],
        'partial' => ['আংশিক', 'Partially Paid'],
        'paid' => ['পরিশোধ', 'Paid'],
        'overdue' => ['বকেয়া', 'Overdue'],
        'bank' => ['ব্যাংক', 'Bank'],
        'ngo' => ['এনজিও', 'NGO'],
        'financial' => ['আর্থিক প্রতিষ্ঠান', 'Financial institution'],
        'other' => ['অন্যান্য', 'Other'],
        'flat' => ['সমান সুদ', 'Flat'],
        'reducing' => ['কমে আসা সুদ', 'Reducing'],
        'monthly' => ['মাসিক', 'Monthly'],
        'weekly' => ['সাপ্তাহিক', 'Weekly'],
        'payment' => ['কিস্তি পরিশোধ', 'Installment payment'],
        'settlement' => ['আগাম পরিশোধ', 'Early settlement'],
        'receive' => ['ঋণ গ্রহণ', 'Loan received'],
        'charge' => ['চার্জ', 'Charge'],
    ];
    if (!isset($map[$status])) {
        return $status;
    }
    return ame_l($map[$status][0], $map[$status][1]);
}

function ame_bloan_nav(): void
{
    $items = [
        ['dashboard.php', 'ড্যাশবোর্ড', 'Dashboard'],
        ['providers.php', 'ঋণদাতা', 'Providers'],
        ['application.php', 'আবেদন', 'Applications'],
        ['facility.php', 'মাসিক সুবিধা', 'Monthly facility'],
        ['loans.php', 'চালু ঋণ', 'Active loans'],
        ['disbursement.php', 'টাকা গ্রহণ', 'Disbursement'],
        ['installments.php', 'কিস্তি', 'Installments'],
        ['payments.php', 'পরিশোধ', 'Payments'],
        ['ledger.php', 'খাতা', 'Ledger'],
        ['overdue.php', 'বকেয়া', 'Overdue'],
        ['settlement.php', 'আগাম পরিশোধ', 'Settlement'],
        ['documents.php', 'ডকুমেন্ট', 'Documents'],
        ['reports.php', 'রিপোর্ট', 'Reports'],
    ];
    $here = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    echo '<nav class="bl-nav" aria-label="' . ame_bloan_h(ame_l('ব্যবসার ঋণ', 'Business loan')) . '">';
    foreach ($items as $item) {
        $on = $here === $item[0] ? ' on' : '';
        echo '<a class="' . trim($on) . '" href="' . $item[0] . '">' . ame_bloan_h(ame_l($item[1], $item[2])) . '</a>';
    }
    echo '</nav>';
}

function ame_bloan_flash(): void
{
    if (!empty($_GET['err'])) {
        echo '<p class="err">' . ame_bloan_h((string)$_GET['err']) . '</p>';
    }
    if (isset($_GET['ok'])) {
        echo '<p class="ok">' . ame_bloan_h(ame_l('সংরক্ষণ হয়েছে।', 'Saved.')) . '</p>';
    }
}

function ame_bloan_accounts(mysqli $db): array
{
    if (!function_exists('ame_active_accounts')) {
        require_once __DIR__ . '/account_functions.php';
    }
    return ame_active_accounts($db);
}

function ame_bloan_account_options(mysqli $db): string
{
    return ame_render_account_options(ame_bloan_accounts($db));
}

function ame_bloan_money_ready(mysqli $db): void
{
    if (!function_exists('ame_post_account')) {
        require_once __DIR__ . '/account_functions.php';
    }
}

function ame_bloan_receive_statuses(): array
{
    return ['applied', 'review', 'approved'];
}

function ame_bloan_can_receive(string $status): bool
{
    return in_array($status, ame_bloan_receive_statuses(), true);
}

function ame_bloan_receivable_applications(mysqli $db): array
{
    $rows = $db->query("SELECT a.*, p.name provider_name FROM bloan_applications a JOIN bloan_providers p ON p.id=a.provider_id WHERE a.status IN ('applied','review','approved') ORDER BY a.id DESC");
    return $rows ? $rows->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Shop receives a saved business-loan application into one active cash, bank, or mobile account.
 * The form stores আবেদন as applied. That row is receivable without a second approval.
 * The loan balance and the account credit commit together, or both roll back.
 */
function ame_bloan_receive(mysqli $db, int $appId, int $accountId, float $amount, string $date, int $userId): array
{
    ame_bloan_money_ready($db);
    $amount = round($amount, 2);
    $date = trim($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }
    $appStmt = $db->prepare('SELECT * FROM bloan_applications WHERE id=?');
    $appStmt->bind_param('i', $appId);
    $appStmt->execute();
    $app = $appStmt->get_result()->fetch_assoc();
    $appStmt->close();
    if (!$app || !ame_bloan_can_receive((string)$app['status'])) {
        throw new InvalidArgumentException(function_exists('ame_l') ? ame_l('এই আবেদন থেকে টাকা গ্রহণ করা যায় না।', 'This application cannot be received.') : 'এই আবেদন থেকে টাকা গ্রহণ করা যায় না।');
    }
    if ($amount <= 0 || $amount > (float)$app['amount'] + 0.009) {
        throw new InvalidArgumentException(function_exists('ame_l') ? ame_l('টাকা, অ্যাকাউন্ট লাগবে এবং অনুমোদিত টাকার বেশি গ্রহণ করা যাবে না।', 'Enter an amount and account. It cannot exceed the approved amount.') : 'টাকা, অ্যাকাউন্ট লাগবে এবং অনুমোদিত টাকার বেশি গ্রহণ করা যাবে না।');
    }
    ame_require_selectable_account($db, $accountId);

    $db->begin_transaction();
    try {
        $loanNo = ame_bloan_next_no($db, 'bloan_accounts', 'loan_no', 'BL-');
        $count = ($app['frequency'] ?? '') === 'weekly' ? max(1, (int)$app['tenure_months'] * 4) : max(1, (int)$app['tenure_months']);
        $schedule = ame_bloan_schedule_rows($amount, (float)$app['interest_rate'], $count, (string)$app['frequency'], (string)$app['interest_type']);
        $stmt = $db->prepare('INSERT INTO bloan_accounts (loan_no, application_id, provider_id, approved_amount, disbursed_amount, interest_rate, interest_type, tenure_months, installment_count, frequency, installment_amount, principal_outstanding, status, disbursed_on, account_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,"active",?,?,?)');
        $providerId = (int)$app['provider_id'];
        $approved = (float)$app['amount'];
        $rate = (float)$app['interest_rate'];
        $itype = (string)$app['interest_type'];
        $tenure = (int)$app['tenure_months'];
        $freq = (string)$app['frequency'];
        $instAmt = (float)($schedule[0]['total'] ?? 0);
        $stmt->bind_param('siidddsiisddsii', $loanNo, $appId, $providerId, $approved, $amount, $rate, $itype, $tenure, $count, $freq, $instAmt, $amount, $date, $accountId, $userId);
        $stmt->execute();
        $loanId = (int)$db->insert_id;
        $stmt->close();
        ame_bloan_make_schedule($db, $loanId, $amount, $rate, $count, $freq, $date, $itype);
        $txn = $db->prepare('INSERT INTO bloan_txns (loan_id, txn_date, kind, principal_part, total, balance_after, method, account_id, notes, created_by) VALUES (?,?,"receive",?,?,?,"bank",?,"ঋণ গ্রহণ",?)');
        $txn->bind_param('isdddii', $loanId, $date, $amount, $amount, $amount, $accountId, $userId);
        $txn->execute();
        $txnId = (int)$db->insert_id;
        $txn->close();
        if (!ame_post_account($db, 'receive', $accountId, $amount, 'bloan_receive', $txnId, 'ব্যবসার ঋণ গ্রহণ ' . $loanNo)) {
            throw new RuntimeException(function_exists('ame_l') ? ame_l('ক্যাশ/ব্যাংক লেজারে টাকা যোগ হয়নি।', 'Cash or bank was not credited.') : 'ক্যাশ/ব্যাংক লেজারে টাকা যোগ হয়নি।');
        }
        $fee = round((float)$app['processing_fee'] + (float)$app['other_charge'], 2);
        if ($fee > 0) {
            if (!ame_post_account($db, 'pay', $accountId, $fee, 'bloan_charge', $loanId, 'ঋণের চার্জ ' . $loanNo)) {
                throw new RuntimeException(function_exists('ame_l') ? ame_l('চার্জ কাটা যায়নি। ক্যাশ/ব্যাংক ব্যালেন্স দেখুন।', 'The charge could not be deducted. Check the cash or bank balance.') : 'চার্জ কাটা যায়নি। ক্যাশ/ব্যাংক ব্যালেন্স দেখুন।');
            }
            $feeTxn = $db->prepare('INSERT INTO bloan_txns (loan_id, txn_date, kind, charge_part, total, balance_after, method, account_id, notes, created_by) VALUES (?,?,"charge",?,?,?,"bank",?,"প্রসেসিং ও অন্যান্য চার্জ",?)');
            $feeTxn->bind_param('isdddii', $loanId, $date, $fee, $fee, $amount, $accountId, $userId);
            $feeTxn->execute();
            $feeTxn->close();
            $feeUp = $db->prepare('UPDATE bloan_accounts SET charges_paid=? WHERE id=?');
            $feeUp->bind_param('di', $fee, $loanId);
            $feeUp->execute();
            $feeUp->close();
        }
        $mark = $db->prepare('UPDATE bloan_applications SET status="disbursed" WHERE id=?');
        $mark->bind_param('i', $appId);
        $mark->execute();
        $mark->close();
        $db->commit();
        return ['loan_id' => $loanId, 'loan_no' => $loanNo, 'txn_id' => $txnId];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function ame_bloan_collect(mysqli $db, int $loanId, int $accountId, float $principal, float $interest, float $charge, string $kind, int $userId, string $method, string $ref, string $notes): array
{
    ame_bloan_money_ready($db);
    ame_require_selectable_account($db, $accountId);
    if (!in_array($method, ['bank', 'cash', 'mobile'], true)) {
        $method = 'bank';
    }
    $notes = function_exists('mb_substr') ? mb_substr($notes, 0, 255) : substr($notes, 0, 255);
    $ref = function_exists('mb_substr') ? mb_substr($ref, 0, 80) : substr($ref, 0, 80);
    $db->begin_transaction();
    try {
        $lock = $db->prepare('SELECT * FROM bloan_accounts WHERE id=? FOR UPDATE');
        $lock->bind_param('i', $loanId);
        $lock->execute();
        $loan = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$loan || (string)$loan['status'] !== 'active') {
            throw new InvalidArgumentException(function_exists('ame_l') ? ame_l('চালু ঋণ পাওয়া যায়নি।', 'No active loan was found.') : 'চালু ঋণ পাওয়া যায়নি।');
        }
        if ($kind === 'settlement') {
            if (!ame_bloan_is_admin()) {
                throw new InvalidArgumentException(function_exists('ame_l') ? ame_l('আগাম পরিশোধ শুধু অ্যাডমিন করতে পারবেন।', 'Only an admin can settle early.') : 'আগাম পরিশোধ শুধু অ্যাডমিন করতে পারবেন।');
            }
            $quote = ame_bloan_settlement_quote($db, $loanId);
            $principal = $quote['principal'];
            $interest = $quote['interest'];
            $charge = round(max(0, $charge), 2);
        } else {
            $kind = 'payment';
            $principal = round($principal, 2);
            $interest = round($interest, 2);
            $charge = round($charge, 2);
        }
        $total = round($principal + $interest + $charge, 2);
        if ($principal > (float)$loan['principal_outstanding'] + 0.009) {
            throw new InvalidArgumentException(function_exists('ame_l') ? ame_l('আসল বাকির চেয়ে বেশি দেওয়া যাবে না।', 'Principal cannot exceed the outstanding balance.') : 'আসল বাকির চেয়ে বেশি দেওয়া যাবে না।');
        }
        if ($total <= 0) {
            throw new InvalidArgumentException(function_exists('ame_l') ? ame_l('টাকা ও অ্যাকাউন্ট লাগবে।', 'Amount and account are required.') : 'টাকা ও অ্যাকাউন্ট লাগবে।');
        }
        $outstanding = round((float)$loan['principal_outstanding'] - $principal, 2);
        $paidP = round((float)$loan['principal_paid'] + $principal, 2);
        $paidI = round((float)$loan['interest_paid'] + $interest, 2);
        $paidC = round((float)$loan['charges_paid'] + $charge, 2);
        $status = $outstanding <= 0.009 ? 'closed' : 'active';
        $upd = $db->prepare('UPDATE bloan_accounts SET principal_outstanding=?, principal_paid=?, interest_paid=?, charges_paid=?, status=? WHERE id=?');
        $upd->bind_param('ddddsi', $outstanding, $paidP, $paidI, $paidC, $status, $loanId);
        $upd->execute();
        $upd->close();
        $left = $total;
        $inst = $db->prepare('SELECT * FROM bloan_installments WHERE loan_id=? AND remaining_amount > 0 ORDER BY due_date, installment_no');
        $inst->bind_param('i', $loanId);
        $inst->execute();
        $list = $inst->get_result();
        $touch = $db->prepare('UPDATE bloan_installments SET paid_amount=?, remaining_amount=?, paid_on=?, account_id=?, status=? WHERE id=?');
        $today = date('Y-m-d');
        $firstInst = 0;
        while ($left > 0.009 && ($row = $list->fetch_assoc())) {
            if ($firstInst === 0) {
                $firstInst = (int)$row['id'];
            }
            $use = min($left, (float)$row['remaining_amount']);
            $newPaid = round((float)$row['paid_amount'] + $use, 2);
            $newRem = round((float)$row['remaining_amount'] - $use, 2);
            $st = $newRem <= 0.009 ? 'paid' : 'partial';
            $iid = (int)$row['id'];
            $touch->bind_param('ddsisi', $newPaid, $newRem, $today, $accountId, $st, $iid);
            $touch->execute();
            $left = round($left - $use, 2);
        }
        $touch->close();
        $inst->close();
        if ($status === 'closed') {
            $paidOn = $db->real_escape_string($today);
            $db->query('UPDATE bloan_installments SET remaining_amount=0, status="paid", paid_on="' . $paidOn . '" WHERE loan_id=' . (int)$loanId . ' AND status<>"paid"');
        } else {
            ame_bloan_refresh($db, $loanId);
        }
        $txn = $db->prepare('INSERT INTO bloan_txns (loan_id, installment_id, txn_date, kind, principal_part, interest_part, charge_part, total, balance_after, method, account_id, reference_no, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $instParam = $firstInst > 0 ? $firstInst : 0;
        $txn->bind_param('iissdddddsissi', $loanId, $instParam, $today, $kind, $principal, $interest, $charge, $total, $outstanding, $method, $accountId, $ref, $notes, $userId);
        $txn->execute();
        $txnId = (int)$db->insert_id;
        $txn->close();
        if (!ame_post_account($db, 'pay', $accountId, $total, 'bloan_payment', $txnId, 'ব্যবসার ঋণ পরিশোধ ' . $loan['loan_no'])) {
            throw new RuntimeException(function_exists('ame_l') ? ame_l('ক্যাশ/ব্যাংক থেকে টাকা কাটা যায়নি।', 'Cash or bank could not be debited.') : 'ক্যাশ/ব্যাংক থেকে টাকা কাটা যায়নি।');
        }
        $db->commit();
        return ['txn_id' => $txnId, 'loan_no' => (string)$loan['loan_no'], 'total' => $total, 'kind' => $kind];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function ame_bloan_refresh_open(mysqli $db): void
{
    $res = $db->query("SELECT id FROM bloan_accounts WHERE status='active'");
    if (!$res) {
        return;
    }
    while ($row = $res->fetch_assoc()) {
        ame_bloan_refresh($db, (int)$row['id']);
    }
}

function ame_bloan_settlement_quote(mysqli $db, int $loanId): array
{
    $stmt = $db->prepare('SELECT principal_outstanding FROM bloan_accounts WHERE id=?');
    $stmt->bind_param('i', $loanId);
    $stmt->execute();
    $loan = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $interest = 0.0;
    $rows = $db->prepare('SELECT interest_part, total_due, remaining_amount FROM bloan_installments WHERE loan_id=? AND remaining_amount > 0');
    $rows->bind_param('i', $loanId);
    $rows->execute();
    $list = $rows->get_result();
    while ($row = $list->fetch_assoc()) {
        $total = (float)$row['total_due'];
        if ($total <= 0) {
            continue;
        }
        $interest += (float)$row['interest_part'] * ((float)$row['remaining_amount'] / $total);
    }
    $rows->close();
    return [
        'principal' => round((float)($loan['principal_outstanding'] ?? 0), 2),
        'interest' => round($interest, 2),
    ];
}

function ame_bloan_summary(mysqli $db): array
{
    $loans = $db->query("SELECT COUNT(*) c, COALESCE(SUM(approved_amount),0) approved, COALESCE(SUM(disbursed_amount),0) disbursed, COALESCE(SUM(principal_paid),0) principal_paid, COALESCE(SUM(interest_paid),0) interest_paid, COALESCE(SUM(charges_paid),0) charges_paid, COALESCE(SUM(principal_outstanding),0) outstanding, SUM(status='active') active_n, SUM(status='closed') closed_n FROM bloan_accounts");
    $base = $loans ? ($loans->fetch_assoc() ?: []) : [];
    $month = date('Y-m');
    $dueStmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN DATE_FORMAT(due_date,'%Y-%m')=? AND status<>'paid' THEN remaining_amount ELSE 0 END),0) month_due, COALESCE(SUM(status='overdue'),0) overdue_n, COALESCE(SUM(CASE WHEN status='overdue' THEN remaining_amount ELSE 0 END),0) overdue_amt, COALESCE(SUM(status='upcoming'),0) upcoming_n FROM bloan_installments");
    $dueStmt->bind_param('s', $month);
    $dueStmt->execute();
    $extra = $dueStmt->get_result()->fetch_assoc() ?: [];
    $dueStmt->close();
    $disbursed = (float)($base['disbursed'] ?? 0);
    $paid = (float)($base['principal_paid'] ?? 0);
    $base['paid_total'] = $paid + (float)($base['interest_paid'] ?? 0) + (float)($base['charges_paid'] ?? 0);
    $base['completion'] = $disbursed > 0 ? round(($paid / $disbursed) * 100, 1) : 0;
    return array_merge($base, $extra);
}

function ame_bloan_dhaka_today(): string
{
    return (new DateTime('now', new DateTimeZone('Asia/Dhaka')))->format('Y-m-d');
}

function ame_bloan_month_key(string $yearMonth): string
{
    if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
        throw new InvalidArgumentException('মাস সঠিক নয়।');
    }
    return 'M:' . $yearMonth;
}

function ame_bloan_month_list(string $fromMonth, string $toMonth): array
{
    $tz = new DateTimeZone('Asia/Dhaka');
    $cur = DateTime::createFromFormat('!Y-m-d', $fromMonth . '-01', $tz);
    $end = DateTime::createFromFormat('!Y-m-d', $toMonth . '-01', $tz);
    if (!$cur || !$end || $cur > $end) {
        return [];
    }
    $out = [];
    while ($cur <= $end) {
        $out[] = $cur->format('Y-m');
        $cur->modify('+1 month');
    }
    return $out;
}

function ame_bloan_facility_row(mysqli $db, int $facilityId): ?array
{
    $stmt = $db->prepare('SELECT f.*, p.name provider_name FROM bloan_facilities f JOIN bloan_providers p ON p.id=f.provider_id WHERE f.id=?');
    $stmt->bind_param('i', $facilityId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ame_bloan_facility_open(mysqli $db, int $providerId, string $institutionName, float $amount, int $accountId, int $userId): int
{
    ame_bloan_money_ready($db);
    $amount = round($amount, 2);
    if ($amount <= 0) {
        throw new InvalidArgumentException(function_exists('ame_l') ? ame_l('মাসিক টাকা লাগবে।', 'A monthly amount is required.') : 'মাসিক টাকা লাগবে।');
    }
    ame_require_selectable_account($db, $accountId);
    if ($providerId < 1) {
        $institutionName = trim($institutionName);
        if ($institutionName === '') {
            throw new InvalidArgumentException(function_exists('ame_l') ? ame_l('ঋণদাতা বা প্রতিষ্ঠানের নাম লাগবে।', 'Choose a lender or name the institution.') : 'ঋণদাতা বা প্রতিষ্ঠানের নাম লাগবে।');
        }
        $type = 'other';
        $branch = '';
        $find = $db->prepare('SELECT id FROM bloan_providers WHERE provider_type=? AND name=? AND branch=? LIMIT 1');
        $find->bind_param('sss', $type, $institutionName, $branch);
        $find->execute();
        $existing = $find->get_result()->fetch_assoc();
        $find->close();
        if ($existing) {
            $providerId = (int)$existing['id'];
        } else {
            $ins = $db->prepare('INSERT INTO bloan_providers (provider_type, name, branch, created_by) VALUES (?,?,?,?)');
            $ins->bind_param('sssi', $type, $institutionName, $branch, $userId);
            $ins->execute();
            $providerId = (int)$db->insert_id;
            $ins->close();
        }
    }
    $dup = $db->prepare("SELECT id FROM bloan_facilities WHERE provider_id=? AND status='active' LIMIT 1");
    $dup->bind_param('i', $providerId);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        $dup->close();
        throw new InvalidArgumentException(function_exists('ame_l') ? ame_l('এই প্রতিষ্ঠানের মাসিক সুবিধা ইতিমধ্যে চালু আছে।', 'This institution already has an active monthly facility.') : 'এই প্রতিষ্ঠানের মাসিক সুবিধা ইতিমধ্যে চালু আছে।');
    }
    $dup->close();
    $started = ame_bloan_dhaka_today();
    $stmt = $db->prepare("INSERT INTO bloan_facilities (provider_id, monthly_amount, account_id, status, started_on, created_by) VALUES (?,?,?,'active',?,?)");
    $stmt->bind_param('idisi', $providerId, $amount, $accountId, $started, $userId);
    $stmt->execute();
    $id = (int)$db->insert_id;
    $stmt->close();
    return $id;
}

function ame_bloan_facility_stop(mysqli $db, int $facilityId): void
{
    $today = ame_bloan_dhaka_today();
    $stmt = $db->prepare("UPDATE bloan_facilities SET status='stopped', stopped_on=? WHERE id=? AND status='active'");
    $stmt->bind_param('si', $today, $facilityId);
    $stmt->execute();
    $stmt->close();
}

function ame_bloan_facility_rebalance(mysqli $db, int $facilityId): void
{
    $stmt = $db->prepare('SELECT id, amount FROM bloan_facility_months WHERE facility_id=? ORDER BY period_key, id');
    $stmt->bind_param('i', $facilityId);
    $stmt->execute();
    $list = $stmt->get_result();
    $upd = $db->prepare('UPDATE bloan_facility_months SET balance_after=? WHERE id=?');
    $run = 0.0;
    while ($row = $list->fetch_assoc()) {
        $run = round($run + (float)$row['amount'], 2);
        $id = (int)$row['id'];
        $upd->bind_param('di', $run, $id);
        $upd->execute();
    }
    $upd->close();
    $stmt->close();
}

function ame_bloan_facility_post_month(mysqli $db, int $facilityId, string $yearMonth, int $userId): array
{
    ame_bloan_money_ready($db);
    $period = ame_bloan_month_key($yearMonth);
    $facility = ame_bloan_facility_row($db, $facilityId);
    if (!$facility) {
        throw new InvalidArgumentException('মাসিক সুবিধা পাওয়া যায়নি।');
    }
    if ((string)$facility['status'] !== 'active') {
        $stopMonth = substr((string)($facility['stopped_on'] ?? ''), 0, 7);
        if ($stopMonth === '' || $yearMonth > $stopMonth) {
            return ['posted' => false, 'reason' => 'stopped', 'id' => 0];
        }
    }
    if ($yearMonth > ame_bloan_dhaka_month_now()) {
        return ['posted' => false, 'reason' => 'future', 'id' => 0];
    }
    $seen = $db->prepare('SELECT id, amount FROM bloan_facility_months WHERE facility_id=? AND period_key=? LIMIT 1');
    $seen->bind_param('is', $facilityId, $period);
    $seen->execute();
    $already = $seen->get_result()->fetch_assoc();
    $seen->close();
    if ($already) {
        return ['posted' => false, 'reason' => 'exists', 'id' => (int)$already['id'], 'amount' => (float)$already['amount']];
    }

    $amount = round((float)$facility['monthly_amount'], 2);
    $accountId = (int)$facility['account_id'];
    ame_require_selectable_account($db, $accountId);
    $token = 'F' . $facilityId . $period;
    if (function_exists('ensure_integrity_schema')) {
        ensure_integrity_schema($db);
    }
    $db->begin_transaction();
    try {
        $existingRef = 0;
        if (function_exists('claim_idempotency_token') && !claim_idempotency_token($db, 'bloan_facility', $token, $existingRef)) {
            $db->rollback();
            return ['posted' => false, 'reason' => 'exists', 'id' => $existingRef, 'amount' => $amount];
        }
        $txnDate = $yearMonth . '-01';
        $zero = 0.0;
        $ins = $db->prepare('INSERT INTO bloan_facility_months (facility_id, period_key, txn_date, amount, balance_after, account_id, created_by) VALUES (?,?,?,?,?,?,?)');
        $ins->bind_param('issddii', $facilityId, $period, $txnDate, $amount, $zero, $accountId, $userId);
        $ok = false;
        $errno = 0;
        try {
            $ok = $ins->execute();
        } catch (mysqli_sql_exception $e) {
            $errno = (int)$e->getCode();
        }
        if (!$ok && $errno === 0) {
            $errno = (int)$ins->errno ?: (int)$db->errno;
        }
        $monthId = $ok ? (int)$db->insert_id : 0;
        $ins->close();
        if ($errno === 1062) {
            $db->rollback();
            return ['posted' => false, 'reason' => 'exists', 'id' => 0, 'amount' => $amount];
        }
        if (!$ok || $monthId < 1) {
            throw new RuntimeException('এই মাসের হিসাব সেভ হয়নি।');
        }
        $note = 'মাসিক সুবিধা ' . (string)$facility['provider_name'] . ' ' . $yearMonth;
        if (!ame_post_account($db, 'pay', $accountId, $amount, 'bloan_facility', $monthId, $note)) {
            throw new RuntimeException('অ্যাকাউন্ট থেকে মাসিক টাকা কাটা যায়নি।');
        }
        ame_bloan_facility_rebalance($db, $facilityId);
        if (function_exists('finish_idempotency_token')) {
            finish_idempotency_token($db, 'bloan_facility', $token, $monthId);
        }
        $db->commit();
        return ['posted' => true, 'reason' => 'posted', 'id' => $monthId, 'amount' => $amount];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function ame_bloan_facility_sync(mysqli $db, int $facilityId, int $userId, ?string $throughMonth = null): array
{
    $facility = ame_bloan_facility_row($db, $facilityId);
    if (!$facility) {
        return [];
    }
    $start = substr((string)$facility['started_on'], 0, 7);
    $through = $throughMonth ?: ame_bloan_dhaka_month_now();
    if ((string)$facility['status'] !== 'active') {
        $stopMonth = substr((string)($facility['stopped_on'] ?? ''), 0, 7);
        if ($stopMonth !== '' && $through > $stopMonth) {
            $through = $stopMonth;
        }
    }
    $out = [];
    foreach (ame_bloan_month_list($start, $through) as $month) {
        $out[] = ame_bloan_facility_post_month($db, $facilityId, $month, $userId);
    }
    return $out;
}

function ame_bloan_dhaka_month_now(): string
{
    return (new DateTime('now', new DateTimeZone('Asia/Dhaka')))->format('Y-m');
}
