<?php
/**
 * ================================================================
 * সাধারণ ইমেইল পাঠানোর হেল্পার — PHP-এর নিজস্ব mail() ফাংশন ব্যবহার করে
 * ================================================================
 * ⚠️ গুরুত্বপূর্ণ সীমাবদ্ধতা: এটা হোস্টিং সার্ভারের (Hostinger) নিজস্ব
 * মেইল সিস্টেমের ওপর নির্ভরশীল। Gmail-এ পাঠানো মেইল মাঝে মাঝে
 * স্প্যাম/জাঙ্ক ফোল্ডারে যেতে পারে — এটা কোনো কোডের ত্রুটি না,
 * বরং সাধারণ ইমেইল ডেলিভারেবিলিটি সীমাবদ্ধতা।
 */
require_once __DIR__ . '/brevo_config.php';

function send_simple_email($to_email, $subject, $body_html) {
    // Brevo API Key কনফিগার করা থাকলে সেটাই ব্যবহার হবে (নির্ভরযোগ্য ডেলিভারি)
    if (defined('BREVO_API_KEY') && BREVO_API_KEY !== '') {
        return send_email_via_brevo($to_email, $subject, $body_html);
    }
    // key না থাকলে পুরনো পদ্ধতিতে (PHP mail()) পাঠানোর চেষ্টা — ব্যাকআপ হিসেবে
    return send_email_via_php_mail($to_email, $subject, $body_html);
}

/*
 * ================================================================
 * Brevo API দিয়ে ইমেইল পাঠানো — Hostinger-এর ফ্রি সাবডোমেইনে
 * PHP mail()-এর দুর্বল ডেলিভারেবিলিটির বিকল্প হিসেবে
 * ================================================================
 */
function send_email_via_brevo($to_email, $subject, $body_html) {
    global $shop;
    $from_name = $shop['shop_name'] ?? 'System';

    $payload = json_encode([
        'sender' => ['name' => $from_name, 'email' => BREVO_SENDER_EMAIL],
        'to' => [['email' => $to_email]],
        'subject' => $subject,
        'htmlContent' => $body_html,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        error_log("[Mail/Brevo] সফলভাবে পাঠানো হয়েছে — প্রাপক: {$to_email}");
        return true;
    }

    error_log("[Mail/Brevo] ব্যর্থ হয়েছে — প্রাপক: {$to_email} — HTTP কোড: {$http_code} — সার্ভার উত্তর: {$response} — cURL এরর: {$curl_error}");
    return false;
}

/* পুরনো পদ্ধতি — শুধু ব্যাকআপ হিসেবে রাখা হলো (Brevo key না থাকলে) */
function send_email_via_php_mail($to_email, $subject, $body_html) {
    global $shop;

    $from_name = $shop['shop_name'] ?? 'System';
    $trusted_host = defined('TRUSTED_SITE_HOSTS') ? TRUSTED_SITE_HOSTS[0] : preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $from_email = 'no-reply@' . $trusted_host;

    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encoded_from_name = '=?UTF-8?B?' . base64_encode($from_name) . '?=';

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$encoded_from_name} <{$from_email}>\r\n";
    $headers .= "Reply-To: {$from_email}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $sent = mail($to_email, $encoded_subject, $body_html, $headers);

    if ($sent) {
        error_log("[Mail/PHP] সফলভাবে পাঠানো হয়েছে (PHP mail() থেকে true পাওয়া গেছে) — প্রাপক: {$to_email}");
    } else {
        $last_error = error_get_last();
        $reason = $last_error['message'] ?? 'অজানা কারণ (mail() ফাংশন false রিটার্ন করেছে)';
        error_log("[Mail/PHP] ব্যর্থ হয়েছে — প্রাপক: {$to_email} — কারণ: {$reason}");
    }

    return $sent;
}

function generate_reset_token($db, $user_id) {
    // ৩০ মিনিটের মেয়াদে টোকেন — নিরাপত্তার জন্য শুধু hash সংরক্ষণ হয়, আসল টোকেন কখনো ডাটাবেজে যায় না
    $raw_token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $raw_token);
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    // পুরনো, ব্যবহার-না-হওয়া টোকেন থাকলে বাতিল করা (একসাথে একাধিক সক্রিয় টোকেন এড়াতে)
    $cleanup = $db->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
    $cleanup->bind_param('i', $user_id);
    $cleanup->execute();

    $stmt = $db->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $user_id, $token_hash, $expires_at);
    $stmt->execute();

    return $raw_token;
}

function verify_reset_token($db, $raw_token, $lock_for_update = false) {
    $token_hash = hash('sha256', $raw_token);
    $lock_clause = $lock_for_update ? ' FOR UPDATE' : '';
    $stmt = $db->prepare(
        "SELECT pr.*, u.username FROM password_resets pr
         JOIN users u ON pr.user_id = u.id
         WHERE pr.token_hash = ? AND pr.used = 0 AND pr.expires_at > NOW()
         LIMIT 1" . $lock_clause
    );
    $stmt->bind_param('s', $token_hash);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/*
 * ডাটাবেজ-ভিত্তিক রেট-লিমিট — সেশন/কুকি মুছে বা ইনকগনিটো ব্যবহার করে
 * বাইপাস করা যায় না (সেশন-ভিত্তিক রেট-লিমিটের বিপরীতে)।
 * একই ইউজারনেমের জন্য নির্দিষ্ট সময়ে সর্বোচ্চ কতবার রিসেট-অনুরোধ করা
 * যাবে তা এখানে ডাটাবেজ থেকেই গোনা হয়।
 */
function too_many_reset_attempts($db, $user_id, $max_attempts = 3, $window_minutes = 15) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) c FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
    );
    $stmt->bind_param('ii', $user_id, $window_minutes);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    return $count >= $max_attempts;
}
