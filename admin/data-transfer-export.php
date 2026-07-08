<?php
// Export-Endpunkt der Datenübertragung: bündelt MOTD, Mitglieder und Expeditionen
// (inkl. Bilder) als ein ZIP-Archiv zum Download. Nur für eingeloggte Admins.
require __DIR__ . '/auth.php';
require __DIR__ . '/data-transfer-lib.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
admin_check_csrf();

if (!class_exists('ZipArchive')) {
    header('Location: index.php?msg=' . urlencode('export-error') . '#data-bereich');
    exit;
}

$modules = data_transfer_modules();

$tmpZip = tempnam(sys_get_temp_dir(), 'mbe');
$zip = new ZipArchive();
if ($tmpZip === false || $zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
    if ($tmpZip !== false) {
        @unlink($tmpZip);
    }
    header('Location: index.php?msg=' . urlencode('export-error') . '#data-bereich');
    exit;
}

$included = [];
foreach ($modules as $id => $module) {
    $result = call_user_func($module['export']);
    foreach (($result['files'] ?? []) as $path => $content) {
        $zip->addFromString($path, (string) $content);
    }
    foreach (($result['images'] ?? []) as $zipPath => $srcFile) {
        $zip->addFile($srcFile, $zipPath);
    }
    $included[] = $id;
}
$zip->addFromString(
    'manifest.json',
    json_encode(data_transfer_build_manifest($included), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
$zip->close();

$hostSlug = preg_replace('/[^a-z0-9.-]+/i', '-', (string) ($_SERVER['HTTP_HOST'] ?? 'export'));
$filename = 'mobout-data-' . $hostSlug . '-' . date('Ymd-His') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . (string) filesize($tmpZip));
readfile($tmpZip);
@unlink($tmpZip);
exit;
