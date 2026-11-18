<?php
require_once '../../includes/config.php';
require_once '../../includes/unit_engine.php';
ame_unit_ensure($db);
require_permission($db, 'm-inv');
if (strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') {
    http_response_code(403);
    die(ame_l('স্টক সমন্বয় শুধু অ্যাডমিন করতে পারবেন।', 'Only an admin can adjust stock.'));
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $productId = (int)($_POST['product_id'] ?? 0);
    $unitId = (int)($_POST['unit_id'] ?? 0);
    $qty = (string)($_POST['qty'] ?? '0');
    try {
        $db->begin_transaction();
        $base = ame_unit_to_base($db, $productId, $qty, $unitId);
        ame_unit_move($db, $productId, $base, [
            'kind' => 'adjustment',
            'entered_qty' => $qty,
            'unit_id' => $unitId,
            'ref_type' => 'adjustment',
            'note' => (string)($_POST['note'] ?? ''),
        ], true);
        $db->commit();
        header('Location: unit_ledger.php?id=' . $productId);
        exit;
    } catch (Throwable $e) {
        $db->rollback();
        $err = $e->getMessage();
    }
}
$products = $db->query('SELECT id, name FROM products WHERE ' . ame_product_option_sql('status') . ' ORDER BY name LIMIT 300');
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/inventory/units.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="mu">
    <h1><?php echo ame_l('স্টক সমন্বয়', 'Stock adjustment'); ?></h1>
    <p class="lead"><?php echo ame_l('মাইনাস দিলে স্টক কমবে। ইউনিট অনুযায়ী বেস স্টকে রূপান্তর হবে।', 'A minus quantity reduces stock. The unit is converted into the base stock.'); ?></p>
    <nav class="mu-nav">
        <a href="unit_setup.php"><?php echo ame_l('সেটআপ', 'Setup'); ?></a>
        <a href="unit_stock.php"><?php echo ame_l('স্টক', 'Stock'); ?></a>
        <a href="unit_ledger.php"><?php echo ame_l('খাতা', 'Ledger'); ?></a>
        <a class="on" href="unit_adjust.php"><?php echo ame_l('সমন্বয়', 'Adjustment'); ?></a>
    </nav>
    <?php if ($err !== ''): ?><p class="err"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <form class="mu-card" method="post">
        <?php echo csrf_field(); ?>
        <label><?php echo ame_l('পণ্য', 'Product'); ?><select name="product_id" required><?php if ($products) while ($p = $products->fetch_assoc()): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endwhile; ?></select></label>
        <label><?php echo ame_l('পরিমাণ', 'Quantity'); ?><input name="qty" required placeholder="-5"></label>
        <label><?php echo ame_l('ইউনিট', 'Unit'); ?><select name="unit_id" required><?php $all = $db->query('SELECT id, unit_name FROM product_units ORDER BY unit_name'); if ($all) while ($u = $all->fetch_assoc()): ?><option value="<?php echo (int)$u['id']; ?>"><?php echo htmlspecialchars($u['unit_name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endwhile; ?></select></label>
        <label><?php echo ame_l('নোট', 'Note'); ?><input name="note"></label>
        <button type="submit"><?php echo ame_l('সমন্বয় করুন', 'Adjust'); ?></button>
    </form>
</div>
<?php include '../../partials/footer.php'; ?>
