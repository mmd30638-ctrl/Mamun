<?php
require_once __DIR__ . '/_boot.php';
if (!fam_can_see('health')) {
    $_SESSION['fam_err'] = ame_l('স্বাস্থ্য তথ্য দেখার অনুমতি নেই।', 'You cannot view health records.');
    header('Location: /modules/family/index.php');
    exit;
}
$id = (int) ($_GET['id'] ?? 0);
$rx = $id > 0 ? fam_one('SELECT d.*, m.name AS member_name, doc.name AS doctor_name, doc.hospital, doc.specialist
    FROM family_documents d
    LEFT JOIN family_members m ON m.id=d.member_id
    LEFT JOIN family_doctors doc ON doc.id=d.doctor_id
    WHERE d.id=' . $id . " AND d.kind='প্রেসক্রিপশন'") : null;
if (!$rx) {
    $_SESSION['fam_err'] = ame_l('প্রেসক্রিপশন পাওয়া যায়নি।', 'Prescription was not found.');
    header('Location: /modules/family/doctors.php#rx');
    exit;
}
$lines = fam_rows('SELECT * FROM family_rx_lines WHERE document_id=' . (int) $rx['id'] . ' ORDER BY id');
if (!$lines) {
    $lines = fam_rows('SELECT name, strength, when_to_take, times_per_day, days_for FROM family_medicines WHERE document_id=' . (int) $rx['id'] . ' ORDER BY id');
}
$when = (string) ($rx['rx_date'] ?? '');
if ($when === '') {
    $when = substr((string) $rx['created_at'], 0, 10);
}
$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8');
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_doctors', 'fam_lead_doctors');
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker"><?php echo ame_l('পারিবারিক খাতা', 'Family book'); ?></p>
  <h2><?php echo ame_l('সম্পূর্ণ প্রেসক্রিপশন', 'Full prescription'); ?></h2>
  <p><?php echo ame_l('একই রেকর্ড — নতুন ডুপ্লিকেট জেনারেটর নয়।', 'The same record — not a second generator.'); ?></p>
</header>
<p><a class="fam-mini" href="doctors.php#rx"><?php echo ame_l('প্রেসক্রিপশনে ফিরুন', 'Back to prescriptions'); ?></a></p>
<div id="memo-container" class="fam-rx-paper" data-ame-capture="clone" style="max-width:720px;margin:0 auto 16px;padding:22px;border:1px solid #cbd5e1;border-radius:16px;background:#fff;color:#0b1e4d;">
  <h3 style="margin:0 0 6px;"><?php echo esc_html((string) $rx['title']); ?></h3>
  <p style="margin:0 0 12px;"><?php echo esc_html(trim(($rx['member_name'] ?: '') . ' · ' . ($rx['doctor_name'] ?: '') . ' · ' . $when, ' ·')); ?></p>
  <?php if (!empty($rx['notes'])): ?>
    <p><?php echo nl2br(esc_html((string) $rx['notes'])); ?></p>
  <?php endif; ?>
  <table style="width:100%;border-collapse:collapse;">
    <thead>
      <tr>
        <th style="text-align:left;border-bottom:1px solid #94a3b8;padding:6px 0;"><?php echo ame_l('ওষুধ', 'Medicine'); ?></th>
        <th style="text-align:left;border-bottom:1px solid #94a3b8;padding:6px 0;"><?php echo ame_l('শক্তি', 'Strength'); ?></th>
        <th style="text-align:left;border-bottom:1px solid #94a3b8;padding:6px 0;"><?php echo ame_l('নিয়ম', 'How'); ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($lines as $ln): ?>
      <tr>
        <td style="padding:7px 0;border-bottom:1px dashed #e2e8f0;"><?php echo esc_html((string) ($ln['name'] ?? '')); ?></td>
        <td style="padding:7px 0;border-bottom:1px dashed #e2e8f0;"><?php echo esc_html((string) ($ln['strength'] ?? '')); ?></td>
        <td style="padding:7px 0;border-bottom:1px dashed #e2e8f0;"><?php echo esc_html(trim(($ln['when_to_take'] ?? '') . ' · ' . ((int) ($ln['times_per_day'] ?? 0) > 0 ? ((int) $ln['times_per_day'] . 'x') : ''), ' ·')); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="fam-rx-acts" style="display:flex;flex-wrap:wrap;gap:8px;">
  <button type="button" class="fam-btn" onclick="if(window.ameMemoCapture)ameMemoCapture('share','rx-<?php echo (int) $rx['id']; ?>')"><?php echo ame_l('শেয়ার', 'Share'); ?></button>
  <button type="button" class="fam-btn" onclick="window.print()"><?php echo ame_l('প্রিন্ট', 'Print'); ?></button>
  <button type="button" class="fam-btn" onclick="if(window.ameMemoCapture)ameMemoCapture('download','rx-<?php echo (int) $rx['id']; ?>')"><?php echo ame_l('ডাউনলোড', 'Download'); ?></button>
  <a class="fam-btn" href="medicines.php?rx=<?php echo (int) $rx['id']; ?>"><?php echo ame_l('ওষুধ তালিকা', 'Medicine list'); ?></a>
</p>
<script src="/assets/js/html2canvas.min.js?v=<?php echo $ver; ?>"></script>
<script src="/assets/js/ame-memo-file.js?v=<?php echo $ver; ?>"></script>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
