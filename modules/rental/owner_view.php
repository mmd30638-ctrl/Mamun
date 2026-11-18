<?php
require_once __DIR__ . '/_boot.php';
$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    redirect('/modules/rental/owners.php');
}
redirect('/modules/rental/statement.php?id=' . $id);
