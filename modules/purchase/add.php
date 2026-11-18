<?php
require_once '../../includes/config.php';
require_once '../../includes/purchase_functions.php';
require_once '../../includes/purchase_draft.php';
require_once '../../includes/inventory_functions.php';
require_once '../../includes/lang_helper.php';
admin_only();
ensure_purchase_schema($db);
ame_purchase_draft_schema($db);
ensure_inventory_schema($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$edit_id = (int)($_GET['edit_id'] ?? 0);
if ($edit_id > 0 && ame_purchase_normalize_memo($_GET['edit_memo'] ?? '') === '') {
    $id_stmt = $db->prepare('SELECT id, memo_no, supplier_id FROM purchases WHERE id = ? LIMIT 1');
    if ($id_stmt) {
        $id_stmt->bind_param('i', $edit_id);
        $id_stmt->execute();
        $id_row = $id_stmt->get_result()->fetch_assoc();
        $id_stmt->close();
        if ($id_row) {
            $id_memo = ame_purchase_normalize_memo($id_row['memo_no'] ?? '');
            if ($id_memo === '') {
                $id_memo = 'P' . $edit_id;
                $claim = $db->prepare("UPDATE purchases SET memo_no = ? WHERE id = ? AND (memo_no IS NULL OR memo_no = '')");
                if ($claim) {
                    $claim->bind_param('si', $id_memo, $edit_id);
                    $claim->execute();
                    $claim->close();
                }
            }
            header(
                'Location: add.php?edit_memo=' . rawurlencode($id_memo)
                . '&supplier_id=' . (int)($id_row['supplier_id'] ?? 0)
            );
            exit();
        }
    }
}

$ame_body_class = 'ame-purchase-classic';
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
/* "✅ ক্রয় সম্পন্ন করুন" / "💾 মেমো আপডেট নিশ্চিত করুন" বাটনে ক্লিক করলে
   সাধারণ রিং-স্পিনারের বদলে দোকানের লোগো ঘুরে দেখাবে। */
#amePcCompleteBtn .ame-wait-spin i {
    border: none !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important;
    background-repeat: no-repeat !important;
    background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}

/* 10.98.404 — বিক্রয় পেজের মতো বাম-ঘেঁষা প্রফেশনাল লেআউট। কেন্দ্র-জোর সরানো। */

/* ✏️ পণ্যের পরিমাণের পাশে "−"/"+" বাটন — মোবাইলে নাম্বার বক্সের নিজস্ব
   ছোট্ট তীরচিহ্ন প্রায়ই দেখা যায় না, তাই আলাদা বড় বাটন দেওয়া হলো। চাপার
   সাথে সাথেই (stepQty() ফাংশন) লাইন-টোটাল ও সর্বমোট বদলে যায়। */
.pc-qty-stepper {
    display: flex !important;
    flex-direction: column;
    align-items: stretch;
    justify-content: center;
    gap: 4px;
    min-width: 88px;
}
.pc-qty-stepper .ame-pos-line-input,
.pc-qty-stepper .pc-qty-input {
    text-align: center !important;
    width: 100% !important;
    min-width: 72px !important;
    max-width: none !important;
    min-height: 52px !important;
    padding: 8px 8px !important;
    font-size: 22px !important;
    font-weight: 900 !important;
    color: #111827 !important;
    -webkit-text-fill-color: #111827 !important;
    background: #FFF4CC !important;
    border: 2px solid #003F8F !important;
    border-radius: 10px !important;
}
.pc-qty-btns {
    display: flex;
    gap: 4px;
}
.pc-qty-btn {
    flex: 1 1 auto;
    width: auto;
    height: 32px;
    min-height: 32px;
    border: 1px solid #D7DEE8;
    border-radius: 6px;
    background: #F4F7FB;
    color: #003F8F;
    font-size: 16px;
    font-weight: 800;
    line-height: 1;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.pc-qty-btn:active {
    filter: brightness(0.85);
}

</style>
<?php

$escape = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$is_english = current_lang() === 'en';

$words = $is_english
    ? [
        'memo_no' => 'Memo no.',
        'memo_editable_hint' => 'You can change the memo number if needed — just make sure it doesn\'t match another memo.',
        'optional' => 'Optional',
        'purchase_date' => 'Purchase date',
        'product_search_ph' => 'Product name / SKU / OEM / supplier',
        'supplier_search_ph' => 'Supplier info',
        'add_supplier' => 'Add a new supplier',
        'phone' => 'Phone:',
        'previous' => 'Previous:',
        'recent_purchases' => 'Recent purchases',
        'draft' => 'Draft',
        'complete' => 'Complete',
        'editing_draft' => 'Editing draft:',
        'serial' => 'SL',
        'code' => 'Code',
        'product' => 'Product name',
        'purchase_price' => 'Purchase price',
        'quantity' => 'Qty',
        'dealer' => 'Dealer',
        'wholesale' => 'Wholesale',
        'retail' => 'Retail',
        'total' => 'Total',
        'cancel' => 'Remove',
        'search_add_hint' => 'Search above to add a product',
        'product_total_lines' => 'Product total · 0 line',
        'go_to_payment_complete' => '💳 Open payment',
        'go_to_payment_draft' => '💳 Open payment and save draft',
        'draft_list' => 'Draft list',
        'open_payment' => 'Payment',
        'payment_title' => 'Payment',
        'recent_purchases_complete' => 'Recent purchases · Complete',
        'time' => 'Time',
        'memo' => 'Memo',
        'supplier' => 'Supplier',
        'price' => 'Price',
        'view' => 'View',
        'no_purchase_yet' => 'No purchases yet.',
        'all_history' => '📜 Full history',
        'close' => 'Close',
        'recent_purchases_draft' => 'Recent purchases · Draft',
        'number' => 'Number',
        'edit' => 'Edit',
        'purchase_calc' => '🧮 Purchase calculation',
        'product_total' => 'Product total',
        'extra_expense' => 'Extra expense',
        'invoice_total' => 'Invoice total',
        'account' => 'Account',
        'payment' => 'Payment',
        'ledger_formula' => 'Previous due + this invoice − payment = latest balance',
        'previous_due' => 'Previous due',
        'previous_due_plus' => 'Previous due (+)',
        'previous_due_minus' => 'Previous receivable (−)',
        'total_payable' => 'Total payable (previous + bill)',
        'remaining_balance' => 'Remaining balance',
        'settle_type' => 'Previous due settlement',
        'settle_none' => 'Keep previous unchanged',
        'settle_payment' => 'Pay previous (extra payment)',
        'settle_adjustment' => 'Adjust previous (admin + reason)',
        'settle_reason' => 'Settlement reason',
        'settle_reason_ph' => 'Why previous due is changing',
        'settle_help' => 'Previous due cannot be wiped by editing the number. Pay it or post an adjustment with a reason.',
        'settle_required' => 'Previous due changed. Choose payment or adjustment and write a reason.',
        'pay_history' => 'Payment history',
        'allocate_older' => 'Extra payment will settle older due memos first (FIFO).',
        'this_invoice' => 'This invoice (+)',
        'current' => 'Grand balance',
        'grand_balance' => 'Latest balance',
        'grand_pay' => 'Grand balance',
        'grand_get' => 'Grand balance',
        'expense_type' => 'Expense type',
        'transport' => 'Transport',
        'labor' => 'Labor',
        'loading_unloading' => 'Loading/unloading',
        'packaging' => 'Packaging',
        'other' => 'Other',
        'expense_taka' => 'Expense',
        'add_expense' => '+ Add expense',
        'note' => 'Note',
        'note_ph' => 'Optional note',
        'add_new_product' => '➕ Add new product',
        'back_dashboard' => '🏠 Back to Dashboard',
        'home' => 'Home',
        'supplier_select' => 'Supplier',
        'find_product' => 'Find product',
        'product_list' => 'Product list',
        'supplier_account' => 'Supplier account',
        'total_and_expense' => 'Total and expense',
        'purchase_tools' => 'Purchase tools',
        'complete_purchase' => '✅ Complete purchase',
        'submit' => 'Submit',
        'save_draft' => '💾 Save draft',
        'add_new_product_title' => '➕ Add new product',
        'add_new_product_hint' => 'Create a product not in the list and add it to the cart now',
        'product_name_req' => 'Product name *',
        'purchase_price_req' => 'Purchase price *',
        'retail_req' => 'Retail *',
        'create_and_add' => '✓ Create and add',
        'add_new_supplier_title' => '🚚 Add new supplier',
        'add_new_supplier_hint' => 'Create a supplier not in the list and select it now',
        'name_req' => 'Name *',
        'mobile' => 'Mobile',
        'address' => 'Address',
        'previous_balance' => 'Previous balance (opening due)',
        'previous_balance_hint' => 'How much the shop already owed this supplier',
        'save_previous' => 'Save previous',
        'previous_saved' => 'Previous balance saved.',
        'create_and_select' => '✓ Create and select',
        'pos_title_edit_heading' => 'POS title text',
        'pos_title_edit_hint' => 'Only this text is edited.',
        'save' => 'Save',
        'edit_this_text' => 'Edit this text',
        'server_bad_response' => 'Received an invalid response from the server.',
        'draft_op_failed' => 'Could not complete the draft operation.',
        'add_at_least_one_product' => '⚠️ Add at least one product.',
        'saving_draft' => '⏳ Saving draft...',
        'draft_saved_success' => 'Draft saved successfully.',
        'draft_save_failed' => 'Could not save the draft.',
        'draft_list_loading' => 'Loading draft list...',
        'no_active_draft' => 'No active drafts found.',
        'draft_list_load_failed' => 'Could not load the draft list.',
        'draft_open_failed' => 'Could not open the draft.',
        'confirm_cancel_draft' => 'Do you want to cancel this draft?',
        'draft_cancel_failed' => 'Could not cancel the draft.',
        'no_supplier_found' => 'No supplier found',
        'no_result' => 'No results',
        'search_count' => '%s products',
        'supplier_code_short' => 'Sup',
        'stock_label' => 'Stock',
        'below_purchase_rate_warning' => '⚠️ Below the purchase rate',
        'retail_low_msg' => ' retail price (৳%s) cannot be less than the purchase price (৳%s).',
        'label_dash_prefix' => ' ',
        'price_low_msg' => ' price (৳%s) cannot be less than the purchase price (৳%s).',
        'valid_amount' => 'Enter a valid amount.',
        'memo_empty_hint' => 'Leave it empty to auto-generate a new unique memo.',
        'memo_select_supplier_hint' => 'Select a supplier to verify this memo number.',
        'memo_duplicate_prefix' => 'An invoice ',
        'memo_duplicate_middle' => ' already exists for this supplier. Enter a different number',
        'memo_duplicate_suggestion' => ' (suggestion: ',
        'memo_ok_prefix' => 'Invoice ',
        'memo_ok_suffix' => ' is new for this supplier.',
        'sentence_end' => '.',
        'memo_verify_failed' => 'Could not verify the memo. Check your connection and try again.',
        'err_product_name' => '⚠️ Enter the product name.',
        'err_purchase_price' => '⚠️ Enter a valid purchase price.',
        'err_retail_low' => '⚠️ Retail price cannot be less than the purchase price.',
        'err_dealer_low' => '⚠️ Dealer price cannot be less than the purchase price.',
        'err_wholesale_low' => '⚠️ Wholesale price cannot be less than the purchase price.',
        'adding_label' => '⏳ Adding...',
        'generic_error' => 'Something went wrong.',
        'server_connect_error' => 'Could not connect to the server.',
        'err_supplier_name' => '⚠️ Enter the supplier name.',
        'saving_label' => 'Saving...',
        'saved_label' => 'Saved.',
        'not_saved_label' => 'Not saved.',
        'server_error_label' => 'Server error.',
        'select_supplier_required' => '⚠️ Select a supplier. The statement is settled against the supplier.',
        'payment_negative' => '⚠️ Payment cannot be negative.',
        'select_account_for_payment' => '⚠️ Select an account when paying.',
        'memo_duplicate_alert' => '⚠️ This invoice number already exists for this supplier. Enter a different number.',
        'edit_mode_banner' => '✏️ You are editing an old purchase memo (Memo: %s) — change anything below and press "Confirm memo update".',
        'edit_memo_not_found' => '❌ Memo number "%s" was not found, so the new purchase form is shown instead.',
        'confirm_memo_update' => '💾 Confirm memo update',
        'pay_history' => 'Payment history',
        'void_pay_bar' => 'This memo has ৳%s paid. If that money was deposited by mistake, void it — product lines stay, cash returns to the account, supplier due is rebuilt.',
        'void_pay_btn' => 'Void this memo payment',
        'void_pay_title' => 'Void mistaken payment',
        'void_pay_hint' => 'Only the payment is cancelled. Products and quantities stay. Write why this money must be reversed.',
        'void_pay_reason' => 'Reason',
        'void_pay_need_reason' => 'Write the reason (at least 3 characters).',
        'void_pay_working' => 'Voiding...',
        'void_pay_confirm' => 'Void payment',
    ]
    : [
        'memo_no' => 'মেমো নং',
        'memo_editable_hint' => 'প্রয়োজনে মেমো নম্বর বদলানো যাবে — শুধু অন্য কোনো মেমোর সাথে যেন না মিলে যায়।',
        'optional' => 'ঐচ্ছিক',
        'purchase_date' => 'ক্রয়ের তারিখ',
        'product_search_ph' => 'পণ্যের নাম / SKU / OEM / সাপ্লায়ার',
        'supplier_search_ph' => 'সরবরাহকারীর তথ্য',
        'add_supplier' => 'নতুন সরবরাহকারী যোগ করুন',
        'phone' => 'ফোন:',
        'previous' => 'সাবেক:',
        'recent_purchases' => 'সাম্প্রতিক ক্রয়',
        'draft' => 'ড্রাফট',
        'complete' => 'কমপ্লিট',
        'editing_draft' => 'সম্পাদনাধীন ড্রাফট:',
        'serial' => 'ক্রম',
        'code' => 'কোড',
        'product' => 'পণ্যের নাম',
        'purchase_price' => 'ক্রয় মূল্য',
        'quantity' => 'পরিমাণ',
        'dealer' => 'ডিলার',
        'wholesale' => 'পাইকারি',
        'retail' => 'খুচরা',
        'total' => 'মোট',
        'cancel' => 'বাতিল',
        'search_add_hint' => 'উপরে সার্চ করে পণ্য যোগ করুন',
        'product_total_lines' => 'পণ্য মোট · ০ লাইন',
        'go_to_payment_complete' => '💳 পরিশোধ খুলুন',
        'go_to_payment_draft' => '💳 পরিশোধ খুলে ড্রাফট সেভ করুন',
        'draft_list' => 'ড্রাফট তালিকা',
        'open_payment' => 'পরিশোধ',
        'payment_title' => 'পরিশোধ',
        'recent_purchases_complete' => 'সাম্প্রতিক ক্রয় · কমপ্লিট',
        'time' => 'সময়',
        'memo' => 'মেমো',
        'supplier' => 'সাপ্লায়ার',
        'price' => 'মূল্য',
        'view' => 'দেখুন',
        'no_purchase_yet' => 'এখনো কোনো ক্রয় নেই।',
        'all_history' => '📜 সব ইতিহাস',
        'close' => 'বন্ধ',
        'recent_purchases_draft' => 'সাম্প্রতিক ক্রয় · ড্রাফট',
        'number' => 'নম্বর',
        'edit' => 'এডিট',
        'purchase_calc' => '🧮 ক্রয় হিসাব',
        'product_total' => 'পণ্য মোট',
        'extra_expense' => 'অতিরিক্ত খরচ',
        'invoice_total' => 'ইনভয়েস মোট',
        'account' => 'অ্যাকাউন্ট',
        'payment' => 'পরিশোধ',
        'ledger_formula' => 'সাবেক বকেয়া + এই ইনভয়েস − পরিশোধ = সর্বশেষ ব্যালেন্স',
        'previous_due' => 'সাবেক বকেয়া',
        'previous_due_plus' => 'সাবেক বকেয়া (+)',
        'previous_due_minus' => 'সাবেক পাওনা (−)',
        'total_payable' => 'মোট পরিশোধযোগ্য (সাবেক + বিল)',
        'remaining_balance' => 'অবশিষ্ট ব্যালেন্স',
        'settle_type' => 'সাবেক সমন্বয়',
        'settle_none' => 'সাবেক অপরিবর্তিত',
        'settle_payment' => 'সাবেক পরিশোধ (অতিরিক্ত পেমেন্ট)',
        'settle_adjustment' => 'সাবেক সমন্বয় (অ্যাডমিন + কারণ)',
        'settle_reason' => 'সমন্বয়ের কারণ',
        'settle_reason_ph' => 'কেন সাবেক বদলাচ্ছে',
        'settle_help' => 'সাবেক শূন্য করতে পরিশোধ বা সমন্বয় লাগবে — শুধু সংখ্যা মুছলে আগের দেনা হারিয়ে যাবে না।',
        'settle_required' => 'সাবেক বদলেছে। পরিশোধ বা সমন্বয় বেছে কারণ লিখুন।',
        'pay_history' => 'পরিশোধ ইতিহাস',
        'allocate_older' => 'অতিরিক্ত পরিশোধ আগে পুরনো বকেয়া মেমোতে সমন্বয় হবে।',
        'this_invoice' => 'এই ইনভয়েস (+)',
        'current' => 'সর্বমোট ব্যালেন্স',
        'grand_balance' => 'সর্বশেষ ব্যালেন্স',
        'grand_pay' => 'সর্বমোট ব্যালেন্স',
        'grand_get' => 'সর্বমোট ব্যালেন্স',
        'expense_type' => 'খরচের ধরন',
        'transport' => 'পরিবহন',
        'labor' => 'শ্রমিক',
        'loading_unloading' => 'লোডিং/আনলোডিং',
        'packaging' => 'প্যাকেজিং',
        'other' => 'অন্যান্য',
        'expense_taka' => 'খরচ ৳',
        'add_expense' => '+ খরচ যোগ',
        'note' => 'মন্তব্য',
        'note_ph' => 'ঐচ্ছিক নোট',
        'add_new_product' => '➕ নতুন পণ্য যোগ',
        'back_dashboard' => '🏠 ড্যাশবোর্ডে ফিরে যান',
        'home' => 'হোমে যান',
        'supplier_select' => 'সাপ্লায়ার নির্বাচন',
        'find_product' => 'পণ্য খুঁজে বের করুন',
        'product_list' => 'পণ্য তালিকা',
        'supplier_account' => 'সাপ্লায়ার হিসাব',
        'total_and_expense' => 'মোট ও খরচ',
        'purchase_tools' => 'ক্রয় টুল',
        'complete_purchase' => '✅ ক্রয় সম্পন্ন করুন',
        'submit' => 'সাবমিট',
        'save_draft' => '💾 ড্রাফট সেভ করুন',
        'add_new_product_title' => '➕ নতুন পণ্য যোগ করুন',
        'add_new_product_hint' => 'তালিকায় নেই এমন পণ্য এখনই তৈরি করে কার্টে যাবে',
        'product_name_req' => 'পণ্যের নাম *',
        'purchase_price_req' => 'ক্রয় মূল্য *',
        'retail_req' => 'খুচরা *',
        'create_and_add' => '✓ তৈরি ও যোগ',
        'add_new_supplier_title' => '🚚 নতুন সরবরাহকারী যোগ করুন',
        'add_new_supplier_hint' => 'তালিকায় নেই এমন সাপ্লায়ার এখনই তৈরি করে সিলেক্ট হয়ে যাবে',
        'name_req' => 'নাম *',
        'mobile' => 'মোবাইল',
        'address' => 'ঠিকানা',
        'previous_balance' => 'পূর্বের ব্যালেন্স (সাবেক বকেয়া)',
        'previous_balance_hint' => 'সফটওয়্যারের আগে এই সাপ্লায়ারের কাছে কত পাওনা ছিল',
        'save_previous' => 'সাবেক সেভ',
        'previous_saved' => 'পূর্বের ব্যালেন্স সেভ হয়েছে।',
        'create_and_select' => '✓ তৈরি ও সিলেক্ট',
        'pos_title_edit_heading' => 'POS শিরোনাম লেখা',
        'pos_title_edit_hint' => 'শুধু এই লেখা এডিট হয়।',
        'save' => 'সেভ করুন',
        'edit_this_text' => 'এই লেখা এডিট করুন',
        'server_bad_response' => 'সার্ভার থেকে সঠিক উত্তর পাওয়া যায়নি।',
        'draft_op_failed' => 'ড্রাফট কার্যক্রম সম্পন্ন করা যায়নি।',
        'add_at_least_one_product' => '⚠️ অন্তত একটি পণ্য যোগ করুন।',
        'saving_draft' => '⏳ ড্রাফট সংরক্ষণ হচ্ছে...',
        'draft_saved_success' => 'ড্রাফট সফলভাবে সংরক্ষণ হয়েছে।',
        'draft_save_failed' => 'ড্রাফট সেভ হয়নি।',
        'draft_list_loading' => 'ড্রাফট তালিকা লোড হচ্ছে...',
        'no_active_draft' => 'কোনো সক্রিয় ড্রাফট পাওয়া যায়নি।',
        'draft_list_load_failed' => 'ড্রাফট তালিকা লোড হয়নি।',
        'draft_open_failed' => 'ড্রাফট খোলা যায়নি।',
        'confirm_cancel_draft' => 'আপনি কি এই ড্রাফট বাতিল করতে চান?',
        'draft_cancel_failed' => 'ড্রাফট বাতিল হয়নি।',
        'no_supplier_found' => 'কোনো সরবরাহকারী পাওয়া যায়নি',
        'no_result' => 'কোনো ফলাফল নেই',
        'search_count' => '%sটি পণ্য',
        'supplier_code_short' => 'সাপ',
        'stock_label' => 'স্টক',
        'below_purchase_rate_warning' => '⚠️ ক্রয় রেটের চেয়ে কম',
        'retail_low_msg' => '-এর খুচরা মূল্য (৳%s) ক্রয় মূল্যের (৳%s) চেয়ে কম হতে পারবে না।',
        'label_dash_prefix' => '-এর ',
        'price_low_msg' => ' মূল্য (৳%s) ক্রয় মূল্যের (৳%s) চেয়ে কম হতে পারবে না।',
        'valid_amount' => 'সঠিক পরিমাণ দিন।',
        'memo_empty_hint' => 'খালি রাখলে নতুন ইউনিক মেমো তৈরি হবে।',
        'memo_select_supplier_hint' => 'সাপ্লায়ার বাছাই করলে এই মেমো নম্বর যাচাই হবে।',
        'memo_duplicate_prefix' => 'এই সাপ্লায়ারের ইনভয়েস ',
        'memo_duplicate_middle' => ' আগেই আছে। অন্য নম্বর দিন',
        'memo_duplicate_suggestion' => ' (পরামর্শ: ',
        'memo_ok_prefix' => 'ইনভয়েস ',
        'memo_ok_suffix' => ' এই সাপ্লায়ারের জন্য নতুন।',
        'sentence_end' => '।',
        'memo_verify_failed' => 'মেমো যাচাই হয়নি। নেট চেক করে আবার চেষ্টা করুন।',
        'err_product_name' => '⚠️ পণ্যের নাম দিন।',
        'err_purchase_price' => '⚠️ ক্রয় মূল্য সঠিকভাবে লিখুন।',
        'err_retail_low' => '⚠️ খুচরা মূল্য ক্রয় মূল্যের চেয়ে কম হতে পারবে না।',
        'err_dealer_low' => '⚠️ ডিলার মূল্য ক্রয় মূল্যের চেয়ে কম হতে পারবে না।',
        'err_wholesale_low' => '⚠️ পাইকারি মূল্য ক্রয় মূল্যের চেয়ে কম হতে পারবে না।',
        'adding_label' => '⏳ যোগ হচ্ছে...',
        'generic_error' => 'সমস্যা হয়েছে।',
        'server_connect_error' => 'সার্ভারে সংযোগে সমস্যা হয়েছে।',
        'err_supplier_name' => '⚠️ সাপ্লায়ারের নাম দিন।',
        'saving_label' => 'সেভ হচ্ছে...',
        'saved_label' => 'সেভ হয়েছে।',
        'not_saved_label' => 'সেভ হয়নি।',
        'server_error_label' => 'সার্ভার এরর।',
        'select_supplier_required' => '⚠️ সাপ্লায়ার নির্বাচন করুন। স্টেটমেন্ট সাপ্লায়ার দিয়ে প্লাস-মাইনাস হয়।',
        'payment_negative' => '⚠️ পরিশোধ ঋণাত্মক হতে পারবে না।',
        'select_account_for_payment' => '⚠️ টাকা দিলে অ্যাকাউন্ট নির্বাচন করুন।',
        'memo_duplicate_alert' => '⚠️ এই সাপ্লায়ারের একই ইনভয়েস নম্বর আগেই আছে। অন্য নম্বর দিন।',
        'edit_mode_banner' => '✏️ আপনি একটা পুরনো ক্রয় মেমো এডিট করছেন (মেমো: %s) — নিচে সবকিছু বদলে "মেমো আপডেট নিশ্চিত করুন" চাপুন।',
        'edit_memo_not_found' => '❌ মেমো নম্বর "%s" খুঁজে পাওয়া যায়নি, তাই নতুন ক্রয়ের ফর্ম দেখানো হচ্ছে।',
        'confirm_memo_update' => '💾 মেমো আপডেট নিশ্চিত করুন',
        'pay_history' => 'পরিশোধ ইতিহাস',
        'void_pay_bar' => 'এই মেমোতে ৳%s পরিশোধ জমা আছে। ভুলে জমা হয়ে থাকলে বাতিল করুন — পণ্য লাইন থাকবে, টাকা অ্যাকাউন্টে ফিরবে, সাপ্লায়ার দেনা সূত্র দিয়ে আবার গোনা হবে।',
        'void_pay_btn' => 'এই মেমোর পরিশোধ বাতিল',
        'void_pay_title' => 'ভুল পরিশোধ বাতিল',
        'void_pay_hint' => 'শুধু পরিশোধ বাতিল হবে। পণ্য ও পরিমাণ থাকবে। কেন টাকা ফেরত নিতে হবে তা লিখুন।',
        'void_pay_reason' => 'কারণ',
        'void_pay_need_reason' => 'কারণ লিখুন (কমপক্ষে ৩ অক্ষর)।',
        'void_pay_working' => 'বাতিল হচ্ছে...',
        'void_pay_confirm' => 'পরিশোধ বাতিল করুন',
    ];

$ame_prod_on = ame_product_option_sql('status');
$products = @$db->query("SELECT id, name, sku, oem_code, supplier_code, barcode, purchase_price, wholesale_price, dealer_price, sale_price, stock FROM products WHERE {$ame_prod_on} ORDER BY name ASC LIMIT 4000");
if (!$products) {
    $products = @$db->query("SELECT id, name, sku, oem_code, supplier_code, purchase_price, wholesale_price, dealer_price, sale_price, stock FROM products WHERE {$ame_prod_on} ORDER BY name ASC LIMIT 4000");
}
if (!$products) {
    $products = $db->query("SELECT id, name, purchase_price, wholesale_price, dealer_price, sale_price, stock FROM products WHERE {$ame_prod_on} ORDER BY name ASC LIMIT 4000");
}
$product_list = $products ? $products->fetch_all(MYSQLI_ASSOC) : [];

if (!function_exists('ame_supplier_status_active_sql')) {
    require_once '../../includes/supplier_functions.php';
}
$ame_sup_on = ame_supplier_status_active_sql();
$ame_sup_live = ame_supplier_live_sql($db);
$suppliers = $db->query("SELECT id, name, phone, address, total_due FROM suppliers WHERE {$ame_sup_on} AND {$ame_sup_live} ORDER BY name ASC");
$supplier_list = $suppliers ? $suppliers->fetch_all(MYSQLI_ASSOC) : [];
foreach ($supplier_list as &$ame_sup_row) {
    $ame_sup_row['source'] = 'supplier';
    $ame_sup_row['customer_id'] = 0;
    $ame_sup_row['customer_balance'] = 0.0;
}
unset($ame_sup_row);

// কাস্টমার-তালিকা থেকে ক্রয় (কাস্টমার থেকে পণ্য কেনা হলে তাদের সাবেক টাকা মেমোতে যোগ-বিয়োগ সমন্বয় হবে)
$ame_cus_seen = [];
$ame_push_purchase_customer = static function (array $ame_cus) use (&$supplier_list, &$ame_cus_seen): void {
    $ame_cus_id = (int)($ame_cus['id'] ?? 0);
    $ame_cus_name = trim((string)($ame_cus['name'] ?? ''));
    if ($ame_cus_id <= 0 || $ame_cus_name === '' || isset($ame_cus_seen[$ame_cus_id])) {
        return;
    }
    if (!ame_customer_row_is_directory($ame_cus)) {
        return;
    }
    $ame_cus_seen[$ame_cus_id] = true;
    $rec_bal = round((float)($ame_cus['receivable_balance'] ?? 0), 2);
    $pay_bal = round((float)($ame_cus['payable_balance'] ?? 0), 2);
    if (abs($rec_bal) > 0.009) {
        $cus_bal = $rec_bal;
    } elseif ($pay_bal > 0.009) {
        $cus_bal = -$pay_bal;
    } else {
        $cus_bal = 0.0;
    }
    $supplier_list[] = [
        'id' => 'C' . $ame_cus_id,
        'customer_id' => $ame_cus_id,
        'customer_code' => (string)($ame_cus['customer_code'] ?? ''),
        'name' => $ame_cus_name,
        'phone' => trim((string)($ame_cus['phone'] ?? '')),
        'address' => (string)($ame_cus['address'] ?? ''),
        'total_due' => ($cus_bal < -0.009) ? abs($cus_bal) : 0.0,
        'customer_balance' => $cus_bal,
        'is_supplier' => (int)($ame_cus['is_supplier'] ?? 0),
        'source' => 'customer',
    ];
};
$ame_cus_role = '(is_customer = 1 OR is_customer IS NULL)';
$ame_cus_pick = function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db) : 'status = 1';
$ame_cus_sql = "SELECT id, customer_code, name, phone, address, receivable_balance, payable_balance, is_supplier, is_customer FROM customers WHERE {$ame_cus_pick} AND {$ame_cus_role} ORDER BY name ASC LIMIT 1000";
$ame_cus_res = @$db->query($ame_cus_sql);
if (!$ame_cus_res) {
    $ame_cus_res = @$db->query("SELECT id, name, phone, address, receivable_balance, payable_balance, is_supplier, is_customer FROM customers WHERE {$ame_cus_pick} AND {$ame_cus_role} ORDER BY name ASC LIMIT 1000");
}
if ($ame_cus_res) {
    while ($ame_cus = $ame_cus_res->fetch_assoc()) {
        $ame_push_purchase_customer($ame_cus);
    }
    $ame_cus_res->close();
}
// সাপ্লায়ার টিক দেওয়া কাস্টমার প্রথম ১০০০ নামের বাইরে থাকলেও ক্রয়ের সাপ্লায়ার খোঁজে আসবে।
$ame_sup_cus = @$db->query("SELECT id, customer_code, name, phone, address, receivable_balance, payable_balance, is_supplier, is_customer FROM customers WHERE is_supplier = 1 AND {$ame_cus_role} AND (status = 1 OR status IS NULL OR status = '') AND purged_at IS NULL ORDER BY name ASC");
if (!$ame_sup_cus) {
    $ame_sup_cus = @$db->query("SELECT id, customer_code, name, phone, address, receivable_balance, payable_balance, is_supplier, is_customer FROM customers WHERE is_supplier = 1 AND {$ame_cus_role} ORDER BY name ASC");
}
if ($ame_sup_cus) {
    while ($ame_cus = $ame_sup_cus->fetch_assoc()) {
        $ame_push_purchase_customer($ame_cus);
    }
    $ame_sup_cus->close();
}

if (!function_exists('ame_active_accounts')) {
    require_once '../../includes/account_functions.php';
}
/*
 * পেমেন্ট সিস্টেমে খোলা অ্যাকাউন্ট সব — ক্যাশ, ব্যাংক, মোবাইল, বিকাশ।
 * ame_active_accounts খালি না হলেও কিছু সারি বাদ দিতে পারে (context /
 * is_personal)। খোলা স্ট্যাটাসের বাকি সারি এখানে জোড়া হয়। বন্ধ সারি বাদ।
 */
$account_list = function_exists('ame_active_accounts') ? ame_active_accounts($db) : [];
$ame_pay_seen = [];
foreach ($account_list as $ame_pay_row) {
    $ame_pay_id = (int)($ame_pay_row['id'] ?? 0);
    if ($ame_pay_id > 0) {
        $ame_pay_seen[$ame_pay_id] = true;
    }
}
$ame_pay_closed = function_exists('ame_account_closed_status_sql')
    ? ame_account_closed_status_sql('status')
    : "LOWER(TRIM(status)) IN ('inactive','0','no','n','off','disabled','disable','false','closed','close','stopped','stop','deleted','archived','void','2','নিষ্ক্রিয়','বন্ধ')";
$ame_pay_open = @$db->query(
    "SELECT id, account_name, account_type, balance, status
     FROM accounts
     WHERE status IS NULL OR TRIM(status) = '' OR NOT ({$ame_pay_closed})
     ORDER BY account_name ASC, id ASC"
);
if ($ame_pay_open) {
    foreach ($ame_pay_open->fetch_all(MYSQLI_ASSOC) as $ame_pay_row) {
        $ame_pay_id = (int)($ame_pay_row['id'] ?? 0);
        if ($ame_pay_id > 0 && empty($ame_pay_seen[$ame_pay_id])) {
            $account_list[] = $ame_pay_row;
            $ame_pay_seen[$ame_pay_id] = true;
        }
    }
}
if (!$account_list) {
    $accounts = @$db->query("SELECT id, account_name, account_type, balance FROM accounts ORDER BY id ASC");
    $account_list = $accounts ? $accounts->fetch_all(MYSQLI_ASSOC) : [];
}
$ame_pay_tone = static function (string $name, string $type): string {
    $label = mb_strtolower($name . ' ' . $type, 'UTF-8');
    if (str_contains($label, 'বিকাশ') || str_contains($label, 'bkash')) {
        return 'is-bkash';
    }
    if (str_contains($label, 'রকেট') || str_contains($label, 'rocket')) {
        return 'is-rocket';
    }
    if (str_contains($label, 'ব্যাংক') || str_contains($label, 'bank')) {
        return 'is-bank';
    }
    if (str_contains($label, 'nagad') || str_contains($label, 'মোবাইল') || str_contains($label, 'mobile')) {
        return 'is-nagad';
    }
    if (str_contains($label, 'ক্যাশ') || str_contains($label, 'cash') || str_contains($label, 'নগদ')) {
        return 'is-cash';
    }
    return 'is-other';
};
$default_account_id = 0;

// ================= "✏️ এডিট" — history.php/memo_digital.php থেকে ?edit_memo=মেমো নম্বর
// দিয়ে এলে সেই পুরনো ক্রয়টা এই একই প্রফেশনাল ক্রয়-স্ক্রিনের কার্টে লোড করে দেওয়া হয়,
// যাতে ঠিক নতুন ক্রয়ের মতোই সবকিছু (সাপ্লায়ার/পণ্য/দাম/পরিমাণ/খরচ/পেমেন্ট) এখান
// থেকে এডিট করে "কনফার্ম" করা যায়। =================
$edit_memo = ame_purchase_normalize_memo($_GET['edit_memo'] ?? '');
$edit_supplier_id = (int)($_GET['supplier_id'] ?? 0);
$ame_edit_payload = null;
if ($edit_memo !== '') {
    $edit_scope = $edit_supplier_id > 0 ? ' AND supplier_id = ?' : '';
    $eh = $db->prepare("SELECT supplier_id, customer_id, previous_supplier_due, previous_customer_balance, customer_balance_after, account_id, created_at, notes FROM purchases WHERE memo_no = ?" . $edit_scope . " ORDER BY id ASC LIMIT 1");
    if (!$eh) {
        $eh = $db->prepare("SELECT supplier_id, customer_id, previous_customer_balance, customer_balance_after, account_id, created_at, notes FROM purchases WHERE memo_no = ?" . $edit_scope . " ORDER BY id ASC LIMIT 1");
    }
    if (!$eh) {
        $eh = $db->prepare("SELECT supplier_id, account_id, created_at, notes FROM purchases WHERE memo_no = ?" . $edit_scope . " ORDER BY id ASC LIMIT 1");
    }
    if ($edit_supplier_id > 0) {
        $eh->bind_param('si', $edit_memo, $edit_supplier_id);
    } else {
        $eh->bind_param('s', $edit_memo);
    }
    $eh->execute();
    $ehr = $eh->get_result()->fetch_assoc();
    $eh->close();

    if ($ehr) {
        $e_supplier_id = (int)($ehr['supplier_id'] ?? 0);
        $e_customer_id = (int)($ehr['customer_id'] ?? 0);

        if ($e_supplier_id > 0) {
            $ei = $db->prepare(
                "SELECT pu.product_id, pu.qty, pu.free_qty, pu.purchase_price, pu.dealer_price, pu.wholesale_price, pu.sale_price,
                        pu.paid_amount, pu.total_cost, p.name AS product_name, p.sku, p.stock
                 FROM purchases pu
                 LEFT JOIN products p ON p.id = pu.product_id
                 WHERE pu.memo_no = ? AND pu.supplier_id = ?
                 ORDER BY pu.id ASC"
            );
            $ei->bind_param('si', $edit_memo, $e_supplier_id);
        } else {
            $ei = $db->prepare(
                "SELECT pu.product_id, pu.qty, pu.free_qty, pu.purchase_price, pu.dealer_price, pu.wholesale_price, pu.sale_price,
                        pu.paid_amount, pu.total_cost, p.name AS product_name, p.sku, p.stock
                 FROM purchases pu
                 LEFT JOIN products p ON p.id = pu.product_id
                 WHERE pu.memo_no = ? AND (pu.supplier_id IS NULL OR pu.supplier_id = 0)
                 ORDER BY pu.id ASC"
            );
            $ei->bind_param('s', $edit_memo);
        }
        $ei->execute();
        $e_items = $ei->get_result()->fetch_all(MYSQLI_ASSOC);
        $ei->close();

        if ($e_supplier_id > 0) {
            $ee = $db->prepare("SELECT expense_type, amount FROM purchase_expenses WHERE memo_no = ? AND supplier_id = ? ORDER BY id ASC");
            $ee->bind_param('si', $edit_memo, $e_supplier_id);
        } else {
            $ee = $db->prepare("SELECT expense_type, amount FROM purchase_expenses WHERE memo_no = ? AND (supplier_id IS NULL OR supplier_id = 0) ORDER BY id ASC");
            $ee->bind_param('s', $edit_memo);
        }
        $ee->execute();
        $e_expenses = $ee->get_result()->fetch_all(MYSQLI_ASSOC);
        $ee->close();

        $e_grand = 0.0;
        $e_paid = 0.0;
        foreach ($e_items as $it) {
            $e_grand += (float)$it['total_cost'];
            $e_paid += (float)$it['paid_amount'];
        }
        $e_paid = function_exists('ame_purchase_canonical_paid')
            ? ame_purchase_canonical_paid($e_grand, $e_paid)
            : $e_paid;
        $e_due_delta = round($e_grand - $e_paid, 2);

        $sup_name = '';
        $sup_phone = '';
        $sup_due_excl = 0.0;
        $cust_bal_excl = 0.0;

        if ($e_customer_id > 0) {
            $cst = $db->prepare('SELECT id, name, phone, receivable_balance, payable_balance FROM customers WHERE id = ?');
            if ($cst) {
                $cst->bind_param('i', $e_customer_id);
                $cst->execute();
                $crow = $cst->get_result()->fetch_assoc();
                $cst->close();
                if ($crow) {
                    $sup_name = (string)$crow['name'];
                    $sup_phone = (string)($crow['phone'] ?? '');
                    $ledger_prev = function_exists('ame_purchase_customer_previous_before_memo')
                        ? ame_purchase_customer_previous_before_memo($db, $e_customer_id, (string)$edit_memo)
                        : null;
                    if ($ledger_prev !== null) {
                        $cust_bal_excl = $ledger_prev;
                    } elseif (isset($ehr['previous_customer_balance']) && $ehr['previous_customer_balance'] !== null) {
                        $cust_bal_excl = (float)$ehr['previous_customer_balance'];
                    } else {
                        $c_rec = round((float)($crow['receivable_balance'] ?? 0), 2);
                        $c_pay = round((float)($crow['payable_balance'] ?? 0), 2);
                        $curr_bal = (abs($c_rec) > 0.009) ? $c_rec : ($c_pay > 0.009 ? -$c_pay : 0.0);
                        $cust_bal_excl = round($curr_bal + $e_grand - $e_paid, 2);
                    }
                }
            }
        }
        if ($sup_name === '' && $e_supplier_id > 0) {
            $sst = $db->prepare('SELECT name, phone, total_due FROM suppliers WHERE id = ?');
            $sst->bind_param('i', $e_supplier_id);
            $sst->execute();
            $srow = $sst->get_result()->fetch_assoc();
            $sst->close();
            if ($srow) {
                $sup_name = (string)$srow['name'];
                $sup_phone = (string)($srow['phone'] ?? '');
                $sup_due_excl = 0.0;
                if (function_exists('ame_running_previous_before_memo')) {
                    $sup_due_excl = ame_running_previous_before_memo($db, $e_supplier_id, (string)$edit_memo);
                } elseif (isset($ehr['previous_supplier_due']) && $ehr['previous_supplier_due'] !== null && $ehr['previous_supplier_due'] !== '') {
                    $sup_due_excl = round((float)$ehr['previous_supplier_due'], 2);
                } else {
                    $sup_due_excl = round((float)$srow['total_due'] - $e_due_delta, 2);
                }
            }
        }

        $ame_edit_payload = [
            'memo' => $edit_memo,
            'supplier_id' => $e_supplier_id,
            'customer_id' => $e_customer_id,
            'customer_balance' => $cust_bal_excl,
            'party_type' => $e_customer_id > 0 ? 'customer' : 'supplier',
            'supplier_name' => $sup_name,
            'supplier_phone' => $sup_phone,
            'supplier_due' => $sup_due_excl,
            'date' => date('Y-m-d', strtotime((string)($ehr['created_at'] ?? 'now'))),
            'notes' => (string)($ehr['notes'] ?? ''),
            'account_id' => (int)($ehr['account_id'] ?? 0),
            'paid_amount' => round($e_paid, 2),
            'cart' => array_map(static function ($it) {
                return [
                    'product_id' => (int)$it['product_id'],
                    'sku' => (string)($it['sku'] ?? ''),
                    'name' => trim((string)($it['product_name'] ?? '')) !== '' ? (string)$it['product_name'] : 'পণ্য',
                    'stock' => $it['stock'],
                    'qty' => max(0, round((float)$it['qty'] - (float)($it['free_qty'] ?? 0), 2)),
                    'free_qty' => (float)($it['free_qty'] ?? 0),
                    'purchase_price' => (float)$it['purchase_price'],
                    'dealer_price' => (float)$it['dealer_price'],
                    'wholesale_price' => (float)$it['wholesale_price'],
                    'sale_price' => (float)$it['sale_price'],
                ];
            }, $e_items),
            'expenses' => array_map(static function ($e) {
                return ['type' => (string)$e['expense_type'], 'amount' => (float)$e['amount']];
            }, $e_expenses),
            'adjustments' => ame_purchase_load_adjustments($db, $edit_memo, $e_supplier_id),
            'pay_history' => function_exists('ame_running_payment_history')
                ? ame_running_payment_history($db, (string)$edit_memo, $e_supplier_id)
                : [],
        ];
    }
}

$recent_purchases = [];
$rq = @$db->query("SELECT pu.id, pu.created_at, pu.memo_no, pu.total_cost, pu.supplier_id, s.name AS supplier_name
    FROM purchases pu
    LEFT JOIN suppliers s ON s.id = pu.supplier_id
    ORDER BY pu.id DESC
    LIMIT 60");
if ($rq) {
    $groups = [];
    while ($r = $rq->fetch_assoc()) {
        $key = ($r['memo_no'] ?: '') . '|' . substr((string)$r['created_at'], 0, 16) . '|' . ($r['supplier_name'] ?? '');
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'time' => date('h:i A', strtotime($r['created_at'])),
                'invoice' => $r['memo_no'] ? $r['memo_no'] : ('#' . $r['id']),
                'supplier' => $r['supplier_name'] ?: '—',
                'supplier_id' => (int)($r['supplier_id'] ?? 0),
                'total' => 0.0,
            ];
        }
        $groups[$key]['total'] += (float)$r['total_cost'];
        if (count($groups) >= 20) {
            break;
        }
    }
    $recent_purchases = array_values($groups);
}

$ame_pos_title = function_exists('ame_pos_title_row') ? ame_pos_title_row($db) : ame_pos_title_defaults();
$ame_pos_title_line = $ame_pos_title['line_text'];
$ame_pos_title_style = function_exists('ame_pos_title_style_attr') ? ame_pos_title_style_attr($ame_pos_title) : '';
$ame_pos_title_can_edit = function_exists('ame_can_edit_pos_title') && ame_can_edit_pos_title();
?>
<link rel="stylesheet" href="/assets/css/purchase-classic.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.438', ENT_QUOTES, 'UTF-8'); ?>">
<style>
    #pbLedger.is-customer-slim { display: none !important; }
    .ame-pc-cust-adj { margin: 0 0 12px; padding: 10px; border: 1.5px solid #0e7490; border-radius: 12px; background: #fff; }
    .ame-pc-cust-adj-note { margin: 0 0 8px; color: #0e7490; font-weight: 800; font-size: 13px; text-align: center; }
    .ame-pc-cust-adj-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
    .ame-pc-cust-adj-box { background: #fff; border-radius: 10px; padding: 10px 6px; text-align: center; border: 1.5px solid #ccc; }
    .ame-pc-cust-adj-box span { display: block; font-size: 12px; font-weight: 800; margin-bottom: 4px; }
    .ame-pc-cust-adj-box strong { display: block; font-size: 18px; }
    .ame-pc-cust-adj-box.c1 { border-color: #c2410c; } .ame-pc-cust-adj-box.c1 span, .ame-pc-cust-adj-box.c1 strong { color: #c2410c; }
    .ame-pc-cust-adj-box.c2 { border-color: #0369a1; } .ame-pc-cust-adj-box.c2 span, .ame-pc-cust-adj-box.c2 strong { color: #0369a1; }
    .ame-pc-cust-adj-box.c3 { border-color: #0f766e; } .ame-pc-cust-adj-box.c3 span, .ame-pc-cust-adj-box.c3 strong { color: #0f766e; }
    @media (max-width: 700px) { .ame-pc-cust-adj-grid { grid-template-columns: 1fr; } }
    .pc-results { position:absolute; left:0; right:0; top:calc(100% + 4px); background:#fff; border:2px solid #003F8F; border-radius:10px; box-shadow:0 14px 32px rgba(11,30,77,0.18); max-height:min(60vh, 520px); overflow-y:auto; z-index:400020; display:none; }
    .pc-result-count { padding:8px 13px 4px; font-size:13px; font-weight:800; color:#111827; }
    .pc-results.open { display:block; }
    .pc-result-item { padding:10px 13px; cursor:pointer; border-bottom:1px solid #E5E7EB; font-size:15px; font-weight:800; color:#111827; border-radius:7px; margin:2px; }
    .pc-result-item:hover { background:var(--pos-c-eaf4ff, #EAF4FF); }
    .pc-result-item small { color:var(--pos-c-64748b, #64748B); display:block; font-size:11px; }
    .pc-supplier-info { display:none; background:linear-gradient(145deg, var(--pos-c-eaf4ff, #EAF4FF), var(--brand-white, #ffffff)); border:1px solid var(--pos-c-bfe0ff, #bfe0ff); border-radius:9px; padding:9px 11px; margin-top:8px; font-size:12px; }
    .pc-supplier-info.show { display:grid; grid-template-columns:1fr 1fr; gap:4px 12px; }
    .pc-remove-btn { color:var(--pos-c-c62828, #C62828); cursor:pointer; font-weight:800; text-align:center; }
    .pc-empty-cart { text-align:center; color:var(--pos-c-64748b, #64748b); padding:70px 10px; }
    .pc-expense-list-item { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--pos-c-f1f5f9, #F1F5F9); font-size:12.5px; }
    .pc-search-rel { position:relative; }
    .pc-draft-banner { margin:8px 0 0; padding:8px 10px; background:var(--brand-warn-soft, #FFF8E1); border:1px solid var(--pos-c-f6c445, #F6C445); border-radius:8px; font-size:12px; font-weight:700; color:var(--brand-navy, #0B1E4D); }
    .ame-pc-supplier-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:6px; align-items:stretch; }
    .pos-customer-add {
        min-width: 42px;
        width: 42px;
        border: 0;
        border-radius: 9px;
        background: linear-gradient(145deg, var(--pos-c-34d399, #34d399), var(--pos-c-0d9488, #0d9488));
        color: var(--pos-c-ecfdf5, #ecfdf5);
        font-size: 22px;
        font-weight: 800;
        line-height: 1;
        cursor: pointer;
        box-shadow: 0 2px 6px var(--pos-rgba-teal-35, rgba(13, 148, 136, 0.35)), inset 0 1px 0 var(--pos-rgba-white-25, rgba(255,255,255,0.25));
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .pos-customer-add:hover { transform: translateY(-1px) scale(1.05); box-shadow: 0 4px 10px var(--pos-rgba-teal-40, rgba(13, 148, 136, 0.4)); }
    .pos-customer-add:active { transform: translateY(0) scale(0.96); }
    body.ame-purchase-terminal-page .ame-pos-term-top,
    body.ame-purchase-terminal-page .ame-pos-term-bar,
    body.ame-purchase-terminal-page .ame-pos-term-actions,
    body.ame-purchase-terminal-page .ame-pos-recent-wrap { overflow:visible !important; }
    body.ame-purchase-terminal-page .ame-pos-recent-wrap { position:relative; z-index:400010; }
    .ame-pc-top-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px 16px;
        margin: 0 0 12px;
        padding: 12px 14px;
        border: 1px solid var(--pos-c-d9dee5, #d9dee5);
        border-radius: 10px;
        background: linear-gradient(145deg, var(--brand-white, #ffffff), var(--brand-surface-soft, #f8fafc));
        box-shadow: 0 1px 3px var(--pos-rgba-navy-06, rgba(11, 30, 77, 0.06));
    }
    .ame-pc-top-meta .ame-pos-calc-row {
        margin: 0;
        display: grid;
        grid-template-columns: 110px minmax(0, 1fr);
        gap: 8px;
        align-items: center;
    }
    .ame-pc-top-meta label {
        margin: 0;
        font-size: 13px;
        font-weight: 800;
        color: var(--brand-navy, #0B1E4D);
    }
    .ame-pc-top-meta input {
        width: 100%;
        min-height: 40px;
        padding: 8px 10px;
        border: 1px solid var(--pos-c-c5ced8, #c5ced8);
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 14px;
    }
    @media (max-width: 900px) {
        .ame-pc-top-meta { grid-template-columns: 1fr; }
        .ame-pc-top-meta .ame-pos-calc-row { grid-template-columns: 1fr; }
    }
    #amePcGrandTotal { margin: 2px 0; }
    #amePcCompleteBtn { width: 100%; margin-top: 2px; }
    .ame-pc-memo-hint { margin: 6px 0 0; font-size: 12px; font-weight: 700; }
    .ame-pc-memo-hint.is-bad { color: var(--pos-c-c62828, #C62828); }
    .ame-pc-memo-hint.is-ok { color: var(--brand-success, #0E8F52); }
    .ame-pc-cart-sum {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
        padding: 10px 12px;
        background: linear-gradient(145deg, var(--pos-c-eaf4ff, #EAF4FF), var(--pos-c-dbeeff, #dbeeff));
        border: 1px solid var(--pos-c-c5ced8, #c5ced8);
        border-top: 0;
        border-radius: 0 0 9px 9px;
        font-weight: 800;
        font-size: 14px;
        color: var(--brand-navy, #0B1E4D);
    }
    .ame-pc-ledger {
        margin: 8px 0 10px;
        padding: 12px;
        border: 1px solid var(--pos-c-ffe082, #ffe082);
        border-radius: 10px;
        background: linear-gradient(145deg, var(--brand-warn-soft, #fff8e1), var(--pos-c-fef3c7, #fef3c7));
        box-shadow: 0 1px 3px var(--pos-rgba-orange-08, rgba(230, 81, 0, 0.08));
    }
    .ame-pc-ledger-formula {
        margin: 0 0 8px;
        font-size: 12px;
        font-weight: 800;
        color: var(--pos-c-e65100, #E65100);
        line-height: 1.4;
    }
    .ame-pos-col-line { text-align: right; font-weight: 800; font-variant-numeric: tabular-nums; min-width: 92px; }
    #cartFoot td { background: var(--brand-surface-soft, #F8FAFC); font-weight: 800; }

    /* =====================================================================
       পেমেন্ট/সারাংশ বক্স (মোট + "ক্রয় সম্পন্ন করুন" বাটন) সবসময় চোখের
       সামনে থাকবে — স্ক্রল করে খুঁজতে হবে না। ডেস্কটপে পাশে (sticky),
       মোবাইলে নিচে আটকানো (sticky bottom) থাকবে; বাকি অংশ (সার্চ, কার্ট)
       স্বাভাবিকভাবে স্ক্রল হবে। কোনো কন্টেন্ট জোর করে লুকানো/কাটা হয় না,
       তাই ছোট স্ক্রিনেও কোনো তথ্য হারানোর ঝুঁকি নেই।
       ===================================================================== */
    @media (min-width: 1000px) {
        body.ame-purchase-terminal-page.ame-purchase-desk .ame-pos-grid {
            grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr) !important;
            align-items: start !important;
        }
        body.ame-purchase-terminal-page .ame-pos-summary {
            position: sticky;
            top: calc(var(--ame-chrome-h, 64px) + 10px);
            max-height: calc((var(--ame-vh, 1vh) * 100) - var(--ame-chrome-h, 64px) - 20px);
            overflow-y: auto;
        }
    }
    @media (max-width: 999px) {
        body.ame-purchase-terminal-page .ame-pos-summary {
            position: static;
            max-height: none;
            overflow-y: visible;
            border-radius: 12px;
            box-shadow: none;
        }
        body.ame-purchase-terminal-page .ame-pos-table-wrap {
            max-height: none;
            overflow-y: visible !important;
        }
    }
</style>

<div class="pb-page" id="pbPage">
    <div class="ame-pos-shell">
        <?php if ($ame_pos_title_can_edit): ?>
        <div
            class="ame-pos-title-line is-editable"
            id="amePosTitleLine"
            hidden
            style="<?php echo esc_html($ame_pos_title_style); ?>"
            role="button"
            tabindex="0"
            title="<?php echo $escape($words['edit_this_text']); ?>"
            onclick="ameOpenPosTitleEdit()"
            onkeydown="if(event.key==='Enter'){ameOpenPosTitleEdit();}"
        ><span class="ame-pos-title-live"><?php echo $escape($ame_pos_title_line); ?></span></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div style="background:var(--pos-c-ffebee, #FFEBEE); color:var(--pos-c-c62828, #C62828); padding:12px 16px; border-radius:4px; margin-bottom:10px; font-size:13px;"><?php echo $escape($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['success'])): ?>
            <div style="background:#ECFDF5; color:#065F46; padding:12px 16px; border-radius:4px; margin-bottom:10px; font-size:13px; font-weight:700;"><?php echo $escape($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <?php if ($ame_edit_payload): ?>
        <div style="background:var(--brand-warn-soft, #FFF8E1); border:1px solid var(--pos-c-ffe082, #FFE082); color:var(--brand-navy, #0B1E4D); padding:10px 16px; border-radius:8px; margin-bottom:10px; font-size:13px; font-weight:700;">
            <?php echo $escape(sprintf($words['edit_mode_banner'], $ame_edit_payload['memo'])); ?>
        </div>
        <?php if ((float)($ame_edit_payload['paid_amount'] ?? 0) > 0.009): ?>
        <div class="ame-void-pay-bar" id="ameVoidPayBar">
            <p><?php echo $escape(sprintf($words['void_pay_bar'], number_format((float)$ame_edit_payload['paid_amount'], 2))); ?></p>
            <button type="button" onclick="openVoidMemoPayModal()"><?php echo $escape($words['void_pay_btn']); ?></button>
        </div>
        <?php endif; ?>
        <?php if (!empty($ame_edit_payload['pay_history'])): ?>
        <div class="ame-pay-history">
            <h4><?php echo $escape($words['pay_history'] ?? 'পরিশোধ ইতিহাস'); ?></h4>
            <?php foreach ($ame_edit_payload['pay_history'] as $ph): ?>
                <div><?php echo $escape(($ph['at'] ?? '') . ' · ' . ($ph['kind'] ?? '') . ' · ৳' . number_format((float)($ph['amount'] ?? 0), 2) . ' · ' . ($ph['note'] ?? '')); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php elseif ($edit_memo !== ''): ?>
        <div style="background:var(--brand-danger-soft, #FFEBEE); color:var(--brand-danger, #C62828); padding:12px 16px; border-radius:4px; margin-bottom:10px; font-size:13px;"><?php echo $escape(sprintf($words['edit_memo_not_found'], $edit_memo)); ?></div>
        <?php endif; ?>

        <form action="<?php echo $ame_edit_payload ? 'process_purchase_edit.php' : 'process_purchase.php'; ?>" method="POST" id="purchaseForm">
            <input type="hidden" name="csrf_token" value="<?php echo $escape($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="idempotency_token" value="<?php echo $escape(bin2hex(random_bytes(16))); ?>">
            <input type="hidden" name="edit_mode" value="<?php echo $ame_edit_payload ? '1' : '0'; ?>">
            <input type="hidden" name="supplier_id" id="supplierIdHidden">
            <input type="hidden" name="customer_id" id="customerIdHidden" value="">
            <input type="hidden" name="party_type" id="partyTypeHidden" value="supplier">
            <input type="hidden" name="customer_balance" id="customerBalanceHidden" value="0">
            <input type="hidden" name="previous_due" id="previousDueHidden" value="">
            <input type="hidden" name="settle_type" id="settleTypeHidden" value="adjustment">
            <input type="hidden" name="settle_reason" id="settleReasonHidden" value="">
            <input type="hidden" name="memo_no" id="memoHidden">
            <input type="hidden" name="original_memo_no" id="originalMemoHidden" value="<?php echo $ame_edit_payload ? $escape($ame_edit_payload['memo']) : ''; ?>">
            <input type="hidden" name="original_supplier_id" id="originalSupplierHidden" value="<?php echo $ame_edit_payload ? (int)$ame_edit_payload['supplier_id'] : 0; ?>">
            <input type="hidden" name="purchase_date" id="purchaseDateHidden" value="<?php echo date('Y-m-d'); ?>">
            <input type="hidden" name="payment_type" id="paymentTypeHidden" value="cash">
            <input type="hidden" name="account_id" id="accountIdHidden">
            <input type="hidden" name="paid_amount" id="paidAmountHidden" value="0">
            <input type="hidden" name="advance_amount" id="advanceAmountHidden" value="0">
            <input type="hidden" name="advance_account_id" id="advanceAccountIdHidden" value="">
            <input type="hidden" name="items_json" id="itemsJsonHidden">
            <input type="hidden" name="expenses_json" id="expensesJsonHidden">
            <input type="hidden" name="notes" id="notesHidden">
            <input type="hidden" name="discount_percent" id="discPctHidden" value="0">
            <input type="hidden" name="discount_amount" id="discAmtHidden" value="0">
            <input type="hidden" name="rounding" id="roundingHidden" value="0">
            <input type="hidden" name="special_discount" id="specialHidden" value="0">
            <input type="hidden" name="draft_id" id="currentDraftId" value="0">
            <input type="hidden" id="amePcMode" value="complete">

            <?php $pbt = static function ($bn, $en) use ($is_english) { return $is_english ? $en : $bn; }; ?>
            <p id="amePcDraftBanner" class="pc-draft-banner" hidden><?php echo $escape($words['editing_draft']); ?> <span id="amePcDraftNumber"></span></p>
            <button type="button" id="amePosRecentComplete" data-mode="complete" hidden></button>
            <button type="button" id="amePosRecentDraft" data-mode="draft" hidden></button>
            <button type="button" id="amePcSaveDraftBtn" hidden></button>
            <button type="button" id="amePosDraftListBtn" hidden></button>
            <button type="button" id="pbPayOpen" hidden></button>

            <header class="er-head">
                <div>
                    <h1>🛒 <?php echo $escape($pbt('পণ্য ক্রয় (Purchase)', 'Purchase')); ?></h1>
                    <p class="er-crumb"><?php echo $escape($pbt('ড্যাশবোর্ড / ক্রয় / পণ্য ক্রয়', 'Dashboard / Purchase / New purchase')); ?></p>
                </div>
                <button type="button" class="er-head-pay" id="pbPayLaunch" aria-controls="pbPayCard">
                    💳 <?php echo $escape($words['open_payment']); ?>
                    <strong id="pbPayLaunchNet">৳0.00</strong>
                </button>
            </header>

            <section class="er-card er-meta">
                <div class="er-meta-grid">
                    <div class="er-field">
                        <label for="purchaseDateInput"><?php echo $escape($words['purchase_date']); ?> <span class="req">*</span></label>
                        <input type="date" id="purchaseDateInput" class="pb-input" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="er-field">
                        <label for="supplierSearch"><?php echo $escape($pbt('সাপ্লায়ার নির্বাচন', 'Supplier')); ?> <span class="req">*</span></label>
                        <div class="er-inline">
                            <div class="pc-search-rel">
                                <input type="text" id="supplierSearch" class="pb-input" autocomplete="off" placeholder="<?php echo $escape($pbt('সাপ্লায়ার নির্বাচন করুন', 'Select supplier')); ?>">
                                <div class="pc-results" id="supplierResults"></div>
                            </div>
                            <button type="button" id="openQuickSupplier" class="er-plus" onclick="openQuickAddSupplierModal()" title="<?php echo $escape($pbt('নতুন সরবরাহকারী', 'New supplier')); ?>">+</button>
                        </div>
                    </div>
                    <div class="er-field">
                        <label for="memoInput"><?php echo $escape($pbt('চালান নং', 'Challan no.')); ?></label>
                        <div class="er-inline-3">
                            <input type="text" id="memoInput" class="pb-input" maxlength="50" autocomplete="off">
                            <button type="button" class="er-ico-btn" id="pbMemoSuggest" title="<?php echo $escape($pbt('নতুন নম্বর', 'Suggest number')); ?>">↻</button>
                        </div>
                    </div>
                    <div class="er-field">
                        <label><?php echo $escape($pbt('পক্ষ', 'Party')); ?></label>
                        <div class="pb-radios" role="radiogroup">
                            <label><input type="radio" name="pb_party" value="supplier" checked> <?php echo $escape($pbt('সরবরাহকারী', 'Supplier')); ?></label>
                            <label><input type="radio" name="pb_party" value="customer"> <?php echo $escape($pbt('ক্রেতা', 'Customer')); ?></label>
                        </div>
                    </div>
                    <button type="button" class="er-new-challan" id="pbNewChallan">📄 <?php echo $escape($pbt('নতুন চালান', 'New challan')); ?></button>
                </div>
                <p id="amePcMemoHint" class="ame-pc-memo-hint" hidden></p>
                <div class="pc-supplier-info" id="supplierInfo">
                    <div id="supPartyTypeWrap" style="display:none;"><span id="supPartyBadge"></span></div>
                    <div><strong><?php echo $escape($words['phone']); ?></strong> <span id="supPhone">—</span></div>
                    <div><strong id="supDueLabel"><?php echo $escape($words['previous']); ?></strong> <span id="supDue">৳0.00</span></div>
                </div>
            </section>

            <section class="er-card er-entry" id="pbEntry">
                <h2 class="er-title">
                    <button type="button" class="er-plus" id="pbQuickAddBtn" onclick="openQuickAddModal()" title="<?php echo $escape($pbt('নতুন পণ্য যোগ', 'Add new product')); ?>" aria-label="<?php echo $escape($pbt('নতুন পণ্য যোগ', 'Add new product')); ?>">+</button>
                    <?php echo $escape($pbt('নতুন পণ্য যোগ করুন', 'Add a product')); ?>
                </h2>
                <div class="er-search pc-search-rel">
                    <span class="er-search-ico" aria-hidden="true">🔍</span>
                    <input type="search" id="productSearch" class="pb-input" autocomplete="off" placeholder="<?php echo $escape($pbt('পণ্যের নাম / কোড / বারকোড লিখুন...', 'Product name / code / barcode')); ?>">
                    <div class="pc-results" id="productResults"></div>
                </div>
                <div class="er-entry-grid">
                    <div class="er-field">
                        <label><?php echo $escape($pbt('পণ্যের নাম', 'Product name')); ?> <span class="req">*</span></label>
                        <input type="text" id="pbNameShow" class="pb-input" readonly placeholder="<?php echo $escape($pbt('পণ্যের নাম লিখুন', 'Product name')); ?>">
                    </div>
                    <div class="er-field">
                        <label><?php echo $escape($pbt('কোড', 'Code')); ?></label>
                        <input type="text" id="pbSku" class="pb-input" readonly>
                    </div>
                    <div class="er-field">
                        <label><?php echo $escape($pbt('ইউনিট', 'Unit')); ?></label>
                        <input type="text" id="pbUnitShow" class="pb-input" value="<?php echo $escape($pbt('পিস', 'Pcs')); ?>" readonly>
                    </div>
                    <div class="er-field">
                        <label for="pbQty"><?php echo $escape($pbt('পরিমাণ', 'Quantity')); ?> <span class="req">*</span></label>
                        <input type="number" id="pbQty" class="pb-input" step="0.01" min="0" inputmode="decimal" value="1" placeholder="1">
                    </div>
                </div>
                <div class="er-rate-grid">
                    <div class="er-rate er-rate-buy">
                        <label for="pbBuy"><?php echo $escape($pbt('ক্রয় রেট (৳)', 'Buy rate')); ?> <span class="req">*</span></label>
                        <input type="number" id="pbBuy" class="pb-input" step="0.01" min="0" inputmode="decimal" placeholder="0.00">
                    </div>
                    <div class="er-rate er-rate-ws">
                        <label for="pbWholesale"><?php echo $escape($pbt('পাইকারি রেট (৳)', 'Wholesale rate')); ?></label>
                        <div class="pb-pair">
                            <input type="number" id="pbWholesale" class="pb-input" step="0.01" min="0" inputmode="decimal" placeholder="0.00">
                            <select id="pbWholesaleMode" class="pb-input pb-mode"><option value="tk">৳</option><option value="pct">%</option></select>
                        </div>
                    </div>
                    <div class="er-rate er-rate-retail">
                        <label for="pbRetail"><?php echo $escape($pbt('খুচরা রেট (৳)', 'Retail rate')); ?></label>
                        <div class="pb-pair">
                            <input type="number" id="pbRetail" class="pb-input" step="0.01" min="0" inputmode="decimal" placeholder="0.00">
                            <select id="pbRetailMode" class="pb-input pb-mode"><option value="tk">৳</option><option value="pct">%</option></select>
                        </div>
                    </div>
                    <div class="er-rate er-rate-dealer">
                        <label for="pbDealer"><?php echo $escape($pbt('ডিলার রেট (৳)', 'Dealer rate')); ?></label>
                        <div class="pb-pair">
                            <input type="number" id="pbDealer" class="pb-input" step="0.01" min="0" inputmode="decimal" placeholder="0.00">
                            <select id="pbDealerMode" class="pb-input pb-mode"><option value="tk">৳</option><option value="pct">%</option></select>
                        </div>
                    </div>
                    <button type="button" id="pbAddBtn" class="er-add">+ <?php echo $escape($pbt('পণ্য যোগ করুন', 'Add product')); ?></button>
                </div>
                <div class="er-entry-grid" style="margin-top:10px;grid-template-columns:repeat(3,minmax(0,1fr));">
                    <div class="er-field">
                        <label for="pbFree"><?php echo $escape($pbt('ফ্রির পরিমাণ', 'Free qty')); ?></label>
                        <input type="number" id="pbFree" class="pb-input" step="0.01" min="0" inputmode="decimal">
                    </div>
                    <div class="er-field">
                        <label for="pbLineTotal"><?php echo $escape($pbt('মোট ক্রয় মূল্য', 'Line total')); ?></label>
                        <input type="number" id="pbLineTotal" class="pb-input" step="0.01" min="0" inputmode="decimal">
                    </div>
                    <label class="pb-check" style="margin-top:22px;">
                        <input type="checkbox" id="pbFreeItem">
                        <span><?php echo $escape($pbt('বিনা মূল্যের পণ্য', 'Free of cost')); ?></span>
                    </label>
                </div>
                <p id="pbEntryMsg" class="pb-msg" hidden></p>
            </section>

            <section class="er-card er-cart-card">
                <h2 class="er-title"># <?php echo $escape($pbt('যোগ করা পণ্যের তালিকা', 'Added products')); ?> <span id="erCartCount">(0 <?php echo $escape($pbt('টি পণ্য', 'items')); ?>)</span></h2>
                <div class="pb-table-wrap">
                    <table class="pb-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th class="pb-col-name"><?php echo $escape($pbt('পণ্যের নাম', 'Product')); ?></th>
                                <th><?php echo $escape($pbt('কোড', 'Code')); ?></th>
                                <th><?php echo $escape($pbt('ইউনিট', 'Unit')); ?></th>
                                <th><?php echo $escape($pbt('পরিমাণ', 'Qty')); ?></th>
                                <th><?php echo $escape($pbt('ফ্রি', 'Free')); ?></th>
                                <th><?php echo $escape($pbt('ক্রয় মূল্য', 'Buy')); ?></th>
                                <th><?php echo $escape($pbt('মোট মূল্য', 'Total')); ?></th>
                                <th><?php echo $escape($pbt('পাইকারি রেট', 'Wholesale')); ?></th>
                                <th><?php echo $escape($pbt('খুচরা রেট', 'Retail')); ?></th>
                                <th><?php echo $escape($pbt('ডিলার রেট', 'Dealer')); ?></th>
                                <th class="pb-col-x"><?php echo $escape($pbt('বাতিল', 'Remove')); ?></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody">
                            <tr><td colspan="12" class="pc-empty-cart"><?php echo $escape($words['search_add_hint']); ?></td></tr>
                        </tbody>
                        <tfoot id="cartFoot" hidden>
                            <tr>
                                <td colspan="7" class="pb-foot-label" id="amePcCartSumLabel"><?php echo $escape($words['product_total']); ?></td>
                                <td class="pb-num" id="cartFootTotal">৳0.00</td>
                                <td class="pb-num" id="cartFootWholesale">৳0.00</td>
                                <td class="pb-num" id="cartFootRetail">৳0.00</td>
                                <td class="pb-num" id="cartFootDealer">৳0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <div class="er-bottom">
                <section class="er-card er-extra">
                    <h2 class="er-title"><?php echo $escape($pbt('অতিরিক্ত তথ্য', 'Extra details')); ?></h2>
                    <div class="er-extra-grid">
                        <div class="er-field">
                            <label for="pbDiscPct"><?php echo $escape($pbt('ছাড় (%)', 'Discount %')); ?></label>
                            <input type="number" id="pbDiscPct" class="pb-input" step="0.01" min="0" max="100" inputmode="decimal">
                        </div>
                        <div class="er-field">
                            <label for="pbDiscAmt"><?php echo $escape($pbt('ছাড়ের পরিমাণ', 'Discount amount')); ?></label>
                            <input type="number" id="pbDiscAmt" class="pb-input" step="0.01" min="0" inputmode="decimal">
                        </div>
                        <div class="er-field">
                            <label for="pbRounding"><?php echo $escape($pbt('রাউন্ড', 'Round')); ?></label>
                            <input type="number" id="pbRounding" class="pb-input" step="0.01" inputmode="decimal">
                        </div>
                        <div class="er-field">
                            <label for="pbSpecial"><?php echo $escape($pbt('স্পেশাল ছাড়', 'Special discount')); ?></label>
                            <input type="number" id="pbSpecial" class="pb-input" step="0.01" min="0" inputmode="decimal">
                        </div>
                    </div>
                    <details class="pb-more" id="pbExpenseBox" style="margin-top:10px;">
                        <summary><?php echo $escape($words['extra_expense']); ?> <span id="pbExpenseSummary"></span></summary>
                        <div class="pb-row-exp">
                            <select id="expenseType" class="pb-input">
                                <option value="<?php echo $escape($words['transport']); ?>"><?php echo $escape($words['transport']); ?></option>
                                <option value="<?php echo $escape($words['labor']); ?>"><?php echo $escape($words['labor']); ?></option>
                                <option value="<?php echo $escape($words['loading_unloading']); ?>"><?php echo $escape($words['loading_unloading']); ?></option>
                                <option value="<?php echo $escape($words['packaging']); ?>"><?php echo $escape($words['packaging']); ?></option>
                                <option value="<?php echo $escape($words['other']); ?>"><?php echo $escape($words['other']); ?></option>
                            </select>
                            <input type="number" id="expenseAmount" class="pb-input" min="0" step="0.01" placeholder="<?php echo $escape($words['expense_taka']); ?>">
                            <button type="button" class="pb-btn pb-btn-green" onclick="addExpense()"><?php echo $escape($words['add_expense']); ?></button>
                        </div>
                        <div id="expenseList"></div>
                    </details>
                    <div class="er-field" style="margin-top:10px;">
                        <label for="notesInput"><?php echo $escape($pbt('নোট', 'Note')); ?></label>
                        <textarea id="notesInput" class="pb-input pb-notes" rows="2" maxlength="500" placeholder="<?php echo $escape($pbt('এখানে নোট লিখুন...', 'Write a note...')); ?>"></textarea>
                    </div>
                </section>

                <div class="er-side">
                <section class="er-card er-sum">
                    <h2 class="er-title"><?php echo $escape($pbt('সারাংশ', 'Summary')); ?></h2>
                    <input type="hidden" id="subtotalDisplay" value="0.00">
                    <input type="hidden" id="expenseDisplay" value="0.00">
                    <input type="hidden" id="pbAfterDisc" value="0.00">
                    <input type="hidden" id="invoiceTotalDisplay" value="0.00">
                    <div class="er-sum-row"><span><?php echo $escape($pbt('মোট পণ্য', 'Goods')); ?> (<span id="erSumCount">0</span>)</span><strong id="erSubtotalText">৳0.00</strong></div>
                    <div class="er-sum-row"><span><?php echo $escape($pbt('মোট ছাড়', 'Discount')); ?></span><strong id="erDiscDisplay">৳0.00</strong></div>
                    <div class="er-sum-row" id="pbExpenseLine"><span><?php echo $escape($words['extra_expense']); ?></span><strong id="erExpenseText">৳0.00</strong></div>
                    <div class="er-sum-row"><span><?php echo $escape($pbt('ছাড়ের পর', 'After discount')); ?></span><strong id="erAfterDiscText">৳0.00</strong></div>
                    <div class="er-sum-row er-sum-net"><span><?php echo $escape($pbt('সর্বমোট পরিশোধযোগ্য', 'Net payable')); ?></span><span id="erNetText">৳0.00</span></div>
                </section>

                <div id="amePcCustAdj" class="ame-pc-cust-adj" hidden>
                    <p class="ame-pc-cust-adj-note">কাস্টমার ক্রয় সমন্বয় চালু · ১০.৯৮.৪৯৫ · পুরোনো মেমোসহ</p>
                    <div class="ame-pc-cust-adj-grid">
                        <div class="ame-pc-cust-adj-box c1"><span>সাবেক পাওনা</span><strong id="amePcAdjPrev">৳0.00</strong></div>
                        <div class="ame-pc-cust-adj-box c2"><span>আজকের ক্রয়</span><strong id="amePcAdjBill">৳0.00</strong></div>
                        <div class="ame-pc-cust-adj-box c3"><span>অবশিষ্ট পাওনা</span><strong id="amePcAdjRemain">৳0.00</strong></div>
                    </div>
                </div>
                <section class="er-card er-pay" id="pbPayCard">
                    <h2 class="er-title" id="pbPayTitle"><?php echo $escape($pbt('পেমেন্ট', 'Payment')); ?></h2>
                    <section class="pb-card ame-paydesk" id="amePayDesk">
                        <label class="ame-paydesk-field">
                            <span><?php echo $escape($pbt('অ্যাকাউন্ট সিলেক্ট', 'Account select')); ?></span>
                            <select id="accountSelect" class="ame-account-select pb-input" name="purchase_pay_account" data-ame-account-select="1" data-ame-direction="pay" aria-label="<?php echo $escape($pbt('অ্যাকাউন্ট সিলেক্ট', 'Account select')); ?>">
                                <option value=""><?php echo $escape($pbt('বাকিতে রাখুন', 'Keep on credit')); ?></option>
                                <?php foreach ($account_list as $a):
                                    $ame_pay_id = (int)($a['id'] ?? 0);
                                    $ame_pay_name = trim((string)($a['account_name'] ?? ''));
                                    $ame_pay_type = trim((string)($a['account_type'] ?? ''));
                                    $ame_pay_label = $ame_pay_name . ($ame_pay_type !== '' ? (' · ' . $ame_pay_type) : '');
                                    ?>
                                    <option value="<?php echo $ame_pay_id; ?>" data-type="<?php echo $escape($ame_pay_type); ?>"<?php echo ($ame_pay_id === $default_account_id) ? ' selected' : ''; ?>><?php echo $escape($ame_pay_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="ame-pay-acc-grid" id="amePayAccGrid" role="listbox" aria-label="<?php echo $escape($pbt('খোলা অ্যাকাউন্ট', 'Open accounts')); ?>">
                            <button type="button" class="ame-pay-acc is-due<?php echo $default_account_id < 1 ? ' is-picked' : ''; ?>" data-id="" role="option"><?php echo $escape($pbt('বাকিতে রাখুন', 'Keep on credit')); ?></button>
                            <?php foreach ($account_list as $a):
                                $ame_pay_id = (int)($a['id'] ?? 0);
                                $ame_pay_name = trim((string)($a['account_name'] ?? ''));
                                $ame_pay_type = trim((string)($a['account_type'] ?? ''));
                                $ame_pay_label = $ame_pay_name . ($ame_pay_type !== '' ? (' · ' . $ame_pay_type) : '');
                                $ame_pay_cls = $ame_pay_tone($ame_pay_name, $ame_pay_type);
                                ?>
                                <button type="button" class="ame-pay-acc <?php echo $escape($ame_pay_cls); ?><?php echo ($ame_pay_id === $default_account_id) ? ' is-picked' : ''; ?>" data-id="<?php echo $ame_pay_id; ?>" role="option"><?php echo $escape($ame_pay_label); ?></button>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!$account_list): ?>
                        <p class="ame-pay-acc-hint" id="amePayAccEmpty"><?php echo $escape($pbt('কোনো খোলা অ্যাকাউন্ট নেই। আগে ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্ট খুলুন।', 'No open account. Open a cash, bank, or mobile account first.')); ?></p>
                        <?php else: ?>
                        <p class="ame-pay-acc-hint" id="amePayAccHint"><?php echo $escape($pbt('খোলা অ্যাকাউন্ট ' . count($account_list) . 'টি — ক্যাশ, ব্যাংক, মোবাইল সব এখানে।', count($account_list) . ' open accounts — cash, bank, and mobile are all here.')); ?></p>
                        <?php endif; ?>
                        <label class="ame-paydesk-field">
                            <span><?php echo $escape($pbt('পেমেন্ট অ্যামাউন্ট', 'Payment amount')); ?></span>
                            <input type="number" step="0.01" min="0" id="paidAmount" class="pb-input pb-edit" value="0" placeholder="0" inputmode="decimal" aria-label="<?php echo $escape($pbt('পেমেন্ট অ্যামাউন্ট', 'Payment amount')); ?>">
                        </label>
                        <label class="ame-paydesk-field">
                            <span id="pbDueCaption"><?php echo $escape($pbt('বাকি (+)', 'Due (+)')); ?></span>
                            <input type="text" id="pbDueDisplay" class="pb-input" value="0.00" readonly aria-label="<?php echo $escape($pbt('বাকি', 'Due')); ?>">
                        </label>
                        <div class="pb-latest ame-paydesk-latest" id="pbLatestBox" aria-live="polite">
                            <span id="pbLatestLabel"><?php echo $escape($pbt('সর্বশেষ ব্যালেন্স', 'Latest balance')); ?></span>
                            <strong id="pbLatestBalance">৳0.00</strong>
                        </div>
                        <p class="pb-pay-note"><?php echo $escape($pbt('পেমেন্টের ঘরে টাকা দিলে মাইনাস (−)। টাকা না দিলে এই বিল প্লাস (+) হয়ে বাকিতে যোগ হবে। ঘরে সবসময় ০ থাকে।', 'Money in the payment box is minus. Leaving it at zero adds this bill as plus.')); ?></p>
                        <style>
                        html body #main-content #pbPage .er-bottom{display:flex !important;flex-direction:column !important;grid-template-columns:1fr !important}
                        html body #main-content #pbPage #pbPayCard{background:#ffffff !important;border:2px solid #0f766e !important;width:100% !important;box-sizing:border-box !important}
                        html body #main-content #pbPage #pbPayCard .ame-paydesk{display:flex !important;flex-direction:column !important;gap:10px !important;background:#fff !important;border-radius:14px !important;padding:12px !important;width:100% !important}
                        html body #main-content #pbPage #pbPayCard .ame-paydesk-field{display:flex !important;flex-direction:column !important;gap:4px !important;width:100% !important;margin:0 !important}
                        html body #main-content #pbPage #pbPayCard .ame-paydesk-field > span{font-weight:800 !important;font-size:14px !important;color:#111827 !important;-webkit-text-fill-color:#111827 !important}
                        html body #main-content #pbPage #pbPayCard .ame-paydesk-field select,
                        html body #main-content #pbPage #pbPayCard .ame-paydesk-field input{width:100% !important;min-height:48px !important;font-size:18px !important;font-weight:800 !important;box-sizing:border-box !important}
                        html body #main-content #pbPage #pbPayCard .ame-pay-acc-grid{display:grid !important;grid-template-columns:1fr 1fr !important;gap:8px !important;width:100% !important;margin:0 !important}
                        html body #main-content #pbPage #pbPayCard .ame-pay-acc{min-height:44px !important;padding:8px 10px !important;border-radius:12px !important;border:1px solid #cbd5e1 !important;background:#f8fafc !important;color:#0f172a !important;-webkit-text-fill-color:#0f172a !important;font:inherit !important;font-weight:800 !important;font-size:14px !important;cursor:pointer !important}
                        html body #main-content #pbPage #pbPayCard .ame-pay-acc.is-cash{background:#eff6ff !important;border-color:#2563eb !important;color:#1d4ed8 !important;-webkit-text-fill-color:#1d4ed8 !important}
                        html body #main-content #pbPage #pbPayCard .ame-pay-acc.is-bank{background:#ecfdf5 !important;border-color:#059669 !important;color:#047857 !important;-webkit-text-fill-color:#047857 !important}
                        html body #main-content #pbPage #pbPayCard .ame-pay-acc.is-bkash{background:#fdf2f8 !important;border-color:#e2136e !important;color:#be185d !important;-webkit-text-fill-color:#be185d !important}
                        html body #main-content #pbPage #pbPayCard .ame-pay-acc.is-nagad{background:#fff7ed !important;border-color:#ea580c !important;color:#c2410c !important;-webkit-text-fill-color:#c2410c !important}
                        html body #main-content #pbPage #pbPayCard .ame-pay-acc.is-rocket{background:#f5f3ff !important;border-color:#7c3aed !important;color:#6d28d9 !important;-webkit-text-fill-color:#6d28d9 !important}
                        html body #main-content #pbPage #pbPayCard .ame-pay-acc.is-due{background:#fef2f2 !important;border-color:#dc2626 !important;color:#b91c1c !important;-webkit-text-fill-color:#b91c1c !important}
                        html body #main-content #pbPage #pbPayCard .ame-pay-acc.is-other{background:#f8fafc !important;border-color:#003f8f !important;color:#003f8f !important;-webkit-text-fill-color:#003f8f !important}
                        html body #main-content #pbPage #pbPayCard .ame-pay-acc.is-picked{box-shadow:inset 0 0 0 2px currentColor !important}
                        html body #main-content #pbPage #pbPayCard .ame-pay-acc-hint{margin:0 !important;font-size:13px !important;font-weight:700 !important;color:#3f3f46 !important}
                        html body #main-content #pbPage #pbPayCard #pbDueDisplay{text-align:right !important;font-size:26px !important;background:#fff7ed !important}
                        html body #main-content #pbPage #pbPayCard .ame-paydesk-quick{display:grid !important;grid-template-columns:1fr 1fr !important;gap:8px !important}
                        html body #main-content #pbPage #pbPayCard .ame-paydesk-btn{min-height:48px !important;border:0 !important;border-radius:12px !important;font:inherit !important;font-weight:800 !important;font-size:16px !important;color:#fff !important;-webkit-text-fill-color:#fff !important;background:#0f766e !important;text-decoration:none !important;cursor:pointer}
                        html body #main-content #pbPage #pbPayCard .ame-paydesk-btn.is-due{background:#b45309 !important}
                        html body #main-content #pbPage #pbPayCard .pb-latest{display:flex !important;justify-content:space-between !important;align-items:center !important;gap:8px !important;margin:0 !important;padding:12px 14px !important;border-radius:14px !important;background:#fff7ed !important;border:1px solid #fdba74 !important}
                        html body #main-content #pbPage #pbPayCard .pb-latest span{font-weight:800 !important;color:#9a3412 !important;-webkit-text-fill-color:#9a3412 !important}
                        html body #main-content #pbPage #pbPayCard .pb-latest strong{font-size:26px !important;color:#9a3412 !important;-webkit-text-fill-color:#9a3412 !important}
                        html body #main-content #pbPage #pbPayCard .pb-latest.is-adv{background:#ecfdf5 !important;border-color:#86efac !important}
                        html body #main-content #pbPage #pbPayCard .pb-latest.is-adv span,html body #main-content #pbPage #pbPayCard .pb-latest.is-adv strong{color:#166534 !important;-webkit-text-fill-color:#166534 !important}
                        html body #main-content #pbPage #pbPayCard .pb-latest.is-zero{background:#f8fafc !important;border-color:#e2e8f0 !important}
                        html body #main-content #pbPage #pbPayCard .pb-latest.is-zero span,html body #main-content #pbPage #pbPayCard .pb-latest.is-zero strong{color:#334155 !important;-webkit-text-fill-color:#334155 !important}
                        html body #main-content #pbPage #pbPayCard .pb-pay-note{margin:0 !important;font-size:13px !important;font-weight:700 !important;color:#3f3f46 !important;-webkit-text-fill-color:#3f3f46 !important}
                        @media (max-width:1100px){html body #main-content #pbPage .er-bottom{grid-template-columns:1fr !important}}
                        </style>

        <details class="pb-ledger" id="pbLedger" open>
                            <summary>
                                <span id="amePcGrandCaption"><?php echo $escape($words['grand_balance']); ?></span>
                                <strong id="grandTotalDisplay">৳0.00</strong>
                            </summary>
                            <div class="ame-pc-memo-balance" id="amePcMemoBalance" aria-live="polite">
                                <div class="ame-pc-mb-row" id="amePcMbPrevRow"><span id="amePcMbPrevLabel"><?php echo $escape($words['previous_due']); ?></span><strong id="amePcMbPrev">৳0.00</strong></div>
                                <div class="ame-pc-mb-row" id="amePcMbBillRow"><span><?php echo $escape($words['this_invoice']); ?></span><strong id="amePcMbBill">৳0.00</strong></div>
                                <div class="ame-pc-mb-row" id="amePcMbPaidRow"><span><?php echo $escape($words['payment']); ?> (−)</span><strong id="amePcMbPaid">৳0.00</strong></div>
                                <div class="ame-pc-mb-row ame-pc-mb-total" id="amePcMbTotalRow"><span id="amePcMbTotalLabel"><?php echo $escape($words['grand_balance']); ?></span><strong id="amePcMbTotal">৳0.00</strong></div>
                            </div>
                            <span id="prevDueLabel" hidden><?php echo $escape($words['previous_due']); ?></span>
                            <input type="hidden" id="prevDueDisplay" value="0.00">
                            <input type="hidden" id="amePcSettleType" value="adjustment">
                            <input type="hidden" id="amePcSettleReason" value="">
                            <input type="hidden" id="totalPayableDisplay" value="0.00">
                            <input type="hidden" id="remainBalDisplay" value="0.00">
                            <input type="hidden" id="billDueDisplay" value="0.00">
                            <input type="hidden" id="newBalDisplay" value="0.00">
                            <p id="amePcPrevDueHint" class="ame-pc-memo-hint" hidden></p>
                            <p class="ame-pc-ledger-formula" id="amePcLedgerFormula" hidden><?php echo $escape($words['ledger_formula']); ?></p>
                            <span id="billDueLabel" hidden><?php echo $escape($words['this_invoice']); ?></span>
                            <span id="newBalLabel" hidden><?php echo $escape($words['current']); ?></span>
                        </details>

                        <div class="pb-submit-row">
                            <button type="button" class="pb-btn pb-btn-green" id="amePcCompleteBtn"
                                data-complete-label="✔ <?php echo $escape($pbt('ক্রয় সম্পন্ন করুন', 'Complete purchase')); ?>"
                                data-draft-label="<?php echo $escape($words['save_draft']); ?>">✔ <?php echo $escape($pbt('ক্রয় সম্পন্ন করুন', 'Complete purchase')); ?></button>
                        </div>
                    </section>
                </section>
                </div>
            </div>

            <div class="er-actions">
                <a class="er-btn er-btn-gray" href="history.php">← <?php echo $escape($pbt('তালিকায় ফিরুন', 'Back to list')); ?></a>
                <button type="button" class="er-btn er-btn-red" id="pbClearAll">🗑 <?php echo $escape($pbt('সব মুছে ফেলুন', 'Clear all')); ?></button>
                <button type="button" class="er-btn er-btn-blue" id="pbDraftSaveVisible">💾 <?php echo $escape($pbt('ড্রাফট সংরক্ষণ', 'Save draft')); ?></button>
                <button type="button" class="er-btn er-btn-gray" id="pbDraftListVisible">📄 <?php echo $escape($words['draft_list']); ?></button>
                <button type="button" class="er-btn er-btn-green" id="pbCompleteLaunch">✓ <?php echo $escape($pbt('ক্রয় সম্পন্ন করুন', 'Complete purchase')); ?></button>
            </div>

            <input type="hidden" id="erSubtotalRaw" value="0">
        </form>
    </div>
</div>

<div id="completeDrawer" class="pos-draft-drawer" aria-hidden="true">
    <section class="pos-draft-panel pos-recent-modal" role="dialog" aria-modal="true">
        <header class="pos-recent-modal-header">
            <h3><?php echo $escape($words['recent_purchases_complete']); ?></h3>
        </header>
        <div class="pos-recent-table-wrap">
            <table class="pos-recent-table">
                <colgroup>
                    <col class="pos-recent-col-sl">
                    <col class="pos-recent-col-time">
                    <col class="pos-recent-col-inv">
                    <col class="pos-recent-col-cust">
                    <col class="pos-recent-col-price">
                    <col class="pos-recent-col-ico">
                </colgroup>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $escape($words['time']); ?></th>
                        <th><?php echo $escape($words['memo']); ?></th>
                        <th><?php echo $escape($words['supplier']); ?></th>
                        <th><?php echo $escape($words['price']); ?></th>
                        <th><?php echo $escape($words['view']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recent_purchases): ?>
                        <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--pos-c-64748b, #64748b);"><?php echo $escape($words['no_purchase_yet']); ?></td></tr>
                    <?php else: foreach ($recent_purchases as $i => $rp): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo $escape($rp['time']); ?></td>
                            <td><?php echo $escape($rp['invoice']); ?></td>
                            <td><?php echo $escape($rp['supplier']); ?></td>
                            <td>৳<?php echo number_format((float)$rp['total'], 2); ?></td>
                            <td>
                                <?php if (!empty($rp['invoice'])): ?>
                                    <a class="pos-recent-icon-btn" href="memo_digital.php?memo=<?php echo urlencode((string)$rp['invoice']); ?>&amp;supplier_id=<?php echo (int)($rp['supplier_id'] ?? 0); ?>" target="_blank" rel="noopener">🖨️</a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <footer class="pos-recent-modal-footer">
            <a href="history.php" class="pos-draft-history-link"><?php echo $escape($words['all_history']); ?></a>
            <button type="button" class="pos-recent-close-btn" onclick="amePcCloseDrawers()"><?php echo $escape($words['close']); ?></button>
        </footer>
    </section>
</div>

<div id="draftDrawer" class="pos-draft-drawer" aria-hidden="true">
    <section class="pos-draft-panel pos-recent-modal" role="dialog" aria-modal="true">
        <header class="pos-recent-modal-header">
            <h3><?php echo $escape($words['recent_purchases_draft']); ?></h3>
        </header>
        <div class="pos-recent-table-wrap">
            <table class="pos-recent-table">
                <colgroup>
                    <col class="pos-recent-col-sl">
                    <col class="pos-recent-col-time">
                    <col class="pos-recent-col-inv">
                    <col class="pos-recent-col-cust">
                    <col class="pos-recent-col-price">
                    <col class="pos-recent-col-ico">
                </colgroup>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo $escape($words['time']); ?></th>
                        <th><?php echo $escape($words['number']); ?></th>
                        <th><?php echo $escape($words['supplier']); ?></th>
                        <th><?php echo $escape($words['price']); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="pcDraftList"></tbody>
            </table>
        </div>
        <footer class="pos-recent-modal-footer">
            <button type="button" class="pos-recent-close-btn" onclick="amePcCloseDrawers()"><?php echo $escape($words['close']); ?></button>
        </footer>
    </section>
</div>

<div id="quickAddOverlay" class="ame-desk-overlay" aria-hidden="true">
    <div class="ame-desk-overlay-card" style="background:var(--brand-white, #fff); border-radius:16px; padding:20px; max-width:420px; width:100%; max-height:90vh; overflow-y:auto;" onclick="event.stopPropagation()">
        <h3 style="margin:0 0 4px; color:var(--brand-navy, #0B1E4D); font-size:16px;"><?php echo $escape($words['add_new_product_title']); ?></h3>
        <p style="margin:0 0 14px; color:var(--pos-c-94a3b8, #94A3B8); font-size:11.5px;"><?php echo $escape($words['add_new_product_hint']); ?></p>
        <div id="quickAddError" style="display:none; background:var(--pos-c-ffebee, #FFEBEE); color:var(--pos-c-c62828, #C62828); padding:10px 12px; border-radius:8px; font-size:12px; margin-bottom:12px;"></div>
        <div class="ame-pos-calc-row" style="grid-template-columns:1fr;"><label><?php echo $escape($words['product_name_req']); ?></label><input type="text" id="qaName" style="text-align:left;"></div>
        <div class="ame-pos-calc-row"><label><?php echo $escape($words['purchase_price_req']); ?></label><input type="number" id="qaPurchase" step="0.01" min="0.01"></div>
        <div class="ame-pos-calc-row"><label><?php echo $escape($words['dealer']); ?></label><input type="number" id="qaDealer" step="0.01" min="0" value="0"></div>
        <div class="ame-pos-calc-row"><label><?php echo $escape($words['wholesale']); ?></label><input type="number" id="qaWholesale" step="0.01" min="0" value="0"></div>
        <div class="ame-pos-calc-row"><label><?php echo $escape($words['retail_req']); ?></label><input type="number" id="qaRetail" step="0.01" min="0.01"></div>
        <div style="display:flex; gap:8px; margin-top:14px;">
            <button type="button" onclick="closeQuickAddModal()" style="flex:1; padding:12px; border:none; border-radius:10px; background:var(--pos-c-f1f5f9, #F1F5F9); color:var(--pos-c-64748b, #64748B); font-weight:800; cursor:pointer;"><?php echo $escape($words['cancel']); ?></button>
            <button type="button" onclick="submitQuickAdd()" id="qaSubmitBtn" style="flex:1; padding:12px; border:none; border-radius:10px; background:var(--brand-success, #0E8F52); color:var(--brand-white, #fff); font-weight:800; cursor:pointer;"><?php echo $escape($words['create_and_add']); ?></button>
        </div>
    </div>
</div>

<div id="quickAddSupplierOverlay" class="ame-desk-overlay" aria-hidden="true">
    <div class="ame-desk-overlay-card" style="background:var(--brand-white, #fff); border-radius:16px; padding:20px; max-width:420px; width:100%; max-height:90vh; overflow-y:auto;" onclick="event.stopPropagation()">
        <h3 style="margin:0 0 4px; color:var(--brand-navy, #0B1E4D); font-size:16px;"><?php echo $escape($words['add_new_supplier_title']); ?></h3>
        <p style="margin:0 0 14px; color:var(--pos-c-94a3b8, #94A3B8); font-size:11.5px;"><?php echo $escape($words['add_new_supplier_hint']); ?></p>
        <div id="quickAddSupplierError" style="display:none; background:var(--pos-c-ffebee, #FFEBEE); color:var(--pos-c-c62828, #C62828); padding:10px 12px; border-radius:8px; font-size:12px; margin-bottom:12px;"></div>
        <div class="ame-pos-calc-row" style="grid-template-columns:1fr;"><label><?php echo $escape($words['name_req']); ?></label><input type="text" id="qsName" style="text-align:left;"></div>
        <div class="ame-pos-calc-row" style="grid-template-columns:1fr;"><label><?php echo $escape($words['mobile']); ?></label><input type="text" id="qsPhone" style="text-align:left;"></div>
        <div class="ame-pos-calc-row" style="grid-template-columns:1fr;"><label><?php echo $escape($words['address']); ?></label><input type="text" id="qsAddress" style="text-align:left;"></div>
        <div class="ame-pos-calc-row" style="grid-template-columns:1fr;"><label><?php echo $escape($words['previous_balance']); ?></label><input type="number" step="0.01" id="qsOpening" value="0" style="text-align:left;"></div>
        <p style="margin:4px 0 0;font-size:12px;font-weight:700;color:#1F2937;"><?php echo $escape($words['previous_balance_hint']); ?></p>
        <div style="display:flex; gap:8px; margin-top:14px;">
            <button type="button" onclick="closeQuickAddSupplierModal()" style="flex:1; padding:12px; border:none; border-radius:10px; background:var(--pos-c-f1f5f9, #F1F5F9); color:var(--pos-c-64748b, #64748B); font-weight:800; cursor:pointer;"><?php echo $escape($words['cancel']); ?></button>
            <button type="button" onclick="submitQuickAddSupplier()" id="qsSubmitBtn" style="flex:1; padding:12px; border:none; border-radius:10px; background:var(--brand-success, #0E8F52); color:var(--brand-white, #fff); font-weight:800; cursor:pointer;"><?php echo $escape($words['create_and_select']); ?></button>
        </div>
    </div>
</div>

<div id="voidMemoPayOverlay" class="ame-desk-overlay" aria-hidden="true">
    <div class="ame-desk-overlay-card" style="background:var(--brand-white, #fff); border-radius:16px; padding:20px; max-width:420px; width:100%; max-height:90vh; overflow-y:auto;" onclick="event.stopPropagation()">
        <h3 style="margin:0 0 4px; color:var(--brand-navy, #0B1E4D); font-size:16px;"><?php echo $escape($words['void_pay_title']); ?></h3>
        <p style="margin:0 0 14px; color:var(--pos-c-94a3b8, #94A3B8); font-size:11.5px;"><?php echo $escape($words['void_pay_hint']); ?></p>
        <div id="voidMemoPayError" style="display:none; background:var(--pos-c-ffebee, #FFEBEE); color:var(--pos-c-c62828, #C62828); padding:10px 12px; border-radius:8px; font-size:12px; margin-bottom:12px;"></div>
        <div class="ame-pos-calc-row" style="grid-template-columns:1fr;"><label><?php echo $escape($words['void_pay_reason']); ?></label><textarea id="voidPayReason" rows="3" style="text-align:left;width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;font:inherit;"></textarea></div>
        <div style="display:flex; gap:8px; margin-top:14px;">
            <button type="button" onclick="closeVoidMemoPayModal()" style="flex:1; padding:12px; border:none; border-radius:10px; background:var(--pos-c-f1f5f9, #F1F5F9); color:var(--pos-c-64748b, #64748B); font-weight:800; cursor:pointer;"><?php echo $escape($words['cancel']); ?></button>
            <button type="button" onclick="submitVoidMemoPay()" id="voidPaySubmitBtn" style="flex:1; padding:12px; border:none; border-radius:10px; background:#b91c1c; color:#fff; font-weight:800; cursor:pointer;"><?php echo $escape($words['void_pay_confirm']); ?></button>
        </div>
    </div>
</div>

<?php if (!empty($ame_pos_title_can_edit)): ?>
<div class="ame-pos-title-edit" id="amePosTitleEdit" hidden>
    <div class="ame-pos-title-edit-card">
        <h3><?php echo $escape($words['pos_title_edit_heading']); ?></h3>
        <p><?php echo $escape($words['pos_title_edit_hint']); ?></p>
        <textarea id="amePosTitleInput" maxlength="300"><?php echo $escape($ame_pos_title_line); ?></textarea>
        <div class="ame-pos-title-edit-msg" id="amePosTitleMsg"></div>
        <button type="button" class="ame-pos-title-save" onclick="ameSavePosTitleLine()"><?php echo $escape($words['save']); ?></button>
        <button type="button" class="ame-pos-title-cancel" onclick="ameClosePosTitleEdit()"><?php echo $escape($words['close']); ?></button>
    </div>
</div>
<?php endif; ?>

<script>
<?php
// পণ্য/সাপ্লায়ারের নামে কেউ যদি ভুলবশত বা ইচ্ছাকৃতভাবে স্ক্রিপ্ট-ট্যাগ বন্ধ করার
// কোড বা HTML-জাতীয় লেখা রেখে দেয়, সেটা যেন এই ইনলাইন স্ক্রিপ্ট ব্লক ভেঙে
// অন্য কোড চালাতে না পারে —
// তাই JSON_HEX_* ফ্ল্যাগ যোগ করা হলো (মান/অর্থ একই থাকে, শুধু <>&'" এস্কেপ হয়)।
$ame_json_flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
var PRODUCTS = <?php echo json_encode($product_list, $ame_json_flags); ?>;
var SUPPLIERS = <?php echo json_encode($supplier_list, $ame_json_flags); ?>;
var AME_PC_L = <?php echo json_encode($words, $ame_json_flags); ?>;
var AME_EDIT_MEMO = <?php echo $ame_edit_payload ? json_encode($ame_edit_payload, $ame_json_flags) : 'null'; ?>;
var cart = [];
var expenses = [];
var selectedSupplierDue = 0;
var selectedPartyType = 'supplier';
var selectedCustomerId = 0;
var selectedCustomerBalance = 0;
function amePcSlimPartyBoxes() {
    var isC = selectedPartyType === 'customer';
    var led = document.getElementById('pbLedger');
    var adj = document.getElementById('amePcCustAdj');
    if (led) {
        led.classList.toggle('is-customer-slim', isC);
        led.hidden = isC;
    }
    if (adj) {
        adj.hidden = !isC;
    }
}
var amePcDraftBusy = false;
var autoPaySynced = false;
var memoIsDuplicate = false;
var memoCheckTimer = null;

/**
 * পণ্য/সাপ্লায়ারের নাম, SKU ইত্যাদি (যা ডাটাবেজ থেকে আসে, ব্যবহারকারীর
 * টাইপ করা তথ্য থেকে তৈরি) সরাসরি innerHTML-এ বসানোর আগে HTML-এস্কেপ করার
 * জন্য — যাতে নামের মধ্যে < > & " ' থাকলেও তা টেক্সট হিসেবেই দেখা যায়,
 * পেজের কোড হিসেবে চলে না যায় (XSS প্রতিরোধ)।
 */
function ameEsc(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function amePcCsrf() {
    return window.AME_CSRF_TOKEN || (document.querySelector('input[name=csrf_token]') || {}).value || '';
}
function amePcMode() {
    return document.getElementById('amePcMode').value === 'draft' ? 'draft' : 'complete';
}
function amePcHideFlyout() {
    var fly = document.getElementById('amePosRecentFlyout');
    var toggle = document.getElementById('amePosRecentToggle');
    if (!fly) { return; }
    fly.classList.remove('is-open');
    fly.setAttribute('hidden', 'hidden');
    if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
}
function amePcOpenFlyout() {
    var fly = document.getElementById('amePosRecentFlyout');
    var toggle = document.getElementById('amePosRecentToggle');
    if (!fly) { return; }
    fly.classList.add('is-open');
    fly.removeAttribute('hidden');
    if (toggle) { toggle.setAttribute('aria-expanded', 'true'); }
}
function amePcSetMode(mode) {
    var isDraft = mode === 'draft';
    document.getElementById('amePcMode').value = isDraft ? 'draft' : 'complete';
    var modeLabel = document.getElementById('amePosRecentCurrent');
    if (modeLabel) { modeLabel.textContent = isDraft ? AME_PC_L.draft : AME_PC_L.complete; }
    document.querySelectorAll('#amePosRecentFlyout .ame-pos-recent-option, .pb-mode-btn').forEach(function (btn) {
        btn.classList.toggle('is-active', btn.getAttribute('data-mode') === (isDraft ? 'draft' : 'complete'));
    });
    var completeBtn = document.getElementById('amePcCompleteBtn');
    var jump = document.getElementById('amePcPayJump');
    var side = document.getElementById('amePosDraftComplete');
    if (isDraft) {
        completeBtn.textContent = completeBtn.getAttribute('data-draft-label') || AME_PC_L.save_draft;
        completeBtn.classList.add('is-draft');
        if (jump) { jump.textContent = jump.getAttribute('data-draft-label'); }
        if (side) {
            side.setAttribute('title', AME_PC_L.save_draft);
            side.setAttribute('aria-label', AME_PC_L.save_draft);
        }
    } else {
        completeBtn.textContent = completeBtn.getAttribute('data-complete-label') || AME_PC_L.complete_purchase;
        completeBtn.classList.remove('is-draft');
        if (jump) { jump.textContent = jump.getAttribute('data-complete-label'); }
        if (side) {
            side.setAttribute('title', AME_PC_L.complete_purchase);
            side.setAttribute('aria-label', AME_PC_L.complete_purchase);
        }
    }
}
function amePcShowDraftBanner(number) {
    var banner = document.getElementById('amePcDraftBanner');
    var label = document.getElementById('amePcDraftNumber');
    if (!banner) { return; }
    if (number) {
        if (label) { label.textContent = number; }
        banner.hidden = false;
    } else {
        banner.hidden = true;
        if (label) { label.textContent = ''; }
    }
}
function amePcCloseDrawers() {
    document.getElementById('completeDrawer').classList.remove('open');
    document.getElementById('draftDrawer').classList.remove('open');
    document.getElementById('completeDrawer').setAttribute('aria-hidden', 'true');
    document.getElementById('draftDrawer').setAttribute('aria-hidden', 'true');
}
function pbOpenPay() {
    var el = document.getElementById('pbPayCard');
    if (el && el.scrollIntoView) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    updateTotal();
    var paid = document.getElementById('paidAmount');
    if (paid) { setTimeout(function () { paid.focus(); }, 40); }
}
function pbClosePay() {
}
function amePcFormatTime(value) {
    if (!value) { return ''; }
    var parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) { return String(value); }
    var day = String(parsed.getDate()).padStart(2, '0');
    var month = String(parsed.getMonth() + 1).padStart(2, '0');
    var year = String(parsed.getFullYear()).slice(-2);
    var hours = parsed.getHours();
    var minutes = String(parsed.getMinutes()).padStart(2, '0');
    var meridian = hours >= 12 ? 'pm' : 'am';
    hours = hours % 12;
    if (hours === 0) { hours = 12; }
    return day + '/' + month + '/' + year + ' ' + String(hours).padStart(2, '0') + ':' + minutes + ' ' + meridian;
}
function amePcOpenDrawer() {
    amePcHideFlyout();
    amePcCloseDrawers();
    var id = amePcMode() === 'draft' ? 'draftDrawer' : 'completeDrawer';
    if (id === 'draftDrawer') { amePcRenderDrafts(); }
    document.getElementById(id).classList.add('open');
    document.getElementById(id).setAttribute('aria-hidden', 'false');
}
async function amePcJson(url, options) {
    options = options || {};
    var headers = Object.assign({
        'X-CSRF-Token': amePcCsrf(),
        'Accept': 'application/json'
    }, options.headers || {});
    var response = await fetch(url, Object.assign({ credentials: 'same-origin' }, options, { headers: headers }));
    var text = await response.text();
    var result;
    try { result = JSON.parse(text); } catch (e) { throw new Error(AME_PC_L.server_bad_response); }
    if (!response.ok || result.status !== 'success') {
        throw new Error(result.message || AME_PC_L.draft_op_failed);
    }
    return result;
}
async function amePcSaveDraft() {
    if (amePcDraftBusy) { return; }
    if (cart.length === 0) { alert(AME_PC_L.add_at_least_one_product); return; }
    amePcDraftBusy = true;
    var btn = document.getElementById('amePcCompleteBtn');
    var oldText = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = AME_PC_L.saving_draft; }
    try {
        var result = await amePcJson('draft_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json; charset=UTF-8' },
            body: JSON.stringify({
                action: 'save',
                csrf_token: amePcCsrf(),
                draft_id: Number(document.getElementById('currentDraftId').value || 0),
                supplier_id: Number(document.getElementById('supplierIdHidden').value || 0),
                cart: cart,
                expenses: expenses,
                memo: document.getElementById('memoInput').value.trim(),
                notes: document.getElementById('notesInput').value.trim(),
                date: document.getElementById('purchaseDateInput').value,
                account_id: Number(document.getElementById('accountSelect').value || 0),
                paid_amount: parseFloat(document.getElementById('paidAmount').value) || 0
            })
        });
        alert(AME_PC_L.draft_saved_success + (result.draft_number ? '\n' + result.draft_number : ''));
        window.location.replace('add.php?new=' + Date.now());
    } catch (error) {
        alert(error.message || AME_PC_L.draft_save_failed);
        amePcDraftBusy = false;
        if (btn) { btn.disabled = false; btn.textContent = oldText; }
    }
}
function amePcDraftTableMessage(message) {
    var body = document.getElementById('pcDraftList');
    body.innerHTML = '<tr><td colspan="6" class="pos-draft-empty">' + message + '</td></tr>';
}
async function amePcRenderDrafts() {
    amePcDraftTableMessage(AME_PC_L.draft_list_loading);
    try {
        var result = await amePcJson('draft_query.php?action=list');
        var list = result.drafts || [];
        var body = document.getElementById('pcDraftList');
        if (!list.length) {
            amePcDraftTableMessage(AME_PC_L.no_active_draft);
            return;
        }
        body.innerHTML = '';
        list.forEach(function (d, i) {
            var row = document.createElement('tr');
            [String(i + 1), amePcFormatTime(d.updated_at), d.draft_number || '-', d.supplier_name || '—', '৳' + Number(d.grand_total || 0).toFixed(2)].forEach(function (value) {
                var td = document.createElement('td');
                td.textContent = value;
                row.appendChild(td);
            });
            var actionCell = document.createElement('td');
            actionCell.className = 'pos-recent-actions';
            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'pos-recent-icon-btn';
            editBtn.textContent = '✏️';
            editBtn.title = AME_PC_L.edit;
            editBtn.addEventListener('click', function () { amePcLoadDraft(d.id); });
            actionCell.appendChild(editBtn);
            var cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'pos-recent-icon-btn';
            cancelBtn.textContent = '✖';
            cancelBtn.title = AME_PC_L.cancel;
            cancelBtn.addEventListener('click', function () { amePcCancelDraft(d.id); });
            actionCell.appendChild(cancelBtn);
            row.appendChild(actionCell);
            body.appendChild(row);
        });
    } catch (error) {
        amePcDraftTableMessage(error.message || AME_PC_L.draft_list_load_failed);
    }
}
async function amePcLoadDraft(id) {
    try {
        var result = await amePcJson('draft_query.php?action=load&id=' + encodeURIComponent(id));
        var d = result.draft || {};
        cart = Array.isArray(d.cart) ? d.cart : [];
        expenses = Array.isArray(d.expenses) ? d.expenses : [];
        document.getElementById('currentDraftId').value = String(d.id || 0);
        document.getElementById('supplierIdHidden').value = d.supplier_id ? String(d.supplier_id) : '';
        document.getElementById('supplierSearch').value = d.supplier_name || '';
        document.getElementById('memoInput').value = d.memo || '';
        document.getElementById('notesInput').value = d.notes || '';
        if (d.date) {
            document.getElementById('purchaseDateInput').value = d.date;
            document.getElementById('purchaseDateHidden').value = d.date;
        }
        document.getElementById('accountSelect').value = d.account_id ? String(d.account_id) : '';
        document.getElementById('paidAmount').value = d.paid_amount != null ? d.paid_amount : 0;
        autoPaySynced = false;
        var s = SUPPLIERS.find(function (x) { return String(x.id) === String(d.supplier_id); });
        if (s) {
            selectedSupplierDue = parseFloat(s.total_due || 0) || 0;
            document.getElementById('supPhone').textContent = s.phone || '—';
            ameShowSupplierDue(selectedSupplierDue);
            document.getElementById('supplierInfo').classList.add('show');
        } else if (d.supplier_id) {
            selectedSupplierDue = parseFloat(d.supplier_due || 0) || 0;
            document.getElementById('supPhone').textContent = d.supplier_phone || '—';
            ameShowSupplierDue(selectedSupplierDue);
            document.getElementById('supplierInfo').classList.add('show');
        } else {
            selectedSupplierDue = 0;
            document.getElementById('supplierInfo').classList.remove('show');
        }
        // ⭐ ফিক্স: ড্রাফট এনে এডিট করার পর আগে মোড "draft"-ই থেকে যেত, তাই
        // মূল বাটনে ক্লিক করলে সেটা আবার ড্রাফট হিসেবেই সেভ হতো, কখনো আসল
        // ক্রয় হিসেবে "সম্পূর্ণ" হতো না — ব্যবহারকারীকে আলাদাভাবে মোড
        // পাল্টাতে হতো। এখন ড্রাফট লোড হওয়ার সাথে সাথেই মোড "complete"-এ
        // চলে যাবে (ঠিক মেমো-এডিটের মতোই), যাতে সরাসরি এডিট করে "সম্পূর্ণ
        // করুন" চাপলেই ক্রয়টা চূড়ান্ত হয়ে যায় — ড্রাফট হিসেবে আবার সেভ
        // করতে চাইলে উপরের ড্রপডাউন থেকে "ড্রাফট" মোডে ফিরে যাওয়া যাবে।
        amePcSetMode('complete');
        amePcShowDraftBanner(d.draft_number || ('#' + d.id));
        renderCart();
        renderExpenses();
        scheduleMemoCheck();
        amePcCloseDrawers();
    } catch (error) {
        alert(error.message || AME_PC_L.draft_open_failed);
    }
}

/**
 * ✏️ পুরনো ক্রয় মেমো এডিট — history.php/memo_digital.php থেকে ?edit_memo=...
 * দিয়ে এলে PHP আগে থেকেই পুরো মেমোটা (cart + expenses + supplier + payment)
 * AME_EDIT_MEMO ভ্যারিয়েবলে বসিয়ে দিয়েছে (draft-load-এর মতোই, কিন্তু
 * সার্ভার-রাউন্ডট্রিপ ছাড়াই, সাথে সাথে) — এখানে শুধু সেটা ফর্মে বসানো হয়।
 */
function amePcLoadEditMemo(d) {
    cart = Array.isArray(d.cart) ? d.cart : [];
    expenses = Array.isArray(d.expenses) ? d.expenses : [];
    document.getElementById('supplierIdHidden').value = d.supplier_id ? String(d.supplier_id) : '';
    document.getElementById('supplierSearch').value = d.supplier_name || '';

    selectedPartyType = (d.party_type === 'customer' || (d.customer_id && parseInt(d.customer_id, 10) > 0)) ? 'customer' : 'supplier';
    selectedCustomerId = d.customer_id ? parseInt(d.customer_id, 10) : 0;
    selectedCustomerBalance = parseFloat(d.customer_balance || 0) || 0;
    document.getElementById('customerIdHidden').value = selectedCustomerId ? String(selectedCustomerId) : '';
    document.getElementById('partyTypeHidden').value = selectedPartyType;
    document.getElementById('customerBalanceHidden').value = selectedCustomerBalance;
    amePcSlimPartyBoxes();

    var partyBadgeWrap = document.getElementById('supPartyTypeWrap');
    var partyBadge = document.getElementById('supPartyBadge');
    if (selectedPartyType === 'customer') {
        selectedSupplierDue = 0;
        if (partyBadgeWrap && partyBadge) {
            partyBadgeWrap.style.display = 'block';
            partyBadge.style.background = '#e0f2fe';
            partyBadge.style.color = '#0369a1';
            partyBadge.textContent = '👤 কাস্টমার থেকে ক্রয়';
        }
        var supDueLabel = document.getElementById('supDueLabel');
        if (selectedCustomerBalance > 0.009) {
            if (supDueLabel) supDueLabel.textContent = 'সাবেক বকেয়া (দোকান পাবে):';
            document.getElementById('supDue').innerHTML = '<span style="color:#d97706;font-weight:700;">৳' + selectedCustomerBalance.toFixed(2) + '</span>';
        } else if (selectedCustomerBalance < -0.009) {
            if (supDueLabel) supDueLabel.textContent = 'সাবেক পাওনা (দোকানের দেনা):';
            document.getElementById('supDue').innerHTML = '<span style="color:#059669;font-weight:700;">৳' + Math.abs(selectedCustomerBalance).toFixed(2) + '</span>';
        } else {
            if (supDueLabel) supDueLabel.textContent = 'সাবেক ব্যালেন্স:';
            document.getElementById('supDue').innerHTML = '<span>৳0.00</span>';
        }
    } else {
        selectedSupplierDue = parseFloat(d.supplier_due || 0) || 0;
        if (partyBadgeWrap && partyBadge) {
            partyBadgeWrap.style.display = 'block';
            partyBadge.style.background = '#f1f5f9';
            partyBadge.style.color = '#475569';
            partyBadge.textContent = '🏢 সাধারণ সাপ্লায়ার';
        }
        ameShowSupplierDue(selectedSupplierDue);
    }

    var memoEl = document.getElementById('memoInput');
    memoEl.value = d.memo || '';
    memoEl.readOnly = false;
    memoEl.title = AME_PC_L.memo_editable_hint || 'প্রয়োজনে মেমো নম্বর বদলানো যাবে — অন্য কোনো মেমোর সাথে না মিললেই হবে।';
    memoEl.style.background = '';
    memoEl.addEventListener('input', scheduleMemoCheck);

    document.getElementById('notesInput').value = d.notes || '';
    if (d.date) {
        document.getElementById('purchaseDateInput').value = d.date;
        document.getElementById('purchaseDateHidden').value = d.date;
    }
    document.getElementById('accountSelect').value = d.account_id ? String(d.account_id) : '';
    var origPaid = d.paid_amount != null ? (parseFloat(d.paid_amount) || 0) : 0;
    if (origPaid <= 0.009) {
        document.getElementById('paidAmount').value = '0';
        document.getElementById('accountSelect').value = '';
    } else {
        // এডিটে আগের আসল পরিশোধ ও অ্যাকাউন্ট অক্ষত রাখতে হবে। আগে পূর্ণ
        // পরিশোধকে "অটো-পে" ধরে দুটোই মুছে দেওয়ায় সেভের পর মেমো বাকি হয়ে যেত।
        document.getElementById('paidAmount').value = origPaid.toFixed(2);
    }
    autoPaySynced = false;

    selectedSupplierDue = parseFloat(d.supplier_due || 0) || 0;
    document.getElementById('supPhone').textContent = d.supplier_phone || '—';
    ameShowSupplierDue(selectedSupplierDue);
    document.getElementById('supplierInfo').classList.add('show');

    amePcSetMode('complete');
    var editLabel = AME_PC_L.confirm_memo_update || '💾 মেমো আপডেট নিশ্চিত করুন';
    var completeBtn = document.getElementById('amePcCompleteBtn');
    completeBtn.setAttribute('data-complete-label', editLabel);
    completeBtn.textContent = editLabel;
    var jump = document.getElementById('amePcPayJump');
    if (jump) {
        jump.setAttribute('data-complete-label', editLabel);
        jump.textContent = editLabel;
    }

    renderCart();
    renderExpenses();
}
async function amePcCancelDraft(id) {
    if (!confirm(AME_PC_L.confirm_cancel_draft)) { return; }
    try {
        await amePcJson('draft_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json; charset=UTF-8' },
            body: JSON.stringify({ action: 'cancel', csrf_token: amePcCsrf(), draft_id: Number(id) })
        });
        if (String(document.getElementById('currentDraftId').value) === String(id)) {
            document.getElementById('currentDraftId').value = '0';
            amePcShowDraftBanner('');
        }
        amePcRenderDrafts();
    } catch (error) {
        alert(error.message || AME_PC_L.draft_cancel_failed);
    }
}
var amePcSubmitBusy = false;
function amePcGo() {
    amePcHideFlyout();
    if (amePcMode() === 'draft') { amePcSaveDraft(); return; }
    if (amePcSubmitBusy) { return; }
    document.getElementById('purchaseForm').requestSubmit();
}

document.getElementById('amePcCompleteBtn').addEventListener('click', amePcGo);
var amePcSaveDraftBtn = document.getElementById('amePcSaveDraftBtn');
if (amePcSaveDraftBtn) {
    amePcSaveDraftBtn.addEventListener('click', function () { amePcSaveDraft(); });
}
var amePosDraftListBtn = document.getElementById('amePosDraftListBtn');
if (amePosDraftListBtn) {
    amePosDraftListBtn.addEventListener('click', function () {
        amePcSetMode('draft');
        amePcCloseDrawers();
        amePcRenderDrafts();
        document.getElementById('draftDrawer').classList.add('open');
        document.getElementById('draftDrawer').setAttribute('aria-hidden', 'false');
    });
}
['pbPayOpen', 'pbPayLaunch'].forEach(function (id) {
    var btn = document.getElementById(id);
    if (btn) { btn.addEventListener('click', pbOpenPay); }
});
var pbCompleteLaunch = document.getElementById('pbCompleteLaunch');
if (pbCompleteLaunch) {
    pbCompleteLaunch.addEventListener('click', function () {
        amePcSetMode('complete');
        if (cart.length === 0) { alert(AME_PC_L.add_at_least_one_product); return; }
        pbOpenPay();
    });
}
var pbDraftSaveVisible = document.getElementById('pbDraftSaveVisible');
if (pbDraftSaveVisible) {
    pbDraftSaveVisible.addEventListener('click', function () { amePcSaveDraft(); });
}
var pbDraftListVisible = document.getElementById('pbDraftListVisible');
if (pbDraftListVisible) {
    pbDraftListVisible.addEventListener('click', pbOpenDraftList);
}
var pbNewChallan = document.getElementById('pbNewChallan');
if (pbNewChallan) {
    pbNewChallan.addEventListener('click', function () {
        if (cart.length > 0 && !confirm(window.AME_LANG === 'en'
            ? 'Start a new challan? Unsaved lines will be lost.'
            : 'নতুন চালান শুরু করবেন? সেভ না করা পণ্য হারিয়ে যাবে।')) {
            return;
        }
        window.location.href = 'add.php?new=' + Date.now();
    });
}
var pbMemoSuggest = document.getElementById('pbMemoSuggest');
if (pbMemoSuggest) {
    pbMemoSuggest.addEventListener('click', pbForceSuggestMemo);
}
var pbClearAll = document.getElementById('pbClearAll');
if (pbClearAll) {
    pbClearAll.addEventListener('click', pbClearAllForm);
}
var pbPayClose = document.getElementById('pbPayClose');
if (pbPayClose) { pbPayClose.addEventListener('click', pbClosePay); }
var pbPayOverlay = document.getElementById('pbPayOverlay');
if (pbPayOverlay) {
    pbPayOverlay.addEventListener('click', function (e) {
        if (e.target === this) { pbClosePay(); }
    });
}
['completeDrawer', 'draftDrawer'].forEach(function (id) {
    var box = document.getElementById(id);
    if (box) {
        box.addEventListener('click', function (e) {
            if (e.target === this) { amePcCloseDrawers(); }
        });
    }
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { amePcCloseDrawers(); amePcHideFlyout(); pbClosePay(); }
});
var recentToggle = document.getElementById('amePosRecentToggle');
if (recentToggle) {
    recentToggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        var fly = document.getElementById('amePosRecentFlyout');
        if (fly && fly.classList.contains('is-open')) { amePcHideFlyout(); return; }
        amePcOpenFlyout();
    }, true);
}
var recentDraft = document.getElementById('amePosRecentDraft');
if (recentDraft) {
    recentDraft.addEventListener('click', function (event) {
        event.preventDefault();
        amePcSetMode('draft');
        amePcHideFlyout();
    }, true);
}
var recentComplete = document.getElementById('amePosRecentComplete');
if (recentComplete) {
    recentComplete.addEventListener('click', function (event) {
        event.preventDefault();
        amePcSetMode('complete');
        amePcHideFlyout();
    }, true);
}
document.getElementById('purchaseDateInput').addEventListener('change', function () {
    document.getElementById('purchaseDateHidden').value = this.value;
});

function amePcPickSupplier(s) {
    if (!s) {
        return;
    }
    document.getElementById('supplierIdHidden').value = s.id;
    supplierSearch.value = s.name;

    selectedPartyType = (s.source === 'customer') ? 'customer' : 'supplier';
    selectedCustomerId = (s.source === 'customer') ? (s.customer_id || parseInt(String(s.id).replace(/^C/, ''), 10) || 0) : 0;
    selectedCustomerBalance = (s.source === 'customer') ? (parseFloat(s.customer_balance || 0) || 0) : 0;
    document.getElementById('customerIdHidden').value = selectedCustomerId ? String(selectedCustomerId) : '';
    document.getElementById('partyTypeHidden').value = selectedPartyType;
    document.getElementById('customerBalanceHidden').value = selectedCustomerBalance;
    amePcSlimPartyBoxes();

    var partyBadgeWrap = document.getElementById('supPartyTypeWrap');
    var partyBadge = document.getElementById('supPartyBadge');
    var supDueLabel = document.getElementById('supDueLabel');

    if (selectedPartyType === 'customer') {
        selectedSupplierDue = 0;
        if (partyBadgeWrap && partyBadge) {
            partyBadgeWrap.style.display = 'block';
            partyBadge.style.background = '#e0f2fe';
            partyBadge.style.color = '#0369a1';
            partyBadge.textContent = '👤 কাস্টমার থেকে ক্রয়';
        }
        document.getElementById('supPhone').textContent = s.phone || '—';
        if (selectedCustomerBalance > 0.009) {
            if (supDueLabel) supDueLabel.textContent = 'সাবেক বকেয়া (দোকান পাবে):';
            document.getElementById('supDue').innerHTML = '<span style="color:#d97706;font-weight:700;">৳' + selectedCustomerBalance.toFixed(2) + '</span>';
        } else if (selectedCustomerBalance < -0.009) {
            if (supDueLabel) supDueLabel.textContent = 'সাবেক পাওনা (দোকানের দেনা):';
            document.getElementById('supDue').innerHTML = '<span style="color:#059669;font-weight:700;">৳' + Math.abs(selectedCustomerBalance).toFixed(2) + '</span>';
        } else {
            if (supDueLabel) supDueLabel.textContent = 'সাবেক ব্যালেন্স:';
            document.getElementById('supDue').innerHTML = '<span>৳0.00</span>';
        }
    } else {
        selectedSupplierDue = parseFloat(s.total_due || 0) || 0;
        if (partyBadgeWrap && partyBadge) {
            partyBadgeWrap.style.display = 'block';
            partyBadge.style.background = '#f1f5f9';
            partyBadge.style.color = '#475569';
            partyBadge.textContent = '🏢 সাধারণ সাপ্লায়ার';
        }
        if (selectedSupplierDue > 0.009) {
            if (supDueLabel) supDueLabel.textContent = (AME_PC_L.previous_due_plus || 'সাবেক বকেয়া (+)') + ':';
        } else if (selectedSupplierDue < -0.009) {
            if (supDueLabel) supDueLabel.textContent = (AME_PC_L.previous_due_minus || 'সাবেক পাওনা (−)') + ':';
        } else if (supDueLabel) {
            supDueLabel.textContent = (AME_PC_L.previous_due || AME_PC_L.previous || 'সাবেক বকেয়া') + ':';
        }
        document.getElementById('supPhone').textContent = s.phone || '—';
        document.getElementById('supDue').textContent = ameMoneyAbs(selectedSupplierDue);
    }

    document.getElementById('supplierInfo').classList.add('show');
    supplierResults.classList.remove('open');
    maybeFillNextMemo();
    scheduleMemoCheck();
    updateTotal();
}

var supplierSearch = document.getElementById('supplierSearch');
var supplierResults = document.getElementById('supplierResults');
supplierSearch.addEventListener('input', function () {
    var q = supplierSearch.value.trim().toLowerCase();
    if (q === '') {
        supplierResults.classList.remove('open');
        document.getElementById('supplierIdHidden').value = '';
        document.getElementById('customerIdHidden').value = '';
        document.getElementById('partyTypeHidden').value = 'supplier';
        document.getElementById('customerBalanceHidden').value = '0';
        selectedSupplierDue = 0;
        selectedPartyType = 'supplier';
        selectedCustomerId = 0;
        selectedCustomerBalance = 0;
        amePcSlimPartyBoxes();
        var partyBadgeWrap = document.getElementById('supPartyTypeWrap');
        if (partyBadgeWrap) partyBadgeWrap.style.display = 'none';
        document.getElementById('supplierInfo').classList.remove('show');
        scheduleMemoCheck();
        updateTotal();
        return;
    }
    var pbMode = pbPartyMode();
    var matches = SUPPLIERS.filter(function (s) {
        var fromCustomer = s.source === 'customer';
        if (pbMode === 'customer') {
            if (!fromCustomer) { return false; }
        } else if (fromCustomer) {
            if (!Number(s.is_supplier)) { return false; }
            var sameParty = SUPPLIERS.some(function (other) {
                if (!other || other.source === 'customer') { return false; }
                var sameName = String(other.name || '').trim().toLowerCase() === String(s.name || '').trim().toLowerCase();
                var phoneA = String(other.phone || '').replace(/\D+/g, '');
                var phoneB = String(s.phone || '').replace(/\D+/g, '');
                return sameName || (phoneA !== '' && phoneA === phoneB);
            });
            if (sameParty) { return false; }
        }
        return s.name.toLowerCase().indexOf(q) !== -1 || (s.phone || '').indexOf(q) !== -1 || String(s.id).indexOf(q) !== -1 || String(s.customer_code || '').toLowerCase().indexOf(q) !== -1;
    }).slice(0, 8);
    supplierResults.innerHTML = matches.length === 0
        ? '<div class="pc-result-item" style="color:var(--pos-c-94a3b8, #94A3B8);">' + AME_PC_L.no_supplier_found + '</div>'
        : matches.map(function (s) {
            var tag = '';
            var dueTxt = '';
            if (s.source === 'customer') {
                tag = ' <span style="background:#e0f2fe;color:#0369a1;padding:1px 6px;border-radius:4px;font-size:11px;font-weight:700;">[কাস্টমার]</span>';
                var cBal = parseFloat(s.customer_balance || 0) || 0;
                if (cBal > 0.009) {
                    dueTxt = 'সাবেক বকেয়া ৳' + cBal.toFixed(2);
                } else if (cBal < -0.009) {
                    dueTxt = 'সাবেক পাওনা ৳' + Math.abs(cBal).toFixed(2);
                } else {
                    dueTxt = 'সাবেক ব্যালেন্স ৳0.00';
                }
            } else {
                tag = ' <span style="background:#f1f5f9;color:#475569;padding:1px 6px;border-radius:4px;font-size:11px;">[সাপ্লায়ার]</span>';
                dueTxt = (AME_PC_L.previous_due || 'সাবেক') + ' ৳' + Number(s.total_due || 0).toFixed(2);
            }
            return '<div class="pc-result-item" data-id="' + ameEsc(s.id) + '">' + ameEsc(s.name) + tag + '<small>' + ameEsc(s.phone || '') + ' · ' + ameEsc(dueTxt) + '</small></div>';
        }).join('');
    supplierResults.classList.add('open');
    supplierResults.querySelectorAll('.pc-result-item[data-id]').forEach(function (el) {
        el.addEventListener('click', function () {
            var pickedId = el.getAttribute('data-id');
            var s = SUPPLIERS.find(function (x) { return String(x.id) === String(pickedId); });
            if (!s) {
                return;
            }
            if (s.source === 'customer') {
                supplierSearch.value = s.name;
                supplierResults.classList.remove('open');
                var body = 'csrf_token=' + encodeURIComponent(amePcCsrf()) + '&customer_id=' + encodeURIComponent(s.customer_id || String(s.id).replace(/^C/, ''));
                fetch('actions/resolve_customer_supplier.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || data.status !== 'success' || !data.supplier) {
                            throw new Error((data && data.message) || 'link failed');
                        }
                        var real = data.supplier;
                        if (data.use_as === 'supplier') {
                            real.source = 'supplier';
                            real.customer_id = 0;
                            real.customer_balance = 0;
                        } else {
                            real.source = 'customer';
                            real.customer_id = data.customer_id || s.customer_id;
                            real.customer_balance = (data.customer_balance !== undefined) ? data.customer_balance : s.customer_balance;
                        }
                        var exists = SUPPLIERS.find(function (x) { return String(x.id) === String(real.id); });
                        if (!exists) {
                            SUPPLIERS.push(real);
                        } else if (real.source !== 'supplier') {
                            exists.source = 'customer';
                            exists.customer_id = real.customer_id;
                            exists.customer_balance = real.customer_balance;
                        }
                        amePcPickSupplier(real);
                    })
                    .catch(function () {
                        alert(AME_PC_L.select_supplier_required);
                    });
                return;
            }
            amePcPickSupplier(s);
        });
    });
});

document.addEventListener('click', function (e) {
    if (!e.target.closest('#supplierSearch') && !e.target.closest('#supplierResults')) { supplierResults.classList.remove('open'); }
    if (!e.target.closest('#productSearch') && !e.target.closest('#productResults')) { productResults.classList.remove('open'); }
    if (!e.target.closest('#amePosRecentToggle') && !e.target.closest('#amePosRecentFlyout')) {
        amePcHideFlyout();
    }
});

var productSearch = document.getElementById('productSearch');
var productResults = document.getElementById('productResults');
var amePcSearchTimer = 0;
var amePcSearchSeq = 0;
function amePcProductMatch(p, q) {
    q = String(q || '').toLowerCase();
    if (q === '') { return false; }
    return String(p.name || '').toLowerCase().indexOf(q) !== -1
        || String(p.id).indexOf(q) !== -1
        || String(p.sku || '').toLowerCase().indexOf(q) !== -1
        || String(p.oem_code || '').toLowerCase().indexOf(q) !== -1
        || String(p.supplier_code || '').toLowerCase().indexOf(q) !== -1
        || String(p.barcode || '').toLowerCase().indexOf(q) !== -1;
}
function amePcCodeRank(p, q) {
    q = String(q || '').toLowerCase();
    var fields = [p.sku, p.oem_code, p.supplier_code, p.barcode, p.id];
    for (var i = 0; i < fields.length; i++) {
        if (String(fields[i] || '').toLowerCase() === q) { return 0; }
    }
    return 1;
}
function amePcMergeProduct(p) {
    if (!p || p.id == null) { return; }
    var id = Number(p.id);
    var i;
    for (i = 0; i < PRODUCTS.length; i++) {
        if (Number(PRODUCTS[i].id) === id) {
            PRODUCTS[i] = Object.assign({}, PRODUCTS[i], p, { id: id });
            return;
        }
    }
    p.id = id;
    PRODUCTS.push(p);
}
function amePcRenderProductHits(list, q) {
    list = (list || []).slice();
    list.sort(function (a, b) {
        var ra = amePcCodeRank(a, q);
        var rb = amePcCodeRank(b, q);
        if (ra !== rb) { return ra - rb; }
        return String(a.name || '').localeCompare(String(b.name || ''));
    });
    if (list.length === 0) {
        productResults.innerHTML = '<div class="pc-result-item" style="color:var(--pos-c-94a3b8, #94A3B8);">' + AME_PC_L.no_result + '</div>';
    } else {
        var countLabel = String(AME_PC_L.search_count || '%sটি পণ্য').replace('%s', String(list.length));
        productResults.innerHTML = '<div class="pc-result-count">' + ameEsc(countLabel) + '</div>' + list.map(function (p) {
            var code = p.sku || p.id;
            var extra = [];
            if (p.oem_code) extra.push('OEM ' + ameEsc(p.oem_code));
            if (p.supplier_code) extra.push(ameEsc(AME_PC_L.supplier_code_short) + ' ' + ameEsc(p.supplier_code));
            var meta = extra.length ? extra.join(' · ') + ' • ' : '';
            var shownName = String(p.name || '').trim() || ('#' + p.id);
            return '<div class="pc-result-item" data-id="' + p.id + '"><strong>' + ameEsc(shownName) + '</strong><small>' + meta + ameEsc(code) + ' • ' + ameEsc(AME_PC_L.stock_label) + ': ' + ameEsc(p.stock) + '</small></div>';
        }).join('');
    }
    productResults.classList.add('open');
    productResults.querySelectorAll('.pc-result-item[data-id]').forEach(function (el) {
        el.addEventListener('click', function () {
            var p = PRODUCTS.find(function (x) { return Number(x.id) === Number(el.getAttribute('data-id')); });
            if (p) { pbSelectProduct(p); }
            productResults.classList.remove('open');
        });
    });
}
function amePcFetchProducts(q) {
    var seq = ++amePcSearchSeq;
    fetch('actions/search_products.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (rows) {
            if (seq !== amePcSearchSeq) { return; }
            if (productSearch.value.trim() !== q) { return; }
            if (!Array.isArray(rows)) { return; }
            rows.forEach(amePcMergeProduct);
            amePcRenderProductHits(PRODUCTS.filter(function (p) { return amePcProductMatch(p, q.toLowerCase()); }), q);
        })
        .catch(function () {});
}
productSearch.addEventListener('input', function () {
    var q = productSearch.value.trim();
    if (q === '') { productResults.classList.remove('open'); return; }
    amePcRenderProductHits(PRODUCTS.filter(function (p) { return amePcProductMatch(p, q.toLowerCase()); }), q);
    clearTimeout(amePcSearchTimer);
    amePcSearchTimer = setTimeout(function () { amePcFetchProducts(q); }, 160);
});

function ameUnitSelect(idx, item) {
    if (!item.units || item.units.length < 2) {
        return item.unit_hint ? '<small>' + ameEsc(item.unit_hint) + '</small>' : '';
    }
    var html = '<select onchange="amePickUnit(' + idx + ', this.value)">';
    item.units.forEach(function (u) {
        html += '<option value="' + u.unit_id + '"' + (Number(item.unit_id) === Number(u.unit_id) ? ' selected' : '') + '>' + ameEsc(u.unit_name) + '</option>';
    });
    html += '</select>';
    if (item.unit_hint) html += '<small>' + ameEsc(item.unit_hint) + '</small>';
    return html;
}
function amePickUnit(idx, unitId) {
    var item = cart[idx];
    if (!item) return;
    item.unit_id = Number(unitId);
    (item.units || []).forEach(function (u) {
        if (Number(u.unit_id) === item.unit_id && u.purchase_price) item.purchase_price = parseFloat(u.purchase_price) || item.purchase_price;
    });
    renderCart();
}
function ameLoadUnits(item) {
    fetch('/modules/inventory/unit_api.php?role=purchase&product_id=' + item.product_id)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            item.units = data.units || [];
            item.unit_hint = data.hint || '';
            if (item.units.length) item.unit_id = Number(item.units[0].unit_id);
            renderCart();
        })
        .catch(function () {});
}
function addToCart(p) {
    var existing = cart.find(function (c) { return c.product_id === p.id; });
    if (existing) { existing.qty += 1; renderCart(); return; }
    cart.push({
        product_id: p.id,
        sku: p.sku || '',
        name: String(p.name || '').trim() || ('#' + p.id),
        stock: p.stock,
        qty: 1,
        free_qty: 0,
        unit_id: 0,
        units: [],
        unit_hint: '',
        purchase_price: parseFloat(p.purchase_price) || 0,
        dealer_price: parseFloat(p.dealer_price) || 0,
        wholesale_price: parseFloat(p.wholesale_price) || 0,
        sale_price: parseFloat(p.sale_price) || 0
    });
    ameLoadUnits(cart[cart.length - 1]);
    renderCart();
}
function removeFromCart(idx) { cart.splice(idx, 1); renderCart(); }

function salePriceIssue(item) {
    var pp = parseFloat(item.purchase_price) || 0;
    var sale = parseFloat(item.sale_price) || 0;
    if (sale < pp) {
        return '"' + item.name + '"' + AME_PC_L.retail_low_msg
            .replace('%s', sale.toFixed(2)).replace('%s', pp.toFixed(2));
    }
    var checks = [
        ['dealer_price', AME_PC_L.dealer],
        ['wholesale_price', AME_PC_L.wholesale]
    ];
    for (var i = 0; i < checks.length; i++) {
        var val = parseFloat(item[checks[i][0]]) || 0;
        if (val > 0 && val < pp) {
            return '"' + item.name + '"' + AME_PC_L.label_dash_prefix + checks[i][1] + AME_PC_L.price_low_msg
                .replace('%s', val.toFixed(2)).replace('%s', pp.toFixed(2));
        }
    }
    return null;
}

/**
 * ক্রয়মূল্যের চেয়ে ডিলার/হোলসেল/বিক্রয়মূল্য কম হলে ইনপুট বক্সের বর্ডার/
 * ব্যাকগ্রাউন্ড রঙ লাল করে দেখানোর জন্য — renderCart() এবং লাইভ-আপডেটের
 * সময় (ameRefreshRowWarning) দুই জায়গাতেই একই লজিক ব্যবহারের জন্য এখানে
 * একবার লেখা হলো (আগে renderCart()-এর ভেতরেই আলাদা করে লেখা ছিল)।
 */
function pcPriceStyle(pp, val) {
    return (val > 0 && val < pp) ? 'border-color:var(--pos-c-c62828, #C62828); background:var(--pos-c-fff5f5, #FFF5F5);' : '';
}

function pbNum(v) { return (parseFloat(v) || 0); }
function pbLineCost(item) { return pbNum(item.qty) * pbNum(item.purchase_price); }
function renderCart() {
    var body = document.getElementById('cartBody');
    var foot = document.getElementById('cartFoot');
    if (cart.length === 0) {
        body.innerHTML = '<tr><td colspan="12" class="pc-empty-cart">' + AME_PC_L.search_add_hint + '</td></tr>';
        if (foot) { foot.hidden = true; }
        updateTotal();
        return;
    }
    body.innerHTML = '';
    cart.forEach(function (item, idx) {
        if (item.free_qty === undefined || item.free_qty === null) { item.free_qty = 0; }
        var row = document.createElement('tr');
        var pp = pbNum(item.purchase_price);
        var saleLow = (pbNum(item.sale_price) < pp);
        var warning = saleLow || (item.wholesale_price > 0 && item.wholesale_price < pp) || (item.dealer_price > 0 && item.dealer_price < pp);
        var nameText = ameEsc(item.name) + (item.stock !== undefined && item.stock !== null ? ' <small>(' + ameEsc(AME_PC_L.stock_label) + ': ' + ameEsc(item.stock) + ')</small>' : '');
        var unitLabel = item.unit_hint || (window.AME_LANG === 'en' ? 'Pcs' : 'পিস');
        row.innerHTML =
            '<td>' + (idx + 1) + '</td>' +
            '<td class="pb-col-name" id="pcNameCell_' + idx + '"><b>' + nameText + '</b>' + (pbNum(item.qty) <= 0 && pbNum(item.free_qty) > 0 ? ' <span class="pb-tag">' + (window.AME_LANG === 'en' ? 'Free' : 'ফ্রি') + '</span>' : '') + (warning ? '<br><small class="pb-warn">' + ameEsc(AME_PC_L.below_purchase_rate_warning) + '</small>' : '') + '</td>' +
            '<td>' + ameEsc(item.sku || '—') + '</td>' +
            '<td>' + ameEsc(unitLabel) + ameUnitSelect(idx, item) + '</td>' +
            '<td><input id="pcQtyInput_' + idx + '" class="pb-cell" type="number" inputmode="decimal" step="0.01" min="0" value="' + item.qty + '" oninput="quickUpdateItem(' + idx + ', \'qty\', this.value)" onchange="updateItem(' + idx + ', \'qty\', this.value)"></td>' +
            '<td><input class="pb-cell" type="number" inputmode="decimal" step="0.01" min="0" value="' + item.free_qty + '" onchange="updateItem(' + idx + ', \'free_qty\', this.value)"></td>' +
            '<td><input class="pb-cell" type="number" step="0.01" min="0" value="' + pp + '" oninput="quickUpdateItem(' + idx + ', \'purchase_price\', this.value)" onchange="updateItem(' + idx + ', \'purchase_price\', this.value)"></td>' +
            '<td class="pb-num" id="pcLineTotal_' + idx + '">৳' + pbLineCost(item).toFixed(2) + '</td>' +
            '<td><input id="pcWholesaleInput_' + idx + '" class="pb-cell" type="number" step="0.01" min="0" value="' + item.wholesale_price + '" style="' + pcPriceStyle(pp, item.wholesale_price) + '" onchange="updateItem(' + idx + ', \'wholesale_price\', this.value)"></td>' +
            '<td><input id="pcSaleInput_' + idx + '" class="pb-cell" type="number" step="0.01" min="0" value="' + item.sale_price + '" style="' + (saleLow ? 'border-color:#C62828;background:#FFF5F5;' : '') + '" onchange="updateItem(' + idx + ', \'sale_price\', this.value)"></td>' +
            '<td><input id="pcDealerInput_' + idx + '" class="pb-cell" type="number" step="0.01" min="0" value="' + (item.dealer_price || 0) + '" style="' + pcPriceStyle(pp, item.dealer_price) + '" onchange="updateItem(' + idx + ', \'dealer_price\', this.value)"></td>' +
            '<td class="pb-col-x"><button type="button" class="er-del" title="' + ameEsc(AME_PC_L.cancel) + '" onclick="removeFromCart(' + idx + ')">🗑</button></td>';
        body.appendChild(row);
    });
    if (foot) { foot.hidden = false; }
    updateTotal();
}

/**
 * ক্রয়মূল্য টাইপ করতে থাকা অবস্থায় (quickUpdateItem, পুরো renderCart না
 * চালিয়ে) ডিলার/হোলসেল/বিক্রয়মূল্য কলামের লাল সতর্কতা রঙ এবং নামের পাশের
 * "ক্রয় রেটের চেয়ে কম" লেখা যেন সাথে সাথেই হালনাগাদ হয় — আগে এগুলো শুধু
 * বক্স থেকে ফোকাস সরার (onchange) পরেই বদলাতো, ততক্ষণ পুরনো/ভুল সতর্কতা
 * দেখাতো।
 */
function ameRefreshRowWarning(idx) {
    var item = cart[idx];
    if (!item) { return; }
    var pp = item.purchase_price;
    var saleLow = (item.sale_price < pp);
    var warning = saleLow || (item.dealer_price > 0 && item.dealer_price < pp) || (item.wholesale_price > 0 && item.wholesale_price < pp);

    var nameCell = document.getElementById('pcNameCell_' + idx);
    if (nameCell) {
        var warnEl = nameCell.querySelector('.pb-warn');
        if (warning && !warnEl) {
            nameCell.insertAdjacentHTML('beforeend', '<br><small class="pb-warn">' + ameEsc(AME_PC_L.below_purchase_rate_warning) + '</small>');
        } else if (!warning && warnEl) {
            if (warnEl.previousSibling && warnEl.previousSibling.nodeName === 'BR') { warnEl.previousSibling.remove(); }
            warnEl.remove();
        }
    }
    var dealerInput = document.getElementById('pcDealerInput_' + idx);
    if (dealerInput) { dealerInput.style.cssText = pcPriceStyle(pp, item.dealer_price); }
    var wholesaleInput = document.getElementById('pcWholesaleInput_' + idx);
    if (wholesaleInput) { wholesaleInput.style.cssText = pcPriceStyle(pp, item.wholesale_price); }
    var saleInput = document.getElementById('pcSaleInput_' + idx);
    if (saleInput) { saleInput.style.cssText = saleLow ? 'border-color:var(--pos-c-c62828, #C62828); background:var(--pos-c-fff5f5, #FFF5F5);' : pcPriceStyle(pp, item.sale_price); }
}
/**
 * পরিমাণ (qty) বা ক্রয়মূল্যের বক্সে +/- করলে বা টাইপ করলে সাথে সাথে
 * (ফোকাস/কার্সার না হারিয়ে) ওই লাইনের টোটাল এবং সবার নিচের সর্বমোট —
 * দুটোই তাৎক্ষণিকভাবে বদলে যাবে। পুরো কার্ট আবার আঁকা হয় না বলে বক্স
 * থেকে ফোকাস সরে না, টাইপ করাও থামে না।
 */
function quickUpdateItem(idx, field, value) {
    if (!cart[idx]) { return; }
    cart[idx][field] = parseFloat(value) || 0;
    var lineTotal = (cart[idx].qty || 0) * (cart[idx].purchase_price || 0);
    var cell = document.getElementById('pcLineTotal_' + idx);
    if (cell) { cell.textContent = '৳' + lineTotal.toFixed(2); }
    if (field === 'purchase_price') {
        // ক্রয়মূল্য বদলালে ডিলার/হোলসেল/বিক্রয়মূল্যের সাথে তুলনা করে
        // লাল সতর্কতাও সাথে সাথেই হালনাগাদ করা দরকার (নিচের ফাংশনের
        // মন্তব্য দ্রষ্টব্য)।
        ameRefreshRowWarning(idx);
    }
    updateTotal();
}
function updateItem(idx, field, value) {
    if (!cart[idx]) { return; }
    var prev = cart[idx][field];
    cart[idx][field] = parseFloat(value) || 0;
    if (['sale_price', 'wholesale_price', 'dealer_price', 'purchase_price'].indexOf(field) !== -1) {
        var issue = salePriceIssue(cart[idx]);
        if (issue) {
            cart[idx][field] = prev;
            alert('⚠️ ' + issue);
        }
    }
    renderCart();
}

/**
 * পরিমাণের পাশের "−"/"+" বাটন — চাপার সাথে সাথে (মোবাইলেও, যেখানে নাম্বার
 * বক্সের নিজস্ব ছোট্ট তীরচিহ্ন প্রায়ই দেখাই যায় না) ওই লাইনের টোটাল এবং
 * সর্বমোট দুটোই সাথে সাথে বদলে যাবে — quickUpdateItem-এর মতোই, পুরো কার্ট
 * নতুন করে না এঁকে।
 */
function stepQty(idx, delta) {
    if (!cart[idx]) { return; }
    var current = parseFloat(cart[idx].qty) || 0;
    var next = Math.round((current + delta) * 100) / 100;
    if (next < 0.01) { next = 0.01; }
    cart[idx].qty = next;
    var input = document.getElementById('pcQtyInput_' + idx);
    if (input) { input.value = next; }
    var lineTotal = (cart[idx].qty || 0) * (cart[idx].purchase_price || 0);
    var cell = document.getElementById('pcLineTotal_' + idx);
    if (cell) { cell.textContent = '৳' + lineTotal.toFixed(2); }
    updateTotal();
}

function addExpense() {
    var type = document.getElementById('expenseType').value;
    var amount = parseFloat(document.getElementById('expenseAmount').value) || 0;
    if (amount <= 0) { alert(AME_PC_L.valid_amount); return; }
    expenses.push({ type: type, amount: amount });
    document.getElementById('expenseAmount').value = '';
    renderExpenses();
}
function removeExpense(idx) { expenses.splice(idx, 1); renderExpenses(); }
function renderExpenses() {
    var list = document.getElementById('expenseList');
    list.innerHTML = expenses.map(function (e, idx) {
        return '<div class="pc-expense-list-item"><span>' + ameEsc(e.type) + '</span><span>৳' + e.amount.toFixed(2) + ' <span class="pc-remove-btn" onclick="removeExpense(' + idx + ')">✖</span></span></div>';
    }).join('');
    updateTotal();
}

function ameMoneyAbs(n) {
    var v = parseFloat(n) || 0;
    return (v < -0.009 ? '−' : '') + '৳' + Math.abs(v).toFixed(2);
}

function ameShowSupplierDue(amount) {
    var el = document.getElementById('supDue');
    var lab = document.getElementById('supDueLabel');
    var v = parseFloat(amount) || 0;
    if (el) el.textContent = ameMoneyAbs(v);
    if (lab) {
        if (v > 0.009) lab.textContent = (AME_PC_L.previous_due_plus || 'সাবেক বকেয়া (+)') + ':';
        else if (v < -0.009) lab.textContent = (AME_PC_L.previous_due_minus || 'সাবেক পাওনা (−)') + ':';
        else lab.textContent = (AME_PC_L.previous_due || AME_PC_L.previous || 'সাবেক বকেয়া') + ':';
    }
    var prevInp = document.getElementById('prevDueDisplay');
    if (prevInp) {
        prevInp.setAttribute('data-live', v.toFixed(2));
    }
}

function amePcReadPrevDueInput() {
    var el = document.getElementById('prevDueDisplay');
    if (!el) { return 0; }
    var raw = String(el.value || '0').replace(/[৳,\s]/g, '').replace('−', '-');
    var n = parseFloat(raw);
    return isNaN(n) ? 0 : n;
}

function amePcSavePreviousDue() {
    var sid = document.getElementById('supplierIdHidden').value;
    var hint = document.getElementById('amePcPrevDueHint');
    var shown = amePcReadPrevDueInput();
    if (selectedPartyType === 'customer') {
        selectedCustomerBalance = shown;
    } else {
        selectedSupplierDue = shown;
    }
    if (!sid) {
        if (hint) {
            hint.hidden = false;
            hint.textContent = AME_PC_L.select_supplier_required || 'সাপ্লায়ার নির্বাচন করুন।';
            hint.className = 'ame-pc-memo-hint is-bad';
        }
        return Promise.resolve(false);
    }
    var body = new URLSearchParams({
        csrf_token: amePcCsrf(),
        supplier_id: sid,
        previous_balance: String(shown),
        party_type: selectedPartyType,
        customer_id: selectedCustomerId ? String(selectedCustomerId) : '',
        settle_type: (document.getElementById('amePcSettleType') || {}).value || 'adjustment',
        settle_reason: (document.getElementById('amePcSettleReason') || {}).value || '',
        memo_no: (document.getElementById('memoInput') || {}).value || ''
    });
    return fetch('actions/set_supplier_previous.php', { method: 'POST', body: body })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data || data.status !== 'success') {
                if (hint) {
                    hint.hidden = false;
                    hint.textContent = (data && data.message) || AME_PC_L.generic_error;
                    hint.className = 'ame-pc-memo-hint is-bad';
                }
                return false;
            }
            selectedSupplierDue = parseFloat(data.previous);
            if (isNaN(selectedSupplierDue)) {
                selectedSupplierDue = (selectedPartyType === 'customer') ? -shown : shown;
            }
            if (selectedPartyType === 'customer') {
                if (data.customer_balance !== undefined) {
                    selectedCustomerBalance = parseFloat(data.customer_balance);
                    if (isNaN(selectedCustomerBalance)) { selectedCustomerBalance = shown; }
                } else {
                    selectedCustomerBalance = -selectedSupplierDue;
                }
            }
            var i;
            for (i = 0; i < SUPPLIERS.length; i++) {
                if (String(SUPPLIERS[i].id) === String(sid)) {
                    SUPPLIERS[i].total_due = selectedSupplierDue;
                    if (selectedPartyType === 'customer') {
                        SUPPLIERS[i].customer_balance = selectedCustomerBalance;
                    }
                    break;
                }
            }
            ameShowSupplierDue(selectedPartyType === 'customer' ? selectedCustomerBalance : selectedSupplierDue);
            updateTotal();
            if (hint) {
                hint.hidden = false;
                hint.textContent = AME_PC_L.previous_saved || 'পূর্বের ব্যালেন্স সেভ হয়েছে।';
                hint.className = 'ame-pc-memo-hint is-ok';
            }
            return true;
        })
        .catch(function () {
            if (hint) {
                hint.hidden = false;
                hint.textContent = AME_PC_L.server_connect_error || 'সার্ভার সংযোগ হয়নি।';
                hint.className = 'ame-pc-memo-hint is-bad';
            }
            return false;
        });
}

function ameSyncMemoBalance(prev, bill, paidAmt, net) {
    var prevEl = document.getElementById('amePcMbPrev');
    var prevLabel = document.getElementById('amePcMbPrevLabel');
    var billEl = document.getElementById('amePcMbBill');
    var paidEl = document.getElementById('amePcMbPaid');
    var totalEl = document.getElementById('amePcMbTotal');
    var totalLabel = document.getElementById('amePcMbTotalLabel');
    var totalRow = document.getElementById('amePcMbTotalRow');
    if (prevLabel) {
        if (prev > 0.009) prevLabel.textContent = AME_PC_L.previous_due_plus || 'সাবেক বকেয়া (+)';
        else if (prev < -0.009) prevLabel.textContent = AME_PC_L.previous_due_minus || 'সাবেক পাওনা (−)';
        else prevLabel.textContent = AME_PC_L.previous_due || 'সাবেক বকেয়া';
    }
    if (prevEl) prevEl.textContent = ameMoneyAbs(prev);
    if (billEl) billEl.textContent = '৳' + (parseFloat(bill) || 0).toFixed(2);
    var paidNum = parseFloat(paidAmt) || 0;
    var paidRow = document.getElementById('amePcMbPaidRow');
    var paidCaption = paidRow ? paidRow.querySelector('span') : null;
    if (paidCaption) {
        paidCaption.textContent = paidNum > 0.009 ? 'পেমেন্ট (−)' : 'পেমেন্ট (+)';
    }
    if (paidEl) paidEl.textContent = (paidNum > 0.009 ? '−' : '+') + '৳' + Math.abs(paidNum).toFixed(2);
    var dueCaption = document.getElementById('pbDueCaption');
    if (dueCaption) dueCaption.textContent = paidNum > 0.009 ? 'বাকি' : 'বাকি (+)';
    if (totalEl) totalEl.textContent = ameMoneyAbs(net);
    if (totalLabel) {
        totalLabel.textContent = AME_PC_L.grand_balance || 'সর্বমোট ব্যালেন্স';
    }
    if (totalRow) {
        totalRow.classList.toggle('is-due', net > 0.009);
        totalRow.classList.toggle('is-ok', net < -0.009);
    }
    var adjPrev = document.getElementById('amePcAdjPrev');
    var adjBill = document.getElementById('amePcAdjBill');
    var adjRemain = document.getElementById('amePcAdjRemain');
    if (adjPrev) adjPrev.textContent = ameMoneyAbs(prev);
    if (adjBill) adjBill.textContent = '৳' + (parseFloat(bill) || 0).toFixed(2);
    if (adjRemain) adjRemain.textContent = ameMoneyAbs(net);
}

var pbDiscFromPct = false;
function pbRound2(n) { return Math.round((parseFloat(n) || 0) * 100) / 100; }
function pbAdjustments(subtotal) {
    var pctEl = document.getElementById('pbDiscPct');
    var amtEl = document.getElementById('pbDiscAmt');
    var pct = pbNum(pctEl.value);
    if (pct < 0) { pct = 0; }
    if (pct > 100) { pct = 100; }
    var amt = pbNum(amtEl.value);
    if (pbDiscFromPct) {
        amt = pbRound2(subtotal * pct / 100);
        if (document.activeElement !== amtEl) { amtEl.value = pct > 0 ? amt.toFixed(2) : ''; }
    }
    if (amt < 0) { amt = 0; }
    var special = pbNum(document.getElementById('pbSpecial').value);
    if (special < 0) { special = 0; }
    return {
        pct: pbDiscFromPct ? pct : 0,
        discount: amt,
        rounding: pbRound2(document.getElementById('pbRounding').value),
        special: special
    };
}
function updateTotal() {
    var subtotal = pbRound2(cart.reduce(function (sum, item) { return sum + pbLineCost(item); }, 0));
    var expenseTotal = pbRound2(expenses.reduce(function (sum, e) { return sum + e.amount; }, 0));
    var adj = pbAdjustments(subtotal);
    var afterDisc = pbRound2(subtotal + expenseTotal - adj.discount);
    var grand = pbRound2(afterDisc + adj.rounding - adj.special);
    document.getElementById('pbAfterDisc').value = afterDisc.toFixed(2);
    var expLine = document.getElementById('pbExpenseLine');
    if (expLine) { expLine.hidden = false; }
    var expSum = document.getElementById('pbExpenseSummary');
    if (expSum) { expSum.textContent = expenseTotal > 0.009 ? '— ৳' + expenseTotal.toFixed(2) : ''; }
    var paidEl = document.getElementById('paidAmount');
    if (paidEl && String(paidEl.value).trim() === '') { paidEl.value = '0'; }
    var paid = parseFloat(paidEl.value) || 0;
    if (paid < 0) { paid = 0; }
    var dueEl = document.getElementById('pbDueDisplay');
    if (dueEl) {
        var billDue = Math.max(0, pbRound2(grand - paid));
        dueEl.value = billDue.toFixed(2);
        dueEl.classList.toggle('is-due', billDue > 0.009);
    }

    document.getElementById('subtotalDisplay').value = subtotal.toFixed(2);
    document.getElementById('expenseDisplay').value = expenseTotal.toFixed(2);
    var invoiceEl = document.getElementById('invoiceTotalDisplay');
    if (invoiceEl) { invoiceEl.value = grand.toFixed(2); }
    var payLaunchNet = document.getElementById('pbPayLaunchNet');
    if (payLaunchNet) { payLaunchNet.textContent = '৳' + grand.toFixed(2); }
    var erSub = document.getElementById('erSubtotalText');
    if (erSub) { erSub.textContent = '৳' + subtotal.toFixed(2); }
    var erExp = document.getElementById('erExpenseText');
    if (erExp) { erExp.textContent = '৳' + expenseTotal.toFixed(2); }
    var erDisc = document.getElementById('erDiscDisplay');
    if (erDisc) { erDisc.textContent = '৳' + (adj.discount + adj.special).toFixed(2); }
    var erAfter = document.getElementById('erAfterDiscText');
    if (erAfter) { erAfter.textContent = '৳' + afterDisc.toFixed(2); }
    var erNet = document.getElementById('erNetText');
    if (erNet) { erNet.textContent = '৳' + grand.toFixed(2); }
    var erCount = document.getElementById('erSumCount');
    if (erCount) { erCount.textContent = String(cart.length); }
    var erCartCount = document.getElementById('erCartCount');
    if (erCartCount) { erCartCount.textContent = '(' + cart.length + (window.AME_LANG === 'en' ? ' items)' : ' টি পণ্য)'); }
    var retailTot = pbRound2(cart.reduce(function (sum, item) { return sum + pbNum(item.qty) * pbNum(item.sale_price); }, 0));
    var wholesaleTot = pbRound2(cart.reduce(function (sum, item) { return sum + pbNum(item.qty) * pbNum(item.wholesale_price); }, 0));
    var dealerTot = pbRound2(cart.reduce(function (sum, item) { return sum + pbNum(item.qty) * pbNum(item.dealer_price); }, 0));
    var footTotal = document.getElementById('cartFootTotal');
    if (footTotal) { footTotal.textContent = '৳' + subtotal.toFixed(2); }
    var footRetail = document.getElementById('cartFootRetail');
    if (footRetail) { footRetail.textContent = '৳' + retailTot.toFixed(2); }
    var footWholesale = document.getElementById('cartFootWholesale');
    if (footWholesale) { footWholesale.textContent = '৳' + wholesaleTot.toFixed(2); }
    var footDealer = document.getElementById('cartFootDealer');
    if (footDealer) { footDealer.textContent = '৳' + dealerTot.toFixed(2); }

    var prevDueLabel = document.getElementById('prevDueLabel');
    var billDueLabel = document.getElementById('billDueLabel');
    var newBalLabel = document.getElementById('newBalLabel');
    var ledgerFormula = document.getElementById('amePcLedgerFormula');
    var caption = document.getElementById('amePcGrandCaption');
    var grandTotalEl = document.getElementById('grandTotalDisplay');
    var newBalEl = document.getElementById('newBalDisplay');

    var memoNet = 0;
    var memoPrev = 0;
    if (selectedPartyType === 'customer') {
        // কাস্টমার থেকে কেনা হলে:
        // সাবেক বকেয়া (+ = দোকান পাবে, - = দোকান দেবে)
        // বর্তমান ক্রয় (- = দোকানের দেনা বৃদ্ধি বা ক্রেতার বকেয়া হ্রাস)
        // আজকের পরিশোধ (+ = ক্রেতাকে নগদ পরিশোধ)
        // নেট ব্যালেন্স = সাবেক ব্যালেন্স - বর্তমান ক্রয় বিল + আজকের পরিশোধ
        var netCustomer = selectedCustomerBalance - grand + paid;
        memoPrev = selectedCustomerBalance;
        memoNet = netCustomer;

        if (ledgerFormula) {
            ledgerFormula.textContent = 'হিসাব: সাবেক ব্যালেন্স যোগ/বিয়োগ − বর্তমান ক্রয় বিল + আজকের পরিশোধ = সর্বমোট ব্যালেন্স';
        }
        if (prevDueLabel) {
            prevDueLabel.textContent = selectedCustomerBalance >= 0 ? (AME_PC_L.previous_due_plus || 'সাবেক বকেয়া (+)') : (AME_PC_L.previous_due_minus || 'সাবেক পাওনা (−)');
        }
        if (billDueLabel) {
            billDueLabel.textContent = 'বর্তমান ক্রয় বিল (−)';
        }
        if (newBalLabel) {
            newBalLabel.textContent = AME_PC_L.grand_balance || 'সর্বমোট ব্যালেন্স';
        }

        var prevEl = document.getElementById('prevDueDisplay');
        if (prevEl && document.activeElement !== prevEl) {
            prevEl.value = selectedCustomerBalance.toFixed(2);
        }
        document.getElementById('billDueDisplay').value = '৳' + grand.toFixed(2);
        
        var netCustText = (netCustomer >= 0 ? '' : '−') + '৳' + Math.abs(netCustomer).toFixed(2);
        if (newBalEl) { newBalEl.value = netCustText; }

        if (netCustomer > 0.009) {
            if (newBalEl) { newBalEl.style.color = '#991B1B'; }
            if (caption) caption.textContent = AME_PC_L.grand_balance || 'সর্বমোট ব্যালেন্স';
            grandTotalEl.textContent = '৳' + netCustomer.toFixed(2);
            grandTotalEl.style.color = '#991B1B';
        } else if (netCustomer < -0.009) {
            if (newBalEl) { newBalEl.style.color = '#166534'; }
            if (caption) caption.textContent = AME_PC_L.grand_balance || 'সর্বমোট ব্যালেন্স';
            grandTotalEl.textContent = '−৳' + Math.abs(netCustomer).toFixed(2);
            grandTotalEl.style.color = '#166534';
        } else {
            if (newBalEl) { newBalEl.style.color = '#3D2A12'; }
            if (caption) caption.textContent = AME_PC_L.grand_balance || 'সর্বমোট ব্যালেন্স';
            grandTotalEl.textContent = '৳0.00';
            grandTotalEl.style.color = '#3D2A12';
        }
    } else {
        // সাধারণ সাপ্লায়ার — সাবেক বকেয়া যোগ/বিয়োগে সর্বমোট ব্যালেন্স
        var current = selectedSupplierDue + grand - paid;
        memoPrev = selectedSupplierDue;
        memoNet = current;
        if (ledgerFormula) {
            ledgerFormula.textContent = AME_PC_L.ledger_formula || 'সাবেক বকেয়া যোগ/বিয়োগ + এই ইনভয়েস − পরিশোধ = সর্বমোট ব্যালেন্স';
        }
        if (prevDueLabel) {
            if (selectedSupplierDue > 0.009) prevDueLabel.textContent = AME_PC_L.previous_due_plus || 'সাবেক বকেয়া (+)';
            else if (selectedSupplierDue < -0.009) prevDueLabel.textContent = AME_PC_L.previous_due_minus || 'সাবেক পাওনা (−)';
            else prevDueLabel.textContent = AME_PC_L.previous_due || 'সাবেক বকেয়া';
        }
        if (billDueLabel) billDueLabel.textContent = AME_PC_L.this_invoice || 'এই ইনভয়েস (+)';
        if (newBalLabel) newBalLabel.textContent = AME_PC_L.grand_balance || 'সর্বমোট ব্যালেন্স';
        if (caption) caption.textContent = AME_PC_L.grand_balance || 'সর্বমোট ব্যালেন্স';

        var prevEl = document.getElementById('prevDueDisplay');
        if (prevEl && document.activeElement !== prevEl) {
            prevEl.value = selectedSupplierDue.toFixed(2);
        }
        document.getElementById('billDueDisplay').value = '৳' + grand.toFixed(2);
        if (newBalEl) {
            newBalEl.value = (current < -0.009 ? '−' : '') + '৳' + Math.abs(current).toFixed(2);
            newBalEl.style.color = current > 0.009 ? '#991B1B' : (current < -0.009 ? '#166534' : '#3D2A12');
        }

        grandTotalEl.textContent = (current < -0.009 ? '−' : '') + '৳' + Math.abs(current).toFixed(2);
        grandTotalEl.style.color = current > 0.009 ? '#991B1B' : (current < -0.009 ? '#166534' : '#3D2A12');
    }
    amePcSlimPartyBoxes();
    ameSyncMemoBalance(memoPrev, grand, paid, memoNet);
    var payableEl = document.getElementById('totalPayableDisplay');
    var remainEl = document.getElementById('remainBalDisplay');
    var payable = memoPrev + grand;
    if (payableEl) payableEl.value = (payable < -0.009 ? '−' : '') + '৳' + Math.abs(payable).toFixed(2);
    if (remainEl) remainEl.value = (memoNet < -0.009 ? '−' : '') + '৳' + Math.abs(memoNet).toFixed(2);

    var footTotal = document.getElementById('cartFootTotal');
    if (footTotal) { footTotal.textContent = '৳' + subtotal.toFixed(2); }
    var sumLabel = document.getElementById('amePcCartSumLabel');
    var sumValue = document.getElementById('amePcCartSumValue');
    if (sumLabel) { sumLabel.textContent = AME_PC_L.product_total + ' · ' + cart.length + (window.AME_LANG === 'en' ? ' line' : ' লাইন'); }
    var netEl = document.getElementById('invoiceTotalDisplay');
    if (netEl) { netEl.classList.toggle('is-bad', grand < 0); }
    if (sumValue) { sumValue.textContent = '৳' + subtotal.toFixed(2); }
    document.getElementById('paymentTypeHidden').value = (grand - paid) > 0.009 ? 'credit' : 'cash';
    amePcPaintLatest(memoNet);
}

function amePcFullPayAmount() {
    var bill = pbNum(document.getElementById('invoiceTotalDisplay').value);
    if (selectedPartyType === 'customer') {
        return pbRound2(Math.max(0, bill - selectedCustomerBalance));
    }
    return pbRound2(Math.max(0, selectedSupplierDue + bill));
}

function amePcPaintLatest(net) {
    var box = document.getElementById('pbLatestBox');
    var label = document.getElementById('pbLatestLabel');
    var value = document.getElementById('pbLatestBalance');
    if (!box || !value) { return; }
    net = pbRound2(net);
    box.classList.toggle('is-due', net > 0.009);
    box.classList.toggle('is-adv', net < -0.009);
    box.classList.toggle('is-zero', Math.abs(net) <= 0.009);
    if (label) {
        if (net < -0.009) {
            label.textContent = window.AME_LANG === 'en' ? 'Advance' : 'অ্যাডভান্স';
        } else {
            label.textContent = AME_PC_L.grand_balance || (window.AME_LANG === 'en' ? 'Latest balance' : 'সর্বশেষ ব্যালেন্স');
        }
    }
    value.textContent = (net < -0.009 ? '−' : '') + '৳' + Math.abs(net).toFixed(2);
}

function pbDefaultChallan() {
    var now = new Date();
    var y = now.getFullYear();
    var m = String(now.getMonth() + 1).padStart(2, '0');
    var d = String(now.getDate()).padStart(2, '0');
    return 'PUR-' + y + m + d + '-001';
}
function maybeFillNextMemo() {
    var memoEl = document.getElementById('memoInput');
    var sid = document.getElementById('supplierIdHidden').value;
    if ((memoEl.value || '').trim() !== '') { return; }
    if (!sid) {
        memoEl.value = pbDefaultChallan();
        return;
    }
    fetch('draft_query.php?action=memo_check&supplier_id=' + encodeURIComponent(sid) + '&memo=', { credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data || data.status !== 'success') { return; }
            if ((memoEl.value || '').trim() === '' && data.suggestion) {
                memoEl.value = data.suggestion;
                scheduleMemoCheck();
            }
        })
        .catch(function () {});
}
function pbForceSuggestMemo() {
    var memoEl = document.getElementById('memoInput');
    var sid = document.getElementById('supplierIdHidden').value;
    if (!sid) {
        memoEl.value = pbDefaultChallan();
        scheduleMemoCheck();
        return;
    }
    fetch('draft_query.php?action=memo_check&supplier_id=' + encodeURIComponent(sid) + '&memo=', { credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data || data.status !== 'success') { return; }
            memoEl.value = data.suggestion || pbDefaultChallan();
            scheduleMemoCheck();
        })
        .catch(function () { memoEl.value = pbDefaultChallan(); });
}
function pbClearAllForm() {
    if (cart.length === 0 && expenses.length === 0) {
        pbResetEntry();
        return;
    }
    var msg = window.AME_LANG === 'en'
        ? 'Clear every product line, extra expense and discount from this challan?'
        : 'এই চালানের সব পণ্য, অতিরিক্ত খরচ ও ছাড় মুছে ফেলবেন?';
    if (!confirm(msg)) { return; }
    cart = [];
    expenses = [];
    ['pbDiscPct', 'pbDiscAmt', 'pbRounding', 'pbSpecial', 'notesInput', 'paidAmount'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { el.value = ''; }
    });
    pbResetEntry();
    renderCart();
    renderExpenses();
}
function pbOpenDraftList() {
    amePcSetMode('complete');
    amePcCloseDrawers();
    amePcRenderDrafts();
    document.getElementById('draftDrawer').classList.add('open');
    document.getElementById('draftDrawer').setAttribute('aria-hidden', 'false');
}

function scheduleMemoCheck() {
    clearTimeout(memoCheckTimer);
    memoCheckTimer = setTimeout(function () { checkMemoUnique(false); }, 280);
}

function checkMemoUnique(force) {
    var sid = document.getElementById('supplierIdHidden').value;
    var memo = (document.getElementById('memoInput').value || '').trim();
    var hint = document.getElementById('amePcMemoHint');
    if (!memo) {
        memoIsDuplicate = false;
        if (hint) {
            hint.hidden = !sid;
            hint.className = 'ame-pc-memo-hint';
            hint.textContent = sid ? AME_PC_L.memo_empty_hint : '';
        }
        return Promise.resolve(false);
    }
    if (!sid) {
        memoIsDuplicate = false;
        if (hint) {
            hint.hidden = false;
            hint.className = 'ame-pc-memo-hint';
            hint.textContent = AME_PC_L.memo_select_supplier_hint;
        }
        return Promise.resolve(false);
    }
    return fetch(
        'draft_query.php?action=memo_check&supplier_id=' + encodeURIComponent(sid) + '&memo=' + encodeURIComponent(memo)
            + '&exclude=' + encodeURIComponent((document.getElementById('originalMemoHidden') || {}).value || ''),
        { credentials: 'same-origin' }
    ).then(function (res) { return res.json(); }).then(function (data) {
        if (!data || data.status !== 'success') { return false; }
        memoIsDuplicate = !!data.exists;
        if (hint) {
            hint.hidden = false;
            if (memoIsDuplicate) {
                hint.className = 'ame-pc-memo-hint is-bad';
                hint.textContent = AME_PC_L.memo_duplicate_prefix + memo + AME_PC_L.memo_duplicate_middle
                    + (data.suggestion ? AME_PC_L.memo_duplicate_suggestion + data.suggestion + ')' : '') + AME_PC_L.sentence_end;
            } else {
                hint.className = 'ame-pc-memo-hint is-ok';
                hint.textContent = AME_PC_L.memo_ok_prefix + memo + AME_PC_L.memo_ok_suffix;
            }
        }
        return memoIsDuplicate;
    }).catch(function () {
        if (force) { throw new Error(AME_PC_L.memo_verify_failed); }
        return false;
    });
}

function ameMountDeskOverlay(el) {
    if (!el || !document.body) { return el; }
    if (el.parentNode !== document.body) {
        document.body.appendChild(el);
    }
    return el;
}
function ameCloseDeskOverlay(id) {
    var el = document.getElementById(id);
    if (!el) { return; }
    el.classList.remove('is-open');
    el.style.display = 'none';
    el.setAttribute('aria-hidden', 'true');
}
function ameOpenDeskOverlay(id) {
    var ids = ['quickAddOverlay', 'quickAddSupplierOverlay', 'voidMemoPayOverlay'];
    ids.forEach(function (other) {
        if (other !== id) { ameCloseDeskOverlay(other); }
    });
    var el = ameMountDeskOverlay(document.getElementById(id));
    if (!el) { return null; }
    el.classList.add('is-open');
    el.style.display = 'flex';
    el.setAttribute('aria-hidden', 'false');
    return el;
}
function openQuickAddModal() {
    document.getElementById('qaName').value = document.getElementById('productSearch').value.trim();
    document.getElementById('qaPurchase').value = '';
    document.getElementById('qaDealer').value = 0;
    document.getElementById('qaWholesale').value = 0;
    document.getElementById('qaRetail').value = '';
    document.getElementById('quickAddError').style.display = 'none';
    ameOpenDeskOverlay('quickAddOverlay');
    var name = document.getElementById('qaName');
    if (name) { setTimeout(function () { name.focus(); }, 30); }
}
function closeQuickAddModal() { ameCloseDeskOverlay('quickAddOverlay'); }

function submitQuickAdd() {
    var name = document.getElementById('qaName').value.trim();
    var purchase = parseFloat(document.getElementById('qaPurchase').value) || 0;
    var dealer = parseFloat(document.getElementById('qaDealer').value) || 0;
    var wholesale = parseFloat(document.getElementById('qaWholesale').value) || 0;
    var retail = parseFloat(document.getElementById('qaRetail').value) || 0;
    var errBox = document.getElementById('quickAddError');
    errBox.style.display = 'none';
    if (!name) { errBox.textContent = AME_PC_L.err_product_name; errBox.style.display = 'block'; return; }
    if (purchase <= 0) { errBox.textContent = AME_PC_L.err_purchase_price; errBox.style.display = 'block'; return; }
    if (retail < purchase) { errBox.textContent = AME_PC_L.err_retail_low; errBox.style.display = 'block'; return; }
    if (dealer > 0 && dealer < purchase) { errBox.textContent = AME_PC_L.err_dealer_low; errBox.style.display = 'block'; return; }
    if (wholesale > 0 && wholesale < purchase) { errBox.textContent = AME_PC_L.err_wholesale_low; errBox.style.display = 'block'; return; }
    var btn = document.getElementById('qaSubmitBtn');
    btn.disabled = true; btn.textContent = AME_PC_L.adding_label;
    var csrfToken = document.querySelector('input[name=csrf_token]').value;
    var body = new URLSearchParams({ csrf_token: csrfToken, name: name, purchase_price: purchase, dealer_price: dealer, wholesale_price: wholesale, sale_price: retail });
    fetch('actions/quick_add_product.php', { method: 'POST', body: body })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            btn.disabled = false; btn.textContent = AME_PC_L.create_and_add;
            if (data.status !== 'success') { errBox.textContent = data.message || AME_PC_L.generic_error; errBox.style.display = 'block'; return; }
            PRODUCTS.push(data.product);
            addToCart(data.product);
            closeQuickAddModal();
            document.getElementById('productSearch').value = '';
        })
        .catch(function () {
            btn.disabled = false; btn.textContent = AME_PC_L.create_and_add;
            errBox.textContent = AME_PC_L.server_connect_error; errBox.style.display = 'block';
        });
}

function openQuickAddSupplierModal() {
    document.getElementById('qsName').value = document.getElementById('supplierSearch').value.trim();
    document.getElementById('qsPhone').value = '';
    document.getElementById('qsAddress').value = '';
    document.getElementById('qsOpening').value = '0';
    document.getElementById('quickAddSupplierError').style.display = 'none';
    ameOpenDeskOverlay('quickAddSupplierOverlay');
    var name = document.getElementById('qsName');
    if (name) { setTimeout(function () { name.focus(); }, 30); }
}
function closeQuickAddSupplierModal() { ameCloseDeskOverlay('quickAddSupplierOverlay'); }

function submitQuickAddSupplier() {
    var name = document.getElementById('qsName').value.trim();
    var phone = document.getElementById('qsPhone').value.trim();
    var address = document.getElementById('qsAddress').value.trim();
    var opening = parseFloat(document.getElementById('qsOpening').value) || 0;
    var errBox = document.getElementById('quickAddSupplierError');
    errBox.style.display = 'none';
    if (!name) { errBox.textContent = AME_PC_L.err_supplier_name; errBox.style.display = 'block'; return; }
    var btn = document.getElementById('qsSubmitBtn');
    btn.disabled = true; btn.textContent = AME_PC_L.adding_label;
    var body = new URLSearchParams({ csrf_token: amePcCsrf(), name: name, phone: phone, address: address, opening_balance: String(opening) });
    fetch('actions/quick_add_supplier.php', { method: 'POST', body: body })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            btn.disabled = false; btn.textContent = AME_PC_L.create_and_select;
            if (data.status !== 'success') { errBox.textContent = data.message || AME_PC_L.generic_error; errBox.style.display = 'block'; return; }
            SUPPLIERS.push(data.supplier);
            document.getElementById('supplierIdHidden').value = data.supplier.id;
            document.getElementById('supplierSearch').value = data.supplier.name;
            selectedSupplierDue = parseFloat(data.supplier.total_due || 0) || 0;
            document.getElementById('supPhone').textContent = data.supplier.phone || '—';
            ameShowSupplierDue(selectedSupplierDue);
            document.getElementById('supplierInfo').classList.add('show');
            closeQuickAddSupplierModal();
            if (typeof maybeFillNextMemo === 'function') { maybeFillNextMemo(); }
            if (typeof scheduleMemoCheck === 'function') { scheduleMemoCheck(); }
            updateTotal();
        })
        .catch(function () {
            btn.disabled = false; btn.textContent = AME_PC_L.create_and_select;
            errBox.textContent = AME_PC_L.server_connect_error; errBox.style.display = 'block';
        });
}

function openVoidMemoPayModal() {
    var errBox = document.getElementById('voidMemoPayError');
    var reason = document.getElementById('voidPayReason');
    if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }
    if (reason) { reason.value = ''; }
    ameOpenDeskOverlay('voidMemoPayOverlay');
    if (reason) { setTimeout(function () { reason.focus(); }, 30); }
}
function closeVoidMemoPayModal() { ameCloseDeskOverlay('voidMemoPayOverlay'); }

function submitVoidMemoPay() {
    var memo = (AME_EDIT_MEMO && AME_EDIT_MEMO.memo) ? String(AME_EDIT_MEMO.memo) : '';
    var reasonEl = document.getElementById('voidPayReason');
    var reason = reasonEl ? String(reasonEl.value || '').trim() : '';
    var errBox = document.getElementById('voidMemoPayError');
    if (errBox) { errBox.style.display = 'none'; }
    if (!memo) {
        if (errBox) { errBox.textContent = AME_PC_L.generic_error; errBox.style.display = 'block'; }
        return;
    }
    if (reason.length < 3) {
        if (errBox) { errBox.textContent = AME_PC_L.void_pay_need_reason; errBox.style.display = 'block'; }
        return;
    }
    var btn = document.getElementById('voidPaySubmitBtn');
    if (btn) { btn.disabled = true; btn.textContent = AME_PC_L.void_pay_working; }
    var supplierId = (AME_EDIT_MEMO && AME_EDIT_MEMO.supplier_id) ? String(AME_EDIT_MEMO.supplier_id) : '';
    var body = new URLSearchParams({ csrf_token: amePcCsrf(), memo_no: memo, supplier_id: supplierId, reason: reason });
    fetch('actions/void_memo_payment.php', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (btn) { btn.disabled = false; btn.textContent = AME_PC_L.void_pay_confirm; }
            if (!data || data.status !== 'success') {
                if (errBox) { errBox.textContent = (data && data.message) || AME_PC_L.generic_error; errBox.style.display = 'block'; }
                return;
            }
            closeVoidMemoPayModal();
            window.location = 'add.php?edit_memo=' + encodeURIComponent(memo) + '&supplier_id=' + encodeURIComponent(supplierId);
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = AME_PC_L.void_pay_confirm; }
            if (errBox) { errBox.textContent = AME_PC_L.server_connect_error; errBox.style.display = 'block'; }
        });
}

document.addEventListener('DOMContentLoaded', function () {
    ['quickAddOverlay', 'quickAddSupplierOverlay', 'voidMemoPayOverlay'].forEach(function (id) {
        var el = document.getElementById(id);
        ameMountDeskOverlay(el);
        if (el && !el.getAttribute('data-ame-overlay-bound')) {
            el.setAttribute('data-ame-overlay-bound', '1');
            el.addEventListener('click', function (e) {
                if (e.target === el) {
                    ameCloseDeskOverlay(id);
                }
            });
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        ameCloseDeskOverlay('quickAddOverlay');
        ameCloseDeskOverlay('quickAddSupplierOverlay');
        ameCloseDeskOverlay('voidMemoPayOverlay');
    });
    var wantVoid = <?php echo (!empty($ame_edit_payload) && (float)($ame_edit_payload['paid_amount'] ?? 0) > 0.009 && !empty($_GET['void_pay'])) ? 'true' : 'false'; ?>;
    if (wantVoid) { openVoidMemoPayModal(); }
});

document.getElementById('purchaseForm').addEventListener('submit', function (e) {
    e.preventDefault();
    amePcSubmitPurchase(this);
});

document.getElementById('accountSelect').addEventListener('change', function () {
    updateTotal();
    if (typeof amePaintPayAccounts === 'function') { amePaintPayAccounts(); }
});
(function () {
    var sel = document.getElementById('accountSelect');
    var grid = document.getElementById('amePayAccGrid');
    if (!sel || !grid) { return; }
    window.amePaintPayAccounts = function () {
        var v = String(sel.value || '');
        Array.prototype.forEach.call(grid.querySelectorAll('.ame-pay-acc'), function (btn) {
            btn.classList.toggle('is-picked', String(btn.getAttribute('data-id') || '') === v);
        });
    };
    grid.addEventListener('click', function (event) {
        var btn = event.target.closest ? event.target.closest('.ame-pay-acc') : null;
        if (!btn) { return; }
        sel.value = btn.getAttribute('data-id') || '';
        sel.dispatchEvent(new Event('change', { bubbles: true }));
    });
    amePaintPayAccounts();
})();
document.getElementById('paidAmount').addEventListener('input', function () {
    autoPaySynced = false;
    updateTotal();
});
document.getElementById('memoInput').addEventListener('input', scheduleMemoCheck);
var amePcPrevDueInput = document.getElementById('prevDueDisplay');
if (amePcPrevDueInput && amePcPrevDueInput.type !== 'hidden') amePcPrevDueInput.addEventListener('input', function () {
    var v = amePcReadPrevDueInput();
    if (selectedPartyType === 'customer') {
        selectedCustomerBalance = v;
    } else {
        selectedSupplierDue = v;
    }
    updateTotal();
});
var amePcSavePrevBtn = document.getElementById('amePcSavePrevDue');
if (amePcSavePrevBtn) amePcSavePrevBtn.addEventListener('click', function () {
    amePcSavePreviousDue();
});
(function () {
    var payBox = document.getElementById('paidAmount');
    if (!payBox) return;
    if (String(payBox.value).trim() === '') payBox.value = '0';
    payBox.addEventListener('focus', function () { var el = this; setTimeout(function () { el.select(); }, 0); });
})();

function amePcFillHidden() {
    updateTotal();
    var paidAmt = parseFloat(document.getElementById('paidAmount').value) || 0;
    document.getElementById('memoHidden').value = document.getElementById('memoInput').value.trim();
    document.getElementById('accountIdHidden').value = document.getElementById('accountSelect').value;
    document.getElementById('paidAmountHidden').value = paidAmt;
    document.getElementById('advanceAmountHidden').value = paidAmt;
    document.getElementById('advanceAccountIdHidden').value = document.getElementById('accountSelect').value;
    document.getElementById('itemsJsonHidden').value = JSON.stringify(cart);
    document.getElementById('expensesJsonHidden').value = JSON.stringify(expenses);
    document.getElementById('notesHidden').value = document.getElementById('notesInput').value.trim();
    var pbSub = pbRound2(cart.reduce(function (sum, item) { return sum + pbLineCost(item); }, 0));
    var pbAdj = pbAdjustments(pbSub);
    document.getElementById('discPctHidden').value = String(pbAdj.pct);
    document.getElementById('discAmtHidden').value = String(pbAdj.discount);
    document.getElementById('roundingHidden').value = String(pbAdj.rounding);
    document.getElementById('specialHidden').value = String(pbAdj.special);
    document.getElementById('customerIdHidden').value = selectedCustomerId ? String(selectedCustomerId) : '';
    document.getElementById('partyTypeHidden').value = selectedPartyType;
    var prevShown = amePcReadPrevDueInput();
    if (selectedPartyType === 'customer') {
        document.getElementById('customerBalanceHidden').value = String(prevShown);
        document.getElementById('previousDueHidden').value = String((-prevShown).toFixed(2));
    } else {
        document.getElementById('customerBalanceHidden').value = '0';
        document.getElementById('previousDueHidden').value = String(prevShown.toFixed(2));
    }
    var st = document.getElementById('amePcSettleType');
    var sr = document.getElementById('amePcSettleReason');
    document.getElementById('settleTypeHidden').value = st && st.value ? st.value : 'adjustment';
    document.getElementById('settleReasonHidden').value = sr ? sr.value.trim() : '';
}

async function amePcSubmitPurchase(form) {
    if (amePcSubmitBusy) { return; }
    if (cart.length === 0) { alert(AME_PC_L.add_at_least_one_product); return; }
    var priceIssue = null;
    cart.forEach(function (item) {
        if (!priceIssue) { priceIssue = salePriceIssue(item); }
    });
    if (priceIssue) {
        alert('⚠️ ' + priceIssue);
        return;
    }
    var pbBadLine = cart.find(function (item) {
        return (pbNum(item.qty) + pbNum(item.free_qty)) <= 0 || (pbNum(item.qty) > 0 && pbNum(item.purchase_price) <= 0);
    });
    if (pbBadLine) {
        alert('⚠️ "' + pbBadLine.name + '" — ' + (window.AME_LANG === 'en' ? 'enter quantity and buy price (or mark it free).' : 'পরিমাণ ও ক্রয় মূল্য দিন (অথবা ফ্রির পরিমাণ লিখুন)।'));
        return;
    }
    var pbCheckSub = pbRound2(cart.reduce(function (sum, item) { return sum + pbLineCost(item); }, 0));
    var pbCheckAdj = pbAdjustments(pbCheckSub);
    if (pbCheckAdj.discount > pbCheckSub + 0.009) {
        alert(window.AME_LANG === 'en' ? 'Regular discount cannot exceed the product total.' : 'রেগুলার ডিসকাউন্ট পণ্যের মোট দামের চেয়ে বেশি হতে পারবে না।');
        return;
    }
    if (pbNum(document.getElementById('invoiceTotalDisplay').value) < 0) {
        alert(window.AME_LANG === 'en' ? 'Net payable cannot be below zero.' : 'নেট প্রদেয় শূন্যের কম হতে পারবে না।');
        return;
    }
    var supplierId = document.getElementById('supplierIdHidden').value;
    if (!supplierId) { alert(AME_PC_L.select_supplier_required); return; }
    var paidAmt = parseFloat(document.getElementById('paidAmount').value) || 0;
    if (paidAmt < 0) { alert(AME_PC_L.payment_negative); return; }
    if (paidAmt > 0 && !document.getElementById('accountSelect').value) {
        alert(AME_PC_L.select_account_for_payment);
        return;
    }
    amePcSubmitBusy = true;
    var completeBtn = document.getElementById('amePcCompleteBtn');
    if (window.AmeSubmitWait) {
        window.AmeSubmitWait.show(completeBtn);
        window.AmeSubmitWait.showIn(form);
    }
    var amePcOriginalMemo = (document.getElementById('originalMemoHidden').value || '').trim();
    var amePcCurrentMemo = (document.getElementById('memoInput').value || '').trim();
    var amePcMemoActuallyChanged = !AME_EDIT_MEMO || (amePcCurrentMemo !== amePcOriginalMemo);
    if (amePcMemoActuallyChanged) {
        // নতুন ক্রয়ের সময়, অথবা এডিট মোডে মেমো নম্বরটা সত্যিই বদলানো হলে —
        // এই মেমো নম্বর অন্য কোনো মেমোর সাথে সাংঘর্ষিক কিনা যাচাই করা হয়।
        // এডিট মোডে মেমো অপরিবর্তিত থাকলে এই চেক চালানো হয় না, কারণ তখন
        // সার্ভারে ওই নম্বরটা এই মেমোরই — সেটাকে "ডুপ্লিকেট" ধরা ভুল হবে।
        try {
            var dup = await checkMemoUnique(true);
            if (dup || memoIsDuplicate) {
                amePcSubmitBusy = false;
                if (window.AmeSubmitWait) {
                    window.AmeSubmitWait.hide(completeBtn);
                    window.AmeSubmitWait.hideIn(form);
                }
                alert(AME_PC_L.memo_duplicate_alert);
                return;
            }
        } catch (err) {
            amePcSubmitBusy = false;
            if (window.AmeSubmitWait) {
                window.AmeSubmitWait.hide(completeBtn);
                window.AmeSubmitWait.hideIn(form);
            }
            alert('⚠️ ' + (err.message || AME_PC_L.memo_verify_failed));
            return;
        }
    }
    amePcFillHidden();
    var postUrl = String(form.getAttribute('action') || 'process_purchase.php');
    var useAjaxComplete = /process_purchase\.php$/i.test(postUrl.split('?')[0]);
    if (!useAjaxComplete) {
        HTMLFormElement.prototype.submit.call(form);
        return;
    }
    var fd = new FormData(form);
    fd.set('ajax_complete', '1');
    try {
        var res = await fetch(postUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        var ct = (res.headers.get('content-type') || '').toLowerCase();
        if (ct.indexOf('application/json') !== -1) {
            var data = await res.json();
            if (data && data.ok === false) {
                alert('⚠️ ' + (data.message || AME_PC_L.generic_error));
                if (data.code === 'insufficient_balance') {
                    pbOpenPay();
                    var acc = document.getElementById('accountSelect');
                    if (acc) { acc.focus(); }
                }
                return;
            }
        }
        if (res.redirected && res.url && res.url.indexOf('success.php') !== -1) {
            window.location.href = res.url;
            return;
        }
        var html = await res.text();
        if (res.url && res.url.indexOf('success.php') !== -1) {
            window.location.href = res.url;
            return;
        }
        if (/অপর্যাপ্ত ব্যালেন্স|পর্যাপ্ত টাকা নেই/i.test(html)) {
            alert('⚠️ ' + (window.AME_LANG === 'en'
                ? 'Insufficient account balance. Pick another account with enough funds and complete again.'
                : 'অ্যাকাউন্টে পর্যাপ্ত টাকা নেই। ব্যালেন্সযুক্ত অন্য অ্যাকাউন্ট বেছে নিয়ে আবার সম্পন্ন করুন।'));
            pbOpenPay();
            var acc2 = document.getElementById('accountSelect');
            if (acc2) { acc2.focus(); }
            return;
        }
        alert('⚠️ ' + (AME_PC_L.generic_error || 'সংরক্ষণ হয়নি।'));
    } catch (netErr) {
        alert('⚠️ ' + (AME_PC_L.server_connect_error || (netErr && netErr.message) || 'সংযোগ হয়নি।'));
    } finally {
        amePcSubmitBusy = false;
        if (window.AmeSubmitWait) {
            window.AmeSubmitWait.hide(completeBtn);
            window.AmeSubmitWait.hideIn(form);
        }
    }
}

<?php if (!empty($ame_pos_title_can_edit)): ?>
function ameOpenPosTitleEdit() {
    var box = document.getElementById('amePosTitleEdit');
    if (box) { box.hidden = false; }
}
function ameClosePosTitleEdit() {
    var box = document.getElementById('amePosTitleEdit');
    if (box) { box.hidden = true; }
}
function ameSavePosTitleLine() {
    var input = document.getElementById('amePosTitleInput');
    var msg = document.getElementById('amePosTitleMsg');
    var line = document.getElementById('amePosTitleLine');
    if (!input) { return; }
    msg.textContent = AME_PC_L.saving_label;
    var body = new FormData();
    body.append('csrf_token', <?php echo json_encode(csrf_token()); ?>);
    body.append('line_text', input.value);
    body.append('ajax', '1');
    fetch('/modules/settings/process_pos_title.php', {
        method: 'POST',
        body: body,
        headers: { 'Accept': 'application/json', 'X-CSRF-Token': <?php echo json_encode(csrf_token()); ?> }
    }).then(function (res) { return res.json(); }).then(function (data) {
        if (data && data.success) {
            if (line) {
                var live = line.querySelector('.ame-pos-title-live');
                var text = data.line_text || input.value.trim();
                if (live) {
                    live.textContent = text;
                } else {
                    line.textContent = text;
                }
            }
            msg.textContent = AME_PC_L.saved_label;
            setTimeout(ameClosePosTitleEdit, 500);
        } else {
            msg.textContent = (data && data.message) ? data.message : AME_PC_L.not_saved_label;
        }
    }).catch(function () { msg.textContent = AME_PC_L.server_error_label; });
}
<?php endif; ?>

var pbPicked = null;
function pbEl(id) { return document.getElementById(id); }
function pbMsg(text, bad) {
    var el = pbEl('pbEntryMsg');
    if (!el) { return; }
    el.hidden = !text;
    el.textContent = text || '';
    el.classList.toggle('is-bad', !!bad);
}
function pbPriceFromMode(valueId, modeId, buy) {
    var v = pbNum(pbEl(valueId).value);
    if (pbEl(modeId).value === 'pct') { return pbRound2(buy + (buy * v / 100)); }
    return v;
}
function pbSyncEntryTotal(fromTotal) {
    var qty = pbNum(pbEl('pbQty').value);
    if (fromTotal) {
        var total = pbNum(pbEl('pbLineTotal').value);
        if (qty > 0 && !pbEl('pbFreeItem').checked) { pbEl('pbBuy').value = pbRound2(total / qty); }
        return;
    }
    var buy = pbNum(pbEl('pbBuy').value);
    pbEl('pbLineTotal').value = (qty > 0 && buy > 0) ? pbRound2(qty * buy).toFixed(2) : '';
}
function pbApplyFreeMode() {
    var free = pbEl('pbFreeItem').checked;
    ['pbBuy', 'pbLineTotal'].forEach(function (id) {
        pbEl(id).disabled = free;
        if (free) { pbEl(id).value = ''; }
    });
    pbEl('pbEntry') && pbEl('pbEntry').classList.toggle('is-free', free);
    if (free && pbPicked) { pbEl('pbFree').focus(); }
}
function pbResetEntry() {
    pbPicked = null;
    ['productSearch', 'pbBuy', 'pbQty', 'pbFree', 'pbLineTotal', 'pbRetail', 'pbWholesale', 'pbDealer', 'pbNameShow', 'pbSku'].forEach(function (id) { var el = pbEl(id); if (el) { el.value = ''; } });
    if (pbEl('pbQty')) { pbEl('pbQty').value = '1'; }
    pbEl('pbRetailMode').value = 'tk';
    pbEl('pbWholesaleMode').value = 'tk';
    pbEl('pbDealerMode').value = 'tk';
    pbEl('pbFreeItem').checked = false;
    pbApplyFreeMode();
}
function pbSelectProduct(p) {
    pbPicked = p;
    productSearch.value = String(p.name || '').trim() || ('#' + p.id);
    var nameShow = pbEl('pbNameShow');
    if (nameShow) { nameShow.value = productSearch.value; }
    var skuEl = pbEl('pbSku');
    if (skuEl) { skuEl.value = p.sku || p.barcode || ''; }
    var unitEl = pbEl('pbUnitShow');
    if (unitEl) { unitEl.value = window.AME_LANG === 'en' ? 'Pcs' : 'পিস'; }
    var free = pbEl('pbFreeItem').checked;
    pbEl('pbBuy').value = free ? '' : (pbNum(p.purchase_price) > 0 ? pbNum(p.purchase_price) : '');
    pbEl('pbRetailMode').value = 'tk';
    pbEl('pbWholesaleMode').value = 'tk';
    pbEl('pbDealerMode').value = 'tk';
    pbEl('pbRetail').value = pbNum(p.sale_price) > 0 ? pbNum(p.sale_price) : '';
    pbEl('pbWholesale').value = pbNum(p.wholesale_price) > 0 ? pbNum(p.wholesale_price) : '';
    pbEl('pbDealer').value = pbNum(p.dealer_price) > 0 ? pbNum(p.dealer_price) : '';
    if (free) {
        pbEl('pbQty').value = '';
        pbEl('pbFree').value = pbEl('pbFree').value || '1';
        pbEl('pbFree').focus();
        pbEl('pbFree').select();
    } else {
        pbEl('pbQty').value = '1';
        pbSyncEntryTotal(false);
        pbEl('pbQty').focus();
        pbEl('pbQty').select();
    }
    pbMsg('');
}
function pbAddEntry() {
    if (!pbPicked) {
        var q = productSearch.value.trim().toLowerCase();
        var exact = q ? PRODUCTS.find(function (p) {
            return [p.barcode, p.sku, p.id].some(function (f) { return String(f || '').toLowerCase() === q; });
        }) : null;
        if (exact) { pbSelectProduct(exact); return; }
        pbMsg(window.AME_LANG === 'en' ? 'Pick a product from the list first.' : 'আগে তালিকা থেকে পণ্য বাছাই করুন।', true);
        productSearch.focus();
        return;
    }
    var free = pbEl('pbFreeItem').checked;
    var buy = free ? 0 : pbNum(pbEl('pbBuy').value);
    var qty = free ? 0 : pbNum(pbEl('pbQty').value);
    var freeQty = pbNum(pbEl('pbFree').value);
    if (qty < 0 || freeQty < 0) { pbMsg(AME_PC_L.valid_amount, true); return; }
    if (qty + freeQty <= 0) {
        pbMsg(window.AME_LANG === 'en' ? 'Enter a quantity.' : 'পরিমাণ লিখুন।', true);
        pbEl(free ? 'pbFree' : 'pbQty').focus();
        return;
    }
    if (qty > 0 && buy <= 0) {
        pbMsg(window.AME_LANG === 'en' ? 'Enter the buy price.' : 'ক্রয় মূল্য লিখুন।', true);
        pbEl('pbBuy').focus();
        return;
    }
    var priceBase = buy > 0 ? buy : pbNum(pbPicked.purchase_price);
    var retail = pbPriceFromMode('pbRetail', 'pbRetailMode', priceBase);
    var wholesale = pbPriceFromMode('pbWholesale', 'pbWholesaleMode', priceBase);
    var dealer = pbPriceFromMode('pbDealer', 'pbDealerMode', priceBase);
    var trial = {
        name: String(pbPicked.name || '').trim() || ('#' + pbPicked.id),
        purchase_price: buy,
        dealer_price: dealer,
        wholesale_price: wholesale,
        sale_price: retail
    };
    var issue = salePriceIssue(trial);
    if (issue) {
        pbMsg('⚠️ ' + issue, true);
        return;
    }
    var line = cart.find(function (c) { return Number(c.product_id) === Number(pbPicked.id) && (pbNum(c.purchase_price) === buy); });
    if (line) {
        line.qty = pbRound2(pbNum(line.qty) + qty);
        line.free_qty = pbRound2(pbNum(line.free_qty) + freeQty);
        line.sale_price = retail;
        line.wholesale_price = wholesale;
        line.dealer_price = dealer;
    } else {
        cart.push({
            product_id: pbPicked.id,
            sku: pbPicked.sku || '',
            name: String(pbPicked.name || '').trim() || ('#' + pbPicked.id),
            stock: pbPicked.stock,
            qty: qty,
            free_qty: freeQty,
            unit_id: 0,
            units: [],
            unit_hint: '',
            purchase_price: buy,
            dealer_price: dealer,
            wholesale_price: wholesale,
            sale_price: retail
        });
        ameLoadUnits(cart[cart.length - 1]);
    }
    renderCart();
    pbResetEntry();
    pbMsg('', false);
    productSearch.focus();
}
pbEl('pbAddBtn').addEventListener('click', pbAddEntry);
pbEl('pbFreeItem').addEventListener('change', pbApplyFreeMode);
pbEl('pbBuy').addEventListener('input', function () { pbSyncEntryTotal(false); });
pbEl('pbQty').addEventListener('input', function () { pbSyncEntryTotal(false); });
pbEl('pbLineTotal').addEventListener('input', function () { pbSyncEntryTotal(true); });
['pbBuy', 'pbQty', 'pbFree', 'pbLineTotal', 'pbRetail', 'pbWholesale', 'pbDealer'].forEach(function (id) {
    pbEl(id).addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); pbAddEntry(); }
    });
});
productSearch.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') { return; }
    e.preventDefault();
    var first = productResults.querySelector('.pc-result-item[data-id]');
    var q = productSearch.value.trim().toLowerCase();
    var exact = q ? PRODUCTS.find(function (p) {
        return [p.barcode, p.sku].some(function (f) { return f && String(f).toLowerCase() === q; });
    }) : null;
    if (exact) { pbSelectProduct(exact); productResults.classList.remove('open'); return; }
    if (pbPicked && productSearch.value === (String(pbPicked.name || '').trim() || ('#' + pbPicked.id))) { pbAddEntry(); return; }
    if (first) { first.click(); }
});
productSearch.addEventListener('input', function () { pbPicked = null; });

var amePcPayFullBtn = pbEl('pbPayFull');
if (amePcPayFullBtn) amePcPayFullBtn.addEventListener('click', function () {
    updateTotal();
    var amount = amePcFullPayAmount();
    if (amount > 0.009 && !pbEl('accountSelect').value) {
        var firstAcc = pbEl('accountSelect').querySelector('option[value]:not([value=""])');
        if (firstAcc) { pbEl('accountSelect').value = firstAcc.value; }
    }
    pbEl('paidAmount').value = amount > 0.009 ? amount.toFixed(2) : '0';
    autoPaySynced = false;
    updateTotal();
});
var amePcPayNoneBtn = pbEl('pbPayNone');
if (amePcPayNoneBtn) amePcPayNoneBtn.addEventListener('click', function () {
    pbEl('paidAmount').value = '0';
    autoPaySynced = false;
    updateTotal();
});
pbEl('pbDiscPct').addEventListener('input', function () { pbDiscFromPct = this.value.trim() !== ''; updateTotal(); });
pbEl('pbDiscAmt').addEventListener('input', function () { pbDiscFromPct = false; pbEl('pbDiscPct').value = ''; updateTotal(); });
['pbRounding', 'pbSpecial'].forEach(function (id) { pbEl(id).addEventListener('input', updateTotal); });

function pbPartyMode() {
    var r = document.querySelector('input[name=pb_party]:checked');
    return r ? r.value : 'supplier';
}
document.querySelectorAll('input[name=pb_party]').forEach(function (r) {
    r.addEventListener('change', function () {
        supplierSearch.placeholder = pbPartyMode() === 'customer'
            ? (window.AME_LANG === 'en' ? 'Registered customer' : 'নিবন্ধিত ক্রেতা')
            : (window.AME_LANG === 'en' ? 'Supplier' : 'সরবরাহকারী');
        pbEl('openQuickSupplier').hidden = pbPartyMode() === 'customer';
        if (supplierSearch.value.trim() !== '') {
            supplierSearch.value = '';
            supplierSearch.dispatchEvent(new Event('input'));
        }
        supplierSearch.focus();
    });
});
function pbSetParty(mode) {
    var r = document.querySelector('input[name=pb_party][value="' + mode + '"]');
    if (r) { r.checked = true; }
    pbEl('openQuickSupplier').hidden = mode === 'customer';
}
function pbLoadAdjustments(a) {
    a = a || {};
    var pct = pbNum(a.discount_percent);
    pbDiscFromPct = pct > 0;
    pbEl('pbDiscPct').value = pct > 0 ? pct : '';
    pbEl('pbDiscAmt').value = pbNum(a.discount_amount) > 0 ? pbNum(a.discount_amount).toFixed(2) : '';
    pbEl('pbRounding').value = Math.abs(pbNum(a.rounding)) > 0.009 ? pbNum(a.rounding).toFixed(2) : '';
    pbEl('pbSpecial').value = pbNum(a.special_discount) > 0 ? pbNum(a.special_discount).toFixed(2) : '';
}
amePcSetMode('complete');
renderCart();
(function () {
    if (AME_EDIT_MEMO) {
        pbSetParty(AME_EDIT_MEMO.party_type === 'customer' ? 'customer' : 'supplier');
        pbLoadAdjustments(AME_EDIT_MEMO.adjustments);
        amePcLoadEditMemo(AME_EDIT_MEMO);
        return;
    }
    var params = new URLSearchParams(window.location.search);
    var draftId = parseInt(params.get('draft') || '0', 10);
    if (draftId > 0) {
        amePcLoadDraft(draftId);
        return;
    }
    maybeFillNextMemo();
})();
</script>
<script src="/assets/js/pos-title-live.js?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '5.71', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
(function () {
    function pad(n) { return String(n).padStart(2, '0'); }
    function paintClock() {
        var now = new Date();
        var clock = document.getElementById('ameDeskClock');
        var date = document.getElementById('ameDeskDate');
        if (clock) {
            var hours = now.getHours();
            var suffix = hours >= 12 ? 'PM' : 'AM';
            var hour12 = hours % 12 || 12;
            clock.textContent = pad(hour12) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()) + ' ' + suffix;
        }
        if (date) {
            date.textContent = pad(now.getDate()) + '/' + pad(now.getMonth() + 1) + '/' + now.getFullYear();
        }
    }
    paintClock();
    setInterval(paintClock, 1000);
})();
</script>
<?php include '../../partials/footer.php'; ?>
