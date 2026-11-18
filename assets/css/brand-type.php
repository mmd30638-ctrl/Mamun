<?php
/**
 * Dynamic brand-type stylesheet. Public CSS — no login redirect.
 */
header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: public, max-age=30');
header('X-Content-Type-Options: nosniff');

$bt = dirname(__DIR__, 2) . '/includes/brand_type.php';
if (is_file($bt)) {
    require_once $bt;
    echo ame_brand_type_css();
    exit;
}
echo "/* brand_type.php missing */\n";
