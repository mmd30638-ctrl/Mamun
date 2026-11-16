<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
require_once '../../includes/account_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
require_post();
csrf_verify();

$action = (string)($_POST['action'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
$back = (string)($_POST['back'] ?? '/modules/bloan/dashboard.php');
if (!str_starts_with($back, '/modules/bloan/')) {
    $back = '/modules/bloan/dashboard.php';
}

function ame_bloan_fail(string $back, string $msg): void
{
    header('Location: ' . $back . (str_contains($back, '?') ? '&' : '?') . 'err=' . rawurlencode($msg));
    exit;
}

try {
    if ($action === 'provider') {
        $name = clean_input($_POST['name'] ?? '');
        $type = clean_input($_POST['provider_type'] ?? 'bank');
        if (!in_array($type, ['bank', 'ngo', 'financial', 'other'], true)) {
            $type = 'bank';
        }
        $branch = clean_input($_POST['branch'] ?? '');
        if ($name === '') {
            ame_bloan_fail('/modules/bloan/providers.php', ame_l('প্রতিষ্ঠানের নাম লাগবে।', 'Organisation name is required.'));
        }
        $dup = $db->prepare('SELECT id FROM bloan_providers WHERE provider_type=? AND name=? AND branch=? LIMIT 1');
        $dup->bind_param('sss', $type, $name, $branch);
        $dup->execute();
        if ($dup->get_result()->fetch_assoc()) {
            $dup->close();
            ame_bloan_fail('/modules/bloan/providers.php', ame_l('এই ঋণদাতা আগেই আছে।', 'This provider already exists.'));
        }
        $dup->close();
        $officer = clean_input($_POST['officer_name'] ?? '');
        $phone = clean_input($_POST['phone'] ?? '');
        $ref = clean_input($_POST['account_ref'] ?? '');
        $address = clean_input($_POST['address'] ?? '');
        $notes = clean_input($_POST['notes'] ?? '');
        $stmt = $db->prepare('INSERT INTO bloan_providers (provider_type, name, branch, officer_name, phone, account_ref, address, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('ssssssssi', $type, $name, $branch, $officer, $phone, $ref, $address, $notes, $userId);
        $stmt->execute();
        $stmt->close();
        log_activity('Business loan provider', 'bloan', $name);
        header('Location: /modules/bloan/providers.php?ok=1');
        exit;
    }

    if ($action === 'application') {
        $providerId = (int)($_POST['provider_id'] ?? 0);
        $amount = round((float)($_POST['amount'] ?? 0), 2);
        $date = clean_input($_POST['apply_date'] ?? date('Y-m-d'));
        $purpose = clean_input($_POST['purpose'] ?? '');
        if ($providerId <= 0 || $amount <= 0) {
            ame_bloan_fail('/modules/bloan/application.php', ame_l('ঋণদাতা ও টাকার পরিমাণ লাগবে।', 'Provider and amount are required.'));
        }
        $dup = $db->prepare('SELECT id FROM bloan_applications WHERE provider_id=? AND amount=? AND apply_date=? AND purpose=? AND status NOT IN ("rejected","closed") LIMIT 1');
        $dup->bind_param('idss', $providerId, $amount, $date, $purpose);
        $dup->execute();
        if ($dup->get_result()->fetch_assoc()) {
            $dup->close();
            ame_bloan_fail('/modules/bloan/application.php', ame_l('একই আবেদন আগেই আছে।', 'The same application already exists.'));
        }
        $dup->close();
        $appNo = ame_bloan_next_no($db, 'bloan_applications', 'app_no', 'BLA-');
        $tenure = (int)($_POST['tenure_months'] ?? 0);
        if ($tenure < 1) {
            ame_bloan_fail('/modules/bloan/application.php', ame_l('মেয়াদ (মাস) লাগবে।', 'Tenure in months is required.'));
        }
        $rate = round((float)($_POST['interest_rate'] ?? 0), 2);
        $interestType = ($_POST['interest_type'] ?? '') === 'reducing' ? 'reducing' : 'flat';
        $frequency = ($_POST['frequency'] ?? '') === 'weekly' ? 'weekly' : 'monthly';
        $fee = round((float)($_POST['processing_fee'] ?? 0), 2);
        $charge = round((float)($_POST['other_charge'] ?? 0), 2);
        $guarantee = clean_input($_POST['guarantee_note'] ?? '');
        $docs = clean_input($_POST['documents_note'] ?? '');
        $notes = clean_input($_POST['notes'] ?? '');
        $status = clean_input($_POST['status'] ?? 'applied');
        if (!in_array($status, ['draft', 'applied', 'review'], true)) {
            $status = 'applied';
        }
        $stmt = $db->prepare('INSERT INTO bloan_applications (app_no, provider_id, apply_date, purpose, amount, tenure_months, interest_rate, interest_type, frequency, processing_fee, other_charge, guarantee_note, documents_note, notes, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('sisididssddssssi', $appNo, $providerId, $date, $purpose, $amount, $tenure, $rate, $interestType, $frequency, $fee, $charge, $guarantee, $docs, $notes, $status, $userId);
        $stmt->execute();
        $id = (int)$db->insert_id;
        $stmt->close();
        log_activity('Business loan application', 'bloan', $appNo . ' amount ' . $amount);
        header('Location: /modules/bloan/application.php?id=' . $id . '&ok=1');
        exit;
    }

    if ($action === 'status') {
        if (strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') {
            ame_bloan_fail('/modules/bloan/application.php', ame_l('অনুমোদন শুধু অ্যাডমিন দিতে পারবেন।', 'Only an admin can approve.'));
        }
        $id = (int)($_POST['application_id'] ?? 0);
        $status = clean_input($_POST['status'] ?? '');
        if (!in_array($status, ['draft', 'applied', 'review', 'approved', 'rejected', 'closed'], true)) {
            ame_bloan_fail('/modules/bloan/application.php?id=' . $id, ame_l('স্ট্যাটাস সঠিক নয়।', 'That status is not valid.'));
        }
        $stmt = $db->prepare('UPDATE bloan_applications SET status=? WHERE id=? AND status NOT IN ("disbursed")');
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $stmt->close();
        log_activity('Business loan status', 'bloan', 'Application #' . $id . ' ' . $status);
        header('Location: /modules/bloan/application.php?id=' . $id . '&ok=1');
        exit;
    }

    if ($action === 'disburse') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $accountId = (int)($_POST['account_id'] ?? 0);
        $amount = round((float)($_POST['disbursed_amount'] ?? 0), 2);
        $date = clean_input($_POST['disbursed_on'] ?? date('Y-m-d'));
        $backTo = $appId > 0 ? '/modules/bloan/application.php?id=' . $appId : '/modules/bloan/disbursement.php';
        try {
            $received = ame_bloan_receive($db, $appId, $accountId, $amount, $date, $userId);
        } catch (InvalidArgumentException $e) {
            ame_bloan_fail($backTo, $e->getMessage());
        }
        log_activity('Business loan disbursed', 'bloan', $received['loan_no'] . ' ' . $amount);
        header('Location: /modules/bloan/loan.php?id=' . (int)$received['loan_id']);
        exit;
    }

    if ($action === 'pay' || $action === 'settle') {
        $loanId = (int)($_POST['loan_id'] ?? 0);
        $accountId = (int)($_POST['account_id'] ?? 0);
        $backLoan = '/modules/bloan/loan.php?id=' . $loanId;
        try {
            $paid = ame_bloan_collect(
                $db,
                $loanId,
                $accountId,
                (float)($_POST['principal_part'] ?? 0),
                (float)($_POST['interest_part'] ?? 0),
                (float)($_POST['charge_part'] ?? 0),
                $action === 'settle' ? 'settlement' : 'payment',
                $userId,
                clean_input($_POST['method'] ?? 'bank'),
                clean_input($_POST['reference_no'] ?? ''),
                clean_input($_POST['notes'] ?? '')
            );
        } catch (InvalidArgumentException $e) {
            ame_bloan_fail($backLoan, $e->getMessage());
        }
        log_activity('Business loan payment', 'bloan', $paid['loan_no'] . ' ' . $paid['kind'] . ' ' . $paid['total']);
        header('Location: /modules/bloan/receipt.php?id=' . (int)$paid['txn_id']);
        exit;
    }

    if ($action === 'facility_open') {
        $backFac = '/modules/bloan/facility.php';
        try {
            $facilityId = ame_bloan_facility_open(
                $db,
                (int)($_POST['provider_id'] ?? 0),
                clean_input($_POST['institution_name'] ?? ''),
                (float)($_POST['monthly_amount'] ?? 0),
                (int)($_POST['account_id'] ?? 0),
                $userId
            );
        } catch (InvalidArgumentException $e) {
            ame_bloan_fail($backFac, $e->getMessage());
        }
        $backFac = '/modules/bloan/facility.php?id=' . $facilityId;
        try {
            ame_bloan_facility_sync($db, $facilityId, $userId);
        } catch (Throwable $e) {
            ame_bloan_fail($backFac, $e->getMessage());
        }
        log_activity('Business loan facility', 'bloan', 'Facility #' . $facilityId);
        header('Location: ' . $backFac . '&ok=1');
        exit;
    }

    if ($action === 'facility_stop') {
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        ame_bloan_facility_stop($db, $facilityId);
        log_activity('Business loan facility stopped', 'bloan', 'Facility #' . $facilityId);
        header('Location: /modules/bloan/facility.php?id=' . $facilityId . '&ok=1');
        exit;
    }

    if ($action === 'facility_post') {
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $backFac = '/modules/bloan/facility.php?id=' . $facilityId;
        $yearMonth = trim((string)($_POST['year_month'] ?? ''));
        try {
            if ($yearMonth === '') {
                ame_bloan_facility_sync($db, $facilityId, $userId);
            } else {
                $posted = ame_bloan_facility_post_month($db, $facilityId, $yearMonth, $userId);
                if (($posted['reason'] ?? '') === 'stopped') {
                    ame_bloan_fail($backFac, ame_l('এই সুবিধা বন্ধ। পরের মাস আর কাটা হবে না।', 'This facility is stopped. Later months are not paid.'));
                }
                if (($posted['reason'] ?? '') === 'future') {
                    ame_bloan_fail($backFac, ame_l('আগামী মাস এখনো আসেনি।', 'That month has not started.'));
                }
            }
        } catch (InvalidArgumentException $e) {
            ame_bloan_fail($backFac, $e->getMessage());
        }
        log_activity('Business loan facility month', 'bloan', 'Facility #' . $facilityId . ' ' . $yearMonth);
        header('Location: ' . $backFac . '&ok=1');
        exit;
    }

    if ($action === 'document') {
        $title = clean_input($_POST['title'] ?? '');
        $appId = (int)($_POST['application_id'] ?? 0);
        $loanId = (int)($_POST['loan_id'] ?? 0);
        $notes = clean_input($_POST['notes'] ?? '');
        if ($title === '' || ($appId <= 0 && $loanId <= 0)) {
            ame_bloan_fail($back, ame_l('ডকুমেন্টের নাম লাগবে।', 'A document title is required.'));
        }
        $fileName = '';
        if (!empty($_FILES['file']['name']) && (int)($_FILES['file']['error'] ?? 1) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo((string)$_FILES['file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true) || (int)$_FILES['file']['size'] > 8 * 1024 * 1024) {
                ame_bloan_fail($back, ame_l('শুধু PDF বা ছবি, সর্বোচ্চ ৮ এমবি।', 'Only a PDF or image, up to 8 MB.'));
            }
            $dir = dirname(__DIR__, 2) . '/uploads/business-loans';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $fileName = 'bl_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file((string)$_FILES['file']['tmp_name'], $dir . '/' . $fileName)) {
                ame_bloan_fail($back, ame_l('ফাইল সেভ হয়নি।', 'The file was not saved.'));
            }
        }
        $stmt = $db->prepare('INSERT INTO bloan_documents (application_id, loan_id, title, file_name, notes, created_by) VALUES (?,?,?,?,?,?)');
        $appParam = $appId > 0 ? $appId : 0;
        $loanParam = $loanId > 0 ? $loanId : 0;
        $stmt->bind_param('iisssi', $appParam, $loanParam, $title, $fileName, $notes, $userId);
        $stmt->execute();
        $stmt->close();
        log_activity('Business loan document', 'bloan', $title);
        header('Location: ' . $back . (str_contains($back, '?') ? '&' : '?') . 'ok=1');
        exit;
    }

    ame_bloan_fail($back, ame_l('কাজটি চেনা যায়নি।', 'That action was not recognised.'));
} catch (Throwable $e) {
    try {
        $db->rollback();
    } catch (Throwable $ignore) {
    }
    error_log('bloan action failed: ' . $e->getMessage());
    ame_bloan_fail($back, $e->getMessage());
}
