<?php
/*
 * পার্টনারশিপ পণ্য ক্যাটেগরি নির্বাচন
 */

require_once '../../../includes/config.php';
require_once '../../../includes/partnership_functions.php';

admin_only();
require_post();
csrf_verify();
ensure_partnership_schema($db);

header('Content-Type: text/plain; charset=UTF-8');

$category_id = (int)($_POST['id'] ?? 0);
$checked = ((string)($_POST['checked'] ?? '0') === '1') ? 1 : 0;

if ($category_id <= 0) {
    http_response_code(422);
    echo 'INVALID_CATEGORY';
    exit();
}

try {
    $stmt = $db->prepare(
        'UPDATE categories
         SET is_partnership_shared = ?
         WHERE id = ?'
    );

    $stmt->bind_param(
        'ii',
        $checked,
        $category_id
    );

    $stmt->execute();
    $stmt->close();

    try {
        require_once '../../../includes/partner_accounting_v2.php';
        pav2_ensure_schema($db);
        if ($checked === 1) {
            pav2_enable_category_half($db, $category_id, date('Y-m-d'));
        } else {
            pav2_stop_category_half($db, $category_id, date('Y-m-d', strtotime('-1 day')));
        }
    } catch (Throwable $syncError) {
        error_log('category 50 sync: ' . $syncError->getMessage());
    }

    echo 'OK';
} catch (Throwable $error) {
    error_log(
        'Partnership category update failed: '
        . $error->getMessage()
    );

    http_response_code(500);
    echo 'UPDATE_FAILED';
}