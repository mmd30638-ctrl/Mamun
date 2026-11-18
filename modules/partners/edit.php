<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
include __DIR__ . '/../../partials/header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM partners WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$partner = $stmt->get_result()->fetch_assoc();

if (!$partner) {
    die('❌ পার্টনার খুঁজে পাওয়া যায়নি। <a href="index.php">তালিকায় ফিরুন</a>');
}
?>
<style>
    .pnr-form-card {
        max-width: 560px; margin: 15px auto; background:#fff; border-radius:18px;
        box-shadow: 0 12px 40px rgba(11,30,77,0.12); overflow:hidden;
    }
    .pnr-form-header {
        background: linear-gradient(135deg, #0B1E4D, #0091EA); color:#fff;
        padding: 22px 26px;
    }
    .pnr-form-header h2 { margin:0; font-size:19px; }
    .pnr-form-body { padding: 24px 26px 28px; }
    .pnr-form-group { margin-bottom: 16px; }
    .pnr-form-group label { display:block; font-weight:700; font-size:13px; color:#0B1E4D; margin-bottom:6px; }
    .pnr-form-group input, .pnr-form-group select, .pnr-form-group textarea {
        width:100%; padding:12px 14px; border:1.5px solid #CFE3F7; border-radius:10px;
        font-size:14.5px; background:#fbfdff; box-sizing:border-box;
    }
    .pnr-form-group input:focus, .pnr-form-group select:focus, .pnr-form-group textarea:focus {
        outline:none; border-color:#0091EA; box-shadow:0 0 0 4px rgba(0,145,234,0.12); background:#fff;
    }
    .pnr-form-row { display:flex; gap:12px; }
    .pnr-form-row > div { flex:1; }
    .pnr-submit-btn {
        width:100%; padding:14px; border:none; border-radius:12px;
        background: linear-gradient(135deg, #0B1E4D, #0091EA); color:#fff;
        font-weight:800; font-size:15px; cursor:pointer; margin-top:8px;
        box-shadow: 0 6px 18px rgba(11,30,77,0.25);
    }
    .pnr-exit-box {
        margin-top: 18px; padding: 16px; background:#FFF3E0; border:1px solid #FFE0B2;
        border-radius: 12px;
    }
</style>

<div class="pnr-form-card">
    <div class="pnr-form-header">
        <h2>✏️ পার্টনার তথ্য এডিট করুন</h2>
    </div>
    <div class="pnr-form-body">
        <?php if (!empty($_SESSION['error'])): ?>
            <div style="background:#FFEBEE; color:#C62828; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13.5px;">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form action="actions/process_edit.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="id" value="<?php echo (int)$partner['id']; ?>">

            <div class="pnr-form-group">
                <label>পার্টনারের নাম *</label>
                <input type="text" name="name" required maxlength="100" value="<?php echo htmlspecialchars($partner['name']); ?>">
            </div>

            <div class="pnr-form-row">
                <div class="pnr-form-group">
                    <label>পার্টনারের ধরন *</label>
                    <select name="partner_type" required>
                        <?php foreach (['equity' => 'ইক্যুইটি পার্টনার (মালিকানা আছে)', 'profit_share' => 'শুধু লাভের ভাগ', 'silent' => 'সাইলেন্ট পার্টনার'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo $partner['partner_type'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pnr-form-group">
                    <label>শেয়ার শতাংশ (%) *</label>
                    <input type="number" step="0.01" min="0" max="100" name="share_percentage" required value="<?php echo htmlspecialchars($partner['share_percentage']); ?>">
                </div>
            </div>

            <div class="pnr-form-row">
                <div class="pnr-form-group">
                    <label>ফোন নম্বর</label>
                    <input type="text" name="phone" maxlength="20" value="<?php echo htmlspecialchars($partner['phone'] ?? ''); ?>">
                </div>
                <div class="pnr-form-group">
                    <label>ইমেইল</label>
                    <input type="email" name="email" maxlength="100" value="<?php echo htmlspecialchars($partner['email'] ?? ''); ?>">
                </div>
            </div>

            <div class="pnr-form-group">
                <label>ঠিকানা</label>
                <input type="text" name="address" maxlength="255" value="<?php echo htmlspecialchars($partner['address'] ?? ''); ?>">
            </div>

            <div class="pnr-form-row">
                <div class="pnr-form-group">
                    <label>যোগদানের তারিখ</label>
                    <input type="date" name="join_date" value="<?php echo htmlspecialchars($partner['join_date'] ?? ''); ?>">
                </div>
                <div class="pnr-form-group">
                    <label>স্ট্যাটাস</label>
                    <select name="status">
                        <?php foreach (['active' => 'সক্রিয়', 'inactive' => 'নিষ্ক্রিয়', 'exited' => 'প্রস্থান করেছেন'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo $partner['status'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="pnr-form-group">
                <label>নোট (ঐচ্ছিক)</label>
                <textarea name="notes" rows="3" maxlength="500"><?php echo htmlspecialchars($partner['notes'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="pnr-submit-btn">✅ পরিবর্তন সংরক্ষণ করুন</button>
        </form>

        <div class="pnr-exit-box">
            <strong style="color:#E65100; font-size:13px;">⚠️ প্রস্থানের তারিখ</strong>
            <p style="font-size:12.5px; color:#8D6E00; margin:6px 0 10px;">পার্টনার ব্যবসা ছেড়ে দিলে এখানে তারিখ দিন — উপরের স্ট্যাটাস "প্রস্থান করেছেন" নির্বাচন করতে ভুলবেন না।</p>
            <form action="actions/process_edit.php" method="POST" style="display:flex; gap:8px;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="id" value="<?php echo (int)$partner['id']; ?>">
                <input type="hidden" name="name" value="<?php echo htmlspecialchars($partner['name']); ?>">
                <input type="hidden" name="partner_type" value="<?php echo htmlspecialchars($partner['partner_type']); ?>">
                <input type="hidden" name="share_percentage" value="<?php echo htmlspecialchars($partner['share_percentage']); ?>">
                <input type="hidden" name="phone" value="<?php echo htmlspecialchars($partner['phone'] ?? ''); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($partner['email'] ?? ''); ?>">
                <input type="hidden" name="address" value="<?php echo htmlspecialchars($partner['address'] ?? ''); ?>">
                <input type="hidden" name="join_date" value="<?php echo htmlspecialchars($partner['join_date'] ?? ''); ?>">
                <input type="hidden" name="status" value="exited">
                <input type="hidden" name="notes" value="<?php echo htmlspecialchars($partner['notes'] ?? ''); ?>">
                <input type="date" name="exit_date" value="<?php echo htmlspecialchars($partner['exit_date'] ?? date('Y-m-d')); ?>" style="flex:1; padding:10px; border-radius:8px; border:1px solid #FFE0B2;">
                <button type="submit" style="background:#E65100; color:#fff; border:none; padding:10px 16px; border-radius:8px; font-weight:700; cursor:pointer;">প্রস্থান নিশ্চিত করুন</button>
            </form>
        </div>

        <div style="margin-top:16px; text-align:center;">
            <a href="index.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← পার্টনার তালিকায় ফিরে যান</a>
        </div>
    </div>
</div>
