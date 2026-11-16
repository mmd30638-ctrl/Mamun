<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
/**
 * প্রতিটা HTML পেজে রেসপন্সিভ CSS/JS ঢোকায় — হেডার/ফুটার না থাকলেও।
 */
function ame_responsive_inject($html)
{
    if (!is_string($html) || $html === '') {
        return $html;
    }
    $trim = ltrim($html);
    if ($trim === '') {
        return $html;
    }
    $first = $trim[0];
    if ($first === '{' || $first === '[') {
        return $html;
    }
    if (stripos($html, '</body>') === false && stripos($html, '</html>') === false) {
        return $html;
    }

    if (function_exists('ame_i18n_apply')) {
        $html = ame_i18n_apply($html);
    }

    $ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '5.20';
    $css = '<link rel="stylesheet" href="/assets/css/responsive-fix.css?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '">'
        . '<link rel="stylesheet" href="/assets/css/ame-android-universal.css?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '">'
        . '<link rel="stylesheet" href="/assets/css/ame-modules-universal-responsive.css?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '">'
        . '<link rel="stylesheet" href="/assets/css/ame-global-responsive-master.css?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '" id="ame-global-responsive-master">';
    $js = '<script src="/assets/js/responsive-fix.js?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '" defer></script>';
    $error_js = '<script src="/assets/js/error-boundary.js?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '" defer></script>';
    $premium_css = '<link rel="stylesheet" href="/assets/css/premium-saas.css?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '">';
    $premium_js = '<script src="/assets/js/premium-saas.710.js?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '" defer></script>';
    $vp = defined('AME_VIEWPORT_META')
        ? AME_VIEWPORT_META
        : '<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">';

    if (stripos($html, 'name="viewport"') === false && stripos($html, '</head>') !== false) {
        $html = preg_replace('/<\/head>/i', $vp . '</head>', $html, 1);
    }
    $desk = '<script src="/assets/js/ame-desktop-site.js?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '"></script>';
    $tierJs = '<script src="/assets/js/ame-viewport-tier.js?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '"></script>';
    if (stripos($html, 'ame-desktop-site.js') === false && stripos($html, 'amePhoneDesktopSite') === false) {
        if (preg_match('/<meta[^>]+name=["\']viewport["\'][^>]*>/i', $html)) {
            $html = preg_replace('/(<meta[^>]+name=["\']viewport["\'][^>]*>)/i', '$1' . $desk . $tierJs, $html, 1);
        } elseif (stripos($html, '</head>') !== false) {
            $html = preg_replace('/<\/head>/i', $desk . $tierJs . '</head>', $html, 1);
        }
    } elseif (stripos($html, 'ame-viewport-tier.js') === false && stripos($html, '</head>') !== false) {
        $html = preg_replace('/<\/head>/i', $tierJs . '</head>', $html, 1);
    }
    if (stripos($html, 'responsive-fix.css') === false) {
        if (stripos($html, '</head>') !== false) {
            $html = preg_replace('/<\/head>/i', $css . $premium_css . '</head>', $html, 1);
        } elseif (stripos($html, '</body>') !== false) {
            $html = preg_replace('/<\/body>/i', $css . $premium_css . '</body>', $html, 1);
        }
    } elseif (stripos($html, 'premium-saas.css') === false && stripos($html, '</head>') !== false) {
        $html = preg_replace('/<\/head>/i', $premium_css . '</head>', $html, 1);
    }
    // 'premium-saas.js' এবং 'premium-saas.710.js' — দুটো নামই বাইট-বাই-বাইট একই
    // ফাইল (নকল কনটেন্ট, শুধু নাম আলাদা ছিল); কিছু পেজ পুরনো নামে, কিছু পেজ
    // (customers/index.php, overlay_assets.php) নতুন সংস্করণ-নাম্বার যুক্ত নামে
    // লোড করে। তাই দুটো নামই এখানে চেক করা হচ্ছে, যাতে যে পেজ আগে থেকেই একটা
    // লোড করে ফেলেছে সেখানে আবার দ্বিতীয়বার (ডুপ্লিকেট) স্ক্রিপ্ট বসে না যায়।
    $premium_js_present = (stripos($html, 'premium-saas.js') !== false) || (stripos($html, 'premium-saas.710.js') !== false);
    if (stripos($html, 'responsive-fix.js') === false && stripos($html, '</body>') !== false) {
        $html = preg_replace('/<\/body>/i', $js . $premium_js . $error_js . '</body>', $html, 1);
    } elseif (!$premium_js_present && stripos($html, '</body>') !== false) {
        $html = preg_replace('/<\/body>/i', $premium_js . $error_js . '</body>', $html, 1);
    } elseif (stripos($html, 'error-boundary.js') === false && stripos($html, '</body>') !== false) {
        $html = preg_replace('/<\/body>/i', $error_js . '</body>', $html, 1);
    }
    if (function_exists('ame_i18n_assets') && stripos($html, 'ame-i18n.js') === false) {
        $i18n = ame_i18n_assets();
        if (stripos($html, '</head>') !== false) {
            $html = preg_replace('/<\/head>/i', $i18n . '</head>', $html, 1);
        } elseif (stripos($html, '</body>') !== false) {
            $html = preg_replace('/<\/body>/i', $i18n . '</body>', $html, 1);
        }
    }
    return $html;
}

function ame_responsive_boot()
{
    if (defined('AME_RESPONSIVE_BOOT_ON')) {
        return;
    }
    define('AME_RESPONSIVE_BOOT_ON', true);
    if (PHP_SAPI === 'cli') {
        return;
    }
    $page = basename((string)($_SERVER['PHP_SELF'] ?? ''));
    if (in_array($page, ['one_click.php'], true)) {
        return;
    }
    ob_start('ame_responsive_inject');
}
