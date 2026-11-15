<?php
require_once '../../includes/config.php';
require_once '../../includes/supplier_functions.php';
require_once '../../includes/party_purge.php';

require_post();
csrf_verify();

if (isset($db) && $db instanceof mysqli && function_exists('ame_super_admin_boot')) {
    ame_super_admin_boot($db);
}

ame_party_purge_handle($db, 'supplier');
