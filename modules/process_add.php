<?php
require_once '../../includes/config.php';

require_post();
csrf_verify();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = clean_input($_POST['name']);
    $phone = clean_input($_POST['phone']);
    $address = clean_input($_POST['address']);
    $stmt = $db->prepare("INSERT INTO suppliers (name, phone, address) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $phone, $address);
    if ($stmt->execute()) { redirect('list.php'); }
}
?>