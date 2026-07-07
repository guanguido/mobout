<?php
// Öffentlicher Bild-Streaming-Endpunkt für Expeditionsfotos, die serverseitig
// unter mitglieder/data/expeditions-images/ liegen (durch Apache Basic Auth
// geschützt). Dieses Skript liegt außerhalb von mitglieder/ und liefert die
// Datei wie motd.php per reinem Dateisystemzugriff aus, umgeht damit die
// Apache-Auth auf mitglieder/ - analog zum motd.php-Mechanismus.

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

$imagesDir = realpath(__DIR__ . '/mitglieder/data/expeditions-images');
if ($imagesDir === false) {
    http_response_code(404);
    exit;
}

$path = realpath($imagesDir . '/' . $requested);
if ($path === false || !str_starts_with($path, $imagesDir . DIRECTORY_SEPARATOR) || !is_file($path)) {
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
