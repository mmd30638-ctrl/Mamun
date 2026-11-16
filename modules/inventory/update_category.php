<?php
require_once '../../includes/config.php';
require_once '../../includes/inventory_functions.php';

require_post();
csrf_verify();
ame_ensure_category_archive($db);

$id = (int)($_POST['id'] ?? 0);
$name = trim((string)clean_input($_POST['name'] ?? ''));
$name = function_exists('mb_substr') ? mb_substr($name, 0, 120, 'UTF-8') : substr($name, 0, 120);

if ($id < 1 || $name === '') {
    $_SESSION['cat_error'] = 'ক্যাটাগরির নাম দিন।';
    header('Location: categories.php');
    exit;
}

$dup = $db->prepare('SELECT id FROM categories WHERE name=? AND id<>? AND (archived=0 OR archived IS NULL) LIMIT 1');
$dup->bind_param('si', $name, $id);
$dup->execute();
if ($dup->get_result()->num_rows > 0) {
    $dup->close();
    $_SESSION['cat_error'] = 'এই নামে আরেকটি ক্যাটাগরি আছে।';
    header('Location: categories.php');
    exit;
}
$dup->close();

$stmt = $db->prepare('UPDATE categories SET name=? WHERE id=?');
$stmt->bind_param('si', $name, $id);
$stmt->execute();
$stmt->close();

$_SESSION['cat_flash'] = 'নাম বদলানো হয়েছে। পুরোনো বিক্রয় ও পার্টনার রিপোর্ট আগের মতো আছে।';
header('Location: categories.php');
exit;
