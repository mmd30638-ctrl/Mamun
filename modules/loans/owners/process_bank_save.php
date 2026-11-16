<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);
require_post();
csrf_verify();

$action = (string)($_POST['action'] ?? 'save');
$id = (int)($_POST['id'] ?? 0);

if ($action === 'toggle') {
    if ($id > 0) {
        $db->query("UPDATE banks SET status = 1 - status WHERE id = " . $id);
        log_activity('Bank Status Toggled', 'owner_loans', "ব্যাংক #$id এর অবস্থা পরিবর্তন করা হলো");
    }
    redirect('banks_list.php');
}

$bank_name = clean_input($_POST['bank_name'] ?? '');
$branch_name = clean_input($_POST['branch_name'] ?? '');
$branch_address = clean_input($_POST['branch_address'] ?? '');
$account_info = clean_input($_POST['account_info'] ?? '');
$contact_info = clean_input($_POST['contact_info'] ?? '');

if ($bank_name === '') {
    redirect('bank_form.php?id=' . $id . '&err=' . rawurlencode(ame_l('ব্যাংকের নাম আবশ্যক।', 'Bank name is required.')));
}

if ($id > 0) {
    $stmt = $db->prepare("UPDATE banks SET bank_name=?, branch_name=?, branch_address=?, account_info=?, contact_info=? WHERE id=?");
    $stmt->bind_param('sssssi', $bank_name, $branch_name, $branch_address, $account_info, $contact_info, $id);
    $stmt->execute();
    $stmt->close();
    log_activity('Bank Updated', 'owner_loans', "ব্যাংক #$id ({$bank_name}) আপডেট করা হলো");
} else {
    $stmt = $db->prepare("INSERT INTO banks (bank_name, branch_name, branch_address, account_info, contact_info, status) VALUES (?,?,?,?,?,1)");
    $stmt->bind_param('sssss', $bank_name, $branch_name, $branch_address, $account_info, $contact_info);
    $stmt->execute();
    $id = $db->insert_id;
    $stmt->close();
    log_activity('Bank Created', 'owner_loans', "নতুন ব্যাংক তৈরি: #$id ({$bank_name})");
}
redirect('banks_list.php');
