<?php
// PHP-Session-Login für den Mitgliederbereich (mitglieder/). Ersetzt den früheren
// Apache Basic Auth aus mitglieder/.htaccess. Der Schutz erfolgt jetzt pro Endpunkt
// über eine PHP-Session (analog zum Admin-Bereich, admin/auth.php).
//
// Enabler-Stufe (Phase 0): Es gibt weiterhin nur EINEN geteilten Zugang. Die
// Zugangsdaten werden aus der bestehenden data/.htpasswd (bcrypt) gelesen, nur die
// Prüfung läuft jetzt über PHP statt über Apache. In der nächsten Ausbaustufe
// (Phase 1) wird member_attempt_login() auf individuelle E-Mail-Accounts
// (accounts.json) umgestellt; die übrigen Helfer bleiben unverändert.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session-Cookies härten: nur HTTPS, kein JS-Zugriff, CSRF-Schutz (wie admin/auth.php).
if (session_status() === PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Aktive geteilte Zugangsdaten: data/.htpasswd (git-ignoriert, überlebt Deploys),
// sonst der git-getrackte Seed mitglieder/.htpasswd.
const MEMBER_HTPASSWD_DATA = __DIR__ . '/data/.htpasswd';
const MEMBER_HTPASSWD_SEED = __DIR__ . '/.htpasswd';

// Ohne Apache Basic Auth wäre mitglieder/data/ direkt per HTTP erreichbar. Wir legen
// darum bei jedem Laden sicher, dass data/ existiert und ein .htaccess enthält, das
// den direkten Web-Zugriff auf die serverseitigen Laufzeitdaten (Hashes, JSON) sperrt.
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

function member_htpasswd_line(): string
{
    $file = is_file(MEMBER_HTPASSWD_DATA) ? MEMBER_HTPASSWD_DATA : MEMBER_HTPASSWD_SEED;
    if (!is_file($file)) {
        return '';
    }
    return trim((string) @file_get_contents($file));
}

function member_is_logged_in(): bool
{
    return !empty($_SESSION['member_authenticated']);
}

function require_member(): void
{
    if (!member_is_logged_in()) {
        header('Location: index.php');
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

// Enabler-Stufe: Login gegen den EINEN geteilten Zugang aus der .htpasswd-Zeile
// (Format username:bcrypt-hash). Zeitkonstanter Vergleich + password_verify.
function member_attempt_login(string $user, string $pass): bool
{
    $line = member_htpasswd_line();
    $pos = strpos($line, ':');
    if ($pos === false) {
        return false;
    }
    $storedUser = substr($line, 0, $pos);
    $storedHash = substr($line, $pos + 1);
    $userOk = $storedUser !== '' && hash_equals($storedUser, $user);
    $passOk = $storedHash !== '' && password_verify($pass, $storedHash);
    if ($userOk && $passOk) {
        session_regenerate_id(true);
        $_SESSION['member_authenticated'] = true;
        return true;
    }
    return false;
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
