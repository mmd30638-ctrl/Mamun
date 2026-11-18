<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/rental_schema.php';
require_once __DIR__ . '/../../includes/rental_functions.php';

if (!isset($db) || !($db instanceof mysqli)) {
    die('Database unavailable');
}

require_permission($db, 'm-rental');
ensure_rental_schema($db);
