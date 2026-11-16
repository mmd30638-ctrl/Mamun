<?php 
require_once '../../includes/config.php';
require_once '../../includes/invoice_functions.php';
admin_only();
include '../../partials/header.php'; 

$settings = get_invoice_settings($db);
?>
<style>
    .inv-wrap { padding: 15px; }
    .inv-card { background:#fff; border-radius:14px; max-width:480px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
    .inv-topbar { display:flex; height:6px; }
    .inv-topbar span { flex:1; }
    .inv-c1{background:var(--brand-navy);} .inv-c2{background:var(--brand-accent);} .inv-c3{background:var(--brand-gold);}
    .inv-header { text-align:center; padding:20px 15px 15px; }
    .inv-header h3 { margin:0; font-size:19px; color:#1B5E20; }
    .inv-header small { color:#666; font-size:12px; }
    .inv-body { padding:0 20px 20px; }
    .inv-group { margin-bottom:14px; }
    .inv-group label { display:block; font-weight:600; font-size:13px; color:#000; margin-bottom:6px; }
    .inv-group input[type=text], .inv-group input[type=number], .inv-group select { width:100%; padding:11px; border:1px solid #ccc; border-radius:8px; box-sizing:border-box; font-size:14px; background:#fafafa; }
    .inv-group input:focus, .inv-group select:focus { outline:none; border-color:var(--brand-accent); background:#fff; }
    .inv-check-row { display:flex; align-items:center; gap:8px; padding:8px 0; font-size:13px; color:#333; }
    .inv-check-row input { width:18px; height:18px; accent-color:var(--brand-accent); }
    .inv-auto { margin: 4px 0 16px; padding: 12px; border: 1px solid #D7DEE8; border-radius: 12px; background: #F4F7FB; }
    .inv-switch-row { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:0 0 10px; }
    .inv-switch-row strong { font-size:14px; color:#003F8F; }
    .inv-switch { position:relative; display:inline-flex; width:52px; height:30px; flex:0 0 52px; }
    .inv-switch input { position:absolute; opacity:0; width:0; height:0; }
    .inv-switch-ui { position:absolute; inset:0; border-radius:999px; background:#D7DEE8; cursor:pointer; transition:.2s; }
    .inv-switch-ui::after { content:""; position:absolute; top:3px; left:3px; width:24px; height:24px; border-radius:50%; background:#fff; box-shadow:0 1px 4px rgba(17,24,39,.2); transition:.2s; }
    .inv-switch input:checked + .inv-switch-ui { background:#04933A; }
    .inv-switch input:checked + .inv-switch-ui::after { transform:translateX(22px); }
    .inv-auto small { display:block; margin-top:6px; color:#667085; font-size:12px; line-height:1.4; }
    .inv-auto.is-off .inv-pct { opacity:.45; pointer-events:none; }
    .inv-submit { width:100%; padding:14px; border:none; border-radius:8px; background:var(--brand-gradient); color:#fff; font-weight:700; font-size:15px; cursor:pointer; margin-top:8px; }
    .inv-submit:active { background: var(--brand-navy-deep); }
    .inv-back { display:block; text-align:center; margin-top:14px; color:#666; text-decoration:none; font-size:13px; }
</style>

<div class="inv-wrap">
    <div class="inv-card">
        <div class="inv-topbar"><span class="inv-c1"></span><span class="inv-c2"></span><span class="inv-c3"></span></div>
        <div class="inv-header">
            <h3>📄 ইনভয়েস টেমপ্লেট সেটিংস</h3>
            <small>বিক্রয় রসিদের ডিজাইন কাস্টমাইজ করুন</small>
        </div>
        <form action="process_invoice_template.php" method="POST" class="inv-body">
            <?php echo csrf_field(); ?>
            <div class="inv-group">
                <label>ফুটার নোট (ধন্যবাদ বার্তা)</label>
                <input type="text" name="footer_note" value="<?php echo htmlspecialchars($settings['footer_note']); ?>">
            </div>
            <div class="inv-group">
                <label>কাগজের সাইজ</label>
                <select name="paper_width">
                    <option value="80mm" <?php echo $settings['paper_width'] === '80mm' ? 'selected' : ''; ?>>80mm (থার্মাল)</option>
                    <option value="58mm" <?php echo $settings['paper_width'] === '58mm' ? 'selected' : ''; ?>>58mm (ছোট থার্মাল)</option>
                    <option value="A4" <?php echo $settings['paper_width'] === 'A4' ? 'selected' : ''; ?>>A4 (সাধারণ প্রিন্টার)</option>
                </select>
            </div>
            <div class="inv-group">
                <label>অ্যাকসেন্ট কালার</label>
                <select name="accent_color">
                    <option value="green" <?php echo $settings['accent_color'] === 'green' ? 'selected' : ''; ?>>সবুজ</option>
                    <option value="black" <?php echo $settings['accent_color'] === 'black' ? 'selected' : ''; ?>>কালো</option>
                    <option value="red" <?php echo $settings['accent_color'] === 'red' ? 'selected' : ''; ?>>লাল</option>
                </select>
            </div>
            <div class="inv-auto" id="posDiscountAutoBox">
                <div class="inv-switch-row">
                    <strong>ছাড় পার্সেন্ট</strong>
                    <span><?php echo !empty($settings['pos_discount_auto']) ? 'চালু' : 'বন্ধ'; ?></span>
                </div>
                <div class="inv-group inv-pct" style="margin-bottom:0;">
                    <label>অটো মাইনাস</label>
                    <input type="text" value="১২%" readonly>
                </div>
                <small>চালু থাকলে প্রতি বিক্রয়ের মোট থেকে শতকরা ১২% অটো মাইনাস হয়। চালু বা বন্ধ শুধু মেনুবার থেকে হয়।</small>
            </div>
            <label class="inv-check-row">
                <input type="checkbox" name="show_discount" <?php echo $settings['show_discount'] ? 'checked' : ''; ?>> ডিসকাউন্ট লাইন দেখান
            </label>
            <label class="inv-check-row">
                <input type="checkbox" name="show_payment_method" <?php echo $settings['show_payment_method'] ? 'checked' : ''; ?>> পেমেন্ট মাধ্যম দেখান
            </label>
            <label class="inv-check-row">
                <input type="checkbox" name="show_address" <?php echo $settings['show_address'] ? 'checked' : ''; ?>> দোকানের ঠিকানা দেখান
            </label>
            <button type="submit" class="inv-submit">✅ টেমপ্লেট সেভ করুন</button>
        </form>
        <a href="dashboard_settings.php" class="inv-back">⬅️ ফিরে যান</a>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>