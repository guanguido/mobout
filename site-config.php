<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/mitglieder/site-config-lib.php';

echo json_encode(load_site_config(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
