<?php
// Passwort selbst ändern (auch der erzwungene erste Wechsel nach einem
// Einmalpasswort läuft über diesen Endpunkt, siehe member_enforce_password_change()
// in member-auth.php). Verlangt das aktuelle Passwort zur Sicherheit, da der
// Mitgliederbereich für alle Mitglieder gleichermaßen erreichbar ist.
declare(strict_types=1);

require __DIR__ . '/member-auth.php';
require_member();
member_check_csrf();

$current = (string) ($_POST['current'] ?? '');
$new = (string) ($_POST['new'] ?? '');
$newRepeat = (string) ($_POST['newRepeat'] ?? '');

function password_change_fail(string $reason): void
{
    header('Location: index.php?pwmsg=' . urlencode($reason) . '#konto-bereich');
    exit;
}

$acc = find_account_by_member_id(member_current_id());
if ($acc === null || empty($acc['passwordHash']) || !password_verify($current, (string) $acc['passwordHash'])) {
    password_change_fail('current-wrong');
}

if (strlen($new) < 8) {
    password_change_fail('too-short');
}

if ($new !== $newRepeat) {
    password_change_fail('mismatch');
}

set_password(member_current_id(), $new);

require_once __DIR__ . '/members-lib.php';
$name = member_current_email();
foreach (load_members() as $m) {
    if (($m['id'] ?? '') === member_current_id()) {
        $name = (string) ($m['name'] ?? $name);
        break;
    }
}
send_password_changed_mail(member_current_email(), $name);

header('Location: index.php?pwmsg=changed#konto-bereich');
exit;
