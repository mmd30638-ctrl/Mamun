<?php
require_once __DIR__ . '/_boot.php';
if (!fam_can_see('money')) {
    $_SESSION['fam_err'] = 'খরচ দেখার অনুমতি নেই।';
    header('Location: /modules/family/index.php');
    exit;
}
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_expenses', 'fam_lead_expenses');
$q = trim((string)($_GET['q'] ?? ''));
$cat = trim((string)($_GET['cat'] ?? ''));
$month = trim((string)($_GET['m'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$edit = !empty($_GET['id']) ? fam_one('SELECT * FROM family_expenses WHERE id=' . (int)$_GET['id']) : null;
$sql = "SELECT e.*, m.name AS member_name FROM family_expenses e LEFT JOIN family_members m ON m.id=e.member_id WHERE e.exp_date LIKE '" . fam_db()->real_escape_string($month) . "%'";
if ($cat !== '') {
    $sql .= " AND e.category='" . fam_db()->real_escape_string($cat) . "'";
}
if ($q !== '') {
    $l = fam_db()->real_escape_string($q);
    $sql .= " AND (e.purpose LIKE '%$l%' OR e.spent_by LIKE '%$l%')";
}
$sql .= ' ORDER BY e.exp_date DESC, e.id DESC';
$rows = fam_rows($sql);
$sum = 0;
foreach ($rows as $r) {
    $sum += (float)$r['amount'];
}
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker">পারিবারিক খাতা</p>
  <h2>খরচ</h2>
  <p>বিক্রয় পাতার নেভি রং, টাকা সবুজ — যোগফল আগের মতো।</p>
</header>
<div class="fam-chips">
  <a class="fam-chip<?php echo $cat === '' ? ' on' : ''; ?>" href="expenses.php?m=<?php echo esc_html($month); ?>"><?php echo esc_html(t('fam_all')); ?> · <?php echo fam_money_txt($sum); ?></a>
  <?php foreach (fam_exp_cats() as $c): ?>
    <a class="fam-chip<?php echo $cat === $c ? ' on' : ''; ?>" href="expenses.php?m=<?php echo esc_html($month); ?>&cat=<?php echo urlencode($c); ?>"><?php echo esc_html(ame_word($c)); ?></a>
  <?php endforeach; ?>
</div>
<form class="fam-q" method="get" style="margin-bottom:1rem;max-width:16rem">
  <input type="month" name="m" value="<?php echo esc_html($month); ?>">
  <button type="submit"><?php echo esc_html(t('fam_month')); ?></button>
</form>
<div class="fam-grid fam-grid-form">
<?php if (fam_can_write('money')): ?>
<section class="fam-panel">
  <h2><?php echo $edit ? esc_html(t('fam_edit_expense')) : esc_html(t('fam_new_expense')); ?></h2>
  <form class="fam-form" method="post" action="process.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="expense_save">
    <input type="hidden" name="back" value="/modules/family/expenses.php">
    <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
    <label><?php echo esc_html(t('fam_date')); ?><input type="date" name="exp_date" value="<?php echo esc_html($edit['exp_date'] ?? date('Y-m-d')); ?>"></label>
    <label><?php echo esc_html(t('fam_category')); ?><select name="category"><?php echo fam_opts(fam_exp_cats(), $edit['category'] ?? ''); ?></select></label>
    <label><?php echo esc_html(t('fam_amount')); ?><input name="amount" type="number" step="0.01" required value="<?php echo esc_html($edit['amount'] ?? ''); ?>"></label>
    <label><?php echo esc_html(t('fam_spent_by')); ?><input name="spent_by" value="<?php echo esc_html($edit['spent_by'] ?? ($_SESSION['username'] ?? '')); ?>"></label>
    <label><?php echo esc_html(t('fam_purpose')); ?><input name="purpose" value="<?php echo esc_html($edit['purpose'] ?? ''); ?>"></label>
    <label><?php echo esc_html(t('fam_payment')); ?><select name="pay_method"><?php echo fam_opts(fam_pay_methods(), $edit['pay_method'] ?? 'নগদ'); ?></select></label>
    <label><?php echo esc_html(t('fam_member')); ?><select name="member_id"><?php echo fam_member_opts($edit['member_id'] ?? null); ?></select></label>
    <label><?php echo esc_html(t('fam_note')); ?><textarea name="notes"><?php echo esc_html($edit['notes'] ?? ''); ?></textarea></label>
    <button class="fam-btn" type="submit"><?php echo esc_html(t('fam_save')); ?></button>
  </form>
</section>
<?php endif; ?>
<section class="fam-panel">
  <h2><?php echo esc_html(t('fam_this_month')); ?> · <?php echo fam_money_txt($sum); ?></h2>
  <?php if (!$rows) echo fam_empty(t('fam_no_expense')); ?>
  <?php foreach ($rows as $e): ?>
    <div class="fam-row">
      <div>
        <strong><?php echo esc_html($e['purpose'] ?: ame_word($e['category'])); ?></strong>
        <small><?php echo esc_html($e['exp_date'] . ' · ' . ame_word($e['category']) . ' · ' . ($e['spent_by'] ?: '') . ' · ' . ame_word($e['pay_method'])); ?></small>
      </div>
      <div>
        <b><?php echo fam_money_txt($e['amount']); ?></b>
        <?php if (fam_can_write('money')): ?><a class="fam-mini" href="expenses.php?id=<?php echo (int)$e['id']; ?>"><?php echo esc_html(t('fam_edit')); ?></a><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
