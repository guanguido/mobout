<?php
// Schreib-Endpunkt für die Berechtigungs-Matrix (Rolle x Recht). Nur für
// eingeloggte Admins. Iteriert generisch über permission_defs()/MEMBER_ROLES,
// damit künftige neue Rechte/Rollen ohne Änderung an diesem Endpunkt funktionieren.
require __DIR__ . '/auth.php';
require __DIR__ . '/../mitglieder/role-permissions-lib.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
admin_check_csrf();

// Zuruecksetzen der GESAMTEN Matrix auf den Standard (Seed) statt Speichern der
// Formularwerte - ausgeloest ueber den Submit-Button "Alle auf Standard
// zuruecksetzen" (Button-Name reset_permissions, formnovalidate), siehe
// admin/index.php. Anders als bei den E-Mail-Templates gibt es hier nur einen
// Reset fuer alles, keinen pro Recht/Rolle.
if (!empty($_POST['reset_permissions'])) {
    reset_role_permissions();
    header('Location: index.php?msg=permissions-reset#berechtigungen-bereich');
    exit;
}

save_role_permissions($_POST['permissions'] ?? []);

header('Location: index.php?msg=permissions-saved#berechtigungen-bereich');
exit;
