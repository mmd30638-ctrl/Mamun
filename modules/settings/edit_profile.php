<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
ensure_user_schema($db);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$stmt = $db->prepare("SELECT id, username, phone, whatsapp, address, photo FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die('❌ ইউজার তথ্য পাওয়া যায়নি। <a href="../../logout.php">লগইন পেজে যান</a>');
}

$photo_url = user_photo_url($user['photo'] ?? null);
include '../../partials/header.php';
?>
<style>
    .ep-wrap { padding:15px; }
    .ep-card { background:#fff; border-radius:14px; max-width:480px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,.08); }
    .ep-topbar { display:flex; height:6px; }
    .ep-topbar span { flex:1; }
    .ep-c1 { background:var(--brand-navy); } .ep-c2 { background:var(--brand-accent); } .ep-c3 { background:var(--brand-gold); }
    .ep-header { text-align:center; padding:20px 15px 15px; }
    .ep-header h3 { margin:0; font-size:19px; color:#0D47A1; }
    .ep-header small { color:#666; font-size:12px; }
    .ep-body { padding:0 20px 20px; }
    .ep-avatar { width:90px; height:90px; object-fit:cover; display:block; margin:0 auto 15px; border-radius:50%; border:3px solid var(--brand-accent); }
    .ep-placeholder { width:90px; height:90px; display:flex; align-items:center; justify-content:center; margin:0 auto 15px; border-radius:50%; background:#E8F5E9; border:3px solid var(--brand-accent); font-size:40px; }
    .ep-name { text-align:center; font-weight:800; color:#111; margin-bottom:20px; }
    .ep-group { margin-bottom:14px; }
    .ep-group label { display:block; font-weight:600; font-size:13px; color:#111; margin-bottom:6px; }
    .ep-group input, .ep-group textarea { width:100%; box-sizing:border-box; padding:11px; border:1px solid #ccc; border-radius:8px; font-size:14px; background:#fafafa; }
    .ep-group input:focus, .ep-group textarea:focus { outline:none; border-color:var(--brand-accent); background:#fff; }
    .ep-file { border:1px dashed var(--brand-accent) !important; padding:10px !important; }
    .ep-submit { width:100%; padding:14px; border:0; border-radius:8px; background:var(--brand-gradient); color:#fff; font-weight:700; font-size:15px; cursor:pointer; }
    .ep-back { display:block; text-align:center; margin-top:14px; color:#666; text-decoration:none; font-size:13px; }
    .ep-note { color:#777; font-size:11px; margin-top:5px; }
</style>

<div class="ep-wrap">
    <div class="ep-card">
        <div class="ep-topbar"><span class="ep-c1"></span><span class="ep-c2"></span><span class="ep-c3"></span></div>
        <div class="ep-header">
            <h3>✏️ প্রোফাইল এডিট করুন</h3>
            <small>আপনার ব্যক্তিগত তথ্য আপডেট করুন</small>
        </div>
        <form action="process_profile_update.php" method="POST" enctype="multipart/form-data" class="ep-body">
            <?php echo csrf_field(); ?>

            <?php if ($photo_url !== ''): ?>
                <img class="ep-avatar" src="<?php echo htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="প্রোফাইল ছবি">
            <?php else: ?>
                <div class="ep-placeholder">👤</div>
            <?php endif; ?>

            <div class="ep-name"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></div>

            <div class="ep-group">
                <label for="photo">নতুন প্রোফাইল ছবি</label>
                <input id="photo" type="file" name="photo" class="ep-file" accept="image/jpeg,image/png,image/webp">
                <div class="ep-note">JPG, PNG বা WebP; সর্বোচ্চ ২ MB</div>
            </div>
            <div class="ep-group">
                <label for="phone">মোবাইল নম্বর (SMS)</label>
                <input id="phone" type="text" name="phone" maxlength="20" value="<?php echo htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="ep-group">
                <label for="whatsapp">WhatsApp নম্বর</label>
                <input id="whatsapp" type="text" name="whatsapp" maxlength="20" placeholder="01XXXXXXXXX" value="<?php echo htmlspecialchars($user['whatsapp'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="ep-note">পাসওয়ার্ড রিসেট ও গুরুত্বপূর্ণ OTP এখানে পাঠানো হবে।</div>
            </div>
            <div class="ep-group">
                <label for="address">ঠিকানা</label>
                <textarea id="address" name="address" rows="3" maxlength="255"><?php echo htmlspecialchars($user['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <button type="submit" class="ep-submit">✅ তথ্য সংরক্ষণ করুন</button>
        </form>
        <a href="profile.php" class="ep-back">⬅️ প্রোফাইলে ফিরে যান</a>
    </div>
</div>

<?php include '../../partials/footer.php'; ?>