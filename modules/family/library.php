<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_library', 'fam_lead_library');
echo '<link rel="stylesheet" href="/assets/css/family-os.817.css">';
echo '<script>document.querySelector(".fam-app")&&document.querySelector(".fam-app").setAttribute("data-desk","library");</script>';
$q = trim((string)($_GET['q'] ?? ''));
$cat = trim((string)($_GET['cat'] ?? ''));
$edit = !empty($_GET['id']) ? fam_one('SELECT * FROM family_books WHERE id=' . (int)$_GET['id']) : null;
$shelf = trim((string)($_GET['shelf'] ?? ''));
$statusF = trim((string)($_GET['status'] ?? ''));
$langF = trim((string)($_GET['lang'] ?? ''));
$authorF = trim((string)($_GET['author'] ?? ''));
$favF = (string)($_GET['fav'] ?? '');
$uid = function_exists('ame_kitab_uid') ? ame_kitab_uid() : (int)($_SESSION['user_id'] ?? 0);
$sql = 'SELECT b.*, m.name AS member_name, p.page_no AS prog_page, p.updated_at AS prog_at FROM family_books b LEFT JOIN family_members m ON m.id=b.member_id LEFT JOIN family_book_progress p ON p.book_id=b.id AND p.user_id=' . (int)$uid . ' WHERE b.archived_at IS NULL';
if (!fam_can_write()) {
    $sql .= " AND (b.visibility IS NULL OR b.visibility='' OR b.visibility='family')";
}
if ($cat !== '') {
    $sql .= " AND b.subject='" . fam_db()->real_escape_string($cat) . "'";
}
if ($q !== '') {
    $l = fam_db()->real_escape_string($q);
    $sql .= " AND (b.title LIKE '%$l%' OR b.subtitle LIKE '%$l%' OR b.author LIKE '%$l%' OR b.publisher LIKE '%$l%' OR b.subject LIKE '%$l%' OR b.tags LIKE '%$l%')";
}
if ($statusF !== '') {
    $sql .= " AND b.read_status='" . fam_db()->real_escape_string($statusF) . "'";
}
if ($langF !== '') {
    $sql .= " AND b.language='" . fam_db()->real_escape_string($langF) . "'";
}
if ($authorF !== '') {
    $sql .= " AND b.author='" . fam_db()->real_escape_string($authorF) . "'";
}
if ($favF === '1') {
    $sql .= ' AND b.favorite=1';
}
if ($shelf === 'reading') {
    $sql .= " AND b.read_status='reading'";
} elseif ($shelf === 'done') {
    $sql .= " AND b.read_status='completed'";
} elseif ($shelf === 'fav') {
    $sql .= ' AND b.favorite=1';
} elseif ($shelf === 'marked') {
    $sql .= ' AND EXISTS (SELECT 1 FROM family_book_folds f WHERE f.book_id=b.id)';
}
$sql .= $shelf === 'recent' ? ' ORDER BY p.updated_at DESC, b.id DESC' : ' ORDER BY b.id DESC';
$rows = fam_rows($sql);
$n = count($rows);
$havePages = [];
if ($edit) {
    $havePages = fam_rows('SELECT page_no, image_file, ocr_text FROM family_book_pages WHERE book_id=' . (int)$edit['id'] . ' ORDER BY page_no');
}
$known = ['titles' => [], 'authors' => [], 'publishers' => [], 'books' => [], 'people' => []];
$mems = fam_rows('SELECT name FROM family_members ORDER BY id DESC LIMIT 400') ?: [];
foreach ($mems as $m) {
    $nm = trim((string)($m['name'] ?? ''));
    if ($nm !== '') {
        $known['people'][] = $nm;
    }
}
foreach (array_slice($rows, 0, 400) as $kb) {
    $t = trim((string)($kb['title'] ?? ''));
    $a = trim((string)($kb['author'] ?? ''));
    $pbl = trim((string)($kb['publisher'] ?? ''));
    if ($t !== '') {
        $known['titles'][] = $t;
    }
    if ($a !== '') {
        $known['authors'][] = $a;
    }
    if ($pbl !== '') {
        $known['publishers'][] = $pbl;
    }
    if ($t !== '') {
        $known['books'][] = [
            'title' => $t,
            'author' => $a,
            'publisher' => $pbl,
            'subject' => (string)($kb['subject'] ?? ''),
        ];
    }
}
?>
<div class="kh-wrap">
  <header class="kh-hero">
    <p class="kh-kicker"><?php echo ame_pair('কিতাব স্টুডিও', 'Kitab studio'); ?></p>
    <h2><?php echo ame_pair('বই তৈরি — স্ক্যান, টাইপসেট, সংরক্ষণ', 'Make a book — scan, typeset, save'); ?></h2>
    <p><?php echo ame_pair(
        'একটা পেজ খুলুন। নাম লিখলে সফটওয়্যার চেনে ও বানান শোধরায়। স্ক্যান বা পেস্ট করুন। বইয়ের সেই লেখা পুরুষ কণ্ঠে শোনা যায় — ছবি সেভ হয় না।',
        'Open one page. Names are recognised and misspellings are corrected. Scan or paste. That same book text plays in a male voice. Photos are not stored.'
    ); ?></p>
  </header>

  <nav class="kh-cats fam-chips" aria-label="<?php echo esc_html('বিষয়'); ?>">
    <a class="fam-chip<?php echo $cat === '' ? ' on' : ''; ?>" href="library.php"><?php echo ame_pair('সব', 'All'); ?></a>
    <?php foreach (fam_book_cats() as $c): ?>
      <a class="fam-chip<?php echo $cat === $c ? ' on' : ''; ?>" href="library.php?cat=<?php echo urlencode($c); ?>"><?php echo esc_html($c); ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="kh-stage">
    <?php if (fam_can_write()): ?>
    <section class="kh-folio">
      <h2><?php echo $edit ? ame_pair('বই সম্পাদনা', 'Edit book') : ame_pair('নতুন বই তৈরি', 'Create a new book'); ?></h2>
      <ol class="kh-steps" aria-label="<?php echo esc_html('ধাপ'); ?>">
        <li class="on"><?php echo ame_pair('১. তথ্য', '1. Details'); ?></li>
        <li class="on"><?php echo ame_pair('২. পেজ', '2. Pages'); ?></li>
        <li><?php echo ame_pair('৩. সংরক্ষণ', '3. Save'); ?></li>
      </ol>
      <form class="fam-form" id="khBookForm" method="post" action="library_process.php" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="do" value="kitab_save">
        <input type="hidden" name="back" value="/modules/family/library.php">
        <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
        <label><?php echo ame_pair('নাম', 'Title'); ?><input name="title" required spellcheck="true" lang="bn" autocomplete="off" value="<?php echo esc_html($edit['title'] ?? ''); ?>"><div class="kh-smart-box" id="khHintTitle"></div></label>
        <div class="row2">
          <label><?php echo ame_pair('লেখক', 'Author'); ?><input name="author" spellcheck="true" lang="bn" autocomplete="off" value="<?php echo esc_html($edit['author'] ?? ''); ?>"><div class="kh-smart-box" id="khHintAuthor"></div></label>
          <label><?php echo ame_pair('প্রকাশনী', 'Publisher'); ?><input name="publisher" spellcheck="true" lang="bn" autocomplete="off" value="<?php echo esc_html($edit['publisher'] ?? ''); ?>"><div class="kh-smart-box" id="khHintPub"></div></label>
        </div>
        <div class="row2">
          <label><?php echo ame_pair('বিষয়', 'Subject'); ?><select name="subject"><?php echo fam_opts(fam_book_cats(), $edit['subject'] ?? ''); ?></select></label>
          <label><?php echo ame_pair('ভাষা', 'Language'); ?><input name="language" value="<?php echo esc_html($edit['language'] ?? 'বাংলা'); ?>"></label>
        </div>
        <div class="row2">
          <label><?php echo ame_l('উপশিরোনাম', 'Subtitle'); ?><input name="subtitle" value="<?php echo esc_html($edit['subtitle'] ?? ''); ?>"></label>
          <label><?php echo ame_l('অনুবাদক', 'Translator'); ?><input name="translator" value="<?php echo esc_html($edit['translator'] ?? ''); ?>"></label>
        </div>
        <div class="row2">
          <label><?php echo ame_l('প্রকাশের বছর', 'Year'); ?><input name="pub_year" value="<?php echo esc_html($edit['pub_year'] ?? ''); ?>"></label>
          <label><?php echo ame_l('সংস্করণ', 'Edition'); ?><input name="edition" value="<?php echo esc_html($edit['edition'] ?? ''); ?>"></label>
        </div>
        <div class="row2">
          <label><?php echo ame_l('উপবিভাগ', 'Subcategory'); ?><input name="subcategory" value="<?php echo esc_html($edit['subcategory'] ?? ''); ?>"></label>
          <label><?php echo ame_l('পড়ার অবস্থা', 'Reading status'); ?><select name="read_status">
            <?php foreach (['not_started' => ame_l('শুরু হয়নি', 'Not started'), 'reading' => ame_l('পড়ছি', 'Reading'), 'paused' => ame_l('থামা', 'Paused'), 'completed' => ame_l('শেষ', 'Completed')] as $sk => $sl): ?>
              <option value="<?php echo esc_html($sk); ?>"<?php echo (($edit['read_status'] ?? '') === $sk) ? ' selected' : ''; ?>><?php echo esc_html($sl); ?></option>
            <?php endforeach; ?>
          </select></label>
        </div>
        <label><?php echo ame_l('ট্যাগ', 'Tags'); ?><input name="tags" value="<?php echo esc_html($edit['tags'] ?? ''); ?>" placeholder="<?php echo ame_l('ইসলামিক, ইতিহাস, শিক্ষা', 'Islamic, history, education'); ?>"></label>
        <label><?php echo ame_l('বিবরণ', 'Description'); ?><textarea name="description"><?php echo esc_html($edit['description'] ?? ''); ?></textarea></label>
        <label><?php echo ame_l('প্রচ্ছদ', 'Cover'); ?><input type="file" name="cover_file" accept="image/*"></label>
        <label><input type="checkbox" name="favorite" <?php echo !empty($edit['favorite']) ? 'checked' : ''; ?>> <?php echo ame_l('প্রিয়', 'Favorite'); ?></label>
        <label><?php echo ame_l('কারা দেখবে', 'Who can open it'); ?><select name="visibility"><option value="family"><?php echo ame_l('পরিবার', 'Family'); ?></option><option value="private"<?php echo (($edit['visibility'] ?? '') === 'private') ? ' selected' : ''; ?>><?php echo ame_l('শুধু লেখার অনুমতি', 'Editors only'); ?></option></select></label>
        <div class="row2">
          <label><?php echo ame_l('অধ্যায়', 'Chapter'); ?><input name="toc_title[]"></label>
          <label><?php echo ame_l('পাতা', 'Page'); ?><input name="toc_page[]" type="number" min="1" value="1"></label>
        </div>
        <div class="row2">
          <label><?php echo ame_pair('কপি', 'Copies'); ?><input name="copies" type="number" value="<?php echo esc_html($edit['copies'] ?? '1'); ?>"></label>
          <label><?php echo ame_pair('দাম', 'Price'); ?><input name="price" type="number" step="0.01" value="<?php echo esc_html($edit['price'] ?? ''); ?>"></label>
        </div>
        <div class="row2">
          <label><?php echo ame_pair('ক্রয়ের তারিখ', 'Buy date'); ?><input type="date" name="buy_date" value="<?php echo esc_html($edit['buy_date'] ?? date('Y-m-d')); ?>"></label>
          <label><?php echo ame_pair('কোথা থেকে', 'Source'); ?><input name="source" value="<?php echo esc_html($edit['source'] ?? ''); ?>"></label>
        </div>
        <label><?php echo ame_pair('কার জন্য', 'For whom'); ?><select name="member_id"><?php echo fam_member_opts($edit['member_id'] ?? null); ?></select></label>
        <label><?php echo ame_pair('কোথায় রাখা', 'Shelf note'); ?><input name="location_note" value="<?php echo esc_html($edit['location_note'] ?? ''); ?>"></label>
        <label><input type="checkbox" name="is_read" <?php echo !empty($edit['is_read']) ? 'checked' : ''; ?>> <?php echo ame_pair('পড়া হয়েছে', 'Already read'); ?></label>
        <label><?php echo ame_pair('নোট', 'Notes'); ?><textarea name="notes"><?php echo esc_html($edit['notes'] ?? ''); ?></textarea></label>

        <div class="kh-studio" id="khScan" data-start="<?php echo count($havePages); ?>">
          <h3><?php echo ame_pair('একটা করে পেজ খুলুন', 'Open one page at a time'); ?></h3>
          <p class="kh-hint"><?php echo ame_pair(
              'পেজ ১ খুলুন। স্ক্যানার খুললে QR-এর মতো সব লেখা কপি হয়ে এখানে বসবে। নিজে পেস্টও করতে পারবেন। তারপর পেজ ২ খুলুন। ছবি রাখা হয় না।',
              'Open page 1. The scanner copies all text onto it like a QR payload. You can also paste. Then open page 2. Photos are not stored.'
          ); ?></p>
          <?php if ($havePages): ?>
          <p class="kh-scan-count"><?php echo ame_pair(count($havePages) . ' পেজ আগে থেকে এন্ট্রি আছে', count($havePages) . ' pages already stored'); ?></p>
          <div class="kh-thumbs kh-thumbs-saved">
            <?php foreach ($havePages as $pg): ?>
              <a class="kh-thumb" href="read.php?id=<?php echo (int)$edit['id']; ?>&amp;p=<?php echo (int)$pg['page_no']; ?>">
                <?php if (trim((string)$pg['image_file']) !== ''): ?>
                  <img src="<?php echo esc_html(function_exists('ame_kitab_href') ? ame_kitab_href($pg['image_file']) : '/uploads/family/' . $pg['image_file']); ?>" alt="">
                <?php endif; ?>
                <b><?php echo ame_pair('পেজ ' . (int)$pg['page_no'], 'Page ' . (int)$pg['page_no']); ?></b>
              </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <div class="kh-paper-bar">
            <button type="button" id="khBlank"><?php echo ame_pair('সাদা পেজ খুলুন', 'Open a blank page'); ?></button>
            <button type="button" class="kh-cam" id="khLiveOn"><?php echo ame_pair('স্ক্যানার খুলুন', 'Open scanner'); ?></button>
            <label class="kh-gal"><?php echo ame_pair('গ্যালারি থেকে লেখা', 'Text from gallery'); ?>
              <input id="khGal" type="file" accept="image/*">
            </label>
            <label class="kh-cam" hidden>ক্যামেরা
              <input id="khCam" type="file" accept="image/*" capture="environment">
            </label>
          </div>

          <div class="kh-finder" id="khFinder" hidden>
            <video id="khVideo" playsinline autoplay muted></video>
            <div class="kh-frame" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
            <span class="kh-laser" aria-hidden="true"></span>
            <p class="kh-finder-status" id="khLiveStatus"><?php echo esc_html('পাতা ফ্রেমে রাখুন'); ?></p>
            <div class="kh-finder-bar">
              <button type="button" id="khShutter"><?php echo ame_pair('স্ক্যান · সব লেখা কপি', 'Scan · copy all text'); ?></button>
              <button type="button" id="khLiveOff"><?php echo ame_pair('বন্ধ', 'Close'); ?></button>
            </div>
          </div>

          <p class="kh-scan-count" id="khScanCount"><?php echo ame_pair('এখনো কোনো পেজ খোলা হয়নি', 'No page open yet'); ?></p>
          <div class="kh-page-list" id="khPageList"></div>
          <p class="kh-status" id="khScanStatus"></p>
          <div id="khOpenPage"></div>
          <div class="kh-smart-box" id="khHintPage"></div>
          <div id="khLeafBag"></div>
          <p class="kh-voice-note"><?php echo ame_pair('বইয়ের যে লেখা আছে, সেটাই পুরুষ কণ্ঠে পড়া হবে।', 'The book text you already have is read in a male voice.'); ?></p>
          <div class="kh-voice-bar">
            <button type="button" id="khVoicePlay"><?php echo ame_pair('পুরুষ কণ্ঠে এই পেজ', 'Male voice · this page'); ?></button>
            <button type="button" id="khVoiceAll"><?php echo ame_pair('পুরুষ কণ্ঠে সব লেখা', 'Male voice · all text'); ?></button>
            <button type="button" id="khVoiceStop"><?php echo ame_pair('থামুন', 'Stop'); ?></button>
          </div>
          <p class="kh-status" id="khVoiceStatus"></p>
          <label><?php echo ame_pair('অথবা পুরো PDF', 'Or a full PDF'); ?><input type="file" name="pdf_file" accept="application/pdf"></label>
        </div>

        <button class="fam-btn kh-save" type="submit"><?php echo ame_pair('বই তৈরি + পেজ এন্ট্রি', 'Create book + enter pages'); ?></button>
      </form>
    </section>
    <?php endif; ?>

    <section class="kh-stacks">
      <nav class="fam-chips">
        <?php
        $shelves = [
            '' => ame_l('আমার বই', 'My books'),
            'recent' => ame_l('সম্প্রতি পড়া', 'Recently read'),
            'reading' => ame_l('পড়ছি', 'Reading'),
            'done' => ame_l('শেষ', 'Completed'),
            'fav' => ame_l('প্রিয়', 'Favorites'),
            'marked' => ame_l('বুকমার্ক', 'Bookmarked'),
        ];
        foreach ($shelves as $key => $label):
            $href = 'library.php' . ($key !== '' ? '?shelf=' . urlencode($key) : '');
        ?>
          <a class="fam-chip<?php echo $shelf === $key ? ' on' : ''; ?>" href="<?php echo esc_html($href); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
      </nav>
      <h2><?php echo ame_pair($n . ' টি বই — আনলিমিটেড শেলফ', $n . ' books — unlimited shelf'); ?></h2>
      <?php if (!$rows): ?>
        <div class="kh-empty">
          <div class="kh-plank" aria-hidden="true">
            <div class="kh-spines">
              <span class="kh-spine ghost"></span>
              <span class="kh-spine ghost"></span>
              <span class="kh-spine ghost"></span>
              <span class="kh-spine ghost"></span>
              <span class="kh-spine ghost"></span>
            </div>
          </div>
          <strong><?php echo ame_pair('শেলফ খালি — প্রথম কিতাব তৈরি করুন।', 'Shelf is empty — create the first kitab.'); ?></strong>
        </div>
      <?php else: ?>
        <div class="kh-plank">
          <div class="kh-spines" aria-hidden="true">
            <?php for ($i = 0; $i < min(5, max(3, $n)); $i++): ?>
              <span class="kh-spine"></span>
            <?php endfor; ?>
          </div>
        </div>
        <?php foreach ($rows as $b):
            $pc = (int)($b['page_count'] ?? 0);
            $last = max(1, (int)($b['prog_page'] ?? $b['last_page'] ?? 1));
            $hasFile = trim((string)($b['pdf_file'] ?? '')) !== '' || $pc > 0;
            $pct = $pc > 0 ? (int)min(100, round($last / max(1, $pc) * 100)) : 0;
        ?>
          <article class="kh-book">
            <i class="spine" aria-hidden="true"></i>
            <div>
              <strong><?php echo esc_html($b['title']); ?></strong>
              <small><?php echo esc_html(trim($b['author'] . ' · ' . $b['subject'] . ' · ' . ($b['member_name'] ?: '') . ' · ' . ($b['is_read'] ? 'পড়া' : 'অপঠিত'), ' ·')); ?></small>
              <em><?php echo $pc > 0 ? ($last . ' / ' . $pc . ' — ' . $pct . '%') : ($hasFile ? 'PDF' : ame_l('এখনো ফাইল নেই', 'No file yet')); ?><?php echo !empty($b['prog_at']) ? ' · ' . esc_html(substr((string)$b['prog_at'], 0, 16)) : ''; ?></em>
            </div>
            <div class="kh-actions">
              <b><?php echo $b['price'] > 0 ? fam_money_txt($b['price']) : ''; ?></b>
              <a class="kh-read" href="read.php?id=<?php echo (int)$b['id']; ?>&amp;p=<?php echo (int)$last; ?>"><?php echo $last > 1 ? ame_l('চালিয়ে পড়ুন', 'Continue') : ame_pair('পড়া', 'Read'); ?></a>
              <?php if (fam_can_write()): ?><a class="kh-edit" href="library.php?id=<?php echo (int)$b['id']; ?>"><?php echo ame_pair('সম্পাদনা', 'Edit'); ?></a><?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </div>
</div>
<script type="application/json" id="kh-known"><?php echo json_encode($known, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
<script src="/assets/js/kitab-studio.816.js" defer></script>
<script src="/assets/js/kitab-assist.817.js" defer></script>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
