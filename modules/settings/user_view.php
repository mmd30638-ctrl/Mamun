<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
if (is_file('../../includes/super_admin.php')) {
    require_once '../../includes/super_admin.php';
}
admin_only();
ensure_user_schema($db);
if (isset($db) && $db instanceof mysqli && function_exists('ame_super_admin_boot')) {
    ame_super_admin_boot($db);
}

$id = (int)($_GET['id'] ?? 0);
$u = null;
$stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if ($u && function_exists('ame_attach_role_label')) {
    $u = ame_attach_role_label($db, $u);
}
if (!$u || (function_exists('ame_user_is_purged') && ame_user_is_purged($u))) {
    die('❌ ইউজার খুঁজে পাওয়া যায়নি। <a href="users.php">ফিরে যান</a>');
}

include '../../partials/header.php';
if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}

$is_admin = strtolower((string)$u['role']) === 'admin';
$is_removed = ame_user_is_removed($u);
$is_active = !$is_removed && ame_user_row_active($u);
// বারবার ভুল পাসওয়ার্ড দিলে লগইন সাময়িকভাবে বন্ধ হয়ে যায় (১৫ মিনিট) —
// আগে এটা কোথাও দেখানো হতো না, তাই সঠিক পাসওয়ার্ড দিলেও কেন লগইন হচ্ছে
// না তা অ্যাডমিনও বুঝতে পারতেন না। এখন এখানে দেখানো হচ্ছে + এখনই আনলক
// করার বাটন দেওয়া হলো।
$locked_until_raw = trim((string)($u['locked_until'] ?? ''));
$is_locked = $locked_until_raw !== '' && strtotime($locked_until_raw) > time();
$locked_until_display = $is_locked ? date('d M Y, h:i A', strtotime($locked_until_raw)) : '';
$shown_name = ame_user_display_name($u);
$me = (int)($_SESSION['user_id'] ?? 0);
$is_self = $me === (int)$u['id'];
$is_last_admin = $is_admin && $is_active && ame_count_active_admins($db, (int)$u['id']) < 1;
$is_sa_row = function_exists('ame_super_admin_user_ids') && in_array((int)$u['id'], ame_super_admin_user_ids($db), true);
$can_remove = !$is_removed && !$is_self && !$is_last_admin && !$is_sa_row;
$flash = (string)($_SESSION['user_flash'] ?? '');
$flash_ok = !empty($_SESSION['user_flash_ok']);
unset($_SESSION['user_flash'], $_SESSION['user_flash_ok']);
$joined = !empty($u['created_at']) ? date('d M Y, h:i A', strtotime((string)$u['created_at'])) : '-';
$stores = function_exists('ame_user_store_names') ? ame_user_store_names($db, (int)$u['id']) : [];
$store_txt = count($stores) ? implode(', ', $stores) : ($is_admin ? 'সব স্টোর' : '—');
$photo = (string)($u['photo'] ?? '');
$ini = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($shown_name, 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($shown_name, 0, 1));
?>
<link rel="stylesheet" href="/assets/css/roster.808.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.1', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
/* এই পেজের বাটনে (পাসওয়ার্ড রিসেট, এক্টিভ/ইনঅ্যাকটিভ, আনলক, সরান/ফেরত)
   ক্লিক করলে সাধারণ রিং-স্পিনারের বদলে দোকানের লোগো ঘুরে দেখাবে। */
.ar-btn.ame-wait-spin i, .ar-btn .ame-wait-spin i {
    border: none !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important;
    background-repeat: no-repeat !important;
    background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<script src="/assets/js/device-display.js?v=8.08"></script>
<div class="ar-wrap">
  <header class="ar-mast">
    <p class="ar-kicker"><?php echo ame_pair('অ্যাকসেস ব্যাজ', 'Access badge'); ?></p>
    <h1><?php echo ame_pair('ইউজার ডিটেইল', 'User detail'); ?></h1>
    <p><?php echo ame_pair('সিস্টেম অ্যাকসেসের খোলা পরিচয়পত্র।', 'Open identity card for this login.'); ?></p>
  </header>
  <div class="ar-toolbar">
    <a class="ar-btn ghost" href="users.php"><?php echo ame_pair('তালিকায় ফিরুন', 'Back to list'); ?></a>
    <a class="ar-btn" href="user_profile.php?id=<?php echo (int)$u['id']; ?>"><?php echo ame_pair('প্রোফাইল এডিট', 'Edit profile'); ?></a>
    <?php if (!$is_removed): ?>
    <a class="ar-btn" href="reset_password.php?id=<?php echo (int)$u['id']; ?>"><?php echo ame_pair('পাসওয়ার্ড রিসেট', 'Reset password'); ?></a>
    <?php if (!$is_admin): ?>
      <a class="ar-btn ghost" href="role_permissions.php?user=<?php echo (int)$u['id']; ?>"><?php echo ame_pair('পারমিশন', 'Permissions'); ?></a>
    <?php endif; ?>
    <form method="post" action="process_user_status.php" class="ar-status-form">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
      <input type="hidden" name="status" value="<?php echo $is_active ? '0' : '1'; ?>">
      <input type="hidden" name="from" value="view">
      <button type="submit" class="ar-btn<?php echo $is_active ? ' ghost' : ''; ?>"<?php
        if ($is_active && $is_self) {
            echo ' onclick="alert(\'নিজের একাউন্ট ইনঅ্যাকটিভ করা যাবে না। / You cannot inactivate yourself.\'); return false;"';
        } elseif ($is_active) {
            echo ' onclick="return confirm(\'এই ইউজার ইনঅ্যাকটিভ করবেন? লগইন বন্ধ হয়ে যাবে।\\n\\nSet this user inactive? Login will stop.\')"';
        }
      ?>><?php echo $is_active ? ame_pair('ইনঅ্যাকটিভ করুন', 'Set inactive') : ame_pair('এক্টিভ করুন', 'Set active'); ?></button>
    </form>
    <?php if ($is_locked): ?>
    <form method="post" action="process_user_unlock.php" class="ar-status-form">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
      <input type="hidden" name="from" value="view">
      <button type="submit" class="ar-btn ghost" onclick="return confirm('বারবার ভুল পাসওয়ার্ডের কারণে লগইন সাময়িকভাবে বন্ধ আছে। এখনই খুলে দেবেন?\n\nLogin is temporarily locked from repeated wrong attempts. Unlock right now?');"><?php echo ame_pair('🔓 এখনই আনলক করুন', '🔓 Unlock now'); ?></button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($is_removed): ?>
    <form method="post" action="process_user_remove.php" class="ar-status-form">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
      <input type="hidden" name="action" value="restore">
      <input type="hidden" name="from" value="view">
      <button type="submit" class="ar-btn" onclick="return confirm('এই ব্যবহারকারী ফেরত আসবে এবং আবার লগইন করতে পারবে।\n\nThis user will be restored and can sign in again.');"><?php echo ame_pair('ফেরত আনুন', 'Restore'); ?></button>
    </form>
    <form method="post" action="process_user_delete.php" class="ar-status-form" data-ame-hard-delete="1" data-confirm-type="danger" data-confirm="এই ইউজার চিরতরে ডিলিট হবে। লগইন আর থাকবে না। বিক্রয়, লেজার ও ইনভয়েস থাকবে — কোনো ব্যবসার তথ্য নষ্ট হবে না।">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
      <input type="hidden" name="action" value="purge">
      <input type="hidden" name="from" value="view">
      <button type="submit" class="ar-btn ar-btn-remove ame-hard-delete-btn"><?php echo ame_pair('চিরতরে ডিলিট', 'Delete forever'); ?></button>
    </form>
    <?php elseif ($can_remove): ?>
    <form method="post" action="process_user_remove.php" class="ar-status-form">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
      <input type="hidden" name="action" value="remove">
      <input type="hidden" name="from" value="view">
      <button type="submit" class="ar-btn ghost ar-btn-remove" onclick="return confirm('এই ব্যবহারকারী তালিকা থেকে সরে যাবে। লগইন বন্ধ থাকবে। বিক্রয়, লেজার ও ইতিহাস থাকবে — রেকর্ড মুছে যাবে না।\n\nThis user will leave the list. Login stops. Sales, ledger and history stay — the record is not deleted.');"><?php echo ame_pair('সরান', 'Remove'); ?></button>
    </form>
    <?php endif; ?>
  </div>
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
  <section class="ar-id">
    <?php if ($photo !== ''): ?>
      <img class="ar-ava" src="../../assets/img/<?php echo htmlspecialchars($photo, ENT_QUOTES, 'UTF-8'); ?>" alt="">
    <?php else: ?>
      <div class="ar-ava-ph"><?php echo htmlspecialchars($ini, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <h1><span class="ar-id-num">#<?php echo (int)$u['id']; ?></span> <?php echo htmlspecialchars($shown_name, ENT_QUOTES, 'UTF-8'); ?></h1>
    <span class="ar-role <?php echo $is_admin ? 'admin' : (strtolower((string)$u['role']) === 'manager' ? 'manager' : 'employee'); ?>">
      <?php echo htmlspecialchars((string)($u['role_label'] ?? $u['role']), ENT_QUOTES, 'UTF-8'); ?>
    </span>
    <?php
    $uv_sa = false;
    if (isset($db) && $db instanceof mysqli && function_exists('ame_super_admin_user_ids')) {
        $uv_sa = in_array((int)$u['id'], ame_super_admin_user_ids($db), true);
    }
    if ($uv_sa) {
        echo '<span class="ar-status on">সুপার অ্যাডমিন</span>';
    }
    ?>
    <?php if ($is_removed): ?>
      <span class="ar-status gone"><?php echo ame_pair('সরানো', 'Removed'); ?></span>
    <?php else: ?>
      <span class="ar-status <?php echo $is_active ? 'on' : 'off'; ?>"><?php echo $is_active ? ame_pair('এক্টিভ', 'Active') : ame_pair('ইনঅ্যাকটিভ', 'Inactive'); ?></span>
    <?php endif; ?>
    <form method="post" action="process_user_id.php" class="ar-id-form ar-id-form-block">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
      <input type="hidden" name="from" value="view">
      <label><?php echo ame_pair('ইউজার আইডি ম্যানুয়ালি বসান', 'Set user ID manually'); ?></label>
      <div class="ar-id-row">
        <input type="number" name="new_id" min="1" max="2000000000" step="1" required value="<?php echo (int)$u['id']; ?>" inputmode="numeric">
        <button type="submit" class="ar-btn" onclick="return confirm('এই ব্যবহারকারীর আইডি বদলাবেন? বিক্রয় ও লেজার নতুন আইডিতে যাবে, রেকর্ড মুছে যাবে না।\n\nChange this user ID? Sales and ledger will follow the new number. Nothing is deleted.');"><?php echo ame_pair('আইডি বসান', 'Set ID'); ?></button>
      </div>
      <p class="ar-id-hint"><?php echo ame_pair('খালি নম্বর দিন। অন্যজনের আইডি নেওয়া যাবে না — ইতিহাস অক্ষত থাকবে।', 'Pick a free number. A taken ID is refused so history stays intact.'); ?></p>
    </form>
    <div class="ar-rows">
      <div class="ar-row"><span><?php echo ame_pair('ইউজার আইডি', 'User ID'); ?></span><span>#<?php echo (int)$u['id']; ?></span></div>
      <div class="ar-row"><span><?php echo ame_pair('ইউজারনেম', 'Username'); ?></span><span><code><?php echo htmlspecialchars((string)($u['username'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></code></span></div>
      <div class="ar-row"><span><?php echo ame_pair('পাসওয়ার্ড', 'Password'); ?></span><span><?php
        echo '••••••••';
        if (!$is_removed) {
            echo ' <a href="reset_password.php?id=' . (int)$u['id'] . '">' . ame_pair('বদলান', 'Change') . '</a>';
        }
      ?></span></div>
      <div class="ar-row"><span><?php echo ame_pair('ফোন', 'Phone'); ?></span><span><?php echo htmlspecialchars((string)($u['phone'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span></div>
      <div class="ar-row"><span><?php echo ame_pair('ইমেইল', 'Email'); ?></span><span><?php echo htmlspecialchars((string)($u['email'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span></div>
      <div class="ar-row"><span><?php echo ame_pair('WhatsApp', 'WhatsApp'); ?></span><span><?php echo htmlspecialchars((string)($u['whatsapp'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span></div>
      <div class="ar-row"><span><?php echo ame_pair('স্টোর', 'Stores'); ?></span><span><?php echo htmlspecialchars($store_txt, ENT_QUOTES, 'UTF-8'); ?></span></div>
      <div class="ar-row"><span><?php echo ame_pair('যোগদান', 'Joined'); ?></span><span><?php echo htmlspecialchars($joined, ENT_QUOTES, 'UTF-8'); ?></span></div>
      <div class="ar-row"><span><?php echo ame_pair('স্ট্যাটাস', 'Status'); ?></span><span><?php
        if ($is_removed) {
            echo ame_pair('সরানো — লগইন বন্ধ, ইতিহাস অক্ষত', 'Removed — login off, history kept');
        } elseif ($is_active) {
            echo ame_pair('এক্টিভ', 'Active');
        } else {
            echo ame_pair('ইনঅ্যাকটিভ', 'Inactive');
        }
      ?></span></div>
      <?php if ($is_removed && !empty($u['removed_at'])): ?>
      <div class="ar-row"><span><?php echo ame_pair('সরানোর তারিখ', 'Removed on'); ?></span><span><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime((string)$u['removed_at'])), ENT_QUOTES, 'UTF-8'); ?></span></div>
      <?php endif; ?>
      <?php if ($is_locked): ?>
      <div class="ar-row"><span><?php echo ame_pair('লগইন লক', 'Login lock'); ?></span><span style="color: var(--brand-danger, #D32F2F); font-weight:700;"><?php echo ame_pair('বারবার ভুল পাসওয়ার্ড — পর্যন্ত বন্ধ', 'Repeated wrong password — locked until'); ?> <?php echo htmlspecialchars($locked_until_display, ENT_QUOTES, 'UTF-8'); ?></span></div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php include '../../partials/footer.php'; ?>
