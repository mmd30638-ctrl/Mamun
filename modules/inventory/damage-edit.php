<?php
require_once '../../includes/config.php';
require_once '../../includes/damage_functions.php';
require_permission($db, 'm-inv');
ame_damage_ensure($db);

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare(
    "SELECT d.*, p.name AS product_name
     FROM inventory_damage d
     JOIN products p ON p.id = d.product_id
     WHERE d.id = ?"
);
$row = null;
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$row || (string)$row['status'] !== 'posted') {
    header('Location: damage.php');
    exit;
}
$back = (string)($_GET['back'] ?? '');
if ($back !== '' && !preg_match('/^[A-Za-z0-9_%=&.\-]*$/', $back)) {
    $back = '';
}
$reasons = ame_damage_reasons();
$ame_body_class = 'ame-damage-page';
include '../../partials/header.php';
$ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.357';
?>
<link rel="stylesheet" href="/assets/css/damage-control.css?v=<?php echo htmlspecialchars((string)$ver, ENT_QUOTES, 'UTF-8'); ?>">
<div class="dmg dmg-narrow">
    <div class="dmg-head">
        <div>
            <h1>ড্যামেজ সংশোধন</h1>
            <p><?php echo htmlspecialchars((string)$row['product_name'], ENT_QUOTES, 'UTF-8'); ?> · পরিমাণ <?php echo rtrim(rtrim(number_format((float)$row['qty'], 3, '.', ''), '0'), '.'); ?></p>
        </div>
        <a class="dmg-clear" href="damage.php<?php echo $back !== '' ? '?' . htmlspecialchars($back, ENT_QUOTES, 'UTF-8') : ''; ?>">তালিকায় ফিরুন</a>
    </div>
    <form class="dmg-form" method="post" action="process_damage.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
        <input type="hidden" name="back" value="<?php echo htmlspecialchars($back, ENT_QUOTES, 'UTF-8'); ?>">
        <label>তারিখ
            <input type="date" name="damage_date" required value="<?php echo htmlspecialchars((string)$row['damage_date'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>কারণ
            <select name="reason">
                <?php foreach ($reasons as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo (string)$row['reason'] === $key ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>নোট
            <textarea name="note" maxlength="255" rows="3"><?php echo htmlspecialchars((string)($row['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>
        <p class="dmg-hint">পরিমাণ ও ক্ষতির টাকা এখানে বদলায় না। ভুল পরিমাণ হলে এন্ট্রি বাতিল করে নতুন করে দিন — স্টক তখন ফিরে আসবে।</p>
        <button class="dmg-btn save" type="submit">সংশোধন সেভ</button>
    </form>
    <form class="dmg-form" method="post" action="process_damage.php" onsubmit="return confirm('স্টক ফিরিয়ে এই ড্যামেজ বাতিল হবে?');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="void">
        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
        <input type="hidden" name="back" value="<?php echo htmlspecialchars($back, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="dmg-btn void">এই এন্ট্রি বাতিল</button>
    </form>
</div>
<?php include '../../partials/footer.php'; ?>
