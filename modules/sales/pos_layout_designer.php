<?php
/**
 * Keep the old POS designer URL working — it now lives in Settings.
 */
$qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
$to = '/modules/settings/layout_designer.php';
if ($qs !== '') {
    $to .= '?' . $qs;
}
header('Location: ' . $to, true, 302);
exit;
