<?php
require_once '../../includes/config.php';
require_once '../../includes/unit_engine.php';
ame_unit_ensure($db);
require_permission($db, 'm-inv');
$id = (int)($_GET['id'] ?? 0);
$product = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT id, name, sku, barcode, unit_id, stock FROM products WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
$catalog = $db->query('SELECT id, unit_name FROM product_units ORDER BY unit_name');
$units = [];
if ($catalog) {
    while ($row = $catalog->fetch_assoc()) {
        $units[] = $row;
    }
}
$rules = [];
$map = [];
if ($product) {
    $r = $db->prepare('SELECT * FROM product_unit_factors WHERE product_id=?');
    $r->bind_param('i', $id);
    $r->execute();
    $rules = $r->get_result()->fetch_all(MYSQLI_ASSOC);
    $r->close();
    $m = $db->prepare('SELECT * FROM product_unit_map WHERE product_id=?');
    $m->bind_param('i', $id);
    $m->execute();
    foreach ($m->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $map[(int)$row['unit_id']] = $row;
    }
    $m->close();
}
$q = trim((string)($_GET['q'] ?? ''));
$list = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $find = $db->prepare('SELECT id, name, sku FROM products WHERE ' . ame_product_option_sql('status') . ' AND (name LIKE ? OR sku LIKE ?) ORDER BY name LIMIT 20');
    $find->bind_param('ss', $like, $like);
    $find->execute();
    $list = $find->get_result()->fetch_all(MYSQLI_ASSOC);
    $find->close();
}
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/inventory/units.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="mu">
    <h1><?php echo ame_l('ইউনিট ও রূপান্তর', 'Units and conversion'); ?></h1>
    <p class="lead"><?php echo ame_l('প্রতিটি পণ্যের রূপান্তর আলাদা। পুরনো স্টক সংখ্যা বদলায় না।', 'Each product has its own conversion. Existing stock numbers stay as they are.'); ?></p>
    <nav class="mu-nav">
        <a class="on" href="unit_setup.php"><?php echo ame_l('সেটআপ', 'Setup'); ?></a>
        <a href="unit_stock.php"><?php echo ame_l('স্টক', 'Stock'); ?></a>
        <a href="unit_ledger.php"><?php echo ame_l('খাতা', 'Ledger'); ?></a>
        <a href="unit_adjust.php"><?php echo ame_l('সমন্বয়', 'Adjustment'); ?></a>
    </nav>
    <?php if (!empty($_GET['ok'])): ?><p class="ok"><?php echo ame_l('সংরক্ষণ হয়েছে।', 'Saved.'); ?></p><?php endif; ?>
    <?php if (!empty($_GET['err'])): ?><p class="err"><?php echo htmlspecialchars((string)$_GET['err'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <form method="get" class="mu-card">
        <label><?php echo ame_l('পণ্য খুঁজুন', 'Find a product'); ?><input name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"></label>
        <button type="submit"><?php echo ame_l('খুঁজুন', 'Search'); ?></button>
        <?php foreach ($list as $row): ?>
            <p><a href="?id=<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars($row['name'] . ' ' . $row['sku'], ENT_QUOTES, 'UTF-8'); ?></a></p>
        <?php endforeach; ?>
    </form>
    <?php if ($product): ?>
    <p class="hint"><?php echo htmlspecialchars(ame_unit_hint($db, $id) ?: ame_l('এখনো কোনো রূপান্তর নেই।', 'No conversion yet.'), ENT_QUOTES, 'UTF-8'); ?></p>
    <form method="post" action="unit_save.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="product_id" value="<?php echo $id; ?>">
        <div class="mu-grid">
            <section class="mu-card">
                <h2><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><?php echo ame_l('এসকেইউ', 'SKU'); ?>: <?php echo htmlspecialchars((string)$product['sku'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo ame_l('বারকোড', 'Barcode'); ?>: <?php echo htmlspecialchars((string)$product['barcode'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><?php echo ame_l('বর্তমান স্টক এই বেস ইউনিটে আছে', 'Current stock is stored in this base unit'); ?>: <b><?php echo htmlspecialchars(ame_unit_name($db, (int)$product['unit_id']), ENT_QUOTES, 'UTF-8'); ?></b> · <?php echo htmlspecialchars(ame_unit_stock_base($db, $id), ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="mu-checks">
                    <?php foreach ($units as $unit): $uid = (int)$unit['id']; ?>
                    <label><input type="checkbox" name="purchase_units[]" value="<?php echo $uid; ?>" <?php echo !empty($map[$uid]['can_purchase']) || $uid === (int)$product['unit_id'] ? 'checked' : ''; ?>> <?php echo ame_l('ক্রয়', 'Buy'); ?> <?php echo htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8'); ?></label>
                    <label><input type="checkbox" name="sales_units[]" value="<?php echo $uid; ?>" <?php echo !empty($map[$uid]['can_sell']) || $uid === (int)$product['unit_id'] ? 'checked' : ''; ?>> <?php echo ame_l('বিক্রি', 'Sell'); ?> <?php echo htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8'); ?></label>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="mu-card">
                <h2><?php echo ame_l('রূপান্তর', 'Conversion'); ?></h2>
                <label><?php echo ame_l('থেকে', 'From'); ?><select name="from_unit_id"><?php foreach ($units as $unit): ?><option value="<?php echo (int)$unit['id']; ?>"><?php echo htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></label>
                <label><?php echo ame_l('পর্যন্ত', 'To'); ?><select name="to_unit_id"><?php foreach ($units as $unit): ?><option value="<?php echo (int)$unit['id']; ?>"><?php echo htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></label>
                <label><?php echo ame_l('১ থেকে = কত', '1 from unit equals'); ?><input name="factor" inputmode="decimal" placeholder="20"></label>
                <h2><?php echo ame_l('ইউনিটের দাম', 'Unit price'); ?></h2>
                <label><?php echo ame_l('ইউনিট', 'Unit'); ?><select name="price_unit_id"><?php foreach ($units as $unit): ?><option value="<?php echo (int)$unit['id']; ?>"><?php echo htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></label>
                <label><?php echo ame_l('ক্রয় দর', 'Purchase price'); ?><input name="purchase_price" inputmode="decimal" value="0"></label>
                <label><?php echo ame_l('খুচরা দর', 'Retail price'); ?><input name="sale_price" inputmode="decimal" value="0"></label>
                <label><?php echo ame_l('পাইকারি দর', 'Wholesale price'); ?><input name="wholesale_price" inputmode="decimal" value="0"></label>
                <label><?php echo ame_l('ডিলার দর', 'Dealer price'); ?><input name="dealer_price" inputmode="decimal" value="0"></label>
                <button type="submit"><?php echo ame_l('সংরক্ষণ', 'Save'); ?></button>
            </section>
        </div>
    </form>
    <table>
        <tr><th><?php echo ame_l('নিয়ম', 'Rule'); ?></th></tr>
        <?php foreach ($rules as $rule): ?>
        <tr><td>1 <?php echo htmlspecialchars(ame_unit_name($db, (int)$rule['from_unit_id']), ENT_QUOTES, 'UTF-8'); ?> = <?php echo htmlspecialchars(rtrim(rtrim((string)$rule['factor'], '0'), '.'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars(ame_unit_name($db, (int)$rule['to_unit_id']), ENT_QUOTES, 'UTF-8'); ?></td></tr>
        <?php endforeach; ?>
    </table>
    <?php elseif ($units === []): ?>
    <p class="err"><?php echo ame_l('আগে পণ্যের ইউনিটের নাম যোগ করুন।', 'Add unit names first.'); ?> <a href="units.php"><?php echo ame_l('ইউনিট', 'Units'); ?></a></p>
    <?php endif; ?>
</div>
<?php include '../../partials/footer.php'; ?>
