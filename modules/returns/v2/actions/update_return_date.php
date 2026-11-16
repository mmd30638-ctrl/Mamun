<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/return_v2_functions.php';
ensure_return_v2_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register.php');
    exit;
}
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$new_date = trim($_POST['return_date'] ?? '');

if ($id <= 0) {
    header('Location: ../register.php');
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
    $_SESSION['error'] = 'সঠিক তারিখ দিন।';
    header('Location: ../view.php?id=' . $id);
    exit;
}
// অযৌক্তিকভাবে ভবিষ্যতের তারিখ ঠেকানো (সাধারণ ভুল-টাইপ প্রতিরোধ)
if (strtotime($new_date) > strtotime('+1 day')) {
    $_SESSION['error'] = 'ফেরতের তারিখ ভবিষ্যতের হতে পারবে না।';
    header('Location: ../view.php?id=' . $id);
    exit;
}

try {
    $check = $db->prepare("SELECT return_no, return_date FROM sale_returns_v2 WHERE id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();

    if (!$row) {
        $_SESSION['error'] = 'রিটার্ন খুঁজে পাওয়া যায়নি।';
        header('Location: ../register.php');
        exit;
    }

    $old_date = $row['return_date'];

    $stmt = $db->prepare("UPDATE sale_returns_v2 SET return_date = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $new_date, $id);
    $stmt->execute();

    log_activity('Return Date Updated', 'Returns', $row['return_no'] . ' — ' . $old_date . ' → ' . $new_date);

    $_SESSION['success'] = 'ফেরতের তারিখ আপডেট করা হয়েছে।';
} catch (Throwable $e) {
    error_log('Return date update failed: ' . $e->getMessage());
    $_SESSION['error'] = 'তারিখ আপডেট করতে সমস্যা হয়েছে।';
}

header('Location: ../view.php?id=' . $id);
exit;
