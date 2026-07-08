<?php
// Verwaltung des EINEN geteilten Mitglied-Logins (Basic Auth des Mitgliederbereichs).
// Die aktive .htpasswd liegt deploy-sicher unter mitglieder/data/.htpasswd
// (git-ignoriert). mitglieder/.htpasswd bleibt nur der git-getrackte Default/Seed;
// der Deploy kopiert ihn einmalig nach data/, falls dort noch keiner existiert.
declare(strict_types=1);

define('MEMBER_HTPASSWD_DATA', __DIR__ . '/../mitglieder/data/.htpasswd');
define('MEMBER_HTPASSWD_SEED', __DIR__ . '/../mitglieder/.htpasswd');

function member_htpasswd_file(): string
{
    return is_file(MEMBER_HTPASSWD_DATA) ? MEMBER_HTPASSWD_DATA : MEMBER_HTPASSWD_SEED;
}

function member_account_username(): string
{
    $file = member_htpasswd_file();
    if (!is_file($file)) {
        return '';
    }
    $line = trim((string) @file_get_contents($file));
    $pos = strpos($line, ':');
    return $pos === false ? '' : substr($line, 0, $pos);
}

// Schreibt Benutzername + Passwort (bcrypt) in die aktive data/.htpasswd.
// PHP password_hash(PASSWORD_BCRYPT) liefert einen $2y$-Hash, den Apache akzeptiert.
function save_member_account(string $user, string $pass): void
{
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $line = $user . ':' . $hash . "\n";
    $dir = dirname(MEMBER_HTPASSWD_DATA);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(MEMBER_HTPASSWD_DATA, $line, LOCK_EX);
}
