<?php
/**
 * Settings → লেআউট ডিজাইন
 * Full-page editor: front, colors, boxes, icons on one screen.
 * The 📐 icon is not a sales-desk button. Settings owns it.
 * A toggle places that same icon on every page.
 */
require_once '../../includes/config.php';
require_once '../../includes/page_layout.php';

$db = $GLOBALS['db'] ?? null;
if (!ame_page_layout_can_edit($db instanceof mysqli ? $db : null)) {
    header('Location: /index.php');
    exit;
}

$ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.324';
if (is_file(__DIR__ . '/../../VERSION.txt')) {
    $fileVer = trim((string) file_get_contents(__DIR__ . '/../../VERSION.txt'));
    if ($fileVer !== '') {
        $ver = $fileVer;
    }
}
$data = ame_page_layout_load();
$catalog = ame_page_layout_catalog();
$startPage = (string) ($_GET['page'] ?? 'sales/pos');
$startUrl = '/modules/sales/pos.php';
foreach ($catalog as $item) {
    if ($item['key'] === $startPage || $item['url'] === $startPage) {
        $startPage = $item['key'];
        $startUrl = $item['url'];
        break;
    }
}
if (str_starts_with($startPage, '/')) {
    $startUrl = $startPage;
    $startPage = trim(preg_replace('#^/modules/#', '', preg_replace('#\.php$#', '', $startPage) ?? ''), '/');
    if ($startPage === '' || $startPage === 'index') {
        $startPage = 'home';
        $startUrl = '/index.php';
    }
}
$boot = [
    'api' => '/modules/settings/layout_designer_api.php',
    'version' => $ver,
    'data' => $data,
    'catalog' => $catalog,
    'startPage' => $startPage,
    'startUrl' => $startUrl,
];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📐 লেআউট ডিজাইন · সেটিংস</title>
    <link rel="stylesheet" href="/assets/css/ame-front-colors.css?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/page-layout-editor.css?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="ame-ld-body">
    <header class="ame-ld-bar">
        <div class="ame-ld-title">
            <span>📐</span>
            <div>
                <strong>লেআউট ডিজাইন</strong>
                <small>সেটিংস · ফ্রন্ট, রং, বক্স, আইকন এক পেজে</small>
            </div>
        </div>
        <div class="ame-ld-actions">
            <label class="ame-ld-switch" title="সব পেজে 📐 আইকন দেখান">
                <input type="checkbox" id="ameIconAll" <?php echo !empty($data['iconOnAllPages']) ? 'checked' : ''; ?>>
                <span>সব পেজে 📐 (বিক্রয় বাদে)</span>
            </label>
            <button type="button" class="ame-ld-btn ame-ld-save" id="ameSave">💾 সংরক্ষণ</button>
            <button type="button" class="ame-ld-btn ame-ld-reset" id="ameReset">🔄 ডিফল্ট</button>
            <a class="ame-ld-btn ame-ld-back" href="/modules/settings/tools.php">⬅ সেটিংস</a>
        </div>
    </header>

    <div class="ame-ld-pages" id="amePages"></div>

    <main class="ame-ld-main">
        <section class="ame-ld-stage">
            <div class="ame-ld-hint">
                নিচের পুরো পেজই এডিট হবে। বক্স বা আইকনে ক্লিক করুন। ফ্রন্ট রং ডান পাশে সাথে সাথে বদলায়।
                <span id="amePickLabel">কোনো উপাদান সিলেক্ট হয়নি</span>
            </div>
            <iframe id="ameStage" title="পেজ প্রিভিউ"></iframe>
        </section>
        <aside class="ame-ld-dock">
            <section>
                <h2>ফ্রন্ট রং</h2>
                <div class="ame-ld-colors" id="ameColors"></div>
            </section>
            <section>
                <h2>বক্স</h2>
                <p class="ame-ld-note">পেজে ক্লিক করে বক্স বেছে নিন। তারপর মাপ ও রং বদলান।</p>
                <div class="ame-ld-fields">
                    <label>প্রস্থ <input type="number" id="boxW" min="40" step="5"></label>
                    <label>উচ্চতা <input type="number" id="boxH" min="24" step="5"></label>
                    <label>বামে <input type="number" id="boxX" step="5"></label>
                    <label>উপরে <input type="number" id="boxY" step="5"></label>
                    <label>ব্যাকগ্রাউন্ড <input type="color" id="boxBg"></label>
                    <label>লেখা <input type="color" id="boxFg"></label>
                    <label class="ame-ld-check"><input type="checkbox" id="boxHide"> লুকান</label>
                </div>
            </section>
            <section>
                <h2>আইকন</h2>
                <p class="ame-ld-note">হেডার বা সাইডবারের আইকনে ক্লিক করুন। সব পেজে নেওয়ার সুইচ উপরে।</p>
                <div class="ame-ld-fields">
                    <label>সাইজ <input type="number" id="iconSize" min="12" max="72"></label>
                    <label>রং <input type="color" id="iconColor"></label>
                    <label class="ame-ld-check"><input type="checkbox" id="iconHide"> লুকান</label>
                </div>
            </section>
            <p id="ameStatus" class="ame-ld-status"></p>
        </aside>
    </main>
    <script>window.AME_LAYOUT_STUDIO=<?php echo json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;</script>
    <script src="/assets/js/page-layout-editor.js?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
