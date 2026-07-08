<?php
// Setzt Benutzername + Passwort des EINEN geteilten Mitglied-Logins (Basic Auth).
// Schreibt mitglieder/data/.htpasswd (deploy-sicher). Nur für eingeloggte Admins.
require __DIR__ . '/auth.php';
require __DIR__ . '/account-lib.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
admin_check_csrf();

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

// Benutzername: nicht leer, kein Doppelpunkt (htpasswd-Trenner), keine Zeilenumbrüche.
$userValid = $username !== ''
    && strpbrk($username, ":\r\n") === false
    && mb_strlen($username) <= 60;
// Passwort: Mindestlänge 6.
$passValid = strlen($password) >= 6;

if (!$userValid || !$passValid) {
    header('Location: index.php?msg=' . urlencode('account-error') . '#account-bereich');
    exit;
}

save_member_account($username, $password);
header('Location: index.php?msg=' . urlencode('account-saved') . '#account-bereich');
exit;
