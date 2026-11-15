<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/islamic_audio.php';
admin_only();
ame_islamic_audio_schema($db);

$ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.900';
$cs = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
$categories = ame_islamic_audio_categories();
$ffmpegOk = ame_islamic_audio_ffmpeg_bin() !== null;
include dirname(__DIR__, 2) . '/partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/islamic-audio-admin.css?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>">
<div class="ia-admin" id="iaAdmin" data-csrf="<?php echo $cs; ?>">
  <header class="ia-admin-head">
    <h1>🕌 ইসলামিক অডিও লাইব্রেরি</h1>
    <p class="ia-admin-sub">PC ও মোবাইল থেকে অডিও আপলোড · অথবা Direct MP3 লিংক</p>
    <?php if (!$ffmpegOk): ?>
    <p class="ia-ffmpeg-note">নোট: সার্ভারে ffmpeg নেই — আপলোড হবে, কম্প্রেশন সীমিত থাকতে পারে।</p>
    <?php endif; ?>
  </header>

  <div class="ia-admin-grid">
    <section class="ia-admin-card ia-upload-hero">
      <h2>📲 PC ও মোবাইল — সরাসরি অডিও আপলোড</h2>
      <p class="ia-upload-lead">যেমন PC-তে yt-dlp দিয়ে নামানো MP3, বা ফোনের গ্যালারি থেকে ওয়াজ/গজল — এখানে দিন।</p>
      <form id="iaFormUpload" class="ia-upload-form">
        <input type="file" id="iaFile" name="audio[]" accept="audio/*,.mp3,.wav,.m4a,.ogg,.aac,.flac,.opus,.webm,video/mp4" multiple hidden>
        <div class="ia-pick-row">
          <button type="button" class="ia-btn ia-btn-mobile-pick" id="iaPickMobile">📂 মোবাইল / গ্যালারি থেকে বাছুন</button>
          <button type="button" class="ia-btn ia-btn-ghost ia-pick-pc" id="iaPick">💻 PC — এক বা একাধিক ফাইল</button>
        </div>
        <div class="ia-drop ia-drop-pc" id="iaDrop" tabindex="0">
          <p class="ia-drop-lead">PC: এখানে ড্র্যাগ করে ফেলুন</p>
          <p class="ia-drop-hint">MP3, M4A, WAV — প্রতি ফাইল সর্বোচ্চ ১২০ MB · একসাথে ৪০টি পর্যন্ত</p>
        </div>
        <ul class="ia-file-queue" id="iaFileQueue" hidden></ul>
        <div class="ia-upload-progress" id="iaUploadProgress" hidden>
          <div class="ia-upload-progress-bar" id="iaUploadBar"></div>
          <span class="ia-upload-progress-txt" id="iaUploadTxt"></span>
        </div>
        <label>শিরোনাম (একটিমাত্র ফাইল হলে; একাধিক হলে ফাইলের নাম থেকে)</label>
        <input type="text" name="title" id="iaTitle" maxlength="255" placeholder="ঐচ্ছিক">
        <label>বক্তা / চ্যানেল (সব ফাইলে একই)</label>
        <input type="text" name="speaker" id="iaSpeaker" maxlength="200" placeholder="যেমন: মাওলানা …">
        <label>ক্যাটাগরি</label>
        <select name="category" id="iaCategory">
          <?php foreach ($categories as $k => $label): ?>
          <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
        <label class="ia-check"><input type="checkbox" name="is_published" value="1" checked> হোমপেজে প্রকাশ</label>
        <button type="submit" class="ia-btn ia-btn-primary ia-btn-upload" id="iaUploadBtn" disabled>আপলোড ও অপ্টিমাইজ</button>
      </form>
    </section>

    <section class="ia-admin-card">
      <h2>🔗 Direct MP3 লিংক</h2>
      <form id="iaFormUrl" class="ia-upload-form">
        <label>MP3 / অডিও ফাইলের সরাসরি URL (YouTube লিংক নয়)</label>
        <input type="url" name="audio_url" required placeholder="https://example.com/path/audio.mp3" autocomplete="off">
        <label>শিরোনাম</label>
        <input type="text" name="title" maxlength="255" placeholder="অডিওর নাম">
        <label>বক্তা</label>
        <input type="text" name="speaker" maxlength="200">
        <label>ক্যাটাগরি</label>
        <select name="category">
          <?php foreach ($categories as $k => $label): ?>
          <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
        <label class="ia-check"><input type="checkbox" name="is_published" value="1" checked> হোমপেজে প্রকাশ</label>
        <button type="submit" class="ia-btn ia-btn-primary">লিংক ডাউনলোড + অপ্টিমাইজ</button>
      </form>
    </section>
  </div>

  <section class="ia-admin-card ia-admin-table-wrap">
    <h2>📋 অডিও তালিকা</h2>
    <p class="ia-admin-note" id="iaStatus">লোড হচ্ছে…</p>
    <div class="ia-table-scroll">
      <table class="ia-table" id="iaTable">
        <thead>
          <tr>
            <th>#</th>
            <th>শিরোনাম</th>
            <th>বক্তা</th>
            <th>ক্যাটাগরি</th>
            <th>MB</th>
            <th>প্রকাশ</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="iaTableBody"></tbody>
      </table>
    </div>
  </section>

  <a class="ia-back" href="/modules/settings/tools.php">← ইঞ্জিন মেনু</a>
</div>
<script src="/assets/js/islamic-audio-admin.js?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include dirname(__DIR__, 2) . '/partials/footer.php'; ?>