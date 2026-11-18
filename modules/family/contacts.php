<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_contacts', 'fam_lead_contacts');
$q = trim((string)($_GET['q'] ?? ''));
$g = (int)($_GET['g'] ?? 0);
$edit = !empty($_GET['id']) ? fam_one('SELECT * FROM family_contacts WHERE id=' . (int)$_GET['id']) : null;
$backQs = [];
if ($g > 0) {
    $backQs['g'] = (string)$g;
}
if ($q !== '') {
    $backQs['q'] = $q;
}
$backHref = '/modules/family/contacts.php' . ($backQs ? ('?' . http_build_query($backQs)) : '');

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

$groupSections = [];
$activeGroupName = '';
if ($g > 0) {
    foreach (fam_groups() as $gr) {
        if ((int)$gr['id'] === $g) {
            $activeGroupName = (string)$gr['name'];
            break;
        }
    }
    if ($activeGroupName !== '') {
        $groupSections[] = ['id' => $g, 'name' => $activeGroupName, 'rows' => $rows];
    } else {
        $groupSections[] = ['id' => $g, 'name' => 'নির্বাচিত গ্রুপ', 'rows' => $rows];
    }
} else {
    $allSql = 'SELECT c.*, g.name AS gname FROM family_contacts c LEFT JOIN family_groups g ON g.id=c.group_id WHERE 1';
    if ($q !== '') {
        $l = fam_db()->real_escape_string($q);
        $allSql .= " AND (c.name LIKE '%$l%' OR c.mobile LIKE '%$l%' OR c.relation_note LIKE '%$l%' OR c.profession LIKE '%$l%' OR c.organization LIKE '%$l%')";
    }
    $allSql .= ' ORDER BY g.name IS NULL, g.name, c.name';
    $allRows = fam_rows($allSql);
    $bucket = [];
    foreach ($allRows as $c) {
        $gid = (int)($c['group_id'] ?? 0);
        if (!isset($bucket[$gid])) {
            $lbl = $gid > 0 ? (string)($c['gname'] ?? 'গ্রুপ') : 'গ্রুপ নির্ধারণ নেই';
            $bucket[$gid] = ['id' => $gid, 'name' => $lbl, 'rows' => []];
        }
        $bucket[$gid]['rows'][] = $c;
    }
    foreach (fam_groups() as $gr) {
        $gid = (int)$gr['id'];
        if (!empty($bucket[$gid]['rows'])) {
            $bucket[$gid]['name'] = (string)$gr['name'];
            $groupSections[] = $bucket[$gid];
            unset($bucket[$gid]);
        }
    }
    if (!empty($bucket[0]['rows'] ?? [])) {
        $groupSections[] = $bucket[0];
        unset($bucket[0]);
    }
    foreach ($bucket as $sec) {
        if (!empty($sec['rows'])) {
            $groupSections[] = $sec;
        }
    }
}

$formGroup = $edit ? ($edit['group_id'] ?? null) : ($g > 0 ? $g : null);
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
  <form class="fam-form" method="post" action="/modules/family/process.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="contact_save">
    <input type="hidden" name="back" value="<?php echo esc_html($backHref); ?>">
    <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
    <label>নাম<input name="name" required value="<?php echo esc_html($edit['name'] ?? ''); ?>"></label>
    <label>গ্রুপ<select name="group_id" required><?php echo fam_group_opts($formGroup); ?></select></label>
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
<section class="fam-panel fam-contacts-list">
  <h2><?php echo count($rows); ?> জন<?php echo $g > 0 && $activeGroupName !== '' ? ' · ' . esc_html($activeGroupName) : ''; ?></h2>
  <?php if (!$rows): ?>
    <?php echo fam_empty($g > 0 ? 'এই গ্রুপে এখনো কাউকে যোগ করেননি। বামের ফর্মে গ্রুপ সিলেক্ট করে সংরক্ষণ করুন।' : 'কোনো পরিচিত নেই।'); ?>
  <?php else: ?>
    <?php foreach ($groupSections as $sec): ?>
      <div class="fam-grp-block">
        <h3 class="fam-grp-hd">
          <span><?php echo esc_html($sec['name']); ?></span>
          <em><?php echo count($sec['rows']); ?> জন</em>
        </h3>
        <?php foreach ($sec['rows'] as $c): ?>
        <div class="fam-row">
          <div>
            <strong><?php echo esc_html($c['name']); ?></strong>
            <small><?php echo esc_html(trim(($c['relation_note'] ?? '') . ' · ' . ($c['profession'] ?? ''), ' ·')); ?></small>
          </div>
          <div>
            <?php echo fam_tel_btns($c['mobile'] ?: $c['alt_mobile']); ?>
            <?php if (fam_can_write()): ?>
              <a class="fam-mini" href="contacts.php?id=<?php echo (int)$c['id']; ?><?php echo $g ? '&g=' . $g : ''; ?>">সম্পাদনা</a>
              <form method="post" action="/modules/family/process.php" style="display:inline" onsubmit="return confirm('মুছবেন?')">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="do" value="contact_delete">
                <input type="hidden" name="back" value="<?php echo esc_html($backHref); ?>">
                <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                <button class="fam-mini" type="submit">মুছুন</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
