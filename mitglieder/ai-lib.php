<?php
// Bibliothek fuer die KI-gestuetzte Slogan-/Kurztext-Generierung (Anthropic Claude API).
// Folgt dem gleichen data/-Seed-Muster wie mitglieder/imap-lib.php:
//   - git-ignorierte Konfiguration in data/ai-config.json (server-only, per .htaccess gesperrt)
//   - git-getrackter Seed-Fallback ai-config-seed.json (Standard: deaktiviert, ohne Key)
// Der eigentliche API-Aufruf laeuft per cURL - der erste ausgehende HTTPS-Aufruf im Projekt.
//
// Zwei bewusste Design-Entscheidungen (vom Nutzer gefordert):
//  1. Kostenkontrolle: ai_is_active() ist nur true, wenn die Funktion bewusst aktiviert
//     wurde UND ein Key hinterlegt ist. Sonst wird nie ein (kostenpflichtiger) Aufruf gemacht.
//  2. Stille Fehlertoleranz: ai_generate_slogan() wirft NIE eine Exception und liefert bei
//     jedem Fehler ok=false zurueck, damit der Aufrufer einfach auf den getippten Text
//     zurueckfallen kann - kein harter Fehler, keine blockierte Speicherung.
declare(strict_types=1);

const AI_CONFIG_FILE = __DIR__ . '/data/ai-config.json';
const AI_CONFIG_SEED = __DIR__ . '/ai-config-seed.json';
const AI_API_URL = 'https://api.anthropic.com/v1/messages';
const AI_API_VERSION = '2023-06-01';
const AI_DEFAULT_MODEL = 'claude-haiku-4-5';

function ai_normalize_config(array $c): array
{
    $model = trim((string) ($c['model'] ?? ''));
    return [
        'enabled' => !empty($c['enabled']),
        'api_key' => trim((string) ($c['api_key'] ?? '')),
        'model' => $model !== '' ? $model : AI_DEFAULT_MODEL,
    ];
}

function load_ai_config(): array
{
    if (file_exists(AI_CONFIG_FILE)) {
        $data = json_decode((string) file_get_contents(AI_CONFIG_FILE), true);
        if (is_array($data)) {
            return ai_normalize_config($data);
        }
    }
    if (file_exists(AI_CONFIG_SEED)) {
        $data = json_decode((string) file_get_contents(AI_CONFIG_SEED), true);
        if (is_array($data)) {
            return ai_normalize_config($data);
        }
    }
    return ai_normalize_config([]);
}

function save_ai_config(array $config): void
{
    $data = ai_normalize_config($config);
    // Ohne Key ist eine Aktivierung sinnlos - dann konsequent deaktiviert speichern.
    if ($data['api_key'] === '') {
        $data['enabled'] = false;
    }
    file_put_contents(
        AI_CONFIG_FILE,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
    );
}

// Aktiv nur, wenn bewusst eingeschaltet UND ein Key hinterlegt ist. Steuert die Anzeige
// des Buttons und ob ueberhaupt ein API-Aufruf stattfindet (Kostenkontrolle).
function ai_is_active(): bool
{
    $c = load_ai_config();
    return $c['enabled'] && $c['api_key'] !== '';
}

// Kernaufruf: kurzen Slogan/Kurztext aus Schlagworten erzeugen. Gibt IMMER ein Array
// zurueck: ['ok'=>true,'text'=>...] oder ['ok'=>false,'reason'=>...]. Wirft nie.
function ai_generate_slogan(string $keywords): array
{
    $c = load_ai_config();
    if (!$c['enabled'] || $c['api_key'] === '') {
        return ['ok' => false, 'reason' => 'inactive'];
    }
    $keywords = trim($keywords);
    if ($keywords === '') {
        return ['ok' => false, 'reason' => 'no-keywords'];
    }

    $system = 'Du formulierst kurze, praegnante Kurztexte fuer Mitglieder der Angelgruppe '
        . 'MobOut, die auf der oeffentlichen Website unter dem Namen der Person angezeigt '
        . 'werden. Schreibe auf Deutsch, freundlich, mit leichtem Angel-/Gruppenbezug. '
        . 'Maximal 1 bis 2 Saetze. Keine Anfuehrungszeichen, keine Emojis, keine Vorrede - '
        . 'gib ausschliesslich den Kurztext selbst zurueck.';
    $user = 'Erzeuge einen Kurztext aus diesen Schlagworten: ' . $keywords;

    $result = ai_api_call($c['api_key'], $c['model'], $system, $user, 200);
    if (empty($result['ok'])) {
        return $result;
    }

    $text = trim((string) $result['text']);
    // Umschliessende Anfuehrungszeichen entfernen, falls das Modell doch welche liefert.
    $text = trim($text, "\"'");
    $text = mb_substr($text, 0, 600);
    if ($text === '') {
        return ['ok' => false, 'reason' => 'empty'];
    }
    return ['ok' => true, 'text' => $text];
}

// Minimaler Verbindungstest fuers Admin-Panel: winziger Aufruf, prueft Key/Konto/Netz.
function test_ai_connection(string $apiKey, string $model): array
{
    $model = trim($model) !== '' ? trim($model) : AI_DEFAULT_MODEL;
    return ai_api_call(trim($apiKey), $model, 'Antworte nur mit dem Wort OK.', 'Bitte antworte mit OK.', 5);
}

// Gemeinsamer cURL-Aufruf an die Anthropic Messages-API. Rueckgabe:
//   ['ok'=>true, 'text'=>string, 'http'=>200]  bei Erfolg
//   ['ok'=>false, 'reason'=>string, 'http'=>int] bei jedem Fehler (nie eine Exception).
function ai_api_call(string $apiKey, string $model, string $system, string $userMsg, int $maxTokens): array
{
    if ($apiKey === '') {
        return ['ok' => false, 'reason' => 'no-key', 'http' => 0];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'reason' => 'no-curl', 'http' => 0];
    }

    $payload = json_encode([
        'model' => $model,
        'max_tokens' => $maxTokens,
        'system' => $system,
        'messages' => [
            ['role' => 'user', 'content' => $userMsg],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(AI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $apiKey,
            'anthropic-version: ' . AI_API_VERSION,
            'content-type: application/json',
        ],
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return ['ok' => false, 'reason' => 'network', 'http' => 0];
    }
    if ($http !== 200 || !is_string($body)) {
        return ['ok' => false, 'reason' => 'http-' . $http, 'http' => $http];
    }

    $decoded = json_decode($body, true);
    $text = $decoded['content'][0]['text'] ?? null;
    if (!is_string($text)) {
        return ['ok' => false, 'reason' => 'parse', 'http' => $http];
    }

    return ['ok' => true, 'text' => $text, 'http' => $http];
}
