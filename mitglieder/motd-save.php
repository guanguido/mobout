<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Schutz früher über Apache Basic Auth, jetzt über die PHP-Session (member-auth.php).
require __DIR__ . '/member-auth.php';
require_member();
member_check_csrf();
member_enforce_password_change();
require __DIR__ . '/role-permissions-lib.php';
require_permission('motd_edit');

$text = trim(substr($_POST['motd'] ?? '', 0, 500));
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}
file_put_contents($dataDir . '/motd.txt', $text);

header('Location: index.php');
exit;
