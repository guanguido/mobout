<?php
// Gemeinsame Lese-/Schreibfunktionen für die Expeditionen-Verwaltung.
// Solange mitglieder/data/expeditions.json noch nicht existiert, liefert
// load_expeditions() den Ausgangsbestand aus der git-getrackten Seed-Datei
// (expeditions-seed.json) - so brauchen frische Staging-/Produktionsumgebungen
// keinen manuellen Zwischenschritt. Sobald einmal gespeichert wurde, ist die
// Seed-Datei irrelevant für den laufenden Betrieb.

define('EXPEDITIONS_DATA_FILE', __DIR__ . '/data/expeditions.json');
define('EXPEDITIONS_SEED_FILE', __DIR__ . '/expeditions-seed.json');
define('EXPEDITIONS_IMAGES_DIR', __DIR__ . '/data/expeditions-images');

function load_expeditions(): array
{
    $file = is_file(EXPEDITIONS_DATA_FILE) ? EXPEDITIONS_DATA_FILE : EXPEDITIONS_SEED_FILE;
    if (!is_file($file)) {
        return [];
    }
    $list = json_decode(file_get_contents($file), true);
    return is_array($list) ? $list : [];
}

function save_expeditions(array $list): void
{
    usort($list, static fn($a, $b) => ($a['year'] ?? 0) <=> ($b['year'] ?? 0));
    $dataDir = dirname(EXPEDITIONS_DATA_FILE);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    file_put_contents(EXPEDITIONS_DATA_FILE, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function expedition_slug(int $year, string $location): string
{
    $base = strtolower($location);
    $base = preg_replace('/[^a-z0-9]+/u', '-', $base) ?? '';
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'expedition';
    }
    return 'exp-' . $year . '-' . $base;
}

function unique_expedition_id(array $list, int $year, string $location): string
{
    $base = expedition_slug($year, $location);
    $id = $base;
    $suffix = 2;
    $existingIds = array_column($list, 'id');
    while (in_array($id, $existingIds, true)) {
        $id = $base . '-' . $suffix;
        $suffix++;
    }
    return $id;
}
