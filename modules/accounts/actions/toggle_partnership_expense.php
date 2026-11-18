<?php
/*
 * পার্টনারশিপ খরচ ক্যাটেগরি নির্বাচন
 */

require_once '../../../includes/config.php';
require_once '../../../includes/partnership_functions.php';

admin_only();
require_post();
csrf_verify();
ensure_partnership_schema($db);

header('Content-Type: text/plain; charset=UTF-8');

$expense_category_id = (int)($_POST['id'] ?? 0);
$checked = ((string)($_POST['checked'] ?? '0') === '1') ? 1 : 0;

if ($expense_category_id <= 0) {
    http_response_code(422);
    echo 'INVALID_EXPENSE_CATEGORY';
    exit();
}

try {
    $stmt = $db->prepare(
        'UPDATE expense_categories
         SET is_partnership_shared = ?
         WHERE id = ?'
    );

    $stmt->bind_param(
        'ii',
        $checked,
        $expense_category_id
    );

    $stmt->execute();
    $stmt->close();

    echo 'OK';
} catch (Throwable $error) {
    error_log(
        'Partnership expense category update failed: '
        . $error->getMessage()
    );

    http_response_code(500);
    echo 'UPDATE_FAILED';
}