<?php
// PHP-Session-Authentifizierung für den Admin-Bereich. Bewusst unabhängig vom
// Apache Basic Auth des Mitgliederbereichs (mitglieder/): Der Admin ist ein
// hardcodierter Einzel-Account (fester Benutzername + Passwort), damit das Ändern
// des geteilten Mitglied-Logins den Admin nie aussperrt. Das Admin-Passwort liegt
// ausschließlich als bcrypt-Hash vor (nie im Klartext im Repo).
declare(strict_types=1);

// Fuer die Anmeldung von Member-Admins (siehe admin_attempt_login()): individuelle
// Accounts (E-Mail + Passwort) aus accounts.json und das isAdmin-Flag aus members.json.
// Bewusst NICHT member-auth.php einbinden - die wuerde eine zweite session_start()-/
// Cookie-Logik mitbringen; Admin- und Mitgliederbereich teilen sich dieselbe
// PHP-Session, nutzen aber getrennte Session-Keys (admin_authenticated vs.
// member_authenticated), daher konfliktfrei.
require_once __DIR__ . '/../mitglieder/accounts-lib.php';
require_once __DIR__ . '/../mitglieder/members-lib.php';

const ADMIN_USER = 'Admin';
// bcrypt-Hash des Admin-Passworts. Neu erzeugen z. B. mit:
//   php -r "echo password_hash('NEUES_PW', PASSWORD_BCRYPT), PHP_EOL;"
//   oder:  htpasswd -nbBC 12 x 'NEUES_PW'   (Teil nach dem ersten ':' verwenden)
const ADMIN_PASS_HASH = '$2y$12$9wIVTo2Y9t6xNToGtEO.ke4NeKNZ5rDg5Bb25AH589h2ZJ457ujxu';

// Erkennt echtes HTTPS. Production läuft über HTTPS (Secure-Cookies erzwungen), die
// Testumgebung (staging) bewusst über HTTP – dort darf 'secure' nicht gesetzt sein,
// sonst verwirft der Browser das Session-Cookie und der Admin-Login greift nicht.
// Ein gefälschtes X-Forwarded-Proto kann das Cookie nur strenger machen (Secure),
// nie unsicherer – kein Downgrade-Risiko für Production.
function admin_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

// Cookie-Parameter VOR session_start() setzen, damit sie schon fürs erste
// Session-Cookie greifen (nicht erst beim session_regenerate_id im Login).
// 'secure' nur bei echtem HTTPS (Prod), damit der Login in der HTTP-Testumgebung greift.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'secure' => admin_request_is_https(),  // HTTPS: hart erzwungen; HTTP-Staging: aus
        'httponly' => true,    // No JavaScript access (prevents XSS-based session theft)
        'samesite' => 'Lax'    // CSRF protection (blocks cross-site request forgery)
    ]);
    session_start();
}

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['admin_authenticated']);
}

function require_admin(): void
{
    if (!admin_is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function admin_check_csrf(): void
{
    $token = (string) ($_POST['csrf'] ?? '');
    if (!admin_is_logged_in() || empty($_SESSION['admin_csrf']) || !hash_equals((string) $_SESSION['admin_csrf'], $token)) {
        http_response_code(400);
        exit('Ungültiges oder abgelaufenes Formular. Bitte Seite neu laden und erneut versuchen.');
    }
}

// Sucht ein Mitglied per E-Mail-Login zu einem Admin-Zugang: Passwort muss stimmen,
// das Konto muss fertig eingerichtet sein (kein offenes Einmalpasswort mehr) UND das
// Mitglied muss die administrative Zusatz-Berechtigung isAdmin = true tragen. Gibt
// den Mitglieds-Datensatz zurueck, sonst null. Ein blosses Einmalpasswort
// (mustChangePassword) reicht bewusst NICHT fuer den Admin-Zugang - der Member muss
// sein Konto zuerst im Mitgliederbereich fertig einrichten (echtes Passwort setzen).
function admin_member_admin_login(string $email, string $pass): ?array
{
    $acc = find_account_by_email($email);
    if ($acc === null || empty($acc['passwordHash']) || !empty($acc['mustChangePassword'])) {
        return null;
    }
    if (!password_verify($pass, (string) $acc['passwordHash'])) {
        return null;
    }
    $memberId = (string) ($acc['memberId'] ?? '');
    foreach (load_members() as $m) {
        if ((string) ($m['id'] ?? '') === $memberId && !empty($m['isAdmin'])) {
            return $m;
        }
    }
    return null;
}

// Anmeldung im Admin-Bereich. Zwei Wege, beide fuehren zu IDENTISCHEN (vollen) Rechten:
//  1. Der hartcodierte Backup-Admin (Benutzer "Admin" + bcrypt-Hash) - unveraenderlich
//     und dauerhaft verfuegbar, damit ein Fehl-Entzug der isAdmin-Rechte nie alle
//     aussperrt.
//  2. Ein Member-Admin: gibt seine E-Mail (im Benutzerfeld) + sein Mitglieder-Passwort
//     ein; Zugang nur, wenn sein Mitglied isAdmin = true traegt (siehe oben).
function admin_attempt_login(string $user, string $pass): bool
{
    // Weg 1: hartcodierter Admin (zeitkonstanter Vergleich + password_verify).
    $userOk = hash_equals(ADMIN_USER, $user);
    $passOk = password_verify($pass, ADMIN_PASS_HASH);
    if ($userOk && $passOk) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_label'] = ADMIN_USER;
        return true;
    }

    // Weg 2: Member-Admin per E-Mail + Mitglieder-Passwort.
    $member = admin_member_admin_login($user, $pass);
    if ($member !== null) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_label'] = (string) ($member['name'] ?? $user);
        return true;
    }

    return false;
}

// Anzeigename des aktuell angemeldeten Admins (nur fuer die Begruessung; die Rechte
// sind fuer alle Admins identisch). Faellt auf "Admin" zurueck.
function admin_current_label(): string
{
    return (string) ($_SESSION['admin_label'] ?? ADMIN_USER);
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
