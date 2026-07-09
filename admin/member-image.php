<?php
// Admin-geschützter Bild-Streaming-Endpunkt für Mitglieder-Fotos, für die Foto-
// Vorschau in admin/index.php. Bewusst getrennt vom öffentlichen member-image.php
// (Repo-Root): dort wird ein Foto nur bei erteilter Zustimmung ausgeliefert
// (Consent-Filter), aber der Admin muss unabhängig von der Zustimmung IMMER alle
// Fotos sehen können, um sie zu verwalten. Gleicher Pfad-Traversal-Schutz wie beim
// öffentlichen Pendant (basename()-Check + realpath()-Containment), nur ohne
// Consent-Prüfung und dafür mit Admin-Session-Pflicht.
require __DIR__ . '/auth.php';

require_admin();

$mimeByExtension = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];

$requested = (string) ($_GET['f'] ?? '');
if ($requested === '' || basename($requested) !== $requested) {
    http_response_code(404);
    exit;
}

$searchDirs = [
    realpath(__DIR__ . '/../mitglieder/data/members-images'),
    realpath(__DIR__ . '/../mitglieder/members-seed-images'),
];

$path = false;
foreach ($searchDirs as $dir) {
    if ($dir === false) {
        continue;
    }
    $candidate = realpath($dir . '/' . $requested);
    if ($candidate !== false && str_starts_with($candidate, $dir . DIRECTORY_SEPARATOR) && is_file($candidate)) {
        $path = $candidate;
        break;
    }
}

if ($path === false) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if (!isset($mimeByExtension[$ext])) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mimeByExtension[$ext]);
header('Cache-Control: private, no-store');
readfile($path);
