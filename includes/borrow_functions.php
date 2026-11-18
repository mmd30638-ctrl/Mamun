<?php
/**
 * Business borrowing only. Bank, NGO, institution, or a person the shop owes.
 * Customer lending tables are not touched.
 */

function ame_borrow_ensure(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS biz_loans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider_name VARCHAR(150) NOT NULL,
        provider_type VARCHAR(30) NOT NULL DEFAULT 'bank',
        repayment_style VARCHAR(20) NOT NULL DEFAULT 'installment',
        branch VARCHAR(150) NULL,
        phone VARCHAR(40) NULL,
        account_no VARCHAR(80) NULL,
        loan_ref VARCHAR(80) NULL,
        contact_person VARCHAR(120) NULL,
        agreement_date DATE NULL,
        start_date DATE NULL,
        maturity_date DATE NULL,
        notes VARCHAR(500) NULL,
        principal DECIMAL(15,2) NOT NULL DEFAULT 0,
        interest_rate DECIMAL(8,3) NOT NULL DEFAULT 0,
        processing_fee DECIMAL(15,2) NOT NULL DEFAULT 0,
        frequency VARCHAR(20) NOT NULL DEFAULT 'monthly',
        installment_count INT NOT NULL DEFAULT 0,
        installment_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        monthly_fee DECIMAL(15,2) NOT NULL DEFAULT 0,
        first_payment_date DATE NULL,
        principal_outstanding DECIMAL(15,2) NOT NULL DEFAULT 0,
        principal_repaid DECIMAL(15,2) NOT NULL DEFAULT 0,
        interest_paid_total DECIMAL(15,2) NOT NULL DEFAULT 0,
        fee_paid_total DECIMAL(15,2) NOT NULL DEFAULT 0,
        savings_paid_total DECIMAL(15,2) NOT NULL DEFAULT 0,
        security_held DECIMAL(15,2) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_type (provider_type),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS biz_loan_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_id INT NOT NULL,
        installment_no INT NOT NULL,
        due_date DATE NOT NULL,
        principal_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        interest_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        charges DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_due DECIMAL(15,2) NOT NULL DEFAULT 0,
        paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        remaining_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'upcoming',
        KEY idx_loan (loan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS biz_loan_txns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_id INT NOT NULL,
        txn_date DATE NOT NULL,
        kind VARCHAR(30) NOT NULL,
        principal_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        interest_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        fee_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        savings_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        security_part DECIMAL(15,2) NOT NULL DEFAULT 0,
        charges DECIMAL(15,2) NOT NULL DEFAULT 0,
        total DECIMAL(15,2) NOT NULL DEFAULT 0,
        method VARCHAR(30) NOT NULL DEFAULT 'cash',
        account_id INT NOT NULL DEFAULT 0,
        reference_no VARCHAR(80) NULL,
        notes VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_loan (loan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ame_borrow_require(mysqli $db): void
{
    if (isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin') {
        return;
    }
    $ok = function_exists('has_permission') && (has_permission($db, 'm-oloans') || has_permission($db, 'm-loans'));
    if ($ok) {
        return;
    }
    http_response_code(403);
    die('ব্যবসার ধার দেখার অনুমতি নেই।');
}

function ame_borrow_fetch(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM biz_loans WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ame_borrow_schedule(mysqli $db, int $loan_id, float $principal, float $rate, int $count, string $frequency, string $start): void
{
    if ($count <= 0 || $principal <= 0) {
        return;
    }
    $months = $frequency === 'weekly' ? ($count / 4) : $count;
    $interest = round($principal * ($rate / 100) * ($months / 12), 2);
    $principal_per = round($principal / $count, 2);
    $interest_per = round($interest / $count, 2);
    $del = $db->prepare('DELETE FROM biz_loan_schedule WHERE loan_id = ? AND paid_amount = 0');
    $del->bind_param('i', $loan_id);
    $del->execute();
    $del->close();
    $ins = $db->prepare('INSERT INTO biz_loan_schedule (loan_id, installment_no, due_date, principal_part, interest_part, charges, total_due, paid_amount, remaining_amount, status) VALUES (?,?,?,?,?,0,?,0,?,"upcoming")');
    $due = new DateTime($start !== '' ? $start : date('Y-m-d'));
    for ($i = 1; $i <= $count; $i++) {
        if ($frequency === 'weekly') {
            $due->modify('+7 day');
        } else {
            $due->modify('+1 month');
        }
        $p = $principal_per;
        $n = $interest_per;
        if ($i === $count) {
            $p = round($principal - ($principal_per * ($count - 1)), 2);
            $n = round($interest - ($interest_per * ($count - 1)), 2);
        }
        $total = round($p + $n, 2);
        $date = $due->format('Y-m-d');
        $ins->bind_param('iisdddd', $loan_id, $i, $date, $p, $n, $total, $total);
        $ins->execute();
    }
    $ins->close();
}

function ame_borrow_summary(mysqli $db): array
{
    $sql = "SELECT
        COALESCE(SUM(principal),0) borrowed,
        COALESCE(SUM(principal_outstanding),0) outstanding,
        COALESCE(SUM(principal_repaid),0) repaid,
        COALESCE(SUM(interest_paid_total),0) interest_paid,
        COALESCE(SUM(fee_paid_total),0) fees,
        COALESCE(SUM(savings_paid_total),0) savings,
        COALESCE(SUM(security_held),0) security
        FROM biz_loans WHERE status <> 'cancelled'";
    $row = $db->query($sql);
    return $row ? ($row->fetch_assoc() ?: []) : [];
}
