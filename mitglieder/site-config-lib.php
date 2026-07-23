<?php

function site_config_field_defs(): array
{
    return [
        'email' => 'E-Mail',
        'phone' => 'Telefon',
        'instagram' => 'Instagram-Link',
    ];
}

function site_config_data_file(): string
{
    return __DIR__ . '/data/site-config.json';
}

function site_config_seed_file(): string
{
    return __DIR__ . '/site-config-seed.json';
}

function load_site_config(): array
{
    $file = site_config_data_file();
    if (is_file($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            return $data;
        }
    }

    $seed_file = site_config_seed_file();
    if (is_file($seed_file)) {
        $data = json_decode(file_get_contents($seed_file), true);
        if (is_array($data)) {
            return $data;
        }
    }

    $defaults = [];
    foreach (site_config_field_defs() as $key => $label) {
        $defaults[$key] = '';
    }
    return $defaults;
}

function save_site_config(array $data): void
{
    $dir = dirname(site_config_data_file());
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fields = site_config_field_defs();
    $sanitized = [];
    foreach ($fields as $key => $label) {
        $sanitized[$key] = isset($data[$key]) ? trim((string) $data[$key]) : '';
    }

    file_put_contents(
        site_config_data_file(),
        json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}
