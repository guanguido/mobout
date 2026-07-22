<?php
// AJAX-Endpunkt: erzeugt aus Schlagworten einen Kurztext-Vorschlag. Session- + CSRF-
// geschuetzt. Antwortet IMMER mit HTTP 200 und JSON - jeder Fehlerfall wird sanft als
// {ok:false, message:...} gemeldet (stille Fehlertoleranz, vom Nutzer gefordert), damit
// die Admin-UI sauber degradiert und der getippte Text nie verloren geht.
require __DIR__ . '/auth.php';
require_admin();
require_once __DIR__ . '/../mitglieder/ai-lib.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF bewusst als sanfter Check (kein harter 400-Exit wie admin_check_csrf()), damit die
// Antwort immer sauberes JSON bleibt.
$token = (string) ($_POST['csrf'] ?? '');
if (!admin_is_logged_in() || empty($_SESSION['admin_csrf']) || !hash_equals((string) $_SESSION['admin_csrf'], $token)) {
    echo json_encode(['ok' => false, 'message' => 'Sitzung abgelaufen. Bitte Seite neu laden.']);
    exit;
}

if (!ai_is_active()) {
    echo json_encode(['ok' => false, 'message' => 'KI-Funktion ist nicht aktiv.']);
    exit;
}

$keywords = trim((string) ($_POST['keywords'] ?? ''));
$keywords = mb_substr($keywords, 0, 300);
if ($keywords === '') {
    echo json_encode(['ok' => false, 'message' => 'Bitte zuerst ein paar Schlagworte eingeben.']);
    exit;
}

$result = ai_generate_slogan($keywords);
if (!empty($result['ok'])) {
    echo json_encode(['ok' => true, 'text' => (string) $result['text']]);
} else {
    echo json_encode(['ok' => false, 'message' => 'KI derzeit nicht verfuegbar – bitte Text manuell eingeben.']);
}
exit;
