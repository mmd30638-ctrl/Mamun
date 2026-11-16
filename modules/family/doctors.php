<?php
require_once __DIR__ . '/_boot.php';
if (!fam_can_see('health')) {
    $_SESSION['fam_err'] = ame_l('স্বাস্থ্য তথ্য দেখার অনুমতি নেই।', 'You cannot view health records.');
    header('Location: /modules/family/index.php');
    exit;
}
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_doctors', 'fam_lead_doctors');
$q = trim((string)($_GET['q'] ?? ''));
$spec = trim((string)($_GET['spec'] ?? ''));
$edit = !empty($_GET['id']) ? fam_one('SELECT * FROM family_doctors WHERE id=' . (int)$_GET['id']) : null;
$sql = 'SELECT d.*, m.name AS member_name FROM family_doctors d LEFT JOIN family_members m ON m.id=d.member_id WHERE 1';
if ($spec !== '') {
    $sql .= " AND d.specialist='" . fam_db()->real_escape_string($spec) . "'";
}
if ($q !== '') {
    $l = fam_db()->real_escape_string($q);
    $sql .= " AND (d.name LIKE '%$l%' OR d.hospital LIKE '%$l%' OR d.specialist LIKE '%$l%')";
}
$sql .= ' ORDER BY d.name';
$rows = fam_rows($sql);
$rxSql = "SELECT d.*, m.name AS member_name, doc.name AS doctor_name
    FROM family_documents d
    LEFT JOIN family_members m ON m.id=d.member_id
    LEFT JOIN family_doctors doc ON doc.id=d.doctor_id
    WHERE d.kind='প্রেসক্রিপশন' AND d.archived_at IS NULL
    ORDER BY d.id DESC";
$rxRows = fam_rows($rxSql);
$rxMeds = [];
$rxIds = array_map(static fn ($rx) => (int)$rx['id'], $rxRows);
if ($rxIds) {
    $in = implode(',', $rxIds);
    foreach (fam_rows('SELECT * FROM family_rx_lines WHERE document_id IN (' . $in . ') ORDER BY id') as $ln) {
        $rxMeds[(int)$ln['document_id']][] = $ln;
    }
}
$none = ame_l('সদস্য নেই', 'No member');
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker"><?php echo ame_l('পারিবারিক খাতা', 'Family book'); ?></p>
  <h2><?php echo ame_l('ডাক্তার ও ওষুধ', 'Doctors and medicine'); ?></h2>
  <p><?php echo ame_l('বিক্রয় পাতার নেভি রং — নাম ও ফি দূর থেকে স্পষ্ট। হিসাব আগের মতো।', 'Sales-desk navy — names and fees stay clear. The numbers stay the same.'); ?></p>
</header>
<form class="fam-form" method="get" action="doctors.php" style="margin-bottom:12px;">
  <label><?php echo ame_l('ডাক্তার খুঁজুন', 'Find a doctor'); ?>
    <input name="q" value="<?php echo esc_html($q); ?>" placeholder="<?php echo ame_l('নাম বা হাসপাতাল', 'Name or hospital'); ?>">
  </label>
</form>
<div class="fam-chips">
  <a class="fam-chip<?php echo $spec === '' ? ' on' : ''; ?>" href="doctors.php"><?php echo ame_l('সব', 'All'); ?></a>
  <?php foreach (fam_specialists() as $s): ?>
    <a class="fam-chip<?php echo $spec === $s ? ' on' : ''; ?>" href="doctors.php?spec=<?php echo urlencode($s); ?>"><?php echo esc_html(fam_spec_label($s)); ?></a>
  <?php endforeach; ?>
</div>
<div class="fam-grid fam-grid-form">
<?php if (fam_can_write('health')): ?>
<section class="fam-panel">
  <h2><?php echo $edit ? ame_l('ডাক্তার সম্পাদনা', 'Edit doctor') : ame_l('নতুন ডাক্তার', 'New doctor'); ?></h2>
  <form class="fam-form" method="post" action="process.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="doctor_save">
    <input type="hidden" name="back" value="/modules/family/doctors.php">
    <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
    <label><?php echo ame_l('নাম', 'Name'); ?><input name="name" required value="<?php echo esc_html($edit['name'] ?? ''); ?>"></label>
    <label><?php echo ame_l('বিশেষজ্ঞ', 'Specialist'); ?><select name="specialist"><?php
        $cur = (string)($edit['specialist'] ?? '');
        foreach (fam_specialists() as $s) {
            $on = $s === $cur ? ' selected' : '';
            echo '<option value="' . esc_html($s) . '"' . $on . '>' . esc_html(fam_spec_label($s)) . '</option>';
        }
    ?></select></label>
    <label><?php echo ame_l('হাসপাতাল বা ক্লিনিক', 'Hospital or clinic'); ?><input name="hospital" value="<?php echo esc_html($edit['hospital'] ?? ''); ?>"></label>
    <div class="row2">
      <label><?php echo ame_l('মোবাইল', 'Mobile'); ?><input name="mobile" value="<?php echo esc_html($edit['mobile'] ?? ''); ?>"></label>
      <label><?php echo ame_l('কোন সদস্যের', 'Which member'); ?><select name="member_id"><?php echo fam_member_opts($edit['member_id'] ?? null); ?></select></label>
    </div>
    <label><?php echo ame_l('ঠিকানা', 'Address'); ?><textarea name="address"><?php echo esc_html($edit['address'] ?? ''); ?></textarea></label>
    <label><?php echo ame_l('চেম্বারের সময়', 'Chamber time'); ?><input name="chamber_time" value="<?php echo esc_html($edit['chamber_time'] ?? ''); ?>"></label>
    <div class="row2">
      <label><?php echo ame_l('অ্যাপয়েন্টমেন্ট', 'Appointment'); ?><input type="date" name="appointment_date" value="<?php echo esc_html($edit['appointment_date'] ?? ''); ?>"></label>
      <label><?php echo ame_l('ভিজিট ফি', 'Visit fee'); ?><input type="number" name="visit_fee" inputmode="decimal" min="0" step="1" value="<?php
        $fee_val = $edit['visit_fee'] ?? '';
        echo esc_html($fee_val !== '' && (float)$fee_val > 0 ? (string)(0 + $fee_val) : '');
      ?>"></label>
    </div>
    <label><?php echo ame_l('পরবর্তী ভিজিট', 'Next visit'); ?><input type="date" name="next_visit" value="<?php echo esc_html($edit['next_visit'] ?? ''); ?>"></label>
    <label><?php echo ame_l('নোট', 'Note'); ?><textarea name="notes"><?php echo esc_html($edit['notes'] ?? ''); ?></textarea></label>
    <button class="fam-btn" type="submit"><?php echo ame_l('সংরক্ষণ', 'Save'); ?></button>
  </form>
</section>
<section class="fam-panel" id="rx">
  <h2><?php echo ame_l('প্রেসক্রিপশন ও ওষুধ', 'Prescription and medicines'); ?></h2>
  <p class="fam-lead"><?php echo ame_l('ছবি তুলুন, তারপর ওষুধের নাম লিখুন। ওষুধ তালিকায়ও যোগ হবে।', 'Take a photo, then write the medicine names. They are added to the medicine list too.'); ?></p>
  <form class="fam-form" method="post" action="process.php" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="rx_save">
    <input type="hidden" name="back" value="/modules/family/doctors.php#rx">
    <label><?php echo ame_l('ছবি বা PDF', 'Photo or PDF'); ?>
      <input type="file" name="rx_file" accept="image/*,.pdf" capture="environment" required>
    </label>
    <label><?php echo ame_l('শিরোনাম', 'Title'); ?><input name="title" placeholder="<?php echo ame_l('যেমন রক্তচাপের প্রেসক্রিপশন', 'For example a blood-pressure prescription'); ?>"></label>
    <div class="row2">
      <label><?php echo ame_l('ডাক্তার', 'Doctor'); ?><select name="doctor_id"><?php echo fam_doctor_opts($edit['id'] ?? null); ?></select></label>
      <label><?php echo ame_l('সদস্য', 'Member'); ?><select name="member_id"><?php echo fam_member_opts($edit['member_id'] ?? null); ?></select></label>
    </div>
    <label><?php echo ame_l('তারিখ', 'Date'); ?><input type="date" name="rx_date" value="<?php echo esc_html(date('Y-m-d')); ?>"></label>
    <div class="rx-meds" id="rxMeds">
      <div class="rx-med">
        <label><?php echo ame_l('ওষুধ', 'Medicine'); ?><input name="med_name[]" autocomplete="off"></label>
        <label><?php echo ame_l('শক্তি', 'Strength'); ?><input name="med_strength[]" autocomplete="off"></label>
        <label><?php echo ame_l('কখন', 'When'); ?><input name="med_when[]" autocomplete="off"></label>
        <label><?php echo ame_l('দিনে', 'Times a day'); ?><input name="med_times[]" type="number" min="1" value="1"></label>
        <label><?php echo ame_l('কতদিন', 'Days'); ?><input name="med_days[]" type="number" min="0" value="0"></label>
      </div>
    </div>
    <button type="button" class="fam-mini" id="rxAddMed"><?php echo ame_l('আরেকটি ওষুধ', 'Another medicine'); ?></button>
    <label><?php echo ame_l('নোট', 'Note'); ?><textarea name="notes" placeholder="<?php echo ame_l('খাবারের নির্দেশ', 'How to take it'); ?>"></textarea></label>
    <button class="fam-btn" type="submit"><?php echo ame_l('প্রেসক্রিপশন সেভ', 'Save prescription'); ?></button>
  </form>
</section>
<?php endif; ?>
<section class="fam-panel">
  <h2><?php echo ame_l('ডাক্তার তালিকা', 'Doctor list'); ?></h2>
  <?php if (!$rows) echo fam_empty(ame_l('ডাক্তার নেই।', 'No doctors yet.')); ?>
  <?php foreach ($rows as $d): ?>
    <div class="fam-row">
      <div>
        <strong><?php echo esc_html($d['name']); ?></strong>
        <small><?php echo esc_html(fam_spec_label((string)$d['specialist']) . ' · ' . $d['hospital'] . ' · ' . ($d['member_name'] ?: $none)); ?>
          <?php if ((float)($d['visit_fee'] ?? 0) > 0): ?> · <?php echo ame_l('ভিজিট', 'Visit'); ?> <?php echo esc_html(fam_money_txt($d['visit_fee'])); endif; ?>
          <?php if ($d['next_visit'] || $d['appointment_date']): ?> · <?php echo ame_l('পরবর্তী', 'Next'); ?> <?php echo esc_html($d['appointment_date'] ?: $d['next_visit']); endif; ?></small>
      </div>
      <div>
        <?php echo fam_tel_btns($d['mobile']); ?>
        <?php if (fam_can_write('health')): ?>
          <a class="fam-mini" href="doctors.php?id=<?php echo (int)$d['id']; ?>"><?php echo ame_l('সম্পাদনা', 'Edit'); ?></a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>
<section class="fam-panel">
  <h2><?php echo ame_l('সেভ করা প্রেসক্রিপশন', 'Saved prescriptions'); ?></h2>
  <?php if (!$rxRows) echo fam_empty(ame_l('এখনো কোনো প্রেসক্রিপশন নেই।', 'No prescription yet.')); ?>
  <div class="fam-rx-grid">
  <?php foreach ($rxRows as $rx):
      $fn = (string)($rx['file_name'] ?? '');
      $url = fam_file_url($fn);
      $isImg = fam_is_image($fn);
      $when = (string)($rx['rx_date'] ?? '');
      if ($when === '') {
          $when = substr((string)$rx['created_at'], 0, 10);
      }
      $lines = $rxMeds[(int)$rx['id']] ?? [];
  ?>
    <article class="fam-rx-card">
      <?php if ($url && $isImg): ?>
        <a href="<?php echo esc_html($url); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_html($url); ?>" alt=""></a>
      <?php elseif ($url): ?>
        <a class="fam-mini" href="<?php echo esc_html($url); ?>" target="_blank" rel="noopener"><?php echo ame_l('PDF খুলুন', 'Open PDF'); ?></a>
      <?php endif; ?>
      <strong><?php echo esc_html($rx['title']); ?></strong>
      <small><?php echo esc_html(trim(($rx['doctor_name'] ?: '') . ' · ' . ($rx['member_name'] ?: '') . ' · ' . $when, ' ·')); ?></small>
      <?php if ($lines): ?>
        <ul class="fam-rx-list">
          <?php foreach ($lines as $ln): ?>
            <li><?php echo esc_html(trim($ln['name'] . ' ' . ($ln['strength'] ?? '') . ' · ' . ($ln['when_to_take'] ?? ''), ' ·')); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <a class="fam-mini" href="rx.php?id=<?php echo (int)$rx['id']; ?>"><?php echo ame_l('সম্পূর্ণ প্রেসক্রিপশন তৈরি করুন', 'Make the full prescription'); ?></a>
      <a class="fam-mini" href="medicines.php?rx=<?php echo (int)$rx['id']; ?>"><?php echo ame_l('ওষুধ দেখুন', 'View medicines'); ?></a>
      <?php if (fam_can_write('health')): ?>
        <form method="post" action="process.php" onsubmit="return confirm(<?php echo json_encode(ame_l('প্রেসক্রিপশন আর্কাইভ করবেন? ছবি থাকবে।', 'Archive this prescription? The photo stays.'), JSON_UNESCAPED_UNICODE); ?>)">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="do" value="rx_archive">
          <input type="hidden" name="back" value="/modules/family/doctors.php#rx">
          <input type="hidden" name="id" value="<?php echo (int)$rx['id']; ?>">
          <button class="fam-mini" type="submit"><?php echo ame_l('আর্কাইভ', 'Archive'); ?></button>
        </form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
  </div>
</section>
</div>
<script>
(function () {
  var box = document.getElementById('rxMeds');
  var btn = document.getElementById('rxAddMed');
  if (!box || !btn) return;
  btn.addEventListener('click', function () {
    var row = box.querySelector('.rx-med');
    if (!row) return;
    var copy = row.cloneNode(true);
    copy.querySelectorAll('input').forEach(function (input) {
      if (input.type === 'number' && input.name.indexOf('times') !== -1) input.value = '1';
      else if (input.type === 'number') input.value = '0';
      else input.value = '';
    });
    box.appendChild(copy);
  });
})();
</script>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
