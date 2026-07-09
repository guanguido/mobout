<?php
// Öffentlicher Lese-Endpunkt für die Mitgliederliste als JSON (kein Basic Auth).
// Liest serverseitig vom Dateisystem - funktioniert trotz Apache-Auth auf
// mitglieder/, analog zu expeditions.php / motd.php.
//
// Zeigt NUR Mitglieder, die der öffentlichen Anzeige selbst (oder per
// Bestands-Zustimmung durch den Admin) zugestimmt haben (consentGiven === true).
// Zusätzlich eine Feld-Whitelist, damit interne Felder (Zustimmungs-Metadaten)
// nie öffentlich sichtbar werden.
require __DIR__ . '/mitglieder/members-lib.php';

$public = array_map(
    static fn(array $m): array => [
        'id' => $m['id'],
        'name' => $m['name'],
        'role' => $m['role'],
        'text' => $m['text'],
        'icon' => $m['icon'],
        'emoji' => $m['emoji'],
        'image' => $m['image'],
    ],
    array_values(array_filter(load_members(), static fn(array $m): bool => !empty($m['consentGiven'])))
);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($public, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
