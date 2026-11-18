<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
function ame_permission_catalog(): array
{
    return [
        'commerce' => [
            'en' => 'Commerce',
            'bn' => 'বিক্রি ও কেনাকাটা',
            'lead' => 'কাউন্টার, স্টক ও সাপ্লাই চেইন — দোকানের মূল স্রোত',
            'items' => [
                'm-sales' => [
                    'title' => 'পার্টস বিক্রি ও কাউন্টার',
                    'desc' => 'যন্ত্রাংশ বিক্রি, ইনভয়েস ও নতুন সেল',
                    'icon' => 'receipt',
                ],
                'm-sales-report' => [
                    'title' => 'বিক্রয় রিপোর্ট',
                    'desc' => 'বিক্রয় রিপোর্ট দেখা। না দিলে অন্য ইউজার দেখতে পারবে না — শুধু অ্যাডমিন ও যাদের টিক দেওয়া।',
                    'icon' => 'chart',
                ],
                'm-sales-returns' => [
                    'title' => 'বিক্রয় ফেরত',
                    'desc' => 'বিক্রি হওয়া পণ্য ফেরত ও অ্যাডজাস্ট',
                    'icon' => 'undo',
                ],
                'm-purchase' => [
                    'title' => 'ক্রয়',
                    'desc' => 'সাপ্লায়ার থেকে মাল কেনা ও মেমো',
                    'icon' => 'truck',
                ],
                'm-purchase-returns' => [
                    'title' => 'ক্রয় ফেরত',
                    'desc' => 'কেনা মাল ফেরত ও ক্রয় সংশোধন',
                    'icon' => 'return',
                ],
                'm-inv' => [
                    'title' => 'পার্টস স্টক',
                    'desc' => 'যন্ত্রাংশ, মজুদ, ট্রান্সফার ও কাউন্ট',
                    'icon' => 'box',
                ],
                'm-workshop' => [
                    'title' => 'ওয়ার্কশপ',
                    'desc' => 'জব কার্ড, কোটেশন, সিরিয়াল ও যন্ত্রাংশ রিজার্ভ',
                    'icon' => 'shield',
                ],
            ],
        ],
        'people' => [
            'en' => 'People',
            'bn' => 'মানুষ',
            'lead' => 'ক্রেতা, সাপ্লায়ার ও কর্মী — সম্পর্কের খাতা',
            'items' => [
                'm-customers' => [
                    'title' => 'ক্রেতা',
                    'desc' => 'কাস্টমার লিস্ট, বকেয়া ও পেমেন্ট',
                    'icon' => 'person',
                ],
                'm-hire' => [
                    'title' => 'কাস্টমার ভাড়া দেখা',
                    'desc' => 'ভাড়া চুক্তি, ড্যাশবোর্ড ও স্টেটমেন্ট',
                    'icon' => 'box',
                ],
                'm-hire-rate' => [
                    'title' => 'ভাড়া হার পরিবর্তন',
                    'desc' => 'দৈনিক/মাসিক রেট বদলানো। আলাদা অনুমতি।',
                    'icon' => 'ledger',
                ],
                'm-hire-product' => [
                    'title' => 'ভাড়া পণ্য পরিবর্তন',
                    'desc' => 'চুক্তির পণ্য/সিরিয়াল বদল ও স্টক সরানো',
                    'icon' => 'box',
                ],
                'm-hire-deposit' => [
                    'title' => 'জামানত ব্যবহার',
                    'desc' => 'ডিপোজিট ওয়ালেট থেকে সমন্বয়',
                    'icon' => 'bank',
                ],
                'm-hire-refund' => [
                    'title' => 'জামানত ফেরত',
                    'desc' => 'কাস্টমারকে ডিপোজিট ফেরত দেওয়া',
                    'icon' => 'undo',
                ],
                'm-hire-adjust' => [
                    'title' => 'ভাড়া আর্থিক সমন্বয়',
                    'desc' => 'রেক্যালকুলেট ও অ্যাডজাস্টমেন্ট পোস্ট',
                    'icon' => 'ledger',
                ],
                'm-hire-cancel' => [
                    'title' => 'ভাড়া চুক্তি বাতিল',
                    'desc' => 'আর্কাইভ/বাতিল — হার্ড ডিলিট নয়',
                    'icon' => 'undo',
                ],
                'm-suppliers' => [
                    'title' => 'সাপ্লায়ার',
                    'desc' => 'সাপ্লায়ার লেজার ও পেমেন্ট',
                    'icon' => 'handshake',
                ],
                'm-hrm' => [
                    'title' => 'কর্মী',
                    'desc' => 'এইচআর, উপস্থিতি ও নিয়মিত বেতন',
                    'icon' => 'badge',
                ],
                'm-hrm-advance' => [
                    'title' => 'বেতন অগ্রিম',
                    'desc' => 'অগ্রিম দেওয়া, বদলানো ও ফেরত। না দিলে শুধু অ্যাডমিন পারবে।',
                    'icon' => 'badge',
                ],
                'm-rental' => [
                    'title' => 'ভাড়া মালিক স্টেটমেন্ট',
                    'desc' => 'যার কাছ থেকে জিনিস আনা হয় তার হিসাব ও স্টেটমেন্ট',
                    'icon' => 'box',
                ],
                'm-shop-rent' => [
                    'title' => 'দোকান ভাড়া',
                    'desc' => 'আমরা যে দোকান ভাড়া নিয়েছি তার চুক্তি, ভাড়া ও কাগজ',
                    'icon' => 'box',
                ],
                'm-family' => [
                    'title' => 'পারিবারিক ব্যবস্থাপনা',
                    'desc' => 'সদস্য, স্বাস্থ্য, ওষুধ, বাজার, খরচ ও ক্যালেন্ডার',
                    'icon' => 'person',
                ],
            ],
        ],
        'money' => [
            'en' => 'Treasury',
            'bn' => 'অর্থ ও হিসাব',
            'lead' => 'ক্যাশ, খরচ, অংশীদার ও ঋণ — টাকার শৃঙ্খলা',
            'items' => [
                'm-transactions' => [
                    'title' => 'লেনদেন',
                    'desc' => 'ক্যাশ ইন/আউট ও দৈনিক জমা',
                    'icon' => 'swap',
                ],
                'm-acc' => [
                    'title' => 'হিসাব-নিকাশ',
                    'desc' => 'খরচ, অংশীদার ও অ্যাকাউন্ট',
                    'icon' => 'ledger',
                ],
                'm-loans' => [
                    'title' => 'লোন / ঋণ',
                    'desc' => 'ঋণ, কিস্তি ও আদায়',
                    'icon' => 'bank',
                ],
                'm-oloans' => [
                    'title' => 'দোকান মালিক ও ঋণ',
                    'desc' => 'দোকান মালিক, ব্যাংক ঋণ ও সঞ্চয় ব্যবস্থাপনা',
                    'icon' => 'bank',
                ],
                'm-bloan' => [
                    'title' => 'ব্যবসার ঋণ',
                    'desc' => 'দোকান ব্যাংক, এনজিও বা প্রতিষ্ঠান থেকে যে ঋণ নেয়',
                    'icon' => 'bank',
                ],
            ],
        ],
        'operations' => [
            'en' => 'Operations',
            'bn' => 'অপারেশন',
            'lead' => 'সার্ভিস, রিপোর্ট ও অন্যান্য টুল',
            'items' => [
                'm-warranty' => [
                    'title' => 'ওয়ারেন্টি',
                    'desc' => 'সার্ভিস, ইঞ্জিনিয়ার ও গ্যারান্টি',
                    'icon' => 'shield',
                ],
                'm-reports' => [
                    'title' => 'রিপোর্ট',
                    'desc' => 'বিক্রি, স্টক ও লাভের বিবরণ',
                    'icon' => 'chart',
                ],
                'm-pulse' => [
                    'title' => 'দোকানের অবস্থা',
                    'desc' => 'আজকের কাজ, এজেন্ট, রিস্টক ও হোয়াটসঅ্যাপ স্লিপ',
                    'icon' => 'chart',
                ],
                'm-smart' => [
                    'title' => 'স্মার্ট ব্যবসা হাব',
                    'desc' => 'সহকারী, স্টক অনুমান, ক্রেতা বুদ্ধি, লাভ ও ক্যালেন্ডার',
                    'icon' => 'chart',
                ],
                'm-others' => [
                    'title' => 'অন্যান্য',
                    'desc' => 'কন্টাক্ট, ইউটিলিটি ও ব্যক্তিগত নোট',
                    'icon' => 'folder',
                ],
            ],
        ],
        'appearance' => [
            'en' => 'House style',
            'bn' => 'চেহারা ও সিস্টেম',
            'lead' => 'দোকানের মুখ ও অ্যাডমিন কন্ট্রোল',
            'items' => [
                'm-ticker' => [
                    'title' => 'চলমান লেখা',
                    'desc' => 'হোম স্ক্রিনের টিকার সম্পাদনা',
                    'icon' => 'megaphone',
                ],
                'm-countdown' => [
                    'title' => 'কাউন্টডাউন',
                    'desc' => 'অফার ও ইভেন্টের ঘড়ি',
                    'icon' => 'clock',
                ],
                'm-settings' => [
                    'title' => 'সিস্টেম সেটিংস',
                    'desc' => 'দোকান, ইউজার, রং ও নিরাপত্তা',
                    'icon' => 'gear',
                ],
            ],
        ],
    ];
}

function ame_permission_icon(string $name): string
{
    $icons = [
        'receipt' => '<path d="M6 3h12v18l-2-1.4-2 1.4-2-1.4-2 1.4-2-1.4-2 1.4V3z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'undo' => '<path d="M8 8H4V4"/><path d="M4 8a8 8 0 1 1-1 5"/>',
        'truck' => '<path d="M3 7h11v10H3zM14 10h4l3 3v4h-7"/><circle cx="7" cy="17" r="1.5"/><circle cx="17" cy="17" r="1.5"/>',
        'return' => '<path d="M16 8H8l3-3M8 8l3 3"/><path d="M8 16h8"/>',
        'box' => '<path d="M3 8l9-5 9 5-9 5-9-5z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>',
        'person' => '<circle cx="12" cy="8" r="3.2"/><path d="M5 19c1.4-3.2 3.8-4.8 7-4.8S17.6 15.8 19 19"/>',
        'handshake' => '<path d="M8 13l3 3 6-6M4 14l4 4 3-3"/><path d="M14 8l3-3 4 4"/>',
        'badge' => '<circle cx="12" cy="9" r="3"/><path d="M6 20c.8-3.4 3-5 6-5s5.2 1.6 6 5"/><path d="M12 3l1.4 2.6L16 6.2l-2 2 .5 2.8L12 10.2 9.5 11l.5-2.8-2-2 2.6-.6z"/>',
        'swap' => '<path d="M7 8h12l-3-3M17 16H5l3 3"/>',
        'ledger' => '<path d="M6 4h12v16H6z"/><path d="M9 8h6M9 12h6M9 16h4"/>',
        'bank' => '<path d="M4 10h16M6 10v8M10 10v8M14 10v8M18 10v8M3 18h18M12 4l9 6H3z"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.4 8.2-8 9-4.6-.8-8-4-8-9V6l8-3z"/>',
        'chart' => '<path d="M5 19V9M11 19V5M17 19v-7"/>',
        'folder' => '<path d="M3 8h6l2 2h10v9H3z"/><path d="M3 8V6h6l2 2"/>',
        'megaphone' => '<path d="M4 10v4h3l7 4V6L7 10H4z"/><path d="M18 10.5a3 3 0 0 1 0 3"/>',
        'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 4v2M12 18v2M4 12h2M18 12h2M6.2 6.2l1.4 1.4M16.4 16.4l1.4 1.4M17.8 6.2l-1.4 1.4M7.6 16.4l-1.4 1.4"/>',
    ];
    $path = $icons[$name] ?? $icons['folder'];
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="square" stroke-linejoin="miter">' . $path . '</svg>';
}

function ame_permission_coverage(mysqli $db, array $user, int $total): array
{
    $role = strtolower((string)($user['role'] ?? ''));
    if ($role === 'admin') {
        return [$total, 100, 'সব খোলা'];
    }
    if ((int)($user['use_custom_perms'] ?? 0) === 1) {
        $n = count(ame_user_permission_keys($db, (int)$user['id']));
        return [$n, $total ? (int)round(100 * $n / $total) : 0, 'কাস্টম অ্যাটেলিয়ে'];
    }
    $stmt = $db->prepare("SELECT COUNT(*) c FROM role_permissions WHERE role_key=?");
    $stmt->bind_param('s', $user['role']);
    $stmt->execute();
    $n = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return [$n, $total ? (int)round(100 * $n / $total) : 0, 'রোল অনুযায়ী'];
}
