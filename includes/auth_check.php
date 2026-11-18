<?php
// এই ফাইলটি প্রতিটি মডিউলের শুরুতে ইনক্লুড করা হবে
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // যদি লগইন না থাকে, তবে লগইন পেজে পাঠিয়ে দেবে
    header("Location: /login.php"); 
    exit();
}
?>