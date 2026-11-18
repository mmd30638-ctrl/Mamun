<?php
/**
 * ================================================================
 * Purchase Product Return Module (Supplier Return)
 * ================================================================
 * ক্রয় নগদ বা বাকি দুটোই হতে পারে। রিটার্নে:
 *  ১. cash_refund — ক্যাশ/ব্যাংকে টাকা ফেরত (account_credit)
 *  ২. supplier_credit — ক্রেডিট নোট: credit_balance বাড়ে এবং total_due কমে।
 */

function ensure_purchase_return_schema($db) {
    $db->query("CREATE TABLE IF NOT EXISTS purchase_return_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_id INT NOT NULL,
        action VARCHAR(30) NOT NULL,
        old_status VARCHAR(20) NULL,
        new_status VARCHAR(20) NULL,
        comment VARCHAR(255) NULL,
        user_id INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_return (return_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // সাপ্লায়ারের কাছে জমে থাকা ক্রেডিট (আমাদের প্রাপ্য) ট্র্যাক করার কলাম
    $cols = [];
    $res = $db->query("SHOW COLUMNS FROM suppliers");
    if ($res) {
        while ($row = $res->fetch_assoc()) { $cols[] = $row['Field']; }
    }
    if (!in_array('credit_balance', $cols, true)) {
        @$db->query("ALTER TABLE suppliers ADD COLUMN credit_balance DECIMAL(12,2) NOT NULL DEFAULT 0");
    }

    $db->query("CREATE TABLE IF NOT EXISTS purchase_returns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_no VARCHAR(30) NOT NULL UNIQUE,
        credit_note_no VARCHAR(30) NULL,
        supplier_id INT NULL,
        supplier_name VARCHAR(100) NULL,
        memo_no VARCHAR(50) NULL,
        return_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        refund_type VARCHAR(20) NOT NULL DEFAULT 'supplier_credit',
        account_id INT NULL,
        inspection_status VARCHAR(20) NOT NULL DEFAULT 'pending',
        inspection_note VARCHAR(255) NULL,
        supplier_response VARCHAR(20) NOT NULL DEFAULT 'pending',
        supplier_response_note VARCHAR(255) NULL,
        dispatch_date DATE NULL,
        dispatch_note VARCHAR(255) NULL,
        reason VARCHAR(255) NULL,
        notes VARCHAR(500) NULL,
        reason_category VARCHAR(30) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_by INT NULL,
        reviewed_by INT NULL,
        approved_by INT NULL,
        reviewed_at DATETIME NULL,
        approved_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        INDEX idx_status (status),
        INDEX idx_supplier (supplier_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS purchase_return_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_id INT NOT NULL,
        product_id INT NOT NULL,
        purchased_qty DECIMAL(10,2) NULL,
        returned_qty DECIMAL(10,2) NOT NULL,
        free_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
        unit_price DECIMAL(12,2) NOT NULL,
        line_total DECIMAL(14,2) NOT NULL,
        INDEX idx_return (return_id),
        INDEX idx_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ================= আগে থেকে টেবিল তৈরি থাকলেও নতুন কলাম যোগ করা (নিরাপদ প্যাটার্ন) =================
    $pr_cols = [];
    $pr_res = $db->query("SHOW COLUMNS FROM purchase_returns");
    if ($pr_res) {
        while ($row = $pr_res->fetch_assoc()) { $pr_cols[] = $row['Field']; }
    }
    if (!in_array('reason_category', $pr_cols, true)) {
        @$db->query("ALTER TABLE purchase_returns ADD COLUMN reason_category VARCHAR(30) NULL AFTER reason");
    }
    if (!in_array('notes', $pr_cols, true)) {
        @$db->query("ALTER TABLE purchase_returns ADD COLUMN notes VARCHAR(500) NULL AFTER reason_category");
    }

    $pri_cols = [];
    $pri_res = $db->query("SHOW COLUMNS FROM purchase_return_items");
    if ($pri_res) {
        while ($row = $pri_res->fetch_assoc()) { $pri_cols[] = $row['Field']; }
    }
    if (!in_array('free_qty', $pri_cols, true)) {
        @$db->query("ALTER TABLE purchase_return_items ADD COLUMN free_qty DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER returned_qty");
    }
}

/* স্বয়ংক্রিয় রিটার্ন নম্বর — PRET-20260904-0001 প্যাটার্নে */
function generate_purchase_return_no($db) {
    $today = date('Ymd');
    $prefix = 'PRET-' . $today . '-';
    $stmt = $db->prepare("SELECT COUNT(*) c FROM purchase_returns WHERE return_no LIKE ?");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    return $prefix . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
}

function generate_credit_note_no($db) {
    $today = date('Ymd');
    $prefix = 'CN-' . $today . '-';
    $stmt = $db->prepare("SELECT COUNT(*) c FROM purchase_returns WHERE credit_note_no LIKE ?");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    return $prefix . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
}

/* একই মেমোর কোন পণ্য কত ফেরত হয়ে গেছে (approved/completed থেকে) */
function get_already_purchase_returned_qty($db, $memo_no, $supplier_id, $product_id) {
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(pri.returned_qty), 0) AS total
         FROM purchase_return_items pri
         JOIN purchase_returns pr ON pri.return_id = pr.id
         WHERE pr.memo_no = ? AND pr.supplier_id = ? AND pri.product_id = ?
           AND pr.status IN ('approved', 'completed')"
    );
    $stmt->bind_param('sii', $memo_no, $supplier_id, $product_id);
    $stmt->execute();
    return (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
}

/*
 * একটা Purchase Return "সম্পন্ন" (completed) করা:
 *  ১. স্টক কমা (পণ্য সাপ্লায়ারের কাছে ফেরত যাচ্ছে)
 *  ২. রিফান্ড টাইপ অনুযায়ী — cash_refund হলে account_credit(),
 *     supplier_credit হলে suppliers.credit_balance বৃদ্ধি
 *  ৩. স্ট্যাটাস আপডেট
 * এটা একটা DB ট্রানজেকশনের ভেতরে কল করতে হবে।
 */
function complete_purchase_return_transaction($db, $return_id, $is_english = false) {
    if (!function_exists('ame_require_active_account')) {
        require_once __DIR__ . '/account_functions.php';
    }
    $stmt = $db->prepare("SELECT * FROM purchase_returns WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $return_id);
    $stmt->execute();
    $ret = $stmt->get_result()->fetch_assoc();

    if (!$ret) {
        throw new RuntimeException($is_english ? 'Return not found.' : 'রিটার্ন খুঁজে পাওয়া যায়নি।');
    }
    if ($ret['status'] !== 'approved') {
        throw new RuntimeException($is_english ? 'Only approved returns can be completed.' : 'শুধু অনুমোদিত রিটার্নই সম্পন্ন করা যাবে।');
    }

    $items_stmt = $db->prepare("SELECT * FROM purchase_return_items WHERE return_id = ?");
    $items_stmt->bind_param('i', $return_id);
    $items_stmt->execute();
    $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($items as $item) {
        // পণ্য সাপ্লায়ারকে ফেরত যাচ্ছে — স্টক কমবে (পর্যাপ্ত স্টক না থাকলেও
        // অনুমতি দেওয়া হচ্ছে, কারণ হিসাব-সংশোধনও এই পথে হতে পারে)
        $qty = (float)$item['returned_qty'];
        $unit_id = (int)($item['unit_id'] ?? 0);
        if ($unit_id <= 0 && !empty($ret['memo_no']) && is_file(__DIR__ . '/unit_engine.php')) {
            require_once __DIR__ . '/unit_engine.php';
            $find = $db->prepare('SELECT unit_id FROM purchases WHERE memo_no=? AND product_id=? AND unit_id IS NOT NULL ORDER BY id DESC LIMIT 1');
            $memo = (string)$ret['memo_no'];
            $product_id = (int)$item['product_id'];
            $find->bind_param('si', $memo, $product_id);
            $find->execute();
            $found = $find->get_result()->fetch_assoc();
            $find->close();
            $unit_id = (int)($found['unit_id'] ?? 0);
        }
        if ($unit_id > 0 && function_exists('ame_unit_to_base')) {
            $qty = (float)ame_unit_to_base($db, (int)$item['product_id'], (string)$item['returned_qty'], $unit_id);
        }
        $upd = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $upd->bind_param('di', $qty, $item['product_id']);
        $upd->execute();
        if ($unit_id > 0 && function_exists('ame_unit_move')) {
            ame_unit_move($db, (int)$item['product_id'], ame_unit_num((string)(-1 * $qty)), [
                'kind' => 'purchase_return',
                'entered_qty' => $item['returned_qty'],
                'unit_id' => $unit_id,
                'ref_type' => 'purchase_return',
                'ref_id' => (int)$return_id,
            ], false, false);
        }
    }

    $return_no = $ret['return_no'];
    $amount = (float)$ret['return_amount'];

    if (in_array($ret['refund_type'], ['cash_refund', 'bank_transfer', 'mobile_banking'], true) && $ret['account_id']) {
        ame_require_active_account($db, (int)$ret['account_id']);
        account_credit(
            $ret['account_id'], $amount, 'purchase_return', $return_id,
            $is_english ? 'Purchase return refund (' . $return_no . ')' : 'ক্রয় ফেরত — ফেরত (' . $return_no . ')'
        );
    } elseif ($ret['supplier_id']) {
        $sid = (int)$ret['supplier_id'];
        $lock_sup = $db->prepare('SELECT id, total_due, credit_balance FROM suppliers WHERE id = ? FOR UPDATE');
        if (!$lock_sup) {
            throw new RuntimeException($is_english ? 'Supplier lock failed.' : 'সাপ্লায়ার লক করা যায়নি।');
        }
        $lock_sup->bind_param('i', $sid);
        $lock_sup->execute();
        $sup_row = $lock_sup->get_result()->fetch_assoc();
        $lock_sup->close();
        if (!$sup_row) {
            throw new RuntimeException($is_english ? 'Supplier not found.' : 'সাপ্লায়ার খুঁজে পাওয়া যায়নি।');
        }
        $new_due = round((float)$sup_row['total_due'] - $amount, 2);
        $new_credit = round((float)$sup_row['credit_balance'] + $amount, 2);
        $upd_sup = $db->prepare('UPDATE suppliers SET credit_balance = ?, total_due = ? WHERE id = ?');
        $upd_sup->bind_param('ddi', $new_credit, $new_due, $sid);
        $upd_sup->execute();
        $upd_sup->close();
    }

    $upd_status = $db->prepare(
        "UPDATE purchase_returns SET status = 'completed', updated_at = NOW() WHERE id = ?"
    );
    $upd_status->bind_param('i', $return_id);
    $upd_status->execute();

    log_activity('Purchase Return Completed', 'Purchase Returns', $return_no);

    return true;
}

/*
 * ================================================================
 * Return History Timeline — প্রতিটা স্ট্যাটাস পরিবর্তনের রেকর্ড
 * ================================================================
 */
function log_return_action($db, $return_id, $action, $old_status, $new_status, $comment = null) {
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $db->prepare(
        "INSERT INTO purchase_return_logs (return_id, action, old_status, new_status, comment, user_id)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    // টাইপ: i(return_id) s(action) s(old_status) s(new_status) s(comment) i(user_id) = "issssi" (৬টি প্যারামিটার)
    $stmt->bind_param('issssi', $return_id, $action, $old_status, $new_status, $comment, $user_id);
    $stmt->execute();
}

function get_return_timeline($db, $return_id) {
    $stmt = $db->prepare(
        "SELECT prl.*, u.username
         FROM purchase_return_logs prl
         LEFT JOIN users u ON prl.user_id = u.id
         WHERE prl.return_id = ?
         ORDER BY prl.id ASC"
    );
    $stmt->bind_param('i', $return_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
