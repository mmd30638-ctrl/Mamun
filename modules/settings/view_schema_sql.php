<?php
require_once '../../includes/config.php';
admin_only();

$path = dirname(__DIR__, 2) . '/sql/aponshop_full_schema.sql';
$sql = is_readable($path) ? (string) file_get_contents($path) : '';
$bytes = $sql !== '' ? strlen($sql) : 0;
$tables = $sql !== '' ? preg_match_all('/^CREATE TABLE IF NOT EXISTS/m', $sql) : 0;
$ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '10.97.1';

include '../../partials/header.php';
?>
<style>
.sql-wrap{max-width:980px;margin:0 auto;padding:16px 14px 40px;}
.sql-hero{background:linear-gradient(135deg,#0B1E4D,#1a3a7a);color:#fff;border-radius:18px;padding:22px 20px;margin-bottom:16px;}
.sql-hero h2{margin:0 0 6px;font-size:1.35rem;}
.sql-hero p{margin:0;opacity:.9;font-size:14px;line-height:1.55;}
.sql-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px;margin-bottom:14px;}
.sql-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;}
.sql-btn{display:inline-block;border:0;cursor:pointer;background:linear-gradient(135deg,#0B1E4D,#1565c0);color:#fff;font-weight:700;padding:12px 16px;border-radius:12px;font-size:14px;text-decoration:none;}
.sql-btn.ghost{background:#fff;color:#0B1E4D;border:1px solid #cbd5e1;}
.sql-meta{font-size:13px;color:#475569;margin:0 0 10px;}
.sql-box{width:100%;min-height:70vh;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;line-height:1.45;border:1px solid #cbd5e1;border-radius:12px;padding:12px;background:#0f172a;color:#e2e8f0;box-sizing:border-box;resize:vertical;}
.sql-note{font-size:13px;color:#475569;line-height:1.6;}
@media(max-width:640px){.sql-btn{width:100%;text-align:center;}}
</style>
<div class="sql-wrap">
    <div class="sql-hero">
        <h2>সরাসরি ডাটাবেজ টেবিল কোড</h2>
        <p>phpMyAdmin-এ ইমপোর্টের জন্য পূর্ণ <code>CREATE TABLE IF NOT EXISTS</code>। যা আছে থাকবে, যা নেই যোগ হবে। কোনো টেবিল বা সারি মুছে যায় না।</p>
    </div>

    <div class="sql-card">
        <p class="sql-meta">ভার্সন <?php echo esc_html($ver); ?> · <?php echo (int)$tables; ?> টেবিল · <?php echo number_format($bytes); ?> বাইট</p>
        <div class="sql-bar">
            <a class="sql-btn" href="download_schema_sql.php">SQL ফাইল ডাউনলোড</a>
            <button type="button" class="sql-btn ghost" id="copySql">কোড কপি করুন</button>
            <a class="sql-btn ghost" href="schema_sync.php">স্কিমা সিঙ্ক পেজ</a>
        </div>
        <p class="sql-note">Hostinger → phpMyAdmin → আপনার ডাটাবেজ → <b>Import</b> → Choose file → এই SQL → Go। SQL ট্যাবে পেস্ট করবেন না। যে টেবিল আগে হয়ে গেছে সেগুলো থাকবে; আবার Import দিলে বাকিগুলো যোগ হবে।</p>
    </div>

    <div class="sql-card">
        <?php if ($sql === ''): ?>
            <p class="sql-note" style="color:#b91c1c;">sql/aponshop_full_schema.sql ফাইল পাওয়া যায়নি। ZIP আবার তুলুন।</p>
        <?php else: ?>
            <textarea class="sql-box" id="schemaSql" readonly spellcheck="false"><?php echo htmlspecialchars($sql, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <?php endif; ?>
    </div>

    <a href="tools.php" style="display:block;text-align:center;margin-top:8px;color:#0B1E4D;font-weight:700;text-decoration:none;">← সিস্টেম টুলস</a>
</div>
<script>
(function () {
    var btn = document.getElementById('copySql');
    var box = document.getElementById('schemaSql');
    if (!btn || !box) return;
    btn.addEventListener('click', function () {
        box.select();
        box.setSelectionRange(0, box.value.length);
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) {}
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(box.value).then(function () {
                btn.textContent = 'কপি হয়েছে';
            }).catch(function () {
                btn.textContent = ok ? 'কপি হয়েছে' : 'ম্যানুয়ালি সিলেক্ট করুন';
            });
        } else {
            btn.textContent = ok ? 'কপি হয়েছে' : 'ম্যানুয়ালি সিলেক্ট করুন';
        }
        setTimeout(function () { btn.textContent = 'কোড কপি করুন'; }, 1800);
    });
})();
</script>
<?php include '../../partials/footer.php'; ?>
