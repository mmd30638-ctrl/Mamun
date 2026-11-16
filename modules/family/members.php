<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
$edit = !empty($_GET['id']) ? fam_member((int)$_GET['id']) : null;
fam_open('fam_title_members', 'fam_lead_members');
$q = trim((string)($_GET['q'] ?? ''));
$rows = fam_members();
if ($q !== '') {
    $rows = array_values(array_filter($rows, static function ($r) use ($q) {
        $hay = mb_strtolower((string)($r['name'] ?? '') . fam_member_relation($r), 'UTF-8');
        return str_contains($hay, mb_strtolower($q, 'UTF-8'));
    }));
}
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker"><?php echo ame_pair('পারিবারিক খাতা', 'Family book'); ?></p>
  <h2><?php echo ame_pair('সদস্য', 'Members'); ?></h2>
  <p><?php echo ame_pair('বিক্রয় পাতার নেভি রং — নাম, সম্পর্ক ও মোবাইল দূর থেকে স্পষ্ট।', 'Sales-desk navy — name, relation and mobile stay clear from a distance.'); ?></p>
</header>
<div class="fam-grid fam-grid-form">
<?php if (fam_can_write()): ?>
<section class="fam-panel">
  <h2><?php echo $edit ? ame_pair('সদস্য সম্পাদনা', 'Edit member') : ame_pair('নতুন সদস্য', 'New member'); ?></h2>
  <form class="fam-form" method="post" action="process.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="member_save">
    <input type="hidden" name="back" value="/modules/family/members.php">
    <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
    <label><?php echo ame_pair('নাম', 'Name'); ?><input name="name" required value="<?php echo esc_html($edit['name'] ?? ''); ?>"></label>
    <div class="row2">
      <label><?php echo ame_pair('সম্পর্ক', 'Relation'); ?><select name="relation_name"><?php echo fam_opts(fam_relations(), $edit['relation_name'] ?? ''); ?></select></label>
      <label><?php echo ame_pair('জন্মতারিখ', 'Birth date'); ?><input type="date" name="birth_date" value="<?php echo esc_html($edit['birth_date'] ?? ''); ?>"></label>
    </div>
    <label><?php echo ame_pair('মোবাইল', 'Mobile'); ?><input name="mobile" value="<?php echo esc_html($edit['mobile'] ?? ''); ?>"></label>
    <label><?php echo ame_pair('ঠিকানা', 'Address'); ?><textarea name="address"><?php echo esc_html($edit['address'] ?? ''); ?></textarea></label>
    <label><?php echo ame_pair('জরুরি যোগাযোগ — নাম', 'Emergency name'); ?><input name="emergency_name" value="<?php echo esc_html($edit['emergency_name'] ?? ''); ?>"></label>
    <label><?php echo ame_pair('জরুরি ফোন', 'Emergency phone'); ?><input name="emergency_phone" value="<?php echo esc_html($edit['emergency_phone'] ?? ''); ?>"></label>
    <label><?php echo ame_pair('গুরুত্বপূর্ণ তথ্য', 'Important notes'); ?><textarea name="important_info"><?php echo esc_html($edit['important_info'] ?? ''); ?></textarea></label>
    <label><?php echo ame_pair('নোট', 'Notes'); ?><textarea name="notes"><?php echo esc_html($edit['notes'] ?? ''); ?></textarea></label>
    <button class="fam-btn" type="submit"><?php echo ame_pair('সংরক্ষণ', 'Save'); ?></button>
  </form>
</section>
<?php endif; ?>
<section class="fam-panel">
  <h2><?php echo ame_pair('সদস্য তালিকা', 'Member list'); ?></h2>
  <?php if (!$rows) echo fam_empty('এখনো কোনো সদস্য নেই।', 'members.php', 'প্রথম সদস্য যোগ করুন'); ?>
  <?php foreach ($rows as $m): ?>
    <a class="fam-row" href="member.php?id=<?php echo (int)$m['id']; ?>">
      <div>
        <strong><?php echo esc_html($m['name']); ?></strong>
        <small><?php echo esc_html(fam_member_relation($m) !== '' ? fam_member_relation($m) : 'সম্পর্ক নেই'); ?> · <?php echo esc_html(trim((string)($m['mobile'] ?? '')) !== '' ? $m['mobile'] : 'মোবাইল নেই'); ?></small>
      </div>
      <span class="fam-mini"><?php echo ame_pair('প্রোফাইল', 'Profile'); ?></span>
    </a>
  <?php endforeach; ?>
</section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
