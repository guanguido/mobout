<?php
// Import-Endpunkt der Datenübertragung: nimmt ein zuvor exportiertes ZIP-Bundle
// entgegen und ersetzt die ausgewählten Datentypen (MOTD/Mitglieder/Expeditionen)
// vollständig - mit automatischem Backup des Vorzustands. Nur für eingeloggte Admins.
require __DIR__ . '/auth.php';
require __DIR__ . '/data-transfer-lib.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
admin_check_csrf();

function done(string $msg, string $info = ''): void
{
    $url = 'index.php?msg=' . urlencode($msg);
    if ($info !== '') {
        $url .= '&info=' . urlencode($info);
    }
    header('Location: ' . $url . '#data-bereich');
    exit;
}

if (!class_exists('ZipArchive')) {
    done('import-error', 'Server unterstützt kein ZIP (ZipArchive-Erweiterung fehlt).');
}

if (empty($_FILES['bundle']) || ($_FILES['bundle']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    done('import-error', 'Keine Datei hochgeladen.');
}

$size = (int) $_FILES['bundle']['size'];
if ($size <= 0 || $size > DATA_TRANSFER_MAX_ZIP_BYTES) {
    done('import-error', 'Datei leer oder zu groß (max. 50 MB).');
}

$tmpPath = $_FILES['bundle']['tmp_name'];
$zip = new ZipArchive();
if ($zip->open($tmpPath) !== true) {
    done('import-error', 'Ungültiges ZIP-Archiv.');
}

// Zip-Slip-Schutz: jeder Eintragsname muss ein sicherer relativer Pfad sein.
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if ($name === false || !data_transfer_safe_zip_entry($name)) {
        $zip->close();
        done('import-error', 'Archiv enthält unsichere Pfade.');
    }
}

$manifestIdx = $zip->locateName('manifest.json');
if ($manifestIdx === false) {
    $zip->close();
    done('import-error', 'manifest.json fehlt im Archiv - kein gültiges Datenübertragungs-Bundle.');
}
$manifest = json_decode((string) $zip->getFromIndex($manifestIdx), true);
if (!is_array($manifest) || (int) ($manifest['version'] ?? 0) !== DATA_TRANSFER_MANIFEST_VERSION) {
    $zip->close();
    done('import-error', 'Unbekanntes oder unpassendes Manifest-Format.');
}

$modules = data_transfer_modules();
$requested = is_array($_POST['modules'] ?? null) ? $_POST['modules'] : [];
$selected = array_values(array_intersect(array_keys($modules), $requested));
if (empty($selected)) {
    $zip->close();
    done('import-error', 'Kein Datentyp zum Importieren ausgewählt.');
}

$summaries = [];
$anyFailed = false;
foreach ($selected as $id) {
    $result = call_user_func($modules[$id]['import'], $zip);
    $summaries[] = $result['summary'];
    if (empty($result['ok'])) {
        $anyFailed = true;
    }
}
$zip->close();

done($anyFailed ? 'import-error' : 'import-ok', implode(' ', $summaries));
