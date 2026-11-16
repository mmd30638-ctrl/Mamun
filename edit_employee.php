<?php
/** @deprecated — নতুন HRM মডিউলে রিডাইরেক্ট */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$url = '/modules/hrm/employee_edit.php';
if ($id > 0) {
    $url .= '?id=' . $id;
}
header('Location: ' . $url, true, 301);
exit();
