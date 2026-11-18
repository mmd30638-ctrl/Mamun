<?php
/* -------------------------------------------------------------
 * LOAN MANAGEMENT — SCHEMA ম্যানেজার
 * -------------------------------------------------------------
 * উদ্দেশ্য: শুধু আজকের Batch 1 (Products/Applications/Approvals/
 * Disbursement/Dashboard) না, বরং পুরো Loan Management মেনুর
 * (Repayment Schedule, Collections, Due & Overdue, Recovery,
 * Restructure, Ledger, Settlement, Reports, Settings) জন্য দরকারি
 * টেবিলগুলো একসাথে ডিজাইন করে রাখা হলো, যাতে পরের ধাপগুলোতে আর
 * স্কিমা ভাঙতে না হয়। প্রতিটা টেবিল CREATE TABLE IF NOT EXISTS দিয়ে
 * নিরাপদ — বিদ্যমান ডাটা কখনো মুছে না।
 * ------------------------------------------------------------- */

function ensure_loan_tables($db) {
    try {
        // ১. লোন প্রোডাক্ট (টেমপ্লেট) — সুদের হার, মেয়াদ, সীমা ইত্যাদি
        $db->query("CREATE TABLE IF NOT EXISTS loan_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
            interest_type ENUM('flat','reducing') NOT NULL DEFAULT 'flat',
            default_term_months INT NOT NULL DEFAULT 12,
            min_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            max_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            processing_fee_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            penalty_rate_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ২. লোন আবেদন (Application → Approval ওয়ার্কফ্লো)
        $db->query("CREATE TABLE IF NOT EXISTS loan_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            product_id INT NOT NULL,
            requested_amount DECIMAL(12,2) NOT NULL,
            requested_term_months INT NOT NULL,
            purpose VARCHAR(255) DEFAULT NULL,
            guarantor_name VARCHAR(150) DEFAULT NULL,
            guarantor_phone VARCHAR(20) DEFAULT NULL,
            status ENUM('pending','approved','rejected','disbursed','cancelled') NOT NULL DEFAULT 'pending',
            applied_by INT DEFAULT NULL,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            decided_by INT DEFAULT NULL,
            decided_at DATETIME DEFAULT NULL,
            decision_note VARCHAR(255) DEFAULT NULL,
            resulting_loan_id INT DEFAULT NULL,
            INDEX(customer_id), INDEX(product_id), INDEX(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ৩. বিদ্যমান `loans` টেবিলে ফরমাল লেন্ডিং-এর জন্য নতুন কলাম প্যাচ করা
        //    (বাগ ফিক্স নয়, বরং প্রসারণ — পুরনো কলাম/ডাটা অক্ষত থাকছে)
        $loan_cols = [];
        $lc = $db->query("SHOW COLUMNS FROM loans");
        if ($lc) { while ($row = $lc->fetch_assoc()) { $loan_cols[] = $row['Field']; } }

        $new_loan_columns = [
            'customer_id'             => "INT DEFAULT NULL",
            'product_id'              => "INT DEFAULT NULL",
            'application_id'          => "INT DEFAULT NULL",
            'interest_rate'           => "DECIMAL(5,2) DEFAULT NULL",
            'interest_type'           => "VARCHAR(20) DEFAULT NULL",
            'term_months'             => "INT DEFAULT NULL",
            'disbursed_at'            => "DATETIME DEFAULT NULL",
            'disbursed_by'            => "INT DEFAULT NULL",
            'disbursement_account_id' => "INT DEFAULT NULL",
            'next_due_date'           => "DATE DEFAULT NULL",
        ];
        foreach ($new_loan_columns as $col => $def) {
            if (!in_array($col, $loan_cols, true)) {
                $db->query("ALTER TABLE loans ADD COLUMN $col $def");
            }
        }

        // status কলামটা যদি সীমিত ENUM('open',...) টাইপের হয়ে থাকে (পুরনো
        // স্ট্রাকচার), সেটাকে VARCHAR-এ প্রসারিত করা হচ্ছে যাতে Settlement/
        // Closure ফিচারের নতুন স্ট্যাটাস ('closed','settled','written_off')
        // নিরাপদে বসানো যায় — চেষ্টা ব্যর্থ হলেও সাইট বন্ধ হবে না
        try {
            $db->query("ALTER TABLE loans MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'open'");
        } catch (mysqli_sql_exception $e) {
            error_log('loans.status column widen skipped: ' . $e->getMessage());
        }

        // ৪. রিপেমেন্ট শিডিউল (প্রতিটা কিস্তি আলাদা সারি হিসেবে)
        $db->query("CREATE TABLE IF NOT EXISTS loan_repayment_schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_id INT NOT NULL,
            installment_no INT NOT NULL,
            due_date DATE NOT NULL,
            due_principal DECIMAL(12,2) NOT NULL DEFAULT 0,
            due_interest DECIMAL(12,2) NOT NULL DEFAULT 0,
            due_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            status ENUM('pending','partial','paid','overdue') NOT NULL DEFAULT 'pending',
            INDEX(loan_id), INDEX(due_date), INDEX(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ৫. রিকভারি/তাগাদার লগ (কল/ভিজিট/নোটিশ ইত্যাদি)
        $db->query("CREATE TABLE IF NOT EXISTS loan_recovery_actions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_id INT NOT NULL,
            action_type ENUM('call','visit','notice','legal','other') NOT NULL DEFAULT 'call',
            action_date DATE NOT NULL,
            notes TEXT DEFAULT NULL,
            done_by INT DEFAULT NULL,
            next_follow_up_date DATE DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX(loan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ৬. পুনর্গঠন (Restructure) ইতিহাস
        $db->query("CREATE TABLE IF NOT EXISTS loan_restructure_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_id INT NOT NULL,
            old_amount DECIMAL(12,2) NOT NULL,
            old_term_months INT NOT NULL,
            old_interest_rate DECIMAL(5,2) NOT NULL,
            new_amount DECIMAL(12,2) NOT NULL,
            new_term_months INT NOT NULL,
            new_interest_rate DECIMAL(5,2) NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            approved_by INT DEFAULT NULL,
            restructured_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX(loan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ৭. লোন মডিউলের নিজস্ব সেটিংস (গ্রেস পিরিয়ড, ডিফল্ট পেনাল্টি ইত্যাদি)
        $db->query("CREATE TABLE IF NOT EXISTS loan_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grace_period_days INT NOT NULL DEFAULT 3,
            default_penalty_rate_percent DECIMAL(5,2) NOT NULL DEFAULT 2,
            require_guarantor TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $chk = $db->query("SELECT id FROM loan_settings LIMIT 1");
        if ($chk && $chk->num_rows === 0) {
            $db->query("INSERT INTO loan_settings (grace_period_days, default_penalty_rate_percent) VALUES (3, 2)");
        }

        // ৯. ফি ও চার্জ (প্রসেসিং ফি, বিলম্ব জরিমানা ইত্যাদি) — প্রতিটা লোনে
        //    কী কী চার্জ ধরা হয়েছে তার আলাদা লগ, যাতে Loan Ledger-এ পুরো
        //    হিসাব (আসল + সুদ + ফি) একসাথে দেখানো যায়
        $db->query("CREATE TABLE IF NOT EXISTS loan_fees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_id INT NOT NULL,
            fee_type ENUM('processing','penalty','other') NOT NULL DEFAULT 'other',
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            note VARCHAR(255) DEFAULT NULL,
            charged_by INT DEFAULT NULL,
            charged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX(loan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ১০. Settlement/Closure — লোন কীভাবে বন্ধ হলো তার ইতিহাস (স্বাভাবিক
        //     মেয়াদ শেষে, নাকি আগেভাগে মওকুফ/সেটেলমেন্ট করে)
        $db->query("CREATE TABLE IF NOT EXISTS loan_settlements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_id INT NOT NULL,
            settlement_type ENUM('full_payment','early_settlement','written_off') NOT NULL DEFAULT 'full_payment',
            waived_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            note VARCHAR(255) DEFAULT NULL,
            settled_by INT DEFAULT NULL,
            settled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX(loan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ১১. customers টেবিলে is_borrower ফ্ল্যাগ (is_customer/is_supplier-এর
        //    মতো একই কনভেনশন অনুসরণ করে) — যাতে "Borrowers" লিস্ট আলাদা টেবিল
        //    ছাড়াই বিদ্যমান customers থেকেই ফিল্টার করে দেখানো যায়
        $cust_cols = [];
        $cc = $db->query("SHOW COLUMNS FROM customers");
        if ($cc) { while ($row = $cc->fetch_assoc()) { $cust_cols[] = $row['Field']; } }
        if (!in_array('is_borrower', $cust_cols, true)) {
            $db->query("ALTER TABLE customers ADD COLUMN is_borrower TINYINT(1) NOT NULL DEFAULT 0");
        }

    } catch (mysqli_sql_exception $e) {
        die("❌ লোন মডিউলের টেবিল তৈরিতে সমস্যা: " . htmlspecialchars($e->getMessage()));
    }
}

/* সহায়ক ফাংশন: একটা লোন প্রোডাক্ট + মূল টাকা + মেয়াদ থেকে ফ্ল্যাট-রেট
   পদ্ধতিতে একটা সরল রিপেমেন্ট শিডিউল তৈরি করে (সমান মাসিক কিস্তি) */
function generate_flat_repayment_schedule($db, $loan_id, $principal, $annual_rate_percent, $term_months, $start_date) {
    if ($term_months <= 0) { return; }

    $total_interest = $principal * ($annual_rate_percent / 100) * ($term_months / 12);
    $total_payable = $principal + $total_interest;
    $installment_amount = round($total_payable / $term_months, 2);

    $principal_per_month = round($principal / $term_months, 2);
    $interest_per_month = round($total_interest / $term_months, 2);

    $stmt = $db->prepare("INSERT INTO loan_repayment_schedule
        (loan_id, installment_no, due_date, due_principal, due_interest, due_amount, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')");

    $due_date = new DateTime($start_date);
    for ($i = 1; $i <= $term_months; $i++) {
        $due_date->modify('+1 month');
        $due_date_str = $due_date->format('Y-m-d');

        // শেষ কিস্তিতে রাউন্ডিং-এর ছোট পার্থক্য সমন্বয় করা হয়
        $p = $principal_per_month;
        $amt = $installment_amount;
        if ($i === $term_months) {
            $p = round($principal - ($principal_per_month * ($term_months - 1)), 2);
            $amt = round($total_payable - ($installment_amount * ($term_months - 1)), 2);
        }

        $stmt->bind_param("iisddd", $loan_id, $i, $due_date_str, $p, $interest_per_month, $amt);
        $stmt->execute();
    }
}
