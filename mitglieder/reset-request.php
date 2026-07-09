<?php
// Öffentlicher Endpunkt "Passwort vergessen / Zugang einrichten": kein Session-Zwang,
// da genau hier ein Mitglied ohne gültige Anmeldung starten muss. Stellt bei
// existierender E-Mail ein neues Einmalpasswort aus (siehe accounts-lib.php,
// issue_otp()) und verschickt es per Mail. Antwortet IMMER mit derselben generischen
// Meldung, unabhängig davon, ob die E-Mail existiert - schützt gegen das Erraten
// registrierter Adressen (User-Enumeration).
declare(strict_types=1);

require __DIR__ . '/accounts-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $acc = find_account_by_email($email);
    if ($acc !== null) {
        $memberId = (string) $acc['memberId'];
        $otp = issue_otp($memberId);
        if ($otp !== null) {
            require_once __DIR__ . '/members-lib.php';
            $name = $email;
            foreach (load_members() as $m) {
                if (($m['id'] ?? '') === $memberId) {
                    $name = (string) ($m['name'] ?? $email);
                    break;
                }
            }
            send_otp_mail($email, $name, $otp);
        }
    }
}

header('Location: index.php?msg=reset-requested');
exit;
