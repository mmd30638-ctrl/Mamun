<?php
require_once '../../includes/config.php';
admin_only();
include '../../partials/header.php';

$db_name_result = $db->query("SELECT DATABASE() as dbname")->fetch_assoc();
$db_name = $db_name_result['dbname'] ?? 'অজানা';

$unlock_until = (int)($_SESSION['danger_tools_unlocked_until'] ?? 0);
if ($unlock_until > 0 && $unlock_until < time()) {
    unset($_SESSION['danger_tools_unlocked'], $_SESSION['danger_tools_unlocked_until']);
}
$danger_unlocked = !empty($_SESSION['danger_tools_unlocked']);

$ame_app_ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '?';
$ame_recent_err = function_exists('ame_recent_error_count') ? ame_recent_error_count(24) : 0;
$ame_eng_ok = $ame_recent_err === 0;
require_once __DIR__ . '/../../includes/leftover_cleanup.php';
require_once __DIR__ . '/../../includes/db_backup.php';
$ame_leftovers = function_exists('ame_leftover_demo_scan') ? ame_leftover_demo_scan() : [];
$ame_upload_leftovers = function_exists('ame_uploads_legacy_code_scan') ? ame_uploads_legacy_code_scan() : [];
?>
<script src="/assets/js/device-display.js?v=8.01"></script>
<style>
.eng-console { max-width: 860px; margin: 12px auto; padding: 14px; }
.eng-core { background: linear-gradient(160deg, #050C1F 0%, #0B1E4D 60%, #12306E 100%); border-radius: 20px; padding: 22px 18px; color: #fff; text-align: center; position: relative; overflow: hidden; border: 1px solid #1E3A6E; box-shadow: 0 10px 30px rgba(5,12,31,.45); }
.eng-core::before { content: ''; position: absolute; inset: -40%; background: conic-gradient(from 0deg, transparent 0 40%, rgba(29,155,240,.14) 50%, transparent 60% 100%); animation: engSweep 7s linear infinite; pointer-events: none; }
@keyframes engSweep { to { transform: rotate(360deg); } }
.eng-reactor { width: 92px; height: 92px; margin: 0 auto 10px; border-radius: 50%; position: relative; background: radial-gradient(circle at 50% 50%, #69F0AE 0 18%, #0E8F52 30%, #053 62%, transparent 72%); box-shadow: 0 0 24px rgba(105,240,174,.55), inset 0 0 18px rgba(0,0,0,.5); animation: engPulse 2.6s ease-in-out infinite; }
.eng-reactor.err { background: radial-gradient(circle at 50% 50%, #FFB4A2 0 18%, #C62828 30%, #530000 62%, transparent 72%); box-shadow: 0 0 24px rgba(255,110,90,.6), inset 0 0 18px rgba(0,0,0,.5); }
@keyframes engPulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.06); } }
.eng-ring { position: absolute; inset: -12px; border-radius: 50%; border: 3px dashed rgba(105,240,174,.5); animation: engSpin 12s linear infinite; }
@keyframes engSpin { to { transform: rotate(360deg); } }
.eng-title { margin: 0; font-size: 24px; font-weight: 900; letter-spacing: 1px; }
.eng-sub { margin: 4px 0 0; font-size: 13px; color: #9FB6D8; font-weight: 700; }
.eng-chips { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 14px; position: relative; }
.eng-chip { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.22); border-radius: 999px; padding: 7px 14px; font-size: 13px; font-weight: 800; display: flex; align-items: center; gap: 6px; }
.eng-led { width: 11px; height: 11px; border-radius: 50%; background: #69F0AE; box-shadow: 0 0 8px #69F0AE; display: inline-block; }
.eng-led.err { background: #FF6B5E; box-shadow: 0 0 8px #FF6B5E; animation: engBlink 1s steps(2) infinite; }
@keyframes engBlink { 50% { opacity: .35; } }
.eng-ignite { display: block; margin: 14px auto 0; max-width: 420px; background: linear-gradient(135deg, #0E8F52, #35C26E); color: #fff; font-size: 20px; font-weight: 900; text-decoration: none; border-radius: 14px; padding: 16px; position: relative; box-shadow: 0 6px 18px rgba(14,143,82,.5); }
.eng-ignite small { display: block; font-size: 12px; font-weight: 700; opacity: .9; margin-top: 4px; }
.eng-sec-title { margin: 20px 4px 10px; font-size: 16px; font-weight: 900; color: #0B1E4D; display: flex; align-items: center; gap: 8px; }
.eng-sec-title::after { content: ''; flex: 1; height: 2px; background: linear-gradient(90deg, #0B1E4D, transparent); border-radius: 2px; }
.eng-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.eng-mod { background: #fff; border: 1px solid #D7DEE8; border-top: 5px solid #1565C0; border-radius: 14px; padding: 14px; box-shadow: 0 2px 8px rgba(11,30,77,.06); }
.eng-mod.green { border-top-color: #0E8F52; }
.eng-mod.gold { border-top-color: #C9A227; }
.eng-mod.red { border-top-color: #C62828; }
.eng-mod.purple { border-top-color: #6A1B9A; }
.eng-mod h4 { margin: 0 0 6px; font-size: 16px; font-weight: 900; color: #0B1E4D; }
.eng-mod p { margin: 0 0 10px; font-size: 13px; line-height: 1.55; color: #45607E; }
.eng-btn { display: block; text-align: center; background: linear-gradient(135deg, #0B1E4D, #1D9BF0); color: #fff; font-weight: 800; font-size: 15px; text-decoration: none; border-radius: 10px; padding: 12px; border: 0; width: 100%; box-sizing: border-box; cursor: pointer; }
.eng-btn.alt { background: #fff; color: #0B1E4D; border: 2px solid #0B1E4D; margin-top: 8px; }
.eng-btn.danger { background: linear-gradient(135deg, #B71C1C, #E53935); }
.eng-file { width: 100%; box-sizing: border-box; padding: 12px; border: 2px dashed #0E8F52; border-radius: 10px; background: #F2FBF6; font-size: 13px; margin: 10px 0; }
.eng-backup-row { display: flex; align-items: center; gap: 8px; background: #F4F7FC; border: 1px solid #D7DEE8; border-radius: 8px; padding: 8px 10px; }
.eng-danger-hidden { display: none; }
.eng-danger-hidden.show { display: block; }
.eng-info { background: #0B1E4D; color: #fff; border-radius: 14px; padding: 14px 16px; margin-top: 16px; }
.eng-info-row { display: flex; justify-content: space-between; gap: 8px; padding: 7px 0; border-bottom: 1px dashed rgba(255,255,255,.2); font-size: 14px; font-weight: 700; }
.eng-info-row:last-of-type { border-bottom: 0; }
.eng-hidden-icon { background: none; border: 0; font-size: 18px; cursor: pointer; opacity: .55; }
.eng-hidden-icon.unlocked { opacity: 1; }
.eng-back { display: block; text-align: center; margin-top: 14px; color: #0B1E4D; font-weight: 800; text-decoration: none; font-size: 15px; }
@media (max-width: 640px) { .eng-grid { grid-template-columns: 1fr; } .eng-title { font-size: 20px; } }
</style>

<div class="eng-console">
    <div class="eng-core">
        <div class="eng-reactor <?php echo $ame_eng_ok ? '' : 'err'; ?>"><span class="eng-ring"></span></div>
        <h1 class="eng-title">⚙️ APONSHOP ENGINE</h1>
        <p class="eng-sub">সফটওয়্যারের মূল ইঞ্জিন — এখান থেকেই সফটওয়্যার পরিবর্তন হয়</p>
        <div class="eng-chips">
            <span class="eng-chip">v<?php echo htmlspecialchars($ame_app_ver, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="eng-chip"><?php echo htmlspecialchars($db_name, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="eng-chip"><span class="eng-led <?php echo $ame_eng_ok ? '' : 'err'; ?>"></span><?php echo $ame_eng_ok ? 'ইঞ্জিন সুস্থ' : $ame_recent_err . 'টি এরর'; ?></span>
        </div>
        <a class="eng-ignite" href="easy_update.php">🚀 সহজ আপডেট খুলুন<small>নামানো ZIP এখান থেকেই ইমপোর্ট হয়</small></a>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
    <p class="eng-mod red" style="margin:12px 0;padding:12px;"><?php echo htmlspecialchars((string)$_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['success'])): ?>
    <p class="eng-mod green" style="margin:12px 0;padding:12px;"><?php echo htmlspecialchars((string)$_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?></p>
    <?php endif; ?>

    <?php
    if (!function_exists('ame_daily_backup_tick')) {
        require_once dirname(__DIR__, 2) . '/includes/daily_backup.php';
    }
    $ame_bak_dir = function_exists('ame_daily_backup_dir') ? ame_daily_backup_dir() : '';
    $ame_bak_files = ($ame_bak_dir !== '' && function_exists('ame_daily_backup_files')) ? ame_daily_backup_files($ame_bak_dir) : [];
    $ame_bak_latest = $ame_bak_files[0] ?? null;
    $ame_bak_count = count($ame_bak_files);
    $ame_cron_cmd = '0 0 * * * php /home/u718102670/domains/mavo12.online/public_html/cron/duty_midnight.php';
    ?>
    <div class="eng-sec-title">🌙 প্রতিদিন অটো ব্যাকআপ</div>
    <div class="eng-grid">
        <div class="eng-mod green">
            <h4>🌙 সার্ভার নিজেই নেয়</h4>
            <?php if ($ame_bak_latest): ?>
            <p>সর্বশেষ অটো ফাইল: <b><?php echo htmlspecialchars($ame_bak_latest['file'], ENT_QUOTES, 'UTF-8'); ?></b><br>
            <?php echo date('d M Y, h:i A', (int) $ame_bak_latest['time']); ?> · <?php echo round(((int) $ame_bak_latest['bytes']) / 1048576, 2); ?> MB · মোট <?php echo (int) $ame_bak_count; ?>টি (৭ দিন)</p>
            <?php else: ?>
            <p>মধ্যরাতের ক্রন প্রতিদিন SQL রাখে। বাটন চাপতে হয় না। প্রথম রাতের পর এখানে ফাইল দেখাবে।</p>
            <?php endif; ?>
            <p style="font-size:12px;">যে ক্রন চালায় (আগে থেকেই আছে):<br>
            <code id="ameCronCmd" style="word-break:break-all;"><?php echo htmlspecialchars($ame_cron_cmd, ENT_QUOTES, 'UTF-8'); ?></code></p>
            <a href="/modules/settings/backup.php" class="eng-btn">তৈরি করুন ও নামান</a>
        </div>
        <div class="eng-mod">
            <h4>💾 সাত দিনের খাতা</h4>
            <p>ফাইল সার্ভারে জমে। ওয়েব থেকে খোলা যায় না। অ্যাডমিন চাইলে এখান থেকে নামিয়ে নিতে পারেন।</p>
            <a href="/modules/settings/backup.php" class="eng-btn">নামান / তালিকা</a>
        </div>
    </div>

    <div class="eng-sec-title">🧩 ইঞ্জিন মডিউল</div>
    <div class="eng-grid">
        <div class="eng-mod purple">
            <h4>🕌 ইসলামিক অডিও লাইব্রেরি</h4>
            <p>হোমপেজ অডিও, URL ডাউনলোড, অটো কম্প্রেশন ও সাউন্ড অপ্টিমাইজ।</p>
            <a href="islamic_audio.php" class="eng-btn">🎧 অডিও ম্যানেজ করুন</a>
        </div>
        <div class="eng-mod green">
            <h4>📥 আপডেট ZIP ডাউনলোড</h4>
            <p>সর্বশেষ ZIP প্যাকেজ নামিয়ে সহজ আপডেটে দিন।</p>
            <a href="app_update.php" class="eng-btn">📥 ZIP ডাউনলোড</a>
        </div>
        <div class="eng-mod red">
            <h4>♻️ ব্যাকআপ থেকে রিস্টোর</h4>
            <p>.sql আপলোড করে ডাটা ফিরান — বর্তমান ডাটা মুছে যায়।</p>
            <a href="restore.php" class="eng-btn danger">♻️ রিস্টোর করুন</a>
        </div>
        <div class="eng-mod">
            <h4>🗄️ ডাটাবেজ স্কিমা সিঙ্ক</h4>
            <p>টেবিল মিলিয়ে নেয় — ডাটা মুছে না।</p>
            <a href="schema_sync.php" class="eng-btn">🗄️ স্কিমা চেক / মিলান</a>
            <a href="download_schema_sql.php" class="eng-btn alt">📄 SQL ডাউনলোড</a>
            <a href="view_schema_sql.php" class="eng-btn alt">সরাসরি টেবিল কোড</a>
        </div>
        <div class="eng-mod <?php echo $ame_eng_ok ? 'green' : 'red'; ?>">
            <h4><?php echo $ame_eng_ok ? '✅' : '⚠️'; ?> সিস্টেম স্বাস্থ্য ও এরর লগ</h4>
            <p><?php echo $ame_eng_ok ? 'গত ২৪ ঘণ্টায় কোনো এরর নেই।' : 'গত ২৪ ঘণ্টায় ' . (int)$ame_recent_err . 'টি এরর — দেখে নিন।'; ?></p>
            <a href="error_diagnostics.php" class="eng-btn">🩺 এরর লগ দেখুন</a>
            <a href="system_health.php" class="eng-btn alt">🖥️ সিস্টেম হেলথ চেক</a>
        </div>
        <div class="eng-mod gold">
            <h4>📷 পুরনো ক্রেতা ছবি চালু</h4>
            <p>পুরনো ফোল্ডারের ছবি ক্রেতার সাথে জোড়া লাগান।</p>
            <a href="relink_photos.php" class="eng-btn">📷 ছবি চালু করুন</a>
        </div>
        <div class="eng-mod green">
            <h4>🛡️ প্রজেক্ট রক্ষা</h4>
            <p>কোড ZIP আর হিসাব SQL আলাদা রাখার নিয়ম।</p>
            <a href="project_care.php" class="eng-btn">🛡️ রক্ষার নিয়ম খুলুন</a>
        </div>
        <div class="eng-mod green">
            <h4>৳ টাকা মিল</h4>
            <p>বকেয়া, ক্যাশ/ব্যাংক ও সাপ্লায়ার হিসাব মিলিয়ে দেখুন।</p>
            <a href="money_guard.php" class="eng-btn">৳ টাকা মিল খুলুন</a>
        </div>
        <div class="eng-mod">
            <h4>📦 মোবাইল ওয়ান-ক্লিক ZIP</h4>
            <p>এক ট্যাপে কোড ZIP — .env ও ছবি ছাড়াই।</p>
            <a href="/one_click.php?go=update" class="eng-btn" download="Aponshop_<?php echo htmlspecialchars($ame_app_ver, ENT_QUOTES, 'UTF-8'); ?>_FULL_STACK.zip">⬇️ এক ট্যাপে ZIP নামান</a>
            <a href="/one_click.php" class="eng-btn alt">📱 নির্দেশনা দেখুন</a>
        </div>
        <?php if (is_file(__DIR__ . '/../../install_stack.php') || is_file(__DIR__ . '/../../Aponshop_POS_STACK_UPDATE.zip')): ?>
        <div class="eng-mod">
            <h4>📦 POS স্ট্যাক — সহজ ZIP</h4>
            <p><b>Aponshop_POS_STACK_UPDATE.zip</b> আপলোড করুন।</p>
            <a href="/install_stack.php" class="eng-btn">📦 ZIP ইনস্টল</a>
        </div>
        <?php endif; ?>
        <?php if (is_file(__DIR__ . '/../../install_1074.php')): ?>
        <div class="eng-mod purple">
            <h4>🔐 সম্পূর্ণ v10.74 সিকিউরিটি ইনস্টল</h4>
            <p>CSRF, পাসওয়ার্ড পলিসি, সহজ আপডেট — এক ক্লিকে।</p>
            <a href="/install_1074.php" class="eng-btn">🔐 ইনস্টল করুন</a>
        </div>
        <?php endif; ?>
        <?php if (is_file(__DIR__ . '/../../patch_sidebar_pos.php')): ?>
        <div class="eng-mod purple">
            <h4>📱 সাইডবার + POS সার্চ প্যাচ</h4>
            <p>মেনু আইকন ও বিক্রয় পেজে কর্মী সার্চ।</p>
            <a href="/patch_sidebar_pos.php" class="eng-btn">📱 প্যাচ করুন</a>
        </div>
        <?php elseif (is_file(__DIR__ . '/../../install_sidebar_pos.php')): ?>
        <div class="eng-mod purple">
            <h4>📱 সাইডবার + POS সার্চ আপডেট</h4>
            <p>মেনু আইকন ও বিক্রয় পেজে কর্মী সার্চ।</p>
            <a href="/install_sidebar_pos.php" class="eng-btn">📱 ইনস্টল করুন</a>
        </div>
        <?php endif; ?>
        <?php if (!is_file(__DIR__ . '/../../install_stack.php') && (is_file(__DIR__ . '/../../install_pos_customer.php') || is_file(__DIR__ . '/../../patch_pos_customer.php'))): ?>
        <div class="eng-mod">
            <h4>👤 POS কাস্টমার বক্স আপডেট</h4>
            <p>পুরনো পদ্ধতি — নতুন স্ট্যাক থাকলে সেটাই ব্যবহার করুন।</p>
            <a href="/install_pos_customer.php" class="eng-btn">👤 ইনস্টল করুন</a>
        </div>
        <?php endif; ?>
        <div class="eng-mod">
            <h4>🧹 Next.js আবর্জনা সরান</h4>
            <?php if (isset($_GET['cleaned']) && empty($ame_leftovers)): ?>
            <p>ডেমো ফাইল আর নেই। PHP দোকান অক্ষত।</p>
            <?php elseif ($ame_leftovers): ?>
            <p>পুরনো ডেমো ফাইল: <b><?php echo esc_html(implode(', ', array_column($ame_leftovers, 'name'))); ?></b></p>
            <form action="process_cleanup_demo.php" method="POST" onsubmit="return confirm('শুধু Next.js আবর্জনা মুছবে। চালিয়ে যাবেন?');">
                <?php echo csrf_field(); ?>
                <button type="submit" class="eng-btn">🧹 এখনই সরান</button>
            </form>
            <?php else: ?>
            <p>কোনো ডেমো ফোল্ডার নেই। কিছু করার দরকার নেই।</p>
            <?php endif; ?>
        </div>
        <div class="eng-mod red">
            <h4>🧹 uploads-এ পুরনো কোড সরান</h4>
            <?php if (isset($_GET['ucleaned']) && empty($ame_upload_leftovers)): ?>
            <p>uploads/-এ পুরনো কোড নেই। ছবি-ডকুমেন্ট অক্ষত।</p>
            <?php elseif ($ame_upload_leftovers): ?>
            <?php $ame_upload_kb = array_sum(array_column($ame_upload_leftovers, 'bytes')) / 1024; ?>
            <p>পুরনো কোড ফাইল (প্রায় <?php echo number_format($ame_upload_kb, 0); ?> KB): <b><?php echo esc_html(implode(', ', array_column($ame_upload_leftovers, 'name'))); ?></b></p>
            <form action="process_cleanup_uploads_code.php" method="POST" onsubmit="return confirm('শুধু uploads/-এর পুরনো কোড ফাইল মুছবে, ছবি/ডকুমেন্ট না। চালিয়ে যাবেন?');">
                <?php echo csrf_field(); ?>
                <button type="submit" class="eng-btn danger">🧹 এখনই সরান</button>
            </form>
            <?php else: ?>
            <p>uploads/-এ পুরনো কোড নেই। কিছু করার দরকার নেই।</p>
            <?php endif; ?>
        </div>
        <div class="eng-mod">
            <h4>🏭 সব ডাটা মুছে পুরনো সাইট বসান</h4>
            <p>সব মুছে পুরনো সাইটের ডাটা বসানো হয়।</p>
            <a href="oldpos_replace.php" class="eng-btn danger">সব ডাটা মুছে পুরনো সাইট বসান</a>
        </div>
        <div class="eng-mod">
            <h4>👥 ক্রেতা ও সাপ্লায়ার বদলান</h4>
            <p>ক্রেতা-সাপ্লায়ার তালিকা বদলানোর টুল।</p>
            <a href="fresh_contacts.php" class="eng-btn">ক্রেতা ও সাপ্লায়ার বদলান</a>
        </div>
        <?php if (env_flag_enabled('AME_ENABLE_MIGRATION_TOOLS')): ?>
        <div class="eng-mod">
            <h4>🗃️ পুরনো SQL ডাটাবেজে দিন</h4>
            <p>পুরনো ডাটাবেজ ইমপোর্ট টুল।</p>
            <a href="/import.php" class="eng-btn">পুরনো SQL ডাটাবেজে দিন</a>
        </div>
        <?php endif; ?>
        <?php if (env_flag_enabled('AME_ALLOW_INSTALL_WIPE')): ?>
        <div class="eng-mod red">
            <h4>🗑️ সব ডাটা মুছে খালি করুন</h4>
            <p>রিপোর্ট/লেনদেন/কাস্টমার সব মুছুন — টেবিল থাকবে।</p>
            <a href="/install_wipe_data.php" class="eng-btn danger">🗑️ ইনস্টল ওয়াইপ খুলুন</a>
            <a href="download_wipe_sql.php" class="eng-btn alt">📄 phpMyAdmin Wipe SQL</a>
        </div>
        <?php endif; ?>
        <div class="eng-mod red eng-danger-hidden <?php echo $danger_unlocked ? 'show' : ''; ?>" id="dangerClearBox">
            <h4>🗑️ ব্যবসায়িক ডাটা মুছুন (OTP)</h4>
            <p>WhatsApp/SMS OTP দিয়ে। ভুলবশত চাপা এড়াতে লুকানো।</p>
            <a href="clear_business_data.php" class="eng-btn danger">🗑️ OTP দিয়ে ক্লিয়ার</a>
        </div>
    </div>

    <?php if (isset($_GET['restored'])): ?>
    <div style="background:#E8F5E9; color:#0E8F52; padding:12px; border-radius:8px; font-size:13px; font-weight:600; margin:12px 0; text-align:center;">
        ✅ ব্যাকআপ থেকে সফলভাবে রিস্টোর করা হয়েছে।
    </div>
    <?php endif; ?>

    <div class="eng-info">
        <div class="eng-info-row" id="versionRow"><span>ভার্সন</span><span id="versionText"><?php echo htmlspecialchars($ame_app_ver, ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="eng-info-row"><span>ডেটাবেজ</span><span><?php echo htmlspecialchars($db_name, ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="eng-info-row"><span>অ্যাডভান্সড</span><button type="button" class="eng-hidden-icon <?php echo $danger_unlocked ? 'unlocked' : ''; ?>" id="hiddenLockBtn" title="অ্যাডভান্সড">🔒</button></div>
    </div>
    <a href="dashboard_settings.php" class="eng-back">⬅️ ফিরে যান</a>
</div>
<script>
(function () {
    var taps = 0;
    var timer = null;
    var versionRow = document.getElementById('versionRow');
    var lockBtn = document.getElementById('hiddenLockBtn');
    var dangerBox = document.getElementById('dangerClearBox');
    var csrf = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;

    function unlockDanger() {
        fetch('process_unlock_danger.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'csrf_token=' + encodeURIComponent(csrf)
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                dangerBox.classList.add('show');
                lockBtn.classList.add('unlocked');
                lockBtn.textContent = '🔓';
            }
        }).catch(function () {});
    }

    function registerTap() {
        taps++;
        clearTimeout(timer);
        timer = setTimeout(function () { taps = 0; }, 2500);
        if (taps >= 5) {
            taps = 0;
            unlockDanger();
        }
    }

    if (versionRow) {
        versionRow.addEventListener('click', registerTap);
    }
    if (lockBtn) {
        lockBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            unlockDanger();
        });
    }
})();
</script>
<?php include '../../partials/footer.php'; ?>
