<?php
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../../../includes/helpers.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function hire_agr_api_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_post();
    csrf_verify();
    hire_require($db, 'edit');
    $do = (string)($_POST['do'] ?? $_GET['do'] ?? '');
    $id = (int)($_POST['agreement_id'] ?? $_GET['id'] ?? 0);
    $contractId = (int)($_POST['contract_id'] ?? 0);

    if ($do === 'save') {
        $contract = $contractId > 0 ? hire_contract($db, $contractId) : null;
        if (!$contract && $id > 0) {
            $existing = hire_agr_get($db, $id);
            if ($existing) {
                $contract = hire_contract($db, (int)$existing['contract_id']);
            }
        }
        if (!$contract) {
            throw new RuntimeException('ভাড়ার চুক্তি পাওয়া যায়নি।');
        }
        $saved = hire_agr_save($db, $contract, $_POST, $id);
        $row = hire_agr_get($db, $saved);
        hire_agr_api_out([
            'ok' => true,
            'id' => $saved,
            'agreement_no' => (string)($row['agreement_no'] ?? ''),
            'guarantor_code' => (string)($row['guarantor_code'] ?? ''),
        ]);
    }

    if ($do === 'sign') {
        $row = hire_agr_get($db, $id);
        if (!$row) {
            throw new RuntimeException('আগে চুক্তিনামা সংরক্ষণ করুন।');
        }
        $role = (string)($_POST['role'] ?? '');
        hire_agr_store_sign($db, $row, $role, (string)($_POST['png'] ?? ''));
        $fresh = hire_agr_get($db, $id);
        $both = $fresh && !empty($fresh['customer_signed_at']) && !empty($fresh['guarantor_signed_at']);
        hire_agr_api_out([
            'ok' => true,
            'id' => $id,
            'both' => $both,
            'customer_signed_at' => (string)($fresh['customer_signed_at'] ?? ''),
            'guarantor_signed_at' => (string)($fresh['guarantor_signed_at'] ?? ''),
        ]);
    }

    if ($do === 'pdf') {
        $row = hire_agr_get($db, $id);
        if (!$row) {
            throw new RuntimeException('চুক্তিনামা পাওয়া যায়নি।');
        }
        $bin = (string)file_get_contents('php://input');
        if ($bin === '' && isset($_FILES['pdf']['tmp_name']) && is_uploaded_file($_FILES['pdf']['tmp_name'])) {
            $bin = (string)file_get_contents($_FILES['pdf']['tmp_name']);
        }
        $ref = hire_agr_store_pdf($db, $row, $bin);
        hire_agr_api_out(['ok' => true, 'id' => $id, 'verify_ref' => $ref]);
    }

    if ($do === 'version') {
        $row = hire_agr_get($db, $id);
        if (!$row) {
            throw new RuntimeException('চুক্তিনামা পাওয়া যায়নি।');
        }
        $newId = hire_agr_new_version($db, $row);
        hire_agr_api_out(['ok' => true, 'id' => $newId]);
    }

    hire_agr_api_out(['ok' => false, 'error' => 'কাজটি চেনা যায়নি।'], 400);
} catch (Throwable $e) {
    hire_agr_api_out(['ok' => false, 'error' => $e->getMessage()], 400);
}
