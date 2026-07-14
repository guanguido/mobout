<?php
require_admin();
require_once __DIR__ . '/../mitglieder/imap-lib.php';

member_check_csrf($_POST['csrf'] ?? '');

$host = trim($_POST['host'] ?? '');
$port = (int)($_POST['port'] ?? 0);
$user = trim($_POST['user'] ?? '');
$pass = trim($_POST['pass'] ?? '');

if (empty($host) || empty($port) || empty($user) || empty($pass)) {
  http_response_code(400);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'Alle Felder erforderlich']);
  exit;
}

$test = test_imap_connection($host, $port, $user, $pass);
if (!$test['ok']) {
  http_response_code(400);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => $test['error']]);
  exit;
}

save_imap_config([
  'host' => $host,
  'port' => $port,
  'user' => $user,
  'pass' => $pass,
]);

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'message' => 'Konfiguration gespeichert ✓']);
exit;
