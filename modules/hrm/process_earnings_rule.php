<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
ame_earnings_boot($db, 'rules');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: earnings_rules.php');
    exit();
}
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    header('Location: earnings_rules.php?err=csrf');
    exit();
}
$id = (int)($_POST['employee_id'] ?? 0);
if ($id <= 0) {
    header('Location: earnings_rules.php?err=worker');
    exit();
}
ame_earnings_save_rule($db, $id, ame_earnings_rule_from_post($_POST));
header('Location: earnings_rules.php?id=' . $id . '&ok=rule');
exit();
