<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
include __DIR__ . '/../../partials/header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
</style>

<div class="pnr-form-card">
    <div class="pnr-form-header">
        <h2>➕ নতুন পার্টনার যোগ করুন</h2>
    </div>
    <div class="pnr-form-body">
        <?php if (!empty($_SESSION['error'])): ?>
            <div style="background:#FFEBEE; color:#C62828; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13.5px;">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form action="actions/process_add.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="pnr-form-group">
                <label>পার্টনারের নাম *</label>
                <input type="text" name="name" required maxlength="100">
            </div>

            <div class="pnr-form-row">
                <div class="pnr-form-group">
                    <label>পার্টনারের ধরন *</label>
                    <select name="partner_type" required>
                        <option value="equity">ইক্যুইটি পার্টনার (মালিকানা আছে)</option>
                        <option value="profit_share">শুধু লাভের ভাগ</option>
                        <option value="silent">সাইলেন্ট পার্টনার</option>
                    </select>
                </div>
                <div class="pnr-form-group">
                    <label>শেয়ার শতাংশ (%) *</label>
                    <input type="number" step="0.01" min="0" max="100" name="share_percentage" required value="0">
                </div>
            </div>

            <div class="pnr-form-row">
                <div class="pnr-form-group">
                    <label>ফোন নম্বর</label>
                    <input type="text" name="phone" maxlength="20">
                </div>
                <div class="pnr-form-group">
                    <label>ইমেইল</label>
                    <input type="email" name="email" maxlength="100">
                </div>
            </div>

            <div class="pnr-form-group">
                <label>ঠিকানা</label>
                <input type="text" name="address" maxlength="255">
            </div>

            <div class="pnr-form-row">
                <div class="pnr-form-group">
                    <label>যোগদানের তারিখ</label>
                    <input type="date" name="join_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="pnr-form-group">
                    <label>স্ট্যাটাস</label>
                    <select name="status">
                        <option value="active">সক্রিয়</option>
                        <option value="inactive">নিষ্ক্রিয়</option>
                    </select>
                </div>
            </div>

            <div class="pnr-form-group">
                <label>নোট (ঐচ্ছিক)</label>
                <textarea name="notes" rows="3" maxlength="500"></textarea>
            </div>

            <button type="submit" class="pnr-submit-btn">✅ পার্টনার সংরক্ষণ করুন</button>
        </form>

        <div style="margin-top:16px; text-align:center;">
            <a href="index.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← পার্টনার তালিকায় ফিরে যান</a>
        </div>
    </div>
</div>
