<?php
// Ein Mitglied erteilt selbst die Zustimmung zur öffentlichen Anzeige auf mobout.de.
// Nur erlaubt, wenn die hinterlegte E-Mail bereits verifiziert ist UND kein
// erzwungener Passwortwechsel mehr aussteht (beides beweist: die eingeloggte Person
// hat die E-Mail selbst empfangen UND ihr eigenes Passwort gesetzt). Schreibt zusätzlich
// zum members.json-Flag ein Audit-Log und eine Info-Mail an info@mobout.de.
declare(strict_types=1);

require __DIR__ . '/member-auth.php';
require_member();
member_check_csrf();
member_enforce_password_change();

require_once __DIR__ . '/members-lib.php';

$memberId = member_current_id();
$acc = find_account_by_member_id($memberId);

if ($acc === null || empty($acc['emailVerified'])) {
    header('Location: index.php?consentmsg=not-verified#konto-bereich');
    exit;
}

$list = load_members();
$name = $memberId;
$found = false;
$now = date('c');
foreach ($list as &$m) {
    if (($m['id'] ?? '') !== $memberId) {
        continue;
    }
    $found = true;
    $name = (string) ($m['name'] ?? $memberId);
    $m['consentGiven'] = true;
    $m['consentAt'] = $now;
    $m['consentSource'] = 'self';
    break;
}
unset($m);

if (!$found) {
    header('Location: index.php?consentmsg=error#konto-bereich');
    exit;
}

save_members($list);
write_consent_log($memberId, $name, (string) $acc['email'], $now, 'self');
send_consent_notice_mail($name, (string) $acc['email'], $now, 'self');

header('Location: index.php?consentmsg=granted#konto-bereich');
exit;
