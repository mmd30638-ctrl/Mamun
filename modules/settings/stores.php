<?php
require_once '../../includes/config.php';
include '../../partials/header.php';
admin_only();

$is_english = current_lang() === 'en';
$stores = ame_list_stores($db, false);
$current_id = current_store_id();
?>
<style>
    .set-wrap { padding: 15px; }
    .set-card { background:#fff; border-radius:14px; max-width:720px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
    .set-topbar { display:flex; height:6px; }
    .set-topbar span { flex:1; }
    .set-c1{background:var(--brand-navy);} .set-c2{background:var(--brand-accent);} .set-c3{background:var(--brand-gold);}
    .set-header { text-align:center; padding:20px 15px 10px; }
    .set-header h3 { margin:0; font-size:19px; color:#1B5E20; }
    .set-header small { color:#666; font-size:12px; }
    .set-nav { display:flex; gap:8px; overflow-x:auto; padding:0 20px 15px; }
    .set-nav a { flex-shrink:0; padding:8px 14px; border-radius:20px; background:#f4f4f4; color:#000; text-decoration:none; font-size:12px; font-weight:600; white-space:nowrap; border:1px solid #eee; }
    .set-nav a.active { background:var(--brand-accent); color:#fff; border-color:var(--brand-accent); }
    .st-body { padding:0 16px 20px; }
    .st-linkbox { background:#F3F9FF; border:1px dashed #90CAF9; border-radius:10px; padding:12px; margin-bottom:14px; }
    .st-linkbox strong { display:block; margin-bottom:6px; color:#0D47A1; }
    .st-linkbox input { width:100%; box-sizing:border-box; padding:10px; border:1px solid #90CAF9; border-radius:8px; font-weight:700; font-size:13px; }
    .st-linkbox small { display:block; margin-top:6px; color:#546E7A; font-weight:600; }
    .st-add { display:block; text-align:center; margin:0 0 14px; padding:12px; border-radius:10px; background:var(--brand-gradient); color:#fff; font-weight:800; text-decoration:none; }
    .st-table { width:100%; border-collapse:collapse; font-size:13px; }
    .st-table th, .st-table td { text-align:left; padding:10px 6px; border-bottom:1px solid #eee; }
    .st-table th { color:#64748B; font-size:11px; text-transform:uppercase; }
    .st-badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:800; }
    .st-on { background:#E8F5E9; color:#1B5E20; }
    .st-off { background:#FFEBEE; color:#C62828; }
    .st-now { background:#E3F2FD; color:#0D47A1; }
    .st-link { color:var(--brand-accent); font-weight:800; text-decoration:none; }
    .st-empty { text-align:center; color:#64748B; padding:28px 10px; }
    @media (max-width: 640px) {
        .st-table th:nth-child(3), .st-table td:nth-child(3) { display:none; }
    }
</style>
<div class="set-wrap">
    <div class="set-card">
        <div class="set-topbar"><span class="set-c1"></span><span class="set-c2"></span><span class="set-c3"></span></div>
        <div class="set-header">
            <h3>🏪 <?php echo $is_english ? 'Stores' : 'স্টোর / শাখা'; ?></h3>
            <small><?php echo $is_english
                ? 'Open a store, then create its username and password on the same page.'
                : 'নতুন স্টোর খুলুন। ইউজারনেম ও পাসওয়ার্ড সেই পাতাতেই দিন — আলাদা জায়গায় খোঁজার দরকার নেই।'; ?></small>
        </div>
        <div class="set-nav">
            <a href="dashboard_settings.php">🏢 <?php echo $is_english ? 'Shop info' : 'দোকানের তথ্য'; ?></a>
            <a href="stores.php" class="active">🏪 <?php echo $is_english ? 'Stores' : 'স্টোর'; ?></a>
            <a href="users.php">👥 <?php echo $is_english ? 'Users' : 'ইউজার'; ?></a>
        </div>
        <div class="st-body">
            <?php if (isset($_GET['saved'])): ?>
                <div style="background:#E8F5E9;color:#1B5E20;padding:12px;border-radius:8px;font-weight:700;margin-bottom:12px;">
                    ✅ <?php echo $is_english ? 'Store saved.' : 'স্টোর সেভ হয়েছে।'; ?>
                    <?php if (!empty($_GET['login'])): ?>
                        <div style="margin-top:6px;">
                            <?php echo $is_english ? 'Login username:' : 'লগইন ইউজারনেম:'; ?>
                            <strong><?php echo htmlspecialchars((string)$_GET['login'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div style="background:#FFEBEE;color:#C62828;padding:12px;border-radius:8px;font-weight:700;margin-bottom:12px;">
                    <?php echo htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <div class="st-linkbox">
                <strong><?php echo $is_english ? 'Give new users this link' : 'নতুন ইউজারকে এই লিংক দিন'; ?></strong>
                <input type="text" readonly value="<?php echo htmlspecialchars(ame_public_login_url(), ENT_QUOTES, 'UTF-8'); ?>" onclick="this.select()">
                <small><?php echo $is_english
                    ? 'Same login page for every store. They enter the username and password you created for that store.'
                    : 'সব স্টোরের লিংক একই। তারা যে ইউজারনেম-পাসওয়ার্ড আপনি খুলে দিয়েছেন, সেটা দিয়ে ঢুকবে।'; ?></small>
            </div>
            <a class="st-add" href="store_edit.php">➕ <?php echo $is_english ? 'Add a store' : 'নতুন স্টোর যোগ করুন'; ?></a>
            <?php if (count($stores) === 0): ?>
                <p class="st-empty"><?php echo $is_english ? 'No stores yet.' : 'এখনো কোনো স্টোর নেই।'; ?></p>
            <?php else: ?>
                <table class="st-table">
                    <thead>
                        <tr>
                            <th><?php echo $is_english ? 'Store' : 'স্টোর'; ?></th>
                            <th><?php echo $is_english ? 'Code' : 'কোড'; ?></th>
                            <th><?php echo $is_english ? 'Phone' : 'ফোন'; ?></th>
                            <th><?php echo $is_english ? 'Login' : 'লগইন'; ?></th>
                            <th><?php echo $is_english ? 'Status' : 'অবস্থা'; ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stores as $store): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars((string)$store['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if ((int)$store['is_default'] === 1): ?>
                                    <span class="st-badge st-now"><?php echo $is_english ? 'Default' : 'ডিফল্ট'; ?></span>
                                <?php endif; ?>
                                <?php if ((int)$store['id'] === $current_id): ?>
                                    <span class="st-badge st-now"><?php echo $is_english ? 'Open now' : 'এখন খোলা'; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string)$store['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($store['phone'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php
                                $store_logins = ame_store_users($db, (int)$store['id']);
                                if (count($store_logins) === 0) {
                                    echo $is_english ? 'None yet' : 'এখনো নেই';
                                } else {
                                    echo htmlspecialchars(implode(', ', array_column($store_logins, 'username')), ENT_QUOTES, 'UTF-8');
                                }
                            ?></td>
                            <td>
                                <span class="st-badge <?php echo (int)$store['is_active'] === 1 ? 'st-on' : 'st-off'; ?>">
                                    <?php echo (int)$store['is_active'] === 1
                                        ? ($is_english ? 'Active' : 'চালু')
                                        : ($is_english ? 'Off' : 'বন্ধ'); ?>
                                </span>
                            </td>
                            <td><a class="st-link" href="store_edit.php?id=<?php echo (int)$store['id']; ?>"><?php
                                echo count($store_logins) === 0
                                    ? ($is_english ? 'Open login' : 'লগইন খুলুন')
                                    : ($is_english ? 'Edit' : 'এডিট');
                            ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
