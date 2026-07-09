<?php
// PHP-Session-Login für den Mitgliederbereich (mitglieder/). Ersetzt den früheren
// Apache Basic Auth. Individuelle Mitglieder-Accounts (E-Mail als Loginname), siehe
// accounts-lib.php. Löst die Enabler-Stufe (EIN geteilter Zugang aus data/.htpasswd)
// ab.
declare(strict_types=1);

require_once __DIR__ . '/accounts-lib.php';

// Erkennt, ob die aktuelle Anfrage wirklich über HTTPS läuft. Production läuft über
// HTTPS (Secure-Cookies erzwungen), die Testumgebung (staging) bewusst über HTTP.
// Dort darf das Secure-Flag NICHT gesetzt sein, sonst verwirft der Browser das
// Session-Cookie und der Login greift nicht. Ein gefälschtes X-Forwarded-Proto kann
// das Cookie nur strenger (Secure) machen, nie unsicherer – kein Downgrade-Risiko.
function member_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

// Cookie-Parameter VOR session_start() setzen, damit sie schon für das erste
// Session-Cookie greifen (nicht erst beim session_regenerate_id im Login).
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'secure' => member_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Ohne Apache Basic Auth wäre mitglieder/data/ direkt per HTTP erreichbar. Wir legen
// darum bei jedem Laden sicher, dass data/ existiert und ein .htaccess enthält, das
// den direkten Web-Zugriff auf die serverseitigen Laufzeitdaten (accounts.json,
// email-templates.json, consent-log/, ...) sperrt.
function member_ensure_data_hardening(): void
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ht = $dir . '/.htaccess';
    if (is_dir($dir) && !is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n");
    }
}

function member_is_logged_in(): bool
{
    return !empty($_SESSION['member_authenticated']);
}

function member_current_id(): string
{
    return (string) ($_SESSION['member_id'] ?? '');
}

function member_current_email(): string
{
    return (string) ($_SESSION['member_email'] ?? '');
}

function require_member(): void
{
    if (!member_is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

// Blockiert die Gruppeninhalte-Endpunkte (motd-save.php, expeditions-save.php) und
// die Zustimmung (consent-save.php), solange das Konto ein mustChangePassword=true
// trägt (z. B. direkt nach einem frisch ausgestellten Einmalpasswort). index.php
// selbst ruft diese Funktion NICHT auf - dort wird stattdessen die Ansicht auf das
// Passwort-Ändern-Formular reduziert (siehe Kommentar in index.php), sonst gäbe es
// eine Redirect-Schleife auf sich selbst.
function member_enforce_password_change(): void
{
    if (!member_is_logged_in()) {
        return;
    }
    $acc = find_account_by_member_id(member_current_id());
    if ($acc !== null && !empty($acc['mustChangePassword'])) {
        header('Location: index.php#konto-bereich');
        exit;
    }
}

function member_csrf_token(): string
{
    if (empty($_SESSION['member_csrf'])) {
        $_SESSION['member_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['member_csrf'];
}

function member_check_csrf(): void
{
    $token = (string) ($_POST['csrf'] ?? '');
    if (!member_is_logged_in() || empty($_SESSION['member_csrf']) || !hash_equals((string) $_SESSION['member_csrf'], $token)) {
        http_response_code(400);
        exit('Ungültiges oder abgelaufenes Formular. Bitte Seite neu laden und erneut versuchen.');
    }
}

// Login per E-Mail + Passwort gegen den individuellen Account (accounts.json). Ein
// erfolgreicher Login - egal ob mit einem regulären Passwort oder einem gerade per
// Mail zugestellten Einmalpasswort - beweist, dass die Person die hinterlegte E-Mail
// tatsächlich empfangen kann: die E-Mail gilt danach als verifiziert.
function member_attempt_login(string $email, string $pass): bool
{
    $acc = find_account_by_email($email);
    if ($acc === null || empty($acc['passwordHash']) || !password_verify($pass, (string) $acc['passwordHash'])) {
        return false;
    }
    $memberId = (string) $acc['memberId'];
    session_regenerate_id(true);
    $_SESSION['member_authenticated'] = true;
    $_SESSION['member_id'] = $memberId;
    $_SESSION['member_email'] = (string) $acc['email'];
    if (empty($acc['emailVerified'])) {
        mark_email_verified($memberId);
    }
    return true;
}

function member_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

member_ensure_data_hardening();
