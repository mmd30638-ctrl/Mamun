<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_docs', 'fam_lead_docs');
$kind = trim((string)($_GET['kind'] ?? ''));
$sql = 'SELECT d.*, m.name AS member_name FROM family_documents d LEFT JOIN family_members m ON m.id=d.member_id WHERE 1';
if ($kind !== '') {
    $sql .= " AND d.kind='" . fam_db()->real_escape_string($kind) . "'";
}
$sql .= ' ORDER BY d.id DESC';
$rows = fam_rows($sql);
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker">পারিবারিক খাতা</p>
  <h2>কাগজপত্র</h2>
  <p>বিক্রয় পাতার নেভি রং — নাম দূর থেকে স্পষ্ট।</p>
</header>
<div class="fam-chips">
  <a class="fam-chip<?php echo $kind === '' ? ' on' : ''; ?>" href="documents.php">সব</a>
  <?php foreach (fam_doc_kinds() as $k): ?>
    <a class="fam-chip<?php echo $kind === $k ? ' on' : ''; ?>" href="documents.php?kind=<?php echo urlencode($k); ?>"><?php echo esc_html($k); ?></a>
  <?php endforeach; ?>
</div>
<div class="fam-grid fam-grid-form">
<?php if (fam_can_write()): ?>
<section class="fam-panel">
  <h2>নতুন রেকর্ড</h2>
  <form class="fam-form" method="post" action="process.php" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="doc_save">
    <input type="hidden" name="back" value="/modules/family/documents.php">
    <label>শিরোনাম<input name="title" required></label>
    <label>ধরন<select name="kind"><?php echo fam_opts(fam_doc_kinds()); ?></select></label>
    <label>সদস্য<select name="member_id"><?php echo fam_member_opts(); ?></select></label>
    <label>ছবি / ফাইল<input type="file" name="file_name" accept="image/*,.pdf" capture="environment"></label>
    <label>নোট<textarea name="notes"></textarea></label>
    <button class="fam-btn" type="submit">সংরক্ষণ</button>
  </form>
</section>
<?php endif; ?>
<section class="fam-panel">
  <h2>রেকর্ড</h2>
  <?php if (!$rows) echo fam_empty('ডকুমেন্ট নেই।'); ?>
  <?php foreach ($rows as $d):
      $url = fam_file_url($d['file_name'] ?? '');
  ?>
    <div class="fam-row">
      <div>
        <?php if ($url && fam_is_image($d['file_name'] ?? '')): ?>
          <a href="<?php echo esc_html($url); ?>" target="_blank"><img class="fam-photo" src="<?php echo esc_html($url); ?>" alt=""></a>
        <?php endif; ?>
        <strong><?php echo esc_html($d['title']); ?></strong>
        <small><?php echo esc_html($d['kind'] . ' · ' . ($d['member_name'] ?: '') . ' · ' . $d['created_at']); ?></small>
      </div>
      <div>
        <?php if ($url): ?>
          <a class="fam-mini" href="<?php echo esc_html($url); ?>" target="_blank"><?php echo fam_is_image($d['file_name'] ?? '') ? 'ছবি' : 'ফাইল'; ?></a>
        <?php endif; ?>
        <?php if (fam_can_write()): ?>
          <form method="post" action="process.php" style="display:inline" onsubmit="return confirm('মুছবেন?')">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="do" value="doc_delete">
            <input type="hidden" name="back" value="/modules/family/documents.php">
            <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
            <button class="fam-mini" type="submit">মুছুন</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
