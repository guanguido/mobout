<?php
// Individuelle Mitglieder-Accounts (E-Mail als Loginname). Ersetzt den früheren EINEN
// geteilten Basic-Auth-Zugang (data/.htpasswd, siehe Enabler-Stufe in member-auth.php).
//
// Speicher: data/accounts.json (git-ignoriert, per HTTP gesperrt durch
// member_ensure_data_hardening() in member-auth.php). Pro Mitglied ein Eintrag:
//   memberId, email, emailVerified, passwordHash, mustChangePassword,
//   createdAt, updatedAt
//
// OTP = Passwort-Hash: Ein Einmalpasswort wird nicht separat gespeichert.
// issue_otp() setzt passwordHash = bcrypt(OTP) + mustChangePassword = true. Der
// erfolgreiche Login mit dem OTP verifiziert gegen genau diesen Hash; danach markiert
// member_enforce_password_change() den Nutzer als "muss Passwort ändern". Derselbe Weg
// deckt Erst-Einrichtung UND "Passwort vergessen" ab. Bewusst OHNE Ablauf und OHNE
// Rate-Limit (siehe CLAUDE.md / Plan - Einfachheit vor Robustheit).
declare(strict_types=1);

require_once __DIR__ . '/email-templates-lib.php';

define('ACCOUNTS_DATA_FILE', __DIR__ . '/data/accounts.json');

const MEMBER_AREA_URL = 'https://www.mobout.de/mitglieder/';
const MOBOUT_INFO_EMAIL = 'info@mobout.de';

function load_accounts(): array
{
    if (!is_file(ACCOUNTS_DATA_FILE)) {
        return [];
    }
    $list = json_decode((string) file_get_contents(ACCOUNTS_DATA_FILE), true);
    return is_array($list) ? $list : [];
}

function save_accounts(array $list): void
{
    $dir = dirname(ACCOUNTS_DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(
        ACCOUNTS_DATA_FILE,
        json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function find_account_by_email(string $email): ?array
{
    $email = strtolower(trim($email));
    foreach (load_accounts() as $acc) {
        if (strtolower((string) ($acc['email'] ?? '')) === $email) {
            return $acc;
        }
    }
    return null;
}

function find_account_by_member_id(string $memberId): ?array
{
    foreach (load_accounts() as $acc) {
        if ((string) ($acc['memberId'] ?? '') === $memberId) {
            return $acc;
        }
    }
    return null;
}

// Legt einen neuen Account an (memberId ohne bestehenden Account, E-Mail eindeutig).
// Ohne Passwort - erst issue_otp() macht den Zugang nutzbar. Gibt false zurück, wenn
// die memberId schon einen Account hat oder die E-Mail bereits vergeben ist.
function create_account(string $memberId, string $email): bool
{
    $email = trim($email);
    if ($memberId === '' || $email === '' || find_account_by_member_id($memberId) !== null || find_account_by_email($email) !== null) {
        return false;
    }
    $list = load_accounts();
    $now = date('c');
    $list[] = [
        'memberId' => $memberId,
        'email' => $email,
        'emailVerified' => false,
        'passwordHash' => null,
        'mustChangePassword' => true,
        'createdAt' => $now,
        'updatedAt' => $now,
    ];
    save_accounts($list);
    return true;
}

// Ändert die hinterlegte E-Mail eines bestehenden Accounts (z. B. Admin-Korrektur).
// E-Mail-Verifizierung wird zurückgesetzt, da die neue Adresse noch nicht bestätigt ist.
function update_account_email(string $memberId, string $email): bool
{
    $email = trim($email);
    if ($email === '') {
        return false;
    }
    $existing = find_account_by_email($email);
    if ($existing !== null && (string) ($existing['memberId'] ?? '') !== $memberId) {
        return false; // E-Mail bereits einem anderen Mitglied zugeordnet
    }
    $list = load_accounts();
    $found = false;
    foreach ($list as &$acc) {
        if ((string) ($acc['memberId'] ?? '') === $memberId) {
            $acc['email'] = $email;
            $acc['emailVerified'] = false;
            $acc['updatedAt'] = date('c');
            $found = true;
            break;
        }
    }
    unset($acc);
    if ($found) {
        save_accounts($list);
    }
    return $found;
}

// Stellt ein neues Einmalpasswort aus (Erst-Einrichtung wie "Passwort vergessen"):
// bcrypt-Hash des OTP als passwordHash, mustChangePassword = true. Gibt das
// Klartext-OTP zurück (nur für den Versand, wird selbst nie gespeichert).
function issue_otp(string $memberId): ?string
{
    $list = load_accounts();
    $found = false;
    $otp = bin2hex(random_bytes(6)); // 12 Hex-Zeichen, ausreichend für ein Einmalpasswort
    foreach ($list as &$acc) {
        if ((string) ($acc['memberId'] ?? '') === $memberId) {
            $acc['passwordHash'] = password_hash($otp, PASSWORD_BCRYPT);
            $acc['mustChangePassword'] = true;
            $acc['updatedAt'] = date('c');
            $found = true;
            break;
        }
    }
    unset($acc);
    if (!$found) {
        return null;
    }
    save_accounts($list);
    return $otp;
}

function set_password(string $memberId, string $password): bool
{
    $list = load_accounts();
    $found = false;
    foreach ($list as &$acc) {
        if ((string) ($acc['memberId'] ?? '') === $memberId) {
            $acc['passwordHash'] = password_hash($password, PASSWORD_BCRYPT);
            $acc['mustChangePassword'] = false;
            $acc['updatedAt'] = date('c');
            $found = true;
            break;
        }
    }
    unset($acc);
    if ($found) {
        save_accounts($list);
    }
    return $found;
}

function mark_email_verified(string $memberId): void
{
    $list = load_accounts();
    foreach ($list as &$acc) {
        if ((string) ($acc['memberId'] ?? '') === $memberId) {
            $acc['emailVerified'] = true;
            $acc['updatedAt'] = date('c');
            break;
        }
    }
    unset($acc);
    save_accounts($list);
}

function delete_account(string $memberId): void
{
    $list = array_values(array_filter(
        load_accounts(),
        static fn($acc) => (string) ($acc['memberId'] ?? '') !== $memberId
    ));
    save_accounts($list);
}

// --- Mail-Versand ---------------------------------------------------------
// Nach dem Muster aus contact.php: PHP mail(), From/Reply-To, CR/LF-Strip gegen
// Header-Injection, Content-Type text/plain UTF-8. Betreff zusätzlich über
// mb_encode_mimeheader() kodiert (contact.php macht das nicht - hier bewusst
// nachgerüstet, da unsere Betreffs Namen/Umlaute enthalten können).

function member_mail_strip(string $value): string
{
    return trim(str_replace(["\r", "\n"], ' ', $value));
}

function member_mail_send(string $to, string $toName, string $templateKey, array $vars): bool
{
    $rendered = render_email_template($templateKey, $vars);
    $subject = member_mail_strip($rendered['subject']);
    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    $body = $rendered['body'];

    $toName = member_mail_strip($toName);
    $headers = "From: MobOut Website <" . MOBOUT_INFO_EMAIL . ">\r\n"
        . 'Content-Type: text/plain; charset=utf-8';

    return mail($to, $encodedSubject, $body, $headers);
}

function send_welcome_mail(string $email, string $name): bool
{
    return member_mail_send($email, $name, 'welcome', [
        'NAME' => $name,
        'EMAIL' => $email,
        'MEMBER_AREA_URL' => MEMBER_AREA_URL,
    ]);
}

function send_otp_mail(string $email, string $name, string $otp): bool
{
    return member_mail_send($email, $name, 'otp', [
        'NAME' => $name,
        'ONETIMEPASSWORD' => $otp,
        'MEMBER_AREA_URL' => MEMBER_AREA_URL,
    ]);
}

function send_password_changed_mail(string $email, string $name): bool
{
    return member_mail_send($email, $name, 'password-changed', [
        'NAME' => $name,
        'CHANGE_DATE' => date('d.m.Y H:i'),
        'MEMBER_AREA_URL' => MEMBER_AREA_URL,
    ]);
}

// Info-/Audit-Mail an das MobOut-Postfach, wenn eine Zustimmung erteilt wird.
function send_consent_notice_mail(string $name, string $email, string $consentAt, string $source): bool
{
    return member_mail_send(MOBOUT_INFO_EMAIL, 'MobOut', 'consent-notice', [
        'NAME' => $name,
        'EMAIL' => $email,
        'CONSENT_DATE' => $consentAt,
        'CONSENT_SOURCE' => $source,
    ]);
}

// --- Zustimmungs-Audit-Log --------------------------------------------------
// Jede Zustimmung wird zusätzlich als eigene, unveränderliche JSON-Datei unter
// data/consent-log/ abgelegt. WICHTIG: Der Zeitpunkt steht INHALTLICH im JSON
// (consentAt), nicht als Datei-mtime - die mtime überlebt Kopieren/Backup/Transfer
// nicht zuverlässig und wird hier nirgends als Wahrheit verwendet. Der Dateiname
// dient nur der Sortierung/Eindeutigkeit.
define('CONSENT_LOG_DIR', __DIR__ . '/data/consent-log');

function write_consent_log(string $memberId, string $name, string $email, string $consentAt, string $source): void
{
    if (!is_dir(CONSENT_LOG_DIR)) {
        mkdir(CONSENT_LOG_DIR, 0755, true);
    }
    $entry = [
        'memberId' => $memberId,
        'name' => $name,
        'email' => $email,
        'consentAt' => $consentAt,
        'source' => $source, // 'self' oder 'admin'
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];
    $safeStamp = preg_replace('/[^0-9T:\-]/', '', $consentAt) ?? date('c');
    $safeStamp = str_replace(':', '', $safeStamp);
    $filename = $safeStamp . '-' . preg_replace('/[^a-z0-9\-]/', '', $memberId) . '.json';
    file_put_contents(
        CONSENT_LOG_DIR . '/' . $filename,
        json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}
