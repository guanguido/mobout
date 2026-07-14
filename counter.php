<?php
require __DIR__ . '/mitglieder/visitor-counter-lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    record_visit($_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
