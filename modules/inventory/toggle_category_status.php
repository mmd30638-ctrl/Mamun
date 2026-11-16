<?php
require_once '../../includes/config.php';
require_post();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: categories.php');
    exit;
}

$stmt = $db->prepare("SELECT status FROM categories WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row) {
    $new_status = ((int)$row['status'] === 1) ? 0 : 1;
    $upd = $db->prepare("UPDATE categories SET status = ? WHERE id = ?");
    $upd->bind_param("ii", $new_status, $id);
    $upd->execute();
}

header('Location: categories.php');
exit;
?>
