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

$id = (int) ($_GET['id'] ?? 0);
$target = null;
if ($id > 0) {
    $st = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    if ($st) {
        $st->bind_param('i', $id);
        $st->execute();
        $target = $st->get_result()->fetch_assoc();
        $st->close();
    }
}
if ($target && function_exists('ame_attach_role_label')) {
    $target = ame_attach_role_label($db, $target);
}

include '../../partials/header.php';

if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}

$flash = (string) ($_SESSION['user_flash'] ?? '');
$flash_ok = !empty($_SESSION['user_flash_ok']);
unset($_SESSION['user_flash'], $_SESSION['user_flash_ok']);

$actorIsSa = function_exists('ame_is_super_admin') && ame_is_super_admin();
$deny = null;
if (!$target) {
    $deny = 'ইউজার খুঁজে পাওয়া যায়নি। / User not found.';
} else {
    $deny = ame_user_password_reset_denied($target, $actorIsSa);
}
$display = $target ? ame_user_display_name($target) : '';
$uname = (string) ($target['username'] ?? '');
$hint = function_exists('ame_password_policy_hint') ? ame_password_policy_hint() : 'কমপক্ষে ৮ অক্ষর, একটি অক্ষর ও একটি সংখ্যা।';
?>
<link rel="stylesheet" href="/assets/css/roster.808.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.1', ENT_QUOTES, 'UTF-8'); ?>">
<style>
    .rp-wrap { padding: 15px; }
    .rp-card { background:#fff; border-radius:14px; max-width:480px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
    .rp-topbar { display:flex; height:6px; }
    .rp-topbar span { flex:1; }
    .rp-c1{background:var(--brand-navy);} .rp-c2{background:var(--brand-accent);} .rp-c3{background:var(--brand-gold);}
    .rp-header { text-align:center; padding:20px 15px 15px; }
    .rp-header h3 { margin:0; font-size:19px; color:#1B5E20; }
    .rp-header small { color:#666; font-size:12px; }
    .rp-target { background:#fafafa; margin:0 20px 18px; padding:12px 15px; border-radius:8px; border:1px solid #eee; font-size:14px; font-weight:700; color:#000; text-align:center; }
    .rp-cred { display:flex; justify-content:space-between; align-items:center; gap:8px; margin-top:8px; font-weight:600; font-size:13px; text-align:left; }
    .rp-cred span { color:#555; }
    .rp-cred code { background:#fff; border:1px solid #ddd; border-radius:6px; padding:6px 10px; font-size:14px; word-break:break-all; }
    .rp-body { padding:0 20px 20px; }
    .rp-group { margin-bottom:14px; }
    .rp-group label { display:block; font-weight:600; font-size:13px; color:#000; margin-bottom:6px; }
    .rp-group input { width:100%; padding:11px; border:1px solid #ccc; border-radius:8px; box-sizing:border-box; font-size:14px; background:#fafafa; }
    .rp-group input:focus { outline:none; border-color:var(--brand-accent); background:#fff; }
    .rp-submit { width:100%; padding:14px; border:none; border-radius:8px; background:var(--brand-gradient,#111); color:#fff; font-weight:700; font-size:15px; cursor:pointer; margin-top:8px; }
    .rp-back { display:block; text-align:center; margin-top:14px; color:#666; text-decoration:none; font-size:13px; }
    .rp-warn { background:#FFF3E0; color:#E65100; padding:10px 20px; margin:0 20px 15px; border-radius:8px; font-size:12px; }
    .rp-note { background:#E3F2FD; color:#0D47A1; padding:10px 20px; margin:0 20px 15px; border-radius:8px; font-size:12px; line-height:1.6; }
    .rp-gen-row { display:flex; gap:8px; margin-bottom:14px; }
    .rp-gen-btn { flex:1; padding:11px; border:1px solid var(--brand-accent,#111); border-radius:8px; background:#fff; color:var(--brand-accent,#111); font-weight:700; font-size:13px; cursor:pointer; }
    .rp-field-row { position:relative; }
    .rp-eye { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:15px; padding:4px; }
    .rp-group input { padding-right:38px; }
    .rp-copy-wrap { display:none; margin:0 20px 15px; padding:12px 14px; border-radius:8px; background:#E8F5E9; border:1px solid #A5D6A7; }
    .rp-copy-wrap.show { display:block; }
    .rp-copy-wrap b { display:block; font-size:12px; color:#1B5E20; margin-bottom:6px; }
    .rp-copy-row { display:flex; gap:8px; align-items:center; }
    .rp-copy-row code { flex:1; background:#fff; border:1px solid #A5D6A7; border-radius:6px; padding:8px 10px; font-size:14px; letter-spacing:0.5px; word-break:break-all; }
    .rp-copy-row button { border:none; background:#1B5E20; color:#fff; border-radius:6px; padding:8px 12px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap; }
    .rp-force-row { display:flex; align-items:flex-start; gap:8px; margin:0 0 14px; font-size:12.5px; color:#333; }
    .rp-force-row input { width:auto; margin-top:3px; }
    .rp-flash { margin:0 20px 14px; padding:10px 12px; border-radius:8px; font-size:13px; font-weight:700; }
    .rp-flash.ok { background:#ecfdf3; color:#166534; }
    .rp-flash.err { background:#fef2f2; color:#991b1b; }
</style>

<div class="rp-wrap">
    <div class="rp-card">
        <div class="rp-topbar"><span class="rp-c1"></span><span class="rp-c2"></span><span class="rp-c3"></span></div>
        <div class="rp-header">
            <h3><?php echo ame_pair('পাসওয়ার্ড বদলান', 'Change password'); ?></h3>
            <small><?php echo ame_pair('অ্যাডমিন / সুপার অ্যাডমিন — নিজের পাসওয়ার্ড দিয়ে নিশ্চিত করুন', 'Admin / Super Admin — confirm with your own password'); ?></small>
        </div>
        <?php if ($flash !== ''): ?>
        <div class="rp-flash <?php echo $flash_ok ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($deny !== null): ?>
        <div class="rp-flash err"><?php echo htmlspecialchars($deny, ENT_QUOTES, 'UTF-8'); ?></div>
        <a href="users.php" class="rp-back"><?php echo ame_pair('তালিকায় ফিরুন', 'Back to list'); ?></a>
        <?php else: ?>
        <div class="rp-target">
            <div><?php echo htmlspecialchars($display !== '' ? $display : $uname, ENT_QUOTES, 'UTF-8'); ?> · #<?php echo (int) $target['id']; ?></div>
            <div class="rp-cred">
                <span><?php echo ame_pair('বর্তমান ইউজারনেম', 'Current username'); ?></span>
                <code id="rpUserName"><?php echo htmlspecialchars($uname !== '' ? $uname : '—', ENT_QUOTES, 'UTF-8'); ?></code>
            </div>
            <div class="rp-cred">
                <span><?php echo ame_pair('বর্তমান পাসওয়ার্ড', 'Current password'); ?></span>
                <code>••••••••</code>
            </div>
        </div>
        <div class="rp-note"><?php echo ame_pair(
            'পুরনো পাসওয়ার্ড কেউ দেখতে পারে না — এমনকি অ্যাডমিনও নয়। এখানে নতুন পাসওয়ার্ড বসান, কপি করে ইউজারকে দিন। সেভের পর তার অন্য ডিভাইসের লগইন বন্ধ হয়ে যাবে।',
            'Nobody can see the old password, not even an admin. Set a new one, copy it, and give it to the user. Other sessions close after save.'
        ); ?></div>
        <div class="rp-warn"><?php echo ame_pair('নতুন পাসওয়ার্ড সেভ হলে পুরনোটা আর কাজ করবে না।', 'The old password stops working as soon as the new one is saved.'); ?></div>
        <div class="rp-copy-wrap" id="rpCopyWrap">
            <b><?php echo ame_pair('লগইন কপি করুন — ইউজারনেম ও নতুন পাসওয়ার্ড (আবার দেখা যাবে না):', 'Copy login — username and new password (will not be shown again):'); ?></b>
            <div class="rp-copy-row">
                <code id="rpGenValue">—</code>
                <button type="button" onclick="rpCopyGenerated()"><?php echo ame_pair('কপি', 'Copy'); ?></button>
            </div>
        </div>
        <form action="process_reset_password.php" method="POST" class="rp-body" id="rpForm" autocomplete="off">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int) $target['id']; ?>">
            <div class="rp-group">
                <label><?php echo ame_pair('আপনার নিজের পাসওয়ার্ড', 'Your own password'); ?></label>
                <div class="rp-field-row">
                    <input type="password" name="actor_password" required autocomplete="current-password" placeholder="<?php echo htmlspecialchars(ame_l('নিজের লগইন পাসওয়ার্ড', 'Your login password'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="rp-gen-row">
                <button type="button" class="rp-gen-btn" onclick="rpGeneratePassword()"><?php echo ame_pair('এলোমেলো পাসওয়ার্ড তৈরি', 'Generate a password'); ?></button>
            </div>
            <div class="rp-group">
                <label><?php echo ame_pair('নতুন পাসওয়ার্ড', 'New password'); ?></label>
                <div class="rp-field-row">
                    <input type="password" id="rpPass1" name="new_password" placeholder="<?php echo htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'); ?>" required minlength="8" maxlength="72" autocomplete="new-password">
                    <button type="button" class="rp-eye" onclick="rpToggleShow()" aria-label="show">👁</button>
                </div>
            </div>
            <div class="rp-group">
                <label><?php echo ame_pair('নতুন পাসওয়ার্ড আবার', 'Confirm new password'); ?></label>
                <input type="password" id="rpPass2" name="new_password_confirm" placeholder="<?php echo htmlspecialchars(ame_l('পাসওয়ার্ড আবার লিখুন', 'Type the password again'), ENT_QUOTES, 'UTF-8'); ?>" required minlength="8" maxlength="72" autocomplete="new-password">
            </div>
            <label class="rp-force-row">
                <input type="checkbox" name="force_change" value="1" checked>
                <span><?php echo ame_pair('পরের লগইনে ইউজারকে নিজের পাসওয়ার্ড বসাতে বাধ্য করুন (সুপারিশকৃত)', 'Force this user to set their own password on next login (recommended)'); ?></span>
            </label>
            <p style="margin:0 0 10px;font-size:12px;color:#555;"><?php echo htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'); ?></p>
            <button type="submit" class="rp-submit"><?php echo ame_pair('পাসওয়ার্ড সেভ করুন', 'Save password'); ?></button>
        </form>
        <a href="users.php" class="rp-back"><?php echo ame_pair('তালিকায় ফিরুন', 'Back to list'); ?></a>
        <?php endif; ?>
    </div>
</div>
<script>
function rpGeneratePassword() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    var pass = '';
    var arr = new Uint32Array(12);
    if (window.crypto && window.crypto.getRandomValues) {
        window.crypto.getRandomValues(arr);
    } else {
        for (var k = 0; k < 12; k++) { arr[k] = Math.floor(Math.random() * 4294967295); }
    }
    for (var i = 0; i < 12; i++) { pass += chars[arr[i] % chars.length]; }
    pass = pass.slice(0, 10) + '7a';
    document.getElementById('rpPass1').value = pass;
    document.getElementById('rpPass2').value = pass;
    document.getElementById('rpPass1').type = 'text';
    var uname = (document.getElementById('rpUserName') || {}).textContent || '';
    document.getElementById('rpGenValue').textContent = 'ইউজারনেম: ' + uname.trim() + '\nপাসওয়ার্ড: ' + pass;
    document.getElementById('rpCopyWrap').classList.add('show');
}
function rpToggleShow() {
    var f = document.getElementById('rpPass1');
    f.type = (f.type === 'password') ? 'text' : 'password';
}
function rpCopyGenerated() {
    var v = document.getElementById('rpGenValue').textContent;
    if (v === '—') { return; }
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
    alert('কপি হয়েছে। / Copied.');
}
</script>
<?php include '../../partials/footer.php'; ?>
