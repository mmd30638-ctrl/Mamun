<?php
/**
 * SM40 শোনার হাব — প্রজেক্টের ভিতর অডিও, একের পর এক অটো প্লে।
 * বাইরের sm40.com সোশ্যাল আলাদা লিংক।
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/islamic_audio.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$ver = htmlspecialchars((string)($sys_version ?? AME_ASSET_VERSION), ENT_QUOTES, 'UTF-8');
$shop = function_exists('shop_name') ? shop_name() : 'দোকান';
$social = 'https://sm40.com/welcome?lang=bengali';

include __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/sm40-listen-hub.css?v=<?php echo $ver; ?>">
<main class="sm40-hub" id="sm40Hub">
    <header class="sm40-hub-head">
        <div>
            <h1 class="sm40-hub-title">SM40 · অডিও</h1>
            <p class="sm40-hub-sub">একটি শেষ হলে পরেরটি স্বয়ংক্রিয় চলবে। এই উইন্ডো খোলা রেখে দোকানের অন্য ট্যাবে কাজ করুন।</p>
        </div>
        <a class="sm40-hub-social" href="<?php echo htmlspecialchars($social, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">SM40 সোশ্যাল ↗</a>
    </header>
    <?php
    if (isset($db) && $db instanceof mysqli) {
        ame_islamic_audio_render_widget($db, false, ['hub' => 'sm40', 'autoplay_next' => true, 'panel_open' => true]);
    }
    ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>