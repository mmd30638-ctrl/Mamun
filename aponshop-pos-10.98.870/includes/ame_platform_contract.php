<?php
/**
 * APONSHOP প্ল্যাটফর্ম চুক্তি — প্রতিটি আপডেটের জন্য বাধ্যতামূলক মানদণ্ড।
 *
 * টার্গেট:
 * · ডেস্কটপ (Windows/Linux/macOS ব্রাউজার)
 * · Android Chrome (সাধারণ মোবাইল ভিউ)
 * · Android Chrome «ডেস্কটপ সাইট» (চওড়া ভিউ + টাচ)
 *
 * INVARIANT (১০০% / আন্তর্জাতিক):
 * ১) নতুন UI/CSS যোগ করলে তিনটি মোডেই স্মোক টেস্ট।
 * ২) টাচ টার্গেট ≥ ৪৪px, রঙ + লেবেল (WCAG mindset), ট্যাবুলার অঙ্ক।
 * ৩) Party/business অ্যামাউন্ট — শুধু লেনদেন পথে লেখা (Shield)।
 * ৪) মডিউল-নির্দিষ্ট CSS এর পাশাপাশি এই চুক্তি CSS সব পেজে লোড থাকে।
 *
 * @version 1.0.0
 */
if (!defined('AME_PLATFORM_CONTRACT_VERSION')) {
    define('AME_PLATFORM_CONTRACT_VERSION', '1.0.0');
}
if (!defined('AME_QUALITY_STANDARD')) {
    define('AME_QUALITY_STANDARD', 'intl-100');
}

if (!function_exists('ame_platform_contract_boot')) {
    function ame_platform_contract_boot(): void
    {
        if (!defined('AME_PLATFORM_CONTRACT_BOOT')) {
            define('AME_PLATFORM_CONTRACT_BOOT', true);
        }
    }
}

if (!function_exists('ame_platform_contract_print_assets')) {
    function ame_platform_contract_print_assets(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : AME_PLATFORM_CONTRACT_VERSION;
        $v = htmlspecialchars((string)$ver, ENT_QUOTES, 'UTF-8');
        echo '<link rel="stylesheet" href="/assets/css/ame-platform-contract.css?v=' . $v . '" id="amePlatformContractCss">' . "\n";
        echo '<script defer src="/assets/js/ame-platform-contract.js?v=' . $v . '" id="amePlatformContractJs"></script>' . "\n";
    }
}

if (!function_exists('ame_platform_touch_desktop_media')) {
    /** @return string CSS media query for mobile Chrome desktop mode */
    function ame_platform_touch_desktop_media(): string
    {
        return '(max-width: 1100px) and (pointer: coarse)';
    }
}

if (!function_exists('ame_platform_update_checklist')) {
    /**
     * Easy Update / PR চেকলিস্ট (মানুষ + এজেন্ট)।
     *
     * @return list<string>
     */
    function ame_platform_update_checklist(): array
    {
        return [
            'ডেস্কটপ ব্রাউজার — লেআউট ও রঙ',
            'Android Chrome মোবাইল ভিউ',
            'Android Chrome ডেস্কটপ সাইট (টাচ)',
            'party অ্যামাউন্ট শুধু ক্রয়/পেমেন্ট/অ্যাডমিন',
            'AME_ASSET_VERSION বাড়ানো + DB restore অচেক',
        ];
    }
}