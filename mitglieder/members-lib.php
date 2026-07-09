<?php
// Gemeinsame Lese-/Schreibfunktionen für die Mitglieder-Verwaltung (Personen, die
// auf der Website angezeigt werden). Analog zu expeditions-lib.php: Solange
// mitglieder/data/members.json noch nicht existiert, liefert load_members() den
// Ausgangsbestand aus der git-getrackten Seed-Datei (members-seed.json). Sobald der
// Admin zum ersten Mal speichert, schreibt save_members() die echte Datendatei.
// Fotos liegen als echte Dateien in data/members-images/ (bzw. beim Seed in
// members-seed-images/, siehe member-image.php).

define('MEMBERS_DATA_FILE', __DIR__ . '/data/members.json');
define('MEMBERS_SEED_FILE', __DIR__ . '/members-seed.json');
define('MEMBERS_IMAGES_DIR', __DIR__ . '/data/members-images');
define('MEMBERS_SEED_IMAGES_DIR', __DIR__ . '/members-seed-images');

// Erlaubte Rollen; bestimmen, in welcher Sektion die Person auf der Website erscheint.
const MEMBER_ROLES = ['team', 'supporter', 'anwaerter'];

// Normalisiert fehlende Consent-Felder (Altbestände vor der Zustimmungs-Funktion
// hatten diese Felder nicht). Rückwärtskompatibel: alte 9-Feld-Datensätze bekommen
// hier sichere Defaults, ohne dass ein Migrationsskript nötig wäre. Wird erst beim
// nächsten save_members() tatsächlich persistiert.
function normalize_member(array $entry): array
{
    $entry['consentGiven'] = (bool) ($entry['consentGiven'] ?? false);
    $entry['consentAt'] = $entry['consentAt'] ?? null;
    $entry['consentSource'] = $entry['consentSource'] ?? null; // 'self' | 'admin' | null
    return $entry;
}

function load_members(): array
{
    $file = is_file(MEMBERS_DATA_FILE) ? MEMBERS_DATA_FILE : MEMBERS_SEED_FILE;
    if (!is_file($file)) {
        return [];
    }
    $list = json_decode(file_get_contents($file), true);
    if (!is_array($list)) {
        return [];
    }
    return array_map('normalize_member', $list);
}

function save_members(array $list): void
{
    // Reihenfolge wird bewusst NICHT sortiert - die Anzeige-Reihenfolge entspricht der
    // gespeicherten Reihenfolge; neu angelegte Mitglieder landen am Ende ihrer Rolle.
    $dataDir = dirname(MEMBERS_DATA_FILE);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    file_put_contents(MEMBERS_DATA_FILE, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function member_slug(string $name): string
{
    $base = strtolower($name);
    $base = preg_replace('/[^a-z0-9]+/u', '-', $base) ?? '';
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'member';
    }
    return 'mem-' . $base;
}

function unique_member_id(array $list, string $name): string
{
    $base = member_slug($name);
    $id = $base;
    $suffix = 2;
    $existingIds = array_column($list, 'id');
    while (in_array($id, $existingIds, true)) {
        $id = $base . '-' . $suffix;
        $suffix++;
    }
    return $id;
}
