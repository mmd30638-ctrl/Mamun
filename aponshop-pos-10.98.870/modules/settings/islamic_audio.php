<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/islamic_audio.php';
admin_only();
ame_islamic_audio_schema($db);

$ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.900';
$cs = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
$categories = ame_islamic_audio_categories();
include dirname(__DIR__, 2) . '/partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/islamic-audio-admin.css?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>">
<div class="ia-admin" id="iaAdmin" data-csrf="<?php echo $cs; ?>">
  <header class="ia-admin-head">
    <h1>🕌 ইসলামিক অডিও লাইব্রেরি</h1>
    <p class="ia-admin-sub">ফাইল আপলোড, MP3 লিংক, বা YouTube — অটো কম্প্রেশন ও সাউন্ড অপ্টিমাইজেশন</p>
  </header>

  <div class="ia-admin-grid">
    <section class="ia-admin-card">
      <h2>📁 ফাইল আপলোড</h2>
      <form id="iaFormUpload" class="ia-upload-form">
        <div class="ia-drop" id="iaDrop" tabindex="0">
          <input type="file" id="iaFile" name="audio" accept="audio/*,.mp3,.wav,.m4a,.ogg" hidden>
          <p class="ia-drop-lead">ড্র্যাগ করে ফেলুন অথবা ব্রাউজ করুন</p>
          <p class="ia-drop-hint">MP3, WAV — সর্বোচ্চ ১২০ MB</p>
          <button type="button" class="ia-btn ia-btn-ghost" id="iaPick">ফাইল বাছুন</button>
          <p class="ia-drop-name" id="iaFileName" hidden></p>
        </div>
        <label>শিরোনাম</label>
        <input type="text" name="title" id="iaTitle" maxlength="255" placeholder="অডিওর নাম">
        <label>বক্তা</label>
        <input type="text" name="speaker" maxlength="200" placeholder="বক্তার নাম">
        <label>ক্যাটাগরি</label>
        <select name="category">
          <?php foreach ($categories as $k => $label): ?>
          <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
        <label class="ia-check"><input type="checkbox" name="is_published" value="1" checked> হোমপেজে প্রকাশ</label>
        <button type="submit" class="ia-btn ia-btn-primary">আপলোড ও অপ্টিমাইজ</button>
      </form>
    </section>

    <section class="ia-admin-card">
      <h2>🔗 অডিও URL থেকে নিন</h2>
      <form id="iaFormUrl" class="ia-upload-form">
        <label>Direct MP3 / Audio Link</label>
        <input type="url" name="audio_url" required placeholder="https://example.com/audio.mp3" autocomplete="off">
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

    <section class="ia-admin-card ia-admin-card-yt">
      <h2>▶ YouTube ভিডিও / চ্যানেল / প্লেলিস্ট</h2>
      <form id="iaFormYt" class="ia-upload-form">
        <label>YouTube লিংক (ভিডিও, @চ্যানেল, বা প্লেলিস্ট)</label>
        <input type="url" name="youtube_url" required placeholder="https://youtube.com/@channel ... বা youtu.be/..." autocomplete="off">
        <p class="ia-yt-hint">চ্যানেল লিংক (যেমন <code>@shamim7912</code>) দিলে স্বয়ংক্রিয়ভাবে সেই চ্যানেলের <strong>Videos</strong> তালিকা থেকে অডিও নামাবে। সার্ভারে <strong>yt-dlp</strong> + <strong>ffmpeg</strong> লাগবে।</p>
        <label>একবারে কয়টি ভিডিও (চ্যানেল/প্লেলিস্ট)</label>
        <input type="number" name="max_items" min="1" max="50" value="25" style="width:120px">
        <label>শিরোনাম (ঐচ্ছিক — এক ভিডিও হলে; না দিলে YouTube শিরোনাম)</label>
        <input type="text" name="title" maxlength="255" placeholder="অডিওর নাম">
        <label>বক্তা / চ্যানেল</label>
        <input type="text" name="speaker" maxlength="200" placeholder="যেমন: চ্যানেলের নাম">
        <label>ক্যাটাগরি</label>
        <select name="category">
          <?php foreach ($categories as $k => $label): ?>
          <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
        <label class="ia-check"><input type="checkbox" name="is_published" value="1" checked> হোমপেজে প্রকাশ</label>
        <button type="submit" class="ia-btn ia-btn-primary ia-btn-yt">YouTube থেকে অডিও নিন</button>
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