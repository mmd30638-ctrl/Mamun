<?php
/**
 * APONSHOP Unified SMS Templates — সব কনফার্ম SMS এক ফরম্যাটে।
 * ============================================================
 * নিয়ম:
 * - প্রতিটি SMS: দোকানের নাম (হেডার) + মূল বার্তা + ধন্যবাদ (ফুটার)।
 * - সংক্ষিপ্ত রাখো (SMS সেগমেন্ট খরচ) — ইমোজি সর্বোচ্চ ২-৩টি।
 * - টাকার ফরম্যাট একরকম: ৳1,234.00।
 * - নতুন SMS লাগলে এখানে টেমপ্লেট যোগ করে সবাই এটাই ব্যবহার করবে।
 */

if (!function_exists('ame_sms_shop')) {
    function ame_sms_shop(): string
    {
        $name = function_exists('shop_name') ? (string)shop_name() : '';
        return $name !== '' ? $name : 'APONSHOP';
    }
}

if (!function_exists('ame_sms_taka')) {
    function ame_sms_taka($amount): string
    {
        return '৳' . number_format((float)$amount, 2);
    }
}

if (!function_exists('ame_sms_text')) {
    /**
     * @param string $type sale_confirm|payment_receive|due_reminder|
     *   workshop_receive|workshop_ready|workshop_deliver|otp|low_balance|sim_test
     */
    function ame_sms_text(string $type, array $data = []): string
    {
        $shop = ame_sms_shop();
        switch ($type) {
            case 'sale_confirm':
                return $shop . "\n✅ বিক্রয় " . ($data['invoice'] ?? '') .
                    "\n💰 মোট: " . ame_sms_taka($data['total'] ?? 0) .
                    "\n💵 জমা: " . ame_sms_taka($data['paid'] ?? 0) .
                    "\n⏳ বাকি: " . ame_sms_taka($data['due'] ?? 0) .
                    "\n🙏 ধন্যবাদ";
            case 'payment_receive':
                return $shop . "\n✅ পেমেন্ট " . ame_sms_taka($data['paid'] ?? 0) .
                    "\n⏳ বাকি: " . ame_sms_taka($data['due'] ?? 0) .
                    "\n🙏 ধন্যবাদ";
            case 'due_reminder':
                $msg = $shop . "\n⏰ বকেয়া: " . ame_sms_taka($data['due'] ?? 0);
                if (!empty($data['date'])) {
                    $msg .= "\n📅 তারিখ: " . $data['date'];
                }
                return $msg . "\n🙏 ধন্যবাদ";
            case 'workshop_receive':
                return $shop . "\n✅ জব গ্রহণ: " . ($data['job'] ?? '') .
                    "\n🔧 মেশিন: " . ($data['item'] ?? '') .
                    "\n🙏 ধন্যবাদ";
            case 'workshop_ready':
                return $shop . "\n✅ জব রেডি: " . ($data['job'] ?? '') .
                    "\n📍 মেশিন নিতে আসুন" .
                    "\n🙏 ধন্যবাদ";
            case 'workshop_deliver':
                return $shop . "\n✅ জব ডেলিভারি: " . ($data['job'] ?? '') .
                    "\n💰 বিল: " . ame_sms_taka($data['total'] ?? 0) .
                    "\n⏳ বাকি: " . ame_sms_taka($data['due'] ?? 0) .
                    "\n🙏 ধন্যবাদ";
            case 'otp':
                return $shop . "\n🔑 " . ($data['label'] ?? 'OTP') . ': ' . ($data['code'] ?? '') .
                    "\n⏳ মেয়াদ ১০ মিনিট। কাউকে দেবেন না।";
            case 'low_balance':
                return $shop . "\n⚠️ '" . ($data['account'] ?? '') . "' ব্যালেন্স কম: " .
                    ame_sms_taka($data['balance'] ?? 0) .
                    ' (সীমা ' . ame_sms_taka($data['limit'] ?? 0) . ')';
            case 'sim_test':
                return $shop . "\n✅ টেস্ট SMS সফল\n🙏 ধন্যবাদ";
            default:
                return $shop . "\n" . (string)($data['text'] ?? '');
        }
    }
}
