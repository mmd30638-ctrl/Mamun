<?php
/**
 * ================================================================
 * Brevo (আগে Sendinblue) API কনফিগারেশন
 * ================================================================
 * Credentials are loaded from the private environment configuration.
 *
 * API Key পাবেন যেভাবে:
 * ১. https://www.brevo.com -এ ফ্রি অ্যাকাউন্ট খুলুন
 * ২. লগইন করে উপরে ডানে প্রোফাইল আইকন → SMTP & API → API Keys
 * ৩. "Generate a new API key" চেপে একটা key তৈরি করুন
 * ৪. BREVO_API_KEY ও BREVO_SENDER_EMAIL .env-এ সেট করুন
 */

define('BREVO_API_KEY', (string)(getenv('BREVO_API_KEY') ?: ''));

// ================= পাঠানো ইমেইলে "প্রেরক" হিসেবে যা দেখাবে =================
// ⚠️ গুরুত্বপূর্ণ: Brevo-তে এই ইমেইল ঠিকানাটা আগে থেকে "verified sender"
// হিসেবে যোগ করা থাকতে হবে (Brevo ড্যাশবোর্ড → Senders & IP → Senders)।
define('BREVO_SENDER_EMAIL', (string)(getenv('BREVO_SENDER_EMAIL') ?: ''));
