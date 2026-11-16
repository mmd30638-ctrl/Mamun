<?php
/**
 * POS টার্মিনাল ও পিসি আইকনের মাঝের লেখা — অন্য কোনো সেটিংসের সাথে মিশে না।
 */

require_once __DIR__ . '/display_fonts.php';

function ame_pos_title_default()
{
    return 'সাইয়্যিদুনা হযরত সুলত্বানুন নাছীর আলাইহিস সালাম';
}

function ame_pos_title_defaults()
{
    return [
        'line_text' => ame_pos_title_default(),
        'text_color' => '#0D47A1',
        'font_size' => 13,
        'font_weight' => '800',
        'font_family' => '',
    ];
}

function ame_pos_title_ensure_columns($db)
{
    if (!($db instanceof mysqli)) {
        return;
    }
    $cols = [
        'text_color' => "VARCHAR(20) NOT NULL DEFAULT '#0D47A1'",
        'font_size' => 'INT NOT NULL DEFAULT 13',
        'font_weight' => "VARCHAR(10) NOT NULL DEFAULT '800'",
        'font_family' => "VARCHAR(120) NOT NULL DEFAULT ''",
    ];
    $have = [];
    $res = @$db->query('SHOW COLUMNS FROM pos_title_line');
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
        @$db->query("ALTER TABLE pos_title_line ADD COLUMN `{$safe}` {$ddl}");
    }
}

function ame_ensure_pos_title($db)
{
    static $done = false;
    if ($done) {
        return;
    }
    if (!($db instanceof mysqli)) {
        return;
    }
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('pos_title')) {
        $done = true;
        return;
    }
    $done = true;
    $db->query("CREATE TABLE IF NOT EXISTS pos_title_line (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        line_text TEXT NOT NULL,
        text_color VARCHAR(20) NOT NULL DEFAULT '#0D47A1',
        font_size INT NOT NULL DEFAULT 13,
        font_weight VARCHAR(10) NOT NULL DEFAULT '800',
        font_family VARCHAR(120) NOT NULL DEFAULT '',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    ame_pos_title_ensure_columns($db);
    $res = $db->query("SELECT id FROM pos_title_line WHERE id = 1");
    if ($res && $res->num_rows === 0) {
        $def = ame_pos_title_defaults();
        $stmt = $db->prepare("INSERT INTO pos_title_line (id, line_text, text_color, font_size, font_weight, font_family) VALUES (1, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param(
                'ssiss',
                $def['line_text'],
                $def['text_color'],
                $def['font_size'],
                $def['font_weight'],
                $def['font_family']
            );
            $stmt->execute();
            $stmt->close();
        }
    }
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('pos_title', true);
    }
}

function ame_pos_title_row($db = null)
{
    $def = ame_pos_title_defaults();
    $conn = $db instanceof mysqli ? $db : ($GLOBALS['db'] ?? null);
    if (!($conn instanceof mysqli)) {
        return $def;
    }
    try {
        ame_ensure_pos_title($conn);
        $res = $conn->query("SELECT line_text, text_color, font_size, font_weight, font_family FROM pos_title_line WHERE id = 1");
        $row = $res ? $res->fetch_assoc() : null;
        if (!$row) {
            return $def;
        }
        $text = trim((string)($row['line_text'] ?? ''));
        return [
            'line_text' => $text !== '' ? $text : $def['line_text'],
            'text_color' => ame_style_hex_color($row['text_color'] ?? '', $def['text_color']),
            'font_size' => ame_sanitize_font_size($row['font_size'] ?? $def['font_size'], $def['font_size'], 10, 24),
            'font_weight' => ame_sanitize_font_weight($row['font_weight'] ?? '', $def['font_weight']),
            'font_family' => ame_sanitize_font_family($row['font_family'] ?? '', $def['font_family']),
        ];
    } catch (Throwable $e) {
        return $def;
    }
}

function ame_pos_title_line($db = null)
{
    return ame_pos_title_row($db)['line_text'];
}

function ame_pos_title_style_attr(array $row): string
{
    $parts = [
        'color:' . ame_style_hex_color($row['text_color'] ?? '', '#0D47A1'),
        'font-size:' . ame_sanitize_font_size($row['font_size'] ?? 13, 13, 10, 24) . 'px',
        'font-weight:' . ame_sanitize_font_weight($row['font_weight'] ?? '', '800'),
    ];
    $family = ame_sanitize_font_family($row['font_family'] ?? '', '');
    if ($family !== '') {
        $parts[] = 'font-family:' . $family;
    }
    return implode(';', $parts);
}

function ame_sanitize_pos_title_post(array $post): array
{
    $def = ame_pos_title_defaults();
    $text = trim((string)($post['line_text'] ?? ''));
    if ($text === '') {
        $text = $def['line_text'];
    }
    if (function_exists('mb_substr')) {
        $text = mb_substr($text, 0, 300, 'UTF-8');
    } else {
        $text = substr($text, 0, 900);
    }
    return [
        'line_text' => $text,
        'text_color' => ame_style_hex_color($post['text_color'] ?? '', $def['text_color']),
        'font_size' => ame_sanitize_font_size($post['font_size'] ?? $def['font_size'], $def['font_size'], 10, 24),
        'font_weight' => ame_sanitize_font_weight($post['font_weight'] ?? '', $def['font_weight']),
        'font_family' => ame_sanitize_font_family($post['font_family'] ?? '', $def['font_family']),
    ];
}

function ame_save_pos_title($db, $data)
{
    if (!($db instanceof mysqli)) {
        return false;
    }
    ame_ensure_pos_title($db);
    if (is_string($data)) {
        $row = ame_pos_title_row($db);
        $row['line_text'] = trim($data);
        if ($row['line_text'] === '') {
            $row['line_text'] = ame_pos_title_default();
        }
        if (function_exists('mb_substr')) {
            $row['line_text'] = mb_substr($row['line_text'], 0, 300, 'UTF-8');
        } else {
            $row['line_text'] = substr($row['line_text'], 0, 900);
        }
        $data = $row;
    } elseif (!is_array($data)) {
        return false;
    } else {
        $data = ame_sanitize_pos_title_post($data);
    }
    $stmt = $db->prepare("INSERT INTO pos_title_line (id, line_text, text_color, font_size, font_weight, font_family)
        VALUES (1, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            line_text = VALUES(line_text),
            text_color = VALUES(text_color),
            font_size = VALUES(font_size),
            font_weight = VALUES(font_weight),
            font_family = VALUES(font_family)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param(
        'ssiss',
        $data['line_text'],
        $data['text_color'],
        $data['font_size'],
        $data['font_weight'],
        $data['font_family']
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function ame_can_edit_pos_title()
{
    return isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin';
}
