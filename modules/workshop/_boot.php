<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/account_functions.php';
require_once __DIR__ . '/../../includes/workshop_functions.php';
require_once __DIR__ . '/../../includes/inventory_functions.php';
ensure_workshop_schema($db);
ensure_inventory_schema($db);
require_permission($db, 'm-workshop');
