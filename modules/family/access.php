<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_access', 'fam_lead_access');
$isShopAdmin = strtolower((string)($_SESSION['role'] ?? '')) === 'admin';
$users = fam_rows('SELECT id, username, role, removed_at FROM users ORDER BY username');
$users = array_values(array_filter($users, static function ($u) {
    return trim((string) ($u['removed_at'] ?? '')) === '';
}));
$access = [];
foreach (fam_rows('SELECT * FROM family_access') as $a) {
    $access[(int)$a['user_id']] = $a;
}
$me = fam_role();
?>
<section class="fam-panel" style="margin-bottom:1rem">
  <p>আপনার রোল: <strong><?php echo esc_html($me['role']); ?></strong>
    · স্বাস্থ্য <?php echo $me['can_health'] ? 'খোলা' : 'বন্ধ'; ?>
    · খরচ <?php echo $me['can_money'] ? 'খোলা' : 'বন্ধ'; ?></p>
  <p class="fam-muted">Admin সব দেখে। Family Member লিখতে পারে। Limited Member সাধারণ কাজ পারে, স্বাস্থ্য/খরচ টিক দিলে। View Only শুধু দেখে।</p>
</section>
<?php if (!$isShopAdmin): ?>
  <?php echo fam_empty('অ্যাক্সেস বদলাতে দোকানের অ্যাডমিন লাগবে।'); ?>
<?php else: ?>
<section class="fam-panel">
  <h2>ইউজার</h2>
  <?php foreach ($users as $u):
      $a = $access[(int)$u['id']] ?? ['role_key' => 'member', 'can_health' => 1, 'can_money' => 1];
  ?>
    <form class="fam-form" method="post" action="process.php" style="border-bottom:1px solid var(--fam-line);padding-bottom:0.7rem;margin-bottom:0.7rem">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="do" value="access_save">
      <input type="hidden" name="back" value="/modules/family/access.php">
      <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
      <strong><?php echo esc_html($u['username']); ?></strong>
      <span class="fam-muted">দোকান রোল: <?php echo esc_html($u['role']); ?></span>
      <label>পারিবারিক রোল
        <select name="role_key">
          <?php echo fam_opts(['admin' => 'Admin', 'member' => 'Family Member', 'limited' => 'Limited Member', 'view' => 'View Only'], $a['role_key']); ?>
        </select>
      </label>
      <label><input type="checkbox" name="can_health" <?php echo !empty($a['can_health']) ? 'checked' : ''; ?>> স্বাস্থ্য দেখা/লেখা</label>
      <label><input type="checkbox" name="can_money" <?php echo !empty($a['can_money']) ? 'checked' : ''; ?>> খরচ দেখা/লেখা</label>
      <button class="fam-btn" type="submit">সেভ</button>
    </form>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
