<?php
/**
 * সিরিয়াল ২১১: মাস্টার ইঞ্জিন V10
 * কাজ: সাব-ফোল্ডারের ভেতর থেকে অটোমেটিক আপডেট লোড করা।
 */

$root_update_path = __DIR__ . '/updates/';
$css_hooks = []; $dash_hooks = []; $side_hooks = [];

// ১. হুক ফাংশনসমূহ (সবার আগে)
if (!function_exists('aponshop_register_css')) {
    function aponshop_register_css($f) { global $css_hooks; $css_hooks[] = $f; }
}
if (!function_exists('aponshop_dashboard_hooks_add')) {
    function aponshop_dashboard_hooks_add($f) { global $dash_hooks; $dash_hooks[] = $f; }
}

// ২. অটো-লোডার লজিক (সাব-ফোল্ডার স্ক্যানার)
// এটি প্রতিটি সাব-ফোল্ডারের (উদা: 02_customers) ভেতর ঢুকে .php ফাইলগুলো খুঁজে নেবে
//
// নিরাপত্তা ফিক্স: আগে যেকোনো .php ফাইল নাম যাচাই ছাড়াই স্বয়ংক্রিয়ভাবে
// চলে যেত। এখন শুধু নিরাপদ নামের ফাইল (a-z, 0-9, _, -) চলবে এবং
// প্রতিটা লোড হওয়া ফাইল লগে রেকর্ড হবে (audit trail)।
$safe_filename_pattern = '/^[a-zA-Z0-9_\-]+\.php$/';

$folders = glob($root_update_path . '*', GLOB_ONLYDIR);
if ($folders) {
    foreach ($folders as $folder) {
        $files = glob($folder . "/*.php");
        if ($files) {
            sort($files);
            foreach ($files as $file) {
                $basename = basename($file);

                if (!preg_match($safe_filename_pattern, $basename)) {
                    error_log('engine.php: অনিরাপদ ফাইলনেম বাদ দেওয়া হলো -> ' . $file);
                    continue;
                }

                error_log('engine.php: auto-loaded hook file -> ' . $file);
                include_once $file;
            }
        }
    }
}

// ৩. ডিফেন্সিভ হুক এক্সিকিউটর
if (!function_exists('aponshop_css_hooks')) {
    function aponshop_css_hooks() {
        global $css_hooks;
        if(!empty($css_hooks)) foreach($css_hooks as $f) if(function_exists($f)) $f();
    }
}
if (!function_exists('aponshop_dashboard_hooks')) {
    function aponshop_dashboard_hooks() {
        global $dash_hooks;
        if(!empty($dash_hooks)) foreach($dash_hooks as $f) if(function_exists($f)) $f();
    }
}
?>