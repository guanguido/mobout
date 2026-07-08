<?php
// PHP-Session-Authentifizierung für den Admin-Bereich. Bewusst unabhängig vom
// Apache Basic Auth des Mitgliederbereichs (mitglieder/): Der Admin ist ein
// hardcodierter Einzel-Account (fester Benutzername + Passwort), damit das Ändern
// des geteilten Mitglied-Logins den Admin nie aussperrt. Das Admin-Passwort liegt
// ausschließlich als bcrypt-Hash vor (nie im Klartext im Repo).
declare(strict_types=1);

const ADMIN_USER = 'Admin';
// bcrypt-Hash des Admin-Passworts. Neu erzeugen z. B. mit:
//   php -r "echo password_hash('NEUES_PW', PASSWORD_BCRYPT), PHP_EOL;"
//   oder:  htpasswd -nbBC 12 x 'NEUES_PW'   (Teil nach dem ersten ':' verwenden)
const ADMIN_PASS_HASH = '$2y$12$9wIVTo2Y9t6xNToGtEO.ke4NeKNZ5rDg5Bb25AH589h2ZJ457ujxu';

if (session_status() === PHP_SESSION_NONE) {
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

function admin_attempt_login(string $user, string $pass): bool
{
    // Zeitkonstanter Benutzervergleich + password_verify gegen den bcrypt-Hash.
    $userOk = hash_equals(ADMIN_USER, $user);
    $passOk = password_verify($pass, ADMIN_PASS_HASH);
    if ($userOk && $passOk) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        return true;
    }
    return false;
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
