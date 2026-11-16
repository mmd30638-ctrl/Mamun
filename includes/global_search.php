<?php
require_once 'config.php';
$q = trim((string)($_GET['q'] ?? ''));

$results = [];

if ($q !== '') {
    // ১. পণ্য সার্চ
    $like = '%' . $q . '%';
    $stmt = $db->prepare('SELECT id, name, \'product\' as type FROM products WHERE ' . ame_product_option_sql('status') . ' AND name LIKE ? LIMIT 3');
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $prods = $stmt->get_result();

    // ২. কাস্টমার সার্চ
    $pick = function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db) : '1=1';
    $stmt2 = $db->prepare("SELECT id, name, 'customer' as type FROM customers WHERE {$pick} AND (name LIKE ? OR phone LIKE ?) LIMIT 3");
    $stmt2->bind_param("ss", $like, $like);
    $stmt2->execute();
    $custs = $stmt2->get_result();

    // ৩. ইনভয়েস সার্চ (শুধু সংখ্যা হলেই আইডি ম্যাচ করার চেষ্টা করবে)
    $sales = null;
    if (ctype_digit($q)) {
        $sale_id = (int)$q;
        $stmt3 = $db->prepare("SELECT id, 'sale' as type FROM sales WHERE id = ? LIMIT 1");
        $stmt3->bind_param("i", $sale_id);
        $stmt3->execute();
        $sales = $stmt3->get_result();
    }

    while ($r = $prods->fetch_assoc()) $results[] = $r;
    while ($r = $custs->fetch_assoc()) $results[] = $r;
    if ($sales) { while ($r = $sales->fetch_assoc()) $results[] = $r; }
}

echo json_encode($results);
