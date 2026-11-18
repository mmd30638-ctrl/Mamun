<?php
require_once '../../includes/config.php';
admin_only();

$releases_dir = dirname(__DIR__, 2) . '/releases';
$latest = null;
$latest_ver = 0.0;
foreach (glob($releases_dir . '/Aponshop_*_FULL_STACK.zip') ?: [] as $path) {
    if (!preg_match('/Aponshop_([0-9.]+)_FULL_STACK\.zip$/', $path, $m)) {
        continue;
    }
    $ver = (float)$m[1];
    if ($ver >= $latest_ver) {
        $latest_ver = $ver;
        $latest = [
            'version' => $m[1],
            'path' => $path,
            'size' => filesize($path),
            'sha256' => hash_file('sha256', $path),
        ];
    }
}
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$dl_page = $base . '/download_update.php';
$dl_direct = $latest ? $base . '/download_update.php?go=1&v=' . urlencode($latest['version']) : '';

include '../../partials/header.php';
?>
<style>
    .upd-wrap { padding:15px; }
    .upd-card { background:#fff; border-radius:14px; max-width:520px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,.08); }
    .upd-topbar { display:flex; height:6px; }
    .upd-topbar span { flex:1; }
    .upd-c1{background:var(--brand-navy);} .upd-c2{background:var(--brand-accent);} .upd-c3{background:var(--brand-gold);}
    .upd-header { text-align:center; padding:20px 15px 15px; }
    .upd-header h3 { margin:0; font-size:19px; color:var(--brand-navy); }
    .upd-body { padding:0 20px 20px; font-size:13px; line-height:1.6; color:#333; }
    .upd-info { background:#f0f7ff; border:1px solid #b8d4f0; border-radius:10px; padding:14px; margin-bottom:14px; }
    .upd-btn { display:block; text-align:center; padding:14px; border-radius:8px; background:var(--brand-gradient); color:#fff; font-weight:700; text-decoration:none; margin-bottom:10px; }
    .upd-btn.alt { background:#f4f4f4; color:#333; border:1px solid #ddd; }
    .upd-sha { font-size:11px; word-break:break-all; color:#666; margin-top:10px; }
    .upd-back { display:block; text-align:center; margin-top:14px; color:#666; text-decoration:none; font-size:13px; }
</style>
<div class="upd-wrap">
    <div class="upd-card">
        <div class="upd-topbar"><span class="upd-c1"></span><span class="upd-c2"></span><span class="upd-c3"></span></div>
        <div class="upd-header">
            <h3>📥 আপডেট ডাউনলোড</h3>
            <small>বর্তমান সাইট ভার্সন: <?php echo htmlspecialchars(defined('AME_APP_VERSION') ? AME_APP_VERSION : '?'); ?></small>
        </div>
        <div class="upd-body">
            <?php if ($latest): ?>
            <div class="upd-info">
                <b>সর্বশেষ প্যাকেজ:</b> v<?php echo htmlspecialchars($latest['version']); ?><br>
                <b>সাইজ:</b> <?php echo round($latest['size'] / 1048576, 2); ?> MB<br>
                <b>ফাইল:</b> Aponshop_<?php echo htmlspecialchars($latest['version']); ?>_FULL_STACK.zip
            </div>
            <a href="<?php echo htmlspecialchars($dl_page); ?>" class="upd-btn" id="updAutoBtn">⬇️ অটো ডাউনলোড শুরু করুন</a>
            <a href="<?php echo htmlspecialchars($dl_direct); ?>" class="upd-btn alt">📎 সরাসরি ZIP লিংক</a>
            <div class="upd-sha">SHA256: <?php echo htmlspecialchars($latest['sha256']); ?></div>
            <p style="margin-top:14px;color:#666;">লিংক শেয়ার: <code><?php echo htmlspecialchars($dl_page); ?></code></p>
            <?php else: ?>
            <div class="upd-info" style="background:#fff8e6;border-color:#f0c36d;">
                সার্ভারে releases/ ফোল্ডারে কোনো ZIP নেই। Hostinger File Manager দিয়ে ZIP আপলোড করুন।
            </div>
            <?php endif; ?>
            <p><b>সহজ ইনস্টল:</b> ZIP ডাউনলোড করুন → Settings → Tools → <b>সহজ আপডেট</b> → ZIP আপলোড করুন।</p>
            <a href="easy_update.php" class="upd-btn" style="margin-top:12px;">🚀 সহজ আপডেটে যান</a>
        </div>
        <a href="tools.php" class="upd-back">⬅️ টুলসে ফিরে যান</a>
    </div>
</div>
<?php if ($latest): ?>
<script>
document.getElementById('updAutoBtn').addEventListener('click', function (e) {
    e.preventDefault();
    window.location.href = <?php echo json_encode($dl_page); ?>;
});
</script>
<?php endif; ?>
<?php include '../../partials/footer.php'; ?>
