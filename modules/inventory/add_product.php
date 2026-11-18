<?php
require_once '../../includes/config.php';
require_once '../../includes/inventory_functions.php';
require_once '../../includes/lang_helper.php';

try {
    ensure_inventory_schema($db);
} catch (Throwable $e) {
    error_log('add_product schema: ' . $e->getMessage());
}

$is_edit = false;
$p = [];

include __DIR__ . '/add_product_boot.php';
include '../../partials/header.php';
include __DIR__ . '/add_product_style.php';
include __DIR__ . '/add_product_form.php';
include __DIR__ . '/add_product_script.php';
include '../../partials/footer.php';
