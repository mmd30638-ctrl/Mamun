<?php
require_once __DIR__ . '/_boot.php';
$id = (int)($_GET['id'] ?? 0);
$m = fam_member($id);
if (!$m) {
    $_SESSION['fam_err'] = 'সদস্য পাওয়া যায়নি।';
    header('Location: /modules/family/members.php');
    exit;
}
include __DIR__ . '/../../partials/header.php';
fam_open($m['name'], ame_word(fam_member_relation($m) !== '' ? fam_member_relation($m) : t('fam_title_member')) . ' — ' . t('fam_lead_member'));
$docs = fam_can_see('health') ? fam_doctors($id) : [];
$meds = fam_can_see('health') ? fam_rows('SELECT * FROM family_medicines WHERE member_id=' . $id . ' ORDER BY name') : [];
$ev = fam_rows('SELECT * FROM family_events WHERE member_id=' . $id . ' ORDER BY event_date DESC LIMIT 8');
$ex = fam_can_see('money') ? fam_rows('SELECT * FROM family_expenses WHERE member_id=' . $id . ' ORDER BY exp_date DESC LIMIT 8') : [];
$notes = fam_rows('SELECT * FROM family_notes WHERE member_id=' . $id . ' ORDER BY is_pinned DESC, id DESC LIMIT 6');
$age = '';
if (!empty($m['birth_date'])) {
    try {
        $age = (new DateTime($m['birth_date']))->diff(new DateTime('today'))->y . ' বছর';
    } catch (Throwable $e) {
    }
}
?>
<div class="fam-profile">
  <p class="fam-kicker">সদস্য প্রোফাইল</p>
  <h2><?php echo esc_html($m['name']); ?></h2>
  <p><?php echo esc_html($m['relation_name'] ?: ''); ?><?php echo $age ? ' · ' . esc_html($age) : ''; ?><?php echo !empty($m['birth_date']) ? ' · জন্ম ' . esc_html($m['birth_date']) : ''; ?></p>
  <p><?php echo esc_html($m['address'] ?: ''); ?></p>
  <?php if ($m['important_info']): ?><p><?php echo nl2br(esc_html($m['important_info'])); ?></p><?php endif; ?>
  <div class="fam-actions">
    <?php echo fam_tel_btns($m['mobile']); ?>
    <?php if ($m['emergency_phone']): ?>
      <span class="fam-mini">জরুরি: <?php echo esc_html($m['emergency_name'] . ' ' . $m['emergency_phone']); ?></span>
    <?php endif; ?>
    <?php if (fam_can_write()): ?>
      <a class="fam-btn soft" href="members.php?id=<?php echo $id; ?>">সম্পাদনা</a>
    <?php endif; ?>
  </div>
</div>
<div class="fam-hub" style="margin-top:1rem">
  <?php if (fam_can_see('health')): ?>
  <section class="fam-panel">
    <h2>ডাক্তার</h2>
    <?php if (!$docs) echo fam_empty('এই সদস্যের ডাক্তার নেই।', 'doctors.php', 'ডাক্তার যোগ'); ?>
    <?php foreach ($docs as $d): ?>
      <div class="fam-row"><div><strong><?php echo esc_html($d['name']); ?></strong><small><?php echo esc_html($d['specialist'] . ' · ' . $d['hospital']); ?></small></div><?php echo fam_tel_btns($d['mobile']); ?></div>
    <?php endforeach; ?>
  </section>
  <section class="fam-panel">
    <h2>ওষুধ</h2>
    <?php if (!$meds) echo fam_empty('চলমান ওষুধ নেই।', 'medicines.php', 'ওষুধ যোগ'); ?>
    <?php foreach ($meds as $x): ?>
      <div class="fam-row"><div><strong><?php echo esc_html($x['name']); ?></strong><small>স্টক <?php echo esc_html($x['stock']); ?> · <?php echo esc_html($x['when_to_take']); ?></small></div></div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
  <section class="fam-panel">
    <h2>অ্যাপয়েন্টমেন্ট ও তারিখ</h2>
    <?php if (!$ev) echo fam_empty('কোনো তারিখ নেই।'); ?>
    <?php foreach ($ev as $e): ?>
      <div class="fam-row"><div><strong><?php echo esc_html($e['title']); ?></strong><small><?php echo esc_html(fam_kind_label($e['kind']) . ' · ' . $e['event_date']); ?></small></div></div>
    <?php endforeach; ?>
  </section>
  <?php if (fam_can_see('money')): ?>
  <section class="fam-panel">
    <h2>খরচ</h2>
    <?php if (!$ex) echo fam_empty('এই সদস্যের খরচ নেই।', 'expenses.php', 'খরচ যোগ'); ?>
    <?php foreach ($ex as $e): ?>
      <div class="fam-row"><div><strong><?php echo esc_html($e['purpose'] ?: $e['category']); ?></strong><small><?php echo esc_html($e['exp_date']); ?></small></div><b><?php echo fam_money_txt($e['amount']); ?></b></div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
  <section class="fam-panel">
    <h2>নোট</h2>
    <?php if (!$notes) echo fam_empty('নোট নেই।', 'notes.php', 'নোট লিখুন'); ?>
    <?php foreach ($notes as $n): ?>
      <div class="fam-row"><div><strong><?php echo esc_html($n['title']); ?></strong><small><?php echo esc_html(mb_strimwidth((string)$n['body'], 0, 80, '…')); ?></small></div></div>
    <?php endforeach; ?>
  </section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
