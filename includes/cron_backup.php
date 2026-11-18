<?php
/**
 * স্বয়ংক্রিয় ডাটাবেজ ব্যাকআপ (Cron Job দিয়ে চালানোর জন্য)
 * =========================================================
 * প্রতি রাতে Hostinger Cron Job থেকে চলবে:
 *  ১. পুরো ডাটাবেজের .sql.gz ব্যাকআপ (chunked — বড় DB-তেও RAM ফুরায় না)
 *  ২. webroot-এর বাইরে সংরক্ষণ (ব্রাউজারে খোলা যায় না)
 *  ৩. ৭ দিনের বেশি পুরনো ব্যাকআপ অটো-ডিলিট (retention)
 *  ৪. lock ফাইল — একসাথে দুই ব্যাকআপ চলবে না, লাইভ স্লো হয় না
 *
 * === Hostinger hPanel-এ Cron Job সেটআপ ===
 * ১. hPanel -> Advanced -> Cron Jobs -> Create a new Cron Job
 * ২. Command (এই সার্ভারের আসল পাথ টুলস পেজে দেখায়):
 *    php /home/USER/domains/DOMAIN/public_html/maintenance.php
 * ৩. Schedule: Once a day (রাত ২টা, 0 2 * * *)
 *
 * নিরাপত্তা: শুধু CLI (cron) থেকে চলবে — ব্রাউজার থেকে ব্লক।
 */

// ব্রাউজার থেকে সরাসরি অ্যাক্সেস বন্ধ — শুধু CLI (cron) থেকে চলবে
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('এই স্ক্রিপ্ট শুধুমাত্র সার্ভার Cron Job থেকে চলার জন্য।');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_backup.php';

if (!defined('AME_PRIVATE_DATA_DIR')) {
    define('AME_PRIVATE_DATA_DIR', dirname(__DIR__, 2) . '/private_data');
}

$backup_dir = ame_backup_storage_dir();

$result = ame_db_backup_run($db, $backup_dir, 7);
if (!$result['ok']) {
    error_log('cron_backup.php: ব্যর্থ — ' . $result['message']);
    echo 'ব্যাকআপ ব্যর্থ: ' . $result['message'] . "\n";
    exit(1);
}

error_log('cron_backup.php: ব্যাকআপ সফল -> ' . basename($result['file']));
echo "ব্যাকআপ সফলভাবে সম্পন্ন হয়েছে: " . basename($result['file']) . "\n";
exit(0);
