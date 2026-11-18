<?php
/**
 * মেমোর ভাষা। বাংলা সিলেক্ট থাকলে মেমোর লেবেল বাংলা, ইংরেজি সিলেক্ট থাকলে ইংরেজি।
 * নাম, ফোন, ইনভয়েস নম্বর ও টাকার অঙ্ক যেমন আছে তেমন থাকে।
 */
if (!function_exists('ame_ui_bn')) {
    function ame_ui_bn(): bool
    {
        if (function_exists('is_en')) {
            return !is_en();
        }
        if (function_exists('current_lang')) {
            return current_lang() !== 'en';
        }
        return true;
    }
}

if (!function_exists('ame_ui')) {
    function ame_ui(string $bn, string $en): string
    {
        return ame_ui_bn() ? $bn : $en;
    }
}

if (!function_exists('ame_ui_digits')) {
    function ame_ui_digits(string $text): string
    {
        if (!ame_ui_bn()) {
            return $text;
        }
        return strtr($text, [
            '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
            '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
        ]);
    }
}

if (!function_exists('ame_ui_date')) {
    function ame_ui_date(int $ts): string
    {
        if ($ts <= 0) {
            $ts = time();
        }
        if (!ame_ui_bn()) {
            return date('j F Y', $ts);
        }
        $months = [
            1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 4 => 'এপ্রিল',
            5 => 'মে', 6 => 'জুন', 7 => 'জুলাই', 8 => 'আগস্ট',
            9 => 'সেপ্টেম্বর', 10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর',
        ];
        $text = (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
        return ame_ui_digits($text);
    }
}

if (!function_exists('ame_ui_time')) {
    function ame_ui_time(int $ts): string
    {
        if ($ts <= 0) {
            $ts = time();
        }
        $clock = date('g:i', $ts);
        if (!ame_ui_bn()) {
            return $clock . ' ' . date('A', $ts);
        }
        $half = ((int) date('G', $ts) < 12) ? 'পূর্বাহ্ন' : 'অপরাহ্ন';
        return ame_ui_digits($clock) . ' ' . $half;
    }
}

if (!function_exists('ame_ui_sale_type')) {
    function ame_ui_sale_type(string $raw): string
    {
        $key = strtolower(trim($raw));
        $key = str_replace([' ', '-'], '_', $key);
        $map = [
            'retail' => ['খুচরা', 'Retail'],
            'wholesale' => ['পাইকারি', 'Wholesale'],
            'dealer' => ['ডিলার', 'Dealer'],
            'registered' => ['নিবন্ধিত', 'Registered'],
            'cash' => ['নগদ', 'Cash'],
            'walk_in' => ['নগদ', 'Cash'],
            'walkin' => ['নগদ', 'Cash'],
            'credit' => ['বাকি', 'Credit'],
            'খুচরা' => ['খুচরা', 'Retail'],
            'পাইকারি' => ['পাইকারি', 'Wholesale'],
            'ডিলার' => ['ডিলার', 'Dealer'],
            'নিবন্ধিত' => ['নিবন্ধিত', 'Registered'],
            'নগদ' => ['নগদ', 'Cash'],
        ];
        if (!isset($map[$key])) {
            return $raw;
        }
        return ame_ui_bn() ? $map[$key][0] : $map[$key][1];
    }
}
