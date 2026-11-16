<?php
require_once '../../includes/config.php';
require_once '../../includes/unit_engine.php';
ame_unit_ensure($db);
require_permission($db, 'm-inv');
$id = (int)($_GET['id'] ?? 0);
$rows = null;
$product = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT name FROM products WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $list = $db->prepare('SELECT * FROM unit_stock_ledger WHERE product_id=? ORDER BY id DESC LIMIT 200');
    $list->bind_param('i', $id);
    $list->execute();
    $rows = $list->get_result();
}
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/inventory/units.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="mu">
    <h1><?php echo ame_l('স্টক খাতা', 'Stock ledger'); ?></h1>
    <p class="lead"><?php echo $product ? htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') : ame_l('পণ্য বেছে নিন।', 'Choose a product.'); ?></p>
    <nav class="mu-nav">
        <a href="unit_setup.php"><?php echo ame_l('সেটআপ', 'Setup'); ?></a>
        <a href="unit_stock.php"><?php echo ame_l('স্টক', 'Stock'); ?></a>
        <a class="on" href="unit_ledger.php"><?php echo ame_l('খাতা', 'Ledger'); ?></a>
        <a href="unit_adjust.php"><?php echo ame_l('সমন্বয়', 'Adjustment'); ?></a>
    </nav>
    <?php if ($rows): ?>
    <table>
        <tr>
            <th><?php echo ame_l('তারিখ', 'Date'); ?></th>
            <th><?php echo ame_l('লেনদেন', 'Transaction'); ?></th>
            <th class="num"><?php echo ame_l('পরিমাণ', 'Qty'); ?></th>
            <th><?php echo ame_l('ইউনিট', 'Unit'); ?></th>
            <th class="num"><?php echo ame_l('বেস', 'Base'); ?></th>
            <th class="num"><?php echo ame_l('ব্যালেন্স', 'Balance'); ?></th>
        </tr>
        <?php while ($row = $rows->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['txn_date'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($row['kind'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="num"><?php echo htmlspecialchars(rtrim(rtrim((string)$row['entered_qty'], '0'), '.'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(ame_unit_name($db, (int)$row['unit_id']), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="num"><?php echo htmlspecialchars(rtrim(rtrim((string)$row['base_qty'], '0'), '.'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="num"><?php echo htmlspecialchars(rtrim(rtrim((string)$row['balance_base'], '0'), '.'), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?>
    <p class="mu-card"><?php echo ame_l('স্টক পাতা থেকে একটি পণ্যের খাতা খুলুন।', 'Open a product ledger from the stock page.'); ?></p>
    <?php endif; ?>
</div>
<?php include '../../partials/footer.php'; ?>
