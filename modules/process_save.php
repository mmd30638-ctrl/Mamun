<?php
require_once '../../includes/config.php';
require_once '../../includes/supplier_functions.php';
require_once '../../includes/supplier_settlement.php';
require_once '../../includes/supplier_module_guard.php';

require_post();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);

try {
    if ($id <= 0 && !isset($_POST['opening_balance']) && isset($_POST['previous_balance'])) {
        $_POST['opening_balance'] = $_POST['previous_balance'];
    }
    require_once '../../includes/purchase_functions.php';
    $new_id = ame_supplier_save($db, $_POST, $id);
    ame_spay_save_party_meta($db, $new_id, $_POST);
    if (function_exists('log_activity')) {
        log_activity($id > 0 ? 'Supplier Updated' : 'Supplier Added', 'Suppliers', trim((string)($_POST['name'] ?? '')));
    }

    if (isset($_POST['previous_balance'])) {
        $prev = (float)str_replace(',', '', (string)$_POST['previous_balance']);
        $reason = trim((string)($_POST['due_adjust_reason'] ?? ''));
        if ($reason === '') {
            $reason = $id > 0 ? 'প্রোফাইল থেকে পূর্বের ব্যালেন্স এডিট' : 'নতুন সাপ্লায়ার যোগ';
        }
        ame_supplier_set_previous_balance($db, $new_id, $prev, $reason);
    }

    // "সাবেক/বর্তমান পাওনা" সংশোধন — শুধু বিদ্যমান সাপ্লায়ারে, এবং শুধু
    // ব্যবহারকারী স্পষ্টভাবে "নিশ্চিত করুন" টিক দিলেই (ভুলবশত ফোন/ঠিকানা
    // বদলাতে গিয়ে বকেয়া বদলে যাওয়া ঠেকাতে)
    if ($id > 0 && ($_POST['adjust_due_confirm'] ?? '') === '1') {
        ame_supplier_module_admin_write_boot($db);
        $new_due = (float)str_replace(',', '', (string)($_POST['adjusted_total_due'] ?? '0'));
        $reason = trim((string)($_POST['due_adjust_reason'] ?? ''));
        ame_supplier_adjust_due($db, $new_id, $new_due, $reason);
        if (function_exists('ame_purchase_sync_linked_customer_due')) {
            ame_purchase_sync_linked_customer_due($db, $new_id, 0, $new_due);
        }
    }

    redirect('profile.php?id=' . $new_id . '&saved=1');
} catch (Throwable $e) {
    error_log('Supplier save failed: ' . $e->getMessage());
    $back_id = $id > 0 ? $id : 0;
    redirect('profile.php?id=' . $back_id . '&err=' . rawurlencode($e->getMessage()));
}
