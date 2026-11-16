<?php
require_once '../../includes/config.php';
require_once '../../includes/unit_engine.php';
ame_unit_ensure($db);
require_permission($db, 'm-inv');
require_post();
csrf_verify();
$productId = (int)($_POST['product_id'] ?? 0);
if ($productId <= 0) {
    header('Location: unit_setup.php?err=1');
    exit;
}
$purchase = array_map('intval', (array)($_POST['purchase_units'] ?? []));
$sales = array_map('intval', (array)($_POST['sales_units'] ?? []));
$base = ame_unit_base_id($db, $productId);
$ids = array_unique(array_filter(array_merge($purchase, $sales, [$base])));
$db->begin_transaction();
try {
    $db->query('DELETE FROM product_unit_map WHERE product_id=' . $productId);
    $ins = $db->prepare('INSERT INTO product_unit_map (product_id, unit_id, can_purchase, can_sell, is_base) VALUES (?,?,?,?,?)');
    foreach ($ids as $unitId) {
        if ($unitId <= 0) {
            continue;
        }
        $canBuy = in_array($unitId, $purchase, true) || $unitId === $base ? 1 : 0;
        $canSell = in_array($unitId, $sales, true) || $unitId === $base ? 1 : 0;
        $isBase = $unitId === $base ? 1 : 0;
        $ins->bind_param('iiiii', $productId, $unitId, $canBuy, $canSell, $isBase);
        $ins->execute();
    }
    $ins->close();
    $from = (int)($_POST['from_unit_id'] ?? 0);
    $to = (int)($_POST['to_unit_id'] ?? 0);
    $factor = ame_unit_num((string)($_POST['factor'] ?? '0'));
    if ($from > 0 && $to > 0 && $from !== $to && (float)$factor > 0) {
        $dup = $db->prepare('SELECT id FROM product_unit_factors WHERE product_id=? AND from_unit_id=? AND to_unit_id=?');
        $dup->bind_param('iii', $productId, $from, $to);
        $dup->execute();
        if ($dup->get_result()->fetch_assoc()) {
            $dup->close();
            throw new RuntimeException(ame_l('এই রূপান্তর আগেই আছে।', 'This conversion already exists.'));
        }
        $dup->close();
        $rule = $db->prepare('INSERT INTO product_unit_factors (product_id, from_unit_id, to_unit_id, factor) VALUES (?,?,?,?)');
        $rule->bind_param('iiid', $productId, $from, $to, $factor);
        $rule->execute();
        $rule->close();
    }
    $priceUnit = (int)($_POST['price_unit_id'] ?? 0);
    if ($priceUnit > 0) {
        $pp = round((float)($_POST['purchase_price'] ?? 0), 2);
        $sp = round((float)($_POST['sale_price'] ?? 0), 2);
        $wp = round((float)($_POST['wholesale_price'] ?? 0), 2);
        $dp = round((float)($_POST['dealer_price'] ?? 0), 2);
        $price = $db->prepare('INSERT INTO product_unit_prices (product_id, unit_id, purchase_price, sale_price, wholesale_price, dealer_price) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE purchase_price=VALUES(purchase_price), sale_price=VALUES(sale_price), wholesale_price=VALUES(wholesale_price), dealer_price=VALUES(dealer_price)');
        $price->bind_param('iidddd', $productId, $priceUnit, $pp, $sp, $wp, $dp);
        $price->execute();
        $price->close();
    }
    $db->commit();
    header('Location: unit_setup.php?id=' . $productId . '&ok=1');
} catch (Throwable $e) {
    $db->rollback();
    header('Location: unit_setup.php?id=' . $productId . '&err=' . rawurlencode($e->getMessage()));
}
exit;
