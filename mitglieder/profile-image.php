<?php
// Serviert ausschließlich das eigene Foto des eingeloggten Mitglieds - für die
// Profil-Vorschau in der Konto-Sektion. Bewusst KEIN f-Parameter: der Dateiname
// wird ausschließlich aus dem eigenen Mitglied-Datensatz (Session-memberId)
// abgeleitet, damit über diesen Endpunkt niemals das Foto einer anderen Person
// abrufbar ist. Getrennt vom öffentlichen member-image.php (das den Consent-Filter
// hat - ein Mitglied ohne eigene Zustimmung könnte sein Foto sonst nicht mal selbst
// sehen) und von admin/member-image.php (admin-geschützt, beliebiges Mitglied).
require __DIR__ . '/member-auth.php';
require_member();
require_once __DIR__ . '/members-lib.php';

$mimeByExtension = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];

$memberId = member_current_id();
$imageName = null;
foreach (load_members() as $m) {
    if ((string) ($m['id'] ?? '') === $memberId) {
        $imageName = $m['image'] ?? null;
        break;
    }
}

if (empty($imageName)) {
    http_response_code(404);
    exit;
}

$requested = basename((string) $imageName);
$searchDirs = [
    realpath(__DIR__ . '/data/members-images'),
    realpath(__DIR__ . '/members-seed-images'),
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
