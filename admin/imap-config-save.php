<?php
require __DIR__ . '/auth.php';
require_admin();
require_once __DIR__ . '/../mitglieder/imap-lib.php';

admin_check_csrf();

$host = trim($_POST['host'] ?? '');
$port = (int)($_POST['port'] ?? 0);
$user = trim($_POST['user'] ?? '');
$pass = trim($_POST['pass'] ?? '');

if (empty($host) || empty($port) || empty($user) || empty($pass)) {
  header('Location: index.php?msg=imap-config-error&info=' . rawurlencode('Alle Felder erforderlich') . '#email-config-bereich');
  exit;
}

$test = test_imap_connection($host, $port, $user, $pass);
if (!$test['ok']) {
  header('Location: index.php?msg=imap-config-error&info=' . rawurlencode($test['error']) . '#email-config-bereich');
  exit;
}

save_imap_config([
  'host' => $host,
  'port' => $port,
  'user' => $user,
  'pass' => $pass,
]);

header('Location: index.php?msg=imap-config-saved#email-config-bereich');
exit;
