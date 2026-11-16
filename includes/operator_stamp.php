<?php
/**
 * Print / share operator — user name + ID on memos, invoices, receipts, pages.
 */

if (!function_exists('ame_recalled_user_name') && is_file(__DIR__ . '/user_functions.php')) {
    require_once __DIR__ . '/user_functions.php';
}

function ame_operator_info(?mysqli $db = null, int $user_id = 0): array
{
    static $cache = [];
    if ($user_id <= 0) {
        $user_id = (int)($_SESSION['user_id'] ?? 0);
    }
    if ($user_id > 0 && isset($cache[$user_id])) {
        return $cache[$user_id];
    }
    $info = [
        'id' => $user_id,
        'name' => '',
        'code' => '',
        'employee_id' => 0,
    ];
    if ($user_id <= 0) {
        return $info;
    }
    if (!($db instanceof mysqli) && isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) {
        $db = $GLOBALS['db'];
    }
    if ($db instanceof mysqli) {
        $sql = "SELECT u.id, u.username, u.removed_username, u.employee_id,
                       e.name AS emp_name, e.employee_code
                FROM users u
                LEFT JOIN employees e ON e.id = u.employee_id
                WHERE u.id = ?
                LIMIT 1";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $emp = trim((string)($row['emp_name'] ?? ''));
                $kept = trim((string)($row['removed_username'] ?? ''));
                $user = trim((string)($row['username'] ?? ''));
                $info['name'] = $emp !== '' ? $emp : ($user !== '' ? $user : $kept);
                $info['code'] = trim((string)($row['employee_code'] ?? ''));
                $info['employee_id'] = (int)($row['employee_id'] ?? 0);
            }
        }
        if ($info['name'] === '' && function_exists('ame_recalled_user_name')) {
            $info['name'] = ame_recalled_user_name($db, $user_id);
        }
    }
    if ($info['name'] === '') {
        $info['name'] = trim((string)($_SESSION['username'] ?? ''));
    }
    if ($info['name'] === '' && $user_id > 0) {
        $info['name'] = '#' . $user_id;
    }
    $cache[$user_id] = $info;
    return $info;
}

function ame_operator_format(array $info): string
{
    $name = trim((string)($info['name'] ?? ''));
    $id = (int)($info['id'] ?? 0);
    $code = trim((string)($info['code'] ?? ''));
    $en = function_exists('current_lang') && current_lang() === 'en';
    $parts = [];
    if ($name !== '') {
        $parts[] = $name;
    }
    if ($id > 0) {
        $parts[] = ($en ? 'ID ' : 'আইডি ') . $id;
    }
    if ($code !== '') {
        $parts[] = $code;
    }
    return implode(' · ', $parts);
}

function ame_operator_stamp_text(?mysqli $db = null, int $created_by = 0): string
{
    if (!($db instanceof mysqli) && isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) {
        $db = $GLOBALS['db'];
    }
    $en = function_exists('current_lang') && current_lang() === 'en';
    $session_id = (int)($_SESSION['user_id'] ?? 0);
    $session = $session_id > 0 ? ame_operator_info($db, $session_id) : ['id' => 0, 'name' => '', 'code' => ''];
    $doc = $created_by > 0 ? ame_operator_info($db, $created_by) : ['id' => 0, 'name' => '', 'code' => ''];
    $lines = [];
    if ((int)$doc['id'] > 0 && (int)$doc['id'] !== (int)$session['id']) {
        $lines[] = ($en ? 'Sold / received: ' : 'বিক্রয় / গ্রহণ: ') . ame_operator_format($doc);
    }
    $who = (int)$session['id'] > 0 ? $session : $doc;
    if ((int)$who['id'] > 0) {
        $lines[] = ($en ? 'User: ' : 'ইউজার: ') . ame_operator_format($who);
    }
    return implode("\n", $lines);
}

function ame_operator_stamp_html(?mysqli $db = null, int $created_by = 0, array $opt = []): string
{
    $text = ame_operator_stamp_text($db, $created_by);
    if ($text === '') {
        return '';
    }
    $class = trim((string)($opt['class'] ?? 'ame-op-stamp'));
    if ($class === '') {
        $class = 'ame-op-stamp';
    }
    $lines = preg_split("/\n/", $text) ?: [$text];
    static $css_once = false;
    $html = '';
    if (!$css_once) {
        $css_once = true;
        $html .= '<style class="ame-op-stamp-css">.ame-op-stamp{margin:8px 0 0;font-size:12px;font-weight:800;color:#1e293b;line-height:1.35}.ame-op-stamp p{margin:0}</style>';
    }
    $html .= '<div class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">';
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }
        $html .= '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $html .= '</div>';
    return $html;
}
