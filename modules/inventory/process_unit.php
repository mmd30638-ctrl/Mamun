<?php
require_once '../../includes/config.php';


require_post();
csrf_verify();
$unit_name = clean_input($_POST['unit_name'] ?? '');
if ($unit_name !== '') {
    $stmt = $db->prepare("INSERT INTO product_units (unit_name) VALUES (?)");
    $stmt->bind_param("s", $unit_name);
    $stmt->execute();
}
header('Location: units.php');
exit;
?>
