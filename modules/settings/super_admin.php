<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/super_admin.php';
require_once __DIR__ . '/../../includes/staff_archive.php';

$db = $GLOBALS['db'] ?? null;
if (!$db instanceof mysqli) {
    header('Location: /index.php');
    exit;
}
ame_super_admin_boot($db);
if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
$can_manage = ame_is_super_admin();
if (!$can_manage && !ame_is_admin_like()) {
    header('Location: /modules/settings/users.php');
    exit;
}

$flash = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    $okCsrf = empty($_SESSION['csrf_token']) || hash_equals((string) $_SESSION['csrf_token'], $token);
    if (!$okCsrf) {
        $err = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
    } else {
        if (!$can_manage) {
            $err = 'সুপার অ্যাডমিন একটিভ করতে এই লগইনে সুপার অ্যাডমিন দায়িত্ব লাগবে।';
        }
        $do = trim((string) ($_POST['do'] ?? ''));
        $id = (int) ($_POST['id'] ?? 0);
        if ($can_manage && $do === 'grant') {
            $err = ame_super_admin_set($db, $id, true);
            if ($err === '') {
                $flash = 'সুপার অ্যাডমিন দায়িত্ব দেওয়া হয়েছে।';
            }
        } elseif ($can_manage && $do === 'revoke') {
            $err = ame_super_admin_set($db, $id, false);
            if ($err === '') {
                $flash = 'সুপার অ্যাডমিন দায়িত্ব সরানো হয়েছে।';
                ame_super_admin_boot($db);
            }
        }
    }
}

$users = ame_super_admin_list($db);
$duties = ame_super_admin_duties();
$dutyLinks = ame_super_admin_duty_links();
$ver = '10.98.340';
if (is_file(__DIR__ . '/../../VERSION.txt')) {
    $fileVer = trim((string) file_get_contents(__DIR__ . '/../../VERSION.txt'));
    if ($fileVer !== '') {
        $ver = $fileVer;
    }
}
$csrf = htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
$archived = ame_staff_archive_rows($db, true);
include __DIR__ . '/../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/staff-archive.css?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>">
<div class="ame-staff-page ame-sa-page">
  <a class="ame-staff-home" href="/index.php" title="হোমে যান" aria-label="হোমে যান">🏠 হোম</a>
  <header class="ame-staff-hero ame-sa-hero">
    <p class="ame-staff-kicker">সেটিংস</p>
    <h1>সুপার অ্যাডমিন</h1>
    <p>প্রজেক্টের পূর্ণ দায়িত্ব এই আসনে। অন্য অ্যাডমিন সুপার অ্যাডমিনকে মুছতে বা সরাতে পারে না। কোনো সোর্স কোড মুছে যায় না।</p>
  </header>
  <section class="ame-staff-card">
    <div class="ame-staff-card-h"><strong>কোথায় পাবেন ও কীভাবে একটিভ করবেন</strong></div>
    <div style="padding:14px 16px;font-size:15px;font-weight:700;line-height:1.55;color:#3D2A12;">
      <ol style="margin:0;padding-left:20px;">
        <li>সাইডবার → সেটিংস → ★ সুপার অ্যাডমিন। ইউজার তালিকার উপরের কার্ড থেকেও এই পেজ খোলে।</li>
        <li>দোকানে কেউ সুপার অ্যাডমিন না থাকলে প্রথম অ্যাডমিন ইউজার অটো একটিভ হয় — আলাদা কোড লাগে না।</li>
        <li>যে ইউজার ইতিমধ্যে সুপার অ্যাডমিন, সে নিচের তালিকায় অন্য ইউজারের পাশে «দায়িত্ব দিন» চাপে। তখন সেই ইউজারও সুপার অ্যাডমিন হয়।</li>
        <li>দায়িত্ব সরাতে «দায়িত্ব সরান» চাপুন। শেষ সুপার অ্যাডমিনকে সরানো যায় না।</li>
      </ol>
      <p style="margin:12px 0 0;">এই লগইন এখন <?php echo $can_manage ? 'সুপার অ্যাডমিন — নিচে দায়িত্ব দিতে পারবেন।' : 'সুপার অ্যাডমিন নয়। যে ইউজার সুপার অ্যাডমিন সে লগইন করে «দায়িত্ব দিন» চাপবে।'; ?></p>
      <p style="margin:8px 0 0;"><a href="/modules/settings/users.php">ইউজার তালিকায় ফিরুন</a> · <a href="/modules/settings/role_control.php">রোল কন্ট্রোল — সবার রোল</a></p>
    </div>
  </section>
  <?php if ($flash !== ''): ?>
    <div class="ame-staff-ok"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>
  <?php if ($err !== ''): ?>
    <div class="ame-staff-err"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <section class="ame-staff-grid">
    <article class="ame-staff-card">
      <div class="ame-staff-card-h"><strong>যে দায়িত্ব এই আসনের</strong></div>
      <ul class="ame-sa-duties">
        <?php foreach ($duties as $duty): ?>
          <li><?php echo htmlspecialchars($duty, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
      <div class="ame-sa-links">
        <?php foreach ($dutyLinks as $pair): ?>
          <a href="<?php echo htmlspecialchars($pair[1], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($pair[0], ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endforeach; ?>
      </div>
    </article>
    <article class="ame-staff-card">
      <div class="ame-staff-card-h">
        <strong>সরানো কর্মী</strong>
        <a href="/modules/hrm/employee_archive.php">আর্কাইভ খুলুন</a>
      </div>
      <p class="ame-sa-kpi"><?php echo number_format(count($archived)); ?> <span>জন আর্কাইভে</span></p>
      <p class="ame-staff-muted">ফেরত আনলে কোড আগের মতোই থাকে। চিরতরে মুছলে তালিকা থেকে উঠে, খাতা মুছে যায় না।</p>
    </article>
  </section>

  <div class="ame-staff-card">
    <div class="ame-staff-card-h"><strong>ইউজার ও সুপার অ্যাডমিন</strong></div>
    <div class="ame-staff-table-wrap">
      <table class="ame-staff-table">
        <thead>
          <tr>
            <th>নাম</th>
            <th>ইউজার</th>
            <th>রোল</th>
            <th>সুপার অ্যাডমিন</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <?php $isSa = !empty($u['is_super_admin']); ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($u['display_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($u['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($u['role'] ?? 'admin'), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo $isSa ? 'হ্যাঁ' : 'না'; ?></td>
              <td>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                  <input type="hidden" name="id" value="<?php echo (int) ($u['id'] ?? 0); ?>">
                  <?php if (!$can_manage): ?>
                    <span class="ame-staff-muted">একটিভ করতে সুপার অ্যাডমিন লগইন লাগবে</span>
                  <?php elseif ($isSa): ?>
                    <input type="hidden" name="do" value="revoke">
                    <button type="submit" class="ame-staff-muted-btn">দায়িত্ব সরান</button>
                  <?php else: ?>
                    <input type="hidden" name="do" value="grant">
                    <button type="submit" class="ame-staff-restore">দায়িত্ব দিন</button>
                  <?php endif; ?>
                    <a href="/modules/settings/user_profile.php?id=<?php echo (int) ($u['id'] ?? 0); ?>">প্রোফাইল এডিট</a>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$users): ?>
            <tr><td colspan="5">ইউজার তালিকা খোলা যায়নি — তবু এই সেশন সুপার অ্যাডমিন হিসেবে চলছে।</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
