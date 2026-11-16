<?php
/**
 * ================================================================
 * পারিবারিক আর্থিক ব্যবস্থাপনা (Personal/Family Finance) — MyFinance
 * ================================================================
 * ⭐ একীভূতকরণ: এই মডিউল এখন দোকানের মূল "accounts" ও "account_ledger"
 * টেবিল ব্যবহার করে — আলাদা কোনো account/transaction টেবিল নেই।
 * এর ফলে লোন গ্রহণ, দোকানের বিক্রয়/ক্রয়, সাপ্লায়ার/কাস্টমার পেমেন্ট —
 * সবকিছুই এই পারিবারিক ফাইন্যান্স ড্যাশবোর্ডে স্বয়ংক্রিয়ভাবে প্রতিফলিত হবে,
 * কারণ এটা একই ব্যক্তির একই টাকা — কৃত্রিমভাবে আলাদা করার প্রয়োজন নেই।
 *
 * শুধু ক্যাটাগরি/মার্চেন্ট/পরিবারের-সদস্য — এই বাড়তি বিবরণগুলোর জন্যই
 * এখনো আলাদা ছোট lookup টেবিল (pf_categories, pf_merchants,
 * pf_family_members) ব্যবহার হচ্ছে, কারণ ব্যবসায়িক লেনদেনে এসবের
 * প্রয়োজন নেই।
 */
function ensure_pf_schema($db) {
    $db->query("CREATE TABLE IF NOT EXISTS pf_family_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        role VARCHAR(30) NOT NULL DEFAULT 'other',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS pf_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        parent_group VARCHAR(50) NOT NULL DEFAULT 'অন্যান্য',
        type VARCHAR(10) NOT NULL DEFAULT 'expense',
        icon VARCHAR(10) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS pf_merchants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        category_id INT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ================= প্রি-সেট ক্যাটাগরি (শুধু প্রথমবার, টেবিল খালি থাকলে) =================
    $count = $db->query("SELECT COUNT(*) c FROM pf_categories")->fetch_assoc()['c'] ?? 0;
    if ($count == 0) {
        $defaults = [
            ['বাজার/মুদি', 'Household', 'expense', '🛒'],
            ['খাবার', 'Household', 'expense', '🍽️'],
            ['গৃহস্থালী সামগ্রী', 'Household', 'expense', '🏠'],
            ['বিদ্যুৎ বিল', 'Bills', 'expense', '💡'],
            ['গ্যাস বিল', 'Bills', 'expense', '🔥'],
            ['পানি বিল', 'Bills', 'expense', '🚰'],
            ['ইন্টারনেট', 'Bills', 'expense', '🌐'],
            ['মোবাইল', 'Bills', 'expense', '📱'],
            ['বাস/সিএনজি', 'Transport', 'expense', '🚌'],
            ['জ্বালানি', 'Transport', 'expense', '⛽'],
            ['শিক্ষা', 'Family', 'expense', '🎓'],
            ['চিকিৎসা', 'Family', 'expense', '🏥'],
            ['পোশাক', 'Family', 'expense', '👕'],
            ['পারিবারিক কেনাকাটা', 'Family', 'expense', '🛍️'],
            ['ব্যক্তিগত', 'Personal', 'expense', '🙂'],
            ['বিনোদন', 'Personal', 'expense', '🎬'],
            ['সাবস্ক্রিপশন', 'Personal', 'expense', '📺'],
            ['অন্যান্য খরচ', 'অন্যান্য', 'expense', '📦'],
            ['বেতন', 'Income', 'income', '💰'],
            ['ব্যবসা আয়', 'Income', 'income', '🏪'],
            ['ফ্রিল্যান্স', 'Income', 'income', '💻'],
            ['অন্যান্য আয়', 'Income', 'income', '➕'],
        ];
        $stmt = $db->prepare("INSERT INTO pf_categories (name, parent_group, type, icon) VALUES (?, ?, ?, ?)");
        foreach ($defaults as $d) {
            $stmt->bind_param('ssss', $d[0], $d[1], $d[2], $d[3]);
            $stmt->execute();
        }
    }

    // ================= পুরনো pf_accounts থাকলে (আগের সংস্করণ থেকে) মূল accounts-এ মাইগ্রেট =================
    migrate_pf_accounts_if_needed($db);
}

/*
 * পুরনো সংস্করণে যদি আলাদা "pf_accounts" টেবিলে কোনো অ্যাকাউন্ট তৈরি করা
 * হয়ে থাকে, সেগুলো একবারই মূল accounts টেবিলে কপি করে আনা হচ্ছে — যাতে
 * কোনো তথ্য হারিয়ে না যায়। নতুন ইনস্টলেশনে pf_accounts টেবিলই থাকবে না,
 * তখন এই ফাংশন কিছুই করবে না।
 */
function migrate_pf_accounts_if_needed($db) {
    $table_check = $db->query("SHOW TABLES LIKE 'pf_accounts'");
    if (!$table_check || $table_check->num_rows === 0) {
        return; // পুরনো টেবিলই নেই — মাইগ্রেশনের প্রয়োজন নেই
    }

    $old_accounts = $db->query("SELECT * FROM pf_accounts");
    if (!$old_accounts || $old_accounts->num_rows === 0) {
        $db->query("DROP TABLE pf_accounts");
        return;
    }

    while ($old = $old_accounts->fetch_assoc()) {
        // একই নামের অ্যাকাউন্ট আগে থেকে থাকলে ডুপ্লিকেট না করে বাদ দেওয়া
        $exists = $db->prepare("SELECT id FROM accounts WHERE account_name = ? LIMIT 1");
        $exists->bind_param('s', $old['name']);
        $exists->execute();
        if ($exists->get_result()->num_rows > 0) {
            continue;
        }
        $ins = $db->prepare("INSERT INTO accounts (account_name, account_type, balance, status) VALUES (?, ?, ?, 'active')");
        $ins->bind_param('ssd', $old['name'], $old['type'], $old['current_balance']);
        $ins->execute();
        $new_id = $ins->insert_id;

        // পুরনো pf_transactions-এ এই অ্যাকাউন্টের রেফারেন্স থাকলে নতুন account_ledger-এ কপি
        $old_txns_check = $db->query("SHOW TABLES LIKE 'pf_transactions'");
        if ($old_txns_check && $old_txns_check->num_rows > 0) {
            $old_txns = $db->prepare("SELECT * FROM pf_transactions WHERE account_id = ? OR to_account_id = ?");
            $old_txns->bind_param('ii', $old['id'], $old['id']);
            $old_txns->execute();
            $rows = $old_txns->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($rows as $t) {
                $is_credit = in_array($t['type'], ['income', 'refund'], true) || ((int)$t['to_account_id'] === (int)$old['id']);
                $type = $is_credit ? 'credit' : 'debit';
                $ledger_ins = $db->prepare(
                    "INSERT INTO account_ledger (account_id, type, amount, balance_after, ref_type, ref_id, note, category_id, merchant_id, member_id, created_at)
                     VALUES (?, ?, ?, 0, ?, 0, ?, ?, ?, ?, ?)"
                );
                $ref_type = 'personal_' . $t['type'];
                $note = $t['note'] ?: $t['source_label'];
                $ledger_ins->bind_param('isdssiiis', $new_id, $type, $t['amount'], $ref_type, $note, $t['category_id'], $t['merchant_id'], $t['member_id'], $t['created_at']);
                $ledger_ins->execute();
            }
        }
    }

    $db->query("DROP TABLE IF EXISTS pf_transactions");
    $db->query("DROP TABLE pf_accounts");
    log_activity('Personal Finance Migration', 'Finance', 'পুরনো pf_accounts মূল accounts টেবিলে মাইগ্রেট করা হয়েছে');
}

function pf_recalc_account_balance($db, $account_id) {
    if (!$account_id) return;
    $acc = $db->prepare("SELECT opening_balance FROM pf_accounts WHERE id = ?");
    $acc->bind_param('i', $account_id);
    $acc->execute();
    $opening = (float)($acc->get_result()->fetch_assoc()['opening_balance'] ?? 0);

    // ইনকাম/ট্রান্সফার-ইন = জমা, খরচ/ট্রান্সফার-আউট = বিয়োগ
    $sum_stmt = $db->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type='income' AND account_id = ? THEN amount ELSE 0 END),0)
          + COALESCE(SUM(CASE WHEN type='transfer' AND to_account_id = ? THEN amount ELSE 0 END),0)
          + COALESCE(SUM(CASE WHEN type='refund' AND account_id = ? THEN amount ELSE 0 END),0)
          - COALESCE(SUM(CASE WHEN type='expense' AND account_id = ? THEN amount ELSE 0 END),0)
          - COALESCE(SUM(CASE WHEN type='transfer' AND account_id = ? THEN amount ELSE 0 END),0)
          as net
         FROM pf_transactions"
    );
    $sum_stmt->bind_param('iiiii', $account_id, $account_id, $account_id, $account_id, $account_id);
    $sum_stmt->execute();
    $net = (float)($sum_stmt->get_result()->fetch_assoc()['net'] ?? 0);

    $new_balance = $opening + $net;
    $upd = $db->prepare("UPDATE pf_accounts SET current_balance = ? WHERE id = ?");
    $upd->bind_param('di', $new_balance, $account_id);
    $upd->execute();
}
