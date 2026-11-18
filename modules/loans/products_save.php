<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
admin_only();
csrf_verify();
ensure_loan_tables($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: products_list.php");
    exit();
}

$id = (int)($_POST['id'] ?? 0);
$name = clean_input($_POST['name'] ?? '');
$interest_rate = (float)($_POST['interest_rate'] ?? 0);
$interest_type = ($_POST['interest_type'] ?? 'flat') === 'reducing' ? 'reducing' : 'flat';
$term = (int)($_POST['default_term_months'] ?? 12);
$min_amount = (float)($_POST['min_amount'] ?? 0);
$max_amount = (float)($_POST['max_amount'] ?? 0);
$fee = (float)($_POST['processing_fee_percent'] ?? 0);
$penalty = (float)($_POST['penalty_rate_percent'] ?? 0);
$is_active = isset($_POST['is_active']) ? 1 : 0;

if ($name === '') {
    header("Location: products_form.php?id=" . $id . "&error=" . urlencode('প্রোডাক্টের নাম আবশ্যক'));
    exit();
}

if ($id > 0) {
    $stmt = $db->prepare("UPDATE loan_products SET name=?, interest_rate=?, interest_type=?, default_term_months=?, min_amount=?, max_amount=?, processing_fee_percent=?, penalty_rate_percent=?, is_active=? WHERE id=?");
    $stmt->bind_param("sdsiddddii", $name, $interest_rate, $interest_type, $term, $min_amount, $max_amount, $fee, $penalty, $is_active, $id);
    $stmt->execute();
    log_activity('loan_product_update', 'loans', 'লোন প্রোডাক্ট হালনাগাদ: ' . $name);
} else {
    $stmt = $db->prepare("INSERT INTO loan_products (name, interest_rate, interest_type, default_term_months, min_amount, max_amount, processing_fee_percent, penalty_rate_percent, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdsiddddi", $name, $interest_rate, $interest_type, $term, $min_amount, $max_amount, $fee, $penalty, $is_active);
    $stmt->execute();
    log_activity('loan_product_create', 'loans', 'নতুন লোন প্রোডাক্ট: ' . $name);
}

header("Location: products_list.php?saved=1");
exit();
