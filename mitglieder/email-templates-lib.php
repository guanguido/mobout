<?php
// Editierbare E-Mail-Templates (Betreff + Textkörper) für die vom Mitglieder-Login
// ausgelösten Mails. Gleiches data/-Seed-Muster wie MOTD/Expeditionen: Solange
// data/email-templates.json fehlt, greift der git-getrackte Seed
// (email-templates-seed.json). Der Admin bearbeitet die Texte im Admin-Bereich.
//
// Platzhalter je Template sind über eine Whitelist definiert (email_template_defs()).
// Beim Rendern werden NUR diese Platzhalter im Format {{NAME}} ersetzt.
declare(strict_types=1);

define('EMAIL_TEMPLATES_DATA_FILE', __DIR__ . '/data/email-templates.json');
define('EMAIL_TEMPLATES_SEED_FILE', __DIR__ . '/email-templates-seed.json');

// Registry: die vier Templates + jeweils erlaubte Platzhalter (bewusst festgelegt).
function email_template_defs(): array
{
    return [
        'welcome' => [
            'label' => 'Mitglieder-Willkommens-E-Mail',
            'description' => 'Geht an ein neu angelegtes Mitglied mit E-Mail-Adresse (Empfänger: Mitglied).',
            'placeholders' => ['NAME', 'EMAIL', 'MEMBER_AREA_URL', 'RESET_URL'],
        ],
        'otp' => [
            'label' => 'Passwort-Zurückgesetzt-E-Mail (Einmalpasswort)',
            'description' => 'Geht an das Mitglied bei „Passwort vergessen" – enthält das Einmalpasswort (Empfänger: Mitglied).',
            'placeholders' => ['NAME', 'ONETIMEPASSWORD', 'MAGIC_LINK', 'MEMBER_AREA_URL'],
        ],
        'password-changed' => [
            'label' => 'Passwort-Geändert-Info',
            'description' => 'Bestätigung an das Mitglied nach einer Passwortänderung (Empfänger: Mitglied).',
            'placeholders' => ['NAME', 'CHANGE_DATE', 'MEMBER_AREA_URL'],
        ],
        'consent-notice' => [
            'label' => 'Zustimmungs-Info (Audit an info@mobout.de)',
            'description' => 'Info-/Audit-Mail ans MobOut-Postfach, wenn eine Zustimmung erteilt wird (Empfänger: info@mobout.de).',
            'placeholders' => ['NAME', 'EMAIL', 'CONSENT_DATE', 'CONSENT_SOURCE'],
        ],
    ];
}

function email_templates_read_file(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

// Liefert für jedes bekannte Template subject/body – aus data/, sonst Seed, sonst leer.
function load_email_templates(): array
{
    $data = email_templates_read_file(EMAIL_TEMPLATES_DATA_FILE);
    $seed = email_templates_read_file(EMAIL_TEMPLATES_SEED_FILE);
    $out = [];
    foreach (email_template_defs() as $key => $def) {
        $tpl = $data[$key] ?? $seed[$key] ?? ['subject' => '', 'body' => ''];
        $out[$key] = [
            'subject' => (string) ($tpl['subject'] ?? ''),
            'body' => (string) ($tpl['body'] ?? ''),
        ];
    }
    return $out;
}

// Setzt genau EIN Template auf den mitgelieferten Standard (Seed) zurueck, indem
// nur dessen Override-Eintrag aus data/email-templates.json entfernt wird - die
// anderen Templates bleiben unangetastet. Da nicht der aktuelle Seed-Inhalt
// hineinkopiert, sondern der Override geloescht wird, greift danach beim naechsten
// Laden automatisch wieder load_email_templates()' Seed-Fallback - inkl. kuenftiger
// Seed-Aktualisierungen im Code, ohne dass dafuer erneut manuell zurueckgesetzt
// werden muesste.
function reset_email_template(string $key): void
{
    if (!isset(email_template_defs()[$key])) {
        return;
    }
    $data = email_templates_read_file(EMAIL_TEMPLATES_DATA_FILE);
    unset($data[$key]);
    $dir = dirname(EMAIL_TEMPLATES_DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(
        EMAIL_TEMPLATES_DATA_FILE,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

// Speichert nur bekannte Keys + subject/body nach data/email-templates.json.
function save_email_templates(array $templates): void
{
    $dir = dirname(EMAIL_TEMPLATES_DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $clean = [];
    foreach (email_template_defs() as $key => $def) {
        $clean[$key] = [
            'subject' => (string) ($templates[$key]['subject'] ?? ''),
            'body' => (string) ($templates[$key]['body'] ?? ''),
        ];
    }
    file_put_contents(
        EMAIL_TEMPLATES_DATA_FILE,
        json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

// Rendert subject+body eines Templates. Ersetzt nur die pro-Template erlaubten
// Platzhalter ({{NAME}} etc.). Werte, die in den Betreff fließen, werden von CR/LF
// befreit (Header-Injection-Schutz). Rückgabe: ['subject' => ..., 'body' => ...].
function render_email_template(string $key, array $vars): array
{
    $templates = load_email_templates();
    $defs = email_template_defs();
    $tpl = $templates[$key] ?? ['subject' => '', 'body' => ''];
    $allowed = $defs[$key]['placeholders'] ?? [];

    $subject = (string) $tpl['subject'];
    $body = (string) $tpl['body'];
    foreach ($allowed as $ph) {
        $val = (string) ($vars[$ph] ?? '');
        $body = str_replace('{{' . $ph . '}}', $val, $body);
        $subjectVal = trim(str_replace(["\r", "\n"], ' ', $val));
        $subject = str_replace('{{' . $ph . '}}', $subjectVal, $subject);
    }
    return ['subject' => $subject, 'body' => $body];
}
