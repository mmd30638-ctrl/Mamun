<?php
if (!function_exists('ame_account_select')) {
    require_once __DIR__ . '/../../includes/account_functions.php';
}
$ame_fin_direction = $ame_fin_direction ?? 'receive';
echo ame_account_select($db, [
    'name' => 'account_id',
    'required' => true,
    'direction' => $ame_fin_direction,
    'placeholder' => '-- নির্বাচন করুন --',
]);
