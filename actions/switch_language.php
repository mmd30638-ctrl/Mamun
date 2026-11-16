<?php
/*
 * নিরাপদ বাংলা–ইংরেজি ভাষা পরিবর্তন
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/lang_helper.php';

$requested_language = strtolower(
    trim((string)($_GET['lang'] ?? ''))
);

$languages = supported_languages();

/* অনুমোদিত ভাষা হলেই পরিবর্তন হবে */
if (array_key_exists($requested_language, $languages)) {
    $_SESSION['app_lang'] = $requested_language;
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie('app_lang', $requested_language, [
        'expires' => time() + 86400 * 365,
        'path' => '/',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/*
 * শুধু অ্যাপের HTML/PHP পেজে ফেরত যাওয়া যাবে। পুরোনো বা ভুল
 * Referer (যেমন /sw.js) এ redirect করলে browser-এ source code খুলে যায়।
 */
$is_safe_app_page = static function (string $url): bool {
    if (
        $url === ''
        || $url[0] !== '/'
        || str_starts_with($url, '//')
        || preg_match('/[\r\n]/', $url)
    ) {
        return false;
    }

    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path)) {
        return false;
    }

    return $path === '/'
        || (
            str_ends_with(strtolower($path), '.php')
            && !str_starts_with($path, '/actions/')
        );
};

/* Header বর্তমান পেজটি স্পষ্টভাবে পাঠায়; সেটিই প্রথম পছন্দ। */
$redirect_url = '/index.php';
$requested_return = trim((string)($_GET['return'] ?? ''));
if ($is_safe_app_page($requested_return)) {
    $redirect_url = $requested_return;
} else {
    /* পুরোনো links-এর জন্য same-site Referer fallback রাখা হয়েছে। */
    $previous_url = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $previous_host = parse_url($previous_url, PHP_URL_HOST);
    $previous_path = parse_url($previous_url, PHP_URL_PATH);
    $previous_query = parse_url($previous_url, PHP_URL_QUERY);
    $current_host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));

    if (
        is_string($previous_host)
        && strcasecmp($previous_host, $current_host) === 0
        && is_string($previous_path)
    ) {
        $referer_return = $previous_path;
        if (is_string($previous_query) && $previous_query !== '') {
            $referer_return .= '?' . $previous_query;
        }
        if ($is_safe_app_page($referer_return)) {
            $redirect_url = $referer_return;
        }
    }
}

header('Location: ' . $redirect_url, true, 303);
exit();