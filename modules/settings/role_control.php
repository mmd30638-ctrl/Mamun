<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/user_role_control.php';
admin_only();
if (function_exists('ame_super_admin_boot')) {
    ame_super_admin_boot($db);
}
$can = function_exists('ame_is_super_admin') && ame_is_super_admin();
$en = function_exists('is_en') && is_en();
$users = ame_role_control_users($db);
$catalog = ame_role_control_catalog($db);
$counts = ame_role_control_counts($users);
$history = $can ? ame_role_control_history($db, 10) : [];
$me = function_exists('ame_current_user_id') ? ame_current_user_id() : (int) ($_SESSION['user_id'] ?? 0);
$flashOk = (string) ($_SESSION['rc_ok'] ?? '');
$flashErr = (string) ($_SESSION['rc_err'] ?? '');
unset($_SESSION['rc_ok'], $_SESSION['rc_err']);

include __DIR__ . '/../../partials/header.php';
?>
<style>
.rc{max-width:980px;margin:16px auto 40px;padding:0 12px;font-family:"Noto Sans Bengali","Segoe UI",sans-serif}
.rc h1{margin:0 0 6px;font-size:26px;font-weight:850;color:#047857}
.rc-lead{margin:0 0 14px;font-weight:700;color:#1D4ED8;line-height:1.55}
.rc .rc-card,.rc .rc-flash,.rc .rc-kpi,.rc .rc-user,.rc .rc-btn{background:#fff !important}
.rc-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:0 0 14px}
.rc-kpi{border:1px solid #E6ECF4;border-radius:14px;padding:12px;box-shadow:0 8px 18px rgba(27,42,74,.05)}
.rc-kpi span{display:block;font-size:12px;font-weight:800}
.rc-kpi b{display:block;margin-top:4px;font-size:22px;font-weight:850}
.rc-kpi:nth-child(1) span,.rc-kpi:nth-child(1) b{color:#047857}
.rc-kpi:nth-child(2) span,.rc-kpi:nth-child(2) b{color:#7C3AED}
.rc-kpi:nth-child(3) span,.rc-kpi:nth-child(3) b{color:#1D4ED8}
.rc-kpi:nth-child(4) span,.rc-kpi:nth-child(4) b{color:#B45309}
.rc-flash{border-radius:12px;padding:10px 12px;font-weight:800;margin:0 0 12px;border:1.5px solid #E6ECF4}
.rc-flash.ok{color:#047857;border-color:#86efac}
.rc-flash.err{color:#B91C1C;border-color:#FECACA}
.rc-card{border:1px solid #E6ECF4;border-radius:16px;padding:16px;margin:0 0 14px;box-shadow:0 8px 18px rgba(27,42,74,.05)}
.rc-card h2{margin:0 0 10px;font-size:16px;font-weight:850;color:#7C3AED}
.rc-search{width:100%;min-height:44px;border:1.5px solid #CFE3F7;border-radius:10px;padding:8px 12px;font-size:14px;margin:0 0 12px;background:#fff}
.rc-user{border:1px solid #E6ECF4;border-radius:14px;padding:12px;margin:0 0 10px}
.rc-user header{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 10px}
.rc-user b{color:#047857;font-size:16px}
.rc-user .meta{color:#1D4ED8;font-weight:700;font-size:13px}
.rc-badge{display:inline-flex;align-items:center;min-height:26px;padding:2px 10px;border-radius:999px;border:1px solid #E6ECF4;font-size:12px;font-weight:800;background:#fff}
.rc-badge.admin{color:#047857;border-color:#86efac}
.rc-badge.manager{color:#1D4ED8;border-color:#93C5FD}
.rc-badge.sa{color:#7C3AED;border-color:#DDD6FE}
.rc-badge.off{color:#B91C1C}
.rc-form{display:grid;grid-template-columns:1.1fr .85fr 1fr 1fr auto;gap:8px;align-items:end}
.rc-form label{display:block;font-size:11px;font-weight:800;color:#64748B;margin-bottom:4px}
.rc-form select,.rc-form input[type=text],.rc-form input[type=password]{width:100%;min-height:42px;border:1.5px solid #CFE3F7;border-radius:10px;padding:6px 10px;background:#fff;font-weight:700}
.rc-sa{display:flex;align-items:center;gap:8px;min-height:42px;font-weight:800;color:#7C3AED}
.rc-btn{min-height:42px;border:1.5px solid #047857;border-radius:10px;padding:0 14px;color:#047857;font-weight:800;cursor:pointer}
.rc-btn:disabled{opacity:.45;cursor:not-allowed}
.rc-hint{margin:8px 0 0;font-size:12px;font-weight:700;color:#C2410C}
.rc-hist{list-style:none;margin:0;padding:0}
.rc-hist li{padding:8px 0;border-bottom:1px solid #F1F5F9;font-size:13px;font-weight:700;color:#334155}
.rc-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}
.rc-nav a{min-height:40px;display:inline-flex;align-items:center;padding:0 12px;border-radius:10px;border:1.5px solid #1D4ED8;color:#1D4ED8;text-decoration:none;font-weight:800;background:#fff}
@media (max-width:800px){
  .rc-kpis{grid-template-columns:1fr 1fr}
  .rc-form{grid-template-columns:1fr}
}
</style>
<div class="rc">
  <h1><?php echo $en ? 'Role control — every user, including Super Admin' : 'রোল কন্ট্রোল — সুপার অ্যাডমিনসহ সবাই'; ?></h1>
  <p class="rc-lead"><?php echo $en
    ? 'Change job role and Super Admin duty from one desk. The last Super Admin cannot be removed. Shop money is not written.'
    : 'এক জায়গা থেকে সবার কাজের রোল ও সুপার অ্যাডমিন দায়িত্ব বদলান। শেষ সুপার অ্যাডমিন সরানো যায় না। টাকা কাটে না।'; ?></p>

  <nav class="rc-nav">
    <a href="/modules/settings/users.php"><?php echo $en ? 'User list' : 'ইউজার তালিকা'; ?></a>
    <a href="/modules/settings/super_admin.php"><?php echo $en ? 'Super Admin desk' : 'সুপার অ্যাডমিন ডেস্ক'; ?></a>
    <a href="/modules/settings/role_permissions.php"><?php echo $en ? 'Module permissions' : 'মডিউল পারমিশন'; ?></a>
  </nav>

  <?php if ($flashOk !== ''): ?><div class="rc-flash ok"><?php echo htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
  <?php if ($flashErr !== ''): ?><div class="rc-flash err"><?php echo htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

  <div class="rc-kpis">
    <div class="rc-kpi"><span><?php echo $en ? 'On list' : 'তালিকায়'; ?></span><b><?php echo (int) $counts['total']; ?></b></div>
    <div class="rc-kpi"><span><?php echo $en ? 'Super Admin' : 'সুপার অ্যাডমিন'; ?></span><b><?php echo (int) $counts['super']; ?></b></div>
    <div class="rc-kpi"><span><?php echo $en ? 'Admin-like' : 'অ্যাডমিন'; ?></span><b><?php echo (int) $counts['admin']; ?></b></div>
    <div class="rc-kpi"><span><?php echo $en ? 'Other roles' : 'অন্য রোল'; ?></span><b><?php echo (int) $counts['other']; ?></b></div>
  </div>

  <section class="rc-card">
    <h2><?php echo $en ? 'Assign a role' : 'রোল বসান'; ?></h2>
    <?php if (!$can): ?>
      <p class="rc-hint"><?php echo $en ? 'Sign in as Super Admin to change these roles.' : 'রোল বদলাতে যে ইউজার সুপার অ্যাডমিন, সেই লগইন দিয়ে খুলুন।'; ?></p>
    <?php endif; ?>
    <input class="rc-search" id="rcSearch" type="search" placeholder="<?php echo $en ? 'Search name, login, role…' : 'নাম, লগইন বা রোল খুঁজুন…'; ?>" autocomplete="off">
    <?php foreach ($users as $u):
        $id = (int) $u['id'];
        $role = (string) $u['role'];
        $isSa = !empty($u['is_super_admin']);
        $label = (string) ($u['role_label'] ?? $role);
        $self = $id === $me;
        $blob = mb_strtolower(($u['display_name'] ?? '') . ' ' . ($u['username'] ?? '') . ' ' . $role . ' ' . $label, 'UTF-8');
    ?>
    <article class="rc-user" data-q="<?php echo htmlspecialchars($blob, ENT_QUOTES, 'UTF-8'); ?>">
      <header>
        <b><?php echo htmlspecialchars((string) $u['display_name'], ENT_QUOTES, 'UTF-8'); ?></b>
        <span class="meta">#<?php echo $id; ?> · <?php echo htmlspecialchars((string) $u['username'], ENT_QUOTES, 'UTF-8'); ?><?php echo $self ? ($en ? ' · you' : ' · আপনি') : ''; ?></span>
        <span class="rc-badge <?php echo $role === 'admin' ? 'admin' : ($role === 'manager' ? 'manager' : ''); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php if ($isSa): ?><span class="rc-badge sa"><?php echo $en ? 'Super Admin' : 'সুপার অ্যাডমিন'; ?></span><?php endif; ?>
        <?php if (!empty($u['is_removed'])): ?><span class="rc-badge off"><?php echo $en ? 'Removed' : 'সরানো'; ?></span><?php endif; ?>
      </header>
      <form class="rc-form" method="post" action="/modules/settings/process_role_control.php" autocomplete="off">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="user_id" value="<?php echo $id; ?>">
        <div>
          <label><?php echo $en ? 'Job role' : 'কাজের রোল'; ?></label>
          <select name="role" <?php echo $can && empty($u['is_removed']) ? '' : 'disabled'; ?>>
            <?php foreach ($catalog as $key => $lab): ?>
              <option value="<?php echo htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $role === $key ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $lab, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
            <?php if ($role !== '' && !isset($catalog[$role])): ?>
              <option value="<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>" selected><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endif; ?>
          </select>
        </div>
        <div>
          <label><?php echo $en ? 'Super Admin duty' : 'সুপার অ্যাডমিন'; ?></label>
          <label class="rc-sa">
            <input type="checkbox" name="is_super_admin" value="1" <?php echo $isSa ? 'checked' : ''; ?> <?php echo $can && empty($u['is_removed']) ? '' : 'disabled'; ?>>
            <?php echo $en ? 'Full project duty' : 'পূর্ণ দায়িত্ব'; ?>
          </label>
        </div>
        <div>
          <label><?php echo $en ? 'Your password (admin / Super Admin / yourself)' : 'আপনার পাসওয়ার্ড (অ্যাডমিন/সুপার/নিজের)'; ?></label>
          <input type="password" name="actor_password" autocomplete="current-password" <?php echo $can ? '' : 'disabled'; ?> placeholder="<?php echo $en ? 'Required for privileged change' : 'দায়িত্ব বদলালে লাগে'; ?>">
        </div>
        <div>
          <label><?php echo $en ? 'Note' : 'নোট'; ?></label>
          <input type="text" name="reason" maxlength="200" <?php echo $can ? '' : 'disabled'; ?> placeholder="<?php echo $en ? 'Optional' : 'ইচ্ছামতো'; ?>">
        </div>
        <div>
          <label>&nbsp;</label>
          <button class="rc-btn" type="submit" <?php echo $can && empty($u['is_removed']) ? '' : 'disabled'; ?>><?php echo $en ? 'Save role' : 'রোল সেভ'; ?></button>
        </div>
      </form>
    </article>
    <?php endforeach; ?>
    <?php if ($users === []): ?>
      <p class="rc-hint"><?php echo $en ? 'No users found.' : 'কোনো ইউজার নেই।'; ?></p>
    <?php endif; ?>
  </section>

  <?php if ($can && $history): ?>
  <section class="rc-card">
    <h2><?php echo $en ? 'Recent role changes' : 'সাম্প্রতিক রোল বদল'; ?></h2>
    <ul class="rc-hist">
      <?php foreach ($history as $h): ?>
        <li>
          <?php echo htmlspecialchars((string) ($h['actor_name'] ?? ('#' . $h['actor_id'])), ENT_QUOTES, 'UTF-8'); ?>
          →
          <?php echo htmlspecialchars((string) ($h['target_name'] ?? ('#' . $h['user_id'])), ENT_QUOTES, 'UTF-8'); ?>
          · <?php echo htmlspecialchars((string) $h['old_role'], ENT_QUOTES, 'UTF-8'); ?>
          → <?php echo htmlspecialchars((string) $h['new_role'], ENT_QUOTES, 'UTF-8'); ?>
          <?php if ((int) $h['old_super'] !== (int) $h['new_super']): ?>
            · SA <?php echo (int) $h['new_super'] === 1 ? ($en ? 'on' : 'চালু') : ($en ? 'off' : 'বন্ধ'); ?>
          <?php endif; ?>
          · <?php echo htmlspecialchars((string) $h['created_at'], ENT_QUOTES, 'UTF-8'); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</div>
<script>
(function () {
  var q = document.getElementById('rcSearch');
  if (!q) return;
  q.addEventListener('input', function () {
    var n = (q.value || '').toLowerCase();
    document.querySelectorAll('.rc-user').forEach(function (row) {
      var hay = (row.getAttribute('data-q') || '');
      row.style.display = hay.indexOf(n) === -1 ? 'none' : '';
    });
  });
})();
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
