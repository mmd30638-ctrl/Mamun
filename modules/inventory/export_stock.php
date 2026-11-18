<?php
require_once '../../includes/config.php';

$filename = "Stock_Report_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// বাংলা ফন্টের জন্য BOM যোগ করা (Excel এ যাতে সঠিক দেখায়)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// হেডার কলাম
fputcsv($output, ['পণ্যের নাম', 'ক্রয় মূল্য', 'খুচরা মূল্য', 'পাইকারি মূল্য', 'বর্তমান স্টক']);

// ডাটাবেজ থেকে ডাটা নিয়ে রাইট করা
$res = $db->query("SELECT name, purchase_price, sale_price, wholesale_price, stock FROM products WHERE status='active' ORDER BY name ASC");
while($row = $res->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit;