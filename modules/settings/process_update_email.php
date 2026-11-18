<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
ensure_user_schema($db);

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit();
}

$session_token = $_SESSION['csrf_token'] ?? '';
$posted_token = $_POST['csrf_token'] ?? '';
if (empty($session_token) || !hash_equals($session_token, $posted_token)) {
    die("<script>alert('নিরাপত্তা যাচাই ব্যর্থ হয়েছে।'); window.location='profile.php';</script>");
}

$email = trim($_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("<script>alert('সঠিক ইমেইল ঠিকানা দিন।'); window.location='profile.php';</script>");
}

$user_id = (int)$_SESSION['user_id'];

$stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
$stmt->bind_param('si', $email, $user_id);
$stmt->execute();

log_activity('Email Updated', 'Profile', 'নিজের ইমেইল আপডেট করেছেন');

echo "<script>alert('ইমেইল সফলভাবে সংরক্ষণ করা হয়েছে।'); window.location='profile.php';</script>";
