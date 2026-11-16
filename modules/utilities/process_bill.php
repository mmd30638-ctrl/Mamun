<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';

require_post();
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $p_id = (int)$_POST['partner_id'];
    $amt = (float)$_POST['amount'];
    $month = $_POST['month_year'];

    $db->begin_transaction();
    try {
        // ১. কালেকশন টেবিলে সেভ
        $stmt = $db->prepare("INSERT INTO bill_collections (partner_id, amount, month_year) VALUES (?, ?, ?)");
        $stmt->bind_param("ids", $p_id, $amt, $month);
        $stmt->execute();
        $ref_id = $db->insert_id;

        // ২. মেইন ক্যাশে টাকা যোগ করা (Credit)
        // আমরা এখানে ১ নম্বর ডিফল্ট অ্যাকাউন্ট ব্যবহার করছি
        account_credit(1, $amt, 'shared_bill', $ref_id, "WiFi Bill Collection - $month");

        $db->commit();
        echo "<script>alert('টাকা সফলভাবে আদায় হয়েছে!'); window.location='shared_bills.php';</script>";
    } catch (Exception $e) { $db->rollback(); echo $e->getMessage(); }
}
?>