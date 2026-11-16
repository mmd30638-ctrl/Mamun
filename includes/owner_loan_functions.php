<?php
/**
 * দোকান মালিক ও ঋণ ব্যবস্থাপনা (Shop Owner & Loan Management) — কোর ফাংশন।
 *
 * এই ফাইলটি modules/loans/owners/ এর সব পেজ থেকে require হয়। এটি
 * বিদ্যমান customer-lending (loans/loan_applications) বা bank_loans
 * সিস্টেম স্পর্শ করে না — এটি সম্পূর্ণ নতুন, সমান্তরাল সাব-সিস্টেম, যা
 * shop_owners / owner_loans / owner_savings_* / owner_transaction_ledger
 * টেবিল সেট নিয়ে কাজ করে।
 *
 * ⭐ কোর অ্যাকাউন্টিং রুল (স্পষ্টভাবে ডকুমেন্টেড কনভেনশন):
 * -----------------------------------------------------------------
 * ১) owner_loans.outstanding_amount শুধুমাত্র payment-এর
 *    "principal_paid" অংশ দিয়ে কমে — interest_paid বা penalty_charge
 *    দিয়ে কখনো কমে না (স্পেকের মূল নিয়ম)।
 * ২) সঞ্চয় (owner_savings_accounts.current_balance) সম্পূর্ণ আলাদা —
 *    ঋণের হিসাবের সাথে কোনোভাবেই মিশবে না।
 * ৩) owner_transaction_ledger একটি *প্রদর্শনী/অডিট* লেজার — এখানকার
 *    running "balance" কলামটি owner-ভিত্তিক ক্রমপুঞ্জিত
 *    (পূর্বের balance + credit - debit), যেখানে:
 *      - loan_disbursement      → credit  (দোকান থেকে মালিকের কাছে টাকা গেল, ঋণ দায় বাড়ল)
 *      - loan_payment           → debit   (মালিক কিস্তি দিলেন, ঋণ দায় কমল)
 *      - savings_deposit        → credit  (মালিক সঞ্চয় জমা দিলেন, দোকানের কাছে তার জমা বাড়ল)
 *      - savings_withdrawal     → debit   (মালিক সঞ্চয় তুললেন, জমা কমল)
 *    এই "balance" কলামটি শুধু ক্রমান্বয়ে লেনদেনের ইতিহাস/অডিট-ট্রেইলের
 *    জন্য একটি নির্দেশক সংখ্যা — সত্যিকারের বকেয়া/জমার হিসাবের জন্য
 *    কর্তৃত্বপূর্ণ (authoritative) সংখ্যা সবসময় owner_loans.outstanding_amount
 *    এবং owner_savings_accounts.current_balance থেকেই নিতে হবে।
 * ৪) কালেকশন স্ক্রিনে একই ফিজিক্যাল ক্যাশ রিসিপ্টে ঋণ কিস্তি + সঞ্চয়
 *    জমা একসাথে নেওয়া হলেও, এই দুটো সবসময় দুটো আলাদা ঋণ/সঞ্চয় রেকর্ড
 *    ও দুটো আলাদা ledger row হিসেবে সংরক্ষিত হয় — কখনো মেশানো হয় না।
 *    দোকানের accounts.balance-এ, বাস্তবে একটাই ক্যাশ রিসিপ্ট হওয়ায়,
 *    account_credit()/account_debit() মোট (grand total) পরিমাণ দিয়ে
 *    *একবার* কল করা হয় (process_payment.php দেখুন)।
 */

if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php';
}

/** নতুন পারমিশন কী দিয়ে অ্যাক্সেস গার্ড — admin_only() নয়, has_permission() ব্যবহার করা হয় */
function ame_oloan_is_admin(): bool
{
    return isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin';
}

/** যেকোনো owners/ পেজের শুরুতে কল করুন — অনুমতি না থাকলে বার্তা দেখিয়ে থেমে যায় */
function ame_oloan_require_access(mysqli $db): void
{
    if (ame_oloan_is_admin()) {
        return;
    }
    if (function_exists('has_permission') && has_permission($db, 'm-oloans')) {
        return;
    }
    http_response_code(403);
    $msg = function_exists('ame_l')
        ? ame_l('❌ এই পেজ দেখার অনুমতি আপনার নেই।', '❌ You do not have permission to view this page.')
        : '❌ এই পেজ দেখার অনুমতি আপনার নেই।';
    die('<div style="padding:40px;text-align:center;font-family:sans-serif;">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . ' <br><a href="/index.php">⬅️ হোমে ফিরুন</a></div>');
}

/** সক্রিয় প্রকল্প অ্যাকাউন্ট (ক্যাশ, ব্যাংক, মোবাইলসহ) — পেমেন্ট/ডিসবার্সমেন্ট/উইথড্র ফর্মের ড্রপডাউনে */
function ame_oloan_shop_accounts(mysqli $db): array
{
    if (!function_exists('ame_active_accounts')) {
        require_once __DIR__ . '/account_functions.php';
    }
    return ame_active_accounts($db);
}

/** সক্রিয়/সব মালিকের অপশন লিস্ট */
function ame_oloan_owner_options(mysqli $db, bool $active_only = false): array
{
    $sql = "SELECT id, owner_name, shop_name, mobile, status FROM shop_owners";
    if ($active_only) {
        $sql .= " WHERE status = 1";
    }
    $sql .= " ORDER BY owner_name ASC";
    $res = @$db->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/** সক্রিয় ব্যাংক মাস্টার-লিস্ট */
function ame_oloan_bank_options(mysqli $db, bool $active_only = true): array
{
    $sql = "SELECT id, bank_name, branch_name FROM banks";
    if ($active_only) {
        $sql .= " WHERE status = 1";
    }
    $sql .= " ORDER BY bank_name ASC";
    $res = @$db->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function ame_oloan_owner_fetch(mysqli $db, int $owner_id): ?array
{
    $stmt = $db->prepare("SELECT * FROM shop_owners WHERE id = ?");
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ame_oloan_loan_fetch(mysqli $db, int $loan_id): ?array
{
    $stmt = $db->prepare("SELECT ol.*, so.owner_name, so.shop_name, so.mobile, b.bank_name
        FROM owner_loans ol
        LEFT JOIN shop_owners so ON so.id = ol.owner_id
        LEFT JOIN banks b ON b.id = ol.bank_id
        WHERE ol.id = ?");
    $stmt->bind_param('i', $loan_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * ফ্ল্যাট-রেট ইন্টারেস্ট হিসাব — includes/loan_schema.php এর
 * generate_flat_repayment_schedule() থেকে ম্যাথ অ্যাডাপ্ট করা হয়েছে।
 * সরলীকরণ (comment, spec violation নয়): installment_frequency বর্তমানে
 * শুধু 'monthly' পূর্ণভাবে সাপোর্ট করে — অন্য যেকোনো ভ্যালু দিলেও
 * প্রতি কিস্তিতে +1 মাস ধরে হিসাব হয় (ভবিষ্যতে সাপ্তাহিক/পাক্ষিক
 * যোগ করা যাবে)।
 */
function ame_owner_loan_calc(float $principal, float $annual_rate_percent, int $installment_count): array
{
    $installment_count = max(0, $installment_count);
    if ($installment_count <= 0 || $principal <= 0) {
        return [
            'total_interest' => 0.0,
            'total_payable' => round($principal, 2),
            'installment_amount' => 0.0,
            'principal_per' => 0.0,
            'interest_per' => 0.0,
        ];
    }
    // মাসিক কিস্তি ধরে term_months == installment_count (সরলীকরণ)
    $term_months = $installment_count;
    $total_interest = round($principal * ($annual_rate_percent / 100) * ($term_months / 12), 2);
    $total_payable = round($principal + $total_interest, 2);
    $installment_amount = round($total_payable / $installment_count, 2);
    $principal_per = round($principal / $installment_count, 2);
    $interest_per = round($total_interest / $installment_count, 2);

    return [
        'total_interest' => $total_interest,
        'total_payable' => $total_payable,
        'installment_amount' => $installment_amount,
        'principal_per' => $principal_per,
        'interest_per' => $interest_per,
    ];
}

/** কিস্তির শিডিউল তৈরি করে owner_loan_installments-এ ইনসার্ট করে */
function ame_owner_loan_generate_schedule(mysqli $db, int $loan_id, float $principal, float $annual_rate_percent, int $installment_count, string $installment_frequency, string $start_date): void
{
    if ($installment_count <= 0) {
        return;
    }
    $calc = ame_owner_loan_calc($principal, $annual_rate_percent, $installment_count);
    $principal_per = $calc['principal_per'];
    $interest_per = $calc['interest_per'];
    $installment_amount = $calc['installment_amount'];
    $total_payable = $calc['total_payable'];

    // পুরনো শিডিউল থাকলে (রি-জেনারেট) মুছে ফেলা হয়
    $del = $db->prepare("DELETE FROM owner_loan_installments WHERE loan_id = ?");
    $del->bind_param('i', $loan_id);
    $del->execute();
    $del->close();

    $stmt = $db->prepare("INSERT INTO owner_loan_installments
        (loan_id, installment_no, due_date, principal_amount, interest_amount, other_charges, total_installment, paid_amount, remaining_amount, status)
        VALUES (?, ?, ?, ?, ?, 0, ?, 0, ?, 'upcoming')");

    $due_date = new DateTime($start_date !== '' ? $start_date : date('Y-m-d'));
    for ($i = 1; $i <= $installment_count; $i++) {
        // সরলীকরণ: ফ্রিকোয়েন্সি যা-ই হোক, +1 মাস করে বাড়ানো হচ্ছে (উপরের কমেন্ট দেখুন)
        $due_date->modify('+1 month');
        $due_date_str = $due_date->format('Y-m-d');

        $p = $principal_per;
        $amt = $installment_amount;
        if ($i === $installment_count) {
            $p = round($principal - ($principal_per * ($installment_count - 1)), 2);
            $amt = round($total_payable - ($installment_amount * ($installment_count - 1)), 2);
        }
        $remaining = $amt;
        $stmt->bind_param('iisdddd', $loan_id, $i, $due_date_str, $p, $interest_per, $amt, $remaining);
        $stmt->execute();
    }
    $stmt->close();
}

/**
 * কিস্তির স্ট্যাটাস রিফ্রেশ করে — কোনো cron ছাড়াই dashboard/list/loan_view-এর
 * শুরুতে কল করা হয়, যাতে overdue/upcoming/partial/paid সবসময় আপ-টু-ডেট থাকে।
 * $loan_id দিলে শুধু সেই লোনের কিস্তি, না দিলে সব সক্রিয়/অনুমোদিত লোনের কিস্তি আপডেট হয়।
 */
function ame_owner_loan_recalc_installment_statuses(mysqli $db, ?int $loan_id = null): void
{
    $sql = "UPDATE owner_loan_installments oi
        JOIN owner_loans ol ON ol.id = oi.loan_id
        SET oi.status = CASE
            WHEN oi.remaining_amount <= 0.005 THEN 'paid'
            WHEN oi.paid_amount > 0 AND oi.remaining_amount > 0.005 THEN 'partial'
            WHEN oi.due_date < CURDATE() AND oi.remaining_amount > 0.005 THEN 'overdue'
            ELSE 'upcoming'
        END
        WHERE ol.status IN ('approved','active','overdue')";
    if ($loan_id !== null) {
        $sql .= " AND ol.id = " . (int)$loan_id;
    }
    @$db->query($sql);
}

/** মালিকের সঞ্চয় হিসাব — না থাকলে opening_balance=0 দিয়ে নতুন খুলে দেয় */
function ame_oloan_find_or_create_savings_account(mysqli $db, int $owner_id): array
{
    $stmt = $db->prepare("SELECT * FROM owner_savings_accounts WHERE owner_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        return $row;
    }
    $today = date('Y-m-d');
    $ins = $db->prepare("INSERT INTO owner_savings_accounts (owner_id, account_opening_date, opening_balance, current_balance, status) VALUES (?, ?, 0, 0, 'active')");
    $ins->bind_param('is', $owner_id, $today);
    $ins->execute();
    $new_id = $db->insert_id;
    $ins->close();
    return [
        'id' => $new_id,
        'owner_id' => $owner_id,
        'account_opening_date' => $today,
        'opening_balance' => 0.0,
        'current_balance' => 0.0,
        'status' => 'active',
    ];
}

/** owner_transaction_ledger-এ একটি সারি যোগ করে, ক্রমপুঞ্জিত balance হিসাব করে — উপরের কনভেনশন দেখুন */
function ame_owner_ledger_add(mysqli $db, int $owner_id, ?int $loan_id, ?int $savings_account_id, string $txn_date, string $txn_type, string $description, float $debit, float $credit, ?string $reference, int $created_by): int
{
    $prevStmt = $db->prepare("SELECT balance FROM owner_transaction_ledger WHERE owner_id = ? ORDER BY id DESC LIMIT 1");
    $prevStmt->bind_param('i', $owner_id);
    $prevStmt->execute();
    $prevRow = $prevStmt->get_result()->fetch_assoc();
    $prevStmt->close();
    $prev_balance = $prevRow ? (float)$prevRow['balance'] : 0.0;
    $new_balance = round($prev_balance + $credit - $debit, 2);

    $stmt = $db->prepare("INSERT INTO owner_transaction_ledger
        (owner_id, loan_id, savings_account_id, txn_date, txn_type, description, debit, credit, balance, reference, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    // টাইপ: i(owner) i(loan) i(savings) s(date) s(type) s(desc) d(debit) d(credit) d(balance) s(ref) i(created_by) = "iiisssdddsi" (১১টি)
    $stmt->bind_param('iiisssdddsi', $owner_id, $loan_id, $savings_account_id, $txn_date, $txn_type, $description, $debit, $credit, $new_balance, $reference, $created_by);
    $stmt->execute();
    $id = $db->insert_id;
    $stmt->close();
    return $id;
}

/** স্পেক সেকশন ১২ — মালিকের আর্থিক সারসংক্ষেপ */
function ame_owner_financial_summary(mysqli $db, int $owner_id): array
{
    $out = [
        'total_loan_approved' => 0.0,
        'total_loan_disbursed' => 0.0,
        'total_principal_paid' => 0.0,
        'total_interest_paid' => 0.0,
        'total_collected' => 0.0,
        'total_outstanding' => 0.0,
        'savings_deposited' => 0.0,
        'savings_withdrawn' => 0.0,
        'savings_balance' => 0.0,
        'next_installment_amount' => null,
        'next_installment_due' => null,
        'overdue_amount' => 0.0,
        'loan_count' => 0,
        'active_loan_count' => 0,
    ];

    $q = function (string $sql, string $types = '', array $params = []) use ($db) {
        if ($types === '') {
            $r = @$db->query($sql);
            return $r ? $r->fetch_assoc() : null;
        }
        $stmt = $db->prepare($sql);
        if (!$stmt) { return null; }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $r;
    };

    $row = $q("SELECT COUNT(*) c, COALESCE(SUM(approved_amount),0) a, COALESCE(SUM(outstanding_amount),0) o,
        SUM(CASE WHEN status IN ('approved','active','overdue') THEN 1 ELSE 0 END) ac
        FROM owner_loans WHERE owner_id = ?", 'i', [$owner_id]);
    if ($row) {
        $out['loan_count'] = (int)$row['c'];
        $out['total_loan_approved'] = (float)$row['a'];
        $out['total_outstanding'] = (float)$row['o'];
        $out['active_loan_count'] = (int)$row['ac'];
    }

    $row = $q("SELECT COALESCE(SUM(disbursed_amount),0) v FROM owner_loan_disbursements WHERE owner_id = ?", 'i', [$owner_id]);
    if ($row) { $out['total_loan_disbursed'] = (float)$row['v']; }

    $row = $q("SELECT COALESCE(SUM(principal_paid),0) p, COALESCE(SUM(interest_paid),0) i, COALESCE(SUM(total_payment),0) t
        FROM owner_loan_payments WHERE owner_id = ?", 'i', [$owner_id]);
    if ($row) {
        $out['total_principal_paid'] = (float)$row['p'];
        $out['total_interest_paid'] = (float)$row['i'];
        $out['total_collected'] = (float)$row['t'];
    }

    $row = $q("SELECT COALESCE(SUM(amount),0) v FROM owner_savings_transactions WHERE owner_id = ? AND txn_type = 'deposit'", 'i', [$owner_id]);
    if ($row) { $out['savings_deposited'] = (float)$row['v']; }

    $row = $q("SELECT COALESCE(SUM(amount),0) v FROM owner_savings_withdrawals WHERE owner_id = ?", 'i', [$owner_id]);
    if ($row) { $out['savings_withdrawn'] = (float)$row['v']; }

    $row = $q("SELECT COALESCE(SUM(current_balance),0) v FROM owner_savings_accounts WHERE owner_id = ?", 'i', [$owner_id]);
    if ($row) { $out['savings_balance'] = (float)$row['v']; }

    $row = $q("SELECT oi.due_date d, (oi.total_installment - oi.paid_amount) amt
        FROM owner_loan_installments oi JOIN owner_loans ol ON ol.id = oi.loan_id
        WHERE ol.owner_id = ? AND oi.status IN ('upcoming','partial')
        ORDER BY oi.due_date ASC LIMIT 1", 'i', [$owner_id]);
    if ($row) {
        $out['next_installment_due'] = $row['d'];
        $out['next_installment_amount'] = (float)$row['amt'];
    }

    $row = $q("SELECT COALESCE(SUM(oi.remaining_amount),0) v
        FROM owner_loan_installments oi JOIN owner_loans ol ON ol.id = oi.loan_id
        WHERE ol.owner_id = ? AND oi.status = 'overdue'", 'i', [$owner_id]);
    if ($row) { $out['overdue_amount'] = (float)$row['v']; }

    return $out;
}

/** ডকুমেন্ট আপলোডের জন্য কমন ডিরেক্টরি */
function ame_oloan_upload_dir(): string
{
    return ame_webroot_path() . '/uploads/owners';
}
