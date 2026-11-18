<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/display_widgets.php';
admin_only();
include __DIR__ . '/../../partials/header.php';

ensure_display_widgets_schema($db);
$items = ame_get_widgets($db, 'countdown');
$edit_id = (int)($_GET['edit'] ?? 0);
$row = $edit_id ? ame_get_widget($db, $edit_id, 'countdown') : null;
if (!$row) {
    $row = ame_widget_defaults('countdown');
}
$ends_local = '';
$end_year = (int)date('Y');
$end_month = (int)date('n');
$end_day = (int)date('j');
$end_time = '23:59';
if (!empty($row['ends_at'])) {
    $end_ts = strtotime($row['ends_at']);
    if ($end_ts) {
        $ends_local = date('Y-m-d\TH:i', $end_ts);
        $end_year = (int)date('Y', $end_ts);
        $end_month = (int)date('n', $end_ts);
        $end_day = (int)date('j', $end_ts);
        $end_time = date('H:i', $end_ts);
    }
}
$bn_months = [
    1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 4 => 'এপ্রিল',
    5 => 'মে', 6 => 'জুন', 7 => 'জুলাই', 8 => 'আগস্ট',
    9 => 'সেপ্টেম্বর', 10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর',
];
$pack = ame_countdown_icon_pack();
?>
<link rel="stylesheet" href="/assets/css/display-editor.css?v=<?php echo esc_html($sys_version ?? '1.0'); ?>">
<style>
  .dw-cd-accent .dw-submit { background:linear-gradient(135deg,#0B1E4D,#6A1B9A); }
  .dw-cd-accent .dw-new { background:#6A1B9A; }
</style>

<div class="dw-wrap dw-cd-accent">
  <div class="dw-card">
    <div class="dw-top"><span style="background:#0B1E4D"></span><span style="background:#6A1B9A"></span><span style="background:#FFD600"></span></div>
    <div class="dw-head">
      <h3>কাউন্টডাউন</h3>
      <small>অফার/ইভেন্টের সময় — তারিখ, রং, বক্স স্টাইল ও নিজস্ব আইকন প্যাক</small>
    </div>
    <?php if (isset($_GET['saved'])): ?><div class="dw-ok">সেভ হয়েছে। সব পেজে দেখাবে।</div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="dw-ok">মুছে ফেলা হয়েছে।</div><?php endif; ?>
    <?php if (!empty($_GET['err'])): ?><div class="dw-ok" style="background:#FFEBEE;color:#C62828;"><?php echo esc_html((string)$_GET['err']); ?></div><?php endif; ?>

    <div class="dw-preview-label">লাইভ প্রিভিউ</div>
    <div class="dw-preview" id="cdPreview" style="background:<?php echo esc_html($row['bg_color']); ?>;color:<?php echo esc_html($row['text_color']); ?>;padding:10px;">
      <div style="display:flex;align-items:center;gap:10px;">
        <span id="cdPvIcon" style="width:32px;height:32px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:<?php echo esc_html($row['icon_bg']); ?>;color:<?php echo esc_html($row['icon_color']); ?>;">
          <?php echo ame_icon_svg('countdown', $row['icon_key'], 18); ?>
        </span>
        <div style="flex:1;min-width:0;">
          <div id="cdPvTitle" style="font-weight:800;"><?php echo esc_html($row['title'] ?: 'কাউন্টডাউন'); ?></div>
          <div id="cdPvSub" style="font-size:11px;opacity:.9;"><?php echo esc_html($row['message']); ?></div>
        </div>
        <div id="cdPvUnits" style="display:flex;gap:6px;">
          <?php
          $pv_digit_fs = ame_sanitize_font_size($row['digit_font_size'] ?? $row['font_size'], 14, 11, 28);
          $pv_digit_fw = ame_sanitize_font_weight($row['digit_font_weight'] ?? $row['font_weight'] ?? '800', '800');
          $pv_digit_ff = ame_sanitize_font_family($row['digit_font_family'] ?? '', '');
          $pv_digit_style = 'background:' . esc_html($row['digit_bg']) . ';color:' . esc_html($row['digit_color']) . ';border-radius:8px;padding:4px 7px;text-align:center;font-size:' . $pv_digit_fs . 'px;font-weight:' . esc_html($pv_digit_fw) . ';';
          if ($pv_digit_ff !== '') {
              $pv_digit_style .= 'font-family:' . $pv_digit_ff . ';';
          }
          ?>
          <div class="ame-mini" style="<?php echo $pv_digit_style; ?>">12<br><small style="color:<?php echo esc_html($row['label_color']); ?>;font-size:9px;">দিন</small></div>
          <div class="ame-mini" style="<?php echo $pv_digit_style; ?>">08<br><small style="color:<?php echo esc_html($row['label_color']); ?>;font-size:9px;">ঘণ্টা</small></div>
        </div>
      </div>
    </div>

    <a class="dw-new" href="countdown.php">+ নতুন কাউন্টডাউন</a>

    <div class="dw-list">
      <?php if (!$items): ?>
        <div style="text-align:center;color:#94A3B8;font-size:13px;padding:10px;">এখনো কোনো কাউন্টডাউন নেই।</div>
      <?php endif; ?>
      <?php foreach ($items as $it): ?>
      <div class="dw-item">
        <div class="dw-ico" style="background:<?php echo esc_html($it['icon_bg']); ?>;color:<?php echo esc_html($it['icon_color']); ?>;">
          <?php echo ame_icon_svg('countdown', $it['icon_key'], 18); ?>
        </div>
        <div>
          <b><?php echo esc_html($it['title'] ?: 'কাউন্টডাউন'); ?></b>
          <span><?php echo !empty($it['ends_at']) ? date('d M Y, h:i A', strtotime($it['ends_at'])) : 'তারিখ নেই'; ?> · <?php echo (int)$it['is_enabled'] ? 'চালু' : 'বন্ধ'; ?></span>
        </div>
        <div class="dw-actions">
          <form action="process_display_widget.php" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="kind" value="countdown">
            <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
            <input type="hidden" name="action" value="toggle">
            <button type="submit"><?php echo (int)$it['is_enabled'] ? 'বন্ধ' : 'চালু'; ?></button>
          </form>
          <a href="countdown.php?edit=<?php echo (int)$it['id']; ?>">এডিট</a>
          <form action="process_display_widget.php" method="post" onsubmit="return confirm('মুছে ফেলবেন?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="kind" value="countdown">
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
      <h3><?php echo $row['id'] ? 'কাউন্টডাউন সম্পাদনা' : 'নতুন কাউন্টডাউন'; ?></h3>
      <small>কাউন্টডাউন আইকন প্যাক থেকে আইকন বাছুন</small>
    </div>
    <form class="dw-body" action="process_display_widget.php" method="post" id="cdForm">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="kind" value="countdown">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">

      <div class="dw-check"><input type="checkbox" name="is_enabled" value="1" <?php echo (int)$row['is_enabled'] ? 'checked' : ''; ?>> হেডারে দেখাবে</div>

      <div class="dw-group">
        <label>শিরোনাম</label>
        <input type="text" name="title" value="<?php echo esc_html($row['title']); ?>" maxlength="200" required>
      </div>
      <div class="dw-group">
        <label>কাউন্টডাউনের ভিতরের লেখা</label>
        <textarea name="message" rows="3" maxlength="2000" placeholder="এখানে অফার/ইভেন্টের লেখা লিখুন"><?php echo esc_html($row['message']); ?></textarea>
      </div>
      <div class="dw-group">
        <label>শেষ হওয়ার তারিখ — তারিখ, মাস, বছর আলাদা করে দিন</label>
        <div class="dw-row">
          <div class="dw-group">
            <label>তারিখ</label>
            <select name="end_day" required>
              <?php for ($d = 1; $d <= 31; $d++): ?>
              <option value="<?php echo $d; ?>" <?php echo $end_day === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="dw-group">
            <label>মাস</label>
            <select name="end_month" required>
              <?php foreach ($bn_months as $num => $name): ?>
              <option value="<?php echo $num; ?>" <?php echo $end_month === $num ? 'selected' : ''; ?>><?php echo $name; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="dw-group">
            <label>বছর</label>
            <select name="end_year" required>
              <?php
              $year_from = min($end_year, (int)date('Y') - 2);
              $year_to = max($end_year, (int)date('Y') + 10);
              for ($y = $year_from; $y <= $year_to; $y++):
              ?>
              <option value="<?php echo $y; ?>" <?php echo $end_year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="dw-group">
        <label>সময়</label>
        <input type="time" name="end_time" value="<?php echo esc_html($end_time); ?>" required>
        <input type="hidden" name="ends_at" id="cdEndsAt" value="<?php echo esc_html($ends_local); ?>">
      </div>
      <div class="dw-group">
        <label>সময় শেষে কী দেখাবে</label>
        <input type="text" name="ended_message" value="<?php echo esc_html($row['ended_message']); ?>">
      </div>
      <div class="dw-check"><input type="checkbox" name="hide_when_ended" value="1" <?php echo (int)$row['hide_when_ended'] ? 'checked' : ''; ?>> শেষ হলে বার লুকিয়ে রাখবে</div>

      <div class="dw-group">
        <label>আইকন (কাউন্টডাউন প্যাক)</label>
        <?php echo ame_icon_picker_html('countdown', $row['icon_key']); ?>
      </div>

      <div class="dw-row">
        <div class="dw-color"><span>আইকন রং</span><input type="color" name="icon_color" value="<?php echo esc_html($row['icon_color']); ?>"><input type="text" data-sync="icon_color" value="<?php echo esc_html($row['icon_color']); ?>"></div>
        <div class="dw-color"><span>আইকন ব্যাকগ্রাউন্ড</span><input type="color" name="icon_bg" value="<?php echo esc_html($row['icon_bg']); ?>"><input type="text" data-sync="icon_bg" value="<?php echo esc_html($row['icon_bg']); ?>"></div>
        <div class="dw-color"><span>লেখার রং</span><input type="color" name="text_color" value="<?php echo esc_html($row['text_color']); ?>"><input type="text" data-sync="text_color" value="<?php echo esc_html($row['text_color']); ?>"></div>
        <div class="dw-color"><span>বারের রং</span><input type="color" name="bg_color" value="<?php echo esc_html($row['bg_color']); ?>"><input type="text" data-sync="bg_color" value="<?php echo esc_html($row['bg_color']); ?>"></div>
        <div class="dw-color"><span>সংখ্যার রং</span><input type="color" name="digit_color" value="<?php echo esc_html($row['digit_color']); ?>"><input type="text" data-sync="digit_color" value="<?php echo esc_html($row['digit_color']); ?>"></div>
        <div class="dw-color"><span>সংখ্যার বক্স</span><input type="color" name="digit_bg" value="<?php echo esc_html($row['digit_bg']); ?>"><input type="text" data-sync="digit_bg" value="<?php echo esc_html($row['digit_bg']); ?>"></div>
        <div class="dw-color"><span>লেবেল রং</span><input type="color" name="label_color" value="<?php echo esc_html($row['label_color']); ?>"><input type="text" data-sync="label_color" value="<?php echo esc_html($row['label_color']); ?>"></div>
        <div class="dw-color"><span>অ্যাকসেন্ট</span><input type="color" name="accent_color" value="<?php echo esc_html($row['accent_color']); ?>"><input type="text" data-sync="accent_color" value="<?php echo esc_html($row['accent_color']); ?>"></div>
      </div>

      <div class="dw-group" style="margin-top:4px;">
        <label>সময়ের সংখ্যা — ফন্ট ও রং (ঘড়ির টাইম)</label>
      </div>
      <div class="dw-row">
        <div class="dw-group">
          <label>সময়ের ফন্ট সাইজ (px)</label>
          <input type="number" name="digit_font_size" min="11" max="28" value="<?php echo (int)($row['digit_font_size'] ?? $row['font_size'] ?? 14); ?>">
        </div>
        <div class="dw-group">
          <label>সময়ের ফন্ট ওজন</label>
          <?php echo ame_font_weight_select_html('digit_font_weight', (string)($row['digit_font_weight'] ?? $row['font_weight'] ?? '800')); ?>
        </div>
      </div>
      <div class="dw-group">
        <label>সময়ের ফন্ট পরিবার</label>
        <?php echo ame_font_family_select_html('digit_font_family', (string)($row['digit_font_family'] ?? '')); ?>
      </div>

      <div class="dw-row">
        <div class="dw-group">
          <label>বারের ফন্ট সাইজ</label>
          <input type="number" name="font_size" min="11" max="22" value="<?php echo (int)$row['font_size']; ?>">
        </div>
        <div class="dw-group">
          <label>স্টাইল</label>
          <select name="box_style">
            <option value="boxed" <?php echo $row['box_style']==='boxed'?'selected':''; ?>>বক্সসহ সংখ্যা</option>
            <option value="plain" <?php echo $row['box_style']==='plain'?'selected':''; ?>>সাদামাটা</option>
          </select>
        </div>
      </div>

      <label class="dw-check"><input type="checkbox" name="show_days" value="1" <?php echo (int)$row['show_days'] ? 'checked' : ''; ?>> দিন দেখাবে</label>
      <label class="dw-check"><input type="checkbox" name="show_hours" value="1" <?php echo (int)$row['show_hours'] ? 'checked' : ''; ?>> ঘণ্টা দেখাবে</label>
      <label class="dw-check"><input type="checkbox" name="show_minutes" value="1" <?php echo (int)$row['show_minutes'] ? 'checked' : ''; ?>> মিনিট দেখাবে</label>
      <label class="dw-check"><input type="checkbox" name="show_seconds" value="1" <?php echo (int)$row['show_seconds'] ? 'checked' : ''; ?>> সেকেন্ড দেখাবে</label>

      <div class="dw-group">
        <label>ক্রম</label>
        <input type="number" name="sort_order" min="0" value="<?php echo (int)$row['sort_order']; ?>">
      </div>
      <input type="hidden" name="font_weight" value="<?php echo esc_html($row['font_weight'] ?: '700'); ?>">
      <button class="dw-submit" type="submit">সেভ করুন</button>
    </form>
  </div>
</div>
<script>
(function(){
  var icons = <?php echo json_encode(array_map(fn($k) => ame_icon_svg('countdown', $k, 18), array_keys($pack)), JSON_UNESCAPED_UNICODE); ?>;
  var keys = <?php echo json_encode(array_keys($pack)); ?>;
  var form = document.getElementById('cdForm');
  function val(name){ var el=form.querySelector('[name="'+name+'"]'); return el ? el.value : ''; }
  function paint(){
    var box = document.getElementById('cdPreview');
    box.style.background = val('bg_color');
    box.style.color = val('text_color');
    document.getElementById('cdPvTitle').textContent = val('title') || 'কাউন্টডাউন';
    document.getElementById('cdPvSub').textContent = val('message');
    var ic = document.getElementById('cdPvIcon');
    ic.style.background = val('icon_bg');
    ic.style.color = val('icon_color');
    var idx = keys.indexOf(val('icon_key'));
    if (idx >= 0) ic.innerHTML = icons[idx];
    box.querySelectorAll('.ame-mini').forEach(function(u){
      var digitFs = parseInt(val('digit_font_size'), 10) || 14;
      var digitFw = val('digit_font_weight') || '800';
      var digitFf = val('digit_font_family') || '';
      u.style.background = val('digit_bg');
      u.style.color = val('digit_color');
      u.style.fontSize = digitFs + 'px';
      u.style.fontWeight = digitFw;
      u.style.fontFamily = digitFf;
      var sm = u.querySelector('small');
      if (sm) sm.style.color = val('label_color');
    });
  }
  form.addEventListener('input', paint);
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
  function pad2(n) {
    n = String(n);
    return n.length < 2 ? '0' + n : n;
  }
  function composeEnds() {
    var hidden = document.getElementById('cdEndsAt');
    if (!hidden) return;
    hidden.value = val('end_year') + '-' + pad2(val('end_month')) + '-' + pad2(val('end_day')) + 'T' + (val('end_time') || '23:59');
  }
  form.addEventListener('submit', composeEnds);
  form.addEventListener('change', composeEnds);
  composeEnds();
})();
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
