<?php
/**
 * ডাটাবেজ ব্যাকআপ/ডাম্প তৈরির কেন্দ্রীয় ফাংশন
 * -------------------------------------------------------------
 * $write একটা callback — যেভাবে আউটপুট পাঠাতে হবে তা কলার ঠিক করে:
 * ব্রাউজারে সরাসরি ডাউনলোডের জন্য echo, অথবা ফাইলে লেখার জন্য fwrite।
 * এভাবে backup.php ও pre-restore সেফটি-স্ন্যাপশট — দুটোই একই,
 * পরীক্ষিত মেমোরি-নিরাপদ (unbuffered) লজিক ব্যবহার করে, কোড ডুপ্লিকেট হয় না।
 */
function ame_stream_sql_dump(mysqli $db, callable $write) {
    $tables = array();
    $result = $db->query('SHOW TABLES');
    while ($row = $result->fetch_row()) { $tables[] = $row[0]; }

    $write("-- Database Dump — " . date('Y-m-d H:i:s') . "\n\n");
    $write("SET FOREIGN_KEY_CHECKS=0;\n");

    foreach ($tables as $table) {
        $row2 = $db->query('SHOW CREATE TABLE `' . $table . '`')->fetch_row();
        $write("\n\nDROP TABLE IF EXISTS `$table`;\n");
        $write($row2[1] . ";\n\n");

        $db->real_query('SELECT * FROM `' . $table . '`');
        $unbuffered = $db->use_result();
        if ($unbuffered) {
            $field_count = $unbuffered->field_count;
            while ($row = $unbuffered->fetch_row()) {
                $values = array();
                for ($j = 0; $j < $field_count; $j++) {
                    if ($row[$j] === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $db->real_escape_string((string)$row[$j]) . "'";
                    }
                }
                $write("INSERT INTO `$table` VALUES(" . implode(',', $values) . ");\n");
            }
            $unbuffered->free();
        }
    }

    $write("\nSET FOREIGN_KEY_CHECKS=1;\n");
    return count($tables);
}

/**
 * কোড/ডাটা আলাদা রাখার লাইভ স্ট্যাটাস — হেলথ ও প্রজেক্ট-রক্ষা পেজে।
 */
function ame_file_guard_status(): array
{
    $web = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)), '/');
    $outside = dirname($web);
    $env_web = $web . '/.env';
    $env_out = $outside . '/.env';
    if (function_exists('ame_backup_storage_dir')) {
        $backup_dir = ame_backup_storage_dir();
    } else {
        $backup_dir = $outside . '/db_backups';
    }
    $latest = null;
    $latest_age = null;
    if (function_exists('ame_backup_latest_any')) {
        $hit = ame_backup_latest_any();
        if ($hit) {
            $latest = ['name' => $hit['file'], 'mtime' => (int)$hit['time'], 'bytes' => (int)$hit['bytes']];
            $latest_age = max(0, time() - (int)$hit['time']);
        }
    } elseif (is_dir($backup_dir)) {
        $files = glob($backup_dir . '/backup_*.sql*') ?: [];
        foreach ($files as $f) {
            $mt = (int)@filemtime($f);
            if ($latest === null || $mt > $latest['mtime']) {
                $latest = ['name' => basename($f), 'mtime' => $mt, 'bytes' => (int)@filesize($f)];
            }
        }
        if ($latest) {
            $latest_age = max(0, time() - $latest['mtime']);
        }
    }
    $uploads = $web . '/uploads';

    return [
        'env_outside' => is_readable($env_out),
        'env_in_webroot' => is_file($env_web),
        'backup_dir' => $backup_dir,
        'backup_dir_ok' => is_dir($backup_dir) && is_writable($backup_dir),
        'latest_backup' => $latest,
        'latest_backup_age' => $latest_age,
        'uploads_writable' => is_dir($uploads) && is_writable($uploads),
        'app_version' => defined('AME_APP_VERSION') ? AME_APP_VERSION : '',
    ];
}
