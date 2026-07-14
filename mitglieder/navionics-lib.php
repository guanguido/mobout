<?php

function navionics_field_defs(): array
{
    return [
        'login' => 'Login / E-Mail',
        'password' => 'Passwort',
        'app' => 'App',
        'maps' => 'Karten',
        'expires' => 'Läuft ab',
    ];
}

function navionics_data_file(): string
{
    return __DIR__ . '/data/navionics.json';
}

function navionics_seed_file(): string
{
    return __DIR__ . '/navionics-seed.json';
}

function load_navionics(): array
{
    $file = navionics_data_file();
    if (is_file($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            return $data;
        }
    }

    $seed_file = navionics_seed_file();
    if (is_file($seed_file)) {
        $data = json_decode(file_get_contents($seed_file), true);
        if (is_array($data)) {
            return $data;
        }
    }

    $defaults = [];
    foreach (navionics_field_defs() as $key => $label) {
        $defaults[$key] = '';
    }
    return $defaults;
}

function save_navionics(array $data): void
{
    $dir = dirname(navionics_data_file());
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fields = navionics_field_defs();
    $sanitized = [];
    foreach ($fields as $key => $label) {
        $sanitized[$key] = isset($data[$key]) ? trim((string) $data[$key]) : '';
    }

    file_put_contents(
        navionics_data_file(),
        json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}
