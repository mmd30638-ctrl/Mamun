<?php
require_once '../../includes/config.php';
require_once '../../includes/backup_functions.php';
admin_only();
csrf_verify();

if (ame_is_production() && getenv('ALLOW_WEB_DB_RESTORE') !== '1') {
    http_response_code(403);
    die('Production web database restore is disabled. Use an authenticated hosting or database administration channel.');
}

function restore_fail($msg) {
    header("Location: restore.php?error=" . urlencode($msg));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    restore_fail('সরাসরি এই পেজ খোলা যাবে না।');
}

if (empty($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    $err_map = [
        UPLOAD_ERR_INI_SIZE => 'ফাইলটি সার্ভারের অনুমোদিত সর্বোচ্চ সাইজের চেয়ে বড়।',
        UPLOAD_ERR_FORM_SIZE => 'ফাইলটি অনুমোদিত সাইজের চেয়ে বড়।',
        UPLOAD_ERR_PARTIAL => 'ফাইলটি সম্পূর্ণ আপলোড হয়নি, আবার চেষ্টা করুন।',
        UPLOAD_ERR_NO_FILE => 'কোনো ফাইল বাছাই করা হয়নি।',
    ];
    $code = $_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    restore_fail($err_map[$code] ?? 'ফাইল আপলোডে সমস্যা হয়েছে।');
}

$tmp_path = $_FILES['backup_file']['tmp_name'];
$orig_name = $_FILES['backup_file']['name'];
$ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

if ($ext !== 'sql') {
    restore_fail('শুধুমাত্র .sql ফাইল আপলোড করা যাবে।');
}

// ৩০MB পর্যন্ত (এর বেশি হলে chunked/CLI-ভিত্তিক restore দরকার হবে, যেটা
// শেয়ার্ড হোস্টিং থেকে ব্রাউজার-আপলোডে বাস্তবসম্মত না)
if ($_FILES['backup_file']['size'] > 30 * 1024 * 1024) {
    restore_fail('ফাইলটি ৩০MB-এর চেয়ে বড় — এত বড় ফাইল ব্রাউজার থেকে রিস্টোর করা নিরাপদ না। অনুগ্রহ করে phpMyAdmin থেকে সরাসরি Import করুন।');
}

$sql = file_get_contents($tmp_path);
if ($sql === false || trim($sql) === '') {
    restore_fail('ফাইলটি পড়া যায়নি বা খালি।');
}

// সাধারণ স্যানিটি-চেক: এটা আসলেই একটা SQL ডাম্প কিনা
if (stripos($sql, 'CREATE TABLE') === false && stripos($sql, 'INSERT INTO') === false) {
    restore_fail('ফাইলটি বৈধ ডাটাবেজ ব্যাকআপ বলে মনে হচ্ছে না।');
}

// রিস্টোরের আগে বর্তমান ডাটার একটা সেফটি-স্ন্যাপশট (নিজের সার্ভারেই ফাইল
// হিসেবে সেভ হয়, ডাউনলোড হয় না) — DROP/CREATE TABLE MySQL-এ রোলব্যাক করা
// যায় না বলে, রিস্টোর মাঝপথে ব্যর্থ হলে এই ফাইল থেকে ম্যানুয়ালি phpMyAdmin
// দিয়ে আগের অবস্থা ফিরিয়ে আনা সম্ভব হবে
$pre_restore_dir = AME_PRIVATE_DATA_DIR . '/pre_restore_snapshots';
if (!is_dir($pre_restore_dir)) {
    @mkdir($pre_restore_dir, 0700, true);
}
$snapshot_path = $pre_restore_dir . '/pre_restore_' . date('Y-m-d_H-i-s') . '.sql';
$snapshot_handle = @fopen($snapshot_path, 'w');
if ($snapshot_handle) {
    @chmod($snapshot_path, 0600);
    try {
        ame_stream_sql_dump($db, function ($chunk) use ($snapshot_handle) {
            fwrite($snapshot_handle, $chunk);
        });
    } catch (Throwable $e) {
        error_log('Pre-restore snapshot failed: ' . $e->getMessage());
    } finally {
        fclose($snapshot_handle);
    }
} else {
    error_log('Pre-restore snapshot: could not open file for writing at ' . $snapshot_path);
}

// লক্ষ্য করুন: begin_transaction()/rollback() শুধু বিশুদ্ধ ডাটা-সংক্রান্ত (INSERT)
// ব্যর্থতার ক্ষেত্রে কাজ করবে। DROP/CREATE TABLE-এর মতো স্ট্রাকচার-পরিবর্তন
// MySQL-এ implicit commit ঘটায় বলে সেগুলো ট্রানজেকশনে রোলব্যাক হয় না —
// এটা মাথায় রেখেই রিস্টোরের আগে ব্যাকআপ নেওয়ার পরামর্শ পেজে দেওয়া আছে।
$db->begin_transaction();
$had_error = false;
$error_detail = '';

try {
    if (!$db->multi_query($sql)) {
        throw new Exception($db->error);
    }
    // multi_query-এর সবগুলো স্টেটমেন্ট শেষ পর্যন্ত প্রসেস করা আবশ্যক,
    // নাহলে কানেকশন "sync" অবস্থায় আটকে যায় এবং পরের কুয়েরি ব্যর্থ হয়
    do {
        if ($result = $db->store_result()) {
            $result->free();
        }
        if ($db->more_results()) {
            if (!$db->next_result()) {
                throw new Exception($db->error);
            }
        } else {
            break;
        }
    } while (true);

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    $had_error = true;
    $error_detail = $e->getMessage();
    error_log('Restore failed: ' . $error_detail);
}

if ($had_error) {
    // সততার সাথে বলা দরকার: DROP/CREATE TABLE-এর মতো স্ট্রাকচার-পরিবর্তনকারী
    // স্টেটমেন্ট MySQL-এ নিজে থেকেই স্থায়ীভাবে সেভ হয়ে যায় (implicit commit) —
    // ট্রানজেকশন/রোলব্যাক দিয়ে সেগুলো ফেরানো সম্ভব না। তাই মাঝপথে ব্যর্থ হলে
    // ডাটাবেজ আংশিকভাবে পরিবর্তিত অবস্থায় থাকতে পারে — এই কারণেই রিস্টোরের
    // আগে বর্তমান ডাটার একটা তাজা ব্যাকআপ রাখার পরামর্শ পেজেই দেওয়া হয়েছে।
    restore_fail('রিস্টোর মাঝপথে ব্যর্থ হয়েছে। সতর্কতা: টেবিল-গঠন পরিবর্তনের অংশ (যদি ততক্ষণে চলে থাকে) MySQL-এ স্বয়ংক্রিয়ভাবে সেভ হয়ে যায়, তাই ডাটাবেজ আংশিক পরিবর্তিত হয়ে থাকতে পারে। আপনার আগের ডাটার একটি কপি webroot-এর বাইরের private_data/pre_restore_snapshots ফোল্ডারে সেভ করা হয়েছে — প্রয়োজনে সেখান থেকে phpMyAdmin দিয়ে Import করে ফিরিয়ে আনতে পারবেন। কারণ: ' . $error_detail);
}

log_activity('database_restore', 'settings', 'ব্যাকআপ ফাইল থেকে সম্পূর্ণ ডাটাবেজ রিস্টোর করা হয়েছে (' . htmlspecialchars($orig_name) . ')');

header("Location: tools.php?restored=1");
exit();
