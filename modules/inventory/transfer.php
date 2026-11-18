<?php
require_once '../../includes/config.php';
require_once '../../includes/workshop_functions.php';
ensure_workshop_schema($db);
require_permission($db, 'm-inv');
include '../../partials/header.php';

$stores = function_exists('ame_stores_for_user') ? ame_stores_for_user($db) : ame_list_stores($db, true);
$products = ame_workshop_products($db);
$err = trim((string)($_GET['err'] ?? ''));
$ok = trim((string)($_GET['ok'] ?? ''));

$stmt = $db->prepare(
    "SELECT t.*, fs.name AS from_name, ts.name AS to_name
     FROM store_transfers t
     LEFT JOIN stores fs ON fs.id = t.from_store_id
     LEFT JOIN stores ts ON ts.id = t.to_store_id
     ORDER BY t.id DESC LIMIT 40"
);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<div class="ws-wrap">
    <h3>↔️ স্টোর থেকে স্টোরে স্টক</h3>
    <?php if ($ok === '1'): ?><div class="ws-flash">ট্রান্সফার সম্পন্ন। স্টক সরানো হয়েছে।</div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="ws-err"><?php echo ame_workshop_h($err); ?></div><?php endif; ?>

    <?php if (count($stores) < 2): ?>
        <div class="ws-card">
            <p>ট্রান্সফার করতে অন্তত দুইটি স্টোর লাগবে। সেটিংস থেকে আরেকটি শাখা যোগ করুন। একটি স্টোর থাকলে এই পেজ শুধু দেখার জন্য।</p>
        </div>
    <?php else: ?>
    <form class="ws-card" method="post" action="transfer_process.php">
        <?php echo csrf_field(); ?>
        <div class="ws-row">
            <div>
                <label>যেখান থেকে</label>
                <select name="from_store_id" required>
                    <?php foreach ($stores as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>" <?php echo (int)$s['id'] === (int)current_store_id() ? 'selected' : ''; ?>>
                            <?php echo ame_workshop_h($s['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>যেখানে যাবে</label>
                <select name="to_store_id" required>
                    <?php foreach ($stores as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>"><?php echo ame_workshop_h($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <label>পণ্য</label>
        <select name="product_id" required>
            <option value="">বাছুন</option>
            <?php foreach ($products as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>"><?php echo ame_workshop_h($p['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <label>পরিমাণ</label>
        <input type="number" step="0.001" min="0.001" name="qty" required>
        <label>নোট</label>
        <input type="text" name="note">
        <button class="ws-btn" type="submit">ট্রান্সফার সম্পন্ন</button>
    </form>
    <?php endif; ?>

    <div class="ws-card" style="overflow-x:auto;">
        <b>ইতিহাস</b>
        <?php if (!$history): ?>
            <p class="ws-empty">এখনো কোনো ট্রান্সফার নেই।</p>
        <?php else: ?>
            <table class="ws-table">
                <thead><tr><th>নম্বর</th><th>পথ</th><th>অবস্থা</th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?php echo ame_workshop_h($h['transfer_no']); ?>
                            <div class="ws-hint"><?php echo ame_workshop_h(substr((string)$h['created_at'], 0, 16)); ?></div>
                        </td>
                        <td><?php echo ame_workshop_h($h['from_name']); ?> → <?php echo ame_workshop_h($h['to_name']); ?>
                            <?php if ($h['note']): ?><div class="ws-hint"><?php echo ame_workshop_h($h['note']); ?></div><?php endif; ?>
                        </td>
                        <td><?php echo $h['status'] === 'completed' ? 'সম্পন্ন' : ame_workshop_h($h['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
