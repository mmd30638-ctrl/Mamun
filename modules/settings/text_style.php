<?php
/**
 * Settings → লেখা বড় ছোট ও রঙ
 * Separate icon from 📐 layout design. Per-page text size and color.
 */
require_once '../../includes/config.php';
require_once '../../includes/page_layout.php';
require_once '../../includes/page_text.php';

$db = $GLOBALS['db'] ?? null;
if (!ame_page_layout_can_edit($db instanceof mysqli ? $db : null)) {
    header('Location: /index.php');
    exit;
}

$ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.340';
if (is_file(__DIR__ . '/../../VERSION.txt')) {
    $fileVer = trim((string) file_get_contents(__DIR__ . '/../../VERSION.txt'));
    if ($fileVer !== '') {
        $ver = $fileVer;
    }
}
$data = ame_page_text_load();
$catalog = ame_page_text_catalog();
$startPage = (string) ($_GET['page'] ?? 'home');
$startUrl = '/index.php';
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
    'api' => '/modules/settings/text_style_api.php',
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
    <title>🔤 লেখা বড় ছোট ও রঙ · সেটিংস</title>
    <link rel="stylesheet" href="/assets/css/ame-front-colors.css?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/page-layout-editor.css?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/page-text-editor.css?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="ame-ld-body">
    <header class="ame-ld-bar">
        <div class="ame-ld-title">
            <span>🔤</span>
            <div>
                <strong>লেখা বড় ছোট ও রঙ</strong>
                <small>সেটিংস · প্রতিটি পেজ আলাদা · 📐 লেআউট থেকে আলাদা</small>
            </div>
        </div>
        <div class="ame-ld-actions">
            <button type="button" class="ame-ld-btn ame-ld-save" id="ameSave">💾 সংরক্ষণ</button>
            <button type="button" class="ame-ld-btn ame-ld-reset" id="ameResetPage">এই পেজ ডিফল্ট</button>
            <button type="button" class="ame-ld-btn ame-ld-reset" id="ameReset">সব ডিফল্ট</button>
            <a class="ame-ld-btn ame-ld-back" href="/modules/settings/tools.php">⬅ সেটিংস</a>
        </div>
    </header>

    <div class="ame-ld-pages" id="amePages"></div>

    <main class="ame-ld-main">
        <section class="ame-ld-stage">
            <div class="ame-ld-hint">
                পেজ বেছে নিয়ে লেখা বড়/ছোট করুন, রং বদলান। সাথে সাথে নিচে দেখাবে।
                <span id="amePickLabel">এই পেজের লেখা</span>
            </div>
            <iframe id="ameStage" title="পেজ প্রিভিউ"></iframe>
        </section>
        <aside class="ame-ld-dock">
            <section>
                <h2>লেখা বড় / ছোট</h2>
                <p class="ame-ld-note">শুধু এই পেজের লেখা। অন্য পেজ আলাদা রাখুন।</p>
                <div class="ame-text-size-row">
                    <button type="button" id="ameSmaller" class="ame-text-step">A−</button>
                    <strong id="ameScaleLabel">১০০%</strong>
                    <button type="button" id="ameBigger" class="ame-text-step ame-text-step-lg">A+</button>
                </div>
                <input type="range" id="ameScale" min="0.7" max="1.8" step="0.05" value="1">
            </section>
            <section>
                <h2>লেখার রং</h2>
                <div class="ame-ld-fields">
                    <label>রং <input type="color" id="ameColor" value="#111827"></label>
                    <label class="ame-ld-check"><input type="checkbox" id="ameColorOn"> রং চালু</label>
                </div>
            </section>
            <p id="ameStatus" class="ame-ld-status"></p>
        </aside>
    </main>
    <script>window.AME_TEXT_STUDIO=<?php echo json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;</script>
    <script src="/assets/js/page-text-editor.js?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
