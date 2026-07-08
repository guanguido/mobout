<?php
// Öffentlicher Bild-Streaming-Endpunkt für Mitglieder-Fotos (kein Basic Auth),
// analog zu expedition-image.php. Sucht zuerst in mitglieder/data/members-images/
// (vom Admin hochgeladene Fotos) und fällt dann auf mitglieder/members-seed-images/
// zurück (git-getrackte Startfotos) - so werden Seed-Fotos auch ohne vorheriges
// Speichern ausgeliefert, analog zum Seed-Fallback in load_members().
// Strikter Pfad-Traversal-Schutz: basename()-Check + realpath()-Containment.

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
    realpath(__DIR__ . '/mitglieder/data/members-images'),
    realpath(__DIR__ . '/mitglieder/members-seed-images'),
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
header('Cache-Control: public, max-age=86400');
readfile($path);
