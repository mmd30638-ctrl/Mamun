<?php
require_once __DIR__ . '/icon_pack_ticker.php';
require_once __DIR__ . '/icon_pack_countdown.php';
require_once __DIR__ . '/display_fonts.php';

function ame_hex_color(?string $value, string $fallback): string
{
    $value = trim((string)$value);
    if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
        return strtoupper($value);
    }
    return $fallback;
}

function ame_icon_svg(string $kind, string $key, int $size = 18): string
{
    $pack = $kind === 'countdown' ? ame_countdown_icon_pack() : ame_ticker_icon_pack();
    if (!isset($pack[$key])) {
        $key = $kind === 'countdown' ? 'hourglass' : 'megaphone';
    }
    $inner = $pack[$key]['svg'] ?? '';
    $size = max(12, min(48, $size));
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

function ame_icon_keys(string $kind): array
{
    return array_keys($kind === 'countdown' ? ame_countdown_icon_pack() : ame_ticker_icon_pack());
}

function ensure_display_widgets_schema(mysqli $db): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('display_widgets')) {
        return;
    }
    $db->query("CREATE TABLE IF NOT EXISTS display_widgets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kind VARCHAR(20) NOT NULL,
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        title VARCHAR(200) NOT NULL DEFAULT '',
        message TEXT,
        icon_key VARCHAR(40) NOT NULL DEFAULT 'megaphone',
        icon_color VARCHAR(20) NOT NULL DEFAULT '#FFFFFF',
        icon_bg VARCHAR(20) NOT NULL DEFAULT '#0B1E4D',
        text_color VARCHAR(20) NOT NULL DEFAULT '#1B5E20',
        bg_color VARCHAR(20) NOT NULL DEFAULT '#FFFFFF',
        accent_color VARCHAR(20) NOT NULL DEFAULT '#FFD600',
        digit_color VARCHAR(20) NOT NULL DEFAULT '#0B1E4D',
        digit_bg VARCHAR(20) NOT NULL DEFAULT '#FFFFFF',
        label_color VARCHAR(20) NOT NULL DEFAULT '#E2E8F0',
        digit_font_size INT NOT NULL DEFAULT 14,
        digit_font_weight VARCHAR(10) NOT NULL DEFAULT '800',
        digit_font_family VARCHAR(120) NOT NULL DEFAULT '',
        font_size INT NOT NULL DEFAULT 13,
        font_weight VARCHAR(10) NOT NULL DEFAULT '700',
        speed_seconds INT NOT NULL DEFAULT 22,
        direction VARCHAR(10) NOT NULL DEFAULT 'left',
        ends_at DATETIME NULL,
        ended_message VARCHAR(255) NOT NULL DEFAULT 'সময় শেষ হয়েছে',
        hide_when_ended TINYINT(1) NOT NULL DEFAULT 0,
        show_days TINYINT(1) NOT NULL DEFAULT 1,
        show_hours TINYINT(1) NOT NULL DEFAULT 1,
        show_minutes TINYINT(1) NOT NULL DEFAULT 1,
        show_seconds TINYINT(1) NOT NULL DEFAULT 1,
        box_style VARCHAR(20) NOT NULL DEFAULT 'boxed',
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY kind_enabled (kind, is_enabled, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ame_display_widget_ensure_columns($db);

    $count = $db->query("SELECT COUNT(*) c FROM display_widgets");
    $n = $count ? (int)($count->fetch_assoc()['c'] ?? 0) : 0;
    if ($n > 0) {
        if (function_exists('ame_schema_stamp')) {
            ame_schema_stamp('display_widgets', true);
        }
        return;
    }

    $ticker = $db->prepare("INSERT INTO display_widgets
        (kind, is_enabled, title, message, icon_key, icon_color, icon_bg, text_color, bg_color, accent_color, font_size, font_weight, speed_seconds, direction)
        VALUES ('ticker', 1, 'স্বাগতম', ?, 'megaphone', '#FFFFFF', '#0B1E4D', '#1B5E20', '#FFFFFF', '#FFD600', 13, '700', 22, 'left')");
    $shopLine = function_exists('shop_name') ? shop_name() : '';
    $tagLine = function_exists('shop_tagline') ? shop_tagline() : '';
    $msg = trim($shopLine . ($tagLine !== '' ? ' · ' . $tagLine : ''));
    if ($msg === '') {
        $msg = 'স্বাগতম! আপনার প্রতিষ্ঠানের সকল হিসাব এখন আমাদের উন্নত প্রযুক্তিতে আরও নিরাপদ এবং স্বচ্ছ।';
    }
    $ticker->bind_param('s', $msg);
    $ticker->execute();
    $ticker->close();

    $end = (new DateTime('now', new DateTimeZone('Asia/Dhaka')))->modify('+15 days')->format('Y-m-d 23:59:59');
    $cd = $db->prepare("INSERT INTO display_widgets
        (kind, is_enabled, title, message, icon_key, icon_color, icon_bg, text_color, bg_color, accent_color, digit_color, digit_bg, label_color, font_size, ends_at, ended_message, box_style)
        VALUES ('countdown', 0, 'বিশেষ অফার', 'শেষ হতে বাকি', 'hourglass', '#0B1E4D', '#FFD600', '#FFFFFF', '#0B1E4D', '#FFD600', '#0B1E4D', '#FFFFFF', '#FFE082', 13, ?, 'অফার শেষ হয়েছে', 'boxed')");
    $cd->bind_param('s', $end);
    $cd->execute();
    $cd->close();
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('display_widgets', true);
    }
}

function ame_display_widget_ensure_columns(mysqli $db): void
{
    $cols = [
        'kind' => "VARCHAR(20) NOT NULL DEFAULT 'ticker'",
        'is_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'title' => "VARCHAR(200) NOT NULL DEFAULT ''",
        'message' => 'TEXT NULL',
        'icon_key' => "VARCHAR(40) NOT NULL DEFAULT 'megaphone'",
        'icon_color' => "VARCHAR(20) NOT NULL DEFAULT '#FFFFFF'",
        'icon_bg' => "VARCHAR(20) NOT NULL DEFAULT '#0B1E4D'",
        'text_color' => "VARCHAR(20) NOT NULL DEFAULT '#1B5E20'",
        'bg_color' => "VARCHAR(20) NOT NULL DEFAULT '#FFFFFF'",
        'accent_color' => "VARCHAR(20) NOT NULL DEFAULT '#FFD600'",
        'digit_color' => "VARCHAR(20) NOT NULL DEFAULT '#0B1E4D'",
        'digit_bg' => "VARCHAR(20) NOT NULL DEFAULT '#FFFFFF'",
        'label_color' => "VARCHAR(20) NOT NULL DEFAULT '#E2E8F0'",
        'digit_font_size' => 'INT NOT NULL DEFAULT 14',
        'digit_font_weight' => "VARCHAR(10) NOT NULL DEFAULT '800'",
        'digit_font_family' => "VARCHAR(120) NOT NULL DEFAULT ''",
        'font_size' => 'INT NOT NULL DEFAULT 13',
        'font_weight' => "VARCHAR(10) NOT NULL DEFAULT '700'",
        'speed_seconds' => 'INT NOT NULL DEFAULT 22',
        'direction' => "VARCHAR(10) NOT NULL DEFAULT 'left'",
        'ends_at' => 'DATETIME NULL',
        'ended_message' => "VARCHAR(255) NOT NULL DEFAULT 'সময় শেষ হয়েছে'",
        'hide_when_ended' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'show_days' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'show_hours' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'show_minutes' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'show_seconds' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'box_style' => "VARCHAR(20) NOT NULL DEFAULT 'boxed'",
        'sort_order' => 'INT NOT NULL DEFAULT 0',
    ];
    $have = [];
    $res = @$db->query('SHOW COLUMNS FROM display_widgets');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $have[(string)($row['Field'] ?? '')] = true;
        }
        $res->free();
    }
    foreach ($cols as $col => $ddl) {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
        if ($safe === '' || isset($have[$safe])) {
            continue;
        }
        @$db->query("ALTER TABLE display_widgets ADD COLUMN `{$safe}` {$ddl}");
    }
}

function ame_widget_defaults(string $kind): array
{
    if ($kind === 'countdown') {
        return [
            'id' => 0,
            'kind' => 'countdown',
            'is_enabled' => 1,
            'title' => 'বিশেষ অফার',
            'message' => 'শেষ হতে বাকি',
            'icon_key' => 'hourglass',
            'icon_color' => '#0B1E4D',
            'icon_bg' => '#FFD600',
            'text_color' => '#FFFFFF',
            'bg_color' => '#0B1E4D',
            'accent_color' => '#FFD600',
            'digit_color' => '#0B1E4D',
            'digit_bg' => '#FFFFFF',
            'label_color' => '#FFE082',
            'digit_font_size' => 14,
            'digit_font_weight' => '800',
            'digit_font_family' => '',
            'font_size' => 13,
            'font_weight' => '700',
            'speed_seconds' => 22,
            'direction' => 'left',
            'ends_at' => (new DateTime('now', new DateTimeZone('Asia/Dhaka')))->modify('+7 days')->format('Y-m-d 23:59:00'),
            'ended_message' => 'সময় শেষ হয়েছে',
            'hide_when_ended' => 0,
            'show_days' => 1,
            'show_hours' => 1,
            'show_minutes' => 1,
            'show_seconds' => 1,
            'box_style' => 'boxed',
            'sort_order' => 0,
        ];
    }
    return [
        'id' => 0,
        'kind' => 'ticker',
        'is_enabled' => 1,
        'title' => 'হেডলাইন',
        'message' => '',
        'icon_key' => 'megaphone',
        'icon_color' => '#FFFFFF',
        'icon_bg' => '#0B1E4D',
        'text_color' => '#1B5E20',
        'bg_color' => '#FFFFFF',
        'accent_color' => '#FFD600',
        'digit_color' => '#0B1E4D',
        'digit_bg' => '#FFFFFF',
        'label_color' => '#E2E8F0',
        'digit_font_size' => 14,
        'digit_font_weight' => '800',
        'digit_font_family' => '',
        'font_size' => 13,
        'font_weight' => '700',
        'speed_seconds' => 22,
        'direction' => 'left',
        'ends_at' => null,
        'ended_message' => '',
        'hide_when_ended' => 0,
        'show_days' => 1,
        'show_hours' => 1,
        'show_minutes' => 1,
        'show_seconds' => 1,
        'box_style' => 'boxed',
        'sort_order' => 0,
    ];
}

function ame_get_widgets(mysqli $db, string $kind, bool $enabled_only = false): array
{
    ensure_display_widgets_schema($db);
    $sql = "SELECT * FROM display_widgets WHERE kind = ?";
    if ($enabled_only) {
        $sql .= " AND is_enabled = 1";
    }
    $sql .= " ORDER BY sort_order ASC, id ASC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $kind);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows ?: [];
}

function ame_get_widget(mysqli $db, int $id, string $kind): ?array
{
    ensure_display_widgets_schema($db);
    $stmt = $db->prepare("SELECT * FROM display_widgets WHERE id = ? AND kind = ? LIMIT 1");
    $stmt->bind_param('is', $id, $kind);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ame_sanitize_widget_post(array $post, string $kind): array
{
    $d = ame_widget_defaults($kind);
    $keys = ame_icon_keys($kind);
    $icon = (string)($post['icon_key'] ?? $d['icon_key']);
    if (!in_array($icon, $keys, true)) {
        $icon = $d['icon_key'];
    }
    $weight = (string)($post['font_weight'] ?? '700');
    if (!in_array($weight, ['400', '600', '700', '800'], true)) {
        $weight = '700';
    }
    $dir = (string)($post['direction'] ?? 'left');
    if (!in_array($dir, ['left', 'right'], true)) {
        $dir = 'left';
    }
    $style = (string)($post['box_style'] ?? 'boxed');
    if (!in_array($style, ['boxed', 'plain'], true)) {
        $style = 'boxed';
    }
    $ends = trim((string)($post['ends_at'] ?? ''));
    $end_year = (int)($post['end_year'] ?? 0);
    $end_month = (int)($post['end_month'] ?? 0);
    $end_day = (int)($post['end_day'] ?? 0);
    $end_time = trim((string)($post['end_time'] ?? ''));
    if (
        $end_year >= 2000
        && $end_year <= 2100
        && checkdate($end_month, $end_day, $end_year)
    ) {
        if ($end_time === '' || !preg_match('/^\d{2}:\d{2}/', $end_time)) {
            $end_time = '23:59';
        }
        $ends = sprintf(
            '%04d-%02d-%02d %s:00',
            $end_year,
            $end_month,
            $end_day,
            substr($end_time, 0, 5)
        );
    } elseif ($ends !== '' && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $ends)) {
        $ends = str_replace('T', ' ', substr($ends, 0, 16)) . ':00';
    } elseif ($ends !== '' && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $ends)) {
        $ends = $d['ends_at'];
    }

    return [
        'is_enabled' => empty($post['is_enabled']) ? 0 : 1,
        'title' => mb_substr(trim((string)($post['title'] ?? '')), 0, 200),
        'message' => mb_substr(trim((string)($post['message'] ?? '')), 0, 2000),
        'icon_key' => $icon,
        'icon_color' => ame_hex_color($post['icon_color'] ?? '', $d['icon_color']),
        'icon_bg' => ame_hex_color($post['icon_bg'] ?? '', $d['icon_bg']),
        'text_color' => ame_hex_color($post['text_color'] ?? '', $d['text_color']),
        'bg_color' => ame_hex_color($post['bg_color'] ?? '', $d['bg_color']),
        'accent_color' => ame_hex_color($post['accent_color'] ?? '', $d['accent_color']),
        'digit_color' => ame_hex_color($post['digit_color'] ?? '', $d['digit_color']),
        'digit_bg' => ame_hex_color($post['digit_bg'] ?? '', $d['digit_bg']),
        'label_color' => ame_hex_color($post['label_color'] ?? '', $d['label_color']),
        'digit_font_size' => ame_sanitize_font_size($post['digit_font_size'] ?? $d['digit_font_size'], (int)$d['digit_font_size'], 11, 28),
        'digit_font_weight' => ame_sanitize_font_weight($post['digit_font_weight'] ?? '', (string)$d['digit_font_weight']),
        'digit_font_family' => ame_sanitize_font_family($post['digit_font_family'] ?? '', (string)$d['digit_font_family']),
        'font_size' => max(11, min(22, (int)($post['font_size'] ?? 13))),
        'font_weight' => $weight,
        'speed_seconds' => max(8, min(80, (int)($post['speed_seconds'] ?? 22))),
        'direction' => $dir,
        'ends_at' => $kind === 'countdown' ? $ends : null,
        'ended_message' => mb_substr(trim((string)($post['ended_message'] ?? $d['ended_message'])), 0, 255),
        'hide_when_ended' => empty($post['hide_when_ended']) ? 0 : 1,
        'show_days' => empty($post['show_days']) ? 0 : 1,
        'show_hours' => empty($post['show_hours']) ? 0 : 1,
        'show_minutes' => empty($post['show_minutes']) ? 0 : 1,
        'show_seconds' => empty($post['show_seconds']) ? 0 : 1,
        'box_style' => $style,
        'sort_order' => max(0, (int)($post['sort_order'] ?? 0)),
    ];
}

function ame_icon_picker_html(string $kind, string $selected): string
{
    $pack = $kind === 'countdown' ? ame_countdown_icon_pack() : ame_ticker_icon_pack();
    $html = '<div class="dw-icons">';
    foreach ($pack as $key => $meta) {
        $on = $key === $selected ? ' selected' : '';
        $html .= '<label class="dw-icon' . $on . '" title="' . esc_html($meta['name']) . '">';
        $html .= '<input type="radio" name="icon_key" value="' . esc_html($key) . '"' . ($key === $selected ? ' checked' : '') . '>';
        $html .= ame_icon_svg($kind, $key, 20);
        $html .= '<small>' . esc_html($meta['name']) . '</small>';
        $html .= '</label>';
    }
    $html .= '</div>';
    return $html;
}

function ame_save_widget(mysqli $db, string $kind, int $id, array $data): int
{
    $fields = 'is_enabled=?, title=?, message=?, icon_key=?, icon_color=?, icon_bg=?, text_color=?, bg_color=?, accent_color=?, digit_color=?, digit_bg=?, label_color=?, digit_font_size=?, digit_font_weight=?, digit_font_family=?, font_size=?, font_weight=?, speed_seconds=?, direction=?, ends_at=NULLIF(?,\'\'), ended_message=?, hide_when_ended=?, show_days=?, show_hours=?, show_minutes=?, show_seconds=?, box_style=?, sort_order=?';
    $types = 'isssssssssssissisisssiiiiisi';
    $ends_at = $data['ends_at'] ? (string)$data['ends_at'] : '';
    $vals = [
        $data['is_enabled'], $data['title'], $data['message'], $data['icon_key'],
        $data['icon_color'], $data['icon_bg'], $data['text_color'], $data['bg_color'], $data['accent_color'],
        $data['digit_color'], $data['digit_bg'], $data['label_color'],
        $data['digit_font_size'], $data['digit_font_weight'], $data['digit_font_family'],
        $data['font_size'], $data['font_weight'],
        $data['speed_seconds'], $data['direction'], $ends_at, $data['ended_message'],
        $data['hide_when_ended'], $data['show_days'], $data['show_hours'], $data['show_minutes'], $data['show_seconds'],
        $data['box_style'], $data['sort_order'],
    ];
    if (strlen($types) !== count($vals)) {
        throw new RuntimeException('display widget bind mismatch');
    }
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE display_widgets SET $fields WHERE id=? AND kind=?");
        if (!$stmt) {
            throw new RuntimeException('widget update prepare: ' . $db->error);
        }
        $types .= 'is';
        $vals[] = $id;
        $vals[] = $kind;
        $stmt->bind_param($types, ...$vals);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('widget update: ' . $err);
        }
        $stmt->close();
        return $id;
    }
    $stmt = $db->prepare("INSERT INTO display_widgets SET kind=?, $fields");
    if (!$stmt) {
        throw new RuntimeException('widget insert prepare: ' . $db->error);
    }
    $types = 's' . $types;
    array_unshift($vals, $kind);
    $stmt->bind_param($types, ...$vals);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('widget insert: ' . $err);
    }
    $new_id = (int)$db->insert_id;
    $stmt->close();
    if ($new_id < 1) {
        throw new RuntimeException('widget insert gave no id');
    }
    return $new_id;
}
