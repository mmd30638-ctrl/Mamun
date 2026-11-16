<?php
require_once __DIR__ . '/../../includes/config.php';
admin_only();
include __DIR__ . '/../../partials/header.php';

$row = ame_pos_title_row($db);
$default = ame_pos_title_defaults();
$style_attr = ame_pos_title_style_attr($row);
?>
<style>
    .set-wrap { padding: 15px; }
    .set-card { background:#fff; border-radius:14px; max-width:560px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
    .set-topbar { display:flex; height:6px; }
    .set-topbar span { flex:1; }
    .set-c1{background:var(--brand-navy);} .set-c2{background:var(--brand-accent);} .set-c3{background:var(--brand-gold);}
    .set-header { text-align:center; padding:20px 15px 10px; }
    .set-header h3 { margin:0; font-size:19px; color:#0B1E4D; }
    .set-header small { color:#666; font-size:12px; display:block; margin-top:6px; line-height:1.45; }
    .set-body { padding:0 20px 22px; }
    .set-nav { display:flex; gap:8px; overflow-x:auto; padding:0 20px 15px; }
    .set-nav a { flex-shrink:0; padding:8px 14px; border-radius:20px; background:#f4f4f4; color:#000; text-decoration:none; font-size:12px; font-weight:600; white-space:nowrap; border:1px solid #eee; }
    .set-nav a.active { background:var(--brand-accent); color:#fff; border-color:var(--brand-accent); }
    .set-group { margin-bottom:14px; }
    .set-group label { display:block; font-weight:700; font-size:13px; color:#0B1E4D; margin-bottom:6px; }
    .set-group textarea,
    .set-group select,
    .set-group input[type="number"] { width:100%; padding:10px 12px; border:1.5px solid #CFE3F7; border-radius:10px; box-sizing:border-box; font-size:14px; background:#fbfdff; }
    .set-group textarea { min-height:96px; font-size:15px; line-height:1.5; }
    .set-group textarea:focus,
    .set-group select:focus,
    .set-group input[type="number"]:focus { outline:none; border-color:#0091EA; background:#fff; }
    .set-group small { display:block; margin-top:6px; color:#78909C; font-size:12px; line-height:1.4; }
    .set-preview { background:#f8fbff; border:1px solid #E3F2FD; border-radius:12px; padding:12px 14px; margin-bottom:16px; }
    .set-preview-label { font-size:11px; font-weight:800; color:#546E7A; margin-bottom:8px; }
    .set-preview-title { margin:0; font-size:16px; font-weight:800; color:#111827; line-height:1.45; white-space:normal; }
    .set-preview-line { display:inline; margin:0 6px 0 0; }
    .set-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .set-color { display:flex; align-items:center; gap:8px; padding:8px 10px; border:1px solid #E3F2FD; border-radius:10px; background:#fbfdff; }
    .set-color span { font-size:12px; font-weight:700; color:#0B1E4D; flex:1; }
    .set-color input[type="color"] { width:42px; height:32px; border:0; padding:0; background:none; cursor:pointer; }
    .set-color input[type="text"] { width:88px; padding:6px 8px; border:1px solid #CFE3F7; border-radius:8px; font-size:12px; }
    .set-submit { width:100%; padding:14px; border:none; border-radius:8px; background:var(--brand-gradient); color:#fff; font-weight:700; font-size:15px; cursor:pointer; }
    .set-reset { width:100%; margin-top:8px; padding:12px; border:1px solid #90CAF9; border-radius:8px; background:#fff; color:#0D47A1; font-weight:700; cursor:pointer; }
    .set-ok { background:#E8F5E9; color:#1B5E20; padding:12px 20px; margin:0 20px 15px; border-radius:8px; font-size:13px; font-weight:600; }
    .set-section { margin:18px 0 10px; font-size:12px; font-weight:800; color:#546E7A; text-transform:uppercase; letter-spacing:.4px; }
</style>

<div class="set-wrap">
    <div class="set-card">
        <div class="set-topbar"><span class="set-c1"></span><span class="set-c2"></span><span class="set-c3"></span></div>
        <div class="set-header">
            <h3>🖥️ POS শিরোনাম লেখা</h3>
            <small>লেখা, রং ও ফন্ট — শুধু বিক্রয়/ক্রয় পেজের উপরের লাইন। চলমান লেখা বা কাউন্টডাউনের সাথে মিশে না।</small>
        </div>
        <?php if (isset($_GET['saved'])): ?>
        <div class="set-ok">✅ লেখা ও স্টাইল সেভ হয়েছে। বিক্রয় পেজে দেখাবে।</div>
        <?php endif; ?>

        <div class="set-nav">
            <a href="dashboard_settings.php">🏢 দোকানের তথ্য</a>
            <a href="pos_title.php" class="active">🖥️ POS শিরোনাম</a>
            <a href="scrolling_text.php">📢 চলমান লেখা</a>
            <a href="countdown.php">⏳ কাউন্টডাউন</a>
        </div>

        <form action="process_pos_title.php" method="POST" class="set-body" id="posTitleForm">
            <?php echo csrf_field(); ?>
            <div class="set-preview">
                <div class="set-preview-label">বিক্রয় পেজে যেমন দেখাবে</div>
                <p class="set-preview-title">
                    <span class="set-preview-line" id="posTitlePreview" style="<?php echo esc_html($style_attr); ?>"><?php echo esc_html($row['line_text']); ?></span>
                    <span aria-hidden="true">🖥️</span>
                </p>
            </div>
            <div class="set-group">
                <label>সম্পূর্ণ লেখা</label>
                <textarea name="line_text" id="posTitleInput" maxlength="300" required><?php echo esc_html($row['line_text']); ?></textarea>
                <small>বিক্রয় পেজের উপরে এই লেখা বসবে। লম্বা হলে দুই লাইনে ভাঙবে।</small>
            </div>

            <div class="set-section">রং ও ফন্ট</div>
            <div class="set-color" style="margin-bottom:12px;">
                <span>লেখার রং</span>
                <input type="color" name="text_color" id="posTitleColor" value="<?php echo esc_html($row['text_color']); ?>">
                <input type="text" id="posTitleColorText" value="<?php echo esc_html($row['text_color']); ?>" maxlength="7">
            </div>
            <div class="set-row">
                <div class="set-group">
                    <label>ফন্ট সাইজ (px)</label>
                    <input type="number" name="font_size" id="posTitleSize" min="10" max="24" value="<?php echo (int)$row['font_size']; ?>">
                </div>
                <div class="set-group">
                    <label>ফন্ট ওজন</label>
                    <?php echo ame_font_weight_select_html('font_weight', (string)$row['font_weight'], 'posTitleWeight'); ?>
                </div>
            </div>
            <div class="set-group">
                <label>ফন্ট পরিবার</label>
                <?php echo ame_font_family_select_html('font_family', (string)$row['font_family'], 'posTitleFamily'); ?>
            </div>

            <button type="submit" class="set-submit">✅ সেভ করুন</button>
            <button type="button" class="set-reset" id="posTitleReset">ডিফল্ট ফিরে আনুন</button>
        </form>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('posTitleForm');
    var input = document.getElementById('posTitleInput');
    var preview = document.getElementById('posTitlePreview');
    var color = document.getElementById('posTitleColor');
    var colorText = document.getElementById('posTitleColorText');
    var size = document.getElementById('posTitleSize');
    var weight = document.getElementById('posTitleWeight');
    var family = document.getElementById('posTitleFamily');
    var def = <?php echo json_encode($default, JSON_UNESCAPED_UNICODE); ?>;

    function paint() {
        preview.textContent = input.value.trim() || def.line_text;
        preview.style.color = color.value;
        preview.style.fontSize = (parseInt(size.value, 10) || def.font_size) + 'px';
        preview.style.fontWeight = weight.value || def.font_weight;
        preview.style.fontFamily = family.value || '';
    }

    input.addEventListener('input', paint);
    size.addEventListener('input', paint);
    weight.addEventListener('change', paint);
    family.addEventListener('change', paint);
    color.addEventListener('input', function () {
        colorText.value = color.value;
        paint();
    });
    colorText.addEventListener('input', function () {
        if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(colorText.value)) {
            color.value = colorText.value;
            paint();
        }
    });

    document.getElementById('posTitleReset').addEventListener('click', function () {
        input.value = def.line_text;
        color.value = def.text_color;
        colorText.value = def.text_color;
        size.value = def.font_size;
        weight.value = def.font_weight;
        family.value = def.font_family;
        paint();
        input.focus();
    });
    paint();
})();
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
