<?php
require_once '../../includes/config.php';
include '../../partials/header.php';
admin_only();

$is_english = current_lang() === 'en';
$id = (int)($_GET['id'] ?? 0);
$row = [
    'name' => '',
    'code' => '',
    'phone' => '',
    'address' => '',
    'is_active' => 1,
    'is_default' => 0,
];
$store_users = [];
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM stores WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($found) {
        $row = $found;
        $store_users = ame_store_users($db, $id);
    } else {
        $id = 0;
    }
}
?>
<style>
    .set-wrap { padding: 15px; }
    .set-card { background:#fff; border-radius:14px; max-width:520px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
    .set-topbar { display:flex; height:6px; }
    .set-topbar span { flex:1; }
    .set-c1{background:var(--brand-navy);} .set-c2{background:var(--brand-accent);} .set-c3{background:var(--brand-gold);}
    .set-header { text-align:center; padding:20px 15px 10px; }
    .set-header h3 { margin:0; font-size:19px; color:#1B5E20; }
    .set-body { padding:0 20px 20px; }
    .set-group { margin-bottom:14px; }
    .set-group label { display:block; font-weight:700; font-size:13px; margin-bottom:6px; }
    .set-group input, .set-group textarea { width:100%; padding:11px; border:1px solid #ccc; border-radius:8px; box-sizing:border-box; font-size:14px; background:#fafafa; }
    .set-check { display:flex; align-items:center; gap:8px; font-weight:700; }
    .set-login-box { margin:18px 0; padding:14px; border:1px dashed #90CAF9; border-radius:10px; background:#F3F9FF; }
    .set-login-box h4 { margin:0 0 6px; font-size:15px; color:#0D47A1; }
    .set-login-box p { margin:0 0 12px; font-size:12px; color:#546E7A; font-weight:600; }
    .set-login-list { margin:0 0 12px; padding:0; list-style:none; }
    .set-login-list li { display:flex; flex-wrap:wrap; gap:8px; align-items:center; padding:8px 0; border-bottom:1px solid #E3F2FD; font-size:13px; }
    .set-login-list a { margin-left:auto; font-weight:800; color:#0D47A1; text-decoration:none; }
    .set-submit { width:100%; padding:14px; border:none; border-radius:8px; background:var(--brand-gradient); color:#fff; font-weight:800; font-size:15px; cursor:pointer; }
    .set-back { display:block; text-align:center; margin-top:10px; color:#546E7A; font-weight:700; text-decoration:none; }
</style>
<div class="set-wrap">
    <div class="set-card">
        <div class="set-topbar"><span class="set-c1"></span><span class="set-c2"></span><span class="set-c3"></span></div>
        <div class="set-header">
            <h3><?php echo $id > 0
                ? ($is_english ? 'Edit store' : 'স্টোর এডিট')
                : ($is_english ? 'New store' : 'নতুন স্টোর'); ?></h3>
        </div>
        <?php if (isset($_GET['error'])): ?>
            <div style="background:#FFEBEE;color:#C62828;padding:12px 20px;margin:0 20px 12px;border-radius:8px;font-weight:700;">
                <?php echo htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <form class="set-body" method="post" action="process_store.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="set-group">
                <label><?php echo $is_english ? 'Store name' : 'স্টোরের নাম'; ?></label>
                <input type="text" name="name" required maxlength="150" value="<?php echo htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="set-group">
                <label><?php echo $is_english ? 'Short code' : 'সংক্ষিপ্ত কোড'; ?></label>
                <input type="text" name="code" required maxlength="20" value="<?php echo htmlspecialchars((string)$row['code'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="MAIN">
            </div>
            <div class="set-group">
                <label><?php echo $is_english ? 'Phone' : 'ফোন'; ?></label>
                <input type="text" name="phone" maxlength="30" value="<?php echo htmlspecialchars((string)($row['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="set-group">
                <label><?php echo $is_english ? 'Address' : 'ঠিকানা'; ?></label>
                <textarea name="address" rows="3"><?php echo htmlspecialchars((string)($row['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="set-group set-check">
                <input type="checkbox" name="is_active" value="1" <?php echo (int)$row['is_active'] === 1 ? 'checked' : ''; ?>>
                <?php echo $is_english ? 'Store is open' : 'স্টোর চালু আছে'; ?>
            </div>
            <div class="set-group set-check">
                <input type="checkbox" name="is_default" value="1" <?php echo (int)$row['is_default'] === 1 ? 'checked' : ''; ?>>
                <?php echo $is_english ? 'Default store after login' : 'লগইনের পর এই স্টোর খুলবে'; ?>
            </div>

            <div class="set-login-box">
                <h4><?php echo $is_english ? 'Store login' : 'এই স্টোরের লগইন'; ?></h4>
                <p><?php echo $is_english
                    ? 'Create a username and password here. That person can log in and will only see this store.'
                    : 'ইউজারনেম ও পাসওয়ার্ড এখানেই খুলুন। সেই ইউজার লগইন করলে শুধু এই স্টোর দেখবে।'; ?></p>
                <div class="set-group">
                    <label><?php echo $is_english ? 'Link to give them' : 'তাদেরকে এই লিংক দিন'; ?></label>
                    <input type="text" readonly value="<?php echo htmlspecialchars(ame_public_login_url(), ENT_QUOTES, 'UTF-8'); ?>" onclick="this.select()">
                </div>
                <?php if (count($store_users) > 0): ?>
                    <ul class="set-login-list">
                        <?php foreach ($store_users as $login): ?>
                            <li>
                                <strong><?php echo htmlspecialchars((string)$login['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars((string)$login['role'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <a href="reset_password.php?id=<?php echo (int)$login['id']; ?>"><?php echo $is_english ? 'Reset password' : 'পাসওয়ার্ড বদলান'; ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="set-group">
                    <label><?php echo $is_english ? 'New username' : 'নতুন ইউজারনেম'; ?></label>
                    <input type="text" name="login_username" maxlength="80" autocomplete="off" placeholder="<?php echo $is_english ? 'e.g. store2' : 'যেমন: store2'; ?>">
                </div>
                <div class="set-group">
                    <label><?php echo $is_english ? 'New password' : 'নতুন পাসওয়ার্ড'; ?></label>
                    <input type="password" name="login_password" minlength="6" autocomplete="new-password" placeholder="<?php echo $is_english ? 'At least 6 characters' : 'কমপক্ষে ৬ অক্ষর'; ?>">
                </div>
                <div class="set-group">
                    <label><?php echo $is_english ? 'Repeat password' : 'পাসওয়ার্ড আবার লিখুন'; ?></label>
                    <input type="password" name="login_password_confirm" minlength="6" autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="set-submit"><?php echo $is_english ? 'Save store' : 'স্টোর ও লগইন সেভ করুন'; ?></button>
            <a class="set-back" href="stores.php"><?php echo $is_english ? 'Back to store list' : 'স্টোর তালিকায় ফিরুন'; ?></a>
        </form>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
