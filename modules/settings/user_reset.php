<?php
/**
 * ইউজার রিসেট ও অ্যাডমিন প্রোটেকশন
 * ------------------------------------------------------------
 * এক ক্লিকে (কড়া নিরাপত্তা যাচাইয়ের পর) অ্যাডমিন ছাড়া বাকি সব ইউজার
 * একাউন্ট "সরিয়ে" (soft-remove) দেওয়ার জন্য। গুরুত্বপূর্ণ সিদ্ধান্ত:
 *
 * এটা ডাটাবেজ থেকে ইউজারের সারি (row) সরাসরি DELETE করে না — বরং এই
 * অ্যাপে ইউজার সরানোর জন্য যে পদ্ধতি আগে থেকেই আছে (users.php-এর "সরান"
 * বাটন, includes/user_functions.php-এর ame_remove_user()) সেটাই বাল্ক
 * (একসাথে অনেকজনের জন্য) ব্যবহার করা হয়েছে: ইউজারনেম বদলে রাখা, লগইন বন্ধ
 * (status=0), removed_at/removed_by বসানো, এবং সেশন/লগইন-টোকেন অকার্যকর
 * করা (auth_version বাড়িয়ে — এটাই এই অ্যাপের সেশন-ইনভ্যালিডেশন পদ্ধতি)।
 * সরাসরি হার্ড DELETE করলে বিক্রয়/ক্রয়/লেজারের সাথে যুক্ত user_id/
 * created_by রেফারেন্স ভেঙে যেত (ফরেইন-কি সমস্যা বা এতিম রেকর্ড) — যা এই
 * মডিউলের নিজস্ব "গোল্ডেন রুল"-ই (ব্যবসায়িক তথ্য কখনো হারাবে না) ভঙ্গ করতো।
 * তাই এই পদ্ধতিই সবচেয়ে নিরাপদ এবং সম্পূর্ণভাবে ফেরানো যায় (users.php-এর
 * "সরানো আর্কাইভ" থেকে "ফেরত" বাটনে)।
 *
 * প্রোটেক্ট করা হয় role='admin' থাকা সব ইউজারকে (শুধু যে অ্যাডমিন এখন এই
 * কাজ করছেন তাকেই না) — কারণ স্পেসিফিকেশনে "Admin Account" বলা হয়েছে,
 * নির্দিষ্ট একজনকে না। এই অ্যাপে আলাদা "super admin/master admin" রোল
 * নেই, তাই role='admin' -ই সর্বোচ্চ স্তর এবং সেটাই এখানে প্রোটেক্টেড ধরা
 * হচ্ছে।
 */
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
admin_only();
ensure_user_schema($db);

$present = ame_sql_user_present();
$rows_res = $db->query("SELECT id, username, role, status, phone, employee_id FROM users WHERE {$present} ORDER BY role ASC, id ASC");
$to_remove = [];
$protected = [];
if ($rows_res) {
    while ($r = $rows_res->fetch_assoc()) {
        if (strtolower((string)($r['role'] ?? '')) === 'admin') {
            $protected[] = $r;
        } else {
            $to_remove[] = $r;
        }
    }
}
$remove_count = count($to_remove);
$protected_count = count($protected);

$flash = (string)($_SESSION['user_flash'] ?? '');
$flash_ok = !empty($_SESSION['user_flash_ok']);
unset($_SESSION['user_flash'], $_SESSION['user_flash_ok']);

include '../../partials/header.php';
if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}
?>
<link rel="stylesheet" href="/assets/css/roster.808.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.1', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.ar-reset-wrap { max-width: 760px; margin: 0 auto; }
.ar-reset-card {
    background: var(--brand-white, #fff);
    border: 1px solid var(--pos-c-eeeeee, #eee);
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 16px;
}
.ar-reset-warn {
    background: var(--brand-danger-soft, #FDECEA);
    border: 1px solid var(--brand-danger, #D32F2F);
    color: var(--brand-danger, #D32F2F);
    border-radius: 10px;
    padding: 14px 16px;
    font-weight: 600;
    margin-bottom: 16px;
}
.ar-reset-stats { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 8px; }
.ar-reset-stats > div {
    flex: 1 1 140px;
    background: var(--pos-c-f5f5f5, #f5f5f5);
    border-radius: 10px;
    padding: 12px 14px;
    text-align: center;
}
.ar-reset-stats b { display: block; font-size: 22px; color: var(--dark-blue, #0B1E4D); }
.ar-reset-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
.ar-reset-table th, .ar-reset-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid var(--pos-c-eeeeee, #eee); }
.ar-reset-table th { background: var(--pos-c-f5f5f5, #f5f5f5); }
.ar-reset-table.protected th { background: var(--brand-success-soft, #E7F7EF); }
.ar-reset-form label { display: block; font-weight: 600; margin: 12px 0 6px; }
.ar-reset-form input[type=password], .ar-reset-form input[type=text] {
    width: 100%; box-sizing: border-box; padding: 12px; border-radius: 8px;
    border: 1.5px solid var(--pos-c-cfe3f7, #cfe3f7); font-size: 15px;
}
.ar-reset-submit {
    margin-top: 16px; width: 100%; padding: 14px; border: none; border-radius: 10px;
    background: var(--brand-danger, #D32F2F); color: var(--brand-white, #fff);
    font-weight: 800; font-size: 15px; cursor: pointer;
}
.ar-reset-submit:disabled { opacity: .5; cursor: not-allowed; }
.ar-reset-note { font-size: 12.5px; color: var(--brand-muted, #666); margin-top: 8px; }
.ar-reset-submit.ame-wait-spin i, .ar-reset-submit .ame-wait-spin i {
    border: none !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important;
    background-repeat: no-repeat !important;
    background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="ar-wrap ar-reset-wrap">
  <header class="ar-mast">
    <p class="ar-kicker"><?php echo ame_pair('বিপজ্জনক কাজ', 'Danger zone'); ?></p>
    <h1>🛡️ <?php echo ame_pair('ইউজার রিসেট ও অ্যাডমিন প্রোটেকশন', 'User Reset & Admin Protection'); ?></h1>
    <p><?php echo ame_pair('অ্যাডমিন একাউন্ট সবসময় সুরক্ষিত থাকবে।', 'Admin account will always remain protected.'); ?></p>
  </header>

  <?php if ($flash !== ''): ?>
    <div class="ar-flash <?php echo $flash_ok ? 'ok' : 'err'; ?>"><?php
      if (str_contains($flash, ' / ')) {
          [$bn, $en] = explode(' / ', $flash, 2);
          echo ame_pair($bn, $en);
      } else {
          echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8');
      }
    ?></div>
  <?php endif; ?>

  <div class="ar-reset-card">
    <div class="ar-reset-stats">
      <div><b><?php echo (int)$remove_count; ?></b><?php echo ame_pair('সরানো হবে (Non-Admin)', 'To be removed (non-admin)'); ?></div>
      <div><b><?php echo (int)$protected_count; ?></b><?php echo ame_pair('সুরক্ষিত (Admin)', 'Protected (admin)'); ?></div>
    </div>
  </div>

  <div class="ar-reset-warn">
    ⚠️ <?php echo ame_pair(
      'অ্যাডমিন ছাড়া মোট ' . (int)$remove_count . 'টি ইউজার একাউন্ট "সরিয়ে" দেওয়া হবে (লগইন বন্ধ হয়ে যাবে)। বিক্রয়, ক্রয়, স্টক, লেজার, পেমেন্ট — কোনো ব্যবসায়িক তথ্য মুছবে না বা হারাবে না। এই কাজ users.php-এর "সরানো আর্কাইভ" থেকে ফেরানো যায়।',
      'A total of ' . (int)$remove_count . ' non-admin accounts will be "removed" (login stops). No sales, purchase, stock, ledger or payment data is deleted or lost. This can be undone from the "Removed archive" in users.php.'
    ); ?>
  </div>

  <div class="ar-reset-card">
    <h3 style="margin-top:0;"><?php echo ame_pair('সুরক্ষিত অ্যাডমিন', 'Protected admin'); ?></h3>
    <table class="ar-reset-table protected">
      <thead><tr><th>ID</th><th><?php echo ame_pair('ইউজারনেম', 'Username'); ?></th><th><?php echo ame_pair('রোল', 'Role'); ?></th></tr></thead>
      <tbody>
        <?php if (!$protected): ?>
        <tr><td colspan="3"><?php echo ame_pair('কোনো অ্যাডমিন পাওয়া যায়নি — সতর্কতা!', 'No admin found — warning!'); ?></td></tr>
        <?php else: foreach ($protected as $p): ?>
        <tr><td>#<?php echo (int)$p['id']; ?></td><td><?php echo htmlspecialchars((string)$p['username'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)$p['role'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="ar-reset-card">
    <h3 style="margin-top:0;"><?php echo ame_pair('সরানো হবে যারা', 'Users to be removed'); ?></h3>
    <?php if (!$to_remove): ?>
      <p><?php echo ame_pair('অ্যাডমিন ছাড়া আর কোনো একটিভ ইউজার নেই — করার কিছু নেই।', 'No other active users besides admin — nothing to do.'); ?></p>
    <?php else: ?>
    <table class="ar-reset-table">
      <thead><tr><th>#</th><th>ID</th><th><?php echo ame_pair('ইউজারনেম', 'Username'); ?></th><th><?php echo ame_pair('রোল', 'Role'); ?></th><th><?php echo ame_pair('স্ট্যাটাস', 'Status'); ?></th></tr></thead>
      <tbody>
        <?php $i = 0; foreach ($to_remove as $r): $i++; ?>
        <tr>
          <td><?php echo $i; ?></td>
          <td>#<?php echo (int)$r['id']; ?></td>
          <td><?php echo htmlspecialchars((string)$r['username'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string)$r['role'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo ame_user_row_active($r) ? ame_pair('এক্টিভ', 'Active') : ame_pair('ইনঅ্যাকটিভ', 'Inactive'); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if ($to_remove): ?>
  <div class="ar-reset-card">
    <h3 style="margin-top:0;"><?php echo ame_pair('নিশ্চিত করুন', 'Confirm'); ?></h3>
    <form method="post" action="process_user_reset.php" class="ar-reset-form" id="userResetForm">
      <?php echo csrf_field(); ?>
      <label><?php echo ame_pair('আপনার এডমিন পাসওয়ার্ড দিন', 'Enter your admin password'); ?></label>
      <input type="password" name="admin_password" autocomplete="current-password" required>

      <label><?php echo ame_pair('নিশ্চিত করতে হুবহু টাইপ করুন:', 'To confirm, type exactly:'); ?> <code>DELETE USERS</code></label>
      <input type="text" name="confirm_text" autocomplete="off" placeholder="DELETE USERS" required>

      <button type="submit" class="ar-reset-submit" onclick="return confirm('এই কাজ <?php echo (int)$remove_count; ?> জন ইউজারের লগইন বন্ধ করে দেবে। ব্যবসায়িক তথ্য নষ্ট হবে না, কিন্তু এখনই নিশ্চিত হয়ে এগোন।\n\nThis will stop login for <?php echo (int)$remove_count; ?> users. No business data is lost, but confirm before continuing.');">
        🛡️ <?php echo ame_pair('Non-Admin ইউজার রিসেট করুন', 'Reset Non-Admin Users'); ?>
      </button>
      <p class="ar-reset-note"><?php echo ame_pair('অ্যাডমিন একাউন্ট সবসময় সুরক্ষিত থাকবে।', 'Admin account will always remain protected.'); ?></p>
    </form>
  </div>
  <?php endif; ?>

  <a class="ar-quiet" href="users.php"><?php echo ame_pair('তালিকায় ফিরুন', 'Back to list'); ?></a>
</div>
<?php include '../../partials/footer.php'; ?>
