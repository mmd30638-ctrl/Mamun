<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_bazaar', 'fam_lead_bazaar');

$db = fam_db();
$month = fam_bazaar_month_norm((string) ($_GET['m'] ?? date('Y-m')));
$cat = trim((string) ($_GET['cat'] ?? ''));
if ($cat !== '' && !fam_bazaar_cat_ok($cat)) {
    $cat = '';
}
$q = trim((string) ($_GET['q'] ?? ''));
$editId = (int) ($_GET['id'] ?? 0);
$demand = fam_bazaar_find($db, $month);
$lines = fam_bazaar_lines($db, (int) ($demand['id'] ?? 0), $cat);
if ($q !== '') {
    $ql = mb_strtolower($q, 'UTF-8');
    $lines = array_values(array_filter($lines, static function ($row) use ($ql) {
        return str_contains(mb_strtolower((string) $row['name'], 'UTF-8'), $ql);
    }));
}
$stats = fam_bazaar_stats(fam_bazaar_lines($db, (int) ($demand['id'] ?? 0), ''));
$edit = null;
if ($editId > 0) {
    foreach ($lines as $row) {
        if ((int) $row['id'] === $editId) {
            $edit = $row;
            break;
        }
    }
    if (!$edit) {
        $edit = fam_one('SELECT * FROM family_bazaar_lines WHERE id=' . $editId);
    }
}
$memoQs = 'm=' . rawurlencode($month) . ($cat !== '' ? '&cat=' . rawurlencode($cat) : '');
$houseEntered = fam_bazaar_house_entered($demand);
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker">পারিবারিক খাতা</p>
  <h2><?php echo esc_html(fam_bazaar_month_label($month)); ?> · বাজার চাহিদা</h2>
  <?php if ($houseEntered): ?>
  <p>ঘরের তালিকা এন্ট্রি করা হয়েছিল — বামে সিরিয়াল, নিচে মোট পণ্য। পরে পণ্য বাড়ান-কমান, মেমো আবার প্রিন্ট।</p>
  <?php else: ?>
  <p>চাহিদা লিখুন — ক্যাটাগরি ঐচ্ছিক। পরে পণ্য বাড়ান-কমান, মেমো আবার প্রিন্ট। বামে সিরিয়াল, নিচে মোট পণ্য।</p>
  <?php endif; ?>
</header>

<form class="fam-q" method="get" style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
  <input type="month" name="m" value="<?php echo esc_html($month); ?>">
  <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?php echo esc_html($cat); ?>"><?php endif; ?>
  <input type="search" name="q" value="<?php echo esc_html($q); ?>" placeholder="পণ্য খুঁজুন" style="min-width:10rem">
  <button type="submit">মাস খুলুন</button>
  <a class="fam-mini" href="bazaar_memo.php?<?php echo $memoQs; ?>">মেমো প্রিন্ট</a>
  <a class="fam-mini" href="bazaar_memo.php?<?php echo $memoQs; ?>&amp;all=1">সব আইটেম</a>
  <a class="fam-mini" href="bazaar_statement.php?m=<?php echo esc_html($month); ?>">স্টেটমেন্ট</a>
</form>

<div class="fam-kpis">
  <div class="fam-kpi"><span>চাহিদা আইটেম</span><b><?php echo (int) $stats['items']; ?></b></div>
  <div class="fam-kpi"><span>কেনা হয়েছে</span><b><?php echo (int) $stats['bought']; ?></b></div>
  <div class="fam-kpi"><span>আনুমানিক</span><b><?php echo fam_money_txt($stats['est']); ?></b></div>
  <div class="fam-kpi"><span>বাজার খরচ</span><b><?php echo fam_money_txt($stats['paid']); ?></b></div>
</div>

<div class="fam-chips">
  <a class="fam-chip<?php echo $cat === '' ? ' on' : ''; ?>" href="bazaar.php?m=<?php echo esc_html($month); ?>">সব</a>
  <?php foreach (fam_bazaar_cats() as $c): ?>
    <a class="fam-chip<?php echo $cat === $c ? ' on' : ''; ?>" href="bazaar.php?m=<?php echo esc_html($month); ?>&amp;cat=<?php echo urlencode($c); ?>"><?php echo esc_html($c); ?></a>
  <?php endforeach; ?>
</div>

<div class="fam-grid<?php echo $edit ? ' fam-grid-form' : ''; ?>">
<?php if (fam_can_write()): ?>
<section class="fam-panel" id="bz-add">
  <h2><?php echo $edit ? 'চাহিদা সম্পাদনা' : 'একাধিক পণ্য যোগ'; ?><?php echo $cat !== '' ? ' · ' . esc_html($cat) : ''; ?></h2>
  <?php if ($edit): ?>
  <form class="fam-form" method="post" action="process.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="bazaar_line_save">
    <input type="hidden" name="back" value="/modules/family/bazaar.php?m=<?php echo esc_html($month); ?><?php echo $cat !== '' ? '&cat=' . urlencode($cat) : ''; ?>">
    <input type="hidden" name="id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
    <input type="hidden" name="ym" value="<?php echo esc_html($month); ?>">
    <label>পণ্য<input name="name" required maxlength="160" value="<?php echo esc_html($edit['name'] ?? ''); ?>" placeholder="যেমন ইলিশ, আলু, হলুদ"></label>
    <div class="row2">
      <label>ক্যাটাগরি<select name="category"><?php echo fam_opts(fam_bazaar_cats(), $edit['category'] ?? $cat, 'ঐচ্ছিক'); ?></select></label>
      <label>একক<select name="unit"><?php echo fam_opts(fam_bazaar_units(), $edit['unit'] ?? 'কেজি', 'ঐচ্ছিক'); ?></select></label>
    </div>
    <div class="row2">
      <label>পরিমাণ<input name="qty" value="<?php echo esc_html($edit['qty'] ?? ''); ?>" placeholder="২"></label>
      <label>আনুমানিক দাম ৳<input name="est_price" type="number" step="0.01" min="0" value="<?php echo esc_html($edit['est_price'] ?? ''); ?>"></label>
    </div>
    <label>নোট<textarea name="notes" maxlength="400"><?php echo esc_html($edit['notes'] ?? ''); ?></textarea></label>
    <button class="fam-btn" type="submit">তালিকায় রাখুন</button>
  </form>
  <?php else:
      $defCat = $cat;
      $catOpts = fam_opts(fam_bazaar_cats(), $defCat, 'ঐচ্ছিক');
      $unitOpts = fam_opts(fam_bazaar_units(), 'কেজি', 'ঐচ্ছিক');
      $hints = ['ইলিশ', 'আলু', 'হলুদ', 'চিনি', 'তেল', 'ডিম'];
  ?>
  <p style="margin:0 0 10px;color:#334155;font-weight:700">এক মেমোতে যতগুলো পণ্য লাগে লিখুন। খালি সারি বাদ যাবে। ক্যাটাগরি না দিলেও চলবে। পরে আবার যোগ-মুছবেন, মেমো আবার প্রিন্ট হবে।</p>
  <form class="fam-form" method="post" action="process.php" id="bz-multi">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="bazaar_line_save">
    <input type="hidden" name="back" value="/modules/family/bazaar.php?m=<?php echo esc_html($month); ?><?php echo $cat !== '' ? '&cat=' . urlencode($cat) : ''; ?>">
    <input type="hidden" name="id" value="0">
    <input type="hidden" name="ym" value="<?php echo esc_html($month); ?>">
    <div id="bz-rows">
      <?php for ($i = 0; $i < 6; $i++): ?>
      <div class="bz-line" style="border:1px solid #dbe3ef;border-radius:12px;padding:10px;margin:0 0 8px;background:#f8fafc">
        <strong style="display:block;margin:0 0 6px;color:#0b1e4d">পণ্য <?php echo $i + 1; ?></strong>
        <button type="button" class="fam-mini bz-drop" style="margin:0 0 8px">সারি বাদ</button>
        <label>নাম<input name="name[]" maxlength="160" placeholder="<?php echo esc_html($hints[$i] ?? 'পণ্যের নাম'); ?>"></label>
        <div class="row2">
          <label>ক্যাটাগরি<select name="category[]"><?php echo $catOpts; ?></select></label>
          <label>একক<select name="unit[]"><?php echo $unitOpts; ?></select></label>
        </div>
        <div class="row2">
          <label>পরিমাণ<input name="qty[]" placeholder="২"></label>
          <label>আনুমানিক ৳<input name="est_price[]" type="number" step="0.01" min="0"></label>
        </div>
        <label>নোট<input name="notes[]" maxlength="400" placeholder="ঐচ্ছিক"></label>
      </div>
      <?php endfor; ?>
    </div>
    <p style="display:flex;flex-wrap:wrap;gap:8px;margin:0">
      <button class="fam-mini" type="button" id="bz-add-row">আরেকটি পণ্য</button>
      <button class="fam-btn" type="submit">সবগুলো তালিকায় রাখুন</button>
    </p>
  </form>
  <template id="bz-row-tpl">
    <div class="bz-line" style="border:1px solid #dbe3ef;border-radius:12px;padding:10px;margin:0 0 8px;background:#f8fafc">
      <strong style="display:block;margin:0 0 6px;color:#0b1e4d">পণ্য</strong>
      <button type="button" class="fam-mini bz-drop" style="margin:0 0 8px">সারি বাদ</button>
      <label>নাম<input name="name[]" maxlength="160" placeholder="পণ্যের নাম"></label>
      <div class="row2">
        <label>ক্যাটাগরি<select name="category[]"><?php echo $catOpts; ?></select></label>
        <label>একক<select name="unit[]"><?php echo $unitOpts; ?></select></label>
      </div>
      <div class="row2">
        <label>পরিমাণ<input name="qty[]" placeholder="২"></label>
        <label>আনুমানিক ৳<input name="est_price[]" type="number" step="0.01" min="0"></label>
      </div>
      <label>নোট<input name="notes[]" maxlength="400" placeholder="ঐচ্ছিক"></label>
    </div>
  </template>
  <script>
  (function () {
    var btn = document.getElementById('bz-add-row');
    var box = document.getElementById('bz-rows');
    var tpl = document.getElementById('bz-row-tpl');
    if (!btn || !box || !tpl) return;
    function renumber() {
      box.querySelectorAll('.bz-line').forEach(function (el, i) {
        var title = el.querySelector('strong');
        if (title) title.textContent = 'পণ্য ' + (i + 1);
      });
    }
    btn.addEventListener('click', function () {
      var node = tpl.content.cloneNode(true);
      box.appendChild(node);
      renumber();
      var last = box.querySelector('.bz-line:last-child input[name="name[]"]');
      if (last) last.focus();
    });
    box.addEventListener('click', function (e) {
      var drop = e.target.closest('.bz-drop');
      if (!drop) return;
      var row = drop.closest('.bz-line');
      if (!row) return;
      if (box.querySelectorAll('.bz-line').length < 2) return;
      row.remove();
      renumber();
    });
  })();
  </script>
  <?php endif; ?>
</section>
<?php endif; ?>
<section class="fam-panel">
  <h2><?php echo $cat !== '' ? esc_html($cat) : 'এই মাসের তালিকা'; ?> · মোট পণ্য <?php echo fam_bazaar_bn_num(count($lines)); ?></h2>
  <?php if (!$lines): ?>
    <?php echo fam_empty($cat !== '' ? 'এই ক্যাটাগরিতে এখনো চাহিদা নেই।' : 'এই মাসে এখনো চাহিদা নেই। উপরে পণ্য যোগ করুন, অথবা ঘরের তালিকা মেমোতে রাখুন।'); ?>
  <?php endif; ?>
  <?php
  $sl = 1;
  $lastHead = null;
  foreach ($lines as $s):
      $rowCat = trim((string) $s['category']);
      $head = $rowCat !== '' ? $rowCat : 'ক্যাটাগরি নেই';
      if ($cat === '' && $head !== $lastHead):
          $lastHead = $head;
          echo '<p class="fam-desk-kicker" style="margin:12px 0 6px">' . esc_html($head) . '</p>';
      endif;
      $qtyTxt = fam_bazaar_qty_txt($s);
      $slBn = fam_bazaar_bn_num($sl) . '।';
      $sl++;
  ?>
    <div class="fam-row">
      <div style="display:flex;gap:10px;align-items:flex-start">
        <b style="min-width:2.2rem;color:#0b1e4d"><?php echo esc_html($slBn); ?></b>
        <div>
        <strong><?php echo esc_html($s['name']); ?></strong>
        <small><?php echo esc_html($qtyTxt); ?><?php echo !empty($s['is_bought']) ? ' · কেনা' : ' · চাহিদা'; ?></small>
        </div>
      </div>
      <div>
        <b><?php echo (float) $s['est_price'] > 0 ? fam_money_txt((float) $s['est_price']) : ''; ?></b>
        <?php if (empty($s['is_bought']) && fam_can_write()): ?>
          <form method="post" action="process.php" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="do" value="bazaar_line_bought">
            <input type="hidden" name="back" value="/modules/family/bazaar.php?m=<?php echo esc_html($month); ?><?php echo $cat !== '' ? '&cat=' . urlencode($cat) : ''; ?>">
            <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
            <input type="hidden" name="ym" value="<?php echo esc_html($month); ?>">
            <input name="paid_price" type="number" step="0.01" min="0" placeholder="কেনার দাম" style="width:7rem;min-height:40px" value="<?php echo esc_html($s['est_price'] > 0 ? $s['est_price'] : ''); ?>">
            <button class="fam-mini" type="submit">কেনা হয়েছে</button>
          </form>
        <?php elseif (!empty($s['is_bought'])): ?>
          <small><?php echo fam_money_txt(fam_bazaar_line_spend($s)); ?></small>
        <?php endif; ?>
        <?php if (fam_can_write()): ?>
          <a class="fam-mini" href="bazaar.php?m=<?php echo esc_html($month); ?>&amp;id=<?php echo (int) $s['id']; ?><?php echo $cat !== '' ? '&amp;cat=' . urlencode($cat) : ''; ?>">সম্পাদনা</a>
          <form method="post" action="process.php" style="display:inline" onsubmit="return confirm('এই চাহিদা মুছবেন?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="do" value="bazaar_line_delete">
            <input type="hidden" name="back" value="/modules/family/bazaar.php?m=<?php echo esc_html($month); ?>">
            <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
            <button class="fam-mini" type="submit">মুছুন</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <p style="margin-top:12px">
    <a class="fam-btn" href="bazaar_memo.php?<?php echo $memoQs; ?>">এই তালিকার মেমো প্রিন্ট</a>
  </p>
</section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
