<?php
require_once __DIR__ . '/_boot.php';
if (!fam_can_see('money') && !fam_can_see('health')) {
    header('Location: /modules/family/index.php');
    exit;
}
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_med_buy', 'fam_lead_med_buy');
$rows = fam_rows('SELECT b.*, m.name AS member_name FROM family_med_buys b LEFT JOIN family_members m ON m.id=b.member_id ORDER BY b.buy_date DESC, b.id DESC LIMIT 80');
$meds = fam_rows('SELECT id, name FROM family_medicines ORDER BY name');
$medOpts = [];
foreach ($meds as $x) {
    $medOpts[(int)$x['id']] = $x['name'];
}
?>
<div class="fam-grid fam-grid-form">
<?php if (fam_can_write('money')): ?>
<section class="fam-panel">
  <h2><?php echo ame_l('নতুন কেনা', 'New purchase'); ?></h2>
  <form class="fam-form" method="post" action="process.php" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="med_buy_save">
    <input type="hidden" name="back" value="/modules/family/medicine_buy.php">
    <label><?php echo ame_l('তারিখ', 'Date'); ?><input type="date" name="buy_date" value="<?php echo date('Y-m-d'); ?>"></label>
    <label><?php echo ame_l('ওষুধের নাম', 'Medicine name'); ?><input name="name" required></label>
    <label><?php echo ame_l('তালিকার ওষুধ', 'Medicine on the list'); ?><select name="medicine_id"><?php echo fam_opts($medOpts, null, ame_l('স্টকে যোগ হবে', 'Add to stock')); ?></select></label>
    <div class="row2">
      <label><?php echo ame_l('পরিমাণ', 'Quantity'); ?><input name="qty" type="number" step="0.01" required></label>
      <label><?php echo ame_l('দর, প্রতি পিস', 'Price each'); ?><input name="price" type="number" step="0.01"></label>
    </div>
    <label><?php echo ame_l('ফার্মেসি', 'Pharmacy'); ?><input name="pharmacy"></label>
    <label><?php echo ame_l('সদস্য', 'Member'); ?><select name="member_id"><?php echo fam_member_opts(); ?></select></label>
    <label><?php echo ame_l('রসিদ', 'Receipt'); ?><input type="file" name="invoice_file" accept="image/*,.pdf" capture="environment"></label>
    <label><?php echo ame_l('নোট', 'Note'); ?><textarea name="notes"></textarea></label>
    <button class="fam-btn" type="submit"><?php echo ame_l('এন্ট্রি', 'Save'); ?></button>
  </form>
</section>
<?php endif; ?>
<section class="fam-panel">
  <h2><?php echo ame_l('কেনার ইতিহাস', 'Purchase history'); ?></h2>
  <?php if (!$rows) echo fam_empty(ame_l('এখনো কেনা হয়নি।', 'Nothing bought yet.')); ?>
  <table class="fam-table">
    <tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('ওষুধ', 'Medicine'); ?></th><th><?php echo ame_l('পরিমাণ', 'Qty'); ?></th><th><?php echo ame_l('মোট', 'Total'); ?></th><th><?php echo ame_l('ফার্মেসি', 'Pharmacy'); ?></th></tr>
    <?php foreach ($rows as $r):
        $lineTotal = (float)$r['qty'] * (float)$r['price'];
    ?>
      <tr>
        <td><?php echo esc_html($r['buy_date']); ?></td>
        <td><?php echo esc_html($r['name']); ?><br><span class="fam-muted"><?php echo esc_html($r['member_name'] ?: ''); ?></span></td>
        <td><?php echo esc_html($r['qty']); ?></td>
        <td><?php echo fam_money_txt($lineTotal); ?></td>
        <td><?php echo esc_html($r['pharmacy']); ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
