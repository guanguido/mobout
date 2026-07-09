<?php
require __DIR__ . '/expeditions-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Schutz früher über Apache Basic Auth, jetzt über die PHP-Session (member-auth.php).
// Wichtig wegen der Datei-Uploads: CSRF-Token verpflichtend.
require __DIR__ . '/member-auth.php';
require_member();
member_check_csrf();

const MAX_IMAGES = 8;
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
const ALLOWED_EXTENSIONS = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

function done(): void
{
    header('Location: index.php#expeditionen-bereich');
    exit;
}

function store_uploaded_images(string $id, array $existingImages): array
{
    $images = $existingImages;
    if (empty($_FILES['images']) || !is_array($_FILES['images']['name'] ?? null)) {
        return $images;
    }

    if (!is_dir(EXPEDITIONS_IMAGES_DIR)) {
        mkdir(EXPEDITIONS_IMAGES_DIR, 0755, true);
    }

    $nextIndex = count($images) + 1;
    $count = count($_FILES['images']['name']);
    for ($i = 0; $i < $count; $i++) {
        if (count($images) >= MAX_IMAGES) {
            break;
        }
        if (($_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        $tmpPath = $_FILES['images']['tmp_name'][$i];
        $size = (int) $_FILES['images']['size'][$i];
        if ($size <= 0 || $size > MAX_IMAGE_BYTES) {
            continue;
        }
        $originalName = (string) $_FILES['images']['name'][$i];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(ALLOWED_EXTENSIONS[$ext])) {
            continue;
        }
        if (getimagesize($tmpPath) === false) {
            continue;
        }

        $filename = $id . '-' . $nextIndex . '.' . $ext;
        if (move_uploaded_file($tmpPath, EXPEDITIONS_IMAGES_DIR . '/' . $filename)) {
            $images[] = ['filename' => $filename, 'originalName' => $originalName];
            $nextIndex++;
        }
    }

    return $images;
}

function validate_instagram_url(string $url): bool
{
    if ($url === '') {
        return true;
    }
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && str_starts_with($url, 'https://www.instagram.com/');
}

$action = (string) ($_POST['action'] ?? '');
$list = load_expeditions();

if ($action === 'create') {
    $year = (int) ($_POST['year'] ?? 0);
    $location = trim((string) ($_POST['location'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $instagramUrl = trim((string) ($_POST['instagramUrl'] ?? ''));
    $instagramLabel = trim((string) ($_POST['instagramLabel'] ?? ''));
    $flagEmoji = trim((string) ($_POST['flagEmoji'] ?? '')) ?: '🌊';
    $highlight = isset($_POST['highlight']);

    if ($year < 2000 || $year > 2100 || $location === '' || $description === '' || !validate_instagram_url($instagramUrl)) {
        done();
    }

    $id = unique_expedition_id($list, $year, $location);
    $now = date('c');
    $entry = [
        'id' => $id,
        'year' => $year,
        'location' => substr($location, 0, 120),
        'description' => substr($description, 0, 1000),
        'flagEmoji' => mb_substr($flagEmoji, 0, 8),
        'highlight' => $highlight,
        'instagramUrl' => $instagramUrl,
        'instagramLabel' => substr($instagramLabel, 0, 80),
        'images' => store_uploaded_images($id, []),
        'createdAt' => $now,
        'updatedAt' => $now,
    ];
    $list[] = $entry;
    save_expeditions($list);
    done();
}

if ($action === 'update') {
    $id = (string) ($_POST['id'] ?? '');
    foreach ($list as &$entry) {
        if ($entry['id'] !== $id) {
            continue;
        }
        $year = (int) ($_POST['year'] ?? $entry['year']);
        $location = trim((string) ($_POST['location'] ?? $entry['location']));
        $description = trim((string) ($_POST['description'] ?? $entry['description']));
        $instagramUrl = trim((string) ($_POST['instagramUrl'] ?? $entry['instagramUrl']));
        $instagramLabel = trim((string) ($_POST['instagramLabel'] ?? $entry['instagramLabel']));
        $flagEmoji = trim((string) ($_POST['flagEmoji'] ?? $entry['flagEmoji']));

        if ($year >= 2000 && $year <= 2100) {
            $entry['year'] = $year;
        }
        if ($location !== '') {
            $entry['location'] = substr($location, 0, 120);
        }
        if ($description !== '') {
            $entry['description'] = substr($description, 0, 1000);
        }
        if (validate_instagram_url($instagramUrl)) {
            $entry['instagramUrl'] = $instagramUrl;
        }
        $entry['instagramLabel'] = substr($instagramLabel, 0, 80);
        if ($flagEmoji !== '') {
            $entry['flagEmoji'] = mb_substr($flagEmoji, 0, 8);
        }
        $entry['highlight'] = isset($_POST['highlight']);
        $entry['images'] = store_uploaded_images($entry['id'], $entry['images']);
        $entry['updatedAt'] = date('c');
        break;
    }
    unset($entry);
    save_expeditions($list);
    done();
}

if ($action === 'delete') {
    $id = (string) ($_POST['id'] ?? '');
    foreach ($list as $entry) {
        if ($entry['id'] === $id) {
            foreach ($entry['images'] as $image) {
                @unlink(EXPEDITIONS_IMAGES_DIR . '/' . basename($image['filename']));
            }
        }
    }
    $list = array_values(array_filter($list, static fn($entry) => $entry['id'] !== $id));
    save_expeditions($list);
    done();
}

if ($action === 'delete-image') {
    $id = (string) ($_POST['id'] ?? '');
    $filename = basename((string) ($_POST['filename'] ?? ''));
    foreach ($list as &$entry) {
        if ($entry['id'] !== $id) {
            continue;
        }
        $before = count($entry['images']);
        $entry['images'] = array_values(array_filter(
            $entry['images'],
            static fn($image) => $image['filename'] !== $filename
        ));
        if (count($entry['images']) < $before) {
            @unlink(EXPEDITIONS_IMAGES_DIR . '/' . $filename);
            $entry['updatedAt'] = date('c');
        }
        break;
    }
    unset($entry);
    save_expeditions($list);
    done();
}

done();
