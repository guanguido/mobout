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
