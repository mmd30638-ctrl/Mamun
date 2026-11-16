<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/employee_hub.php';
if (is_file('../../includes/super_admin.php')) {
    require_once '../../includes/super_admin.php';
}
admin_only();
ensure_user_schema($db);
if (isset($db) && $db instanceof mysqli && function_exists('ensure_hrm_schema')) {
    ensure_hrm_schema($db);
}
if (isset($db) && $db instanceof mysqli && function_exists('ame_emp_hub_schema')) {
    ame_emp_hub_schema($db);
}
if (isset($db) && $db instanceof mysqli && function_exists('ame_super_admin_boot')) {
    ame_super_admin_boot($db);
}
include '../../partials/header.php';

if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}

$cols = [];
$colRes = @$db->query('SHOW COLUMNS FROM users');
if ($colRes) {
    while ($c = $colRes->fetch_assoc()) {
        $cols[] = $c['Field'];
    }
}
$has_last = in_array('last_login', $cols, true);

$last_map = [];
$lr = @$db->query('SELECT user_id, MAX(created_at) t FROM activity_logs GROUP BY user_id');
if ($lr) {
    while ($row = $lr->fetch_assoc()) {
        $last_map[(int)$row['user_id']] = $row['t'];
    }
}

$rows = function_exists('ame_user_roster_load')
    ? ame_user_roster_load($db)
    : [];
if ($rows === []) {
    $fallback = @$db->query('SELECT * FROM users ORDER BY id DESC');
    if ($fallback) {
        while ($row = $fallback->fetch_assoc()) {
            $rows[] = $row;
        }
        $fallback->free();
    } elseif (!empty($db->error)) {
        error_log('users roster query failed: ' . $db->error);
    }
    if (function_exists('ame_user_roster_unique')) {
        $rows = ame_user_roster_unique($rows);
    }
    if (function_exists('ame_attach_role_labels')) {
        $rows = ame_attach_role_labels($db, $rows);
    }
}
$stats = function_exists('ame_user_roster_stats')
    ? ame_user_roster_stats($rows)
    : ['total' => count($rows), 'listed' => 0, 'active' => 0, 'inactive' => 0, 'removed' => 0, 'purged' => 0];
$total = (int) $stats['total'];
$listed_n = (int) $stats['listed'];
$active_n = (int) $stats['active'];
$inactive_n = (int) $stats['inactive'];
$removed_n = (int) $stats['removed'];
$q = trim((string) ($_GET['q'] ?? ''));
$filter = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($filter, ['all', 'active', 'inactive', 'archive'], true)) {
    $filter = 'all';
}
$show_archive = $filter === 'archive';
$flash = (string)($_SESSION['user_flash'] ?? '');
$flash_ok = !empty($_SESSION['user_flash_ok']);
unset($_SESSION['user_flash'], $_SESSION['user_flash_ok']);
$sa_ids = (isset($db) && $db instanceof mysqli && function_exists('ame_super_admin_user_ids'))
    ? ame_super_admin_user_ids($db)
    : [];
$i_am_sa = function_exists('ame_is_super_admin') && ame_is_super_admin();

function ar_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'U';
    }
    if (function_exists('mb_substr')) {
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return strtoupper(substr($name, 0, 1));
}
?>
<link rel="stylesheet" href="/assets/css/roster.808.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.1', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
/* এই পেজের বাটনে (ফেরত/সরান ইত্যাদি) ক্লিক করলে সাধারণ রিং-স্পিনারের বদলে
   দোকানের লোগো ঘুরে দেখাবে। */
.ar-act.ame-wait-spin i, .ar-act .ame-wait-spin i {
    border: none !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important;
    background-repeat: no-repeat !important;
    background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<script src="/assets/js/device-display.js?v=8.08"></script>

<div class="ar-wrap ar-desk">
  <header class="ar-mast">
    <p class="ar-kicker"><?php echo ame_pair('অ্যাকসেস', 'Access'); ?></p>
    <h1><?php echo ame_pair('সিস্টেম ইউজার', 'System users'); ?></h1>
    <p><?php echo $show_archive
      ? ame_pair('এরা তালিকায় নেই। ফেরত দিলে আবার লগইন করতে পারবে — ইতিহাস আগে থেকেই আছে।', 'They are off the main list. Restore brings login back — history was never deleted.')
      : ame_pair('ডাটাবেসের প্রতিটি লগইন এখানে। নাম/মোবাইল/আইডি দিয়ে খুঁজুন। পাসওয়ার্ড বদলাতে সারির «পাসওয়ার্ড» চাপুন।', 'Every login account is here. Search by name, mobile or ID. Use Password on a row to change it.'); ?></p>
  </header>

  <nav class="ar-launch" aria-label="ইউজার কাজ">
    <a href="../hrm/employee_list.php"><strong>কর্মী ও বেতন</strong><small>একই প্রোফাইল — বেতন, হাজিরা, কাজ এক পেজ</small></a>
    <a href="user_add.php"><strong>নতুন ইউজার</strong><small>নাম, পাসওয়ার্ড, রোল বসিয়ে যোগ করুন</small></a>
    <a href="role_permissions.php"><strong>পারমিশন</strong><small>কে কোন মডিউল দেখবে সেটা খুলুন/বন্ধ করুন</small></a>
    <a href="role_control.php"><strong>রোল কন্ট্রোল</strong><small>সুপার অ্যাডমিনসহ সবার রোল বদলান</small></a>
    <a href="super_admin.php"><strong>সুপার অ্যাডমিন</strong><small><?php echo $i_am_sa ? 'দায়িত্ব দিন বা সরান' : 'কোথায় পাবেন ও কীভাবে একটিভ'; ?></small></a>
    <a href="user_reset.php"><strong>ইউজার রিসেট</strong><small>অ্যাডমিন বাদে বাকিদের লগইন একসাথে বন্ধ</small></a>
  </nav>

  <section class="ar-sa-help" id="ame-sa-help">
    <h2>সুপার অ্যাডমিন — কোথায় পাবেন, কীভাবে একটিভ</h2>
    <ol>
      <li>সাইডবার → সেটিংস → ★ সুপার অ্যাডমিন, অথবা উপরের কার্ড চাপুন।</li>
      <li>দোকানে সুপার অ্যাডমিন না থাকলে প্রথম অ্যাডমিন ইউজার অটো একটিভ হয়।</li>
      <li>যে ইউজার সুপার অ্যাডমিন, সে সেই পেজে অন্য ইউজারের পাশে «দায়িত্ব দিন» চাপে — তখন সেও সুপার অ্যাডমিন হয়।</li>
      <li>ভুল করে দিলে সেই ইউজারের «প্রোফাইল এডিট» খুলুন, নিজের পাসওয়ার্ড দিয়ে «দায়িত্ব সরান», তারপর এডিট বা সরান।</li>
      <li>শেষ সুপার অ্যাডমিনকে সরানো যায় না। অন্য অ্যাডমিন সুপার অ্যাডমিনকে মুছতে পারে না।</li>
    </ol>
    <p style="padding-left:0;margin-top:10px;">এখন এই লগইন <?php echo $i_am_sa ? 'সুপার অ্যাডমিন — পেজ খুলে দায়িত্ব দিতে পারবেন।' : 'সুপার অ্যাডমিন নয়। যে ইউজার সুপার অ্যাডমিন সে লগইন করে একটিভ করবে।'; ?></p>
  </section>

  <div class="ar-stats ar-stats-4">
    <article>
      <span><?php echo ame_pair('মোট ইউজার', 'All accounts'); ?></span>
      <b><?php echo (int) $total; ?></b>
    </article>
    <article>
      <span><?php echo ame_pair('তালিকায়', 'On this page'); ?></span>
      <b><?php echo (int) $listed_n; ?></b>
    </article>
    <article>
      <span><?php echo ame_pair('এক্টিভ', 'Active'); ?></span>
      <b><?php echo (int)$active_n; ?></b>
    </article>
    <article>
      <span><?php echo ame_pair('ইনঅ্যাকটিভ', 'Inactive'); ?></span>
      <b><?php echo (int)$inactive_n; ?></b>
    </article>
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

  <div class="ar-toolbar no-print">
    <a class="ar-btn" href="user_add.php"><?php echo ame_pair('নতুন ইউজার যোগ করুন', 'Add user'); ?></a>
    <a class="ar-btn ghost" href="role_control.php"><?php echo ame_pair('রোল কন্ট্রোল', 'Role control'); ?></a>
    <a class="ar-btn ghost" href="role_permissions.php"><?php echo ame_pair('পারমিশন', 'Permissions'); ?></a>
    <a class="ar-btn ghost ar-btn-remove" href="user_reset.php" style="margin-left:auto;" title="<?php echo htmlspecialchars(ame_l('অ্যাডমিন অ্যাকাউন্ট সবসময় সুরক্ষিত থাকবে।', 'Admin account will always remain protected.'), ENT_QUOTES, 'UTF-8'); ?>">🛡️ <?php echo ame_pair('ইউজার রিসেট', 'User Reset'); ?></a>
  </div>
  <div class="ar-filters no-print">
    <a class="ar-filter<?php echo $filter === 'all' ? ' on' : ''; ?>" href="users.php"><?php echo ame_pair('সব', 'All'); ?></a>
    <a class="ar-filter<?php echo $filter === 'active' ? ' on' : ''; ?>" href="users.php?status=active"><?php echo ame_pair('এক্টিভ', 'Active'); ?></a>
    <a class="ar-filter<?php echo $filter === 'inactive' ? ' on' : ''; ?>" href="users.php?status=inactive"><?php echo ame_pair('ইনঅ্যাকটিভ', 'Inactive'); ?></a>
  </div>
  <form class="ar-search no-print" method="get" action="users.php" role="search">
    <?php if ($filter !== 'all'): ?>
    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <label class="ar-search-label" for="arUserQ"><?php echo ame_pair('খুঁজুন', 'Search'); ?></label>
    <input type="search" id="arUserQ" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(ame_l('নাম, মোবাইল, ইউজারনেম, আইডি', 'Name, mobile, username, ID'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
    <button type="submit" class="ar-btn ghost"><?php echo ame_pair('খুঁজুন', 'Search'); ?></button>
    <?php if ($q !== ''): ?>
    <a class="ar-btn ghost" href="users.php<?php echo $filter !== 'all' ? ('?status=' . urlencode($filter)) : ''; ?>"><?php echo ame_pair('মুছুন', 'Clear'); ?></a>
    <?php endif; ?>
  </form>

  <?php
    $id_line = function_exists('ame_user_roster_id_line') ? ame_user_roster_id_line($rows) : '';
    $emp_nologin = (isset($db) && $db instanceof mysqli && function_exists('ame_user_roster_employees_without_login'))
        ? ame_user_roster_employees_without_login($db)
        : [];
    $me = (int) ($_SESSION['user_id'] ?? 0);
    $active_admin_n = function_exists('ame_count_active_admins') ? ame_count_active_admins($db, 0) : 1;
    $paint = [];
    foreach ($rows as $u) {
        if (!is_array($u)) {
            continue;
        }
        if (function_exists('ame_user_is_purged') && ame_user_is_purged($u)) {
            continue;
        }
        $is_removed = ame_user_is_removed($u);
        $is_active = !$is_removed && ame_user_row_active($u);
        if ($show_archive) {
            if (!$is_removed) {
                continue;
            }
        } elseif ($filter === 'active' && !$is_active) {
            continue;
        } elseif ($filter === 'inactive' && ($is_removed || $is_active)) {
            continue;
        }
        $paint[] = $u;
    }
    $shown = count($paint);
    if ($id_line !== ''): ?>
  <p class="ar-id-line"><?php echo ame_pair('ডাটাবেসের ইউজার', 'Accounts in database'); ?>: <?php echo htmlspecialchars($id_line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
  <?php endif; ?>

  <div class="ar-user-list" data-ame-no-page="1">
    <?php if ($paint === [] && $emp_nologin === []): ?>
      <p class="ar-empty"><?php echo ame_pair('কোনো ইউজার পাওয়া যায়নি।', 'No users found.'); ?></p>
    <?php endif; ?>
    <?php foreach ($paint as $u):
        $id = (int) ($u['id'] ?? $u['user_id'] ?? 0);
        $role_key = strtolower((string) ($u['role'] ?? ''));
        $is_admin = $role_key === 'admin';
        $role_label = (string) ($u['role_label'] ?? $u['role'] ?? '');
        $role_class = 'employee';
        if ($is_admin) {
            $role_class = 'admin';
        } elseif ($role_key === 'manager') {
            $role_class = 'manager';
        }
        $photo = (string) ($u['photo'] ?? '');
        $is_removed = ame_user_is_removed($u);
        $is_active = !$is_removed && ame_user_row_active($u);
        $shown_name = trim((string) ($u['emp_name'] ?? ''));
        if ($shown_name === '') {
            $shown_name = ame_user_display_name($u);
        }
        $is_self = $me === $id;
        $is_last_admin = $is_admin && $is_active && $active_admin_n < 2;
        $is_sa_row = in_array($id, $sa_ids, true);
        $can_remove = !$is_removed && !$is_self && !$is_last_admin && !$is_sa_row;
        $phone = trim((string) ($u['phone'] ?? ''));
        if ($phone === '') {
            $phone = trim((string) ($u['emp_phone'] ?? ''));
        }
        $empId = (int) ($u['emp_id'] ?? $u['employee_id'] ?? 0);
        $empCode = trim((string) ($u['emp_code'] ?? ''));
        $empPhoto = trim((string) ($u['emp_photo'] ?? ''));
        if ($empPhoto !== '') {
            $photo = $empPhoto;
        }
        $uname = trim((string) ($u['username'] ?? ''));
        $h = static function ($s): string {
            return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $row_q = $h(trim($shown_name . ' ' . $uname . ' ' . $phone . ' ' . $empCode . ' #' . $id));
        $u_locked_raw = trim((string) ($u['locked_until'] ?? ''));
        $is_locked = !$is_removed && $u_locked_raw !== '' && strtotime($u_locked_raw) > time();
    ?>
    <article class="ar-user-card<?php echo $is_removed ? ' is-gone' : ($is_active ? '' : ' is-off'); ?>" data-ar-q="<?php echo $row_q; ?>">
      <div class="ar-person">
        <?php if ($empPhoto !== ''): ?>
          <img class="ar-ava" src="/uploads/employees/<?php echo $h($empPhoto); ?>" alt="" onerror="this.style.display='none'">
        <?php elseif ($photo !== ''): ?>
          <img class="ar-ava" src="../../assets/img/<?php echo $h($photo); ?>" alt="">
        <?php else: ?>
          <div class="ar-ava-ph" aria-hidden="true"><?php echo $h(ar_initials($shown_name)); ?></div>
        <?php endif; ?>
        <div>
          <div class="ar-name"><span class="ar-id-num">#<?php echo $id; ?></span> <?php echo $h($shown_name); ?><?php
            if ($is_sa_row) {
                echo ' <span class="ar-sa-badge">সুপার অ্যাডমিন</span>';
            }
          ?></div>
          <div class="ar-meta"><?php echo $h(($phone !== '' ? $phone : 'মোবাইল নেই') . ($empCode !== '' ? ' · ' . $empCode : '')); ?></div>
        </div>
      </div>
      <div class="ar-cred-grid">
        <div>
          <span><?php echo ame_pair('ইউজারনেম', 'Username'); ?></span>
          <?php if ($uname !== ''): ?>
          <code class="ar-login-name"><?php echo $h($uname); ?></code>
          <button type="button" class="ar-copy-mini" data-copy="<?php echo $h($uname); ?>"><?php echo ame_pair('কপি', 'Copy'); ?></button>
          <?php else: ?>
          <span class="ar-meta">—</span>
          <?php endif; ?>
        </div>
        <div>
          <span><?php echo ame_pair('পাসওয়ার্ড', 'Password'); ?></span>
          <span class="ar-pass-mask">••••••••</span>
          <?php if (!$is_removed): ?>
          <a class="ar-act" href="reset_password.php?id=<?php echo $id; ?>"><?php echo ame_pair('বদলান', 'Change'); ?></a>
          <?php endif; ?>
        </div>
        <div>
          <span><?php echo ame_pair('রোল', 'Role'); ?></span>
          <span class="ar-role <?php echo $role_class; ?>"><?php echo $h($role_label); ?></span>
        </div>
        <div>
          <span><?php echo ame_pair('স্ট্যাটাস', 'Status'); ?></span>
          <?php
            if ($is_removed) {
                echo '<span class="ar-status gone">' . ame_pair('সরানো', 'Removed') . '</span>';
            } elseif ($is_active) {
                echo '<span class="ar-status on">' . ame_pair('এক্টিভ', 'Active') . '</span>';
            } else {
                echo '<span class="ar-status off">' . ame_pair('ইনঅ্যাকটিভ', 'Inactive') . '</span>';
            }
            if ($is_locked) {
                echo ' <span class="ar-status off">' . ame_pair('🔒 লকড', '🔒 Locked') . '</span>';
            }
          ?>
        </div>
      </div>
      <div class="ar-td-ops">
        <a class="ar-act" href="user_view.php?id=<?php echo $id; ?>"><?php echo ame_pair('খুলুন', 'Open'); ?></a>
        <a class="ar-act" href="user_profile.php?id=<?php echo $id; ?>"><?php echo ame_pair('প্রোফাইল এডিট', 'Edit profile'); ?></a>
        <?php if (!$is_removed): ?>
        <a class="ar-act" href="reset_password.php?id=<?php echo $id; ?>"><?php echo ame_pair('পাসওয়ার্ড', 'Password'); ?></a>
        <?php endif; ?>
        <?php if ($empId > 0 && !$is_removed): ?>
        <a class="ar-act ghost" href="../hrm/employee_list.php?emp=<?php echo (int) $empId; ?>&amp;open=salary"><?php echo ame_pair('বেতন', 'Salary'); ?></a>
        <?php endif; ?>
        <?php if ($is_removed): ?>
        <form method="post" action="process_user_remove.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="id" value="<?php echo $id; ?>">
          <input type="hidden" name="action" value="restore">
          <button type="submit" class="ar-act ghost" onclick="return confirm('এই ব্যবহারকারী তালিকায় ফিরবে এবং আবার লগইন করতে পারবে।\n\nThis user will return to the list and can sign in again.');"><?php echo ame_pair('ফেরত', 'Restore'); ?></button>
        </form>
        <form method="post" action="process_user_delete.php" data-ame-hard-delete="1" data-confirm-type="danger" data-confirm="এই ইউজার চিরতরে ডিলিট হবে। লগইন আর থাকবে না। বিক্রয়, লেজার ও ইনভয়েস থাকবে — কোনো ব্যবসার তথ্য নষ্ট হবে না।">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="id" value="<?php echo $id; ?>">
          <input type="hidden" name="action" value="purge">
          <button type="submit" class="ar-act danger ame-hard-delete-btn"><?php echo ame_pair('চিরতরে ডিলিট', 'Delete forever'); ?></button>
        </form>
        <?php elseif ($can_remove): ?>
        <form method="post" action="process_user_remove.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="id" value="<?php echo $id; ?>">
          <input type="hidden" name="action" value="remove">
          <button type="submit" class="ar-act danger" onclick="return confirm('এই ব্যবহারকারী তালিকা থেকে সরে যাবে। লগইন বন্ধ থাকবে। বিক্রয়, লেজার ও ইতিহাস থাকবে — রেকর্ড মুছে যাবে না।\n\nThis user will leave this page. Login stops. Sales, ledger and history stay — the record is not deleted.');"><?php echo ame_pair('সরান', 'Remove'); ?></button>
        </form>
        <?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
    <?php foreach ($emp_nologin as $e):
        if ($show_archive || $filter === 'inactive') {
            continue;
        }
        $eid = (int) ($e['id'] ?? 0);
        $ename = trim((string) ($e['name'] ?? ''));
        $ephone = trim((string) ($e['phone'] ?? ''));
        $ecode = trim((string) ($e['employee_code'] ?? ''));
        $ephoto = trim((string) ($e['photo'] ?? ''));
        $h = static function ($s): string {
            return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $shown++;
    ?>
    <article class="ar-user-card ar-user-nologin" data-ar-q="<?php echo $h($ename . ' ' . $ephone . ' ' . $ecode); ?>">
      <div class="ar-person">
        <?php if ($ephoto !== ''): ?>
          <img class="ar-ava" src="/uploads/employees/<?php echo $h($ephoto); ?>" alt="" onerror="this.style.display='none'">
        <?php else: ?>
          <div class="ar-ava-ph" aria-hidden="true"><?php echo $h(ar_initials($ename !== '' ? $ename : 'E')); ?></div>
        <?php endif; ?>
        <div>
          <div class="ar-name"><?php echo $h($ename !== '' ? $ename : 'কর্মী'); ?> <span class="ar-status off"><?php echo ame_pair('লগইন নেই', 'No login'); ?></span></div>
          <div class="ar-meta"><?php echo $h(($ephone !== '' ? $ephone : 'মোবাইল নেই') . ($ecode !== '' ? ' · ' . $ecode : '')); ?></div>
        </div>
      </div>
      <div class="ar-cred-grid">
        <div>
          <span><?php echo ame_pair('ইউজারনেম', 'Username'); ?></span>
          <span class="ar-meta"><?php echo ame_pair('এখনো নেই', 'Not set'); ?></span>
        </div>
        <div>
          <span><?php echo ame_pair('পাসওয়ার্ড', 'Password'); ?></span>
          <span class="ar-meta"><?php echo ame_pair('এখনো নেই', 'Not set'); ?></span>
        </div>
      </div>
      <div class="ar-td-ops">
        <a class="ar-act" href="user_profile.php?emp=<?php echo $eid; ?>"><?php echo ame_pair('লগইন বসান', 'Set login'); ?></a>
        <a class="ar-act ghost" href="../hrm/employee_list.php?emp=<?php echo $eid; ?>"><?php echo ame_pair('কর্মী পেজ', 'Employee page'); ?></a>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php if (!$show_archive): ?>
    <p class="ar-quiet"><?php echo ame_pair(
        'ডাটাবেসে ' . (int) $total . 'টি ইউজার — এই পাতায় ' . (int) $shown . 'টি দেখাচ্ছি।',
        (int) $total . ' accounts in the database — showing ' . (int) $shown . ' on this page.'
    ); ?></p>
  <?php endif; ?>
  <?php if ($removed_n > 0 && !$show_archive): ?>
    <a class="ar-quiet" href="users.php?status=archive"><?php echo ame_pair('সরানো আর্কাইভ', 'Removed archive'); ?> · <?php echo (int) $removed_n; ?></a>
  <?php elseif ($show_archive): ?>
    <?php if ($removed_n > 0): ?>
    <form method="post" action="process_user_delete.php" data-ame-hard-delete="1" data-confirm-type="danger" data-confirm="সরানো সব ইউজার চিরতরে ডিলিট হবে। লগইন আর থাকবে না। বিক্রয়, লেজার ও ইনভয়েস থাকবে।" style="margin:14px 0 0;text-align:center;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="purge_all">
      <button type="submit" class="ar-act danger ame-hard-delete-btn"><?php echo ame_pair('সরানো সবাইকে চিরতরে ডিলিট', 'Delete all removed forever'); ?></button>
    </form>
    <?php endif; ?>
    <a class="ar-quiet" href="users.php"><?php echo ame_pair('তালিকায় ফিরুন', 'Back to list'); ?></a>
  <?php endif; ?>
</div>
<script>
(function () {
  var box = document.getElementById('arUserQ');
  function applyQ() {
    var needle = box ? String(box.value || '').toLowerCase().trim() : '';
    document.querySelectorAll('.ar-user-card').forEach(function (card) {
      var hay = String(card.getAttribute('data-ar-q') || card.textContent || '').toLowerCase();
      card.style.display = (!needle || hay.indexOf(needle) !== -1) ? '' : 'none';
    });
  }
  if (box) {
    box.addEventListener('input', applyQ);
    applyQ();
  }
  function copyText(v) {
    if (!v) { return; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(v);
    } else {
      var ta = document.createElement('textarea');
      ta.value = v;
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); } catch (e) {}
      document.body.removeChild(ta);
    }
  }
  document.querySelectorAll('.ar-copy-mini').forEach(function (btn) {
    btn.addEventListener('click', function () {
      copyText(btn.getAttribute('data-copy') || '');
      btn.setAttribute('data-done', '1');
    });
  });
})();
</script>
<?php include '../../partials/footer.php'; ?>
