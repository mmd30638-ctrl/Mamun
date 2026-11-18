<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
require_once '../../includes/user_profile.php';
if (is_file('../../includes/super_admin.php')) {
    require_once '../../includes/super_admin.php';
}
admin_only();
ensure_user_schema($db);
if (function_exists('ame_super_admin_boot')) {
    ame_super_admin_boot($db);
}

$userId = (int) ($_GET['id'] ?? 0);
$empId = (int) ($_GET['emp'] ?? 0);
$pack = ame_profile_pack($db, $userId, $empId);
if (!$pack['user'] && !$pack['emp']) {
    include '../../partials/header.php';
    echo '<link rel="stylesheet" href="/assets/css/user-profile.css?v=' . htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8') . '">';
    echo '<div class="up"><div class="up-card up-empty">এই প্রোফাইল পাওয়া যায়নি। <a href="/modules/settings/users.php">ইউজার তালিকায় ফিরুন</a></div></div>';
    include '../../partials/footer.php';
    exit;
}

$userId = (int) $pack['user_id'];
$empId = (int) $pack['emp_id'];
$iAmSa = function_exists('ame_is_super_admin') && ame_is_super_admin();
$canEdit = ame_profile_can_edit_target($db, $pack);
$me = function_exists('ame_current_user_id') ? ame_current_user_id() : (int) ($_SESSION['user_id'] ?? 0);
$isSelf = $me > 0 && $userId === $me;
$flash = (string) ($_SESSION['user_flash'] ?? '');
$flashOk = !empty($_SESSION['user_flash_ok']);
unset($_SESSION['user_flash'], $_SESSION['user_flash_ok']);

$ini = $pack['display_name'] !== ''
    ? (function_exists('mb_substr') ? mb_strtoupper(mb_substr($pack['display_name'], 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($pack['display_name'], 0, 1)))
    : 'U';

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/user-profile.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<div class="up">
  <header class="up-hero">
    <img class="up-photo" src="<?php echo htmlspecialchars($pack['photo_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="" onerror="this.src='/assets/img/user-placeholder.png'">
    <div>
      <p class="up-kicker">প্রোফাইল</p>
      <h1><?php echo htmlspecialchars($pack['display_name'] !== '' ? $pack['display_name'] : 'নামহীন', ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="up-meta"><?php
        $bits = [];
        if ($pack['username'] !== '') {
            $bits[] = $pack['username'];
        }
        if ($userId > 0) {
            $bits[] = '#' . $userId;
        }
        if ($pack['employee_code'] !== '') {
            $bits[] = $pack['employee_code'];
        }
        if ($pack['phone'] !== '') {
            $bits[] = $pack['phone'];
        }
        echo htmlspecialchars($bits !== [] ? implode(' · ', $bits) : 'লগইন বা মোবাইল নেই', ENT_QUOTES, 'UTF-8');
      ?></p>
      <div class="up-badges">
        <?php if ($pack['is_sa']): ?><span class="up-badge sa">সুপার অ্যাডমিন</span><?php endif; ?>
        <?php if ($pack['role'] !== ''): ?><span class="up-badge"><?php echo htmlspecialchars($pack['roles'][$pack['role']] ?? $pack['role'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
        <?php if ($pack['is_removed']): ?>
          <span class="up-badge off">সরানো</span>
        <?php elseif ($pack['is_active']): ?>
          <span class="up-badge on">এক্টিভ</span>
        <?php else: ?>
          <span class="up-badge off">ইনঅ্যাকটিভ</span>
        <?php endif; ?>
        <?php if ($empId > 0): ?><span class="up-badge">কর্মী</span><?php endif; ?>
      </div>
    </div>
  </header>

  <nav class="up-nav">
    <a href="/modules/settings/users.php">ইউজার তালিকা</a>
    <?php if ($empId > 0): ?>
    <a href="/modules/hrm/employee_list.php?emp=<?php echo $empId; ?>">কর্মী ডেস্ক</a>
    <?php endif; ?>
    <a href="/modules/settings/super_admin.php">সুপার অ্যাডমিন তালিকা</a>
    <?php if ($userId > 0): ?>
    <a href="/modules/settings/user_view.php?id=<?php echo $userId; ?>">ডিটেইল কার্ড</a>
    <?php endif; ?>
  </nav>

  <?php if ($flash !== ''): ?>
    <div class="up-flash <?php echo $flashOk ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <?php if ($pack['is_sa']): ?>
  <section class="up-card up-sa">
    <h2>সুপার অ্যাডমিন — ভুল হয়ে থাকলে এখান থেকে সরান</h2>
    <p class="up-lead">এই ইউজার এখন পুরো প্রজেক্ট খুলতে পারে। সাধারণ অ্যাডমিন একে মুছতে বা এডিট করতে পারে না। আপনি সুপার অ্যাডমিন হলে নিজের পাসওয়ার্ড দিয়ে দায়িত্ব সরান — তারপর নাম, রোল এডিট বা তালিকা থেকে সরানো যাবে।</p>
    <?php if ($iAmSa && !$isSelf): ?>
    <form method="post" action="process_user_profile.php" style="margin-top:14px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="revoke">
      <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
      <input type="hidden" name="emp_id" value="<?php echo $empId; ?>">
      <div class="up-grid">
        <label>আপনার পাসওয়ার্ড
          <input type="password" name="actor_password" required autocomplete="current-password" placeholder="নিজের লগইন পাসওয়ার্ড">
        </label>
        <div style="display:flex;align-items:end;">
          <button type="submit" class="up-btn danger" onclick="return confirm('এই ইউজারের সুপার অ্যাডমিন দায়িত্ব সরবে। শেষ সুপার অ্যাডমিন সরানো যায় না।');">দায়িত্ব সরান</button>
        </div>
      </div>
    </form>
    <?php elseif ($isSelf): ?>
      <p class="up-hint">নিজের দায়িত্ব সরাতে সুপার অ্যাডমিন তালিকায় যান — শেষ সুপার অ্যাডমিন সরানো যায় না।</p>
    <?php else: ?>
      <p class="up-hint">দায়িত্ব সরাতে যে ইউজার আসল সুপার অ্যাডমিন, সেই লগইন দিয়ে এই পেজ খুলুন।</p>
    <?php endif; ?>
  </section>
  <?php elseif ($iAmSa && $userId > 0 && !$pack['is_removed']): ?>
  <section class="up-card">
    <h2>সুপার অ্যাডমিন দায়িত্ব দিন</h2>
    <p class="up-lead">শুধু যাকে পুরো প্রজেক্টের দায়িত্ব দিতে চান। ভুল হলে এই পেজ থেকেই আবার সরানো যাবে।</p>
    <form method="post" action="process_user_profile.php" style="margin-top:14px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="grant">
      <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
      <input type="hidden" name="emp_id" value="<?php echo $empId; ?>">
      <div class="up-grid">
        <label>আপনার পাসওয়ার্ড
          <input type="password" name="actor_password" required autocomplete="current-password">
        </label>
        <div style="display:flex;align-items:end;">
          <button type="submit" class="up-btn" onclick="return confirm('এই ইউজার সুপার অ্যাডমিন হবে।');">দায়িত্ব দিন</button>
        </div>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <?php if (!$canEdit): ?>
    <div class="up-card up-empty">সুপার অ্যাডমিনের প্রোফাইল সাধারণ অ্যাডমিন খুলতে পারেন, এডিট করতে পারেন না। দায়িত্ব সরানোর পর এডিট খুলবে।</div>
  <?php elseif ($pack['is_removed']): ?>
    <div class="up-card up-empty">এই ইউজার সরানো। এডিটের আগে ফেরত আনুন, অথবা চিরতরে মুছুন। খাতা মুছে যায় না।</div>
  <?php else: ?>
  <form class="up-card" method="post" action="process_user_profile.php" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
    <input type="hidden" name="emp_id" value="<?php echo $empId; ?>">
    <h2>পরিচয় ও যোগাযোগ</h2>
    <div class="up-grid">
      <label>পূর্ণ নাম
        <input type="text" name="full_name" required value="<?php echo htmlspecialchars($pack['display_name'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>মোবাইল
        <input type="text" name="phone" inputmode="tel" <?php echo $empId > 0 ? 'required' : ''; ?> value="<?php echo htmlspecialchars($pack['phone'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>ইমেইল
        <input type="email" name="email" value="<?php echo htmlspecialchars($pack['email'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>WhatsApp
        <input type="text" name="whatsapp" inputmode="tel" value="<?php echo htmlspecialchars($pack['whatsapp'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label class="wide">ঠিকানা
        <textarea name="address"><?php echo htmlspecialchars($pack['address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
      </label>
      <label>প্রোফাইল ছবি
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
      </label>
      <?php if ($empId > 0): ?>
      <label>পদবি
        <input type="text" name="designation" value="<?php echo htmlspecialchars($pack['designation'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>কর্মী কোড
        <input type="text" value="<?php echo htmlspecialchars($pack['employee_code'] !== '' ? $pack['employee_code'] : '—', ENT_QUOTES, 'UTF-8'); ?>" disabled>
      </label>
      <label>যোগদানের তারিখ
        <input type="date" name="joining_date" value="<?php echo htmlspecialchars($pack['joining_date'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <?php endif; ?>
    </div>
    <h2 style="margin-top:18px;">লগইন ও রোল</h2>
    <div class="up-grid">
      <label>লগইন নাম
        <input type="text" name="username" <?php echo $userId > 0 ? 'required' : ''; ?> value="<?php echo htmlspecialchars($pack['username'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username">
      </label>
      <label>রোল
        <select name="role">
          <?php foreach ($pack['roles'] as $key => $label): ?>
            <option value="<?php echo htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $pack['role'] === $key ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p class="up-hint" style="grid-column:1/-1;"><?php echo $pack['is_sa']
        ? 'সুপার অ্যাডমিন দায়িত্ব আলাদা। কাজের রোল বদলালেও পূর্ণ দায়িত্ব থাকে — দায়িত্ব সরাতে উপরের ফর্ম ব্যবহার করুন অথবা রোল কন্ট্রোল খুলুন।'
        : 'সব ইউজারের রোল একসাথে বদলাতে রোল কন্ট্রোল পেজ ব্যবহার করুন।'; ?>
        <a href="/modules/settings/role_control.php">রোল কন্ট্রোল</a>
      </p>
      <label>নতুন পাসওয়ার্ড
        <input type="password" name="password" autocomplete="new-password" placeholder="খালি রাখলে আগেরটাই থাকবে">
      </label>
      <label>লগইন স্ট্যাটাস
        <select name="status">
          <option value="1"<?php echo $pack['is_active'] ? ' selected' : ''; ?>>এক্টিভ</option>
          <option value="0"<?php echo !$pack['is_active'] ? ' selected' : ''; ?>>ইনঅ্যাকটিভ</option>
        </select>
      </label>
    </div>
    <p class="up-hint"><?php echo function_exists('ame_password_policy_hint') ? htmlspecialchars(ame_password_policy_hint(), ENT_QUOTES, 'UTF-8') : 'পাসওয়ার্ড কমপক্ষে ৮ অক্ষর, একটি অক্ষর ও একটি সংখ্যা।'; ?> বেতন এই পেজে বদলায় না — কর্মী ডেস্ক থেকে।</p>
    <div class="up-nav" style="margin:16px 0 0;">
      <button type="submit" class="up-btn primary">প্রোফাইল সংরক্ষণ</button>
    </div>
  </form>
  <?php endif; ?>

  <?php if ($userId > 0 && !$isSelf): ?>
  <section class="up-card up-danger-zone">
    <h2>সরানো ও মুছা</h2>
    <p class="up-lead">সরালে লগইন বন্ধ, তালিকা থেকে উঠে — বিক্রয় ও লেজার থাকে। সুপার অ্যাডমিন থাকলে আগে দায়িত্ব সরান। চিরতরে মুছতে সুপার অ্যাডমিন ও নিজের পাসওয়ার্ড লাগে।</p>
    <?php if ($pack['is_sa']): ?>
      <p class="up-hint">এই ইউজার এখনো সুপার অ্যাডমিন — উপরের «দায়িত্ব সরান» ছাড়া সরানো বা মুছা যাবে না।</p>
    <?php elseif ($pack['is_removed']): ?>
      <form method="post" action="process_user_profile.php" style="display:inline-block;margin-right:8px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="restore">
        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
        <input type="hidden" name="emp_id" value="<?php echo $empId; ?>">
        <button type="submit" class="up-btn">ফেরত আনুন</button>
      </form>
      <?php if ($iAmSa): ?>
      <form method="post" action="process_user_profile.php" style="display:inline-block;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="purge">
        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
        <input type="hidden" name="emp_id" value="<?php echo $empId; ?>">
        <label>আপনার পাসওয়ার্ড
          <input type="password" name="actor_password" required autocomplete="current-password">
        </label>
        <button type="submit" class="up-btn danger" onclick="return confirm('চিরতরে ডিলিট হবে। লগইন থাকবে না। খাতা থাকবে।');">চিরতরে মুছুন</button>
      </form>
      <?php endif; ?>
    <?php else: ?>
      <form method="post" action="process_user_profile.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
        <input type="hidden" name="emp_id" value="<?php echo $empId; ?>">
        <button type="submit" class="up-btn danger" onclick="return confirm('তালিকা থেকে সরবে, লগইন বন্ধ। খাতা মুছে যাবে না।');">তালিকা থেকে সরান</button>
      </form>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>
<?php include '../../partials/footer.php'; ?>
