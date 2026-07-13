<?php
// Passwort selbst ändern (auch der erzwungene erste Wechsel nach einem
// Einmalpasswort läuft über diesen Endpunkt, siehe member_enforce_password_change()
// in member-auth.php). Verlangt das aktuelle Passwort zur Sicherheit, da der
// Mitgliederbereich für alle Mitglieder gleichermaßen erreichbar ist.
declare(strict_types=1);

require __DIR__ . '/member-auth.php';
require_member();
member_check_csrf();
require __DIR__ . '/role-permissions-lib.php';
require_permission('own_account_edit');

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

// Nur die erzwungene Erst-Anmeldung darf über die Checkbox unten Zustimmung
// erteilen (nicht die freiwillige Passwort-Aenderung im Konto-Bereich, die kein
// consent-Feld sendet) - Flag VOR set_password() sichern, da set_password()
// mustChangePassword loescht.
$mustChangeBefore = !empty($acc['mustChangePassword']);

set_password(member_current_id(), $new);
unset($_SESSION['member_pending_current_password']);

require_once __DIR__ . '/members-lib.php';

$memberId = member_current_id();
$email = member_current_email();
$name = $email;
$wantsConsent = $mustChangeBefore && !empty($_POST['consent']) && !empty($acc['emailVerified']);
$consentGranted = false;
$now = date('c');

$list = load_members();
foreach ($list as &$m) {
    if (($m['id'] ?? '') !== $memberId) {
        continue;
    }
    $name = (string) ($m['name'] ?? $name);
    // Wer schon zugestimmt hat, wird bei einem spaeteren Passwort-Reset (z. B.
    // "Passwort vergessen" bei einem bestehenden Mitglied) nicht erneut gefragt -
    // sonst wuerde consentAt/Audit-Log/Mail unnoetig erneut ausgeloest.
    if ($wantsConsent && empty($m['consentGiven'])) {
        $m['consentGiven'] = true;
        $m['consentAt'] = $now;
        $m['consentSource'] = 'self';
        $consentGranted = true;
    }
    break;
}
unset($m);

if ($consentGranted) {
    save_members($list);
}

send_password_changed_mail($email, $name);

if ($consentGranted) {
    write_consent_log($memberId, $name, $email, $now, 'self');
    send_consent_notice_mail($name, $email, $now, 'self');
}

header('Location: index.php?pwmsg=changed' . ($consentGranted ? '&consentmsg=granted' : '') . '#konto-bereich');
exit;
