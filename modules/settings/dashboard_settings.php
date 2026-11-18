<?php
require_once '../../includes/config.php';
include '../../partials/header.php';
admin_only();

$btFile = dirname(__DIR__, 2) . '/includes/brand_type.php';
if (is_file($btFile)) {
    require_once $btFile;
}
if (function_exists('ame_brand_type_ensure')) {
    ame_brand_type_ensure($db ?? null);
}
$brandType = function_exists('ame_brand_type_get')
    ? ame_brand_type_get($db ?? null)
    : (function_exists('ame_brand_type_defaults') ? ame_brand_type_defaults() : []);
$brandLayers = function_exists('ame_brand_layers') ? ame_brand_layers() : [];
$brandFonts = function_exists('ame_brand_fonts') ? ame_brand_fonts() : [];
$brandPresets = function_exists('ame_brand_color_presets') ? ame_brand_color_presets() : [];
$fontFamilies = [];
foreach ($brandFonts as $id => $meta) {
    $fontFamilies[$id] = $meta['family'];
}

if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}
?>
<link rel="stylesheet" href="/assets/css/settings-display.806.css">
<script src="/assets/js/device-display.js?v=8.06"></script>
<script>
window.AME_BRAND_FONTS = <?php echo json_encode($fontFamilies, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<?php foreach (function_exists('ame_brand_type_font_hrefs') ? ame_brand_type_font_hrefs($brandType) : [] as $href): ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
<?php endforeach; ?>
<?php foreach ($brandFonts as $meta): ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($meta['href'], ENT_QUOTES, 'UTF-8'); ?>">
<?php endforeach; ?>

<div class="set-wrap">
    <div class="set-card">
        <div class="set-topbar"><span class="set-c1"></span><span class="set-c2"></span><span class="set-c3"></span></div>
        <div class="set-header">
            <h3><?php echo ame_pair('ড্যাশবোর্ড ও ব্র্যান্ডিং সেটিংস', 'Dashboard & branding'); ?></h3>
            <small><?php echo ame_pair('প্রিমিয়াম SaaS ডিজাইন সিস্টেম — সব পেজের লেখা, ফন্ট, রঙ ও টাইপ এডিট', 'Premium SaaS design system — type, color and copy on every page'); ?></small>
        </div>
        <?php if (isset($_GET['updated'])): ?>
        <div class="set-ok">✅ <?php echo ame_pair('সেভ হয়েছে। সব পেজের লেখা নতুন টাইপ-ওএস অনুসারে বসবে।', 'Saved. Every page now follows the type OS.'); ?></div>
        <?php endif; ?>

        <nav class="set-nav">
            <a href="dashboard_settings.php" class="active"><?php echo ame_pair('দোকানের তথ্য', 'Shop info'); ?></a>
            <a href="pos_title.php"><?php echo ame_pair('POS শিরোনাম', 'POS title'); ?></a>
            <a href="stores.php"><?php echo ame_pair('স্টোর', 'Stores'); ?></a>
            <a href="lang_settings.php"><?php echo ame_pair('ভাষা', 'Language'); ?></a>
            <a href="scrolling_text.php"><?php echo ame_pair('চলমান লেখা', 'Ticker'); ?></a>
            <a href="countdown.php"><?php echo ame_pair('কাউন্টডাউন', 'Countdown'); ?></a>
            <a href="banners.php"><?php echo ame_pair('ব্যানার', 'Banners'); ?></a>
            <a href="theme.php"><?php echo ame_pair('প্রজেক্টের রং', 'Theme color'); ?></a>
            <a href="trust.php"><?php echo ame_pair('ট্রাস্ট ও সুরক্ষা', 'Trust'); ?></a>
            <a href="role_permissions.php"><?php echo ame_pair('পারমিশন', 'Permissions'); ?></a>
            <a href="sms_config.php"><?php echo ame_pair('সিম এসএমএস', 'Personal SIM SMS'); ?></a>
            <a href="whatsapp_config.php"><?php echo ame_pair('WhatsApp OTP', 'WhatsApp OTP'); ?></a>
            <a href="sms_log.php"><?php echo ame_pair('SMS লগ', 'SMS log'); ?></a>
            <a href="users.php"><?php echo ame_pair('ইউজার', 'Users'); ?></a>
            <a href="profile.php"><?php echo ame_pair('প্রোফাইল', 'Profile'); ?></a>
            <a href="change_password.php"><?php echo ame_pair('পাসওয়ার্ড', 'Password'); ?></a>
            <a href="about.php"><?php echo ame_pair('সম্পর্কে', 'About'); ?></a>
            <a href="tools.php"><?php echo ame_pair('টুলস', 'Tools'); ?></a>
            <a href="activity_logs.php"><?php echo ame_pair('কার্যক্রম লগ', 'Activity'); ?></a>
            <a href="project_care.php"><?php echo ame_pair('প্রজেক্ট রক্ষা', 'Project care'); ?></a>
            <a href="money_guard.php"><?php echo ame_pair('টাকা মিল', 'Money check'); ?></a>
            <a href="system_health.php"><?php echo ame_pair('সিস্টেম হেলথ', 'Health'); ?></a>
            <a href="backup.php" class="danger"><?php echo ame_pair('ব্যাকআপ', 'Backup'); ?></a>
        </nav>
        <form action="process_update.php" method="POST" enctype="multipart/form-data" class="set-body">
            <?php echo csrf_field(); ?>

            <div class="set-banner-box">
                <label><?php echo ame_pair('প্রতিষ্ঠানের লোগো', 'Company logo'); ?></label>
                <?php if (!empty($shop['shop_logo'])): ?>
                    <img src="../../assets/img/<?php echo htmlspecialchars($shop['shop_logo']); ?>" alt="logo">
                <?php else: ?>
                    <div class="set-banner-empty"><?php echo ame_pair('লোগো দেওয়া নেই — ডিফল্ট আইকন দেখাবে', 'No logo yet — default icon will show'); ?></div>
                <?php endif; ?>
            </div>

            <div class="set-group">
                <label><?php echo ame_pair('নতুন লোগো আপলোড করুন', 'Upload a new logo'); ?></label>
                <input type="file" name="shop_logo" class="set-file-input" accept="image/*">
                <small><?php echo ame_pair('লগইন, হোম ও ইনভয়েসে এই লোগো দেখাবে। ফোনের হোম-স্ক্রিন আইকন আলাদা স্কয়ার ফাইল (icon-192/512)।', 'This logo shows on login, home and invoices. Phone home-screen icons stay the square icon-192/512 files.'); ?></small>
            </div>

            <hr class="set-divider">

            <div class="set-banner-box">
                <label><?php echo ame_pair('বর্তমান ড্যাশবোর্ড ব্যানার', 'Current dashboard banner'); ?></label>
                <?php if (!empty($shop['shop_banner'])): ?>
                    <img src="../../assets/img/<?php echo htmlspecialchars($shop['shop_banner']); ?>" alt="banner">
                <?php else: ?>
                    <div class="set-banner-empty"><?php echo ame_pair('ব্যানার ইমেজ নেই', 'No banner image'); ?></div>
                <?php endif; ?>
            </div>

            <div class="set-group">
                <label><?php echo ame_pair('নতুন ব্যানার আপলোড করুন', 'Upload a new banner'); ?></label>
                <input type="file" name="shop_banner" class="set-file-input" accept="image/*">
                <small><?php echo ame_pair('পরামর্শ: ১০০০ × ৪০০ পিক্সেল ভালো দেখাবে।', 'Tip: 1000 × 400 px looks best.'); ?></small>
            </div>

            <hr class="set-divider">

            <div class="set-group">
                <label><?php echo ame_pair('দোকানের নাম', 'Shop name'); ?></label>
                <input type="text" name="shop_name" value="<?php echo htmlspecialchars((string)($shop['shop_name'] ?? '')); ?>" required>
                <small><?php echo ame_pair('যেমন: মা ইঞ্জিনিয়ারিং ওয়ার্কশপ এন্ড মামুন ট্রেডার্স', 'Example: Ma Engineering Workshop and Mamun Traders'); ?></small>
            </div>

            <div class="set-group">
                <label><?php echo ame_pair('ট্যাগলাইন (পরিচয়)', 'Tagline'); ?></label>
                <input type="text" name="shop_tagline" value="<?php echo htmlspecialchars($shop['shop_tagline'] ?? ''); ?>" placeholder="স্পেয়ার পার্টস ব্যবসা ও ইঞ্জিনিয়ারিং ওয়ার্কশপ">
                <small><?php echo ame_pair('লগইন, সাইডবার ও অ্যাবাউট পেজে নামের নিচে দেখাবে।', 'Shows under the name on login, sidebar and About.'); ?></small>
            </div>

            <section id="ame-type-studio" class="ame-atelier" data-ame-type-os>
                <header class="ame-atelier__head">
                    <p class="ame-atelier__kicker"><?php echo ame_pair('টাইপ ওএস', 'Type OS'); ?></p>
                    <h4><?php echo ame_pair('সব পেজের লেখা — পূর্ণ এডিট সিস্টেম', 'Every page — full typography system'); ?></h4>
                    <p><?php echo ame_pair('ওয়ার্ডমার্ক, শিরোনাম, বডি, ক্যাপশন, বাটন, KPI ও লিংক — ফন্ট, রঙ, ওজন, সাইজ, ফাঁক, লাইন-হাইট, ইটালিক, আন্ডারলাইন, ছায়া। সেভ করলে সাইটজুড়ে বসবে।', 'Wordmark, headings, body, captions, buttons, KPIs and links. Save once — applies everywhere.'); ?></p>
                </header>

                <article class="ame-os-preview">
                    <small><?php echo ame_pair('লাইভ স্যাম্পল পেজ', 'Live sample page'); ?></small>
                    <h2 data-t-layer="heading">লোন ব্যবস্থাপনা — ড্যাশবোর্ড</h2>
                    <strong data-t-layer="name" data-ame-wordmark><?php echo htmlspecialchars($shop['shop_name']); ?></strong>
                    <em data-t-layer="tag" data-ame-tagline><?php echo htmlspecialchars($shop['shop_tagline'] ?? ''); ?></em>
                    <p data-t-layer="body">প্রত্যেক পেজের মূল লেখা এই স্তরে বসে — কার্ড, টেবিল, ফর্ম ও হিসাবের বিবরণ।</p>
                    <b data-t-layer="kpi">12,50,000 ৳</b>
                    <span data-t-layer="caption">এই মাসের প্রদত্ত বকেয়া</span>
                    <a href="#type-os" data-t-layer="link">বিস্তারিত দেখুন</a>
                    <button type="button" data-t-layer="button">সব তথ্য আপডেট করুন</button>
                </article>

                <div class="ame-tabs" role="tablist">
                    <?php $first = true; foreach ($brandLayers as $lid => $meta): ?>
                    <button type="button" class="ame-tab<?php echo $first ? ' is-on' : ''; ?>" data-tab="<?php echo htmlspecialchars($lid); ?>"><?php echo ame_pair($meta['bn'], $meta['en']); ?></button>
                    <?php $first = false; endforeach; ?>
                </div>

                <?php $first = true; foreach ($brandLayers as $lid => $meta):
                    $L = $brandType[$lid] ?? (function_exists('ame_brand_layer_defaults') ? ame_brand_layer_defaults($lid) : []);
                ?>
                <div class="ame-layer" data-layer-panel="<?php echo htmlspecialchars($lid); ?>" <?php echo $first ? '' : 'hidden'; ?>>
                    <p class="ame-atelier__label"><?php echo ame_pair($meta['bn'] . ' ফন্ট', $meta['en'] . ' typeface'); ?></p>
                    <div class="ame-font-grid">
                        <?php foreach ($brandFonts as $id => $fmeta): ?>
                        <button type="button" class="ame-font-card<?php echo ($L['font'] ?? '') === $id ? ' is-on' : ''; ?>" data-set="<?php echo htmlspecialchars($lid); ?>:font:<?php echo htmlspecialchars($id); ?>" style="font-family: <?php echo htmlspecialchars($fmeta['family'], ENT_QUOTES, 'UTF-8'); ?>">
                            <b>আআAa</b>
                            <span><?php echo htmlspecialchars($fmeta['label_bn']); ?></span>
                            <em><?php echo htmlspecialchars($fmeta['label_en']); ?></em>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <p class="ame-atelier__label"><?php echo ame_pair('লেখার রঙ', 'Text color'); ?></p>
                    <div class="ame-color-row">
                        <label class="ame-picker">
                            <input type="color" data-color="<?php echo htmlspecialchars($lid); ?>" value="<?php echo htmlspecialchars($L['color'] ?? '#123526'); ?>">
                            <span data-hex="<?php echo htmlspecialchars($lid); ?>"><?php echo htmlspecialchars($L['color'] ?? ''); ?></span>
                        </label>
                        <div class="ame-swatches">
                            <?php foreach ($brandPresets as $hex => $label): ?>
                            <button type="button" class="ame-swatch<?php echo strtoupper($L['color'] ?? '') === strtoupper($hex) ? ' is-on' : ''; ?>" data-set="<?php echo htmlspecialchars($lid); ?>:color:<?php echo htmlspecialchars($hex); ?>" title="<?php echo htmlspecialchars($label); ?>" style="background:<?php echo htmlspecialchars($hex); ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="ame-chips">
                        <?php foreach (['400','500','600','700','800'] as $w): ?>
                        <button type="button" class="ame-chip<?php echo ($L['weight'] ?? '') === $w ? ' is-on' : ''; ?>" data-set="<?php echo htmlspecialchars($lid); ?>:weight:<?php echo $w; ?>"><?php echo $w; ?></button>
                        <?php endforeach; ?>
                        <button type="button" class="ame-chip<?php echo ($L['italic'] ?? '0') === '1' ? ' is-on' : ''; ?>" data-set="<?php echo htmlspecialchars($lid); ?>:italic:<?php echo ($L['italic'] ?? '0') === '1' ? '0' : '1'; ?>"><?php echo ame_pair('ইটালিক', 'Italic'); ?></button>
                        <button type="button" class="ame-chip<?php echo ($L['decoration'] ?? '') === 'underline' ? ' is-on' : ''; ?>" data-set="<?php echo htmlspecialchars($lid); ?>:decoration:<?php echo ($L['decoration'] ?? '') === 'underline' ? 'none' : 'underline'; ?>"><?php echo ame_pair('আন্ডারলাইন', 'Underline'); ?></button>
                        <button type="button" class="ame-chip<?php echo ($L['transform'] ?? '') === 'capitalize' ? ' is-on' : ''; ?>" data-set="<?php echo htmlspecialchars($lid); ?>:transform:<?php echo ($L['transform'] ?? '') === 'capitalize' ? 'none' : 'capitalize'; ?>"><?php echo ame_pair('টাইটেল কেস', 'Title case'); ?></button>
                    </div>
                    <div class="ame-chips">
                        <?php foreach (['none' => 'ছায়া নেই', 'soft' => 'নরম ছায়া', 'glow' => 'গ্লো'] as $sid => $sl): ?>
                        <button type="button" class="ame-chip<?php echo ($L['shadow'] ?? '') === $sid ? ' is-on' : ''; ?>" data-set="<?php echo htmlspecialchars($lid); ?>:shadow:<?php echo $sid; ?>"><?php echo $sl; ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="ame-ctrl-grid">
                        <label class="ame-range">
                            <span><?php echo ame_pair('সাইজ', 'Size'); ?> <b data-out="<?php echo htmlspecialchars($lid); ?>-size"><?php echo (int)($L['size'] ?? 15); ?>px</b></span>
                            <input type="range" name="brand_<?php echo htmlspecialchars($lid); ?>_size" min="10" max="48" step="1" value="<?php echo (int)($L['size'] ?? 15); ?>">
                        </label>
                        <label class="ame-range">
                            <span><?php echo ame_pair('অক্ষর ফাঁক', 'Tracking'); ?> <b data-out="<?php echo htmlspecialchars($lid); ?>-tracking"><?php echo htmlspecialchars($L['tracking'] ?? '0'); ?>em</b></span>
                            <input type="range" name="brand_<?php echo htmlspecialchars($lid); ?>_tracking" min="-0.10" max="0.16" step="0.005" value="<?php echo htmlspecialchars($L['tracking'] ?? '0'); ?>">
                        </label>
                        <label class="ame-range">
                            <span><?php echo ame_pair('লাইন হাইট', 'Leading'); ?> <b data-out="<?php echo htmlspecialchars($lid); ?>-leading"><?php echo htmlspecialchars($L['leading'] ?? '1.50'); ?></b></span>
                            <input type="range" name="brand_<?php echo htmlspecialchars($lid); ?>_leading" min="1.00" max="2.20" step="0.05" value="<?php echo htmlspecialchars($L['leading'] ?? '1.50'); ?>">
                        </label>
                    </div>
                    <input type="hidden" name="brand_<?php echo htmlspecialchars($lid); ?>_font" value="<?php echo htmlspecialchars($L['font'] ?? ''); ?>">
                    <input type="hidden" name="brand_<?php echo htmlspecialchars($lid); ?>_color" value="<?php echo htmlspecialchars($L['color'] ?? ''); ?>">
                    <input type="hidden" name="brand_<?php echo htmlspecialchars($lid); ?>_weight" value="<?php echo htmlspecialchars($L['weight'] ?? ''); ?>">
                    <input type="hidden" name="brand_<?php echo htmlspecialchars($lid); ?>_transform" value="<?php echo htmlspecialchars($L['transform'] ?? 'none'); ?>">
                    <input type="hidden" name="brand_<?php echo htmlspecialchars($lid); ?>_italic" value="<?php echo htmlspecialchars($L['italic'] ?? '0'); ?>">
                    <input type="hidden" name="brand_<?php echo htmlspecialchars($lid); ?>_decoration" value="<?php echo htmlspecialchars($L['decoration'] ?? 'none'); ?>">
                    <input type="hidden" name="brand_<?php echo htmlspecialchars($lid); ?>_shadow" value="<?php echo htmlspecialchars($L['shadow'] ?? 'none'); ?>">
                </div>
                <?php $first = false; endforeach; ?>
            </section>

            <div class="set-group">
                <label><?php echo ame_pair('মোবাইল নম্বর', 'Mobile number'); ?></label>
                <input type="text" name="shop_phone" value="<?php echo htmlspecialchars((string)($shop['shop_phone'] ?? '')); ?>">
            </div>

            <div class="set-group">
                <label><?php echo ame_pair('ঠিকানা', 'Address'); ?></label>
                <textarea name="shop_address" rows="3"><?php echo htmlspecialchars((string)($shop['shop_address'] ?? '')); ?></textarea>
            </div>

            <button type="submit" class="set-submit"><?php echo ame_pair('সব তথ্য আপডেট করুন', 'Save all changes'); ?></button>
        </form>
    </div>
</div>
<script src="/assets/js/brand-type.806.js"></script>
<?php include '../../partials/footer.php'; ?>
