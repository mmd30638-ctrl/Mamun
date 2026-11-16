<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
require_once '../../includes/permission_functions.php';
require_once '../../includes/permission_catalog.php';
admin_only();
ensure_user_schema($db);
ensure_permission_schema($db);
include '../../partials/header.php';

$modules = get_all_modules();
$catalog = ame_permission_catalog();
$total = count($modules);

/* ---------------------------------------------------------------------------
   BUG FIX: "ইউজার সিলেক্ট করা যাচ্ছে না" — বাম পাশের ইউজার-তালিকা খালি দেখাত।

   কারণ: নিচের মূল কোয়েরিতে u.use_custom_perms, u.removed_at এবং
   user_roles টেবিলের সাথে JOIN — এই কলাম/টেবিলগুলো নির্ভর করে
   ensure_user_schema()/ensure_permission_schema()-এর ALTER TABLE ঠিকমতো
   চলার উপর। শেয়ার্ড হোস্টিং-এ মাঝে মাঝে DB ইউজারের ALTER অনুমতি না থাকা,
   আগের একটা অসম্পূর্ণ মাইগ্রেশন, বা কলাম নাম মিসম্যাচের কারণে এই কোয়েরি
   ব্যর্থ (mysqli->query() => false) হয়ে যেত — আর mysqli_report() বন্ধ
   থাকায় (includes/db.php) কোনো এরর/ক্র্যাশ না দেখিয়ে চুপচাপ $users=false
   হয়ে যেত, ফলে $user_rows খালি থেকে যেত এবং "আগে ইউজার যোগ করুন" দেখাত —
   দেখে মনে হতো ইউজার সিলেক্টই করা যাচ্ছে না।

   সমাধান: user_roles JOIN নেই — MariaDB ১০.১১+ utf8mb4_uca1400_ai_ci
   আর পুরনো utf8mb4_general_ci মিক্স করে '=' ফেল করত। রোল লেবেল PHP-তে।
   কলাম না থাকলে ধাপে ধাপে সরল কোয়েরি। */
$users = @$db->query(
    "SELECT u.id, u.username, u.role, u.use_custom_perms, u.removed_at
     FROM users u
     ORDER BY u.username ASC"
);
if (!$users) {
    error_log('role_permissions.php: extra-column user query failed — ' . $db->error . ' — falling back');
    $users = @$db->query(
        "SELECT u.id, u.username, u.role, u.use_custom_perms FROM users u ORDER BY u.username ASC"
    );
}
if (!$users) {
    error_log('role_permissions.php: use_custom_perms query failed — ' . $db->error . ' — falling back further');
    $users = @$db->query("SELECT u.id, u.username, u.role FROM users u ORDER BY u.username ASC");
}
if (!$users) {
    error_log('role_permissions.php: base user query failed — ' . $db->error);
}
$user_rows = [];
if ($users) {
    while ($row = $users->fetch_assoc()) {
        $row += ['use_custom_perms' => 0, 'removed_at' => null, 'role_label' => null];
        if (function_exists('ame_user_is_removed') && ame_user_is_removed($row)) {
            continue;
        }
        $user_rows[] = $row;
    }
}
if (function_exists('ame_attach_role_labels')) {
    $user_rows = ame_attach_role_labels($db, $user_rows);
}

/* ---------------------------------------------------------------------------
   BUG FIX (আসল কারণ): এখানে $db->prepare() ব্যর্থ (false) হলেও আগে সরাসরি
   $st->bind_param() কল করা হতো — prepare() false রিটার্ন করলে (যেমন
   use_custom_perms কলাম কোনো কারণে না থাকলে, বা DB-তে সাময়িক সমস্যা হলে)
   "Call to a member function bind_param() on bool" — একটা ফ্যাটাল এরর হয়ে
   পুরো পেজ ভেঙে পড়ত ঠিক সেই মুহূর্তেই যখন এডমিন বাম পাশ থেকে কোনো ইউজার
   ক্লিক/সিলেক্ট করতেন (?user=ID লোড হওয়ার সময়) — এটাই "ইউজার সিলেক্ট করা
   যাচ্ছে না" সমস্যার আসল কারণ। এখন prepare() ব্যর্থ হলে ফ্যাটাল এরর না দিয়ে
   ফলব্যাক কোয়েরিতে চেষ্টা করা হয় এবং error_log-এ কারণ লেখা থাকে। ---- */
$edit_id = (int)($_GET['user'] ?? 0);
$edit = null;
if ($edit_id > 0) {
    $st = $db->prepare("SELECT id, username, role, use_custom_perms FROM users WHERE id=? LIMIT 1");
    if ($st) {
        $st->bind_param('i', $edit_id);
        $st->execute();
        $edit = $st->get_result()->fetch_assoc();
        $st->close();
    } else {
        error_log('role_permissions.php: prepare(use_custom_perms) failed — ' . $db->error . ' — falling back');
        $st2 = $db->prepare("SELECT id, username, role FROM users WHERE id=? LIMIT 1");
        if ($st2) {
            $st2->bind_param('i', $edit_id);
            $st2->execute();
            $edit = $st2->get_result()->fetch_assoc();
            $st2->close();
            if ($edit) {
                $edit['use_custom_perms'] = 0;
            }
        } else {
            error_log('role_permissions.php: prepare(base) also failed — ' . $db->error);
        }
    }
}

$checked = [];
if ($edit) {
    if (strtolower((string)$edit['role']) === 'admin') {
        $checked = array_keys($modules);
    } elseif ((int)($edit['use_custom_perms'] ?? 0) === 1) {
        $checked = ame_user_permission_keys($db, (int)$edit['id']);
    } else {
        $role = (string)$edit['role'];
        $pr = $db->prepare("SELECT module_key FROM role_permissions WHERE role_key=?");
        if ($pr) {
            $pr->bind_param('s', $role);
            $pr->execute();
            $res = $pr->get_result();
            while ($row = $res->fetch_assoc()) {
                $checked[] = $row['module_key'];
            }
            $pr->close();
        } else {
            error_log('role_permissions.php: prepare(role_permissions) failed — ' . $db->error);
        }
    }
}

$preview_labels = [
    'm-sales' => 'বিক্রয়',
    'm-sales-report' => 'বিক্রয় রিপোর্ট',
    'm-inv' => 'স্টক',
    'm-customers' => 'ক্রেতা',
    'm-reports' => 'রিপোর্ট',
    'm-settings' => 'সেটিংস',
];
?>
<link rel="stylesheet" href="/assets/css/studio.css?v=<?php echo $sys_version ?? '3.1'; ?>">

<div class="studio-page">
  <div class="studio-hero">
    <div class="studio-kicker">Access atelier · Per-user ledger</div>
    <h1><?php echo ame_pair('প্রত্যেক ইউজারের নিজস্ব চাবি', 'Every user has their own key'); ?></h1>
    <div class="studio-hairline"></div>
    <p><?php echo ame_pair(
        'ফ্ল্যাট বক্স নয় — সিনেমা কার্ড, গোষ্ঠীবদ্ধ ক্যাটালগ, অন/অফ সুইচ, কভারেজ ও লাইভ মেনু প্রিভিউ। অ্যাডমিন সবসময় খোলা থাকে। অন্যদের জন্য যেটা অন, সেটাই পাবে।',
        'Not a flat box — cinematic cards, grouped catalogue, on/off switches, coverage and a live menu preview. Admin is always fully open; others get only what is switched on.'
    ); ?></p>
  </div>

  <div class="studio-shell">
    <?php if (isset($_GET['saved'])): ?>
    <div class="flash-ok"><?php echo ame_pair('এই ইউজারের পারমিশন সেভ হয়েছে। পরের লগইনে মেনু সেভাবেই দেখাবে।', "This user's permissions were saved. Their menu will match this from the next login."); ?></div>
    <?php endif; ?>

    <div class="studio-grid studio-grid-perm">
      <aside class="studio-panel">
        <h2><?php echo ame_pair('হাউস', 'Users'); ?></h2>
        <p class="lead"><?php echo ame_pair('একজন বাছুন। বারটা তার অ্যাক্সেসের গভীরতা।', "Pick one. The bar shows how deep their access goes."); ?></p>
        <?php if ($user_rows): ?>
        <div class="user-search-wrap">
          <input type="search" id="userSearch" class="user-search-input" placeholder="<?php echo esc_html(is_en() ? 'Search by name or role…' : 'নাম বা রোল দিয়ে খুঁজুন…'); ?>" autocomplete="off">
        </div>
        <?php endif; ?>
        <div class="user-cinema" id="userCinema">
          <?php if ($user_rows): foreach ($user_rows as $u):
              $cov = ame_permission_coverage($db, $u, $total);
              $role_key = strtolower((string)$u['role']);
              $admin = $role_key === 'admin';
              $role_disp = (string)($u['role_label'] ?? $u['role']);
              $search_blob = mb_strtolower($u['username'] . ' ' . $role_disp . ' ' . $u['role'], 'UTF-8');
          ?>
          <a class="user-tile role-<?php echo esc_html($role_key ?: 'other'); ?> <?php echo (int)$u['id'] === $edit_id ? 'active' : ''; ?>"
             href="role_permissions.php?user=<?php echo (int)$u['id']; ?>"
             data-search="<?php echo esc_html($search_blob); ?>">
            <span class="role"><?php echo esc_html($role_disp); ?></span>
            <div class="nm"><?php echo esc_html($u['username']); ?></div>
            <div class="meta"><?php echo $admin ? esc_html(is_en() ? 'Master key · everything open' : 'মাস্টার কী · সব খোলা') : esc_html($cov[2] . ' · ' . $cov[0] . '/' . $total); ?></div>
            <div class="bar"><i style="width:<?php echo (int)$cov[1]; ?>%"></i></div>
          </a>
          <?php endforeach; else: ?>
          <p class="lead"><?php echo ame_pair('আগে ইউজার যোগ করুন।', 'Add a user first.'); ?></p>
          <?php endif; ?>
          <p class="lead user-search-empty" id="userSearchEmpty" hidden><?php echo ame_pair('কোনো ইউজার পাওয়া যায়নি।', 'No matching user found.'); ?></p>
        </div>
      </aside>

      <div>
        <?php if (!$edit): ?>
        <div class="studio-panel admin-lock">
          <h3><?php echo ame_pair('কাউকে বাছুন', 'Select someone'); ?></h3>
          <p class="lead" style="margin:0;"><?php echo ame_pair('বাম পাশ থেকে একজন ইউজার চাপলে তার ক্যাটালগ খুলবে — কোন অংশ দেখবে, কোনটা অন্ধকার থাকবে।', 'Tap a user on the left to open their catalogue — what they see, and what stays dark.'); ?></p>
        </div>
        <?php elseif (strtolower((string)$edit['role']) === 'admin'): ?>
        <div class="studio-panel admin-lock">
          <div class="studio-kicker">Master key</div>
          <h3><?php echo esc_html($edit['username']); ?></h3>
          <p class="lead"><?php echo ame_pair('অ্যাডমিনের চাবি আটকানো যায় না। এই অ্যাকাউন্ট সব মডিউল, সব সেটিংস ও সব ইউজার দেখে। নিরাপত্তার জন্য আলাদা স্টাফ অ্যাকাউন্টে সীমা বাঁধুন।', "An admin's key can't be locked. This account sees every module, setting and user. For safety, set limits on a separate staff account instead."); ?></p>
        </div>
        <?php else: ?>
        <form class="studio-panel" action="process_role_permissions.php" method="post" id="permForm">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="user_id" value="<?php echo (int)$edit['id']; ?>">
          <h2><?php echo esc_html($edit['username']); ?></h2>
          <p class="lead"><?php echo ame_pair('সুইচ অন = পাবে। অফ = মেনুতেই আসবে না। গ্রুপ হেডার থেকে পুরো ঘর একবারে খোলা/বন্ধ করা যায়।', "Switch on = they get it. Off = it won't even show in the menu. Use the group header to open/close a whole section at once."); ?></p>

          <?php foreach ($catalog as $gid => $group): ?>
          <section class="perm-group" data-group="<?php echo esc_html($gid); ?>">
            <div class="perm-group-h">
              <div>
                <div class="studio-kicker"><?php echo esc_html($group['en']); ?></div>
                <h3><?php echo esc_html($group['bn']); ?></h3>
                <p><?php echo esc_html($group['lead']); ?></p>
              </div>
              <button type="button" class="group-all" data-group="<?php echo esc_html($gid); ?>" data-on-label="<?php echo esc_html(is_en() ? 'Open all' : 'সব খুলুন'); ?>" data-off-label="<?php echo esc_html(is_en() ? 'Close all' : 'সব বন্ধ'); ?>"><?php echo ame_pair('সব খুলুন', 'Open all'); ?></button>
            </div>
            <?php foreach ($group['items'] as $key => $item):
                $on = in_array($key, $checked, true);
            ?>
            <label class="perm-item">
              <span class="perm-ico"><?php echo ame_permission_icon($item['icon']); ?></span>
              <span>
                <strong><?php echo esc_html($item['title']); ?></strong>
                <small><?php echo esc_html($item['desc']); ?></small>
              </span>
              <span class="switch">
                <input type="checkbox" name="mods[]" value="<?php echo esc_html($key); ?>" <?php echo $on ? 'checked' : ''; ?>>
                <span></span>
              </span>
            </label>
            <?php endforeach; ?>
          </section>
          <?php endforeach; ?>

          <div class="device" style="margin:1rem 0 0;">
            <div class="device-bar"><span>Menu preview</span><span><?php echo esc_html($edit['username']); ?></span></div>
            <div class="device-screen" style="min-height:auto;">
              <div class="pv-top">
                <span class="pv-brand"><?php echo esc_html(shop_name()); ?></span>
                <span class="pv-chip">STAFF</span>
              </div>
              <div class="pv-main" id="menuPreview" style="padding:0.8rem;">
              </div>
            </div>
          </div>

          <div class="studio-sticky">
            <div class="cov"><?php echo ame_pair('কভারেজ', 'Coverage'); ?> <b id="covNum">0</b> / <?php echo (int)$total; ?> <?php echo ame_pair('মডিউল', 'modules'); ?></div>
            <button class="btn-ink" type="submit"><?php echo ame_pair('এই চাবি সেভ করুন', 'Save this key'); ?></button>
          </div>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php if ($edit && strtolower((string)$edit['role']) !== 'admin'): ?>
<script>
(function(){
  var form = document.getElementById('permForm');
  var labels = <?php echo json_encode($preview_labels, JSON_UNESCAPED_UNICODE); ?>;
  function boxes(){ return form.querySelectorAll('input[name="mods[]"]'); }
  function paint(){
    var on = 0;
    boxes().forEach(function(b){ if (b.checked) on++; });
    document.getElementById('covNum').textContent = on;
    var html = '';
    Object.keys(labels).forEach(function(k){
      var el = form.querySelector('input[value="'+k+'"]');
      var ok = el && el.checked;
      html += '<a class="'+(ok?'on':'')+'" style="display:inline-block;margin:0 6px 6px 0;padding:6px 8px;font-size:12px;border:1px solid rgba(20,17,14,.12);'+(ok?'background:#14110e;color:#fff;':'opacity:.45;')+'">'+labels[k]+(ok?'':' · বন্ধ')+'</a>';
    });
    document.getElementById('menuPreview').innerHTML = html || '<span class="lead">কিছুই খোলা নেই</span>';
  }
  form.addEventListener('change', paint);
  document.querySelectorAll('.group-all').forEach(function(btn){
    btn.addEventListener('click', function(){
      var g = btn.getAttribute('data-group');
      var inputs = form.querySelectorAll('[data-group="'+g+'"] input[name="mods[]"]');
      var allOn = true;
      inputs.forEach(function(i){ if (!i.checked) allOn = false; });
      inputs.forEach(function(i){ i.checked = !allOn; });
      btn.textContent = allOn ? btn.getAttribute('data-on-label') : btn.getAttribute('data-off-label');
      paint();
    });
  });
  paint();
})();
</script>
<?php endif; ?>
<script>
(function(){
  // ফিচার: ইউজার-তালিকা সার্চ — নাম বা রোল দিয়ে দ্রুত খোঁজার জন্য (client-side,
  // ইনস্ট্যান্ট, কোনো পেজ-রিলোড লাগে না)।
  var input = document.getElementById('userSearch');
  if (!input) { return; }
  var cinema = document.getElementById('userCinema');
  var empty = document.getElementById('userSearchEmpty');
  function normalize(s){ return (s || '').toString().toLowerCase(); }
  function run(){
    var q = normalize(input.value).trim();
    var tiles = cinema.querySelectorAll('.user-tile');
    var visible = 0;
    tiles.forEach(function(tile){
      var hay = tile.getAttribute('data-search') || '';
      var show = q === '' || hay.indexOf(q) !== -1;
      tile.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (empty) { empty.hidden = (visible > 0 || tiles.length === 0); }
  }
  input.addEventListener('input', run);
  run();
})();
</script>
<?php include '../../partials/footer.php'; ?>
