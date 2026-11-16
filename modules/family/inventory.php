<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_inventory', 'fam_lead_inventory');
$q = trim((string)($_GET['q'] ?? ''));
$cat = trim((string)($_GET['cat'] ?? ''));
$edit = !empty($_GET['id']) ? fam_one('SELECT * FROM family_inventory WHERE id=' . (int)$_GET['id']) : null;
$sql = 'SELECT * FROM family_inventory WHERE 1';
if ($cat !== '') {
    $sql .= " AND category='" . fam_db()->real_escape_string($cat) . "'";
}
if ($q !== '') {
    $l = fam_db()->real_escape_string($q);
    $sql .= " AND (name LIKE '%$l%' OR location_note LIKE '%$l%')";
}
$sql .= ' ORDER BY name';
$rows = fam_rows($sql);
?>
<div class="fam-chips">
  <a class="fam-chip<?php echo $cat === '' ? ' on' : ''; ?>" href="inventory.php">সব</a>
  <?php foreach (fam_inv_cats() as $c): ?>
    <a class="fam-chip<?php echo $cat === $c ? ' on' : ''; ?>" href="inventory.php?cat=<?php echo urlencode($c); ?>"><?php echo esc_html($c); ?></a>
  <?php endforeach; ?>
</div>
<div class="fam-grid fam-grid-form">
<?php if (fam_can_write()): ?>
<section class="fam-panel">
  <h2><?php echo $edit ? 'সম্পাদনা' : 'নতুন জিনিস'; ?></h2>
  <form class="fam-form" method="post" action="process.php" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="inv_save">
    <input type="hidden" name="back" value="/modules/family/inventory.php">
    <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
    <label>নাম<input name="name" required value="<?php echo esc_html($edit['name'] ?? ''); ?>"></label>
    <div class="row2">
      <label>ক্যাটাগরি<select name="category"><?php echo fam_opts(fam_inv_cats(), $edit['category'] ?? ''); ?></select></label>
      <label>পরিমাণ<input name="qty" value="<?php echo esc_html($edit['qty'] ?? '1'); ?>"></label>
    </div>
    <div class="row2">
      <label>কেনার তারিখ<input type="date" name="buy_date" value="<?php echo esc_html($edit['buy_date'] ?? ''); ?>"></label>
      <label>দাম<input name="price" type="number" step="0.01" value="<?php echo esc_html($edit['price'] ?? ''); ?>"></label>
    </div>
    <div class="row2">
      <label>অবস্থা<select name="condition_note"><?php echo fam_opts(fam_conditions(), $edit['condition_note'] ?? 'ভালো'); ?></select></label>
      <label>কোথায়<input name="location_note" value="<?php echo esc_html($edit['location_note'] ?? ''); ?>"></label>
    </div>
    <div class="row2">
      <label>ওয়ারেন্টি শেষ<input type="date" name="warranty_until" value="<?php echo esc_html($edit['warranty_until'] ?? ''); ?>"></label>
      <label>মেইনটেন্যান্স<input type="date" name="maintain_date" value="<?php echo esc_html($edit['maintain_date'] ?? ''); ?>"></label>
    </div>
    <label>রসিদ<input type="file" name="receipt_file" accept="image/*,.pdf" capture="environment"></label>
    <label>নোট<textarea name="notes"><?php echo esc_html($edit['notes'] ?? ''); ?></textarea></label>
    <button class="fam-btn" type="submit">সংরক্ষণ</button>
  </form>
</section>
<?php endif; ?>
<section class="fam-panel">
  <h2><?php echo count($rows); ?> টি জিনিস</h2>
  <?php if (!$rows) echo fam_empty('ইনভেন্টরি খালি।'); ?>
  <?php foreach ($rows as $i): ?>
    <div class="fam-row">
      <div>
        <?php
          $invUrl = fam_file_url($i['receipt_file'] ?? '');
          if ($invUrl && fam_is_image($i['receipt_file'] ?? '')):
        ?>
          <a href="<?php echo esc_html($invUrl); ?>" target="_blank"><img class="fam-photo" src="<?php echo esc_html($invUrl); ?>" alt=""></a>
        <?php endif; ?>
        <strong><?php echo esc_html($i['name']); ?></strong>
        <small><?php echo esc_html($i['category'] . ' · ' . $i['qty'] . ' · ' . $i['condition_note'] . ' · ' . ($i['location_note'] ?: '')); ?>
          <?php if ($i['warranty_until']): ?> · ওয়ারেন্টি <?php echo esc_html($i['warranty_until']); endif; ?></small>
      </div>
      <div>
        <b><?php echo $i['price'] > 0 ? fam_money_txt($i['price']) : ''; ?></b>
        <?php if (fam_can_write()): ?><a class="fam-mini" href="inventory.php?id=<?php echo (int)$i['id']; ?>">সম্পাদনা</a><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
