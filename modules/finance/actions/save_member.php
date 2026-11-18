<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/pf_functions.php';
admin_only();
ensure_pf_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../accounts.php');
    exit;
}
csrf_verify();

$name = trim($_POST['name'] ?? '');
$role = in_array($_POST['role'] ?? '', ['father', 'mother', 'brother', 'sister', 'other'], true) ? $_POST['role'] : 'other';

if ($name === '') {
    header('Location: ../accounts.php');
    exit;
}

$stmt = $db->prepare("INSERT INTO pf_family_members (name, role) VALUES (?, ?)");
$stmt->bind_param('ss', $name, $role);
$stmt->execute();

header('Location: ../accounts.php');
exit;
