<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
if (!isset($db) || !($db instanceof mysqli)) {
    return;
}
require_once __DIR__ . '/../includes/display_widgets.php';
try {
    ensure_display_widgets_schema($db);
    $ame_tickers = ame_get_widgets($db, 'ticker', true);
    $ame_countdowns = ame_get_widgets($db, 'countdown', true);
} catch (Throwable $e) {
    $ame_tickers = [];
    $ame_countdowns = [];
}

$has_ticker = count($ame_tickers) > 0;
$has_cd = false;
$now_ts = time();
foreach ($ame_countdowns as $cd) {
    $end_ts = !empty($cd['ends_at']) ? strtotime($cd['ends_at']) : 0;
    if ($end_ts && $end_ts <= $now_ts && (int)$cd['hide_when_ended'] === 1) {
        continue;
    }
    $has_cd = true;
    break;
}
$ame_bar_pad = 64 + ($has_ticker ? 46 : 0);
foreach ($ame_countdowns as $cd_pad) {
    $end_ts_pad = !empty($cd_pad['ends_at']) ? strtotime($cd_pad['ends_at']) : 0;
    if ($end_ts_pad && $end_ts_pad <= $now_ts && (int)$cd_pad['hide_when_ended'] === 1) {
        continue;
    }
    $ame_bar_pad += 58;
}
?>
<style>#main-content{padding-top:<?php echo (int)$ame_bar_pad; ?>px !important;} .dw-wrap,.dw-wrap *,.ame-tbar,.ame-tbar *{flex-wrap:nowrap !important;}</style>
<link rel="stylesheet" href="/assets/css/display-widgets.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0'); ?>">

<?php if ($has_ticker):
    $style = $ame_tickers[0];
    $speed = max(8, (int)$style['speed_seconds']);
    $dir = $style['direction'] === 'right' ? 'right' : 'left';
    $parts = [];
    foreach ($ame_tickers as $t) {
        $txt = trim((string)($t['message'] ?: $t['title']));
        if ($txt === '') continue;
        $parts[] = $txt;
    }
    if (!$parts) {
        $parts[] = 'স্বাগতম!';
    }
    $chunk = implode('   •   ', $parts);
    ?>
<div id="ameTickerBar" class="ame-tbar ame-tbar-ticker ame-tbar-above" data-ame-above="icons" style="background:<?php echo esc_html($style['bg_color']); ?>;color:<?php echo esc_html($style['text_color']); ?>;font-size:<?php echo (int)$style['font_size']; ?>px;font-weight:<?php echo esc_html($style['font_weight']); ?>;">
    <span class="ame-tbar-icon" style="background:<?php echo esc_html($style['icon_bg']); ?>;color:<?php echo esc_html($style['icon_color']); ?>;">
        <?php echo ame_icon_svg('ticker', $style['icon_key'], 15); ?>
    </span>
    <div class="ame-tbar-track-wrap">
        <div class="ame-tbar-track <?php echo $dir; ?>" style="--ame-tick-speed: <?php echo $speed; ?>s;">
            <span class="ame-tbar-item"><?php echo esc_html($chunk); ?></span>
            <span class="ame-tbar-sep" style="color:<?php echo esc_html($style['accent_color']); ?>;">✦</span>
            <span class="ame-tbar-item"><?php echo esc_html($chunk); ?></span>
            <span class="ame-tbar-sep" style="color:<?php echo esc_html($style['accent_color']); ?>;">✦</span>
        </div>
    </div>
</div>
<script>
(function () {
    var ticker = document.getElementById('ameTickerBar');
    var header = document.querySelector('.master-header-fixed');
    if (!ticker || !header) return;
    if (ticker.parentNode !== header) header.insertBefore(ticker, header.firstChild);
    header.style.setProperty('flex-wrap', 'wrap', 'important');
    header.style.setProperty('height', 'auto', 'important');
    ticker.style.setProperty('position', 'relative', 'important');
    ticker.style.setProperty('top', 'auto', 'important');
    ticker.style.setProperty('left', '0', 'important');
    ticker.style.setProperty('width', '100%', 'important');
    ticker.style.setProperty('order', '-1', 'important');
    ticker.style.setProperty('flex', '1 0 100%', 'important');
})();
</script>
<?php endif; ?>

<?php
$cd_index = 0;
foreach ($ame_countdowns as $cd):
    $end_ts = !empty($cd['ends_at']) ? strtotime($cd['ends_at']) : 0;
    if ($end_ts && $end_ts <= $now_ts && (int)$cd['hide_when_ended'] === 1) {
        continue;
    }
    $cd_index++;
    $end_attr = $end_ts ? date('c', $end_ts) : '';
    $cd_units = [
        'days' => ['on' => (int)$cd['show_days'], 'lab' => t('cd_days')],
        'hours' => ['on' => (int)$cd['show_hours'], 'lab' => t('cd_hours')],
        'minutes' => ['on' => (int)$cd['show_minutes'], 'lab' => t('cd_minutes')],
        'seconds' => ['on' => (int)$cd['show_seconds'], 'lab' => t('cd_seconds')],
    ];
    $digit_fs = ame_sanitize_font_size($cd['digit_font_size'] ?? $cd['font_size'], 14, 11, 28);
    $digit_fw = ame_sanitize_font_weight($cd['digit_font_weight'] ?? $cd['font_weight'] ?? '800', '800');
    $digit_ff = ame_sanitize_font_family($cd['digit_font_family'] ?? '', '');
    $digit_style = 'color:' . esc_html($cd['digit_color']) . ';font-size:' . $digit_fs . 'px;font-weight:' . esc_html($digit_fw) . ';';
    if ($digit_ff !== '') {
        $digit_style .= 'font-family:' . $digit_ff . ';';
    }
    ?>
<div class="ame-tbar ame-tbar-cd" data-end="<?php echo esc_html($end_attr); ?>" data-hide="<?php echo (int)$cd['hide_when_ended']; ?>" data-ended="<?php echo esc_html($cd['ended_message']); ?>" style="background:<?php echo esc_html($cd['bg_color']); ?>;color:<?php echo esc_html($cd['text_color']); ?>;font-size:<?php echo (int)$cd['font_size']; ?>px;font-weight:<?php echo esc_html($cd['font_weight']); ?>;">
    <div class="ame-cd-row">
        <span class="ame-tbar-icon" style="background:<?php echo esc_html($cd['icon_bg']); ?>;color:<?php echo esc_html($cd['icon_color']); ?>;width:32px;height:32px;border-radius:10px;">
            <?php echo ame_icon_svg('countdown', $cd['icon_key'], 18); ?>
        </span>
        <div class="ame-cd-copy">
            <div class="ame-cd-title"><?php echo esc_html($cd['title'] ?: t('nav_countdown')); ?></div>
            <?php if (trim((string)$cd['message']) !== ''): ?>
            <div class="ame-cd-sub"><?php echo esc_html($cd['message']); ?></div>
            <?php endif; ?>
            <?php if ($end_ts): ?>
            <div class="ame-cd-enddate"><?php echo esc_html(date('d M Y', $end_ts)); ?></div>
            <?php endif; ?>
            <div data-cd-done style="display:none;font-weight:800;"></div>
        </div>
        <div class="ame-cd-units" data-cd-live>
            <?php foreach ($cd_units as $key => $u): if (!$u['on']) continue; ?>
            <div class="ame-cd-unit <?php echo $cd['box_style'] === 'plain' ? 'plain' : ''; ?>" style="background:<?php echo esc_html($cd['digit_bg']); ?>;">
                <span class="ame-cd-num" data-cd="<?php echo $key; ?>" style="<?php echo $digit_style; ?>">00</span>
                <span class="ame-cd-lab" style="color:<?php echo esc_html($cd['label_color']); ?>;"><?php echo $u['lab']; ?></span>
            </div>
            <?php endforeach; ?>
            <?php if ($end_ts): ?>
            <div class="ame-cd-until" title="<?php echo esc_html(date('d M Y, h:i A', $end_ts)); ?>">
                <?php echo esc_html(date('d/m/Y', $end_ts)); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
<script src="/assets/js/display-widgets.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0'); ?>"></script>
