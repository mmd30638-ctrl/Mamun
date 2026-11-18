<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/shop_rent.php';
shop_rent_boot($db);
$id = (int)($_GET['id'] ?? $_POST['shop_id'] ?? 0);
$row = $id > 0 ? shop_rent_load($db, $id) : null;
$shops = shop_rent_rows($db);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? 'upload');
    try {
        if ($action === 'caption') {
            shop_rent_save_caption($db, (int)($_POST['file_id'] ?? 0), (string)($_POST['caption'] ?? ''));
            $_SESSION['shop_rent_flash'] = 'ক্যাপশন সেভ হয়েছে।';
        } else {
            shop_rent_store_file($db, $_POST, $_FILES['paper'] ?? []);
            $_SESSION['shop_rent_flash'] = 'কাগজ সেভ হয়েছে। ছবিতে তারিখ ও সময় বসে গেছে।';
        }
        header('Location: /modules/shop_rent/locker.php?id=' . (int)($_POST['shop_id'] ?? 0) . '&kind=' . rawurlencode((string)($_POST['kind'] ?? '')));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$flash = (string)($_SESSION['shop_rent_flash'] ?? '');
unset($_SESSION['shop_rent_flash']);
$kinds = shop_rent_kinds();
$focus = (string)($_GET['kind'] ?? 'condition');
if (!isset($kinds[$focus])) {
    $focus = 'condition';
}
$files = $row ? shop_rent_files($db, (int)$row['id']) : [];
$groups = [];
foreach ($files as $file) {
    $groups[(string)$file['kind']][] = $file;
}
include __DIR__ . '/../../partials/header.php';
include __DIR__ . '/_nav.php';
?>
<header class="sr-hero">
    <h1>কাগজ ও ছবি</h1>
    <p>দোকান নেওয়ার সময়ের অবস্থা, চুক্তিনামা, মাসভিত্তিক রসিদ এবং মালিকের পরিচয়। ছবিতে চাপলে বড় হবে।</p>
</header>
<?php if ($flash !== ''): ?><div class="sr-note"><?php echo shop_rent_e($flash); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="sr-error"><?php echo shop_rent_e($error); ?></div><?php endif; ?>
<?php if (!$shops): ?>
    <div class="sr-box"><p>আগে একটি দোকান যোগ করুন।</p><a class="sr-btn" href="/modules/shop_rent/shop.php">নতুন দোকান</a></div>
<?php else: ?>
<form class="sr-form" method="get">
    <section class="sr-box sr-span">
        <label for="shopPick">দোকান</label>
        <select id="shopPick" name="id" onchange="this.form.submit()">
            <?php foreach ($shops as $shop): ?>
                <option value="<?php echo (int)$shop['id']; ?>" <?php echo (int)$shop['id'] === $id ? 'selected' : ''; ?>><?php echo shop_rent_e($shop['title']); ?></option>
            <?php endforeach; ?>
        </select>
    </section>
</form>
<?php if ($row): ?>
<nav class="sr-tabs" aria-label="কাগজের ধরন">
    <?php foreach ($kinds as $key => $label): ?>
        <a class="sr-btn <?php echo $focus === $key ? '' : 'ghost'; ?>" href="/modules/shop_rent/locker.php?id=<?php echo (int)$row['id']; ?>&kind=<?php echo shop_rent_e($key); ?>"><?php echo shop_rent_e($label); ?></a>
    <?php endforeach; ?>
</nav>
<form class="sr-box" method="post" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="upload">
    <input type="hidden" name="shop_id" value="<?php echo (int)$row['id']; ?>">
    <h2><?php echo shop_rent_e($kinds[$focus]); ?> যোগ করুন</h2>
    <input type="hidden" name="kind" value="<?php echo shop_rent_e($focus); ?>">
    <?php if ($focus === 'receipt'): ?>
        <label for="period_ym">কোন মাসের রসিদ</label>
        <input id="period_ym" name="period_ym" type="month" required value="<?php echo shop_rent_e(shop_rent_now()->format('Y-m')); ?>">
    <?php endif; ?>
    <label for="paper">JPG, PNG, WEBP বা PDF (৮ মেগাবাইট)</label>
    <input id="paper" name="paper" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
    <label for="caption">ক্যাপশন বা মন্তব্য</label>
    <input id="caption" name="caption" placeholder="যেমন: সামনের দেয়াল, নেওয়ার দিন">
    <div class="sr-tools"><button class="sr-btn" type="submit">আপলোড</button></div>
</form>
<?php
$shown = $groups[$focus] ?? [];
$byMonth = [];
foreach ($shown as $file) {
    $bucket = $focus === 'receipt' ? (string)($file['period_ym'] ?? '') : '';
    $byMonth[$bucket][] = $file;
}
if (!$shown): ?>
    <div class="sr-box" style="margin-top:14px"><p>এই ভাগে এখনো কিছু নেই।</p></div>
<?php else: ?>
    <?php if ($focus === 'agreement'): ?>
        <div class="sr-tools">
            <button class="sr-btn ghost" type="button" id="srPrev">আগের পাতা</button>
            <button class="sr-btn ghost" type="button" id="srNext">পরের পাতা</button>
        </div>
    <?php endif; ?>
    <?php foreach ($byMonth as $bucket => $items): ?>
        <?php if ($bucket !== ''): ?><h3 class="sr-folder"><?php echo shop_rent_e(shop_rent_period_label($bucket)); ?></h3><?php endif; ?>
        <div class="<?php echo $focus === 'agreement' ? 'sr-slider' : 'sr-grid'; ?>" <?php echo $focus === 'agreement' ? 'id="srAgree"' : ''; ?>>
        <?php foreach ($items as $file):
            $src = '/modules/shop_rent/file.php?id=' . (int)$file['id'];
            $isPdf = (string)$file['mime'] === 'application/pdf';
            $cap = trim((string)($file['caption'] ?? ''));
        ?>
            <figure class="sr-shot">
                <button class="pic" type="button" data-sr-open data-src="<?php echo shop_rent_e($src); ?>" data-pdf="<?php echo $isPdf ? '1' : '0'; ?>" data-cap="<?php echo shop_rent_e($cap); ?>">
                    <?php if ($isPdf): ?><span class="pdf">PDF</span><?php else: ?><img src="<?php echo shop_rent_e($src); ?>" alt="<?php echo shop_rent_e($cap !== '' ? $cap : $kinds[$focus]); ?>"><?php endif; ?>
                </button>
                <figcaption>
                    <time><?php echo shop_rent_e($file['taken_at']); ?></time>
                    <form method="post">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="caption">
                        <input type="hidden" name="shop_id" value="<?php echo (int)$row['id']; ?>">
                        <input type="hidden" name="kind" value="<?php echo shop_rent_e($focus); ?>">
                        <input type="hidden" name="file_id" value="<?php echo (int)$file['id']; ?>">
                        <input type="text" name="caption" value="<?php echo shop_rent_e($cap); ?>" aria-label="ক্যাপশন">
                        <button class="sr-btn ghost" type="submit">লিখুন</button>
                    </form>
                </figcaption>
            </figure>
        <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<div class="sr-light" id="srLight" hidden>
    <button type="button" class="sr-x" id="srLightClose" aria-label="বন্ধ">×</button>
    <figure>
        <img id="srLightImg" alt="">
        <iframe id="srLightFrame" title="কাগজ" hidden style="width:min(920px,92vw);height:72vh;background:#fff;border:0;border-radius:12px"></iframe>
        <figcaption id="srLightCap"></figcaption>
    </figure>
</div>
</div>
<script>
(function () {
    var light = document.getElementById('srLight');
    var img = document.getElementById('srLightImg');
    var frame = document.getElementById('srLightFrame');
    var cap = document.getElementById('srLightCap');
    if (!light) return;
    function closeLight() {
        light.classList.remove('open');
        light.hidden = true;
        if (frame) frame.src = '';
    }
    document.querySelectorAll('[data-sr-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var pdf = btn.getAttribute('data-pdf') === '1';
            cap.textContent = btn.getAttribute('data-cap') || '';
            if (pdf) {
                img.hidden = true;
                frame.hidden = false;
                frame.src = btn.getAttribute('data-src');
            } else {
                frame.hidden = true;
                frame.src = '';
                img.hidden = false;
                img.src = btn.getAttribute('data-src');
                img.alt = cap.textContent;
            }
            light.hidden = false;
            light.classList.add('open');
        });
    });
    light.addEventListener('click', function (event) {
        if (event.target === light) closeLight();
    });
    var closer = document.getElementById('srLightClose');
    if (closer) closer.addEventListener('click', closeLight);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeLight();
    });
    var rail = document.getElementById('srAgree');
    var prev = document.getElementById('srPrev');
    var next = document.getElementById('srNext');
    if (rail && prev && next) {
        prev.addEventListener('click', function () { rail.scrollBy({ left: -240, behavior: 'smooth' }); });
        next.addEventListener('click', function () { rail.scrollBy({ left: 240, behavior: 'smooth' }); });
    }
})();
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
