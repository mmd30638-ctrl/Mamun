<?php
/**
 * "সহজ আপডেট" প্রয়োগের ঠিক আগে স্বয়ংক্রিয় ব্যাকআপ + এক-ক্লিক রোলব্যাক।
 * -------------------------------------------------------------
 * প্রতিটা আপডেট প্রয়োগের আগে —
 *   ১) যেসব ফাইল বদলাতে/নতুন তৈরি হতে যাচ্ছে শুধু তাদের "আগের কপি" একটা zip-এ,
 *   ২) সম্পূর্ণ ডাটাবেজের একটা .sql ডাম্প —
 * webroot-এর বাইরে (public_html-এর একধাপ ওপরে, ব্রাউজার থেকে কখনও অ্যাক্সেসযোগ্য
 * নয়) সংরক্ষণ করা হয়। আপডেটের পর কোনো পেজ ভেঙে গেলে সেটিংস থেকে এক ক্লিকে
 * সবশেষ (বা যেকোনো আগের) স্ন্যাপশটে ফেরত যাওয়া যায় — কোড ও ডাটা দুটোই।
 *
 * শুধু যেসব ফাইল ছোঁয়া হবে তাদেরই ব্যাকআপ নেওয়া হয় (পুরো সাইট নয়), তাই এটা
 * দ্রুত এবং কম জায়গা লাগে।
 */

function ame_update_backups_dir(): string
{
    $web = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)), '/');
    if ($web === '' || !is_dir($web)) {
        $web = dirname(__DIR__);
    }
    return dirname($web) . '/ame_update_backups';
}

/** সবশেষ আগে — প্রতিটা এন্ট্রিতে id, created_at, from_version, file/db তথ্য। */
function ame_update_backup_list(): array
{
    $dir = ame_update_backups_dir();
    if (!is_dir($dir)) {
        return [];
    }
    $out = [];
    foreach (glob($dir . '/*/manifest.json') ?: [] as $manifestFile) {
        $data = json_decode((string)@file_get_contents($manifestFile), true);
        if (!is_array($data)) {
            continue;
        }
        $snapDir = dirname($manifestFile);
        $data['id'] = basename($snapDir);
        $data['bytes'] = 0;
        foreach (glob($snapDir . '/*') ?: [] as $f) {
            if (is_file($f)) {
                $data['bytes'] += (int)@filesize($f);
            }
        }
        $out[] = $data;
    }
    usort($out, function ($a, $b) {
        return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
    });
    return $out;
}

function ame_update_backup_guard_dir(string $dir): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return;
    }
    $guard = $dir . '/.htaccess';
    if (!is_file($guard)) {
        @file_put_contents($guard, "Require all denied\nDeny from all\n");
    }
}

/**
 * আপডেট প্রয়োগের ঠিক আগে কল হয়। $source = নতুন কোডের এক্সট্র্যাক্ট করা ফোল্ডার,
 * $targetRoot = লাইভ public_html। রিটার্ন ['ok'=>bool,'message'=>string,'id'=>?string]
 */
function ame_update_backup_snapshot(?mysqli $db, string $source, string $targetRoot, string $fromVersion): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'message' => 'সার্ভারে ZipArchive নেই — সেফটি ব্যাকআপ ছাড়া আপডেট প্রয়োগ করা যাবে না।'];
    }

    $dir = ame_update_backups_dir();
    ame_update_backup_guard_dir($dir);
    if (!is_dir($dir) || !is_writable($dir)) {
        return ['ok' => false, 'message' => 'ব্যাকআপ ফোল্ডার (' . $dir . ') লেখার অনুমতি নেই — নিরাপত্তার জন্য আপডেট বন্ধ রাখা হলো। হোস্টিং ফাইল ম্যানেজার থেকে অনুমতি ঠিক করুন।'];
    }

    $id = date('Y-m-d_H-i-s') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
    $snapDir = $dir . '/' . $id;
    if (!@mkdir($snapDir, 0750, true)) {
        return ['ok' => false, 'message' => 'স্ন্যাপশট ফোল্ডার তৈরি করা যায়নি — আপডেট বন্ধ রাখা হলো।'];
    }

    // ১) ফাইল স্ন্যাপশট — শুধু যেসব ফাইল এই আপডেটে বদলাবে/তৈরি হবে
    $filesZipPath = $snapDir . '/files.zip';
    $zip = new ZipArchive();
    if ($zip->open($filesZipPath, ZipArchive::CREATE) !== true) {
        ame_easy_update_rrmdir($snapDir);
        return ['ok' => false, 'message' => 'ফাইল ব্যাকআপ zip তৈরি করা যায়নি — আপডেট বন্ধ রাখা হলো।'];
    }
    $manifest = [
        'created_at' => time(),
        'from_version' => $fromVersion,
        'existing' => [],
        'new' => [],
    ];
    ame_update_backup_collect($source, $targetRoot, '', $zip, $manifest);
    ame_update_backup_collect_deletions($source, $targetRoot, $zip, $manifest);
    $zip->close();

    // ২) সম্পূর্ণ ডাটাবেজ ডাম্প (একই পরীক্ষিত ফাংশন যা backup.php/restore.php ব্যবহার করে)
    if ($db instanceof mysqli) {
        if (!function_exists('ame_stream_sql_dump')) {
            ame_easy_update_rrmdir($snapDir);
            return ['ok' => false, 'message' => 'ডাটাবেজ ব্যাকআপ ফাংশন লোড হয়নি।'];
        }
        $dbFile = $snapDir . '/db.sql';
        $fp = @fopen($dbFile, 'w');
        if (!$fp) {
            ame_easy_update_rrmdir($snapDir);
            return ['ok' => false, 'message' => 'ডাটাবেজ ব্যাকআপ ফাইল তৈরি করা যায়নি।'];
        }
        try {
            $tableCount = ame_stream_sql_dump($db, function ($s) use ($fp) {
                if (fwrite($fp, $s) === false) {
                    throw new RuntimeException('ডাটাবেজ ব্যাকআপ লিখতে ব্যর্থ।');
                }
            });
            fclose($fp);
            if ($tableCount < 1 || !is_file($dbFile) || (int)@filesize($dbFile) < 50) {
                throw new RuntimeException('ডাটাবেজ ব্যাকআপ খালি।');
            }
            if (function_exists('gzopen')) {
                $in = @fopen($dbFile, 'rb');
                $gz = @gzopen($dbFile . '.gz', 'w9');
                if ($in && $gz) {
                    while (!feof($in)) {
                        $chunk = fread($in, 1024 * 1024);
                        if ($chunk === false || ($chunk !== '' && gzwrite($gz, $chunk) === false)) {
                            throw new RuntimeException('ডাটাবেজ ব্যাকআপ compress ব্যর্থ।');
                        }
                    }
                    fclose($in);
                    gzclose($gz);
                    @unlink($dbFile);
                    $dbFile .= '.gz';
                } else {
                    if (is_resource($in)) {
                        fclose($in);
                    }
                    if (is_resource($gz)) {
                        gzclose($gz);
                    }
                }
            }
            $manifest['db_file'] = basename($dbFile);
        } catch (Throwable $e) {
            if (is_resource($fp)) {
                @fclose($fp);
            }
            error_log('update backup db dump failed: ' . $e->getMessage());
            ame_easy_update_rrmdir($snapDir);
            return ['ok' => false, 'message' => 'ডাটাবেজ ব্যাকআপ সম্পূর্ণ হয়নি: ' . $e->getMessage()];
        }
    }
    $manifest['files_zip'] = 'files.zip';

    @file_put_contents($snapDir . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // অন্তত পাঁচটি stable checkpoint রাখি, যাতে পরপর একাধিক update-এর পরে
    // সমস্যা ধরা পড়লেও শুধু সর্বশেষ দুটির মধ্যে আটকে যেতে না হয়।
    ame_update_backup_prune($dir, 5);

    return ['ok' => true, 'id' => $id, 'dir' => $snapDir];
}

function ame_update_backup_collect(string $source, string $targetRoot, string $rel, ZipArchive $zip, array &$manifest): void
{
    $sourcePath = rtrim($source, '/') . ($rel !== '' ? '/' . $rel : '');
    $items = @scandir($sourcePath);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $childRel = $rel === '' ? $item : $rel . '/' . $item;
        $childRel = str_replace('\\', '/', $childRel);
        $src = $sourcePath . '/' . $item;

        if (function_exists('ame_easy_update_is_protected') && ame_easy_update_is_protected($childRel)) {
            continue;
        }

        if (is_dir($src)) {
            ame_update_backup_collect($source, $targetRoot, $childRel, $zip, $manifest);
            continue;
        }

        $dest = $targetRoot . '/' . $childRel;
        if (is_file($dest)) {
            $zip->addFile($dest, $childRel);
            $manifest['existing'][] = $childRel;
        } else {
            // এই ফাইলটা আগে ছিল না — রোলব্যাক করলে এটা মুছে দিতে হবে
            $manifest['new'][] = $childRel;
        }
    }
}

function ame_update_backup_collect_deletions(string $source, string $targetRoot, ZipArchive $zip, array &$manifest): void
{
    if (!function_exists('ame_easy_update_delete_manifest_paths')) {
        return;
    }
    foreach (ame_easy_update_delete_manifest_paths($source) as $rel) {
        $dest = rtrim($targetRoot, '/') . '/' . $rel;
        if (!is_file($dest) || in_array($rel, $manifest['existing'], true)) {
            continue;
        }
        $zip->addFile($dest, $rel);
        $manifest['existing'][] = $rel;
    }
}

function ame_update_backup_prune(string $dir, int $keep): void
{
    $entries = glob($dir . '/*', GLOB_ONLYDIR) ?: [];
    usort($entries, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });
    foreach (array_slice($entries, $keep) as $old) {
        ame_easy_update_rrmdir($old);
    }
}

/**
 * রোলব্যাক — নির্দিষ্ট স্ন্যাপশট আইডি থেকে ফাইল, এবং চাইলে ডাটাবেজও, ফিরিয়ে আনে।
 * DB restore-এর SQL-প্রসেসিং process_restore.php-এর পরীক্ষিত পদ্ধতি অনুসরণ করে
 * (multi_query + transaction, একই সতর্কতা: structure পরিবর্তন MySQL-এ
 * implicit commit হয় বলে পুরোপুরি transaction-safe নয়)।
 */
function ame_update_backup_restore(?mysqli $db, string $id, string $targetRoot, bool $restoreDb): array
{
    $id = basename($id);
    $dir = ame_update_backups_dir() . '/' . $id;
    $manifestFile = $dir . '/manifest.json';
    if (!is_file($manifestFile)) {
        return ['ok' => false, 'message' => 'এই ব্যাকআপ খুঁজে পাওয়া যায়নি।'];
    }
    $manifest = json_decode((string)@file_get_contents($manifestFile), true);
    if (!is_array($manifest)) {
        return ['ok' => false, 'message' => 'ব্যাকআপ ফাইল পড়া যায়নি।'];
    }

    // ফাইল ফিরিয়ে আনা
    $filesRestored = 0;
    $zipPath = $dir . '/' . (string)($manifest['files_zip'] ?? 'files.zip');
    if (is_file($zipPath)) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            if (!function_exists('ame_easy_update_zip_entries_safe')
                || !ame_easy_update_zip_entries_safe($zip)) {
                $zip->close();
                return ['ok' => false, 'message' => 'ব্যাকআপ ZIP-এ অবৈধ পাথ বা symlink পাওয়া গেছে।'];
            }
            $filesRestored = $zip->numFiles;
            if (!$zip->extractTo($targetRoot)) {
                $zip->close();
                return ['ok' => false, 'message' => 'ব্যাকআপ ফাইল পুনরুদ্ধার করা যায়নি।'];
            }
            $zip->close();
        }
    }
    // এই আপডেটে নতুন তৈরি হওয়া ফাইল (আগে ছিল না) — মুছে দেওয়া
    foreach ((array)($manifest['new'] ?? []) as $rel) {
        $p = $targetRoot . '/' . ltrim((string)$rel, '/');
        if (is_file($p)) {
            @unlink($p);
        }
    }

    $dbRestored = false;
    $dbError = '';
    if ($restoreDb && $db instanceof mysqli) {
        $dbFileName = (string)($manifest['db_file'] ?? '');
        $dbFile = $dbFileName !== '' ? $dir . '/' . $dbFileName : '';
        if ($dbFile !== '' && is_file($dbFile)) {
            $sql = str_ends_with($dbFile, '.gz')
                ? (function_exists('gzdecode') ? @gzdecode((string)file_get_contents($dbFile)) : false)
                : @file_get_contents($dbFile);
            if (is_string($sql) && trim($sql) !== '') {
                $db->begin_transaction();
                try {
                    if (!$db->multi_query($sql)) {
                        throw new Exception($db->error);
                    }
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
                    $dbRestored = true;
                } catch (Throwable $e) {
                    $db->rollback();
                    $dbError = $e->getMessage();
                    error_log('update backup db restore failed: ' . $dbError);
                }
            }
        }
    }

    // ফাইল ওভাররাইট/মোছার পর OPcache-এ পুরনো কম্পাইল-করা কোড থেকে যেতে পারে —
    // রোলব্যাকের পরও একই কারণে সাথে সাথে রিসেট করা হচ্ছে (দেখুন easy_update_functions.php)
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    $msg = $filesRestored . 'টি ফাইল আগের অবস্থায় ফিরিয়ে আনা হয়েছে।';
    if ($restoreDb) {
        $msg .= $dbRestored
            ? ' ডাটাবেজও এই ব্যাকআপের সময়কার অবস্থায় ফিরিয়ে আনা হয়েছে।'
            : (' ⚠️ ডাটাবেজ ফেরানো যায়নি' . ($dbError !== '' ? (': ' . $dbError) : '') . '।');
    }

    return ['ok' => true, 'message' => $msg, 'db_restored' => $dbRestored];
}
