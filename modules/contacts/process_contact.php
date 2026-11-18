<?php
require_once '../../includes/config.php';

require_post();
csrf_verify();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $group_id = (int)$_POST['group_id'];
    $name = clean_input($_POST['name']);
    $phone = clean_input($_POST['phone']);
    $address = clean_input($_POST['address']);

    $stmt = $db->prepare("INSERT INTO contacts (group_id, name, phone, address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $group_id, $name, $phone, $address);
    if ($stmt->execute()) {
        echo "<script>alert('কন্টাক্ট সফলভাবে সেভ হয়েছে!'); window.location='list.php';</script>";
    }
}
?>