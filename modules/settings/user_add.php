<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
admin_only();
ensure_user_schema($db);
include '../../partials/header.php';

if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}

$roles = $db->query('SELECT * FROM user_roles ORDER BY id ASC');
$selected_role = (string) ($_GET['new_role'] ?? '');
$next_id = ame_next_user_id($db);
$flash = (string) ($_SESSION['user_flash'] ?? '');
$flash_ok = !empty($_SESSION['user_flash_ok']);
unset($_SESSION['user_flash'], $_SESSION['user_flash_ok']);
$ame_user_stores = function_exists('ame_list_stores') ? ame_list_stores($db, true) : [];
$preselect_store = (int) ($_GET['store_id'] ?? 0);
?>
<link rel="stylesheet" href="/assets/css/roster.808.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.1', ENT_QUOTES, 'UTF-8'); ?>">
<script src="/assets/js/device-display.js?v=8.08"></script>

<div class="ar-wrap ua-desk">
  <header class="ar-mast">
    <p class="ar-kicker"><?php echo ame_pair('নতুন অ্যাকসেস', 'New access'); ?></p>
    <h1><?php echo ame_pair('নতুন ইউজার যোগ করুন', 'Add user'); ?></h1>
    <p><?php echo ame_pair('আইডি নিজে বসান, অথবা খালি রাখলে সিস্টেম পরের খালি নম্বর নেবে।', 'Set the ID yourself, or leave it blank and the next free number is used.'); ?></p>
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

  <form action="process_add_user.php" method="POST" enctype="multipart/form-data" class="ua-grid">
    <?php echo csrf_field(); ?>

    <section class="ua-card">
      <h2><?php echo ame_pair('লগইন', 'Login'); ?></h2>
      <label class="ua-field">
        <span><?php echo ame_pair('ইউজার আইডি', 'User ID'); ?></span>
        <input type="number" name="user_id" min="1" max="2000000000" step="1" inputmode="numeric" placeholder="<?php echo (int) $next_id; ?>">
        <small><?php echo ame_pair('ম্যানুয়ালি বসান। খালি রাখলে #' . (int) $next_id . ' বসবে। অন্যজনের আইডি নেওয়া যাবে না।', 'Set it manually. Blank uses #' . (int) $next_id . '. A taken ID is refused.'); ?></small>
      </label>
      <label class="ua-field">
        <span><?php echo ame_pair('ইউজারনেম', 'Username'); ?></span>
        <input type="text" name="username" placeholder="karim123" required autocomplete="off">
      </label>
      <label class="ua-field">
        <span><?php echo ame_pair('পাসওয়ার্ড', 'Password'); ?></span>
        <input type="password" name="password" placeholder="৮+ অক্ষর, অক্ষর ও সংখ্যা · 8+ letters and numbers" required minlength="8">
      </label>
      <label class="ua-field">
        <span><?php echo ame_pair('পাসওয়ার্ড আবার', 'Confirm password'); ?></span>
        <input type="password" name="password_confirm" required minlength="8">
      </label>
      <label class="ua-field">
        <span><?php echo ame_pair('রোল', 'Role'); ?></span>
        <div class="ua-role-row">
          <select name="role" id="roleSelect" required>
            <?php if ($roles): while ($r = $roles->fetch_assoc()): ?>
              <option value="<?php echo htmlspecialchars((string) $r['role_key'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($r['role_key'] === $selected_role) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $r['role_label'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endwhile; endif; ?>
          </select>
          <button type="button" class="ar-btn" onclick="toggleRoleForm()"><?php echo ame_pair('রোল +', 'Role +'); ?></button>
        </div>
      </label>
      <div id="roleInlineForm" class="ua-role-inline" hidden>
        <input type="text" id="newRoleLabel" placeholder="সুপারভাইজার · Supervisor">
        <button type="button" class="ar-btn ghost" onclick="submitNewRole()"><?php echo ame_pair('যোগ করুন', 'Add'); ?></button>
      </div>
      <fieldset class="ua-field">
        <legend><?php echo ame_pair('স্ট্যাটাস', 'Status'); ?></legend>
        <label class="ua-radio"><input type="radio" name="status" value="1" checked> <?php echo ame_pair('এক্টিভ', 'Active'); ?></label>
        <label class="ua-radio"><input type="radio" name="status" value="0"> <?php echo ame_pair('ইনঅ্যাকটিভ', 'Inactive'); ?></label>
      </fieldset>
    </section>

    <section class="ua-card">
      <h2><?php echo ame_pair('পরিচয়', 'Profile'); ?></h2>
      <?php if (count($ame_user_stores) > 0): ?>
      <fieldset class="ua-field">
        <legend><?php echo ame_pair('স্টোর', 'Stores'); ?></legend>
        <small><?php echo ame_pair('খালি রাখলে সব স্টোর দেখবে।', 'Leave empty to see every store.'); ?></small>
        <?php foreach ($ame_user_stores as $store): ?>
          <label class="ua-radio">
            <input type="checkbox" name="store_ids[]" value="<?php echo (int) $store['id']; ?>" <?php echo $preselect_store === (int) $store['id'] ? 'checked' : ''; ?>>
            <?php echo htmlspecialchars((string) $store['name'], ENT_QUOTES, 'UTF-8'); ?>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <?php endif; ?>
      <label class="ua-field">
        <span><?php echo ame_pair('ছবি', 'Photo'); ?></span>
        <input type="file" name="photo" accept="image/*">
      </label>
      <label class="ua-field">
        <span><?php echo ame_pair('ইমেইল', 'Email'); ?></span>
        <input type="email" name="email" placeholder="name@email.com">
      </label>
      <label class="ua-field">
        <span><?php echo ame_pair('মোবাইল', 'Mobile'); ?></span>
        <input type="text" name="phone" placeholder="01XXXXXXXXX" required>
      </label>
      <label class="ua-field">
        <span><?php echo ame_pair('ঠিকানা', 'Address'); ?></span>
        <textarea name="address" rows="2"></textarea>
      </label>
      <label class="ua-field">
        <span><?php echo ame_pair('ডকুমেন্ট', 'Document'); ?></span>
        <input type="file" name="document">
      </label>
    </section>

    <div class="ua-actions">
      <button type="submit" class="ar-btn"><?php echo ame_pair('ইউজার তৈরি করুন', 'Create user'); ?></button>
      <a class="ar-btn ghost" href="users.php"><?php echo ame_pair('তালিকায় ফিরুন', 'Back to list'); ?></a>
    </div>
  </form>
</div>
<script>
function toggleRoleForm() {
    var box = document.getElementById('roleInlineForm');
    if (!box) return;
    box.hidden = !box.hidden;
}
function submitNewRole() {
    var label = (document.getElementById('newRoleLabel') || {}).value || '';
    label = label.trim();
    if (!label) { alert('রোলের নাম লিখুন। / Type a role name.'); return; }
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'add_role.php';
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'role_label';
    input.value = label;
    form.appendChild(input);
    var csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = 'csrf_token';
    csrf.value = <?php echo json_encode(csrf_token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    form.appendChild(csrf);
    document.body.appendChild(form);
    form.submit();
}
</script>
<?php include '../../partials/footer.php'; ?>
