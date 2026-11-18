<?php
/**
 * ================================================================
 * Loan / ঋণ ব্যবস্থাপনা — দোকানদার নিজে ব্যাংক থেকে যে ঋণ নেন তার হিসাব
 * ================================================================
 * পুরনো সাধারণ person-to-person `loans` টেবিলের পাশাপাশি এই সম্পূর্ণ
 * নতুন, ব্যাংক-লোন-উপযোগী সিস্টেম — সুদ, কিস্তি সময়সূচি, এবং মূল
 * হিসাব ব্যবস্থার সাথে স্বয়ংক্রিয় সংযোগসহ।
 */

function ensure_bank_loan_schema($db) {
    $db->query("CREATE TABLE IF NOT EXISTS bank_loans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_name VARCHAR(150) NOT NULL,
        bank_name VARCHAR(150) NOT NULL,
        loan_type VARCHAR(50) NOT NULL DEFAULT 'ব্যবসায়িক লোন',
        principal_amount DECIMAL(14,2) NOT NULL,
        paid_principal DECIMAL(14,2) NOT NULL DEFAULT 0,
        interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
        loan_date DATE NOT NULL,
        account_id INT NULL,
        tenure_months INT NOT NULL DEFAULT 0,
        installment_count INT NOT NULL DEFAULT 0,
        installment_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        first_installment_date DATE NULL,
        last_installment_date DATE NULL,
        processing_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
        other_charges DECIMAL(12,2) NOT NULL DEFAULT 0,
        notes VARCHAR(500) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        closed_date DATE NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        INDEX idx_status (status),
        INDEX idx_bank (bank_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS loan_installments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_id INT NOT NULL,
        installment_no INT NOT NULL,
        due_date DATE NOT NULL,
        installment_amount DECIMAL(14,2) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        paid_date DATE NULL,
        INDEX idx_loan (loan_id),
        INDEX idx_due (due_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS loan_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_id INT NOT NULL,
        txn_type VARCHAR(20) NOT NULL,
        txn_date DATE NOT NULL,
        total_amount DECIMAL(14,2) NOT NULL,
        principal_part DECIMAL(14,2) NOT NULL DEFAULT 0,
        interest_part DECIMAL(14,2) NOT NULL DEFAULT 0,
        charge_part DECIMAL(14,2) NOT NULL DEFAULT 0,
        account_id INT NULL,
        reference_no VARCHAR(100) NULL,
        note VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_loan (loan_id),
        INDEX idx_type (txn_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/*
 * সমান কিস্তির সরল সময়সূচি তৈরি — প্রতিটা কিস্তি সমান পরিমাণে,
 * মাসিক ব্যবধানে। (Amortization বাদে সরল বণ্টন — ছোট দোকানদারের
 * জন্য এটাই সাধারণত যথেষ্ট।)
 */
function generate_installment_schedule($db, $loan_id, $installment_count, $installment_amount, $first_date) {
    if ($installment_count <= 0 || $installment_amount <= 0 || !$first_date) {
        return;
    }
    $stmt = $db->prepare(
        "INSERT INTO loan_installments (loan_id, installment_no, due_date, installment_amount) VALUES (?, ?, ?, ?)"
    );
    for ($i = 1; $i <= $installment_count; $i++) {
        $due_date = date('Y-m-d', strtotime($first_date . ' +' . ($i - 1) . ' months'));
        $stmt->bind_param('iisd', $loan_id, $i, $due_date, $installment_amount);
        $stmt->execute();
    }
}

function generate_loan_ref_no($db) {
    $today = date('Ymd');
    $prefix = 'LN-' . $today . '-';
    $stmt = $db->prepare("SELECT COUNT(*) c FROM bank_loans WHERE id > 0 AND DATE(created_at) = CURDATE()");
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    return $prefix . str_pad((string)($count + 1), 3, '0', STR_PAD_LEFT);
}
