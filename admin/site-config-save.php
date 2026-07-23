<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/auth.php';
require_once __DIR__ . '/../mitglieder/site-config-lib.php';

require_admin();
admin_check_csrf();

$data = [];
foreach (site_config_field_defs() as $key => $label) {
    $data[$key] = $_POST['site_config'][$key] ?? '';
}
save_site_config($data);

header('Location: index.php?msg=site-config-saved#kontaktdaten-bereich');
exit;
