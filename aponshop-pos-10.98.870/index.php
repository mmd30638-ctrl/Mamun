<?php
/**
 * প্রধান হোমপেজ — ব্যানার। বিক্রয় ও পেমেন্ট কে কি করলো আইকনের পপআপে।
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/perf_cache.php';

include __DIR__ . '/partials/header.php';

// ব্যানার বদলায় редко — ১০ মিনিট ক্যাশ (বদলালে process_banners ভেঙে দেয়)।
$banner_rows = ame_perf_remember('dashboard_banners_v1', 600, static function () use ($db) {
    $rows = [];
    $banners = $db->query("SELECT image_name FROM dashboard_banners ORDER BY id DESC");
    if ($banners) {
        while ($b = $banners->fetch_assoc()) {
            $fn = basename((string)($b['image_name'] ?? ''));
            if ($fn !== '') {
                $rows[] = ['image_name' => $fn];
            }
        }
        $banners->close();
    }
    return $rows;
});
$ver = htmlspecialchars((string)($sys_version ?? AME_ASSET_VERSION), ENT_QUOTES, 'UTF-8');
$shop = function_exists('shop_name') ? shop_name() : '';
$tag = function_exists('shop_tagline') ? shop_tagline() : '';
$name = trim((string)($_SESSION['username'] ?? $_SESSION['user_name'] ?? ''));
$hello = $name !== ''
    ? te('home_hello', ['name' => $name])
    : te('home_hello_guest');
$alt = $shop !== '' ? $shop : 'Home';
$img_dir = __DIR__ . '/assets/img';
$banner_rows = array_values(array_filter($banner_rows, static function ($b) use ($img_dir) {
    $fn = basename((string)(is_array($b) ? ($b['image_name'] ?? '') : $b));
    return $fn !== '' && is_file($img_dir . '/' . $fn);
}));
foreach ($banner_rows as &$ame_br) {
    $ame_br = ['image_name' => basename((string)$ame_br['image_name'])];
}
unset($ame_br);
if ($banner_rows === [] && function_exists('ame_dashboard_banner_files')) {
    foreach (ame_dashboard_banner_files() as $fn) {
        $banner_rows[] = ['image_name' => $fn];
    }
}
$banner_count = count($banner_rows);
?>

<link rel="stylesheet" href="/assets/css/home-banner.css?v=<?php echo $ver; ?>">
<div class="hm-gallery">
    <header class="hm-gallery-greet">
        <?php if ($tag !== ''): ?>
        <p class="hm-gallery-kicker"><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <h1 class="hm-gallery-name"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="hm-gallery-hello"><?php echo $hello; ?></p>
    </header>
    <figure class="hm-gallery-frame">
        <div class="hm-gallery-mat">
            <div class="hm-banner-wrap">
                <?php if ($banner_count > 0): ?>
                    <div class="hm-banner-viewport" id="hmBannerViewport">
                        <?php if ($banner_count > 1): ?>
                        <button type="button" class="hm-banner-arrow prev" id="hmBannerPrev" aria-label="<?php echo te('home_banner_prev'); ?>">‹</button>
                        <button type="button" class="hm-banner-arrow next" id="hmBannerNext" aria-label="<?php echo te('home_banner_next'); ?>">›</button>
                        <?php endif; ?>
                        <div class="hm-banner-track" id="hmBannerTrack">
                            <?php foreach ($banner_rows as $b): ?>
                            <div class="hm-banner-slide">
                                <img src="/assets/img/<?php echo htmlspecialchars($b['image_name'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($alt, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if ($banner_count > 1): ?>
                    <div class="hm-banner-dots" id="hmBannerDots">
                        <?php for ($i = 0; $i < $banner_count; $i++): ?>
                            <button type="button" class="hm-banner-dot<?php echo $i === 0 ? ' active' : ''; ?>" onclick="hmGoToSlide(<?php echo $i; ?>)" aria-label="<?php echo te('home_banner_goto', ['n' => $i + 1]); ?>"></button>
                        <?php endfor; ?>
                    </div>
                    <script src="/assets/js/home-banner.js?v=<?php echo $ver; ?>"></script>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="hm-banner-empty">
                        <?php echo te('home_banner_empty'); ?><br>
                        <?php echo te('home_banner_hint'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($shop !== ''): ?>
        <figcaption class="hm-gallery-cap"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></figcaption>
        <?php endif; ?>
    </figure>
</div>

<?php
if (isset($db) && $db instanceof mysqli) {
    if (is_file(__DIR__ . '/includes/islamic_audio.php')) {
        require_once __DIR__ . '/includes/islamic_audio.php';
        ame_islamic_audio_render_home($db);
    }
}
?>

<div id="hrDraw" class="hr-draw" hidden>
    <div id="hrPanel" class="hr-panel" role="dialog" aria-modal="true" aria-labelledby="hrTitle">
        <button type="button" id="hrClose" data-hr-close aria-label="বন্ধ">×</button>
        <h2 id="hrTitle">পণ্য ফেরত</h2>
        <form id="hrForm">
            <input type="hidden" name="idempotency_token" id="hrToken" value="">
            <input type="hidden" name="product_id" id="hrProductId" value="">
            <input type="hidden" name="customer_id" id="hrCustomerId" value="">
            <label for="hrProductQ">পণ্য</label>
            <input id="hrProductQ" type="search" autocomplete="off" placeholder="নাম লিখে বাছুন">
            <div id="hrProductList" class="hr-list" hidden></div>
            <label for="hrQty">পরিমাণ</label>
            <input id="hrQty" name="qty" type="number" min="0.01" step="0.01" value="1">
            <label for="hrCustomerQ">কাস্টমার (ঐচ্ছিক)</label>
            <input id="hrCustomerQ" type="search" autocomplete="off" placeholder="নাম বা মোবাইল">
            <div id="hrCustomerList" class="hr-list" hidden></div>
            <button type="button" id="hrCustomerClear">কাস্টমার সরান</button>
            <label for="hrNote">নোট (ঐচ্ছিক)</label>
            <input id="hrNote" name="note" maxlength="255" placeholder="হাতে ফেরত">
            <button type="submit" id="hrSubmit">ফেরত নিন</button>
        </form>
    </div>
</div>
<div id="hrSay" class="hr-say" hidden>
    <button type="button" id="hrSayBtn"></button>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
