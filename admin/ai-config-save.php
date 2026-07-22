<?php
// Speichert die KI-Konfiguration (Aktiv-Schalter, API-Key, Modell). Session- + CSRF-
// geschuetzt, spiegelt admin/imap-config-save.php.
//
// Aktiv-Schalter ist bewusst vom Key entkoppelt (Kostenkontrolle): Man kann den Key
// hinterlegen und die Funktion trotzdem ausgeschaltet lassen. Beim AKTIVIEREN wird die
// Verbindung geprueft - schlaegt sie fehl, wird die Konfiguration trotzdem gespeichert
// (Key bleibt erhalten), aber deaktiviert, statt sie ganz abzulehnen.
require __DIR__ . '/auth.php';
require_admin();
require_once __DIR__ . '/../mitglieder/ai-lib.php';

admin_check_csrf();

$enabled = !empty($_POST['enabled']);
$apiKey = trim((string) ($_POST['api_key'] ?? ''));
$model = trim((string) ($_POST['model'] ?? ''));
if ($model === '') {
    $model = AI_DEFAULT_MODEL;
}

// Ohne Key keine Aktivierung moeglich.
if ($apiKey === '') {
    $enabled = false;
}

// Nur beim Aktivieren die Verbindung testen (spart einen Aufruf beim reinen Deaktivieren).
if ($enabled) {
    $test = test_ai_connection($apiKey, $model);
    if (empty($test['ok'])) {
        save_ai_config(['enabled' => false, 'api_key' => $apiKey, 'model' => $model]);
        header('Location: index.php?msg=ai-config-testfail#ai-config-bereich');
        exit;
    }
}

save_ai_config(['enabled' => $enabled, 'api_key' => $apiKey, 'model' => $model]);
header('Location: index.php?msg=ai-config-saved#ai-config-bereich');
exit;
