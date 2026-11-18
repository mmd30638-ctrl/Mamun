<?php
/*
 * বাংলা–ইংরেজি ভাষা ব্যবস্থা (Aponshop v11.00.0)
 * নির্বাচিত ভাষায় যা নেই, অন্য ভাষা থেকে আনা হবে না।
 * ইংরেজি মোডে HTML আউটপুটের বাংলা UI লেখা অনুবাদ হয়।
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$GLOBALS['supported_languages'] = [
    'bn' => [
        'name' => 'বাংলা',
        'short_name' => 'ইংরেজি',
        'switch_to' => 'ইংরেজি',
        'direction' => 'ltr'
    ],
    'en' => [
        'name' => 'English',
        'short_name' => 'Bengali',
        'switch_to' => 'Bengali',
        'direction' => 'ltr'
    ]
];

if (!isset($_SESSION['app_lang'])) {
    $cookie_lang = strtolower(trim((string)($_COOKIE['app_lang'] ?? '')));
    if (array_key_exists($cookie_lang, $GLOBALS['supported_languages'])) {
        $_SESSION['app_lang'] = $cookie_lang;
    }
}

$selected_language = $_SESSION['app_lang'] ?? 'bn';

if (!array_key_exists($selected_language, $GLOBALS['supported_languages'])) {
    $selected_language = 'bn';
    $_SESSION['app_lang'] = 'bn';
}

if (!function_exists('ame_load_lang_pack')) {
    function ame_load_lang_pack(string $code): array
    {
        static $cache = [];
        if (isset($cache[$code])) {
            return $cache[$code];
        }
        $out = [];
        foreach ([
            __DIR__ . '/lang/' . $code . '.php',
            __DIR__ . '/lang/' . $code . '_ui.php',
        ] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $loaded = include $file;
            if (is_array($loaded)) {
                $out = array_merge($out, $loaded);
            }
        }
        $cache[$code] = $out;
        return $out;
    }
}

$GLOBALS['__lang_data'] = ame_load_lang_pack($selected_language);
$GLOBALS['__fallback_lang_data'] = [];

if (!function_exists('t')) {
    function t($key, $replacements = [])
    {
        $text = $GLOBALS['__lang_data'][$key] ?? (string)$key;

        foreach ($replacements as $name => $value) {
            $text = str_replace(':' . $name, (string)$value, $text);
        }

        return $text;
    }
}

if (!function_exists('te')) {
    function te($key, $replacements = [])
    {
        return htmlspecialchars(t($key, $replacements), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('has_t')) {
    function has_t($key): bool
    {
        return isset($GLOBALS['__lang_data'][$key]);
    }
}

if (!function_exists('tx')) {
    function tx(string $text): string
    {
        return has_t($text) ? t($text) : $text;
    }
}

if (!function_exists('current_lang')) {
    function current_lang()
    {
        return $_SESSION['app_lang'] ?? 'bn';
    }
}

if (!function_exists('is_en')) {
    function is_en(): bool
    {
        return current_lang() === 'en';
    }
}

if (!function_exists('supported_languages')) {
    function supported_languages()
    {
        return $GLOBALS['supported_languages'];
    }
}

if (!function_exists('current_language_name')) {
    function current_language_name()
    {
        $code = current_lang();
        return $GLOBALS['supported_languages'][$code]['name'] ?? 'বাংলা';
    }
}

if (!function_exists('current_language_short_name')) {
    function current_language_short_name()
    {
        $code = current_lang();
        return $GLOBALS['supported_languages'][$code]['switch_to']
            ?? $GLOBALS['supported_languages'][$code]['short_name']
            ?? 'ইংরেজি';
    }
}

if (!function_exists('current_text_direction')) {
    function current_text_direction()
    {
        $code = current_lang();
        return $GLOBALS['supported_languages'][$code]['direction'] ?? 'ltr';
    }
}

if (!function_exists('ame_word_map')) {
    function ame_word_map(): array
    {
        return [
            'বাজার' => 'word_market',
            'চিকিৎসা' => 'word_medical',
            'ওষুধ' => 'word_medicine',
            'শিক্ষা' => 'word_education',
            'বই' => 'word_book',
            'যাতায়াত' => 'word_travel',
            'বিদ্যুৎ' => 'word_electric',
            'পানি' => 'word_water',
            'ইন্টারনেট' => 'word_internet',
            'মোবাইল' => 'word_mobile',
            'বাসা' => 'word_home',
            'পোশাক' => 'word_clothes',
            'অন্যান্য' => 'word_other',
            'চাল' => 'word_rice',
            'ডাল' => 'word_lentil',
            'তেল' => 'word_oil',
            'মসলা' => 'word_spice',
            'কাপড়' => 'word_cloth',
            'পরিষ্কার' => 'word_cleaning',
            'রান্নাঘর' => 'word_kitchen',
            'ইলেকট্রনিক্স' => 'word_electronics',
            'ফার্নিচার' => 'word_furniture',
            'কিতাব' => 'word_kitab',
            'যন্ত্রপাতি' => 'word_tools',
            'জন্মদিন' => 'word_birthday',
            'বিবাহবার্ষিকী' => 'word_anniversary',
            'ডাক্তার' => 'word_doctor',
            'বিল' => 'word_bill',
            'ওয়ারেন্টি' => 'word_warranty',
            'ওয়ারেন্টি শেষ' => 'word_warranty_end',
            'প্রেসক্রিপশন' => 'word_prescription',
            'নগদ' => 'word_cash',
            'বিকাশ' => 'word_bkash',
            'নগদ-মোবাইল' => 'word_nagad',
            'রকেট' => 'word_rocket',
            'কার্ড' => 'word_card',
            'ব্যাংক' => 'word_bank',
            'জরুরি' => 'word_urgent',
            'উচ্চ' => 'word_high',
            'মাঝারি' => 'word_medium',
            'কম' => 'word_low',
            'ভালো' => 'word_good',
            'মোটামুটি' => 'word_fair',
            'মেরামত লাগবে' => 'word_needs_repair',
            'খারাপ' => 'word_bad',
            'বাবা' => 'word_father',
            'মা' => 'word_mother',
            'স্বামী' => 'word_husband',
            'স্ত্রী' => 'word_wife',
            'ছেলে' => 'word_son',
            'মেয়ে' => 'word_daughter',
            'ভাই' => 'word_brother',
            'বোন' => 'word_sister',
            'দাদা' => 'word_grandpa_p',
            'দাদি' => 'word_grandma_p',
            'নানা' => 'word_grandpa_m',
            'নানি' => 'word_grandma_m',
            'সদস্য' => 'fam_nav_members',
            'পরিচিত' => 'fam_nav_contacts',
            'গ্রুপ' => 'fam_nav_groups',
            'নোট' => 'fam_nav_notes',
            'ডকুমেন্ট' => 'fam_nav_docs',
            'খরচ' => 'fam_nav_expense',
            'রিপোর্ট' => 'fam_nav_report',
            'ইনভেন্টরি' => 'fam_nav_inventory',
            'ইভেন্ট' => 'word_event',
            'admin' => 'role_admin',
            'Admin' => 'role_admin',
            'ADMIN' => 'role_admin',
            'member' => 'role_member',
            'limited' => 'role_limited',
            'view' => 'role_view',
            'warranty' => 'word_warranty',
            'বইয়ের invoice' => 'word_book_invoice',
            'ওষুধ refill' => 'word_med_refill',
        ];
    }
}

if (!function_exists('ame_word')) {
    function ame_word($word): string
    {
        $word = trim((string)$word);
        if ($word === '') {
            return '';
        }
        $map = ame_word_map();
        if (isset($map[$word])) {
            return t($map[$word]);
        }
        return $word;
    }
}

if (!function_exists('ame_i18n_phrases')) {
    function ame_i18n_phrases(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $file = __DIR__ . '/lang/phrases.php';
        $loaded = is_file($file) ? include $file : [];
        $map = is_array($loaded) ? $loaded : [];
        return $map;
    }
}

if (!function_exists('ame_i18n_is_json_payload')) {
    function ame_i18n_is_json_payload(string $html): bool
    {
        $trim = ltrim($html);
        if ($trim === '') {
            return false;
        }
        $first = $trim[0];
        return $first === '{' || $first === '[';
    }
}

if (!function_exists('ame_i18n_apply')) {
    function ame_i18n_apply(string $html): string
    {
        if ($html === '' || !is_en()) {
            return $html;
        }
        if (ame_i18n_is_json_payload($html)) {
            return $html;
        }
        $map = ame_i18n_phrases();
        if (!$map) {
            return $html;
        }
        foreach ($map as $bn => $en) {
            if ($bn === '' || $en === $bn) {
                continue;
            }
            if (strpos($html, $bn) !== false) {
                $html = str_replace($bn, $en, $html);
            }
        }
        return $html;
    }
}

if (!function_exists('ame_pair')) {
    /*
     * বাংলা/ইংরেজি দুটো লেখা একসাথে সবসময় দেখানো হতো — এখন সেটিংসে যে ভাষা
     * বাছা আছে (is_en()) শুধু সেই একটাই দেখাবে। এই ফাংশনটা এখানে (lang_helper.php)
     * সংজ্ঞায়িত হওয়ায় — এবং config.php প্রতিটা পেজে সবচেয়ে আগে এই ফাইল require
     * করায় — কোনো মডিউল ফাইলের নিজের পুরনো "if (!function_exists('ame_pair'))"
     * সংস্করণ আর কার্যকর হয় না, তাই আলাদা করে সব জায়গায় বদলাতে হয়নি।
     */
    function ame_pair(string $bn, string $en): string
    {
        $text = is_en() ? $en : $bn;
        return '<span class="ame-pair"><strong>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</strong></span>';
    }
}

if (!function_exists('ame_l')) {
    /** সংক্ষেপে: is_en() হলে $en, নাহলে $bn — সরাসরি টেক্সট হিসেবে (HTML-escape ছাড়া)। */
    function ame_l(string $bn, string $en): string
    {
        return is_en() ? $en : $bn;
    }
}

if (!function_exists('ame_i18n_assets')) {
    function ame_i18n_assets(): string
    {
        $ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '11.00';
        $ver = htmlspecialchars((string)$ver, ENT_QUOTES, 'UTF-8');
        $out = '<link rel="stylesheet" href="/assets/css/ame-i18n.css?v=' . $ver . '">';
        if (is_en()) {
            $out .= '<script src="/assets/js/ame-i18n-phrases.js?v=' . $ver . '" defer></script>';
        }
        $out .= '<script src="/assets/js/ame-i18n.js?v=' . $ver . '" defer></script>';
        return $out;
    }
}
