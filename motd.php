<?php
header('Content-Type: text/plain; charset=utf-8');
$file = __DIR__ . '/mitglieder/data/motd.txt';
$msg = is_file($file) ? trim(file_get_contents($file)) : '';
echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
