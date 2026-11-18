<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_shopping', 'fam_lead_shopping');
$q = trim((string)($_GET['q'] ?? ''));
$cat = trim((string)($_GET['cat'] ?? ''));
$only = (string)($_GET['open'] ?? '1');
$edit = !empty($_GET['id']) ? fam_one('SELECT * FROM family_shopping WHERE id=' . (int)$_GET['id']) : null;
$sql = 'SELECT s.*, m.name AS member_name FROM family_shopping s LEFT JOIN family_members m ON m.id=s.member_id WHERE 1';
if ($only === '1') {
    $sql .= ' AND s.is_bought=0';
}
if ($cat !== '') {
    $sql .= " AND s.category='" . fam_db()->real_escape_string($cat) . "'";
}
if ($q !== '') {
    $l = fam_db()->real_escape_string($q);
    $sql .= " AND s.name LIKE '%$l%'";
}
$sql .= ' ORDER BY FIELD(s.priority,"জরুরি","উচ্চ","মাঝারি","কম"), s.id DESC';
$rows = fam_rows($sql);
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker">পারিবারিক খাতা</p>
  <h2>বাজার ও খরচ</h2>
  <p>মাসিক মাছ-আনাজ-মসলা চাহিদা এখন <a href="bazaar.php">বাজার চাহিদা</a> পাতায়। এখানে পুরনো সাধারণ তালিকা।</p>
</header>
<div class="fam-chips">
  <a class="fam-chip<?php echo $only === '1' ? ' on' : ''; ?>" href="shopping.php">বাকি</a>
  <a class="fam-chip<?php echo $only !== '1' ? ' on' : ''; ?>" href="shopping.php?open=0">সব</a>
  <?php foreach (fam_shop_cats() as $c): ?>
    <a class="fam-chip<?php echo $cat === $c ? ' on' : ''; ?>" href="shopping.php?cat=<?php echo urlencode($c); ?>"><?php echo esc_html($c); ?></a>
  <?php endforeach; ?>
</div>
<div class="fam-grid fam-grid-form">
<?php if (fam_can_write()): ?>
<section class="fam-panel">
  <h2><?php echo $edit ? 'সম্পাদনা' : 'তালিকায় যোগ'; ?></h2>
  <form class="fam-form" method="post" action="process.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="shop_save">
    <input type="hidden" name="back" value="/modules/family/shopping.php">
    <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
    <label>পণ্য<input name="name" required value="<?php echo esc_html($edit['name'] ?? ''); ?>"></label>
    <div class="row2">
      <label>ক্যাটাগরি<select name="category"><?php echo fam_opts(fam_shop_cats(), $edit['category'] ?? ''); ?></select></label>
      <label>পরিমাণ<input name="qty" value="<?php echo esc_html($edit['qty'] ?? ''); ?>"></label>
    </div>
    <div class="row2">
      <label>আনুমানিক দাম<input name="est_price" type="number" step="0.01" value="<?php echo esc_html($edit['est_price'] ?? ''); ?>"></label>
      <label>প্রাধান্য<select name="priority"><?php echo fam_opts(fam_priorities(), $edit['priority'] ?? 'মাঝারি'); ?></select></label>
    </div>
    <div class="row2">
      <label>কে কিনবে<input name="buyer" value="<?php echo esc_html($edit['buyer'] ?? ''); ?>"></label>
      <label>দোকান<input name="shop_name" value="<?php echo esc_html($edit['shop_name'] ?? ''); ?>"></label>
    </div>
    <label>সদস্য<select name="member_id"><?php echo fam_member_opts($edit['member_id'] ?? null); ?></select></label>
    <label>কেনার তারিখ<input type="date" name="buy_date" value="<?php echo esc_html($edit['buy_date'] ?? ''); ?>"></label>
    <label><input type="checkbox" name="is_bought" <?php echo !empty($edit['is_bought']) ? 'checked' : ''; ?>> কেনা হয়েছে</label>
    <label>নোট<textarea name="notes"><?php echo esc_html($edit['notes'] ?? ''); ?></textarea></label>
    <button class="fam-btn" type="submit">সংরক্ষণ</button>
  </form>
</section>
<?php endif; ?>
<section class="fam-panel">
  <h2>তালিকা</h2>
  <?php if (!$rows) echo fam_empty('তালিকা খালি। সব কেনা হয়ে গেছে, অথবা নতুন যোগ করুন।'); ?>
  <?php foreach ($rows as $s): ?>
    <div class="fam-row">
      <div>
        <strong><?php echo esc_html($s['name']); ?></strong>
        <small><?php echo esc_html($s['category'] . ' · ' . $s['qty'] . ' · ' . $s['priority'] . ' · ' . ($s['buyer'] ?: '')); ?></small>
      </div>
      <div>
        <b><?php echo $s['est_price'] > 0 ? fam_money_txt($s['est_price']) : ''; ?></b>
        <?php if (!(int)$s['is_bought'] && fam_can_write()): ?>
          <form method="post" action="process.php" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="do" value="shop_bought">
            <input type="hidden" name="back" value="/modules/family/shopping.php">
            <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
            <button class="fam-mini" type="submit">কেনা হয়েছে</button>
          </form>
        <?php endif; ?>
        <?php if (fam_can_write()): ?><a class="fam-mini" href="shopping.php?id=<?php echo (int)$s['id']; ?>">সম্পাদনা</a><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
