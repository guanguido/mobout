<?php
header('Content-Type: text/plain; charset=utf-8');
$file = __DIR__ . '/mitglieder/data/motd.txt';
$msg = is_file($file) ? trim(file_get_contents($file)) : '';
// Kein HTML-Escaping nötig: index.html fügt die Antwort ausschließlich über
// textContent ein, nie über innerHTML.
echo $msg;
