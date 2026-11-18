<?php
require_once '../../includes/config.php';
require_once '../../includes/easy_update_functions.php';
require_once '../../includes/update_backup_functions.php';
admin_only();

$verFile = dirname(__DIR__, 2) . '/VERSION.txt';
$fromFile = is_file($verFile) ? trim((string) file_get_contents($verFile)) : '';
$current = $fromFile !== '' ? $fromFile : (defined('AME_APP_VERSION') ? AME_APP_VERSION : '?');
$zip_ok = ame_easy_update_zip_available();
$backups = ame_update_backup_list();
$hintUrl = ame_easy_update_read_hint_url();
$directGetZips = function_exists('ame_easy_update_list_direct_get_urls')
    ? ame_easy_update_list_direct_get_urls()
    : [];

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
    /* ===== APONSHOP ইঞ্জিন রুম — সহজ আপডেট ===== */
    .eroom { --brass:#E8A93D; --brass-dk:#9A6A1B; --steel:#0B1526; --steel2:#12233D; --pipe:#1E3A5F; --glow:#35C26E; --danger:#E53935; --amber:#FFB300;
        max-width: 620px; margin: 0 auto; padding: 14px 12px 26px; color: #EAF2FF;
        background:
            radial-gradient(1200px 300px at 50% -80px, rgba(232,169,61,.14), transparent 60%),
            repeating-linear-gradient(90deg, rgba(255,255,255,.025) 0 2px, transparent 2px 90px),
            linear-gradient(180deg, #070D1A 0%, #0B1526 60%, #0A1220 100%);
        border-radius: 18px; position: relative; overflow: hidden;
    }
    .eroom::before { /* রিভেট সারি */
        content: ''; position: absolute; left: 10px; right: 10px; top: 8px; height: 10px;
        background-image: radial-gradient(circle, #3A4F6E 3px, #141F33 3.6px, transparent 4px);
        background-size: 34px 10px; background-repeat: repeat-x; opacity: .9;
    }
    .eroom::after { content: ''; position: absolute; left: 10px; right: 10px; bottom: 8px; height: 10px;
        background-image: radial-gradient(circle, #3A4F6E 3px, #141F33 3.6px, transparent 4px);
        background-size: 34px 10px; background-repeat: repeat-x; opacity: .9;
    }
    .eroom-name { text-align: center; margin: 14px 0 0; font-size: 15px; font-weight: 900; letter-spacing: 4px; color: var(--brass); }
    .eroom-title { text-align: center; margin: 2px 0 0; font-size: 30px; font-weight: 900; color: #fff; text-shadow: 0 2px 0 #000, 0 0 22px rgba(232,169,61,.35); }
    .eroom-ver { text-align: center; margin: 8px 0 0; }
    .eroom-ver span { display: inline-block; background: #101E33; border: 1px solid var(--brass); color: var(--brass); font-weight: 900; font-size: 15px; border-radius: 999px; padding: 6px 18px; box-shadow: inset 0 0 12px rgba(0,0,0,.6); }

    /* --- বড় চাকা + পাশের গিয়ার --- */
    .ewheel-stage { position: relative; width: 250px; height: 210px; margin: 10px auto 4px; }
    .ewheel { position: absolute; left: 8px; top: 8px; width: 180px; height: 180px; animation: ewSpin 16s linear infinite; filter: drop-shadow(0 6px 10px rgba(0,0,0,.6)); }
    .egear { position: absolute; right: 0; bottom: 0; width: 92px; height: 92px; animation: ewSpinRev 8s linear infinite; filter: drop-shadow(0 4px 8px rgba(0,0,0,.6)); }
    @keyframes ewSpin { to { transform: rotate(360deg); } }
    @keyframes ewSpinRev { to { transform: rotate(-360deg); } }
    body.eroom-running .ewheel { animation-duration: 1.1s; }
    body.eroom-running .egear { animation-duration: .55s; }
    .elamp-row { display: flex; justify-content: center; gap: 10px; margin: 2px 0 10px; }
    .elamp { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 800; color: #B9C8DE; background: #0E1B30; border: 1px solid #2A3F5D; border-radius: 999px; padding: 5px 12px 5px 8px; }
    .elamp i { width: 12px; height: 12px; border-radius: 50%; background: var(--glow); box-shadow: 0 0 10px var(--glow); }
    .elamp.warn i { background: var(--amber); box-shadow: 0 0 10px var(--amber); animation: eblink 1s steps(2) infinite; }
    body.eroom-running .elamp.run i { background: var(--amber); box-shadow: 0 0 12px var(--amber); animation: eblink .5s steps(2) infinite; }
    @keyframes eblink { 50% { opacity: .3; } }

    /* --- প্রেশার মিটার --- */
    .egauge { width: 210px; margin: 0 auto 12px; background: #0E1B30; border: 1px solid #2A3F5D; border-radius: 12px; padding: 8px 12px 10px; }
    .egauge-top { display: flex; justify-content: space-between; font-size: 11.5px; font-weight: 800; color: #8FA3BE; margin-bottom: 6px; }
    .egauge-bar { height: 12px; border-radius: 999px; background: #050B14; border: 1px solid #2A3F5D; overflow: hidden; }
    .egauge-fill { height: 100%; width: 18%; border-radius: 999px; background: linear-gradient(90deg, #0E8F52, #35C26E); transition: width 1.2s ease; box-shadow: 0 0 8px rgba(53,194,110,.7); }
    body.eroom-running .egauge-fill { width: 92%; background: linear-gradient(90deg, #E8A93D, #FFB300, #E53935); box-shadow: 0 0 10px rgba(255,179,0,.8); }

    /* --- প্যানেল/কার্ড --- */
    .eplate { background: linear-gradient(180deg, #13273F 0%, #0E1C30 100%); border: 1px solid #2A3F5D; border-top: 4px solid var(--brass); border-radius: 14px; padding: 14px; margin: 0 2px 12px; box-shadow: 0 4px 14px rgba(0,0,0,.4); font-size: 14px; line-height: 1.6; }
    .eplate b.etitle { color: var(--brass); display: block; margin-bottom: 6px; font-size: 16px; }
    .eplate code { background: #050B14; border: 1px solid #2A3F5D; border-radius: 6px; padding: 1px 7px; color: #FFD97A; font-size: 12.5px; }
    .eplate a { color: #7CC4FF; }
    .ewarn { background: linear-gradient(180deg, #3A2A08 0%, #241A05 100%); border: 1px solid var(--brass); border-radius: 14px; padding: 12px 14px; font-size: 13px; margin: 0 2px 12px; line-height: 1.55; color: #FFE3A6; }
    .eok { background: linear-gradient(180deg, #0B3B24 0%, #072417 100%); border: 1px solid var(--glow); border-radius: 14px; padding: 12px 14px; font-size: 14px; margin: 0 2px 12px; color: #C9F5DA; }
    .eerr { background: linear-gradient(180deg, #3D1010 0%, #220808 100%); border: 1px solid var(--danger); border-radius: 14px; padding: 12px 14px; font-size: 14px; margin: 0 2px 12px; color: #FFC9C9; }
    .emeta { font-size: 13px; color: #8FA3BE; margin: 10px 2px 0; text-align: center; line-height: 1.6; }

    /* --- ফাইল + বোতাম --- */
    .efile { width: 100%; box-sizing: border-box; padding: 14px; border: 2px dashed var(--brass); border-radius: 12px; background: #0A1424; color: #EAF2FF; font-size: 13px; margin: 10px 0; cursor: pointer; }
    .ebtn { width: 100%; padding: 16px; border: 0; border-radius: 12px; background: linear-gradient(135deg, #0E8F52, #35C26E); color: #fff; font-weight: 900; font-size: 19px; cursor: pointer; text-shadow: 0 1px 2px rgba(0,0,0,.4); box-shadow: 0 6px 18px rgba(14,143,82,.45), inset 0 1px 0 rgba(255,255,255,.35); position: relative; overflow: hidden; }
    .ebtn::after { content: ''; position: absolute; top: 0; bottom: 0; width: 60px; background: linear-gradient(100deg, transparent, rgba(255,255,255,.35), transparent); left: -80px; animation: eshine 3s ease-in-out infinite; }
    @keyframes eshine { 60%,100% { left: 110%; } }
    .ebtn:disabled { opacity: .6; cursor: wait; }
    body.eroom-running .ebtn { background: linear-gradient(135deg, #9A6A1B, #E8A93D); box-shadow: 0 6px 18px rgba(232,169,61,.5); }
    .ebtn.sec { background: #13273F; color: #EAF2FF; border: 1px solid var(--brass); margin-top: 8px; display: block; text-align: center; text-decoration: none; padding: 12px; border-radius: 10px; font-weight: 800; font-size: 14px; }
    .ebackup-row { display: flex; align-items: center; gap: 8px; background: #0A1424; border: 1px solid #2A3F5D; border-radius: 10px; padding: 8px 10px; }
    .ebackup-when { display: block; color: var(--brass); }
    .esteam { position: absolute; bottom: 20px; left: 12%; width: 14px; height: 14px; border-radius: 50%; background: rgba(255,255,255,.12); animation: esteam 4s ease-in infinite; pointer-events: none; }
    .esteam.s2 { left: auto; right: 14%; animation-delay: 1.3s; }
    .esteam.s3 { left: 50%; animation-delay: 2.6s; }
    @keyframes esteam { 0% { transform: translateY(0) scale(1); opacity: 0; } 20% { opacity: .8; } 100% { transform: translateY(-190px) scale(2.4); opacity: 0; } }
    @media (max-width: 480px) { .eroom-title { font-size: 24px; } .ewheel-stage { width: 220px; height: 190px; } .ewheel { width: 155px; height: 155px; } }
    /* থিম-ব্যাটল (ame-front-colors): [class*="panel"] সাদা করে + p/li/h1 রং বদলায় —
       eplate নামে panel-substring এড়ানো + উচ্চ-specificity ওভাররাইড। */
    html body #main-content .eroom .eplate { background: linear-gradient(180deg, #13273F 0%, #0E1C30 100%) !important; background-image: linear-gradient(180deg, #13273F 0%, #0E1C30 100%) !important; border: 1px solid #2A3F5D !important; border-top: 4px solid #E8A93D !important; color: #EAF2FF !important; }
    html body #main-content .eroom .eplate div, html body #main-content .eroom .eplate span, html body #main-content .eroom .eplate label { color: #EAF2FF !important; -webkit-text-fill-color: #EAF2FF !important; }
    html body #main-content .eroom .eplate b.etitle { color: #E8A93D !important; -webkit-text-fill-color: #E8A93D !important; }
    html body #main-content .eroom form#eupForm, html body #main-content .eroom form.ebackup-row { background-color: transparent !important; background-image: none !important; }
    html body #main-content .eroom h1.eroom-title { color: #fff !important; -webkit-text-fill-color: #fff !important; }
    html body #main-content .eroom p.eroom-name { color: #E8A93D !important; -webkit-text-fill-color: #E8A93D !important; }
    html body #main-content .eroom p.emeta { color: #B9C8DE !important; -webkit-text-fill-color: #B9C8DE !important; }
</style>

<div class="eroom">
    <div class="esteam"></div><div class="esteam s2"></div><div class="esteam s3"></div>
    <p class="eroom-name">APONSHOP ENGINE ROOM</p>
    <h1 class="eroom-title">🔧 ইঞ্জিন রুম</h1>
    <p class="eroom-ver"><span>সহজ আপডেট · v<?php echo htmlspecialchars($current, ENT_QUOTES, 'UTF-8'); ?></span></p>

    <div class="ewheel-stage" aria-hidden="true">
        <svg class="ewheel" viewBox="-100 -100 200 200">
            <g fill="#5A6B84">
                <?php for ($g = 0; $g < 12; $g++): ?>
                <rect x="-9" y="-80" width="18" height="26" rx="4" transform="rotate(<?php echo $g * 30; ?>)" />
                <?php endfor; ?>
            </g>
            <circle r="64" fill="#22344E" stroke="#E8A93D" stroke-width="5" />
            <circle r="52" fill="none" stroke="#0A1424" stroke-width="3" />
            <g fill="#E8A93D">
                <?php for ($s = 0; $s < 6; $s++): ?>
                <rect x="-7" y="-50" width="14" height="100" rx="7" transform="rotate(<?php echo $s * 30; ?>)" opacity="0.9" />
                <?php endfor; ?>
            </g>
            <circle r="20" fill="#0A1424" stroke="#E8A93D" stroke-width="4" />
            <circle r="7" fill="#35C26E" />
        </svg>
        <svg class="egear" viewBox="-100 -100 200 200">
            <g fill="#8A6A2B">
                <?php for ($g = 0; $g < 9; $g++): ?>
                <rect x="-11" y="-82" width="22" height="28" rx="4" transform="rotate(<?php echo $g * 40; ?>)" />
                <?php endfor; ?>
            </g>
            <circle r="60" fill="#2A3F5D" stroke="#E8A93D" stroke-width="6" />
            <circle r="22" fill="#0A1424" stroke="#E8A93D" stroke-width="4" />
            <circle r="8" fill="#E8A93D" />
        </svg>
    </div>

    <div class="elamp-row">
        <span class="elamp"><i></i>পাওয়ার</span>
        <span class="elamp run"><i></i>ইঞ্জিন</span>
        <span class="elamp warn"><i></i>চাপ</span>
    </div>
    <div class="egauge">
        <div class="egauge-top"><span>বয়লার চাপ</span><span>BOILER</span></div>
        <div class="egauge-bar"><div class="egauge-fill"></div></div>
    </div>

    <?php if (isset($_GET['done'])): ?>
    <div class="eok">
        <?php echo htmlspecialchars((string)($_SESSION['easy_update_message'] ?? 'আপডেট সম্পন্ন'), ENT_QUOTES, 'UTF-8'); ?>
        <?php unset($_SESSION['easy_update_message']); ?>
    </div>
    <p class="emeta"><a href="/v.php">ভার্সন দেখুন</a> · <a href="/import.php">ডাটাবেজ ইনপুট</a></p>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="eerr"><?php echo htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="ewarn">
        Hostinger ফোনে <b>৯ MB ZIP একবারে পোস্ট</b> করলে পেজ মরে যায় (ERR_HTTP2_PING_FAILED)।
        নিচে <b>লিংক থেকে সার্ভার নামান</b> চাপুন, অথবা ছোট PATCH ZIP খণ্ডে আপলোড হবে — পেজ বদলাবে না।
        ফাইলবিন সরাসরি ফাইল লিংক HTML নামান; আর্কাইভ লিংক ব্যবহার করুন।
        <code>.env</code> ও <code>uploads</code> ছবি থাকবে।
        স্পিড সিস্টেম (<code>SPEED.txt</code>) থাকবে — <a href="speed_system.php">সেটিংস → স্পিড সিস্টেম</a>। মুছবেন না।
    </div>

    <div class="eplate">
        <b class="etitle">🛢️ ধাপ ১ — ব্যাকআপ</b>
        প্রতিটা ইমপোর্টের ঠিক আগে ইঞ্জিন নিজে থেকেই একটা সেফটি ব্যাকআপ (বদলাতে যাওয়া ফাইল + সম্পূর্ণ ডাটাবেজ) নিয়ে রাখে — নিচে "ব্যাকআপ ও ফিরে যাওয়া" অংশে দেখতে পাবেন। চাইলে আলাদাভাবেও ডাউনলোড করে রাখতে পারেন:
        <a href="backup.php" class="ebtn sec">💾 ব্যাকআপ ডাউনলোড</a>
    </div>

    <?php if ($directGetZips !== []): ?>
    <div class="eplate">
        <b class="etitle">⬇️ সরাসরি ডাউনলোড — চাপলেই ZIP (এক ক্লিকে)</b>
        <p style="margin:0 0 10px;">লগইন ছাড়াই <code>/get/</code> — ৯ MB পোস্ট করবেন না; ছোট PATCH (~KB) নিন।</p>
        <?php foreach ($directGetZips as $item): ?>
            <?php
            $kb = max(1, (int)ceil(((int)$item['bytes']) / 1024));
            $url = (string)($item['url'] ?? '');
            $name = (string)($item['name'] ?? '');
            if ($url === '' || $name === '') {
                continue;
            }
            ?>
            <a class="ebtn sec" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" download="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
                এক ক্লিকে ডাউনলোড — <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?> (<?php echo $kb; ?> KB)
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="eplate">
        <b class="etitle">⚙️ ধাপ ২ — জিপ ইমপোর্ট (ইঞ্জিন চালু করুন)</b>
        <p style="margin:0 0 10px;">সার্ভার নিজে ফাইলবিন থেকে ZIP নামাবে — ফোনের বড় আপলোড লাগবে না।</p>
        <input type="url" id="eupZipUrl" class="efile" style="cursor:text;border-style:solid;" placeholder="https://filebin.net/archive/aponshopone495/zip" value="<?php echo htmlspecialchars($hintUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="ebtn" id="eupUrlBtn" <?php echo $zip_ok ? '' : 'disabled'; ?>>🔗 লিংক থেকে সার্ভার নামিয়ে ইমপোর্ট</button>
        <p style="margin:14px 0 8px;font-size:13px;color:#8FA3BE;">অথবা ফাইল বাছুন — খণ্ডে (২৫৬ KB) আপলোড হবে, পেজ <code>process_easy_update.php</code>-এ যাবে না।</p>
        <form action="process_easy_update.php" method="POST" enctype="multipart/form-data" id="eupForm">
            <?php echo csrf_field(); ?>
            <input type="file" name="update_zip" id="eupFile" class="efile" accept=".zip,application/zip" <?php echo $zip_ok ? '' : 'disabled'; ?>>
            <button type="submit" class="ebtn" id="eupBtn" <?php echo $zip_ok ? '' : 'disabled'; ?>>🔥 ইঞ্জিন চালু — ইমপোর্ট করুন</button>
        </form>
        <p id="eupProg" class="emeta" hidden></p>
        <?php if (!$zip_ok): ?>
        <div class="eerr" style="margin-top:8px;">সার্ভারে ZIP চালু নেই।</div>
        <?php endif; ?>
    </div>
    <p class="emeta">ইমপোর্টের পর <a href="/v.php">/v.php</a> খুলুন — উপরে দেখানো বর্তমান ভার্সন নম্বরটাই দেখলে কোড বসেছে। তারপর সিস্টেম ইউজার তালিকায় সরান চাপুন — সেই নাম তালিকা থেকে সরে যাবে।</p>

    <?php if (isset($_GET['rolled_back'])): ?>
    <div class="eok">
        <?php echo htmlspecialchars((string)($_SESSION['rollback_message'] ?? 'আগের অবস্থায় ফিরে যাওয়া সম্পন্ন।'), ENT_QUOTES, 'UTF-8'); ?>
        <?php unset($_SESSION['rollback_message']); ?>
    </div>
    <?php endif; ?>

    <div class="eplate">
        <b class="etitle">🕰️ ব্যাকআপ ও ফিরে যাওয়া</b>
        <?php if (!$backups): ?>
        এখনো কোনো স্বয়ংক্রিয় ব্যাকআপ নেই — প্রথমবার «ইমপোর্ট করুন» চাপলে সেই মুহূর্তেরটা এখানে জমা হবে।
        <?php else: ?>
        কোনো আপডেটের পর সমস্যা হলে নিচের যেকোনো সময়ে ফিরে যেতে পারবেন (সর্বশেষ ২টা রাখা হয়):
        <div style="margin-top:10px;display:flex;flex-direction:column;gap:8px;">
        <?php foreach ($backups as $b):
            $ts = (int)($b['created_at'] ?? 0);
            $when = $ts ? date('d M Y, h:i A', $ts) : '?';
            $kb = round(((int)($b['bytes'] ?? 0)) / 1024);
            $fv = (string)($b['from_version'] ?? '');
            $hasDb = !empty($b['db_file']);
        ?>
        <form action="process_update_rollback.php" method="POST" onsubmit="return confirm('এই ব্যাকআপে (<?php echo htmlspecialchars($when, ENT_QUOTES, 'UTF-8'); ?>) ফিরে যাবেন? এরপরের সব ফাইল-পরিবর্তন এবং <?php echo $hasDb ? 'ডাটাবেজের তথ্যও' : 'শুধু ফাইল'; ?> মুছে যাবে।');" class="ebackup-row">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$b['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <div style="flex:1;font-size:12.5px;line-height:1.5;">
                <b class="ebackup-when"><?php echo htmlspecialchars($when, ENT_QUOTES, 'UTF-8'); ?></b>
                <?php echo $fv !== '' ? ('ভার্সন v' . htmlspecialchars($fv, ENT_QUOTES, 'UTF-8') . ' থেকে · ') : ''; ?><?php echo $kb; ?> KB<?php echo $hasDb ? ' · ডাটাবেজসহ' : ' · শুধু ফাইল'; ?>
            </div>
            <?php if ($hasDb): ?>
            <label style="font-size:11px;display:flex;align-items:center;gap:3px;white-space:nowrap;">
                <input type="checkbox" name="restore_db" value="1"> DB-ও ফেরান
            </label>
            <?php endif; ?>
            <button type="submit" class="ebtn sec" style="width:auto;margin:0;padding:8px 12px;font-size:12.5px;">ফিরে যান</button>
        </form>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="/assets/js/ame-logo-spinner.js"></script>
<script>
(function () {
    var form = document.getElementById('eupForm');
    var btn = document.getElementById('eupBtn');
    var urlBtn = document.getElementById('eupUrlBtn');
    var urlBox = document.getElementById('eupZipUrl');
    var fileBox = document.getElementById('eupFile');
    var prog = document.getElementById('eupProg');
    var csrfInput = form ? form.querySelector('input[name="csrf_token"]') : null;
    var csrf = csrfInput ? csrfInput.value : (window.AME_CSRF_TOKEN || '');
    if (!form || !btn) return;

    function note(text) {
        if (!prog) return;
        prog.hidden = false;
        prog.textContent = text;
    }

    function spin(on, label) {
        document.body.classList.toggle('eroom-running', on);
        btn.disabled = on;
        if (urlBtn) urlBtn.disabled = on;
        if (typeof ameShowBtnLoading === 'function' && on) {
            ameShowBtnLoading(btn, label || 'ইঞ্জিন চলছে…');
        } else if (!on) {
            btn.textContent = '🔥 ইঞ্জিন চালু — ইমপোর্ট করুন';
        } else {
            btn.textContent = label || 'ইঞ্জিন চলছে…';
        }
    }

    function postForm(fields, file) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        if (file) fd.append('chunk', file, 'part.bin');
        return fetch('process_easy_update_put.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrf }
        }).then(function (res) {
            return res.text().then(function (text) {
                var json;
                try { json = JSON.parse(text); } catch (e) {
                    throw new Error('সার্ভার JSON দেয়নি। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।');
                }
                if (!res.ok || !json.ok) {
                    throw new Error(json.message || 'আপডেট ব্যর্থ।');
                }
                return json;
            });
        });
    }

    function done(json) {
        spin(false);
        note(json.message || 'আপডেট সম্পন্ন।');
        window.location = 'easy_update.php?done=1';
    }

    function fail(err) {
        spin(false);
        note('');
        alert(err && err.message ? err.message : 'আপডেট হয়নি।');
    }

    urlBtn && urlBtn.addEventListener('click', function () {
        var url = (urlBox && urlBox.value ? urlBox.value : '').trim();
        if (!url) {
            alert('ফাইলবিন আর্কাইভ লিংক দিন — যেমন …/archive/…/zip');
            return;
        }
        spin(true, 'সার্ভার ZIP নামাচ্ছে…');
        note('Hostinger সার্ভার লিংক থেকে নামছে — ফোনের আপলোড লাগবে না।');
        postForm({ action: 'from_url', zip_url: url }).then(done).catch(fail);
    });

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var file = fileBox && fileBox.files && fileBox.files[0];
        if (!file) {
            alert('একটা .zip ফাইল বাছুন। দুইটা একসাথে নয়।');
            return;
        }
        if (!/\.zip$/i.test(file.name)) {
            alert('শুধু .zip ফাইল চলবে।');
            return;
        }
        var chunkSize = 256 * 1024;
        var total = Math.max(1, Math.ceil(file.size / chunkSize));
        var uploadId = 'u' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
        spin(true, 'খণ্ডে আপলোড…');
        note('০/' + total + ' খণ্ড');

        var i = 0;
        function next() {
            if (i >= total) {
                note('ZIP জোড়া লাগিয়ে ইমপোর্ট হচ্ছে… পেজ খোলা রাখুন।');
                return postForm({ action: 'apply', upload_id: uploadId }).then(done);
            }
            var blob = file.slice(i * chunkSize, (i + 1) * chunkSize);
            var idx = i;
            return postForm({
                action: 'chunk',
                upload_id: uploadId,
                index: String(idx),
                total: String(total)
            }, blob).then(function () {
                i += 1;
                note(i + '/' + total + ' খণ্ড আপলোড');
                return next();
            });
        }
        next().catch(fail);
    });
})();
</script>
<?php include '../../partials/footer.php'; ?>
