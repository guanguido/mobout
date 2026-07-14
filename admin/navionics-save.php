<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/auth.php';
require_once __DIR__ . '/../mitglieder/navionics-lib.php';

require_admin();
admin_check_csrf();

$data = [];
foreach (navionics_field_defs() as $key => $label) {
    $data[$key] = $_POST['navionics'][$key] ?? '';
}
save_navionics($data);

header('Location: index.php?msg=navionics-saved#navionics-bereich');
exit;
