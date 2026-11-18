<?php
require_once '../../includes/config.php';


require_post();
csrf_verify();
$name = clean_input($_POST['name'] ?? '');
if ($name !== '') {
    $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
}
header('Location: categories.php');
exit;
?>
