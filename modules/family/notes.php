<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_notes', 'fam_lead_notes');
$q = trim((string)($_GET['q'] ?? ''));
$edit = !empty($_GET['id']) ? fam_one('SELECT * FROM family_notes WHERE id=' . (int)$_GET['id']) : null;
$sql = 'SELECT n.*, m.name AS member_name FROM family_notes n LEFT JOIN family_members m ON m.id=n.member_id WHERE 1';
if ($q !== '') {
    $l = fam_db()->real_escape_string($q);
    $sql .= " AND (n.title LIKE '%$l%' OR n.body LIKE '%$l%')";
}
$sql .= ' ORDER BY n.is_pinned DESC, n.id DESC';
$rows = fam_rows($sql);
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker">পারিবারিক খাতা</p>
  <h2>নোট</h2>
  <p>বিক্রয় পাতার নেভি রং — শিরোনাম দূর থেকে স্পষ্ট।</p>
</header>
<div class="fam-grid fam-grid-form">
<?php if (fam_can_write()): ?>
<section class="fam-panel">
  <h2><?php echo $edit ? 'নোট সম্পাদনা' : 'নতুন নোট'; ?></h2>
  <form class="fam-form" method="post" action="process.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="note_save">
    <input type="hidden" name="back" value="/modules/family/notes.php">
    <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
    <label>শিরোনাম<input name="title" value="<?php echo esc_html($edit['title'] ?? ''); ?>"></label>
    <label>সদস্য<select name="member_id"><?php echo fam_member_opts($edit['member_id'] ?? null); ?></select></label>
    <label>লেখা<textarea name="body" required><?php echo esc_html($edit['body'] ?? ''); ?></textarea></label>
    <label><input type="checkbox" name="is_pinned" <?php echo !empty($edit['is_pinned']) ? 'checked' : ''; ?>> পিন করুন</label>
    <button class="fam-btn" type="submit">সংরক্ষণ</button>
  </form>
</section>
<?php endif; ?>
<section class="fam-panel">
  <h2>সব নোট</h2>
  <?php if (!$rows) echo fam_empty('নোট নেই।'); ?>
  <?php foreach ($rows as $n): ?>
    <div class="fam-row">
      <div>
        <strong><?php echo ((int)$n['is_pinned'] ? '📌 ' : '') . esc_html($n['title']); ?></strong>
        <small><?php echo esc_html(mb_strimwidth((string)$n['body'], 0, 120, '…')); ?> · <?php echo esc_html($n['member_name'] ?: ''); ?></small>
      </div>
      <?php if (fam_can_write()): ?>
      <div>
        <form method="post" action="process.php" style="display:inline">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="do" value="note_pin">
          <input type="hidden" name="back" value="/modules/family/notes.php">
          <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
          <button class="fam-mini" type="submit"><?php echo (int)$n['is_pinned'] ? 'আনপিন' : 'পিন'; ?></button>
        </form>
        <a class="fam-mini" href="notes.php?id=<?php echo (int)$n['id']; ?>">সম্পাদনা</a>
        <form method="post" action="process.php" style="display:inline" onsubmit="return confirm('মুছবেন?')">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="do" value="note_delete">
          <input type="hidden" name="back" value="/modules/family/notes.php">
          <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
          <button class="fam-mini" type="submit">মুছুন</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
