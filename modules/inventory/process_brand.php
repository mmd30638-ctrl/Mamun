<?php
require_once '../../includes/config.php';


require_post();
csrf_verify();
$name = clean_input($_POST['name'] ?? '');
if ($name !== '') {
    $stmt = $db->prepare("INSERT INTO brands (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
}
header('Location: brands.php');
exit;
?>
