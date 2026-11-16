<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_groups', 'fam_lead_groups');
?>
<div class="fam-grid fam-grid-form">
<?php if (fam_can_write()): ?>
<section class="fam-panel">
  <h2>নতুন গ্রুপ</h2>
  <form class="fam-form" method="post" action="process.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="group_save">
    <input type="hidden" name="back" value="/modules/family/groups.php">
    <label>নাম<input name="name" required placeholder="যেমন: মসজিদের কমিটি"></label>
    <button class="fam-btn" type="submit">তৈরি করুন</button>
  </form>
</section>
<?php endif; ?>
<section class="fam-panel">
  <h2>গ্রুপসমূহ</h2>
  <?php foreach (fam_groups() as $g): ?>
    <div class="fam-row">
      <div>
        <strong><?php echo esc_html($g['name']); ?></strong>
        <small><?php echo (int)$g['n']; ?> জন · <?php echo (int)$g['is_system'] ? 'ডিফল্ট' : 'নিজের'; ?></small>
      </div>
      <div>
        <a class="fam-mini" href="contacts.php?g=<?php echo (int)$g['id']; ?>">খুলুন</a>
        <?php if (fam_can_write() && !(int)$g['is_system']): ?>
          <form method="post" action="process.php" style="display:inline" onsubmit="return confirm('মুছবেন?')">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="do" value="group_delete">
            <input type="hidden" name="back" value="/modules/family/groups.php">
            <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
            <button class="fam-mini" type="submit">মুছুন</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
