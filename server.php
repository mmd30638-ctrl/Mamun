<?php
/**
 * Front router for PHP's built-in server (php -S).
 * Mirrors the Hostinger .htaccess rules that Apache would enforce.
 */
$uri = urldecode((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
$doc = __DIR__;

if (
    preg_match('#^/(includes|partials|logs|sql|data|public_html|migrations|tools|verification|docs)(/|$)#i', $uri)
    || preg_match('#^/(install|install_wipe_data|import|check-db|bump_asset_version|cleanup_unused_files)\.php$#i', $uri)
    || preg_match('#^/modules/settings/download_wipe_sql\.php$#i', $uri)
    || preg_match('#^/uploads/(migrations|sql|scripts)(/|$)#i', $uri)
    || preg_match('#^/storage/shop_rent(/|$)#i', $uri)
    || preg_match('#^/modules/shop_rent/_(?:home_alert|nav)\.php$#i', $uri)
) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    return true;
}

if (preg_match('#\.(env|log|sql|md|bak|dump|zip)$#i', $uri) || preg_match('#(?:^|/)\.#', $uri)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    return true;
}

$full = $doc . $uri;
$realDoc = realpath($doc);

if ($uri !== '/' && file_exists($full)) {
    $real = realpath($full);
    if ($real === false || $realDoc === false || !str_starts_with($real, $realDoc)) {
        http_response_code(403);
        echo 'Forbidden';
        return true;
    }
    if (is_dir($real)) {
        foreach (['index.php', 'index.html'] as $idx) {
            $candidate = $real . DIRECTORY_SEPARATOR . $idx;
            if (is_file($candidate)) {
                require $candidate;
                return true;
            }
        }
        http_response_code(403);
        echo 'Forbidden';
        return true;
    }
    return false;
}

if ($uri === '/' || $uri === '') {
    require $doc . '/index.php';
    return true;
}

require $doc . '/router.php';
return true;
