<?php
require_once '../../includes/config.php';
require_once '../../includes/unit_engine.php';
ame_unit_ensure($db);
require_permission($db, 'm-inv');
$rows = $db->query('SELECT DISTINCT p.id, p.name, p.sku, p.stock, p.unit_id FROM products p JOIN product_unit_factors f ON f.product_id=p.id ORDER BY p.name LIMIT 200');
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/inventory/units.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="mu">
    <h1><?php echo ame_l('ইউনিট অনুযায়ী স্টক', 'Stock by unit'); ?></h1>
    <p class="lead"><?php echo ame_l('বেস স্টক আগের সংখ্যা। পাশে সমতুল্য ইউনিট।', 'Base stock is the existing number, with the equivalent unit beside it.'); ?></p>
    <nav class="mu-nav">
        <a href="unit_setup.php"><?php echo ame_l('সেটআপ', 'Setup'); ?></a>
        <a class="on" href="unit_stock.php"><?php echo ame_l('স্টক', 'Stock'); ?></a>
        <a href="unit_ledger.php"><?php echo ame_l('খাতা', 'Ledger'); ?></a>
        <a href="unit_adjust.php"><?php echo ame_l('সমন্বয়', 'Adjustment'); ?></a>
    </nav>
    <div class="mu-grid">
        <?php if ($rows) while ($row = $rows->fetch_assoc()):
            $base = ame_unit_stock_base($db, (int)$row['id']);
            $baseId = (int)$row['unit_id'];
            $edges = ame_unit_graph($db, (int)$row['id']);
        ?>
        <article class="mu-card">
            <h2><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="hint"><?php echo htmlspecialchars(rtrim(rtrim($base, '0'), '.'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars(ame_unit_name($db, $baseId), ENT_QUOTES, 'UTF-8'); ?></p>
            <p><?php echo htmlspecialchars(ame_unit_hint($db, (int)$row['id']), ENT_QUOTES, 'UTF-8'); ?></p>
            <?php
            $seen = [];
            foreach ($edges as $from => $tos) {
                foreach ($tos as $to => $factor) {
                    if (isset($seen[$to]) || $to === $baseId) {
                        continue;
                    }
                    $seen[$to] = true;
                    $eq = ame_unit_convert($edges, $baseId, $to, $base);
                    if ($eq === null) {
                        continue;
                    }
                    echo '<p>' . htmlspecialchars(rtrim(rtrim($eq, '0'), '.') . ' ' . ame_unit_name($db, $to), ENT_QUOTES, 'UTF-8') . '</p>';
                }
            }
            ?>
            <p><a href="unit_ledger.php?id=<?php echo (int)$row['id']; ?>"><?php echo ame_l('খাতা', 'Ledger'); ?></a></p>
        </article>
        <?php endwhile; ?>
    </div>
    <?php if (!$rows || $rows->num_rows === 0): ?><p class="mu-card"><?php echo ame_l('এখনো কোনো পণ্যে রূপান্তর সেট হয়নি।', 'No product has a conversion yet.'); ?></p><?php endif; ?>
</div>
<?php include '../../partials/footer.php'; ?>
