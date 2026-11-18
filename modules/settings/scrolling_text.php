<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/display_widgets.php';
admin_only();
include __DIR__ . '/../../partials/header.php';

ensure_display_widgets_schema($db);
$items = ame_get_widgets($db, 'ticker');
$edit_id = (int)($_GET['edit'] ?? 0);
$row = $edit_id ? ame_get_widget($db, $edit_id, 'ticker') : null;
if (!$row) {
    $row = ame_widget_defaults('ticker');
}
$pack = ame_ticker_icon_pack();
$pv_speed = max(8, (int)($row['speed_seconds'] ?? 22));
$pv_right = (($row['direction'] ?? 'left') === 'right');
$pv_text = (string)($row['message'] ?: 'এখানে আপনার চলমান লেখা দেখাবে');
?>
<link rel="stylesheet" href="/assets/css/display-editor.css?v=<?php echo esc_html($sys_version ?? '1.0'); ?>">
<style>
  .dw-ticker-accent .dw-submit { background:linear-gradient(135deg,#0E8F52,#2ECC71); }
  .dw-ticker-accent .dw-new { background:#0E8F52; }
  .dw-live {
    display:flex;
    align-items:center;
    height:40px;
    overflow:hidden;
    flex-wrap:nowrap;
  }
  .dw-live-icon {
    flex:0 0 26px;
    width:26px;
    height:26px;
    margin:0 8px 0 10px;
    border-radius:8px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    line-height:0;
  }
  .dw-live-view { flex:1 1 auto; min-width:0; height:40px; overflow:hidden; }
  .dw-live-track {
    display:inline-flex;
    align-items:center;
    height:40px;
    width:max-content;
    white-space:nowrap;
    line-height:40px;
    animation:dw-live-left var(--dw-speed, 22s) linear infinite;
  }
  .dw-live-track.is-right { animation-name:dw-live-right; }
  .dw-live-sep { opacity:.55; padding:0 28px 0 8px; }
  @keyframes dw-live-left { from { transform:translate3d(0,0,0); } to { transform:translate3d(-50%,0,0); } }
  @keyframes dw-live-right { from { transform:translate3d(-50%,0,0); } to { transform:translate3d(0,0,0); } }
</style>

<div class="dw-wrap dw-ticker-accent">
  <div class="dw-card">
    <div class="dw-top"><span style="background:#0E8F52"></span><span style="background:#2ECC71"></span><span style="background:#FFD600"></span></div>
    <div class="dw-head">
      <h3>চলমান লেখা</h3>
      <small>হেডারের এক লাইন — লেখা, রং, গতি, দিক ও আইকন</small>
    </div>
    <?php if (isset($_GET['saved'])): ?><div class="dw-ok">সেভ হয়েছে। সব পেজে দেখাবে।</div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="dw-ok">মুছে ফেলা হয়েছে।</div><?php endif; ?>
    <?php if (!empty($_GET['err'])): ?><div class="dw-ok" style="background:#FFEBEE;color:#C62828;"><?php echo esc_html((string)$_GET['err']); ?></div><?php endif; ?>

    <div class="dw-preview-label">লাইভ প্রিভিউ</div>
    <div class="dw-preview dw-live" id="tickPreview" style="background:<?php echo esc_html($row['bg_color']); ?>;color:<?php echo esc_html($row['text_color']); ?>;font-weight:<?php echo esc_html($row['font_weight']); ?>;font-size:<?php echo (int)$row['font_size']; ?>px;">
      <span class="dw-live-icon" id="pvIcon" style="background:<?php echo esc_html($row['icon_bg']); ?>;color:<?php echo esc_html($row['icon_color']); ?>;">
        <?php echo ame_icon_svg('ticker', $row['icon_key'], 15); ?>
      </span>
      <div class="dw-live-view">
        <div class="dw-live-track<?php echo $pv_right ? ' is-right' : ''; ?>" id="pvTrack" style="--dw-speed: <?php echo $pv_speed; ?>s;">
          <span id="pvText"><?php echo esc_html($pv_text); ?></span>
          <span class="dw-live-sep" id="pvSep" style="color:<?php echo esc_html($row['accent_color']); ?>;">✦</span>
          <span id="pvText2"><?php echo esc_html($pv_text); ?></span>
          <span class="dw-live-sep" id="pvSep2" style="color:<?php echo esc_html($row['accent_color']); ?>;">✦</span>
        </div>
      </div>
    </div>

    <a class="dw-new" href="scrolling_text.php">+ নতুন চলমান লেখা</a>

    <div class="dw-list">
      <?php if (!$items): ?>
        <div style="text-align:center;color:#94A3B8;font-size:13px;padding:10px;">এখনো কোনো লেখা নেই।</div>
      <?php endif; ?>
      <?php foreach ($items as $it): ?>
      <div class="dw-item">
        <div class="dw-ico" style="background:<?php echo esc_html($it['icon_bg']); ?>;color:<?php echo esc_html($it['icon_color']); ?>;">
          <?php echo ame_icon_svg('ticker', $it['icon_key'], 18); ?>
        </div>
        <div>
          <b><?php echo esc_html($it['title'] ?: 'হেডলাইন'); ?></b>
          <span><?php echo esc_html(mb_strimwidth((string)$it['message'], 0, 48, '…')); ?> · <?php echo (int)$it['is_enabled'] ? 'চালু' : 'বন্ধ'; ?> · <?php echo (int)$it['speed_seconds']; ?>সে · <?php echo $it['direction'] === 'right' ? 'ডানে' : 'বামে'; ?></span>
        </div>
        <div class="dw-actions">
          <form action="process_display_widget.php" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="kind" value="ticker">
            <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
            <input type="hidden" name="action" value="toggle">
            <button type="submit"><?php echo (int)$it['is_enabled'] ? 'বন্ধ' : 'চালু'; ?></button>
          </form>
          <a href="scrolling_text.php?edit=<?php echo (int)$it['id']; ?>">এডিট</a>
          <form action="process_display_widget.php" method="post" onsubmit="return confirm('মুছে ফেলবেন?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="kind" value="ticker">
            <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
            <input type="hidden" name="action" value="delete">
            <button class="danger" type="submit">মুছুন</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="dw-card">
    <div class="dw-head">
      <h3><?php echo $row['id'] ? 'লেখা সম্পাদনা' : 'নতুন লেখা'; ?></h3>
      <small>চলমান লেখার আইকন প্যাক থেকে আইকন বাছুন</small>
    </div>
    <form class="dw-body" action="process_display_widget.php" method="post" id="tickForm">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="kind" value="ticker">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">

      <div class="dw-check"><input type="checkbox" name="is_enabled" value="1" <?php echo (int)$row['is_enabled'] ? 'checked' : ''; ?>> হেডারে দেখাবে</div>

      <div class="dw-group">
        <label>শিরোনাম</label>
        <input type="text" name="title" value="<?php echo esc_html($row['title']); ?>" maxlength="200">
      </div>
      <div class="dw-group">
        <label>চলমান লেখা</label>
        <textarea name="message" rows="3" maxlength="2000" required><?php echo esc_html($row['message']); ?></textarea>
      </div>

      <div class="dw-group">
        <label>আইকন (চলমান লেখা প্যাক)</label>
        <?php echo ame_icon_picker_html('ticker', $row['icon_key']); ?>
      </div>

      <div class="dw-row">
        <div class="dw-color"><span>আইকন রং</span><input type="color" name="icon_color" value="<?php echo esc_html($row['icon_color']); ?>"><input type="text" data-sync="icon_color" value="<?php echo esc_html($row['icon_color']); ?>"></div>
        <div class="dw-color"><span>আইকন ব্যাকগ্রাউন্ড</span><input type="color" name="icon_bg" value="<?php echo esc_html($row['icon_bg']); ?>"><input type="text" data-sync="icon_bg" value="<?php echo esc_html($row['icon_bg']); ?>"></div>
        <div class="dw-color"><span>লেখার রং</span><input type="color" name="text_color" value="<?php echo esc_html($row['text_color']); ?>"><input type="text" data-sync="text_color" value="<?php echo esc_html($row['text_color']); ?>"></div>
        <div class="dw-color"><span>বারের রং</span><input type="color" name="bg_color" value="<?php echo esc_html($row['bg_color']); ?>"><input type="text" data-sync="bg_color" value="<?php echo esc_html($row['bg_color']); ?>"></div>
        <div class="dw-color"><span>বিভাজক রং</span><input type="color" name="accent_color" value="<?php echo esc_html($row['accent_color']); ?>"><input type="text" data-sync="accent_color" value="<?php echo esc_html($row['accent_color']); ?>"></div>
      </div>

      <div class="dw-row">
        <div class="dw-group">
          <label>ফন্ট সাইজ</label>
          <input type="number" name="font_size" min="11" max="22" value="<?php echo (int)$row['font_size']; ?>">
        </div>
        <div class="dw-group">
          <label>মোটা লেখা</label>
          <select name="font_weight">
            <?php foreach (['400'=>'সাধারণ','600'=>'মাঝারি','700'=>'মোটা','800'=>'খুব মোটা'] as $k=>$lab): ?>
            <option value="<?php echo $k; ?>" <?php echo $row['font_weight']===$k?'selected':''; ?>><?php echo $lab; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="dw-group">
          <label>গতি (সেকেন্ড)</label>
          <input type="number" name="speed_seconds" min="8" max="80" value="<?php echo (int)$row['speed_seconds']; ?>">
        </div>
        <div class="dw-group">
          <label>দিক</label>
          <select name="direction">
            <option value="left" <?php echo $row['direction']==='left'?'selected':''; ?>>বামে যাবে</option>
            <option value="right" <?php echo $row['direction']==='right'?'selected':''; ?>>ডানে যাবে</option>
          </select>
        </div>
      </div>
      <div class="dw-group">
        <label>ক্রম</label>
        <input type="number" name="sort_order" min="0" value="<?php echo (int)$row['sort_order']; ?>">
      </div>
      <button class="dw-submit" type="submit">সেভ করুন</button>
    </form>
  </div>
</div>
<script>
(function(){
  var icons = <?php echo json_encode(array_map(fn($k) => ame_icon_svg('ticker', $k, 15), array_keys($pack)), JSON_UNESCAPED_UNICODE); ?>;
  var keys = <?php echo json_encode(array_keys($pack)); ?>;
  var form = document.getElementById('tickForm');
  function val(name){ var el=form.querySelector('[name="'+name+'"]'); return el ? el.value : ''; }
  function lock(el, prop, value){
    if (!el || !value) return;
    el.style.setProperty(prop, value, 'important');
  }
  function paint(){
    var box = document.getElementById('tickPreview');
    var track = document.getElementById('pvTrack');
    var msg = val('message') || 'এখানে আপনার চলমান লেখা দেখাবে';
    var accent = val('accent_color');
    var fg = val('text_color');
    var bg = val('bg_color');
    var size = (parseInt(val('font_size'), 10) || 16) + 'px';
    var weight = val('font_weight') || '700';
    var secs = parseInt(val('speed_seconds'), 10);
    if (!secs || secs < 8) secs = 8;
    if (secs > 80) secs = 80;
    lock(box, 'background', bg);
    lock(box, 'color', fg);
    lock(box, '-webkit-text-fill-color', fg);
    lock(box, 'font-size', size);
    lock(box, 'font-weight', weight);
    document.getElementById('pvText').textContent = msg;
    document.getElementById('pvText2').textContent = msg;
    lock(document.getElementById('pvText'), 'color', fg);
    lock(document.getElementById('pvText'), '-webkit-text-fill-color', fg);
    lock(document.getElementById('pvText2'), 'color', fg);
    lock(document.getElementById('pvText2'), '-webkit-text-fill-color', fg);
    lock(document.getElementById('pvSep'), 'color', accent);
    lock(document.getElementById('pvSep'), '-webkit-text-fill-color', accent);
    lock(document.getElementById('pvSep2'), 'color', accent);
    lock(document.getElementById('pvSep2'), '-webkit-text-fill-color', accent);
    track.style.setProperty('--dw-speed', secs + 's');
    track.classList.toggle('is-right', val('direction') === 'right');
    var ic = document.getElementById('pvIcon');
    lock(ic, 'background', val('icon_bg'));
    lock(ic, 'color', val('icon_color'));
    lock(ic, '-webkit-text-fill-color', val('icon_color'));
    var key = val('icon_key');
    var idx = keys.indexOf(key);
    if (idx >= 0) ic.innerHTML = icons[idx];
    var bar = document.getElementById('ameTickerBar');
    if (!bar) return;
    lock(bar, 'background', bg);
    lock(bar, 'color', fg);
    lock(bar, '-webkit-text-fill-color', fg);
    lock(bar, 'font-size', size);
    lock(bar, 'font-weight', weight);
    bar.querySelectorAll('.ame-tbar-item').forEach(function(el){
      el.textContent = msg;
      lock(el, 'color', fg);
      lock(el, '-webkit-text-fill-color', fg);
      lock(el, 'font-size', size);
      lock(el, 'font-weight', weight);
    });
    bar.querySelectorAll('.ame-tbar-sep').forEach(function(el){
      lock(el, 'color', accent);
      lock(el, '-webkit-text-fill-color', accent);
    });
    var liveIcon = bar.querySelector('.ame-tbar-icon');
    if (liveIcon) {
      lock(liveIcon, 'background', val('icon_bg'));
      lock(liveIcon, 'color', val('icon_color'));
      lock(liveIcon, '-webkit-text-fill-color', val('icon_color'));
      if (idx >= 0) liveIcon.innerHTML = icons[idx];
    }
    var liveTrack = bar.querySelector('.ame-tbar-track');
    if (liveTrack) {
      var motion = val('direction') === 'right' ? 'ame-tick-right' : 'ame-tick-left';
      liveTrack.style.setProperty('--ame-tick-speed', secs + 's');
      liveTrack.classList.toggle('right', val('direction') === 'right');
      liveTrack.classList.toggle('left', val('direction') !== 'right');
      liveTrack.style.setProperty('animation', motion + ' ' + secs + 's linear infinite', 'important');
    }
  }
  form.addEventListener('input', paint);
  form.addEventListener('change', paint);
  form.querySelectorAll('input[type=color]').forEach(function(c){
    c.addEventListener('input', function(){
      var t = form.querySelector('[data-sync="'+c.name+'"]');
      if (t) t.value = c.value;
      paint();
    });
  });
  form.querySelectorAll('[data-sync]').forEach(function(t){
    t.addEventListener('input', function(){
      var c = form.querySelector('input[name="'+t.getAttribute('data-sync')+'"]');
      if (c && /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(t.value)) c.value = t.value;
      paint();
    });
  });
  form.querySelectorAll('.dw-icon').forEach(function(lab){
    lab.addEventListener('click', function(){
      form.querySelectorAll('.dw-icon').forEach(function(x){ x.classList.remove('selected'); });
      lab.classList.add('selected');
      setTimeout(paint, 0);
    });
  });
})();
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
