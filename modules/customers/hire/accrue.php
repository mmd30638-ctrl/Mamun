<?php
require_once __DIR__ . '/_boot.php';
hire_require($db, 'view');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
csrf_verify();
$n = hire_accrue_all($db, true);
header('Location: index.php?ok=' . rawurlencode('accrued') . '&n=' . $n);
exit;
