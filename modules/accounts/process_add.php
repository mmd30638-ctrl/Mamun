<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';

require_post();
csrf_verify();
ensure_accounts_schema($db);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = clean_input($_POST['account_name']);
    $type = (string) ($_POST['account_type'] ?? 'Cash');
    if (!in_array($type, ['Cash', 'Bank', 'Mobile'], true)) {
        $type = 'Cash';
    }
    $balance = (float)$_POST['balance'];
    $threshold_raw = trim($_POST['low_balance_threshold'] ?? '');
    $threshold = $threshold_raw !== '' ? (float)$threshold_raw : null;
    $alert_phone_raw = trim($_POST['alert_phone'] ?? '');
    $alert_phone = $alert_phone_raw !== '' ? $alert_phone_raw : null;
    $account_number = $type === 'Bank' ? trim((string) ($_POST['account_number'] ?? '')) : '';
    $bank_branch = $type === 'Bank' ? trim((string) ($_POST['bank_branch'] ?? '')) : '';
    $account_number = $account_number !== '' ? $account_number : null;
    $bank_branch = $bank_branch !== '' ? $bank_branch : null;

    // ⭐ অ্যাকাউন্ট সবসময় ব্যালেন্স ০ দিয়ে তৈরি হবে — প্রাথমিক ব্যালেন্স নিচে লেজার এন্ট্রি দিয়ে যোগ হবে
    $stmt = $db->prepare("INSERT INTO accounts (account_name, account_type, balance, low_balance_threshold, alert_phone, account_number, bank_branch) VALUES (?, ?, 0, ?, ?, ?, ?)");
    $stmt->bind_param("ssdsss", $name, $type, $threshold, $alert_phone, $account_number, $bank_branch);

    if ($stmt->execute()) {
        $new_id = $db->insert_id;

        if ($balance > 0) {
            $db->begin_transaction();
            try {
                account_credit($new_id, $balance, 'opening_balance', $new_id, 'প্রারম্ভিক ব্যালেন্স (অ্যাকাউন্ট খোলার সময়)');
                $db->commit();
            } catch (Exception $e) {
                $db->rollback();
                die('❌ প্রাথমিক ব্যালেন্স যোগে সমস্যা: ' . htmlspecialchars($e->getMessage()) . ' <a href="list.php">অ্যাকাউন্ট তালিকায় যান</a>');
            }
        }

        if (function_exists('log_activity')) {
            log_activity('Account Added', 'Accounts', $name, [
                'action_type' => 'add',
                'entity_type' => 'account',
                'entity_id' => (string)(int)$new_id,
                'reference' => $name,
                'amount_total' => $balance > 0 ? (float)$balance : null,
            ]);
        }

        echo "<script>alert('অ্যাকাউন্ট সফলভাবে তৈরি হয়েছে'); window.location='list.php';</script>";
    } else {
        echo "এরর: " . htmlspecialchars($stmt->error);
    }
}
?>
