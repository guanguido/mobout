<?php
// Schreib-Endpunkt für die Mitglieder-Verwaltung. Nur für eingeloggte Admins,
// alle Mutationen über einen action-Parameter (create/update/delete/delete-image).
// Analog zu mitglieder/expeditions-save.php, aber EIN Foto pro Mitglied.
require __DIR__ . '/auth.php';
require __DIR__ . '/../mitglieder/members-lib.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
admin_check_csrf();

const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
const ALLOWED_EXTENSIONS = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

function done(string $status = 'ok'): void
{
    header('Location: index.php?msg=' . urlencode($status) . '#mitglieder-bereich');
    exit;
}

function normalize_role(string $role, string $fallback = 'team'): string
{
    return in_array($role, MEMBER_ROLES, true) ? $role : $fallback;
}

// Speichert ein hochgeladenes Foto (Feld "image") als <id>.<ext> in data/members-images/.
// Gibt den neuen Dateinamen zurück, sonst den bisherigen. Löscht ein altes Foto nur
// aus dem data-Verzeichnis (Seed-Fotos in members-seed-images/ bleiben git-getrackt).
function store_member_image(string $id, ?string $existing): ?string
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

$action = (string) ($_POST['action'] ?? '');
$list = load_members();

if ($action === 'create') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $text = trim((string) ($_POST['text'] ?? ''));
    $icon = trim((string) ($_POST['icon'] ?? ''));
    $emoji = trim((string) ($_POST['emoji'] ?? ''));
    $role = normalize_role((string) ($_POST['role'] ?? ''));

    if ($name === '') {
        done('error');
    }

    $id = unique_member_id($list, $name);
    $now = date('c');
    $entry = [
        'id' => $id,
        'name' => substr($name, 0, 80),
        'role' => $role,
        'text' => substr($text, 0, 600),
        'icon' => mb_substr($icon !== '' ? $icon : mb_substr($name, 0, 1), 0, 4),
        'emoji' => mb_substr($emoji, 0, 16),
        'image' => store_member_image($id, null),
        'createdAt' => $now,
        'updatedAt' => $now,
    ];
    $list[] = $entry;
    save_members($list);
    done('created');
}

if ($action === 'update') {
    $id = (string) ($_POST['id'] ?? '');
    $found = false;
    foreach ($list as &$entry) {
        if ($entry['id'] !== $id) {
            continue;
        }
        $found = true;
        $name = trim((string) ($_POST['name'] ?? $entry['name']));
        $text = trim((string) ($_POST['text'] ?? ($entry['text'] ?? '')));
        $icon = trim((string) ($_POST['icon'] ?? ($entry['icon'] ?? '')));
        $emoji = trim((string) ($_POST['emoji'] ?? ($entry['emoji'] ?? '')));

        if ($name !== '') {
            $entry['name'] = substr($name, 0, 80);
        }
        $entry['role'] = normalize_role((string) ($_POST['role'] ?? ''), $entry['role'] ?? 'team');
        $entry['text'] = substr($text, 0, 600);
        $entry['icon'] = mb_substr($icon !== '' ? $icon : mb_substr($entry['name'], 0, 1), 0, 4);
        $entry['emoji'] = mb_substr($emoji, 0, 16);
        $entry['image'] = store_member_image($entry['id'], $entry['image'] ?? null);
        $entry['updatedAt'] = date('c');
        break;
    }
    unset($entry);
    save_members($list);
    done($found ? 'updated' : 'error');
}

if ($action === 'delete') {
    $id = (string) ($_POST['id'] ?? '');
    foreach ($list as $entry) {
        if ($entry['id'] === $id && !empty($entry['image'])) {
            @unlink(MEMBERS_IMAGES_DIR . '/' . basename((string) $entry['image']));
        }
    }
    $list = array_values(array_filter($list, static fn($entry) => $entry['id'] !== $id));
    save_members($list);
    done('deleted');
}

if ($action === 'delete-image') {
    $id = (string) ($_POST['id'] ?? '');
    foreach ($list as &$entry) {
        if ($entry['id'] !== $id) {
            continue;
        }
        if (!empty($entry['image'])) {
            @unlink(MEMBERS_IMAGES_DIR . '/' . basename((string) $entry['image']));
        }
        $entry['image'] = null;
        $entry['updatedAt'] = date('c');
        break;
    }
    unset($entry);
    save_members($list);
    done('image-removed');
}

done();
