<?php
/**
 * ক্রয় পেজের সাপ্লায়ার বক্সে কাস্টমার-তালিকায় "সাপ্লায়ার" টিক দেওয়া
 * নামও যাতে খুঁজে পাওয়া যায় — সেই বক্স থেকে বাছাই করলে এই এন্ডপয়েন্ট
 * ওই কাস্টমারের জন্য সাপ্লায়ার রো খুঁজে (ফোন, তারপর নামে) বা তৈরি করে
 * আসল suppliers.id ফেরত দেয়। ক্রয়ের হিসাব আগের মতো suppliers দিয়েই হয়।
 */
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/lang_helper.php';
require_once __DIR__ . '/../../../includes/supplier_functions.php';
admin_only();
ame_supplier_schema_boot($db);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => ame_l('অনুমোদিত অনুরোধ নয়।', 'Invalid request method.')], JSON_UNESCAPED_UNICODE);
    exit;
}
csrf_verify();

$customer_id = (int)($_POST['customer_id'] ?? 0);
if ($customer_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ কাস্টমার ঠিক হয়নি।', '⚠️ Invalid customer.')], JSON_UNESCAPED_UNICODE);
    exit;
}

$cstmt = $db->prepare("SELECT id, customer_code, name, phone, address, payable_balance, receivable_balance, is_supplier, is_customer, linked_supplier_id FROM customers WHERE id = ? LIMIT 1");
if (!$cstmt) {
    $cstmt = $db->prepare("SELECT id, name, phone, address, payable_balance, receivable_balance FROM customers WHERE id = ? LIMIT 1");
}
if (!$cstmt) {
    $cstmt = $db->prepare("SELECT id, name, phone, address FROM customers WHERE id = ? LIMIT 1");
}
if (!$cstmt) {
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ আবার চেষ্টা করুন।', '⚠️ Please try again.')], JSON_UNESCAPED_UNICODE);
    exit;
}
$cstmt->bind_param('i', $customer_id);
$cstmt->execute();
$customer = $cstmt->get_result()->fetch_assoc();
$cstmt->close();

if (!$customer) {
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ কাস্টমার খুঁজে পাওয়া যায়নি।', '⚠️ Customer not found.')], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim((string)($customer['name'] ?? ''));
$phone = trim((string)($customer['phone'] ?? ''));
$address = trim((string)($customer['address'] ?? ''));
$customer_code = trim((string)($customer['customer_code'] ?? ''));
if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ কাস্টমারের নাম খালি।', '⚠️ Customer name is empty.')], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!ame_customer_row_is_directory($customer)) {
    $found = null;
    $link_id = (int)($customer['linked_supplier_id'] ?? 0);
    $on = ame_supplier_status_active_sql();
    $live = ame_supplier_live_sql($db);
    if ($link_id > 0) {
        $lst = $db->prepare("SELECT id, name, phone, address, total_due FROM suppliers WHERE id = ? AND {$on} AND {$live} LIMIT 1");
        if ($lst) {
            $lst->bind_param('i', $link_id);
            $lst->execute();
            $found = $lst->get_result()->fetch_assoc();
            $lst->close();
        }
    }
    if (!$found && $phone !== '') {
        $pst = $db->prepare("SELECT id, name, phone, address, total_due FROM suppliers WHERE phone = ? AND {$on} AND {$live} LIMIT 1");
        if ($pst) {
            $pst->bind_param('s', $phone);
            $pst->execute();
            $found = $pst->get_result()->fetch_assoc();
            $pst->close();
        }
    }
    if (!$found) {
        $nst = $db->prepare("SELECT id, name, phone, address, total_due FROM suppliers WHERE name = ? AND {$on} AND {$live} LIMIT 1");
        if ($nst) {
            $nst->bind_param('s', $name);
            $nst->execute();
            $found = $nst->get_result()->fetch_assoc();
            $nst->close();
        }
    }
    if (!$found) {
        echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ এই নাম সাপ্লায়ার তালিকায় নেই। সাপ্লায়ার তালিকা থেকে বেছে নিন।', '⚠️ This name is not in the supplier list. Pick it from the supplier list.')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'status' => 'success',
        'created' => false,
        'use_as' => 'supplier',
        'customer_id' => 0,
        'customer_balance' => 0,
        'supplier' => [
            'id' => (int)$found['id'],
            'name' => (string)($found['name'] ?? $name),
            'phone' => (string)($found['phone'] ?? ''),
            'address' => (string)($found['address'] ?? ''),
            'total_due' => (float)($found['total_due'] ?? 0),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// কাস্টমারের আসল ব্যালেন্স নির্ধারণ:
// পজিটিভ (+) = কাস্টমারের বকেয়া (দোকান পাবে)
// নেগেটিভ (−) = কাস্টমারের পাওনা (দোকানের দেনা)
$rec_bal = round((float)($customer['receivable_balance'] ?? 0), 2);
$pay_bal = round((float)($customer['payable_balance'] ?? 0), 2);
if (abs($rec_bal) > 0.009) {
    $customer_bal = $rec_bal;
} elseif ($pay_bal > 0.009) {
    $customer_bal = -$pay_bal;
} else {
    $customer_bal = 0.0;
}

$found = null;
if ($phone !== '') {
    $pstmt = $db->prepare("SELECT id, name, phone, address, total_due FROM suppliers WHERE phone = ? AND status = 'active' AND purged_at IS NULL LIMIT 1");
    if ($pstmt) {
        $pstmt->bind_param('s', $phone);
        $pstmt->execute();
        $found = $pstmt->get_result()->fetch_assoc();
        $pstmt->close();
    }
}
if (!$found) {
    $nstmt = $db->prepare("SELECT id, name, phone, address, total_due FROM suppliers WHERE name = ? AND status = 'active' AND purged_at IS NULL LIMIT 1");
    if ($nstmt) {
        $nstmt->bind_param('s', $name);
        $nstmt->execute();
        $found = $nstmt->get_result()->fetch_assoc();
        $nstmt->close();
    }
}
$carry_due = round(-$customer_bal, 2);
$mark = $db->prepare('UPDATE customers SET is_supplier = 1 WHERE id = ?');
if ($mark) {
    $mark->bind_param('i', $customer_id);
    $mark->execute();
    $mark->close();
}

if ($found) {
    $sid = (int)$found['id'];
    if ($phone !== '' && trim((string)($found['phone'] ?? '')) === '' && $address !== '' && trim((string)($found['address'] ?? '')) === '') {
        $ustmt = $db->prepare("UPDATE suppliers SET phone = ?, address = ? WHERE id = ?");
        if ($ustmt) {
            $ustmt->bind_param('ssi', $phone, $address, $sid);
            $ustmt->execute();
            $ustmt->close();
            $found['phone'] = $phone;
            $found['address'] = $address;
        }
    }
    $hist = 0;
    $hstmt = $db->prepare('SELECT COUNT(*) AS n FROM purchases WHERE supplier_id = ?');
    if ($hstmt) {
        $hstmt->bind_param('i', $sid);
        $hstmt->execute();
        $hrow = $hstmt->get_result()->fetch_assoc();
        $hstmt->close();
        $hist = (int)($hrow['n'] ?? 0);
    }
    $existing_due = round((float)($found['total_due'] ?? 0), 2);
    if ($hist === 0 && abs($existing_due) < 0.009 && abs($carry_due) > 0.009) {
        $dstmt = $db->prepare('UPDATE suppliers SET total_due = ?, opening_balance = ? WHERE id = ?');
        if (!$dstmt) {
            $dstmt = $db->prepare('UPDATE suppliers SET total_due = ? WHERE id = ?');
            if ($dstmt) {
                $dstmt->bind_param('di', $carry_due, $sid);
                $dstmt->execute();
                $dstmt->close();
            }
        } else {
            $dstmt->bind_param('ddi', $carry_due, $carry_due, $sid);
            $dstmt->execute();
            $dstmt->close();
        }
        $found['total_due'] = $carry_due;
    }
    echo json_encode([
        'status' => 'success',
        'created' => false,
        'customer_id' => (int)$customer['id'],
        'customer_balance' => $customer_bal,
        'customer' => [
            'id' => (int)$customer['id'],
            'customer_code' => $customer_code,
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'balance' => $customer_bal,
            'receivable_balance' => $rec_bal,
            'payable_balance' => $pay_bal,
        ],
        'supplier' => [
            'id' => $sid,
            'name' => (string)($found['name'] ?? $name),
            'phone' => (string)($found['phone'] ?? ''),
            'address' => (string)($found['address'] ?? ''),
            'total_due' => (float)($found['total_due'] ?? 0),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // দোকান পাবে (+) হলে সাপ্লায়ার দেনা ঋণাত্মক; দোকান দেবে (−) হলে সাপ্লায়ার দেনা ধনাত্মক।
    $istmt = $db->prepare('INSERT INTO suppliers (name, phone, address, total_due, opening_balance) VALUES (?, ?, ?, ?, ?)');
    if ($istmt) {
        $istmt->bind_param('sssdd', $name, $phone, $address, $carry_due, $carry_due);
        $istmt->execute();
        $new_id = (int)$istmt->insert_id;
        $istmt->close();
    } else {
        $istmt = $db->prepare('INSERT INTO suppliers (name, phone, address, total_due) VALUES (?, ?, ?, ?)');
        if (!$istmt) {
            throw new RuntimeException('prepare failed');
        }
        $istmt->bind_param('sssd', $name, $phone, $address, $carry_due);
        $istmt->execute();
        $new_id = (int)$istmt->insert_id;
        $istmt->close();
    }
    if (function_exists('log_activity')) {
        log_activity('Customer Supplier Linked', 'Suppliers', $name . ' (কাস্টমার #' . $customer_id . ' থেকে)');
    }
    echo json_encode([
        'status' => 'success',
        'created' => true,
        'customer_id' => (int)$customer['id'],
        'customer_balance' => $customer_bal,
        'customer' => [
            'id' => (int)$customer['id'],
            'customer_code' => $customer_code,
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'balance' => $customer_bal,
            'receivable_balance' => $rec_bal,
            'payable_balance' => $pay_bal,
        ],
        'supplier' => [
            'id' => $new_id,
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'total_due' => $carry_due,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Resolve customer supplier failed: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ সাপ্লায়ার মিলাতে সমস্যা হয়েছে। আবার চেষ্টা করুন।', '⚠️ Could not link the supplier. Please try again.')], JSON_UNESCAPED_UNICODE);
}
