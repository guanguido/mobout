<?php
// Schreib-Endpunkt für die editierbaren E-Mail-Templates (Betreff + Text). Nur für
// eingeloggte Admins. Iteriert generisch über email_template_defs(), damit neue
// Templates künftig ohne Änderung an diesem Endpunkt funktionieren.
require __DIR__ . '/auth.php';
require __DIR__ . '/../mitglieder/email-templates-lib.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
admin_check_csrf();

$posted = $_POST['templates'] ?? [];
$clean = [];
foreach (email_template_defs() as $key => $def) {
    $clean[$key] = [
        'subject' => trim((string) ($posted[$key]['subject'] ?? '')),
        'body' => (string) ($posted[$key]['body'] ?? ''),
    ];
}
save_email_templates($clean);

header('Location: index.php?msg=templates-saved#email-templates-bereich');
exit;
