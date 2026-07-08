<?php
// Öffentlicher Lese-Endpunkt für die Mitgliederliste als JSON (kein Basic Auth).
// Liest serverseitig vom Dateisystem - funktioniert trotz Apache-Auth auf
// mitglieder/, analog zu expeditions.php / motd.php.
require __DIR__ . '/mitglieder/members-lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(load_members(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
