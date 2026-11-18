<?php
require_once '../../includes/config.php';
require_once '../../includes/file_atlas.php';

$english = function_exists('current_lang') && current_lang() === 'en';
$atlas = ame_atlas_build();
$ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '10.98.17';

include '../../partials/header.php';

function ame_atlas_render_folder(array $folder, int $depth = 0): void
{
    $open = $depth < 1 ? ' open' : '';
    echo '<details class="fa-folder"' . $open . ' data-fa-q="'
        . htmlspecialchars(strtolower($folder['name'] . ' ' . $folder['path'] . ' ' . $folder['purpose']), ENT_QUOTES, 'UTF-8')
        . '">';
    echo '<summary>';
    echo '<span class="fa-ico" aria-hidden="true">📁</span>';
    echo '<span class="fa-copy"><strong>' . htmlspecialchars($folder['name'], ENT_QUOTES, 'UTF-8') . '</strong>';
    echo '<small>' . htmlspecialchars($folder['path'] === '' ? '/' : $folder['path'], ENT_QUOTES, 'UTF-8')
        . ' · ' . (int)$folder['fileCount'] . ' ফাইল</small>';
    echo '<em>' . htmlspecialchars($folder['purpose'], ENT_QUOTES, 'UTF-8') . '</em></span>';
    echo '</summary><div class="fa-kids">';
    foreach ($folder['folders'] as $child) {
        ame_atlas_render_folder($child, $depth + 1);
    }
    foreach ($folder['files'] as $file) {
        echo '<div class="fa-file" data-fa-q="'
            . htmlspecialchars(strtolower($file['name'] . ' ' . $file['path'] . ' ' . $file['purpose']), ENT_QUOTES, 'UTF-8')
            . '">';
        echo '<span class="fa-ico" aria-hidden="true">📄</span>';
        echo '<span class="fa-copy"><strong>' . htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') . '</strong>';
        echo '<small>' . htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8') . '</small>';
        echo '<em>' . htmlspecialchars($file['purpose'], ENT_QUOTES, 'UTF-8') . '</em></span></div>';
    }
    echo '</div></details>';
}
?>
<style>
.fa-page { max-width: 920px; margin: 0 auto; padding: 16px 14px 40px; }
.fa-kicker { margin: 0; color: #64748b; font-size: 11px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; }
.fa-page h1 { margin: 4px 0 8px; font-size: 28px; letter-spacing: -.03em; color: #0f172a; }
.fa-lead { margin: 0 0 14px; color: #475569; font-size: 14.5px; line-height: 1.6; }
.fa-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.fa-count { color: #64748b; font-size: 13px; font-weight: 650; }
.fa-search { width: min(100%, 320px); min-height: 44px; padding: 0 12px; border: 1.5px solid #d8e1ec; border-radius: 12px; font-size: 15px; }
.fa-tree { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 8px; }
.fa-folder { border-bottom: 1px solid #f1f5f9; }
.fa-folder > summary { display: flex; gap: 10px; align-items: flex-start; padding: 10px 8px; cursor: pointer; list-style: none; }
.fa-folder > summary::-webkit-details-marker { display: none; }
.fa-kids { padding: 0 0 8px 14px; border-left: 1px solid #e2e8f0; margin-left: 16px; }
.fa-file { display: flex; gap: 10px; align-items: flex-start; padding: 8px; }
.fa-ico { width: 22px; text-align: center; }
.fa-copy { min-width: 0; flex: 1; }
.fa-copy strong { display: block; color: #0f172a; font-size: 14.5px; }
.fa-copy small { display: block; color: #94a3b8; font-family: ui-monospace, monospace; font-size: 11px; }
.fa-copy em { display: block; margin-top: 2px; color: #1e293b; font-style: normal; font-size: 13.5px; line-height: 1.45; }
.fa-empty { padding: 22px; color: #64748b; text-align: center; }
.fa-file.is-hide, .fa-folder.is-hide { display: none !important; }
</style>

<div class="fa-page">
    <p class="fa-kicker">APONSHOP · File atlas · v<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?></p>
    <h1><?php echo $english ? 'File atlas' : 'ফাইল খাতা'; ?></h1>
    <p class="fa-lead">
        <?php echo $english
            ? 'Every folder and file in this shop — a short note on what it does. Tap a folder to open it.'
            : 'সাইডবারের ফাইল খাতা আইকনে ক্লিক করলে এই পেজ। কোন ফোল্ডারে কী কাজ, কোন ফাইলে কী কাজ — সংক্ষেপে। ফোল্ডারে ট্যাপ করলে ভিতর খুলবে।'; ?>
    </p>
    <div class="fa-toolbar">
        <p class="fa-count"><?php echo (int)$atlas['folderCount']; ?> ফোল্ডার · <?php echo (int)$atlas['fileCount']; ?> ফাইল</p>
        <input class="fa-search" id="faSearch" type="search" placeholder="<?php echo $english ? 'Search file, folder, or job' : 'ফাইল, ফোল্ডার বা কাজ খুঁজুন'; ?>" aria-label="<?php echo $english ? 'Search' : 'সার্চ'; ?>">
    </div>
    <div class="fa-tree" id="faTree">
        <?php foreach ($atlas['tree']['folders'] as $child) {
            ame_atlas_render_folder($child, 0);
        }
        foreach ($atlas['tree']['files'] as $file) {
            echo '<div class="fa-file" data-fa-q="'
                . htmlspecialchars(strtolower($file['name'] . ' ' . $file['path'] . ' ' . $file['purpose']), ENT_QUOTES, 'UTF-8')
                . '">';
            echo '<span class="fa-ico" aria-hidden="true">📄</span>';
            echo '<span class="fa-copy"><strong>' . htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') . '</strong>';
            echo '<small>' . htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8') . '</small>';
            echo '<em>' . htmlspecialchars($file['purpose'], ENT_QUOTES, 'UTF-8') . '</em></span></div>';
        } ?>
        <p class="fa-empty" id="faEmpty" hidden><?php echo $english ? 'Nothing matched.' : 'এই খোঁজে কিছু মেলেনি।'; ?></p>
    </div>
</div>
<script>
(function () {
    var input = document.getElementById('faSearch');
    var tree = document.getElementById('faTree');
    var empty = document.getElementById('faEmpty');
    if (!input || !tree) return;
    input.addEventListener('input', function () {
        var q = (input.value || '').trim().toLowerCase();
        var folders = tree.querySelectorAll('.fa-folder');
        var files = tree.querySelectorAll('.fa-file');
        var shown = 0;
        files.forEach(function (el) {
            var on = !q || (el.getAttribute('data-fa-q') || '').indexOf(q) >= 0;
            el.classList.toggle('is-hide', !on);
            if (on) shown += 1;
        });
        folders.forEach(function (el) {
            var self = !q || (el.getAttribute('data-fa-q') || '').indexOf(q) >= 0;
            var child = el.querySelector('.fa-file:not(.is-hide), .fa-folder:not(.is-hide)');
            var on = self || !!child;
            el.classList.toggle('is-hide', !on);
            if (q && on) el.open = true;
            if (on) shown += 1;
        });
        if (empty) empty.hidden = shown > 0;
    });
})();
</script>
<?php include '../../partials/footer.php'; ?>
