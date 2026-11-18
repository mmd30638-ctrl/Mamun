<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function t(string $key): string
{
    $lang = $GLOBALS['vx_lang_pack'] ?? [];
    return (string) ($lang[$key] ?? $key);
}

function vx_lang(): string
{
    $lang = (string) ($_SESSION['vx_lang'] ?? $_COOKIE['vx_lang'] ?? 'bn');
    return $lang === 'en' ? 'en' : 'bn';
}

function vx_lang_boot(): void
{
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['bn', 'en'], true)) {
        $_SESSION['vx_lang'] = $_GET['lang'];
        setcookie('vx_lang', $_GET['lang'], ['expires' => time() + 86400 * 365, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        $q = $_GET;
        unset($q['lang']);
        header('Location: index.php' . ($q ? ('?' . http_build_query($q)) : ''));
        exit;
    }
    $code = vx_lang();
    $GLOBALS['vx_lang_pack'] = require VX_CORE . '/lang/' . $code . '.php';
}

function vx_flash(?string $msg = null, bool $bad = false): string
{
    if ($msg !== null) {
        $_SESSION['vx_flash'] = [$msg, $bad];
        return '';
    }
    if (empty($_SESSION['vx_flash'])) {
        return '';
    }
    [$text, $bad] = $_SESSION['vx_flash'];
    unset($_SESSION['vx_flash']);
    return '<p class="alert ' . ($bad ? 'bad' : 'ok') . '">' . vx_e((string) $text) . '</p>';
}

function vx_go(string $route, array $query = []): void
{
    $query['r'] = $route;
    header('Location: index.php?' . http_build_query($query));
    exit;
}

function vx_page(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

function vx_pager(int $page, int $total, int $per, string $route, array $keep = []): string
{
    $pages = max(1, (int) ceil($total / $per));
    if ($pages < 2) {
        return '';
    }
    $keep['r'] = $route;
    $html = '<nav class="pager">';
    if ($page > 1) {
        $keep['page'] = $page - 1;
        $html .= '<a href="index.php?' . vx_e(http_build_query($keep)) . '">' . vx_e(t('prev')) . '</a>';
    }
    $html .= '<span>' . $page . ' / ' . $pages . '</span>';
    if ($page < $pages) {
        $keep['page'] = $page + 1;
        $html .= '<a href="index.php?' . vx_e(http_build_query($keep)) . '">' . vx_e(t('next')) . '</a>';
    }
    return $html . '</nav>';
}

function vx_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . vx_e(vx_csrf()) . '">';
}

function vx_opts(string $table, string $label = 'name', string $where = 'deleted_at IS NULL'): string
{
    $sql = 'SELECT id, ' . $label . ' AS label FROM ' . vx_t($table) . ' WHERE ' . $where . ' ORDER BY id DESC LIMIT 300';
    $html = '';
    foreach (vx_all($sql) as $row) {
        $html .= '<option value="' . (int) $row['id'] . '">' . vx_e($row['label']) . '</option>';
    }
    return $html;
}

function vx_nav(): array
{
    $items = [
        ['dashboard', 'dashboard'],
        ['parties', 'parties'],
        ['products', 'products'],
        ['purchase', 'purchase'],
        ['sale', 'sale'],
        ['sale_return', 'sale_return'],
        ['purchase_return', 'purchase_return'],
        ['stock', 'stock'],
        ['alerts', 'alerts'],
        ['accounts', 'accounts'],
        ['expenses', 'expenses'],
        ['employees', 'employees'],
        ['reports', 'reports'],
        ['files', 'files'],
        ['settings', 'settings'],
    ];
    if (vx_feature('rooms')) {
        array_splice($items, 1, 0, [['rooms', 'rooms'], ['bookings', 'bookings'], ['housekeeping', 'housekeeping'], ['laundry', 'laundry'], ['restaurant', 'restaurant']]);
    }
    if (vx_feature('transfer')) {
        $items[] = ['transfers', 'transfers'];
    }
    if (vx_feature('income')) {
        $items[] = ['incomes', 'incomes'];
    }
    if (vx_feature('barcode')) {
        $items[] = ['barcode', 'barcode'];
    }
    if (vx_feature('prescription')) {
        $items[] = ['doctors', 'doctors'];
        $items[] = ['expired', 'expired'];
    }
    $items[] = ['users', 'users'];
    $items[] = ['audit', 'audit'];
    return $items;
}

function vx_layout(string $titleKey, string $body, bool $bare = false): void
{
    $app = vx_app();
    $name = $app['name'][vx_lang()] ?? $app['name']['bn'];
    $theme = $app['theme'] ?? '#123';
    $accent = $app['accent'] ?? '#c4a35a';
    $title = t($titleKey);
    if ($bare) {
        echo '<!doctype html><html lang="' . vx_e(vx_lang()) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . vx_e($title) . '</title><link rel="stylesheet" href="index.php?r=asset&f=app.css"></head><body class="bare" style="--brand:' . vx_e($theme) . ';--accent:' . vx_e($accent) . '"><main class="sheet">' . $body . '</main></body></html>';
        return;
    }
    $other = vx_lang() === 'bn' ? 'en' : 'bn';
    $q = $_GET;
    $q['lang'] = $other;
    $nav = '';
    foreach (vx_nav() as $item) {
        if (!vx_can($item[0]) && $item[0] !== 'dashboard') {
            continue;
        }
        if ($item[0] !== 'dashboard' && !vx_can($item[0])) {
            continue;
        }
        $nav .= '<a class="' . (($_GET['r'] ?? '') === $item[0] ? 'on' : '') . '" href="index.php?r=' . vx_e($item[0]) . '">' . vx_e(t($item[1])) . '</a>';
    }
    $user = vx_user();
    echo '<!doctype html><html lang="' . vx_e(vx_lang()) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . vx_e($name . ' · ' . $title) . '</title><link rel="stylesheet" href="index.php?r=asset&f=app.css"></head>';
    echo '<body style="--brand:' . vx_e($theme) . ';--accent:' . vx_e($accent) . '">';
    echo '<input id="navt" type="checkbox" class="navt">';
    echo '<header class="top"><label for="navt" class="burger" aria-label="menu">☰</label><strong>' . vx_e($name) . '</strong><span class="grow"></span>';
    echo '<a class="lang" href="index.php?' . vx_e(http_build_query($q)) . '">' . ($other === 'en' ? 'English' : 'বাংলা') . '</a>';
    if ($user) {
        echo '<span class="who">' . vx_e($user['name']) . '</span><a class="lang" href="index.php?r=logout">' . vx_e(t('logout')) . '</a>';
    }
    echo '</header><div class="shell"><aside class="side">' . $nav . '</aside><main class="main"><h1>' . vx_e($title) . '</h1>' . vx_flash() . $body . '</main></div></body></html>';
}

function vx_code39(string $text): string
{
    $map = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn', '4' => 'nnnwwnnnw',
        '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw', '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw', 'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn',
        'F' => 'nnwnwwnnn', 'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww', 'O' => 'wnnnwnnwn',
        'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw', 'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn',
        'Z' => 'nwwnwnnnn', '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '*' => 'nwnnwnwnn',
    ];
    $text = '*' . strtoupper(preg_replace('/[^0-9A-Z\-\. ]/', '', $text)) . '*';
    $bars = '';
    $x = 0;
    foreach (str_split($text) as $ch) {
        $pat = $map[$ch] ?? $map['-'];
        for ($i = 0; $i < 9; $i++) {
            $wide = $pat[$i] === 'w';
            $w = $wide ? 2.4 : 1;
            if ($i % 2 === 0) {
                $bars .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="42" fill="#111"/>';
            }
            $x += $w;
        }
        $x += 1;
    }
    return '<svg class="barcode" viewBox="0 0 ' . $x . ' 58" role="img"><rect width="100%" height="100%" fill="#fff"/>' . $bars . '<text x="' . ($x / 2) . '" y="56" text-anchor="middle" font-size="8" font-family="monospace">' . vx_e(trim($text, '*')) . '</text></svg>';
}
