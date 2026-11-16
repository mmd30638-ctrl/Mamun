<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/account_functions.php';
require_once __DIR__ . '/../../includes/pf_functions.php';
admin_only();
ensure_pf_schema($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ⭐ একীভূতকরণ: মূল accounts টেবিল (দোকান + ব্যক্তিগত — সব একসাথে)
$accounts = $db->query("SELECT * FROM accounts ORDER BY status DESC, balance DESC")->fetch_all(MYSQLI_ASSOC);
$members = $db->query("SELECT * FROM pf_family_members WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../../partials/header.php';

$type_labels = ['cash' => '💵 নগদ', 'bank' => '🏦 ব্যাংক অ্যাকাউন্ট', 'mobile_banking' => '📱 মোবাইল ব্যাংকিং', 'card' => '💳 কার্ড', 'savings' => '🐷 সঞ্চয়', 'other' => '📦 অন্যান্য'];
?>
<style>
    :root { --pf-navy:#0B1E4D; }
    .pf-wrap { padding: 14px; }
    .pf-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
    .pf-header h2 { margin:0; color:var(--pf-navy); font-size:19px; }
    .pf-card { background:#fff; border-radius:16px; padding:16px; box-shadow:0 4px 18px rgba(11,30,77,0.08); margin-bottom:12px; }
    .pf-field { margin-bottom:12px; }
    .pf-field label { display:block; font-size:11px; font-weight:700; color:#334155; margin-bottom:5px; }
    .pf-field input, .pf-field select { width:100%; padding:10px 12px; border:1.5px solid #E2E8F0; border-radius:9px; font-size:13px; box-sizing:border-box; }
    .pf-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    @media (max-width:500px) { .pf-grid-2 { grid-template-columns:1fr; } }
    .pf-submit-btn { width:100%; padding:13px; border:none; border-radius:11px; background: linear-gradient(135deg,var(--pf-navy),#0091EA); color:#fff; font-weight:800; cursor:pointer; }
    .pf-acc-item { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #F1F5F9; font-size:13px; }
</style>

<div class="pf-wrap">
    <div class="pf-header"><h2>🏦 অ্যাকাউন্ট পরিচালনা</h2><a href="dashboard.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← ড্যাশবোর্ড</a></div>
    <p style="color:#94A3B8; font-size:12px; margin:-8px 0 14px;">⭐ এখানকার সব অ্যাকাউন্টই দোকানের ক্রয়-বিক্রয়/লোনেও ব্যবহৃত হয় — একই ব্যক্তির একই টাকা, তাই সবকিছু একসাথে দেখা যাচ্ছে।</p>

    <?php if (!empty($_SESSION['error'])): ?>
        <div style="background:#FFEBEE; color:#C62828; padding:12px 16px; border-radius:10px; margin-bottom:14px; font-size:13px;"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['success'])): ?>
        <div style="background:#E8F5E9; color:#0B8043; padding:12px 16px; border-radius:10px; margin-bottom:14px; font-size:13px;"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="pf-card">
        <h3 style="margin:0 0 12px; color:var(--pf-navy); font-size:14px;">➕ নতুন অ্যাকাউন্ট যোগ করুন</h3>
        <form action="actions/save_account.php" method="POST">
            <?php echo csrf_field(); ?>
            <div class="pf-grid-2">
                <div class="pf-field"><label>অ্যাকাউন্টের নাম *</label><input type="text" name="name" required placeholder="যেমন: BRAC Bank"></div>
                <div class="pf-field">
                    <label>ধরন</label>
                    <select name="type">
                        <?php foreach ($type_labels as $key => $label): ?>
                            <option value="<?php echo esc_html($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="pf-grid-2">
                <div class="pf-field"><label>প্রাথমিক ব্যালেন্স</label><input type="number" step="0.01" name="opening_balance" value="0"></div>
                <div class="pf-field">
                    <label>মালিক (ঐচ্ছিক)</label>
                    <select name="owner_member_id">
                        <option value="">-- নির্বাচন করুন --</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="pf-submit-btn">✅ অ্যাকাউন্ট সংরক্ষণ করুন</button>
        </form>
    </div>

    <div class="pf-card">
        <h3 style="margin:0 0 8px; color:var(--pf-navy); font-size:14px;">📋 সব অ্যাকাউন্ট (দোকান + ব্যক্তিগত)</h3>
        <?php if (empty($accounts)): ?>
            <p style="text-align:center; color:#94A3B8; font-size:12.5px; padding:16px 0;">এখনো কোনো অ্যাকাউন্ট নেই।</p>
        <?php else: foreach ($accounts as $a): ?>
        <div class="pf-acc-item">
            <span><?php echo $type_labels[$a['account_type']] ?? ('📦 ' . htmlspecialchars($a['account_type'])); ?> <?php echo htmlspecialchars($a['account_name']); ?><?php if (($a['status'] ?? 'active') !== 'active'): ?> <small style="color:#C62828;">(নিষ্ক্রিয়)</small><?php endif; ?></span>
            <strong style="color:var(--pf-navy);"><?php echo ame_balance_label((float)$a['balance']); ?></strong>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="pf-card">
        <h3 style="margin:0 0 12px; color:var(--pf-navy); font-size:14px;">👨‍👩‍👧 পরিবারের সদস্য যোগ করুন</h3>
        <form action="actions/save_member.php" method="POST" style="display:flex; gap:8px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="text" name="name" required placeholder="নাম" style="flex:2; padding:10px; border:1.5px solid #E2E8F0; border-radius:9px;">
            <select name="role" style="flex:1; padding:10px; border:1.5px solid #E2E8F0; border-radius:9px;">
                <option value="father">বাবা</option>
                <option value="mother">মা</option>
                <option value="brother">ভাই</option>
                <option value="sister">বোন</option>
                <option value="other">অন্যান্য</option>
            </select>
            <button type="submit" style="background:var(--pf-navy); color:#fff; border:none; padding:0 16px; border-radius:9px; font-weight:800;">+</button>
        </form>
        <?php if (!empty($members)): ?>
            <div style="margin-top:12px;">
                <?php foreach ($members as $m): ?>
                    <span style="display:inline-block; background:#EEF2FF; color:var(--pf-navy); padding:5px 12px; border-radius:20px; font-size:12px; margin:3px;"><?php echo htmlspecialchars($m['name']); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
