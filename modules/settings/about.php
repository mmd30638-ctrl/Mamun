<?php 
require_once '../../includes/config.php';
include '../../partials/header.php'; 
?>
<style>
    .abt-wrap { padding: 15px; }
    .abt-card { background:#fff; border-radius:14px; max-width:460px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); text-align:center; }
    .abt-topbar { display:flex; height:6px; }
    .abt-topbar span { flex:1; }
    .abt-c1{background:var(--brand-navy);} .abt-c2{background:var(--brand-accent);} .abt-c3{background:var(--brand-gold);}
    .abt-body { padding:30px 20px; }
    .abt-icon { font-size:52px; }
    .abt-logo { display:block; width:min(86vw, 280px); height:auto; max-height:120px; object-fit:contain; margin:0 auto 8px; }
    .abt-name { color:#000; margin:10px 0 4px; font-size:20px; font-weight:800; }
    .abt-version { color:var(--brand-accent); font-weight:700; font-size:13px; }
    .abt-divider { border:none; border-top:1px solid #eee; margin:18px 0; }
    .abt-desc { font-size:13px; color:#666; }
    .abt-info-box { text-align:left; background:#fafafa; padding:15px; border-radius:10px; margin-top:18px; font-size:12px; border:1px solid #eee; }
    .abt-info-box p { margin:6px 0; }
    .abt-back { display:block; text-decoration:none; margin-top:20px; padding:13px; border-radius:8px; background:var(--brand-gradient); color:#fff; font-weight:700; font-size:14px; }
    .abt-back:active { background: var(--brand-navy-deep); }
</style>

<div class="abt-wrap">
    <div class="abt-card">
        <div class="abt-topbar"><span class="abt-c1"></span><span class="abt-c2"></span><span class="abt-c3"></span></div>
        <div class="abt-body">
            <img class="abt-logo" src="<?php echo htmlspecialchars(shop_logo_url('/assets/img/ma-logo.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(shop_name()); ?>">
            <h2 class="abt-name"><?php echo htmlspecialchars(shop_name()); ?></h2>
            <p class="abt-version">পার্টস ও ওয়ার্কশপ · v<?php echo defined('AME_APP_VERSION') ? htmlspecialchars(AME_APP_VERSION) : '-'; ?></p>
            <hr class="abt-divider">
            <p class="abt-desc"><?php echo htmlspecialchars(shop_tagline()); ?> — যন্ত্রাংশ বিক্রি, পার্টস স্টক, ক্রয়, জব কার্ড, কোটেশন ও হিসাব এক সিস্টেমে। শুধু ওয়ার্কশপ নয়, পূর্ণাঙ্গ পার্টস ব্যবসাও।</p>
            <div class="abt-info-box">
                <p>✅ <b>লাইসেন্স:</b> Commercial (Full Access)</p>
                <p>✅ <b>টেকনোলজি:</b> PHP, MySQL, Vanilla JS, PWA</p>
                <p>✅ <b>যোগাযোগ:</b> <?php echo htmlspecialchars($shop['shop_phone'] ?? '-'); ?></p>
            </div>
            <a href="../../index.php" class="abt-back">🏠 ড্যাশবোর্ডে ফিরে যান</a>
        </div>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>