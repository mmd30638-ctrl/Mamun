<?php
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
$f = __DIR__ . '/VERSION.txt';
echo is_file($f) ? trim((string)file_get_contents($f)) : 'missing';
