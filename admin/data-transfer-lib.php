<?php
// Zentrale Registry für den Datenübertragungs-Mechanismus (Export/Import als ein
// ZIP-Bundle) aller dynamischen, git-ignorierten mitglieder/data/-Inhalte (MOTD,
// Mitglieder, Expeditionen inkl. Bilder). Dient als Backup, zur Übertragung zwischen
// Umgebungen (Staging/Production) und für lokale Migrationen/Transformationen.
//
// Neue Datentypen nach demselben data/-Prinzip werden hier durch zwei Funktionen
// (data_transfer_export_<modul>, data_transfer_import_<modul>) plus einen weiteren
// Eintrag in data_transfer_modules() ergänzt - Export-/Import-Endpunkt und die
// Admin-UI iterieren generisch über die Registry und müssen nicht angefasst werden.

require_once __DIR__ . '/../mitglieder/members-lib.php';
require_once __DIR__ . '/../mitglieder/expeditions-lib.php';
require_once __DIR__ . '/../mitglieder/accounts-lib.php';
require_once __DIR__ . '/../mitglieder/email-templates-lib.php';
require_once __DIR__ . '/../mitglieder/navionics-lib.php';
require_once __DIR__ . '/../mitglieder/role-permissions-lib.php';
require_once __DIR__ . '/../mitglieder/visitor-counter-lib.php';

const DATA_TRANSFER_MANIFEST_VERSION = 1;
const DATA_TRANSFER_MAX_ZIP_BYTES = 50 * 1024 * 1024;
const DATA_TRANSFER_MAX_IMAGE_BYTES = 5 * 1024 * 1024;
const DATA_TRANSFER_ALLOWED_EXTENSIONS = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

define('DATA_TRANSFER_BACKUP_DIR', __DIR__ . '/../mitglieder/data/backups');
// Je Datentyp werden nur die letzten N Sicherungen behalten (automatische Rotation).
const DATA_TRANSFER_BACKUP_KEEP = 5;

// --- MOTD: hat bisher keine eigene Lib-Datei (motd.php/mitglieder/motd-save.php
// lesen/schreiben inline) - kleine Helfer hier, ohne die bestehenden Dateien anzufassen.

function motd_file(): string
{
    return __DIR__ . '/../mitglieder/data/motd.txt';
}

function read_motd(): string
{
    $file = motd_file();
    return is_file($file) ? trim((string) file_get_contents($file)) : '';
}

function write_motd(string $text): void
{
    $dir = dirname(motd_file());
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(motd_file(), $text);
}

// --- Backup-Helfer: vor jedem Überschreiben wird das Vorherige gesichert. Alle
// Module eines Imports landen im selben Zeitstempel-Ordner (Snapshot des Vorzustands).

function data_transfer_backup_timestamp(): string
{
    static $ts = null;
    if ($ts === null) {
        $ts = date('Y-m-d_His');
    }
    return $ts;
}

function data_transfer_backup_module_dir(string $module): string
{
    $dir = DATA_TRANSFER_BACKUP_DIR . '/' . data_transfer_backup_timestamp() . '/' . $module;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function data_transfer_backup_file(string $module, string $srcFile): bool
{
    if (!is_file($srcFile)) {
        return false;
    }
    copy($srcFile, data_transfer_backup_module_dir($module) . '/' . basename($srcFile));
    return true;
}

function data_transfer_backup_images_dir(string $module, string $imagesDir): bool
{
    if (!is_dir($imagesDir)) {
        return false;
    }
    $files = glob($imagesDir . '/*') ?: [];
    if (empty($files)) {
        return false;
    }
    $dir = data_transfer_backup_module_dir($module) . '/images';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    foreach ($files as $f) {
        if (is_file($f)) {
            copy($f, $dir . '/' . basename($f));
        }
    }
    return true;
}

// Rekursive Verzeichnisgröße/-löschung - für Auflistung und Rotation der Backups.

function data_transfer_dir_size(string $dir): int
{
    if (!is_dir($dir)) {
        return 0;
    }
    $size = 0;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($items as $item) {
        if ($item->isFile()) {
            $size += $item->getSize();
        }
    }
    return $size;
}

function data_transfer_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

function data_transfer_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $value = $bytes;
    foreach (['KB', 'MB', 'GB'] as $unit) {
        $value /= 1024;
        if ($value < 1024) {
            return number_format($value, 1, ',', '.') . ' ' . $unit;
        }
    }
    return number_format($value, 1, ',', '.') . ' TB';
}

// Listet alle vorhandenen Sicherungen (neueste zuerst) - für Anzeige im Admin-UI
// und als Grundlage für die Rotation.
function data_transfer_list_backups(): array
{
    $result = [];
    if (!is_dir(DATA_TRANSFER_BACKUP_DIR)) {
        return $result;
    }
    foreach (scandir(DATA_TRANSFER_BACKUP_DIR) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = DATA_TRANSFER_BACKUP_DIR . '/' . $entry;
        if (!is_dir($path)) {
            continue;
        }
        $modules = [];
        foreach (scandir($path) ?: [] as $sub) {
            if ($sub !== '.' && $sub !== '..' && is_dir($path . '/' . $sub)) {
                $modules[] = $sub;
            }
        }
        $result[] = [
            'timestamp' => $entry,
            'modules' => $modules,
            'sizeBytes' => data_transfer_dir_size($path),
        ];
    }
    usort($result, static fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
    return $result;
}

// Behält je Modul nur die letzten DATA_TRANSFER_BACKUP_KEEP Sicherungen, ältere werden
// gelöscht (und der Zeitstempel-Ordner mitentfernt, falls dadurch leer). Läuft nach
// jedem tatsächlich geschriebenen Backup automatisch - keine manuelle Pflege/Cron nötig.
function data_transfer_prune_backups(string $module): void
{
    $matching = array_values(array_filter(
        data_transfer_list_backups(),
        static fn($b) => in_array($module, $b['modules'], true)
    ));
    foreach (array_slice($matching, DATA_TRANSFER_BACKUP_KEEP) as $backup) {
        $timestampDir = DATA_TRANSFER_BACKUP_DIR . '/' . $backup['timestamp'];
        data_transfer_rrmdir($timestampDir . '/' . $module);
        if (is_dir($timestampDir) && count(scandir($timestampDir) ?: []) <= 2) {
            rmdir($timestampDir);
        }
    }
}

// --- Zip-Slip-Schutz: nur einfache relative Pfade ohne Traversal akzeptieren.

function data_transfer_safe_zip_entry(string $name): bool
{
    if ($name === '' || str_starts_with($name, '/') || str_contains($name, '..') || str_contains($name, "\\")) {
        return false;
    }
    return true;
}

// Liest einen Bild-Eintrag aus dem ZIP, validiert ihn wie ein normaler Upload
// (Whitelist-Extension, echtes Bild via getimagesize(), Größenlimit) und schreibt
// ihn erst nach realpath()-Containment-Check ins Zielverzeichnis.
function data_transfer_extract_image(ZipArchive $zip, string $entryName, string $targetDir, string $targetFilename): bool
{
    if (!data_transfer_safe_zip_entry($entryName)) {
        return false;
    }
    $ext = strtolower(pathinfo($targetFilename, PATHINFO_EXTENSION));
    if (!isset(DATA_TRANSFER_ALLOWED_EXTENSIONS[$ext])) {
        return false;
    }
    $contents = $zip->getFromName($entryName);
    if ($contents === false || strlen($contents) === 0 || strlen($contents) > DATA_TRANSFER_MAX_IMAGE_BYTES) {
        return false;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'mbi');
    file_put_contents($tmp, $contents);
    if (getimagesize($tmp) === false) {
        @unlink($tmp);
        return false;
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $targetPath = $targetDir . '/' . basename($targetFilename);
    $moved = @rename($tmp, $targetPath);
    if (!$moved) {
        $moved = copy($tmp, $targetPath);
        @unlink($tmp);
    }
    if (!$moved) {
        return false;
    }

    $realTarget = realpath($targetPath);
    $realDir = realpath($targetDir);
    if ($realTarget === false || $realDir === false || !str_starts_with($realTarget, $realDir)) {
        @unlink($targetPath);
        return false;
    }
    return true;
}

// --- Schema-Minimalvalidierung: verhindert, dass eine kaputte/fremde Datei die
// Live-Daten zerstört. Bewusst locker (nur Pflichtfelder + Typen), kein volles Schema.

function data_transfer_validate_members(array $list): bool
{
    foreach ($list as $entry) {
        if (!is_array($entry)
            || !isset($entry['id'], $entry['name'], $entry['role'])
            || !is_string($entry['id']) || $entry['id'] === ''
            || !is_string($entry['name'])
            || !in_array($entry['role'], MEMBER_ROLES, true)) {
            return false;
        }
    }
    return true;
}

function data_transfer_validate_expeditions(array $list): bool
{
    foreach ($list as $entry) {
        if (!is_array($entry)
            || !isset($entry['id'], $entry['year'], $entry['location'])
            || !is_string($entry['id']) || $entry['id'] === ''
            || !is_numeric($entry['year'])
            || !is_string($entry['location'])) {
            return false;
        }
    }
    return true;
}

function data_transfer_validate_accounts(array $list): bool
{
    foreach ($list as $entry) {
        if (!is_array($entry)
            || !isset($entry['memberId'], $entry['email'])
            || !is_string($entry['memberId']) || $entry['memberId'] === ''
            || !is_string($entry['email'])) {
            return false;
        }
    }
    return true;
}

// Lockere Schema-Pruefung: pro bekannter Rolle (falls vorhanden) nur bool-artige
// Werte fuer bekannte Rechte - analog zu data_transfer_validate_accounts().
function data_transfer_validate_role_permissions(array $data): bool
{
    foreach (MEMBER_ROLES as $role) {
        if (!isset($data[$role])) {
            continue;
        }
        if (!is_array($data[$role])) {
            return false;
        }
        foreach (permission_defs() as $perm => $def) {
            if (isset($data[$role][$perm]) && !is_bool($data[$role][$perm])) {
                return false;
            }
        }
    }
    return true;
}

// Lockere Schema-Prüfung wie bei data_transfer_validate_accounts(): nur die beiden
// Zähler müssen numerisch sein, updatedAt ist optional.
function data_transfer_validate_visitor_counter(array $data): bool
{
    return isset($data['totalViews'], $data['uniqueVisitors'])
        && is_numeric($data['totalViews'])
        && is_numeric($data['uniqueVisitors']);
}

// --- Export je Modul: liefert JSON-Dateiinhalte + Quellpfade der zugehörigen Bilder.

function data_transfer_export_motd(): array
{
    return [
        'files' => ['motd/motd.txt' => read_motd()],
        'images' => [],
        'count' => null,
    ];
}

function data_transfer_export_members(): array
{
    $list = load_members();
    $images = [];
    foreach ($list as $m) {
        if (empty($m['image'])) {
            continue;
        }
        $name = basename((string) $m['image']);
        $src = MEMBERS_IMAGES_DIR . '/' . $name;
        if (is_file($src)) {
            $images['members/images/' . $name] = $src;
        }
    }
    return [
        'files' => ['members/members.json' => json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        'images' => $images,
        'count' => count($list),
    ];
}

function data_transfer_export_expeditions(): array
{
    $list = load_expeditions();
    $images = [];
    foreach ($list as $e) {
        foreach (($e['images'] ?? []) as $img) {
            $name = basename((string) ($img['filename'] ?? ''));
            if ($name === '') {
                continue;
            }
            $src = EXPEDITIONS_IMAGES_DIR . '/' . $name;
            if (is_file($src)) {
                $images['expeditions/images/' . $name] = $src;
            }
        }
    }
    return [
        'files' => ['expeditions/expeditions.json' => json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        'images' => $images,
        'count' => count($list),
    ];
}

function data_transfer_export_accounts(): array
{
    $list = load_accounts();
    return [
        'files' => ['accounts/accounts.json' => json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        'images' => [],
        'count' => count($list),
    ];
}

function data_transfer_export_email_templates(): array
{
    $data = load_email_templates();
    return [
        'files' => ['email-templates/email-templates.json' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        'images' => [],
        'count' => count($data),
    ];
}

function data_transfer_export_navionics(): array
{
    $data = load_navionics();
    return [
        'files' => ['navionics/navionics.json' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        'images' => [],
        'count' => 1,
    ];
}

function data_transfer_export_role_permissions(): array
{
    $data = load_role_permissions();
    return [
        'files' => ['role-permissions/role-permissions.json' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        'images' => [],
        'count' => count($data),
    ];
}

// Nur die dauerhafte visitor-counter.json wird exportiert - die transiente
// visitor-counter-today.json (Tages-Hashes zum Entduplizieren) ist Arbeitszustand,
// rotiert ohnehin automatisch und gehört nicht ins Backup/die Übertragung.
function data_transfer_export_visitor_counter(): array
{
    $data = read_visitor_counter();
    return [
        'files' => ['visitor-counter/visitor-counter.json' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        'images' => [],
        'count' => null,
    ];
}

// consent-log ist ein Ordner vieler kleiner JSON-Dateien statt einer zentralen Liste -
// jede Datei wird als eigener Zip-Eintrag mitgenommen (wie Bilder bei anderen Modulen,
// nur ohne Bild-Validierung).
function data_transfer_export_consent_log(): array
{
    $images = [];
    if (is_dir(CONSENT_LOG_DIR)) {
        foreach (glob(CONSENT_LOG_DIR . '/*.json') ?: [] as $f) {
            if (is_file($f)) {
                $images['consent-log/' . basename($f)] = $f;
            }
        }
    }
    return [
        'files' => [],
        'images' => $images,
        'count' => count($images),
    ];
}

// --- Import je Modul: vollständiges Ersetzen (mit vorherigem Backup). Jedes Modul
// ist unabhängig - schlägt die Validierung eines Moduls fehl, bleiben die anderen
// unberührt (kein Alles-oder-nichts über Modulgrenzen hinweg).

function data_transfer_import_motd(ZipArchive $zip): array
{
    $idx = $zip->locateName('motd/motd.txt');
    if ($idx === false) {
        return ['ok' => false, 'summary' => 'MOTD: nicht im Archiv gefunden, übersprungen.'];
    }
    $text = $zip->getFromIndex($idx);
    if ($text === false) {
        return ['ok' => false, 'summary' => 'MOTD: konnte nicht gelesen werden.'];
    }
    if (data_transfer_backup_file('motd', motd_file())) {
        data_transfer_prune_backups('motd');
    }
    write_motd(trim(substr($text, 0, 500)));
    return ['ok' => true, 'summary' => 'MOTD importiert.'];
}

function data_transfer_import_members(ZipArchive $zip): array
{
    $idx = $zip->locateName('members/members.json');
    if ($idx === false) {
        return ['ok' => false, 'summary' => 'Mitglieder: Datei nicht im Archiv gefunden, übersprungen.'];
    }
    $json = $zip->getFromIndex($idx);
    $list = $json !== false ? json_decode($json, true) : null;
    if (!is_array($list) || !data_transfer_validate_members($list)) {
        return ['ok' => false, 'summary' => 'Mitglieder: Datei ungültig, Import abgebrochen.'];
    }

    $backedUp = data_transfer_backup_file('members', MEMBERS_DATA_FILE);
    $backedUp = data_transfer_backup_images_dir('members', MEMBERS_IMAGES_DIR) || $backedUp;
    if ($backedUp) {
        data_transfer_prune_backups('members');
    }

    if (is_dir(MEMBERS_IMAGES_DIR)) {
        foreach (glob(MEMBERS_IMAGES_DIR . '/*') ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
    $imported = 0;
    foreach ($list as $m) {
        if (empty($m['image'])) {
            continue;
        }
        $name = basename((string) $m['image']);
        $entryName = 'members/images/' . $name;
        if ($zip->locateName($entryName) !== false && data_transfer_extract_image($zip, $entryName, MEMBERS_IMAGES_DIR, $name)) {
            $imported++;
        }
    }

    save_members($list);
    return ['ok' => true, 'summary' => sprintf('Mitglieder importiert (%d Einträge, %d Bilder).', count($list), $imported)];
}

function data_transfer_import_expeditions(ZipArchive $zip): array
{
    $idx = $zip->locateName('expeditions/expeditions.json');
    if ($idx === false) {
        return ['ok' => false, 'summary' => 'Expeditionen: Datei nicht im Archiv gefunden, übersprungen.'];
    }
    $json = $zip->getFromIndex($idx);
    $list = $json !== false ? json_decode($json, true) : null;
    if (!is_array($list) || !data_transfer_validate_expeditions($list)) {
        return ['ok' => false, 'summary' => 'Expeditionen: Datei ungültig, Import abgebrochen.'];
    }

    $backedUp = data_transfer_backup_file('expeditions', EXPEDITIONS_DATA_FILE);
    $backedUp = data_transfer_backup_images_dir('expeditions', EXPEDITIONS_IMAGES_DIR) || $backedUp;
    if ($backedUp) {
        data_transfer_prune_backups('expeditions');
    }

    if (is_dir(EXPEDITIONS_IMAGES_DIR)) {
        foreach (glob(EXPEDITIONS_IMAGES_DIR . '/*') ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
    $imported = 0;
    foreach ($list as $e) {
        foreach (($e['images'] ?? []) as $img) {
            $name = basename((string) ($img['filename'] ?? ''));
            if ($name === '') {
                continue;
            }
            $entryName = 'expeditions/images/' . $name;
            if ($zip->locateName($entryName) !== false && data_transfer_extract_image($zip, $entryName, EXPEDITIONS_IMAGES_DIR, $name)) {
                $imported++;
            }
        }
    }

    save_expeditions($list);
    return ['ok' => true, 'summary' => sprintf('Expeditionen importiert (%d Einträge, %d Bilder).', count($list), $imported)];
}

function data_transfer_import_accounts(ZipArchive $zip): array
{
    $idx = $zip->locateName('accounts/accounts.json');
    if ($idx === false) {
        return ['ok' => false, 'summary' => 'Accounts: Datei nicht im Archiv gefunden, übersprungen.'];
    }
    $json = $zip->getFromIndex($idx);
    $list = $json !== false ? json_decode($json, true) : null;
    if (!is_array($list) || !data_transfer_validate_accounts($list)) {
        return ['ok' => false, 'summary' => 'Accounts: Datei ungültig, Import abgebrochen.'];
    }

    if (data_transfer_backup_file('accounts', ACCOUNTS_DATA_FILE)) {
        data_transfer_prune_backups('accounts');
    }

    save_accounts($list);
    return ['ok' => true, 'summary' => sprintf('Accounts importiert (%d Einträge).', count($list))];
}

function data_transfer_import_email_templates(ZipArchive $zip): array
{
    $idx = $zip->locateName('email-templates/email-templates.json');
    if ($idx === false) {
        return ['ok' => false, 'summary' => 'E-Mail-Templates: Datei nicht im Archiv gefunden, übersprungen.'];
    }
    $json = $zip->getFromIndex($idx);
    $data = $json !== false ? json_decode($json, true) : null;
    if (!is_array($data)) {
        return ['ok' => false, 'summary' => 'E-Mail-Templates: Datei ungültig, Import abgebrochen.'];
    }

    if (data_transfer_backup_file('email-templates', EMAIL_TEMPLATES_DATA_FILE)) {
        data_transfer_prune_backups('email-templates');
    }

    save_email_templates($data);
    return ['ok' => true, 'summary' => 'E-Mail-Templates importiert.'];
}

function data_transfer_import_navionics(ZipArchive $zip): array
{
    $idx = $zip->locateName('navionics/navionics.json');
    if ($idx === false) {
        return ['ok' => false, 'summary' => 'Navionics: Datei nicht im Archiv gefunden, übersprungen.'];
    }
    $json = $zip->getFromIndex($idx);
    $data = $json !== false ? json_decode($json, true) : null;
    if (!is_array($data)) {
        return ['ok' => false, 'summary' => 'Navionics: Datei ungültig, Import abgebrochen.'];
    }

    if (data_transfer_backup_file('navionics', navionics_data_file())) {
        data_transfer_prune_backups('navionics');
    }

    save_navionics($data);
    return ['ok' => true, 'summary' => 'Navionics-Zugangsdaten importiert.'];
}

function data_transfer_import_role_permissions(ZipArchive $zip): array
{
    $idx = $zip->locateName('role-permissions/role-permissions.json');
    if ($idx === false) {
        return ['ok' => false, 'summary' => 'Berechtigungen: Datei nicht im Archiv gefunden, übersprungen.'];
    }
    $json = $zip->getFromIndex($idx);
    $data = $json !== false ? json_decode($json, true) : null;
    if (!is_array($data) || !data_transfer_validate_role_permissions($data)) {
        return ['ok' => false, 'summary' => 'Berechtigungen: Datei ungültig, Import abgebrochen.'];
    }

    if (data_transfer_backup_file('role-permissions', ROLE_PERMISSIONS_DATA_FILE)) {
        data_transfer_prune_backups('role-permissions');
    }

    save_role_permissions($data);
    return ['ok' => true, 'summary' => 'Berechtigungen importiert.'];
}

function data_transfer_import_visitor_counter(ZipArchive $zip): array
{
    $idx = $zip->locateName('visitor-counter/visitor-counter.json');
    if ($idx === false) {
        return ['ok' => false, 'summary' => 'Besucherzähler: Datei nicht im Archiv gefunden, übersprungen.'];
    }
    $json = $zip->getFromIndex($idx);
    $data = $json !== false ? json_decode($json, true) : null;
    if (!is_array($data) || !data_transfer_validate_visitor_counter($data)) {
        return ['ok' => false, 'summary' => 'Besucherzähler: Datei ungültig, Import abgebrochen.'];
    }

    if (data_transfer_backup_file('visitor-counter', COUNTER_DATA_FILE)) {
        data_transfer_prune_backups('visitor-counter');
    }

    write_visitor_counter($data);
    return ['ok' => true, 'summary' => 'Besucherzähler importiert.'];
}

// Bewusst ADDITIV statt vollständig ersetzend: das Audit-Log darf durch einen Import
// keine bereits vorhandenen Nachweise verlieren. Vorhandene Dateien (gleicher
// Dateiname) werden nicht überschrieben, nur fehlende ergänzt.
function data_transfer_import_consent_log(ZipArchive $zip): array
{
    $imported = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false || !data_transfer_safe_zip_entry($name)) {
            continue;
        }
        if (!str_starts_with($name, 'consent-log/') || !str_ends_with($name, '.json')) {
            continue;
        }
        $filename = basename($name);
        if ($filename === '') {
            continue;
        }
        $target = CONSENT_LOG_DIR . '/' . $filename;
        if (is_file($target)) {
            continue;
        }
        $contents = $zip->getFromIndex($i);
        if ($contents === false || json_decode($contents, true) === null) {
            continue;
        }
        if (!is_dir(CONSENT_LOG_DIR)) {
            mkdir(CONSENT_LOG_DIR, 0755, true);
        }
        file_put_contents($target, $contents, LOCK_EX);
        $imported++;
    }
    return ['ok' => true, 'summary' => sprintf('Zustimmungs-Audit-Log: %d neue Einträge übernommen.', $imported)];
}

// --- Zentrale Registry.

function data_transfer_modules(): array
{
    return [
        'motd' => [
            'label' => 'Nachricht des Tages',
            'export' => 'data_transfer_export_motd',
            'import' => 'data_transfer_import_motd',
        ],
        'members' => [
            'label' => 'Mitglieder',
            'export' => 'data_transfer_export_members',
            'import' => 'data_transfer_import_members',
        ],
        'expeditions' => [
            'label' => 'Expeditionen',
            'export' => 'data_transfer_export_expeditions',
            'import' => 'data_transfer_import_expeditions',
        ],
        'accounts' => [
            'label' => 'Accounts (Zugangsdaten, sensibel)',
            'export' => 'data_transfer_export_accounts',
            'import' => 'data_transfer_import_accounts',
        ],
        'email-templates' => [
            'label' => 'E-Mail-Templates',
            'export' => 'data_transfer_export_email_templates',
            'import' => 'data_transfer_import_email_templates',
        ],
        'navionics' => [
            'label' => 'Navionics Zugangsdaten',
            'export' => 'data_transfer_export_navionics',
            'import' => 'data_transfer_import_navionics',
        ],
        'role-permissions' => [
            'label' => 'Rollen-Berechtigungen',
            'export' => 'data_transfer_export_role_permissions',
            'import' => 'data_transfer_import_role_permissions',
        ],
        'consent-log' => [
            'label' => 'Zustimmungs-Audit-Log',
            'export' => 'data_transfer_export_consent_log',
            'import' => 'data_transfer_import_consent_log',
        ],
        'visitor-counter' => [
            'label' => 'Besucherzähler',
            'export' => 'data_transfer_export_visitor_counter',
            'import' => 'data_transfer_import_visitor_counter',
        ],
        'imap-config' => [
            'label' => 'IMAP-Konfiguration (E-Mail)',
            'export' => 'data_transfer_export_imap_config',
            'import' => 'data_transfer_import_imap_config',
        ],
        'ai-config' => [
            'label' => 'KI-Konfiguration (API-Key, sensibel)',
            'export' => 'data_transfer_export_ai_config',
            'import' => 'data_transfer_import_ai_config',
        ],
    ];
}

function data_transfer_build_manifest(array $moduleIds): array
{
    return [
        'version' => DATA_TRANSFER_MANIFEST_VERSION,
        'exportedAt' => date('c'),
        'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
        'modules' => array_values($moduleIds),
    ];
}

// IMAP-Konfiguration Export/Import
function data_transfer_export_imap_config(): array
{
    require_once __DIR__ . '/../mitglieder/imap-lib.php';
    $config = load_imap_config();
    return [
        'files' => [
            'imap-config/imap-config.json' => json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        ],
    ];
}

function data_transfer_import_imap_config(array $data): void
{
    require_once __DIR__ . '/../mitglieder/imap-lib.php';
    if (isset($data['files']['imap-config/imap-config.json'])) {
        $config = json_decode($data['files']['imap-config/imap-config.json'], true);
        if (is_array($config)) {
            save_imap_config($config);
        }
    }
}

// KI-Konfiguration Export/Import (enthaelt den API-Key - sensibel behandeln)
function data_transfer_export_ai_config(): array
{
    require_once __DIR__ . '/../mitglieder/ai-lib.php';
    $config = load_ai_config();
    return [
        'files' => [
            'ai-config/ai-config.json' => json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
        ],
    ];
}

function data_transfer_import_ai_config(array $data): void
{
    require_once __DIR__ . '/../mitglieder/ai-lib.php';
    if (isset($data['files']['ai-config/ai-config.json'])) {
        $config = json_decode($data['files']['ai-config/ai-config.json'], true);
        if (is_array($config)) {
            save_ai_config($config);
        }
    }
}
