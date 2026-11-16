<?php
require_once '../../includes/config.php';
require_once '../../includes/invoice_functions.php';

require_post();
csrf_verify();
admin_only();
ensure_invoice_settings_schema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $footer_note = clean_input($_POST['footer_note'] ?? '');
    $paper_width = in_array($_POST['paper_width'] ?? '', ['80mm','58mm','A4']) ? $_POST['paper_width'] : '80mm';
    $accent_color = in_array($_POST['accent_color'] ?? '', ['green','black','red']) ? $_POST['accent_color'] : 'green';
    $show_discount = isset($_POST['show_discount']) ? 1 : 0;
    $show_payment_method = isset($_POST['show_payment_method']) ? 1 : 0;
    $show_address = isset($_POST['show_address']) ? 1 : 0;
    $kept = get_invoice_settings($db);
    $pos_discount_auto = !empty($kept['pos_discount_auto']) ? 1 : 0;
    $pos_discount_percent = $pos_discount_auto === 1 ? 12.0 : round((float)($kept['pos_discount_percent'] ?? 0), 2);

    $stmt = $db->prepare("UPDATE invoice_settings SET footer_note=?, paper_width=?, accent_color=?, show_discount=?, show_payment_method=?, show_address=?, pos_discount_auto=?, pos_discount_percent=? WHERE id=1");
    $stmt->bind_param("sssiiiid", $footer_note, $paper_width, $accent_color, $show_discount, $show_payment_method, $show_address, $pos_discount_auto, $pos_discount_percent);

    if ($stmt->execute()) {
        log_activity("Invoice Template Updated", "Settings", "ইনভয়েস টেমপ্লেট সেটিংস পরিবর্তন করা হলো");
        echo "<script>alert('ইনভয়েস টেমপ্লেট আপডেট হয়েছে'); window.location='invoice_template.php';</script>";
    } else {
        echo "❌ এরর: " . htmlspecialchars($db->error);
    }
} else {
    header("Location: invoice_template.php");
    exit();
}
?>