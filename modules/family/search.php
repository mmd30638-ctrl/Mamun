<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
$q = trim((string)($_GET['q'] ?? ''));
fam_open('fam_title_search', 'fam_lead_search');
$hits = $q !== '' ? fam_search($q) : [];
$quick = [
    ['ডাক্তার', 'doctors.php'],
    ['ওষুধ', 'medicines.php'],
    ['বই', 'library.php'],
    ['জরুরি', 'contacts.php?g='],
    ['এই মাসের খরচ', 'expenses.php'],
];
$urgent = fam_one("SELECT id FROM family_groups WHERE name='জরুরি যোগাযোগ' LIMIT 1");
?>
<div class="fam-chips">
  <a class="fam-chip" href="doctors.php">ডাক্তার</a>
  <a class="fam-chip" href="medicines.php">ওষুধ</a>
  <a class="fam-chip" href="library.php">বই</a>
  <a class="fam-chip" href="contacts.php<?php echo $urgent ? '?g=' . (int)$urgent['id'] : ''; ?>">জরুরি</a>
  <a class="fam-chip" href="expenses.php">এই মাসের খরচ</a>
  <a class="fam-chip" href="bazaar.php">বাজার চাহিদা</a>
  <a class="fam-chip" href="shopping.php">বাজার</a>
</div>
<?php if ($q === ''): ?>
  <?php echo fam_empty('উপরে সার্চ লিখুন, অথবা চিপে ট্যাপ করুন।'); ?>
<?php elseif (!$hits): ?>
  <?php echo fam_empty('“' . $q . '” পাওয়া যায়নি।'); ?>
<?php else: ?>
<section class="fam-panel">
  <h2><?php echo count($hits); ?> টি মিল</h2>
  <?php foreach ($hits as $h): ?>
    <a class="fam-row" href="<?php echo esc_html($h['href']); ?>">
      <div><strong><?php echo esc_html($h['label']); ?></strong><small><?php echo esc_html($h['kind']); ?></small></div>
    </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
