<?php
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $session_token = $_SESSION['csrf_token'] ?? '';
    $posted_token = $_POST['csrf_token'] ?? '';

    if (empty($session_token) || !hash_equals($session_token, $posted_token)) {
        die("<script>alert('Security Error: মেয়াদের সময় শেষ।'); window.location='login.php';</script>");
    }

    $user = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    $blocked = ame_login_guard($db, $user);
    if ($blocked !== null) {
        echo "<script>alert(" . json_encode($blocked, JSON_UNESCAPED_UNICODE) . "); window.location='login.php';</script>";
        exit();
    }

    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $account_active = $row && (int)($row['status'] ?? 1) === 1;
        $until = $row['locked_until'] ?? null;
        $temporarily_locked = $row && !empty($until) && strtotime((string)$until) > time();
        $account_removed = $row && (trim((string)($row['removed_at'] ?? '')) !== '' || trim((string)($row['purged_at'] ?? '')) !== '');

        // নিরাপত্তা: "removed" / "disabled" / "locked" — প্রতিটার জন্য আলাদা মেসেজ
        // দেখালে বাইরের কেউ ইউজারনেম গেস করে বুঝে ফেলতে পারত কোন ইউজারনেম
        // আসলে সিস্টেমে আছে (user enumeration)। তাই এখন সব ব্যর্থ-লগইন কেসে
        // একই জেনেরিক মেসেজ দেখানো হয় — কারণ ভিতরে ভিতরে লগ/রেকর্ড ঠিকই রাখা হচ্ছে,
        // শুধু ইউজারকে যা বলা হয় সেটা আলাদা করে কিছু ফাঁস করে না।
        $generic_fail = 'ইউজারনেম অথবা পাসওয়ার্ড সঠিক নয়!';

        if ($row && $account_removed) {
            ame_login_record($db, $user, false);
            echo "<script>alert(" . json_encode($generic_fail, JSON_UNESCAPED_UNICODE) . "); window.location='login.php';</script>";
            exit();
        }

        if ($row && !$account_active) {
            ame_login_record($db, $user, false);
            echo "<script>alert(" . json_encode($generic_fail, JSON_UNESCAPED_UNICODE) . "); window.location='login.php';</script>";
            exit();
        }

        if ($temporarily_locked) {
            ame_login_record($db, $user, false);
            echo "<script>alert(" . json_encode($generic_fail, JSON_UNESCAPED_UNICODE) . "); window.location='login.php';</script>";
            exit();
        }

        if ($account_active && password_verify($pass, $row['password'])) {
            ame_login_record($db, $user, true);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['auth_version'] = (int)($row['auth_version'] ?? 0);

            $needs_change = (int)($row['must_change_password'] ?? 0) === 1
                || ame_is_default_password($pass, $row['password']);
            if ($needs_change) {
                $_SESSION['must_change_password'] = 1;
                if ((int)($row['must_change_password'] ?? 0) !== 1) {
                    $flag = $db->prepare('UPDATE users SET must_change_password = 1 WHERE id = ? LIMIT 1');
                    if ($flag) {
                        $uid = (int)$row['id'];
                        $flag->bind_param('i', $uid);
                        $flag->execute();
                        $flag->close();
                    }
                }
            } else {
                unset($_SESSION['must_change_password']);
            }
            try {
                require_once __DIR__ . '/includes/hrm_functions.php';
                ensure_hrm_schema($db);
                $employeeId = ensure_employee_for_user($db, (int)$row['id']);
                if ($employeeId > 0) {
                    $_SESSION['employee_id'] = $employeeId;
                }
            } catch (Throwable $identityError) {
                error_log('Login identity sync failed: ' . $identityError->getMessage());
            }
            if (function_exists('ensure_store_schema')) {
                try {
                    ensure_store_schema($db);
                    $assigned = ame_user_store_ids($db, (int)$row['id']);
                    if (count($assigned) > 0) {
                        $_SESSION['current_store_id'] = (int)$assigned[0];
                    }
                    ame_resolve_current_store($db);
                } catch (Throwable $e) {
                    error_log('Store login bind failed: ' . $e->getMessage());
                }
            }

            if (!empty($_SESSION['must_change_password'])) {
                header('Location: /modules/settings/change_password.php?required=1');
                exit();
            }

            header("Location: index.php");
            exit();
        } else {
            ame_login_record($db, $user, false);
            echo "<script>alert(" . json_encode($generic_fail, JSON_UNESCAPED_UNICODE) . "); window.location='login.php';</script>";
        }

    } catch (Throwable $e) {
        error_log($e->getMessage());
        echo "<script>alert('সার্ভার ত্রুটি। পরে চেষ্টা করুন।'); window.location='login.php';</script>";
    }
} else {
    header("Location: login.php");
    exit();
}
