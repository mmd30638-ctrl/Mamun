<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/return_functions.php';
require_once '../../includes/return_validation_functions.php';

require_post();
csrf_verify();
ensure_return_schema($db);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $return_type = $_POST['return_type'] ?? 'with_invoice';
    $acc_id = (int)($_POST['account_id'] ?? 0);
    $reason = clean_input($_POST['reason'] ?? '');
    $return_token = substr(trim((string)($_POST['idempotency_token'] ?? '')), 0, 80);

    if ($acc_id <= 0) {
        die('❌ অ্যাকাউন্ট নির্বাচন করুন। <a href="add.php">ফিরে যান</a>');
    }

    $db->begin_transaction();
    try {
        $return_replay = 0;
        if ($return_token !== ''
            && !claim_idempotency_token($db, 'legacy_sale_return', $return_token, $return_replay)) {
            $db->rollback();
            header('Location: history.php');
            exit();
        }

        if ($return_type === 'exchange') {
            // 🔁 এক্সচেঞ্জ প্রসেসিং
            $old_product_id = (int)($_POST['old_product_id'] ?? 0);
            $old_qty = (float)($_POST['old_qty'] ?? 0);
            $new_product_id = (int)($_POST['new_product_id'] ?? 0);
            $new_qty = (float)($_POST['new_qty'] ?? 0);
            $price_difference = (float)($_POST['price_difference'] ?? 0);
            $customer_name = clean_input($_POST['customer_name_exchange'] ?? '');

            if ($old_product_id <= 0 || $old_qty <= 0 || $new_product_id <= 0 || $new_qty <= 0) {
                die('❌ পুরাতন ও নতুন পণ্য, দুটোই সঠিকভাবে নির্বাচন করুন। <a href="add.php">ফিরে যান</a>');
            }

            $stmt = $db->prepare("INSERT INTO sale_returns (product_id, qty, exchange_product_id, exchange_qty, price_difference, return_type, customer_name, reason, account_id) VALUES (?, ?, ?, ?, ?, 'exchange', ?, ?, ?)");
            $stmt->bind_param("ididdssi", $old_product_id, $old_qty, $new_product_id, $new_qty, $price_difference, $customer_name, $reason, $acc_id);
            $stmt->execute();
            $return_id = $db->insert_id;

            // ⭐ স্টক সমন্বয়: পুরাতন পণ্য ফেরত (+), নতুন পণ্য বের হলো (-)
            $upd1 = $db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $upd1->bind_param("di", $old_qty, $old_product_id);
            $upd1->execute();

            $upd2 = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $upd2->bind_param("di", $new_qty, $new_product_id);
            $upd2->execute();

            // ⭐ মূল্য পার্থক্য অনুযায়ী টাকা লেনদেন
            if ($price_difference > 0) {
                // কাস্টমার আরও টাকা দিয়েছে
                account_credit($acc_id, $price_difference, 'exchange', $return_id, "এক্সচেঞ্জ — বাড়তি গ্রহণ (#$return_id)");
            } elseif ($price_difference < 0) {
                // দোকান টাকা ফেরত দিলো
                account_debit($acc_id, abs($price_difference), 'exchange', $return_id, "এক্সচেঞ্জ — ফেরত প্রদান (#$return_id)", true);
            }

            if ($return_token !== '') {
                finish_idempotency_token($db, 'legacy_sale_return', $return_token, (int)$return_id);
            }
            $db->commit();
            echo "<script>alert('এক্সচেঞ্জ সফল হয়েছে এবং স্টক আপডেট হয়েছে।'); window.location='history.php';</script>";
            exit();
        }

        // 🧾 ইনভয়েসসহ / 📦 ইনভয়েস ছাড়া (আগের লজিক)
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            die('❌ সঠিক পরিমাণ দিন। <a href="add.php">ফিরে যান</a>');
        }

        $sale_id = null;
        $product_id = null;
        $qty = null;
        $customer_name = null;

        if ($return_type === 'with_invoice') {
            $sale_id = (int)($_POST['sale_id'] ?? 0);
            $product_id = (int)($_POST['product_id'] ?? 0);
            $qty = (float)($_POST['qty'] ?? 0);
            if ($sale_id <= 0 || $product_id <= 0 || $qty <= 0) {
                die('❌ ইনভয়েস নম্বর, পণ্য ও পরিমাণ সঠিকভাবে দিন। <a href="add.php">ফিরে যান</a>');
            }
            if (function_exists('ame_lock')
                && !ame_lock($db, 'legacy_return_' . $sale_id . '_' . $product_id)) {
                throw new RuntimeException('এই পণ্যের আরেকটি রিটার্ন চলছে। কয়েক সেকেন্ড পরে চেষ্টা করুন।');
            }
            $is_english = (
    ($_SESSION['app_lang'] ?? 'bn') === 'en'
);

validate_invoice_return_quantity(
    $db,
    $sale_id,
    $product_id,
    $qty,
    $is_english
);
        } else {
            $product_id = (int)($_POST['product_id_manual'] ?? 0);
            $qty = (float)($_POST['qty_manual'] ?? 0);
            $customer_name = clean_input($_POST['customer_name'] ?? '');
            if ($product_id <= 0 || $qty <= 0) {
                die('❌ পণ্য ও পরিমাণ সঠিকভাবে দিন। <a href="add.php">ফিরে যান</a>');
            }
        }

        $stmt = $db->prepare("INSERT INTO sale_returns (sale_id, total_return_amount, account_id, product_id, qty, return_type, customer_name, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idiidsss", $sale_id, $amount, $acc_id, $product_id, $qty, $return_type, $customer_name, $reason);
        $stmt->execute();
        $return_id = $db->insert_id;

        $note = ($return_type === 'with_invoice') ? "বিক্রয় ফেরত (Invoice #$sale_id)" : "বিক্রয় ফেরত (ইনভয়েস ছাড়া)";
        account_debit($acc_id, $amount, 'sale_return', $return_id, $note, true);

        $upd = $db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $upd->bind_param("di", $qty, $product_id);
        $upd->execute();

        if ($return_token !== '') {
            finish_idempotency_token($db, 'legacy_sale_return', $return_token, (int)$return_id);
        }
        $db->commit();
        echo "<script>alert('পণ্য ফেরত সফল হয়েছে এবং স্টক আপডেট হয়েছে।'); window.location='history.php';</script>";
    } catch (Throwable $e) {
        $db->rollback();
        echo "❌ এরর: " . htmlspecialchars($e->getMessage());
    }
} else {
    header("Location: add.php");
    exit();
}
?>