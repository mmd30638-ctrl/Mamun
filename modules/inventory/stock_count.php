<?php
require_once '../../includes/config.php';
require_once '../../includes/workshop_functions.php';
ensure_workshop_schema($db);
require_permission($db, 'm-inv');
include '../../partials/header.php';

$store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
if ($store_id <= 0 && function_exists('ame_default_store_id')) {
    $store_id = (int)ame_default_store_id($db);
}
$err = trim((string)($_GET['err'] ?? ''));
$ok = trim((string)($_GET['ok'] ?? ''));

$products = [];
$res = @$db->query(
    'SELECT id, name, COALESCE(stock, 0) AS stock FROM products
     WHERE ' . ame_product_option_sql('status') . '
     ORDER BY name ASC LIMIT 400'
);
if ($res) {
    $products = $res->fetch_all(MYSQLI_ASSOC);
}

$hist = $db->prepare(
    'SELECT * FROM stock_counts WHERE store_id = ? ORDER BY id DESC LIMIT 20'
);
$hist->bind_param('i', $store_id);
$hist->execute();
$history = $hist->get_result()->fetch_all(MYSQLI_ASSOC);
$hist->close();
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<div class="ws-wrap">
    <h3>🧮 ফিজিক্যাল স্টক কাউন্ট</h3>
    <?php if ($ok === '1'): ?><div class="ws-flash">কাউন্ট সেভ হয়েছে। স্টক মিলিয়ে নেওয়া হয়েছে।</div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="ws-err"><?php echo ame_workshop_h($err); ?></div><?php endif; ?>
    <p class="ws-hint">সিস্টেমের স্টক দেখা যাচ্ছে। গুদামে যা গুনেছেন তা লিখুন। শুধু যে লাইনে সংখ্যা দিবেন সেগুলোই আপডেট হবে। খালি রাখলে অপরিবর্তিত।</p>

    <form class="ws-card" method="post" action="stock_count_process.php">
        <?php echo csrf_field(); ?>
        <label>নোট (ঐচ্ছিক)</label>
        <input type="text" name="note" placeholder="যেমন: রমজানের আগে গোনা">
        <div style="overflow-x:auto;">
            <table class="ws-table">
                <thead><tr><th>পণ্য</th><th>সিস্টেম</th><th>গুনে পেয়েছি</th></tr></thead>
                <tbody>
                <?php foreach ($products as $p):
                    $sys = ame_workshop_on_hand($db, (int)$p['id'], $store_id);
                ?>
                    <tr>
                        <td><?php echo ame_workshop_h($p['name']); ?></td>
                        <td><?php echo rtrim(rtrim(number_format($sys, 3, '.', ''), '0'), '.'); ?></td>
                        <td>
                            <input type="hidden" name="product_id[]" value="<?php echo (int)$p['id']; ?>">
                            <input type="hidden" name="system_qty[]" value="<?php echo ame_workshop_h($sys); ?>">
                            <input type="number" step="0.001" min="0" name="counted_qty[]" placeholder="খালি = বাদ">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$products): ?>
            <div class="ws-empty">সক্রিয় পণ্য নেই।</div>
        <?php else: ?>
            <button class="ws-btn" type="submit">কাউন্ট সম্পন্ন ও স্টক মিলান</button>
        <?php endif; ?>
    </form>

    <div class="ws-card">
        <b>আগের কাউন্ট</b>
        <?php if (!$history): ?>
            <p class="ws-empty">এখনো কাউন্ট নেই।</p>
        <?php else: ?>
            <table class="ws-table">
                <thead><tr><th>নম্বর</th><th>তারিখ</th><th>নোট</th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?php echo ame_workshop_h($h['count_no']); ?></td>
                        <td><?php echo ame_workshop_h(substr((string)$h['completed_at'] ?: (string)$h['created_at'], 0, 16)); ?></td>
                        <td><?php echo ame_workshop_h($h['note'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
