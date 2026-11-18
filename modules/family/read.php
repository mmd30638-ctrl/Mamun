<?php
require_once __DIR__ . '/_boot.php';
$id = (int)($_GET['id'] ?? 0);
$book = $id > 0 ? fam_one('SELECT * FROM family_books WHERE id=' . $id) : null;
$closed = ame_l('এই বইটি বর্তমানে খোলা যাচ্ছে না। অনুগ্রহ করে পরে আবার চেষ্টা করুন।', 'This book cannot be opened right now. Please try again later.');
if (!$book || (!empty($book['archived_at']) && !fam_can_write()) || (($book['visibility'] ?? 'family') === 'private' && !fam_can_write())) {
    $_SESSION['fam_err'] = $closed;
    header('Location: /modules/family/library.php');
    exit;
}
$uid = ame_kitab_uid();
$pages = fam_rows('SELECT page_no, image_file FROM family_book_pages WHERE book_id=' . $id . ' ORDER BY page_no');
$folds = fam_rows('SELECT * FROM family_book_folds WHERE book_id=' . $id . ' ORDER BY page_no');
$notes = fam_rows('SELECT id, page_no, note_text, created_at FROM family_book_notes WHERE book_id=' . $id . ' ORDER BY id DESC LIMIT 80');
$highs = fam_rows('SELECT id, page_no, quote, created_at FROM family_book_highlights WHERE book_id=' . $id . ' ORDER BY id DESC LIMIT 80');
$toc = fam_rows('SELECT title, page_no FROM family_book_toc WHERE book_id=' . $id . ' ORDER BY sort_order, page_no');
$prog = fam_one('SELECT page_no, status FROM family_book_progress WHERE book_id=' . $id . ' AND user_id=' . $uid);
$maxScan = 0;
$pageMap = [];
foreach ($pages as $row) {
    $n = (int)$row['page_no'];
    $pageMap[$n] = $row;
    if ($n > $maxScan) {
        $maxScan = $n;
    }
}
$total = max((int)($book['page_count'] ?? 0), $maxScan, 1);
$p = (int)($_GET['p'] ?? 0);
if ($p < 1) {
    $p = max(1, (int)($prog['page_no'] ?? 0));
    if ($p < 1) {
        $p = max(1, min($total, (int)($book['last_page'] ?? 1)));
    }
}
if ($p > $total && $maxScan < 1 && trim((string)($book['pdf_file'] ?? '')) !== '') {
    $total = max($total, $p);
}
if ($p > $total) {
    $p = $total;
}
$here = $pageMap[$p] ?? null;
$pdf = trim((string)($book['pdf_file'] ?? ''));
$img = trim((string)($here['image_file'] ?? ''));
$leaf = fam_one('SELECT ocr_text FROM family_book_pages WHERE book_id=' . $id . ' AND page_no=' . $p);
$ocr = (string)($leaf['ocr_text'] ?? '');
$pct = $total > 0 ? (int)min(100, round($p / $total * 100)) : 0;
$status = (string)($book['read_status'] ?? 'not_started');
$labels = [
    'not_started' => ame_l('শুরু হয়নি', 'Not started'),
    'reading' => ame_l('পড়ছি', 'Reading'),
    'completed' => ame_l('শেষ', 'Completed'),
    'paused' => ame_l('থামা', 'Paused'),
];
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_library', 'fam_lead_library');
echo '<link rel="stylesheet" href="/assets/css/kitab-read.183.css?v=' . esc_html($sys_version ?? '1') . '">';
?>
<div class="krx" id="krDesk">
  <header class="krx-top">
    <a class="krx-back" href="/modules/family/library.php"><?php echo ame_l('শেলফ', 'Shelf'); ?></a>
    <div>
      <h1><?php echo esc_html($book['title']); ?></h1>
      <p><?php echo esc_html(trim(($book['author'] ?? '') . ' · ' . ($book['subject'] ?? ''), ' ·')); ?></p>
    </div>
    <p class="krx-prog" id="krProg"><?php echo (int)$p; ?> / <?php echo (int)$total; ?> — <?php echo (int)$pct; ?>%</p>
  </header>
  <div class="krx-main">
    <aside class="krx-side" id="krSide">
      <h2><?php echo ame_l('অধ্যায়', 'Chapters'); ?></h2>
      <div id="krToc">
        <?php if (!$toc): ?><p><?php echo ame_l('এখনো অধ্যায় নেই।', 'No chapters yet.'); ?></p><?php endif; ?>
        <?php foreach ($toc as $ch): ?>
          <button type="button" data-go="<?php echo (int)$ch['page_no']; ?>"><?php echo esc_html($ch['title']); ?></button>
        <?php endforeach; ?>
      </div>
      <form id="krTocForm" class="krx-mini">
        <input name="title" placeholder="<?php echo ame_l('অধ্যায়ের নাম', 'Chapter name'); ?>">
        <button type="submit"><?php echo ame_l('যোগ', 'Add'); ?></button>
      </form>
      <h2><?php echo ame_l('বুকমার্ক', 'Bookmarks'); ?></h2>
      <div id="krMarks">
        <?php if (!$folds): ?><p><?php echo ame_l('বুকমার্ক নেই।', 'No bookmarks.'); ?></p><?php endif; ?>
        <?php foreach ($folds as $f): ?>
          <button type="button" data-go="<?php echo (int)$f['page_no']; ?>"><?php echo esc_html(trim(((string)($f['title'] ?? '')) !== '' ? $f['title'] : ame_l('পাতা', 'Page') . ' ' . (int)$f['page_no'])); ?></button>
        <?php endforeach; ?>
      </div>
      <h2><?php echo ame_l('নোট', 'Notes'); ?></h2>
      <div id="krNotes">
        <?php foreach ($notes as $n): ?>
          <button type="button" data-go="<?php echo (int)$n['page_no']; ?>"><?php echo esc_html($n['note_text']); ?></button>
        <?php endforeach; ?>
      </div>
      <h2><?php echo ame_l('হাইলাইট', 'Highlights'); ?></h2>
      <div id="krHigh"><?php foreach ($highs as $h): ?><button type="button" data-go="<?php echo (int)$h['page_no']; ?>"><?php echo esc_html($h['quote']); ?></button><?php endforeach; ?></div>
    </aside>
    <section class="krx-stage">
      <div class="krx-sheet" id="krSheet">
        <canvas id="krPdf" hidden></canvas>
        <canvas id="krPdf2" hidden></canvas>
        <img id="krScan" alt="" hidden>
        <div class="krx-leaf" id="krLeaf" hidden></div>
        <p class="krx-empty" id="krEmpty" hidden><?php echo esc_html($closed); ?></p>
      </div>
      <p class="krx-status" id="krStatus" role="status"></p>
      <div class="krx-ocr" id="krOcr"><?php echo esc_html($ocr); ?></div>
    </section>
  </div>
  <div class="krx-tools">
    <label><?php echo ame_l('পাতা', 'Page'); ?> <input id="krJump" type="number" min="1" value="<?php echo (int)$p; ?>"></label>
    <button type="button" id="krGo"><?php echo ame_l('যান', 'Go'); ?></button>
    <button type="button" id="krZoomOut" aria-label="<?php echo ame_l('ছোট', 'Zoom out'); ?>">−</button>
    <button type="button" id="krZoomIn" aria-label="<?php echo ame_l('বড়', 'Zoom in'); ?>">+</button>
    <button type="button" id="krFit"><?php echo ame_l('ফিট', 'Fit'); ?></button>
    <button type="button" id="krFull"><?php echo ame_l('পূর্ণ', 'Full'); ?></button>
    <button type="button" id="krSingle"><?php echo ame_l('এক পাতা', 'One page'); ?></button>
    <button type="button" id="krDouble"><?php echo ame_l('দুই পাতা', 'Two pages'); ?></button>
    <button type="button" id="krNight"><?php echo ame_l('রাত', 'Night'); ?></button>
    <button type="button" id="krLite"><?php echo ame_l('হালকা', 'Light'); ?></button>
    <button type="button" id="krSideBtn"><?php echo ame_l('তালিকা', 'List'); ?></button>
  </div>
  <form class="krx-search" id="krFind">
    <input id="krQ" placeholder="<?php echo ame_l('বইয়ের ভিতরে খুঁজুন', 'Search inside the book'); ?>">
    <button type="submit"><?php echo ame_l('খুঁজুন', 'Search'); ?></button>
  </form>
  <div id="krHits"></div>
  <form class="krx-note" id="krNoteForm">
    <input name="title" id="krMarkTitle" placeholder="<?php echo ame_l('বুকমার্কের নাম', 'Bookmark title'); ?>">
    <textarea name="note_text" id="krNoteText" placeholder="<?php echo ame_l('নোট', 'Note'); ?>"></textarea>
    <button type="submit" name="kind" value="mark"><?php echo ame_l('বুকমার্ক', 'Bookmark'); ?></button>
    <button type="submit" name="kind" value="note"><?php echo ame_l('নোট সেভ', 'Save note'); ?></button>
    <button type="button" id="krHiBtn"><?php echo ame_l('হাইলাইট', 'Highlight'); ?></button>
  </form>
  <div class="krx-bar">
    <button type="button" id="krPrev"><?php echo ame_l('আগের পাতা', 'Previous'); ?></button>
    <button type="button" id="krNext"><?php echo ame_l('পরের পাতা', 'Next'); ?></button>
    <select id="krState">
      <?php foreach ($labels as $key => $lab): ?>
        <option value="<?php echo esc_html($key); ?>"<?php echo $status === $key ? ' selected' : ''; ?>><?php echo esc_html($lab); ?></option>
      <?php endforeach; ?>
    </select>
    <button type="button" id="krVoicePlay"><?php echo ame_l('শুনুন', 'Listen'); ?></button>
    <button type="button" id="krVoiceStop"><?php echo ame_l('থামুন', 'Stop'); ?></button>
    <?php if (fam_can_write()): ?>
      <label class="krx-cam"><?php echo ame_l('ক্যামেরা', 'Camera'); ?><input id="krCam" type="file" accept="image/*" capture="environment" hidden></label>
    <?php endif; ?>
  </div>
</div>
<script type="application/json" id="kr-data"><?php
$voice = [];
foreach (array_slice($pages, 0, 40) as $vp) {
    $voice[] = ['no' => (int)$vp['page_no'], 'text' => ''];
}
$map = [];
foreach ($pageMap as $n => $row) {
    $file = trim((string)($row['image_file'] ?? ''));
    $map[] = ['no' => (int)$n, 'image' => $file !== '' ? ame_kitab_href($file) : ''];
}
echo json_encode([
    'id' => $id,
    'page' => $p,
    'total' => $total,
    'csrf' => function_exists('csrf_token') ? csrf_token() : '',
    'pdf' => $pdf !== '' ? ame_kitab_href($pdf) : '',
    'image' => $img !== '' ? ame_kitab_href($img) : '',
    'ocr' => $ocr,
    'language' => (string)($book['language'] ?? ''),
    'pages' => $voice,
    'map' => $map,
    'err' => $closed,
    'lite' => ame_l('হালকা পড়া চালু', 'Light reading is on'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
?></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" defer></script>
<script src="/assets/js/kitab-read.183.js" defer></script>
<script src="/assets/js/kitab-assist.817.js" defer></script>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
