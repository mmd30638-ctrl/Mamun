<?php 
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
ensure_user_schema($db);
include '../../partials/header.php'; 

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user = null;
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$user) {
    die('❌ ইউজার তথ্য পাওয়া যায়নি। <a href="../../logout.php">লগইন পেজে যান</a>');
}

if (function_exists('ame_attach_role_label')) {
    $user = ame_attach_role_label($db, $user);
}
$role_label = $user['role_label'] ?? strtoupper((string)$user['role']);
$joined = !empty($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : '-';
?>
<style>
    .prf-wrap { padding: 15px; }
    .prf-card { background:#fff; border-radius:14px; max-width:420px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); text-align:center; }
    .prf-topbar { display:flex; height:6px; }
    .prf-topbar span { flex:1; }
    .prf-c1{background:var(--brand-navy);} .prf-c2{background:var(--brand-accent);} .prf-c3{background:var(--brand-gold);}
    .prf-body { padding:25px 20px; }
    .prf-avatar { width:90px; height:90px; border-radius:50%; object-fit:cover; margin:0 auto 15px; display:block; border:3px solid var(--brand-accent); }
    .prf-avatar-placeholder { width:90px; height:90px; border-radius:50%; background:#E8F5E9; display:flex; align-items:center; justify-content:center; font-size:40px; margin:0 auto 15px; border:3px solid var(--brand-accent); }
    .prf-name { color:#000; margin:0; font-size:19px; font-weight:800; }
    .prf-role-badge { display:inline-block; margin-top:8px; padding:4px 14px; border-radius:14px; font-size:12px; font-weight:700; background:#E8F5E9; color:#1B5E20; }
    .prf-divider { border:none; border-top:1px solid #eee; margin:20px 0; }
    .prf-info-list { text-align:left; font-size:14px; color:#333; }
    .prf-info-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f4f4f4; }
    .prf-info-row span:first-child { color:#777; }
    .prf-info-row span:last-child { font-weight:600; color:#000; }
    .prf-status-active { color:var(--brand-accent); font-weight:700; }
    .prf-btn { display:block; text-decoration:none; padding:13px; border-radius:8px; font-weight:700; font-size:14px; margin-top:20px; }
    .prf-btn-primary { background:var(--brand-gradient); color:#fff; }
    .prf-btn-primary:active { background: var(--brand-navy-deep); }
    .prf-btn-danger { color:var(--brand-danger); margin-top:14px; font-size:13px; }
</style>

<div class="prf-wrap">
    <div class="prf-card">
        <div class="prf-topbar"><span class="prf-c1"></span><span class="prf-c2"></span><span class="prf-c3"></span></div>
        <div class="prf-body">
            <?php if (!empty($user['photo'])): ?>
            <img class="prf-avatar" src="../../assets/img/<?php echo htmlspecialchars($user['photo']); ?>">
            <?php else: ?>
            <div class="prf-avatar-placeholder">👤</div>
            <?php endif; ?>

            <h3 class="prf-name"><?php echo htmlspecialchars(strtoupper($user['username'])); ?></h3>
            <span class="prf-role-badge"><?php echo htmlspecialchars($role_label); ?></span>

            <hr class="prf-divider">

            <div class="prf-info-list">
                <div class="prf-info-row"><span>সিস্টেম আইডি</span><span>#<?php echo (int)$user['id']; ?></span></div>
                <div class="prf-info-row"><span>মোবাইল নম্বর</span><span><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></span></div>
                <div class="prf-info-row"><span>ঠিকানা</span><span><?php echo htmlspecialchars($user['address'] ?? '-'); ?></span></div>
                <div class="prf-info-row"><span>যোগদানের তারিখ</span><span><?php echo $joined; ?></span></div>
                <div class="prf-info-row"><span>স্ট্যাটাস</span><span class="prf-status-active">Active</span></div>
            </div>

            <a href="change_password.php" class="prf-btn prf-btn-primary">🔑 পাসওয়ার্ড পরিবর্তন করুন</a>
            <a href="../../logout.php" class="prf-btn prf-btn-danger">🚪 লগআউট করুন</a>
        </div>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>