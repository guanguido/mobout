<?php
// Löscht eine einzelne, manuell oder automatisch angelegte Sicherung unter
// mitglieder/data/backups/<Zeitstempel>/. Nur für eingeloggte Admins.
require __DIR__ . '/auth.php';
require __DIR__ . '/data-transfer-lib.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
admin_check_csrf();

function done(string $msg): void
{
    header('Location: index.php?msg=' . urlencode($msg) . '#data-bereich');
    exit;
}

$timestamp = basename((string) ($_POST['timestamp'] ?? ''));
// Erwartetes Format wie data_transfer_backup_timestamp(): Y-m-d_His.
if (!preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', $timestamp)) {
    done('backup-delete-error');
}

$dir = DATA_TRANSFER_BACKUP_DIR . '/' . $timestamp;
$realDir = realpath($dir);
$realBackupRoot = realpath(DATA_TRANSFER_BACKUP_DIR);
if ($realDir === false || $realBackupRoot === false || !str_starts_with($realDir, $realBackupRoot)) {
    done('backup-delete-error');
}

data_transfer_rrmdir($dir);
done('backup-deleted');
