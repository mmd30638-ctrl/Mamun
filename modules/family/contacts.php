<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_contacts', 'fam_lead_contacts');
$q = trim((string)($_GET['q'] ?? ''));
$g = (int)($_GET['g'] ?? 0);
$edit = !empty($_GET['id']) ? fam_one('SELECT * FROM family_contacts WHERE id=' . (int)$_GET['id']) : null;
$sql = 'SELECT c.*, g.name AS gname FROM family_contacts c LEFT JOIN family_groups g ON g.id=c.group_id WHERE 1';
if ($g) {
    $sql .= ' AND c.group_id=' . $g;
}
if ($q !== '') {
    $l = fam_db()->real_escape_string($q);
    $sql .= " AND (c.name LIKE '%$l%' OR c.mobile LIKE '%$l%' OR c.relation_note LIKE '%$l%' OR c.profession LIKE '%$l%' OR c.organization LIKE '%$l%')";
}
$sql .= ' ORDER BY c.name';
$rows = fam_rows($sql);
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker">পারিবারিক খাতা</p>
  <h2>পরিচিত</h2>
  <p>বিক্রয় পাতার নেভি রং — নাম ও মোবাইল দূর থেকে স্পষ্ট।</p>
</header>
<div class="fam-chips">
  <a class="fam-chip<?php echo !$g ? ' on' : ''; ?>" href="contacts.php">সব</a>
  <?php foreach (fam_groups() as $gr): ?>
    <a class="fam-chip<?php echo $g === (int)$gr['id'] ? ' on' : ''; ?>" href="contacts.php?g=<?php echo (int)$gr['id']; ?>"><?php echo esc_html($gr['name']); ?></a>
  <?php endforeach; ?>
</div>
<div class="fam-grid fam-grid-form">
<?php if (fam_can_write()): ?>
<section class="fam-panel">
  <h2><?php echo $edit ? 'সম্পাদনা' : 'নতুন পরিচিত'; ?></h2>
  <form class="fam-form" method="post" action="process.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="contact_save">
    <input type="hidden" name="back" value="/modules/family/contacts.php">
    <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
    <label>নাম<input name="name" required value="<?php echo esc_html($edit['name'] ?? ''); ?>"></label>
    <label>গ্রুপ<select name="group_id"><?php echo fam_group_opts($edit['group_id'] ?? null); ?></select></label>
    <div class="row2">
      <label>মোবাইল<input name="mobile" value="<?php echo esc_html($edit['mobile'] ?? ''); ?>"></label>
      <label>বিকল্প নম্বর<input name="alt_mobile" value="<?php echo esc_html($edit['alt_mobile'] ?? ''); ?>"></label>
    </div>
    <label>ঠিকানা<textarea name="address"><?php echo esc_html($edit['address'] ?? ''); ?></textarea></label>
    <label>ইমেইল<input name="email" value="<?php echo esc_html($edit['email'] ?? ''); ?>"></label>
    <div class="row2">
      <label>পেশা<input name="profession" value="<?php echo esc_html($edit['profession'] ?? ''); ?>"></label>
      <label>প্রতিষ্ঠান<input name="organization" value="<?php echo esc_html($edit['organization'] ?? ''); ?>"></label>
    </div>
    <label>সম্পর্ক<input name="relation_note" value="<?php echo esc_html($edit['relation_note'] ?? ''); ?>"></label>
    <label>নোট<textarea name="notes"><?php echo esc_html($edit['notes'] ?? ''); ?></textarea></label>
    <button class="fam-btn" type="submit">সংরক্ষণ</button>
  </form>
</section>
<?php endif; ?>
<section class="fam-panel">
  <h2><?php echo count($rows); ?> জন</h2>
  <?php if (!$rows) echo fam_empty('কোনো পরিচিত নেই।'); ?>
  <?php foreach ($rows as $c): ?>
    <div class="fam-row">
      <div>
        <strong><?php echo esc_html($c['name']); ?></strong>
        <small><?php echo esc_html(trim(($c['gname'] ?? '') . ' · ' . ($c['relation_note'] ?? '') . ' · ' . ($c['profession'] ?? ''), ' ·')); ?></small>
      </div>
      <div>
        <?php echo fam_tel_btns($c['mobile'] ?: $c['alt_mobile']); ?>
        <?php if (fam_can_write()): ?>
          <a class="fam-mini" href="contacts.php?id=<?php echo (int)$c['id']; ?>">সম্পাদনা</a>
          <form method="post" action="process.php" style="display:inline" onsubmit="return confirm('মুছবেন?')">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="do" value="contact_delete">
            <input type="hidden" name="back" value="/modules/family/contacts.php">
            <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
            <button class="fam-mini" type="submit">মুছুন</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
