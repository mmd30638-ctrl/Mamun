<?php
require_once __DIR__ . '/_boot.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
csrf_verify();

$do = trim((string)($_POST['do'] ?? ''));
$cid = (int)($_POST['contract_id'] ?? 0);

function hire_go(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function hire_fail(string $msg, int $cid = 0): void
{
    $back = trim((string) ($_POST['fail_back'] ?? ''));
    if ($cid > 0) {
        $target = 'contract.php?id=' . $cid . '&err=' . rawurlencode($msg);
    } elseif ($back === 'create') {
        $target = 'create.php?err=' . rawurlencode($msg);
    } elseif ($back === 'owners') {
        $oidBack = (int) ($_POST['owner_id'] ?? 0);
        $target = 'owners.php?err=' . rawurlencode($msg) . ($oidBack > 0 ? ('&edit=' . $oidBack) : '');
    } else {
        $target = 'index.php?err=' . rawurlencode($msg);
    }
    header('Location: ' . $target);
    exit;
}

try {
    switch ($do) {
        case 'create':
            hire_require($db, 'create');
            $db->begin_transaction();
            if (function_exists('hire_term_normalize')) {
                $_POST = array_merge($_POST, hire_term_normalize($_POST));
            }
            $newId = hire_create_contract($db, $_POST);
            if (function_exists('hire_term_save_columns')) {
                hire_term_save_columns($db, $newId, (int)($_POST['term_months'] ?? 0), (int)($_POST['term_days'] ?? 0));
            }
            $db->commit();
            try {
                $fresh = hire_contract($db, $newId);
                if (is_array($fresh)) {
                    hire_accrue_contract($db, $fresh);
                }
            } catch (Throwable $e) {
                error_log('hire create catch-up: ' . $e->getMessage());
            }
            hire_go('contract.php?id=' . $newId . '&ok=created');
            break;

        case 'term':
            hire_require($db, 'edit');
            $db->begin_transaction();
            hire_save_term($db, $cid, $_POST, trim((string)($_POST['reason'] ?? '')));
            $db->commit();
            hire_go('contract.php?id=' . $cid . '&ok=saved');
            break;

        case 'pay':
            hire_require($db, 'edit');
            $db->begin_transaction();
            $pid = hire_pay(
                $db,
                $cid,
                (float)($_POST['amount'] ?? 0),
                (int)($_POST['account_id'] ?? 0),
                strtoupper(trim((string)($_POST['payment_method'] ?? 'CASH'))),
                trim((string)($_POST['pay_date'] ?? date('Y-m-d'))),
                trim((string)($_POST['reference_no'] ?? '')),
                trim((string)($_POST['note'] ?? ''))
            );
            $db->commit();
            if (trim((string) ($_POST['return'] ?? '')) === 'desk') {
                $desk = 'index.php?ok=paid';
                $backCust = (int) ($_POST['desk_customer_id'] ?? 0);
                if ($backCust > 0) {
                    $desk .= '&customer_id=' . $backCust;
                }
                hire_go($desk);
            }
            hire_go('print.php?id=' . $cid . '&type=payment&pay=' . $pid . '&ok=paid');
            break;

        case 'use_deposit':
            hire_require($db, 'deposit');
            $db->begin_transaction();
            hire_use_deposit($db, $cid, (float)($_POST['amount'] ?? 0), trim((string)($_POST['reason'] ?? '')));
            $db->commit();
            hire_go('contract.php?id=' . $cid . '&ok=used');
            break;

        case 'add_deposit':
            hire_require($db, 'deposit');
            $db->begin_transaction();
            hire_add_deposit(
                $db,
                $cid,
                (float)($_POST['amount'] ?? 0),
                (int)($_POST['account_id'] ?? 0),
                strtoupper(trim((string)($_POST['payment_method'] ?? 'CASH'))),
                trim((string)($_POST['reason'] ?? ''))
            );
            $db->commit();
            hire_go('contract.php?id=' . $cid . '&ok=saved');
            break;

        case 'refund':
            hire_require($db, 'refund');
            $db->begin_transaction();
            hire_refund_deposit(
                $db,
                $cid,
                (float)($_POST['amount'] ?? 0),
                (int)($_POST['account_id'] ?? 0),
                strtoupper(trim((string)($_POST['payment_method'] ?? 'CASH'))),
                trim((string)($_POST['reason'] ?? ''))
            );
            $db->commit();
            hire_go('print.php?id=' . $cid . '&type=refund&ok=refund');
            break;

        case 'toggle_usage':
            hire_require($db, 'deposit');
            hire_set_advance_usage($db, $cid, (int)($_POST['advance_usage'] ?? 0), trim((string)($_POST['reason'] ?? 'toggle')));
            hire_go('contract.php?id=' . $cid . '&ok=saved');
            break;

        case 'rate':
            hire_require($db, 'rate');
            hire_change_rate($db, $cid, (float)($_POST['rent_rate'] ?? 0), trim((string)($_POST['effective_date'] ?? date('Y-m-d'))), trim((string)($_POST['reason'] ?? '')));
            hire_go('contract.php?id=' . $cid . '&ok=saved');
            break;

        case 'product':
            hire_require($db, 'product');
            $db->begin_transaction();
            hire_change_product($db, $cid, $_POST, trim((string)($_POST['reason'] ?? '')));
            $db->commit();
            hire_go('contract.php?id=' . $cid . '&ok=saved');
            break;

        case 'dates':
            hire_require($db, 'edit');
            hire_change_dates($db, $cid, $_POST, trim((string)($_POST['reason'] ?? '')));
            hire_go('contract.php?id=' . $cid . '&ok=saved');
            break;

        case 'deactivate':
            hire_require($db, 'edit');
            $db->begin_transaction();
            hire_deactivate($db, $cid, trim((string) ($_POST['reason'] ?? 'ইনঅ্যাকটিভ')));
            $db->commit();
            $back = trim((string) ($_POST['return'] ?? ''));
            if ($back === 'desk') {
                $desk = 'index.php?ok=deactivated&view=inactive';
                $backCust = (int) ($_POST['desk_customer_id'] ?? 0);
                if ($backCust > 0) {
                    $desk .= '&customer_id=' . $backCust;
                }
                hire_go($desk);
            }
            if ($back === 'list') {
                hire_go('show.php?ok=deactivated');
            }
            hire_go('contract.php?id=' . $cid . '&ok=deactivated');
            break;

        case 'activate':
            hire_require($db, 'edit');
            $db->begin_transaction();
            hire_activate($db, $cid, trim((string) ($_POST['reason'] ?? 'আবার চালু')));
            $db->commit();
            $back = trim((string) ($_POST['return'] ?? ''));
            if ($back === 'desk') {
                $desk = 'index.php?ok=activated';
                $backCust = (int) ($_POST['desk_customer_id'] ?? 0);
                if ($backCust > 0) {
                    $desk .= '&customer_id=' . $backCust;
                }
                hire_go($desk);
            }
            if ($back === 'list') {
                hire_go('show.php?ok=activated');
            }
            hire_go('contract.php?id=' . $cid . '&ok=activated');
            break;

        case 'pause':
            hire_require($db, 'edit');
            hire_pause($db, $cid, trim((string)($_POST['pause_start'] ?? date('Y-m-d'))), trim((string)($_POST['reason'] ?? '')));
            hire_go('contract.php?id=' . $cid . '&ok=paused');
            break;

        case 'resume':
            hire_require($db, 'edit');
            hire_resume($db, $cid, trim((string)($_POST['resume_date'] ?? date('Y-m-d'))), trim((string)($_POST['reason'] ?? '')));
            hire_go('contract.php?id=' . $cid . '&ok=resumed');
            break;

        case 'return':
            hire_require($db, 'edit');
            $db->begin_transaction();
            hire_return($db, $cid, $_POST);
            $db->commit();
            hire_go('print.php?id=' . $cid . '&type=return&ok=returned');
            break;

        case 'complete':
            hire_require($db, 'edit');
            hire_complete($db, $cid, trim((string)($_POST['reason'] ?? '')));
            hire_go('contract.php?id=' . $cid . '&ok=saved');
            break;

        case 'cancel':
            hire_require($db, 'cancel');
            $db->begin_transaction();
            hire_cancel($db, $cid, trim((string)($_POST['reason'] ?? '')));
            $db->commit();
            if (trim((string) ($_POST['return'] ?? '')) === 'desk') {
                $desk = 'index.php?ok=cancelled';
                $backCust = (int) ($_POST['desk_customer_id'] ?? 0);
                if ($backCust > 0) {
                    $desk .= '&customer_id=' . $backCust;
                }
                hire_go($desk);
            }
            hire_go('contract.php?id=' . $cid . '&ok=cancelled');
            break;

        case 'delete':
            hire_require($db, 'cancel');
            $db->begin_transaction();
            $deleted_no = hire_delete_contract($db, $cid, trim((string)($_POST['reason'] ?? '')));
            $db->commit();
            hire_go('index.php?ok=deleted&no=' . rawurlencode($deleted_no));
            break;

        case 'renew':
            hire_require($db, 'create');
            $db->begin_transaction();
            $nid = hire_renew($db, $cid, $_POST);
            $db->commit();
            hire_go('contract.php?id=' . $nid . '&ok=created');
            break;

        case 'adjust':
            hire_require($db, 'adjust');
            $db->begin_transaction();
            hire_adjustment($db, $cid, (float)($_POST['debit'] ?? 0), (float)($_POST['credit'] ?? 0), trim((string)($_POST['reason'] ?? '')));
            $db->commit();
            hire_go('contract.php?id=' . $cid . '&ok=saved');
            break;

        case 'owner_save':
            $oidIn = (int) ($_POST['owner_id'] ?? 0);
            hire_require($db, $oidIn > 0 ? 'edit' : 'create');
            $db->begin_transaction();
            if (!function_exists('hire_source_upsert_owner')) {
                throw new RuntimeException('মালিক হিসাব লোড হয়নি।');
            }
            $oid = hire_source_upsert_owner($db, $_POST);
            if ($oid <= 0) {
                throw new RuntimeException('মালিকের নাম দিন।');
            }
            $db->commit();
            hire_go('owners.php?ok=saved');
            break;

        case 'owner_pay':
            hire_require($db, 'edit');
            $db->begin_transaction();
            if (!function_exists('hire_source_owner_pay')) {
                throw new RuntimeException('মালিক পরিশোধ লোড হয়নি।');
            }
            hire_source_owner_pay(
                $db,
                (int) ($_POST['owner_id'] ?? 0),
                (float) ($_POST['amount'] ?? 0),
                strtoupper(trim((string) ($_POST['payment_method'] ?? 'CASH'))),
                trim((string) ($_POST['pay_date'] ?? date('Y-m-d'))),
                trim((string) ($_POST['reference_no'] ?? '')),
                trim((string) ($_POST['note'] ?? ''))
            );
            $db->commit();
            hire_go('owners.php?ok=owner_paid');
            break;

        case 'settings':
            hire_require($db, 'adjust');
            hire_set_setting($db, 'billing_method', in_array($_POST['billing_method'] ?? '', ['calendar_month', 'billing_day'], true) ? $_POST['billing_method'] : 'calendar_month');
            hire_set_setting($db, 'default_billing_day', (string)max(1, min(28, (int)($_POST['default_billing_day'] ?? 1))));
            hire_set_setting($db, 'late_fee_type', in_array($_POST['late_fee_type'] ?? '', ['none', 'per_day', 'fixed', 'percent'], true) ? $_POST['late_fee_type'] : 'none');
            hire_set_setting($db, 'late_fee_value', (string)round((float)($_POST['late_fee_value'] ?? 0), 2));
            hire_go('settings.php?ok=saved');
            break;

        default:
            hire_fail('অজানা কাজ।', $cid);
    }
} catch (Throwable $e) {
    if ($db instanceof mysqli) {
        @$db->rollback();
    }
    error_log('hire action ' . $do . ': ' . $e->getMessage());
    hire_fail($e->getMessage(), $cid);
}
