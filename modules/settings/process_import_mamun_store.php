<?php
@ini_set('upload_max_filesize', '64M');
@ini_set('post_max_size', '70M');
@ini_set('max_execution_time', '25');
@ini_set('memory_limit', '256M');
@ignore_user_abort(true);
@set_time_limit(25);

require_once '../../includes/config.php';
require_once '../../includes/mamun_store_migrate.php';
require_once '../../includes/ma_engineering_migrate.php';
admin_only();
require_env_flag('AME_ENABLE_MIGRATION_TOOLS');
csrf_verify();

function msi_fail(string $msg): void
{
    header('Location: import_mamun_store.php?error=' . urlencode($msg));
    exit();
}

function msi_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    msi_fail('সরাসরি এই পেজ খোলা যাবে না।');
}

$options = [
    'import_settings' => !empty($_POST['import_settings']),
    'import_master' => !empty($_POST['import_master']),
    'import_transactions' => !empty($_POST['import_transactions']),
    'skip_existing' => !empty($_POST['skip_existing']),
    'use_existing_tables' => !empty($_POST['use_existing_tables']),
];

$ajax = !empty($_POST['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string)$_SERVER['HTTP_ACCEPT'], 'application/json'));

if ($ajax) {
    $action = (string)($_POST['action'] ?? 'step');

    try {
        if ($action === 'status') {
            $job = mae_migration_find_latest_open_job();
            if (!$job) {
                msi_json(['ok' => true, 'has_job' => false]);
            }
            msi_json([
                'ok' => true,
                'has_job' => true,
                'job' => mae_migration_job_response($job, 'অসম্পূর্ণ ইমপোর্ট পাওয়া গেছে — চালিয়ে যেতে পারবেন।'),
            ]);
        }

        if ($action === 'resume') {
            $job = mae_migration_resolve_job($_POST['job_id'] ?? null);
            if (!$job) {
                msi_json(['ok' => false, 'error' => 'চালিয়ে যাওয়ার মতো কোনো ইমপোর্ট পাওয়া যায়নি। নতুন করে শুরু করুন।'], 404);
            }
            msi_json([
                'ok' => true,
                'job' => mae_migration_job_response($job, 'ইমপোর্ট আবার চালু হয়েছে।'),
            ]);
        }

        if ($action === 'cancel') {
            $job = mae_migration_resolve_job($_POST['job_id'] ?? null);
            if ($job && !empty($job['id'])) {
                mae_migration_delete_job((string)$job['id']);
            }
            msi_json(['ok' => true, 'message' => 'অসম্পূর্ণ ইমপোর্ট বাতিল করা হয়েছে।']);
        }

        if ($action === 'gap19' || $action === 'fixall' || $action === 'balances') {
            if ($action === 'balances') {
                if (!function_exists('mae_apply_balances_stock')) {
                    msi_json(['ok' => false, 'error' => 'ব্যালেন্স মিলানো চালু নেই।'], 400);
                }
                $result = mae_apply_balances_stock($db);
            } elseif ($action === 'fixall' && function_exists('mae_apply_dump_fixall')) {
                $result = mae_apply_dump_fixall($db);
            } else {
                if (!function_exists('mae_apply_recent_gap')) {
                    msi_json(['ok' => false, 'error' => 'গ্যাপ ইমপোর্ট চালু নেই।'], 400);
                }
                $result = mae_apply_recent_gap($db);
                if (!empty($result['ok']) && function_exists('mae_apply_balances_stock') && mae_balances_available()) {
                    $bal = mae_apply_balances_stock($db);
                    $result['message'] = trim(($result['message'] ?? '') . ' ' . ($bal['message'] ?? ''));
                    $result['stats']['balances'] = $bal['stats'] ?? [];
                    if (empty($bal['ok'])) {
                        $result = $bal;
                    }
                }
            }
            if (!empty($result['ok'])) {
                $_SESSION['mamun_import_stats'] = $result['stats'];
            }
            msi_json($result + ['done' => !empty($result['ok'])]);
        }

        if ($action === 'start') {
            $forceNew = !empty($_POST['force_new']) || (function_exists('mae_shop_needs_sep19') && mae_shop_needs_sep19($db));
            $existing = $forceNew ? null : mae_migration_find_latest_open_job();
            if ($existing) {
                msi_json(mae_migration_job_response($existing, 'আগের ইমপোর্ট চালিয়ে যাচ্ছি…'));
            }

            if (!empty($options['use_existing_tables'])) {
                if (!mae_legacy_exists($db)) {
                    msi_json(['ok' => false, 'error' => 'পুরনো POS টেবিল পাওয়া যায়নি।'], 400);
                }
                $job = mae_migration_new_job($options);
                $job['options']['use_existing_tables'] = true;
                $job['staging'] = true;
                $job['phase'] = 'init';
                mae_migration_save_job($job);
                msi_json(mae_migration_job_response($job, 'মাইগ্রেশন শুরু'));
            }

            $path = '';
            $owned = false;
            $serverName = trim((string)($_POST['server_sql'] ?? ''));
            if ($serverName === '' && empty($_FILES['sql_file']['tmp_name'])) {
                $best = mae_pick_best_sql();
                if ($best && !empty($best['path'])) {
                    $path = (string) $best['path'];
                }
            }
            if ($path === '' && $serverName !== '') {
                $resolved = mae_resolve_server_sql($serverName);
                if ($resolved === null || !is_file($resolved)) {
                    msi_json(['ok' => false, 'error' => 'সার্ভারে সেই SQL ফাইল পাওয়া যায়নি।'], 400);
                }
                $path = $resolved;
            } elseif ($path === '') {
                if (empty($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
                    msi_json(['ok' => false, 'error' => 'SQL ফাইল পাইনি। public_html-এ .sql রেখে /import.php খুলুন।'], 400);
                }
                $ext = strtolower(pathinfo((string)$_FILES['sql_file']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'sql') {
                    msi_json(['ok' => false, 'error' => 'শুধুমাত্র .sql ফাইল গ্রহণযোগ্য।'], 400);
                }
                if ($_FILES['sql_file']['size'] > 50 * 1024 * 1024) {
                    msi_json(['ok' => false, 'error' => 'ফাইল ৫০MB-এর বেশি।'], 400);
                }

                $dir = mae_migration_upload_dir();
                $path = $dir . '/mae_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
                if (!move_uploaded_file($_FILES['sql_file']['tmp_name'], $path)) {
                    msi_json(['ok' => false, 'error' => 'ফাইল সেভ করা যায়নি।'], 500);
                }
                $owned = true;
            }
            $head = file_get_contents($path, false, null, 0, 2000000) ?: '';
            if (!mae_is_legacy_dump($head) && !mamun_store_is_dump($head)) {
                if ($owned) {
                    @unlink($path);
                }
                msi_json(['ok' => false, 'error' => 'এই SQL ফাইল চেনা যায়নি।'], 400);
            }
            if (!mae_is_legacy_dump($head)) {
                if ($owned) {
                    @unlink($path);
                }
                msi_json(['ok' => false, 'error' => 'চাঙ্ক মোড শুধু মা ইঞ্জিনিয়ারিং (maengineering) ফাইলের জন্য।'], 400);
            }

            $job = mae_migration_new_job($options);
            $job['sql_path'] = $path;
            $job['sql_owned'] = $owned;
            $job['phase'] = 'init';
            mae_migration_save_job($job);
            msi_json(mae_migration_job_response($job, $owned ? 'ফাইল আপলোড হয়েছে' : 'সার্ভারের SQL নেওয়া হয়েছে'));
        }

        if ($action === 'step') {
            $job = mae_migration_resolve_job($_POST['job_id'] ?? null);
            if (!$job) {
                msi_json([
                    'ok' => false,
                    'error' => 'মাইগ্রেশন সেশন পাওয়া যায়নি। পেজ রিফ্রেশ করলে অটো রিজিউম হবে — অথবা আবার শুরু করুন।',
                    'resumable' => true,
                ], 400);
            }
            $result = mae_migration_step($db, $job);
            if (!empty($result['done'])) {
                $_SESSION['mamun_import_stats'] = $result['stats'];
                if (function_exists('log_activity')) {
                    log_activity('Ma Engineering Import (chunked)', 'Settings', json_encode($result['stats']['staging'] ?? [], JSON_UNESCAPED_UNICODE));
                }
            }
            msi_json($result);
        }

        msi_json(['ok' => false, 'error' => 'অজানা অ্যাকশন'], 400);
    } catch (Throwable $e) {
        error_log('Chunked data import failed: ' . $e->getMessage());
        $job = mae_migration_resolve_job($_POST['job_id'] ?? null);
        if ($job) {
            $job['last_error'] = $e->getMessage();
            mae_migration_save_job($job);
        }
        msi_json([
            'ok' => false,
            'error' => $e->getMessage(),
            'done' => false,
            'job_id' => $job['id'] ?? null,
            'resumable' => (bool) $job,
            'progress' => $job ? mae_migration_progress($job) : null,
            'last_error' => $e->getMessage(),
        ], 500);
    }
}

$from_db = !empty($_POST['use_existing_tables']);

try {
    if ($from_db) {
        if (!mae_legacy_exists($db)) {
            msi_fail('পুরনো POS টেবিল (customer, product, sale) পাওয়া যায়নি। phpMyAdmin-এ পুরো SQL নয় — Settings → Tools-এ ফাইল আপলোড করুন, অথবা শুধু প্রয়োজনীয় টেবিল আলাদা ইমপোর্ট করুন।');
        }
        $options['use_existing_tables'] = true;
        $stats = mae_run_migration($db, $options);
        $label = 'Ma Engineering Import (DB)';
    } else {
        if (empty($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            msi_fail('SQL ফাইল আপলোড হয়নি।');
        }
        $ext = strtolower(pathinfo((string)$_FILES['sql_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            msi_fail('শুধুমাত্র .sql ফাইল গ্রহণযোগ্য।');
        }
        $max = 50 * 1024 * 1024;
        if ($_FILES['sql_file']['size'] > $max) {
            msi_fail('ফাইল ৫০MB-এর বেশি — Hostinger File Manager-এ ফাইল আপলোড করে সার্ভার থেকে চালান, অথবা phpMyAdmin-এ শুধু customer/product/sale টেবিল আলাদা ইমপোর্ট করুন।');
        }
        $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
        if ($sql === false || trim($sql) === '') {
            msi_fail('ফাইল খালি বা পড়া যায়নি।');
        }
        @set_time_limit(0);

        if (mae_is_legacy_dump($sql)) {
            $options['sql'] = $sql;
            $stats = mae_run_migration($db, $options);
            $label = 'Ma Engineering Import (SQL)';
        } elseif (mamun_store_is_dump($sql)) {
            $stats = mamun_store_run_migration($db, $sql, $options);
            $label = 'Mamun Store Import';
        } else {
            msi_fail('এই SQL ফাইল চেনা যায়নি। মা ইঞ্জিনিয়ারিং (maengineering) বা মামু স্টোর (business) ডাম্প দিন।');
        }
    }

    $_SESSION['mamun_import_stats'] = $stats;
    if (function_exists('log_activity')) {
        log_activity($label, 'Settings', json_encode($stats['staging'] ?? [], JSON_UNESCAPED_UNICODE));
    }
    header('Location: import_mamun_store.php?done=1');
    exit();
} catch (Throwable $e) {
    error_log('Data import failed: ' . $e->getMessage());
    msi_fail($e->getMessage());
}
