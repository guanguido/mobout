<?php
// Admin-konfigurierbare Berechtigungs-Matrix (Rolle x Recht) fuer den Mitgliederbereich.
// Gleiches data/-Seed-Muster wie email-templates-lib.php: Solange
// data/role-permissions.json fehlt, greift der git-getrackte Seed
// (role-permissions-seed.json). Anders als bei den E-Mail-Templates gibt es hier
// aber nur EINEN Reset-Knopf fuer die gesamte Matrix statt pro Recht, siehe
// reset_role_permissions().
declare(strict_types=1);

require_once __DIR__ . '/members-lib.php'; // fuer MEMBER_ROLES

define('ROLE_PERMISSIONS_DATA_FILE', __DIR__ . '/data/role-permissions.json');
define('ROLE_PERMISSIONS_SEED_FILE', __DIR__ . '/role-permissions-seed.json');

// Registry: die 5 konfigurierbaren Rechte mit Anzeige-Text.
function permission_defs(): array
{
    return [
        'motd_edit' => [
            'label' => 'Nachricht des Tages bearbeiten',
            'description' => 'Karte + Bereich "Nachricht des Tages" im Mitgliederbereich sehen und speichern.',
        ],
        'expeditions_edit' => [
            'label' => 'Expeditionen bearbeiten',
            'description' => 'Karte + Bereich "Expeditionen" im Mitgliederbereich sehen, anlegen, bearbeiten, löschen.',
        ],
        'navionics_view' => [
            'label' => 'Navionics-Zugangsdaten ansehen',
            'description' => 'Karte + Bereich mit den gemeinsamen Navionics-Zugangsdaten sehen.',
        ],
        'instagram_view' => [
            'label' => 'Instagram-Zugangsdaten ansehen',
            'description' => 'Karte + Bereich mit den gemeinsamen Instagram-Zugangsdaten und der Anleitung sehen.',
        ],
        'own_account_edit' => [
            'label' => 'Eigenes Konto bearbeiten',
            'description' => 'Eigenes Passwort ändern, eigene Zustimmung erteilen, eigenes Profil (Text/Icon/Foto) bearbeiten.',
        ],
    ];
}

function role_permissions_read_file(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

// Liefert fuer jede bekannte Rolle+Recht-Zelle einen bool - aus data/, sonst Seed,
// sonst false (sicherer, geschlossener Default statt Exception bei unbekannten
// Zellen - siehe CLAUDE.md "Rollen-Berechtigungen").
function load_role_permissions(): array
{
    $data = role_permissions_read_file(ROLE_PERMISSIONS_DATA_FILE);
    $seed = role_permissions_read_file(ROLE_PERMISSIONS_SEED_FILE);
    $out = [];
    foreach (MEMBER_ROLES as $role) {
        foreach (permission_defs() as $perm => $def) {
            $out[$role][$perm] = (bool) ($data[$role][$perm] ?? $seed[$role][$perm] ?? false);
        }
    }
    return $out;
}

// Speichert nur bekannte Rollen/Rechte nach data/role-permissions.json.
function save_role_permissions(array $posted): void
{
    $dir = dirname(ROLE_PERMISSIONS_DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $clean = [];
    foreach (MEMBER_ROLES as $role) {
        foreach (permission_defs() as $perm => $def) {
            $clean[$role][$perm] = !empty($posted[$role][$perm]);
        }
    }
    file_put_contents(
        ROLE_PERMISSIONS_DATA_FILE,
        json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

// Setzt die GESAMTE Matrix auf den mitgelieferten Standard (Seed) zurueck, indem
// die komplette Override-Datei geloescht wird (anders als reset_email_template(),
// das nur einen einzelnen Key entfernt - hier gibt es bewusst nur einen
// Reset-Knopf fuer die ganze Konfiguration). Da nicht der aktuelle Seed-Inhalt
// hineinkopiert, sondern die Datei geloescht wird, greift danach beim naechsten
// Laden automatisch wieder load_role_permissions()' Seed-Fallback - inkl.
// kuenftiger Seed-Aktualisierungen im Code.
function reset_role_permissions(): void
{
    if (is_file(ROLE_PERMISSIONS_DATA_FILE)) {
        @unlink(ROLE_PERMISSIONS_DATA_FILE);
    }
}

function role_has_permission(string $role, string $permission): bool
{
    $all = load_role_permissions();
    return (bool) ($all[$role][$permission] ?? false);
}

// Kombiniert die Rolle des eingeloggten Mitglieds mit der Matrix. Bewusst HIER
// platziert statt in members-lib.php, damit members-lib.php ein reines
// Datenzugriffsmodul ohne Kenntnis des Berechtigungssystems bleibt (einseitige
// Abhaengigkeit: role-permissions-lib -> members-lib, nie umgekehrt).
function member_current_has_permission(string $permission): bool
{
    $role = member_current_role();
    return $role !== '' && role_has_permission($role, $permission);
}

// Hard-Exit-Helfer fuer Schreib-Endpunkte, analog member_check_csrf()'s Stil,
// aber mit 403 statt 400 (Autorisierungs- statt Validierungsfehler).
function require_permission(string $permission): void
{
    if (!member_current_has_permission($permission)) {
        http_response_code(403);
        exit('Keine Berechtigung für diese Aktion.');
    }
}
