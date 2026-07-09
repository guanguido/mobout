<?php
// Mitglied bearbeitet die eigenen, nicht-administrativen Profilfelder (Kurztext,
// kleines Icon nach dem Text, Foto). Name und Rolle bleiben bewusst außen vor -
// die bearbeitet weiterhin ausschließlich der Admin. Die memberId kommt
// ausschließlich aus der Session (member_current_id()), nie aus einem
// Request-Parameter - ein Mitglied kann so technisch nie ein fremdes Profil
// erreichen. Bild-Validierung wie beim Admin-CRUD (admin/members-save.php).
declare(strict_types=1);

require __DIR__ . '/member-auth.php';
require_member();
member_check_csrf();
member_enforce_password_change();

require_once __DIR__ . '/members-lib.php';

const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
const ALLOWED_EXTENSIONS = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

function store_own_image(string $id, ?string $existing): ?string
{
    if (empty($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $existing;
    }
    $tmpPath = $_FILES['image']['tmp_name'];
    $size = (int) $_FILES['image']['size'];
    if ($size <= 0 || $size > MAX_IMAGE_BYTES) {
        return $existing;
    }
    $ext = strtolower(pathinfo((string) $_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!isset(ALLOWED_EXTENSIONS[$ext])) {
        return $existing;
    }
    if (getimagesize($tmpPath) === false) {
        return $existing;
    }
    if (!is_dir(MEMBERS_IMAGES_DIR)) {
        mkdir(MEMBERS_IMAGES_DIR, 0755, true);
    }
    $filename = $id . '.' . $ext;
    if (!move_uploaded_file($tmpPath, MEMBERS_IMAGES_DIR . '/' . $filename)) {
        return $existing;
    }
    if ($existing && $existing !== $filename) {
        @unlink(MEMBERS_IMAGES_DIR . '/' . basename($existing));
    }
    return $filename;
}

$memberId = member_current_id();
$list = load_members();
$found = false;

foreach ($list as &$entry) {
    if ((string) ($entry['id'] ?? '') !== $memberId) {
        continue;
    }
    $found = true;
    $text = trim((string) ($_POST['text'] ?? ($entry['text'] ?? '')));
    $emoji = trim((string) ($_POST['emoji'] ?? ($entry['emoji'] ?? '')));
    $entry['text'] = substr($text, 0, 600);
    $entry['emoji'] = mb_substr($emoji, 0, 16);
    $entry['image'] = store_own_image($entry['id'], $entry['image'] ?? null);
    $entry['updatedAt'] = date('c');
    break;
}
unset($entry);

if ($found) {
    save_members($list);
}

header('Location: index.php?profilemsg=' . ($found ? 'saved' : 'error') . '#konto-bereich');
exit;
