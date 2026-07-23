<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/mitglieder/site-config-lib.php';

function fail(string $error): void
{
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

if ($name === '' || $email === '' || $subject === '' || $message === '') {
    fail('missing_fields');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('invalid_email');
}

// Header-Injection verhindern: Zeilenumbrüche aus einzeiligen Feldern entfernen.
$stripNewlines = static fn(string $value): string => trim(str_replace(["\r", "\n"], ' ', $value));
$name = $stripNewlines($name);
$email = $stripNewlines($email);
$subject = $stripNewlines($subject);

$siteConfig = load_site_config();
$to = $siteConfig['email'] !== '' ? $siteConfig['email'] : 'info@mobout.de';
$mailSubject = '[MobOut Kontaktformular] ' . $subject;
$body = "Name: $name\nE-Mail: $email\n\n$message\n";
$headers = "From: MobOut Website <$to>\r\n"
    . "Reply-To: $name <$email>\r\n"
    . 'Content-Type: text/plain; charset=utf-8';

if (!mail($to, $mailSubject, $body, $headers)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'send_failed']);
    exit;
}

echo json_encode(['ok' => true]);
