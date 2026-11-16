<?php
/**
 * সাইডবার মেইন আইকন — প্রতিটা মডিউলের নিজস্ব রঙ সহ "চিপ" আইকন (হালকা রঙের
 * গোলাকার বক্সের ভেতরে সেই একই রঙের লাইন-আইকন)। প্রতিটা মডিউল আলাদা রঙে
 * আলাদা করে চেনা যায় — আগের মতো সব একরঙা/ধূসর নয়।
 */

/** প্রতিটা মডিউল/আইকনের নিজস্ব ব্র্যান্ড রঙ (hex)। */
function ame_sb_color($name)
{
    $navy = '#003F8F';
    $green = '#04933A';
    $red = '#DC2626';
    $colors = [
        'home' => $navy,
        'pulse' => $navy,
        'agents' => $navy,
        'smart' => $navy,
        'sales' => $navy,
        'sale_return' => $red,
        'purchase' => $navy,
        'purchase_return' => $red,
        'inventory' => $navy,
        'workshop' => $navy,
        'customers' => $navy,
        'suppliers' => $navy,
        'transactions' => $navy,
        'accounts' => $navy,
        'hrm' => $green,
        'warranty' => $green,
        'loans' => $navy,
        'rental' => $navy,
        'shop_rent' => $green,
        'reports' => $navy,
        'family' => $navy,
        'others' => $navy,
        'ticker' => $navy,
        'countdown' => $navy,
        'appearance' => $navy,
        'settings' => $navy,
        'expense' => $red,
        'atlas' => $navy,
        'logout' => $red,
        'back' => $navy,
        'fullscreen' => $navy,
        'pay' => $green,
        'bell' => $red,
        'calc' => $navy,
        'lang' => $navy,
        'net' => $green,
        'stores' => $navy,
    ];
    return $colors[$name] ?? $navy;
}

/** hex রঙ থেকে হালকা (tint) ব্যাকগ্রাউন্ড rgba বানায় — চিপের ভেতরের হালকা রঙ। */
function ame_sb_tint($hex, $alpha = 0.14)
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) {
        return 'rgba(100,116,139,' . $alpha . ')';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $alpha . ')';
}

/**
 * সাবমেনু আইটেমের জন্য নিজস্ব ছোট আইকন — লিংকের নাম/কি (key) দেখে কাজের ধরন
 * বুঝে উপযুক্ত আইকন বেছে দেয় (যোগ, তালিকা, রিপোর্ট, প্রিন্ট, সেটিংস...)।
 * এভাবে প্রতিটা ফিচারের নিজস্ব চেহারা থাকে, শুধু একটা রঙিন বিন্দু নয়।
 */
function ame_sb_feature_icon($hint = '')
{
    $h = mb_strtolower((string) $hint, 'UTF-8');
    $map = [
        ['dash(board)?|overview|home|main', '<rect x="4" y="4" width="7" height="7" rx="1.6"/><rect x="13" y="4" width="7" height="7" rx="1.6"/><rect x="4" y="13" width="7" height="7" rx="1.6"/><rect x="13" y="13" width="7" height="7" rx="1.6"/>'],
        ['assistant|smart|pulse|ai\b|magic', '<path d="M12 4l1.6 4.4L18 10l-4.4 1.6L12 16l-1.6-4.4L6 10l4.4-1.6L12 4Z"/>'],
        ['add|new|create|তৈরি|যোগ|নতুন', '<circle cx="12" cy="12" r="8"/><path d="M12 8.5v7M8.5 12h7"/>'],
        ['return|refund|reverse|ফেরত', '<path d="M4 8h9.5a5 5 0 0 1 0 10H10"/><path d="M7 4.5 4 8l3 3.5"/>'],
        ['print', '<rect x="6" y="9" width="12" height="7" rx="1.2"/><path d="M8 9V5h8v4M8 16v3h8v-3"/>'],
        ['search|find|খোঁজ', '<circle cx="10.5" cy="10.5" r="5.5"/><path d="M18.5 18.5 15 15"/>'],
        ['due|remind|alert|notif|pending|বকেয়া|রিমাইন্ড', '<path d="M6.6 9.2a5.4 5.4 0 0 1 10.8 0c0 3.2 1.2 4.4 1.2 4.4H5.4s1.2-1.2 1.2-4.4Z"/><path d="M10 18.4a2 2 0 0 0 4 0"/>'],
        ['pay|payment|receive|due_in|মাধ্যম', '<rect x="3.4" y="6.2" width="17.2" height="11.6" rx="1.6"/><path d="M3.4 10h17.2"/>'],
        ['transfer|exchange|swap|মিল', '<path d="M5 8h11l-2.5-2.5M19 16H8l2.5 2.5"/>'],
        ['setting|rule|config|guard|money_guard|সেটিং|রুল', '<circle cx="12" cy="12" r="2.6"/><path d="M12 4.4v2M12 17.6v2M4.4 12h2M17.6 12h2M6.6 6.6l1.4 1.4M16 16l1.4 1.4M17.4 6.6 16 8M8 16l-1.4 1.4"/>'],
        ['ledger|statement|account|book|খাতা', '<path d="M4.5 5.6c2.4-1 5-1 7.5.4 2.5-1.4 5.1-1.4 7.5-.4v13c-2.4-1-5-1-7.5.4-2.5-1.4-5.1-1.4-7.5-.4V5.6Z"/><path d="M12 6v13"/>'],
        ['report|summary|analysis|analytics|profit|finance|রিপোর্ট|সারাংশ', '<path d="M5 19V10M10.3 19V5M15.6 19v-8M20.9 19V13"/>'],
        ['calendar|date|daily|schedule|attendance|দৈনিক|তারিখ', '<rect x="4.2" y="5.8" width="15.6" height="13.4" rx="1.6"/><path d="M4.2 9.8h15.6M8 4.2v3M16 4.2v3"/>'],
        ['category|tag|brand|ট্যাগ', '<path d="M4.6 12.4 12 5h6.4v6.4L11 18.8a1.4 1.4 0 0 1-2 0l-4.4-4.4a1.4 1.4 0 0 1 0-2Z"/><circle cx="15.5" cy="8.5" r="1.1" fill="currentColor" stroke="none"/>'],
        ['delete|remove|damage|loss|মুছ', '<path d="M5.5 7.4h13M9.5 7.4V5.6h5v1.8M7.2 7.4l.7 11a1.6 1.6 0 0 0 1.6 1.5h5c.85 0 1.55-.65 1.6-1.5l.7-11"/>'],
        ['edit|update|সম্পাদনা', '<path d="M6 18.4 6.4 15.6 15.6 6.4a1.4 1.4 0 0 1 2 0l0 0a1.4 1.4 0 0 1 0 2L8.4 18l-2.8.8Z"/>'],
        ['family|partner|team|পার্টনার|পরিবার', '<circle cx="8.4" cy="9" r="2.4"/><circle cx="15.6" cy="9" r="2.4"/><path d="M3.6 19c.6-2.4 2.4-3.6 4.8-3.6s4.2 1.2 4.8 3.6M11 19c.5-2.1 2.1-3.3 4.4-3.3s3.9 1.2 4.4 3.3"/>'],
        ['customer|employee|staff|user|worker|কর্মী|কাস্টমার', '<circle cx="12" cy="9" r="3"/><path d="M6 19c.9-3 3-4.6 6-4.6s5.1 1.6 6 4.6"/>'],
        ['earning|income|salary|budget|money|cash|expense|আয়|বেতন', '<circle cx="12" cy="12" r="7.6"/><path d="M9.6 15.4c0 1.1.9 1.7 2.1 1.7s2.3-.5 2.3-1.6c0-1.2-1.1-1.5-2.3-1.8-1.2-.3-2.2-.7-2.2-1.8 0-1 1-1.6 2.2-1.6s2 .5 2.1 1.5"/><path d="M12 7.4v1.2M12 15.4v1.2"/>'],
        ['stock|inventory|item|product|warehouse|valuation', '<path d="M4.8 8.2 12 4.8l7.2 3.4v8.4L12 19.8 4.8 16.6V8.2Z"/><path d="M12 9.2v10.4M4.8 8.2 12 11.4 19.2 8.2"/>'],
        ['purchase|buy|vendor|supplier|ক্রয়', '<path d="M3.5 7h11.5l1.4 7H7.2L5.6 7.8H3.5"/><path d="M8 17.5a1 1 0 1 0 0 .1M16.2 17.5a1 1 0 1 0 0 .1"/>'],
        ['sale|pos|invoice|bill|receipt|বিক্রয়', '<path d="M6.5 4v16l2-1.3 2 1.3 2-1.3 2 1.3 2-1.3V4Z"/><path d="M9 8h6M9 11.4h6M9 14.8h4"/>'],
        ['care|protect|backup|security|শিল্ড|রক্ষা', '<path d="M12 4 5 6.6v5.2c0 4 3 6.7 7 8.2 4-1.5 7-4.2 7-8.2V6.6L12 4Z"/><path d="M9.3 12l2 2 3.4-3.6"/>'],
        ['warranty|quote|machine|serial|need|wrench|repair', '<path d="M14.8 6.4a3.6 3.6 0 0 0-5.2 4.9L5 16v3h3l4.6-4.6a3.6 3.6 0 0 0 5-5.1L15.2 12l-3-3 2.6-2.6Z"/>'],
        ['bank|net|import|export|db|database|ডাটাবেজ|ইনপুট', '<ellipse cx="12" cy="6.4" rx="7.2" ry="2.4"/><path d="M4.8 6.4V17.6c0 1.3 3.2 2.4 7.2 2.4s7.2-1.1 7.2-2.4V6.4"/><path d="M4.8 12c0 1.3 3.2 2.4 7.2 2.4s7.2-1.1 7.2-2.4"/>'],
        ['lang|ticker|translate|globe', '<circle cx="12" cy="12" r="8"/><path d="M4.4 12h15.2M12 4.2v15.6M8.2 8.2c1.2 1.6 2.5 2.4 3.8 2.4s2.6-.8 3.8-2.4M8.2 15.8c1.2-1.6 2.5-2.4 3.8-2.4s2.6.8 3.8 2.4"/>'],
        ['clos(e|ing)|lock|password', '<rect x="5.6" y="10.4" width="12.8" height="9" rx="1.8"/><path d="M8.4 10.4V7.6a3.6 3.6 0 0 1 7.2 0v2.8"/>'],
        ['ageing|aging|hourglass', '<path d="M6.4 4.6h11.2M6.4 19.4h11.2M7.4 4.6c0 3.6 3 5 4.6 6.4 1.6-1.4 4.6-2.8 4.6-6.4M7.4 19.4c0-3.6 3-5 4.6-6.4 1.6 1.4 4.6 2.8 4.6 6.4"/>'],
        ['vat|tax|capital', '<circle cx="8.4" cy="8.4" r="2.4"/><circle cx="15.6" cy="15.6" r="2.4"/><path d="M6.4 17.6 17.6 6.4"/>'],
        ['dept|department|building', '<rect x="5.6" y="4.2" width="12.8" height="15.6" rx="1.4"/><path d="M8.6 7.6h1.4M14 7.6h1.4M8.6 11h1.4M14 11h1.4M8.6 14.4h1.4M14 14.4h1.4"/>'],
['att|leave', '<rect x="4.2" y="5.8" width="15.6" height="13.4" rx="1.6"/><path d="M4.2 9.8h15.6M8 4.2v3M16 4.2v3"/><path d="M9.2 14.6l1.9 1.9 3.7-3.9"/>'],
        ['hrm_my|_my$', '<circle cx="12" cy="8.4" r="3.4"/><path d="M5.4 19.4c.9-3.4 3.4-5.2 6.6-5.2s5.7 1.8 6.6 5.2"/>'],
        ['share', '<circle cx="17" cy="6" r="2"/><circle cx="7" cy="12" r="2"/><circle cx="17" cy="18" r="2"/><path d="M8.7 10.9 15.3 7.1M8.7 13.1l6.6 3.8"/>'],
        ['loan|approve|apps', '<circle cx="12" cy="12" r="7.6"/><path d="M9.6 15.4c0 1.1.9 1.7 2.1 1.7s2.3-.5 2.3-1.6c0-1.2-1.1-1.5-2.3-1.8-1.2-.3-2.2-.7-2.2-1.8 0-1 1-1.6 2.2-1.6s2 .5 2.1 1.5"/><path d="M12 7.4v1.2M12 15.4v1.2"/>'],
        ['contact|phone', '<path d="M6 4.4h3.2l1.2 3.6-2 1.6a11 11 0 0 0 5 5l1.6-2 3.6 1.2v3.2c0 1-.9 1.8-1.9 1.6-6.4-1.1-11.2-5.9-12.3-12.3-.2-1 .6-1.9 1.6-1.9Z"/>'],
        ['icon_pack|icon\b|pack|palette', '<circle cx="12" cy="12" r="8"/><circle cx="9" cy="10" r="1.1" fill="currentColor" stroke="none"/><circle cx="14.5" cy="9.2" r="1.1" fill="currentColor" stroke="none"/><circle cx="15.5" cy="14" r="1.1" fill="currentColor" stroke="none"/><circle cx="9.5" cy="15" r="1.1" fill="currentColor" stroke="none"/>'],
        ['area|location|map|owner', '<path d="M12 20.4s6.4-5.8 6.4-11a6.4 6.4 0 1 0-12.8 0c0 5.2 6.4 11 6.4 11Z"/><circle cx="12" cy="9.4" r="2.2"/>'],
        ['group|status|state', '<circle cx="9" cy="8.6" r="2.6"/><circle cx="16.2" cy="10" r="2"/><path d="M4.6 18.4c.6-2.6 2.4-4 4.4-4s3.8 1.4 4.4 4M14.4 18.4c.4-2 1.7-3.2 3.4-3.2s3 1.2 3.4 3.2"/>'],
        ['battery', '<rect x="3.6" y="8.4" width="15.6" height="7.2" rx="1.6"/><path d="M19.2 10.4h1.6v3.2h-1.6"/><path d="M6 10.4h3v3.2H6z" fill="currentColor" stroke="none"/>'],
        ['barcode', '<path d="M4.4 5.4v13.2M7.6 5.4v13.2M9.6 5.4v13.2M12.4 5.4v13.2M14 5.4v13.2M16.8 5.4v13.2M19.6 5.4v13.2"/>'],
        ['withdraw', '<path d="M4 8h9.5a5 5 0 0 1 0 10H10"/><path d="M7 4.5 4 8l3 3.5"/><circle cx="17" cy="6" r="2.4"/>'],
        ['info|about|shop_info', '<circle cx="12" cy="12" r="8"/><path d="M12 11v5.2"/><circle cx="12" cy="8" r="1" fill="currentColor" stroke="none"/>'],
        ['store|stores', '<path d="M4.6 9.2 12 4.8l7.4 4.4V19H4.6V9.2Z"/><path d="M9.2 19v-6.2h5.6V19"/><path d="M4.6 9.2h14.8"/>'],
        ['countdown|timer', '<circle cx="12" cy="13" r="7.4"/><path d="M12 13V9.4M9.4 3.6h5.2"/>'],
        ['trust|provenance|verify', '<path d="M12 4 5 6.6v5.2c0 4 3 6.7 7 8.2 4-1.5 7-4.2 7-8.2V6.6L12 4Z"/><path d="M9.3 12l2 2 3.4-3.6"/>'],
        ['sms|message', '<path d="M4.6 6h14.8v10H9.4l-3.4 3v-3H4.6Z"/><path d="M8 9.6h8M8 12.8h5"/>'],
        ['activity|audit', '<path d="M4.4 12h4l1.6-4.4 3 8.8 1.6-4.4h4.6"/>'],
        ['health', '<path d="M12 19.4S4.6 15 4.6 9.6A3.8 3.8 0 0 1 12 8a3.8 3.8 0 0 1 7.4 1.6c0 5.4-7.4 9.8-7.4 9.8Z"/><path d="M9.2 10.8h2l1-2 1.6 4 1-2h1.6"/>'],
        ['tool', '<path d="M14.8 6.4a3.6 3.6 0 0 0-5.2 4.9L5 16v3h3l4.6-4.6a3.6 3.6 0 0 0 5-5.1L15.2 12l-3-3 2.6-2.6Z"/>'],
        ['profile|personal', '<circle cx="12" cy="8.4" r="3.4"/><path d="M5.4 19.4c.9-3.4 3.4-5.2 6.6-5.2s5.7 1.8 6.6 5.2"/>'],
        ['all|list|history|log|type|general|register', '<path d="M8 6h11M8 12h11M8 18h11"/><circle cx="4.2" cy="6" r="1" fill="currentColor" stroke="none"/><circle cx="4.2" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="4.2" cy="18" r="1" fill="currentColor" stroke="none"/>'],
    ];
    foreach ($map as [$pattern, $svg]) {
        if (preg_match('/' . $pattern . '/u', $h)) {
            return $svg;
        }
    }
    return '<path d="M8 6h11M8 12h11M8 18h9"/>';
}

/**
 * সিস্টেম সেটিংসের প্রতিটা আইটেম — আলাদা আইকন + আলাদা রঙ।
 * @return array<string, array{color:string,svg:string}>
 */
function ame_sb_settings_pack(): array
{
    $navy = '#003F8F';
    $green = '#04933A';
    $red = '#DC2626';
    $pack = [
        'atlas' => ['svg' => '<rect x="4.4" y="5.2" width="6.4" height="13.6" rx="1.2"/><rect x="12.2" y="5.2" width="7.4" height="8.2" rx="1.2"/><path d="M6.2 8.4h2.6M6.2 11h2.6M6.2 13.6h2.2M14 8.4h3.2M14 10.8h2.4"/>'],
        'theme' => ['svg' => '<circle cx="12" cy="12" r="8"/><circle cx="9" cy="10" r="1.2" fill="currentColor" stroke="none"/><circle cx="14.6" cy="9.4" r="1.2" fill="currentColor" stroke="none"/><circle cx="15.2" cy="14.2" r="1.2" fill="currentColor" stroke="none"/><circle cx="9.6" cy="15" r="1.2" fill="currentColor" stroke="none"/>'],
        'shop_info' => ['svg' => '<path d="M4.6 9.2 12 4.8l7.4 4.4V19H4.6V9.2Z"/><path d="M9.2 19v-6.2h5.6V19"/><path d="M4.6 9.2h14.8"/>'],
        'pos_title' => ['svg' => '<path d="M7 4v16l2-1.2 2 1.2 2-1.2 2 1.2 2-1.2V4Z"/><path d="M9.2 8.2h5.6M9.2 11.4h5.6M9.2 14.6h3.6"/>'],
        'stores' => ['svg' => '<path d="M3.6 19V10.2L8 7.4l4 2.4 4-2.4 4.4 2.8V19"/><path d="M8 19v-5.2h3.6V19M14.8 19v-4h3.2"/>'],
        'ticker' => ['svg' => '<path d="M4.6 9.4v5.2l5.2-1.4 2.2.8H19c.8 0 1.4-.6 1.4-1.4v-2c0-.8-.6-1.4-1.4-1.4h-7l-2.2.8Z"/>'],
        'countdown' => ['svg' => '<circle cx="12" cy="13.2" r="6.8"/><path d="M12 13.2V9.6M9.2 3.8h5.6"/>'],
        'banners' => ['svg' => '<rect x="3.6" y="6.2" width="16.8" height="11.6" rx="1.8"/><path d="M7 14.6 10.2 11l3 3 2-1.8 3.2 3.4"/><circle cx="8.4" cy="9.4" r="1.2"/>'],
        'trust' => ['svg' => '<path d="M12 4 5 6.6v5.2c0 4 3 6.7 7 8.2 4-1.5 7-4.2 7-8.2V6.6L12 4Z"/><path d="M9.2 12.2 11.2 14.2 15.2 10"/>'],
        'provenance' => ['svg' => '<circle cx="12" cy="12" r="7.6"/><circle cx="12" cy="12" r="2.2"/><path d="M12 4.4v2.4M12 17.2v2.4M4.4 12h2.4M17.2 12h2.4"/>'],
        'invoice' => ['svg' => '<path d="M7 4.4h7.2L18.4 8.6V19.6H7Z"/><path d="M14.2 4.4v4.2h4.2M9.2 12h5.8M9.2 15h4"/>'],
        'user_list' => ['svg' => '<circle cx="8.2" cy="9" r="2.4"/><circle cx="15.6" cy="9" r="2.4"/><path d="M3.8 19c.6-2.4 2.4-3.6 4.4-3.6s3.8 1.2 4.4 3.6M12.6 19c.5-2 2-3.2 4-3.2s3.5 1.2 4 3.2"/>'],
        'user_new' => ['svg' => '<circle cx="10" cy="8.6" r="3"/><path d="M4.6 19c.8-3 3-4.6 5.4-4.6s4.6 1.6 5.4 4.6M16.4 8.2v5.2M13.8 10.8h5.2"/>'],
        'user_perm' => ['svg' => '<circle cx="8.4" cy="10" r="2.8"/><path d="M4.2 18.6c.7-2.4 2.4-3.6 4.2-3.6s3.5 1.2 4.2 3.6"/><rect x="13.2" y="11.2" width="6.6" height="7.2" rx="1.4"/><path d="M15 11.2V9.6a1.6 1.6 0 0 1 3.2 0v1.6"/>'],
        'sms_setup' => ['svg' => '<path d="M4.4 6.2h15.2v10.2H9.2L6 19.2v-2.8H4.4Z"/><path d="M8 9.6h8M8 12.8h5.2"/>'],
        'sms_log' => ['svg' => '<path d="M5 5.4h12.4v7.4H9.2L7 14.8v-2H5Z"/><path d="M8.4 15.6h10.2v4.6H16l-1.6 1.6v-1.6H8.4Z"/>'],
        'activity' => ['svg' => '<path d="M3.8 12.2h3.6l1.8-5.2 3.2 10.4 2-5.2H20"/>'],
        'audit' => ['svg' => '<rect x="6.2" y="3.8" width="11.6" height="16.4" rx="1.6"/><path d="M9 8.4h6M9 11.6h6M9 14.8h3.6"/>'],
        'project_care' => ['svg' => '<path d="M12 20.2c4.6-2 7.2-4.8 7.2-9.2V6.4L12 4 4.8 6.4v4.6c0 4.4 2.6 7.2 7.2 9.2Z"/>'],
        'money_guard' => ['svg' => '<circle cx="12" cy="12" r="7.6"/><path d="M12 7.4v1.4M12 15.2v1.4M9.4 15c0 1.1 1 1.8 2.6 1.8s2.6-.6 2.6-1.7c0-1.2-1.2-1.5-2.6-1.8s-2.6-.7-2.6-1.8 1-1.6 2.6-1.6 2.4.5 2.6 1.5"/>'],
        'health' => ['svg' => '<path d="M12 19.2S4.8 14.8 4.8 9.8A3.6 3.6 0 0 1 12 8.4a3.6 3.6 0 0 1 7.2 1.4c0 5-7.2 9.4-7.2 9.4Z"/><path d="M8.6 11h2l1.2-2.4 1.8 4.4 1-2h1.8"/>'],
        'backup' => ['svg' => '<path d="M8.2 16.4A5.2 5.2 0 0 1 12 7.2a5 5 0 0 1 4.8 3.4A3.8 3.8 0 0 1 20 14.2c0 2.2-1.8 3.8-4 3.8H8.4"/><path d="M12 11.2v6.2M9.6 14.8 12 17.4 14.4 14.8"/>'],
        'lang' => ['svg' => '<circle cx="12" cy="12" r="8"/><path d="M4.4 12h15.2M12 4.2v15.6M8.2 8.2c1.2 1.6 2.5 2.4 3.8 2.4s2.6-.8 3.8-2.4M8.2 15.8c1.2-1.6 2.5-2.4 3.8-2.4s2.6.8 3.8 2.4"/>'],
        'db_import' => ['svg' => '<ellipse cx="12" cy="6.6" rx="7" ry="2.4"/><path d="M5 6.6v10.6c0 1.3 3.1 2.4 7 2.4s7-1.1 7-2.4V6.6"/><path d="M5 12c0 1.3 3.1 2.4 7 2.4s7-1.1 7-2.4"/>'],
        'tools' => ['svg' => '<path d="M14.8 6.2a3.6 3.6 0 0 0-5.2 5L5 16.8V20h3.2l4.6-4.6a3.6 3.6 0 0 0 5-5.2L15.2 12l-3-3 2.6-2.8Z"/>'],
        'password' => ['svg' => '<rect x="5.4" y="10.6" width="13.2" height="8.8" rx="1.8"/><path d="M8.4 10.6V7.8a3.6 3.6 0 0 1 7.2 0v2.8"/>'],
        'profile' => ['svg' => '<circle cx="12" cy="8.2" r="3.4"/><path d="M5.4 19.4c.9-3.4 3.4-5.2 6.6-5.2s5.7 1.8 6.6 5.2"/>'],
        'about' => ['svg' => '<circle cx="12" cy="12" r="8"/><path d="M12 11v5.2"/><circle cx="12" cy="8" r="1" fill="currentColor" stroke="none"/>'],
    ];
    foreach ($pack as $key => &$item) {
        $item['color'] = $navy;
    }
    unset($item);
    $pack['trust']['color'] = $green;
    $pack['money_guard']['color'] = $green;
    $pack['user_new']['color'] = $green;
    $pack['backup']['color'] = $green;
    $pack['health']['color'] = $red;
    return $pack;
}

function ame_sb_family_pack(): array
{
    return [
        'dash' => ['color' => '#003F8F', 'svg' => '<rect x="4" y="4" width="7" height="7" rx="1.6"/><rect x="13" y="4" width="7" height="7" rx="1.6"/><rect x="4" y="13" width="7" height="7" rx="1.6"/><rect x="13" y="13" width="7" height="7" rx="1.6"/>'],
        'members' => ['color' => '#003F8F', 'svg' => '<circle cx="8.2" cy="9" r="2.4"/><circle cx="15.6" cy="9" r="2.4"/><path d="M3.8 19c.6-2.4 2.4-3.6 4.4-3.6s3.8 1.2 4.4 3.6M12.6 19c.5-2 2-3.2 4-3.2s3.5 1.2 4 3.2"/>'],
        'contacts' => ['color' => '#003F8F', 'svg' => '<path d="M6 4.4h3.2l1.2 3.6-2 1.6a11 11 0 0 0 5 5l1.6-2 3.6 1.2v3.2c0 1-.9 1.8-1.9 1.6-6.4-1.1-11.2-5.9-12.3-12.3-.2-1 .6-1.9 1.6-1.9Z"/>'],
        'health' => ['color' => '#04933A', 'svg' => '<circle cx="12" cy="12" r="8"/><path d="M12 7.4v9.2M7.4 12h9.2"/>'],
        'shop' => ['color' => '#003F8F', 'svg' => '<path d="M5 7.2h14.2l-1.2 8.2H7.4L5.8 7.8H3.8"/><path d="M8.4 18.6a1 1 0 1 0 0 .1M16.2 18.6a1 1 0 1 0 0 .1"/>'],
        'cal' => ['color' => '#003F8F', 'svg' => '<rect x="4.2" y="5.8" width="15.6" height="13.4" rx="1.6"/><path d="M4.2 9.8h15.6M8 4.2v3M16 4.2v3"/>'],
    ];
}

function ame_sb_family_icon(string $key): string
{
    $pack = ame_sb_family_pack();
    $item = $pack[$key] ?? ['color' => '#003F8F', 'svg' => '<circle cx="12" cy="12" r="3"/>'];
    $hex = htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8');
    if (str_contains(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/modules/returns/')) {
        $hex = '#111111';
    }
    $tint = htmlspecialchars(ame_sb_tint($item['color'], 0.16), ENT_QUOTES, 'UTF-8');
    return '<span class="sb-sub-icon sb-sub-icon-chip" aria-hidden="true" style="background:#ffffff !important;color:' . $hex . ' !important;border:2px solid ' . $hex . ';"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
        . $item['svg']
        . '</svg></span>';
}

function ame_sb_settings_icon(string $key, bool $large = false): string
{
    $pack = ame_sb_settings_pack();
    $item = $pack[$key] ?? ['color' => '#475569', 'svg' => '<circle cx="12" cy="12" r="3"/>'];
    $hex = htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8');
    if (str_contains(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/modules/returns/')) {
        $hex = '#111111';
    }
    $tint = htmlspecialchars(ame_sb_tint($item['color'], $large ? 0.22 : 0.16), ENT_QUOTES, 'UTF-8');
    $cls = 'sb-sub-icon sb-sub-icon-chip' . ($large ? ' sb-sub-icon-chip-lg' : '');
    return '<span class="' . $cls . '" aria-hidden="true" style="background:#ffffff !important;color:' . $hex . ' !important;border:2px solid ' . $hex . ';"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
        . $item['svg']
        . '</svg></span>';
}

/** সাবমেনুর লাইনের সামনের ছোট আইকন — ওই সেকশনের রঙে, ইমোজির বদলে নিজস্ব আইকন। */
function ame_sb_dot($name, $hint = '')
{
    $hex = htmlspecialchars(ame_sb_color($name), ENT_QUOTES, 'UTF-8');
    if ($hint !== '') {
        $body = ame_sb_feature_icon($hint);
        return '<span class="sb-sub-icon" aria-hidden="true" style="color:' . $hex . ' !important;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . $body
            . '</svg></span>';
    }
    return '<span class="sb-sub-dot" aria-hidden="true" style="background:' . $hex . ';"></span>';
}

function ame_sb_icon($name)
{
    $icons = [
        'home' => '<path d="M4.5 11.2 12 5.2l7.5 6"/><path d="M7 10.8V19h10v-8.2"/><path d="M10 19v-5h4v5"/>',
        'pulse' => '<path d="M4 12h3.2l1.6-4 2.8 8 1.8-4H20"/>',
        'agents' => '<path d="M12 12a3.2 3.2 0 1 0 0-6.4A3.2 3.2 0 0 0 12 12Z"/><path d="M5.5 19c.7-2.8 3.2-4.4 6.5-4.4s5.8 1.6 6.5 4.4"/>',
        'smart' => '<path d="M12 4.5v3M12 16.5v3M4.5 12h3M16.5 12h3"/><circle cx="12" cy="12" r="3.2"/>',
        'sales' => '<path d="M7.2 8.2h13l-1.3 8.2a2 2 0 0 1-2 1.6H9.2a2 2 0 0 1-2-1.7L5.4 5H3.6"/><circle cx="10" cy="20.2" r="1.1" fill="currentColor" stroke="none"/><circle cx="16.6" cy="20.2" r="1.1" fill="currentColor" stroke="none"/>',
        'sale_return' => '<path d="M7 7H4.5v2.5M17 17h2.5V14.5"/><path d="M5 8.8A7.2 7.2 0 0 1 18.4 9.2M19 15.2A7.2 7.2 0 0 1 5.6 14.8"/>',
        'purchase' => '<path d="M3.5 7h11.5l1.4 7H7.2L5.6 7.8H3.5"/><path d="M8 17.5a1 1 0 1 0 0 .1M16.2 17.5a1 1 0 1 0 0 .1"/><path d="M16.8 7h3.2"/>',
        'purchase_return' => '<path d="M16 8H8l-1.5 7h8.2L16 8Z"/><path d="M8.2 8 7 4.8H4"/><path d="M14 18.5h3.2l1.3-3"/>',
        'inventory' => '<path d="M4.8 8.2 12 4.8l7.2 3.4v8.4L12 19.8 4.8 16.6V8.2Z"/><path d="M12 9.2v10.4M4.8 8.2 12 11.4 19.2 8.2"/>',
        'workshop' => '<path d="M14.8 6.4a3.6 3.6 0 0 0-5.2 4.9L5 16v3h3l4.6-4.6a3.6 3.6 0 0 0 5-5.1L15.2 12l-3-3 2.6-2.6Z"/>',
        'customers' => '<path d="M12 11.2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M5.4 19c.8-2.6 3.2-4 6.6-4s5.8 1.4 6.6 4"/>',
        'suppliers' => '<path d="M8 11.5a2.4 2.4 0 1 0 0-4.8 2.4 2.4 0 0 0 0 4.8Z"/><path d="M16 11.5a2.4 2.4 0 1 0 0-4.8 2.4 2.4 0 0 0 0 4.8Z"/><path d="M4.8 19c.5-2 2-3.2 4.2-3.2 1.4 0 2.5.5 3.2 1.3.7-.8 1.8-1.3 3.2-1.3 2.2 0 3.7 1.2 4.2 3.2"/>',
        'transactions' => '<path d="M7 8h10M7 12h10M7 16h6"/><path d="M16.5 15.2 19 17.5 16.5 19.8"/>',
        'accounts' => '<path d="M8 20V9.5h8V20"/><path d="M5.5 9.5h13"/><path d="M10 6.2 12 4.5 14 6.2V9.5"/>',
        'hrm' => '<path d="M9.2 11a2.4 2.4 0 1 0 0-4.8 2.4 2.4 0 0 0 0 4.8Z"/><path d="M15.6 11.6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/><path d="M4.8 19c.5-2.3 2.2-3.6 4.4-3.6 1.6 0 2.8.6 3.5 1.6"/><path d="M12.8 16.2c.6-.8 1.6-1.2 2.8-1.2 2 0 3.4 1.1 3.8 3"/>',
        'warranty' => '<path d="M12 4.5 19 7.2v5.4c0 4.2-2.8 6.8-7 8-4.2-1.2-7-3.8-7-8V7.2L12 4.5Z"/><path d="m8.8 12.2 2.2 2.2 4.2-4.4"/>',
        'loans' => '<circle cx="12" cy="12" r="7.6"/><path d="M9.6 15.4c0 1.1.9 1.7 2.1 1.7s2.3-.5 2.3-1.6c0-1.2-1.1-1.5-2.3-1.8-1.2-.3-2.2-.7-2.2-1.8 0-1 1-1.6 2.2-1.6s2 .5 2.1 1.5"/><path d="M12 7.4v1.2M12 15.4v1.2"/>',
        'rental' => '<path d="M7 4.8h10v4.4H7z"/><path d="M5.2 9.2h13.6v10H5.2z"/><path d="M9 13h6M9 16h4"/>',
        'shop_rent' => '<path d="M4 10.2 12 4.6l8 5.6"/><path d="M6.2 10V19h11.6V10"/><path d="M10 19v-4.2h4V19"/>',
        'reports' => '<path d="M5 18h14"/><path d="M7 18V12"/><path d="M12 18V8"/><path d="M17 18v-7"/>',
        'family' => '<path d="M4.5 11.2 12 5.2l7.5 6"/><path d="M7 10.8V19h10v-8.2"/><circle cx="12" cy="13.2" r="1.3"/>',
        'others' => '<path d="M5 6.5h6.2v6.2H5zM12.8 6.5H19v6.2h-6.2zM5 13.8h6.2V20H5zM12.8 13.8H19V20h-6.2z"/>',
        'ticker' => '<path d="M4.8 10.2v3.6l4.2-1.2 2 .8h8.2c.8 0 1.4-.6 1.4-1.4v-1.2c0-.8-.6-1.4-1.4-1.4H11l-2 .8-4.2-1.2Z"/>',
        'countdown' => '<path d="M8 5h8M12 5v3.2M8.8 9.5h6.4L12 13.2 8.8 9.5Z"/><path d="M8.8 18.5h6.4L12 14.8 8.8 18.5Z"/>',
        'appearance' => '<rect x="4.2" y="5.2" width="8.4" height="8.4" rx="1.8"/><rect x="10.2" y="9.2" width="8.6" height="8.6" rx="1.8"/><rect x="7.4" y="8" width="6.8" height="6.8" rx="1.4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 4.6v1.6M12 17.8v1.6M4.6 12h1.6M17.8 12h1.6M6.5 6.5l1.1 1.1M16.4 16.4l1.1 1.1M17.5 6.5l-1.1 1.1M7.6 16.4l-1.1 1.1"/>',
        'expense' => '<rect x="5.2" y="3.8" width="13.6" height="16.4" rx="2"/><path d="M8.4 9.2h7.2M8.4 12.6h4.8M8.4 16h3.2"/>',
        'atlas' => '<rect x="4.4" y="5.2" width="6.4" height="13.6" rx="1.2"/><rect x="12.2" y="5.2" width="7.4" height="8.2" rx="1.2"/><path d="M6.2 8.4h2.6M6.2 11h2.6M6.2 13.6h2.2M14 8.4h3.2M14 10.8h2.4"/>',
        'logout' => '<path d="M10 7.2V5.6A1.6 1.6 0 0 1 11.6 4h6.8A1.6 1.6 0 0 1 20 5.6v12.8A1.6 1.6 0 0 1 18.4 20h-6.8A1.6 1.6 0 0 1 10 18.4v-1.6"/><path d="M4 12h9.2"/><path d="m10.2 8.8 3.4 3.2-3.4 3.2"/>',
        'back' => '<circle cx="12" cy="12" r="8.2"/><path d="M13.6 8 9.4 12l4.2 4"/>',
        'fullscreen' => '<path d="M8.2 4.8H4.8v3.4M15.8 4.8h3.4v3.4M8.2 19.2H4.8v-3.4M15.8 19.2h3.4v-3.4"/>',
        'pay' => '<rect x="3.4" y="6.4" width="13.6" height="8.6" rx="1.6"/><rect x="7.2" y="10.4" width="13.4" height="7.4" rx="1.4"/>',
        'bell' => '<path d="M6.6 9.2a5.4 5.4 0 0 1 10.8 0c0 3.2 1.2 4.4 1.2 4.4H5.4s1.2-1.2 1.2-4.4Z"/><path d="M10 18.4a2 2 0 0 0 4 0"/>',
        'calc' => '<rect x="5.4" y="3.6" width="13.2" height="16.8" rx="2"/><rect x="7.4" y="5.8" width="9.2" height="3.4" rx="0.8"/><path d="M8.2 12.4h2.2M13.6 12.4h2.2M8.2 16.2h2.2M13.6 16.2h2.2"/>',
        'lang' => '<circle cx="12" cy="12" r="8"/><path d="M4.4 12h15.2M12 4.2v15.6M8.2 8.2c1.2 1.6 2.5 2.4 3.8 2.4s2.6-.8 3.8-2.4M8.2 15.8c1.2-1.6 2.5-2.4 3.8-2.4s2.6.8 3.8 2.4"/>',
        'net' => '<path d="M4 9.2c4.8-4 11.2-4 16 0M6.6 12.4c3.4-2.6 7.4-2.6 10.8 0M9.4 15.6c1.6-1.2 3.6-1.2 5.2 0"/><circle cx="12" cy="18.8" r="1.1" fill="currentColor" stroke="none"/>',
        'stores' => '<path d="M4.6 9.2 12 4.8l7.4 4.4V19H4.6V9.2Z"/><path d="M9.2 19v-6.2h5.6V19"/>',
    ];
    $body = $icons[$name] ?? $icons['home'];
    $hex = htmlspecialchars(ame_sb_color($name), ENT_QUOTES, 'UTF-8');
    if (str_contains(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/modules/returns/')) {
        $hex = '#111111';
    }
    $tint = htmlspecialchars(ame_sb_tint(ame_sb_color($name)), ENT_QUOTES, 'UTF-8');
    return '<span class="menu-icon-chip" aria-hidden="true" style="background:#ffffff !important;color:' . $hex . ' !important;border:2px solid ' . $hex . ';"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
        . $body
        . '</svg></span>';
}
