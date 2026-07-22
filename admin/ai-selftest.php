<?php
// TEMPORÄRES Phase-0-Diagnose-Skript für die geplante KI-Slogan-Funktion.
// Zweck: VOR der eigentlichen Umsetzung absichern, dass
//   1. cURL + OpenSSL auf dem Strato-Webspace verfügbar sind,
//   2. eine ausgehende HTTPS-Verbindung zu api.anthropic.com erlaubt ist
//      (im Projekt gibt es bisher KEINEN einzigen ausgehenden HTTPS-Aufruf),
//   3. API-Konto/Key/Guthaben end-to-end funktionieren.
// Der API-Key wird NUR für genau einen Test-Aufruf entgegengenommen und NICHT
// gespeichert. Diese Datei ist ein Wegwerf-Werkzeug und wird nach erfolgreichem
// Test wieder entfernt (nicht nach Produktion deployen).
//
// Session-geschützt wie admin/index.php (require_admin()), CSRF auf dem Formular.
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_admin();

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// --- Umgebungs-Diagnose (immer, ohne Netzaufruf) -------------------------------
$curlAvailable = function_exists('curl_init');
$curlVersion = $curlAvailable ? (curl_version()['version'] ?? '?') : null;
$sslVersion = $curlAvailable ? (curl_version()['ssl_version'] ?? '?') : null;
$allowUrlFopen = (bool) ini_get('allow_url_fopen');

// --- Test-Aufruf (nur bei Formular-Absenden) -----------------------------------
$result = null; // gefüllt nach einem Test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test') {
    admin_check_csrf();

    $apiKey = trim((string) ($_POST['api_key'] ?? ''));
    $model = trim((string) ($_POST['model'] ?? 'claude-haiku-4-5'));
    if ($model === '') {
        $model = 'claude-haiku-4-5';
    }

    if ($apiKey === '') {
        $result = ['stage' => 'input', 'ok' => false, 'summary' => 'Kein API-Key eingegeben.'];
    } elseif (!$curlAvailable) {
        $result = ['stage' => 'curl', 'ok' => false, 'summary' => 'cURL ist auf diesem Server NICHT verfügbar (curl_init fehlt). Ausgehende HTTPS-Aufrufe per cURL sind so nicht möglich – Strato-Support kontaktieren oder Stream-Context-Fallback prüfen.'];
    } else {
        $payload = json_encode([
            'model' => $model,
            'max_tokens' => 5,
            'messages' => [
                ['role' => 'user', 'content' => 'Antworte nur mit dem Wort: OK'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
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
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
        ]);

        $start = microtime(true);
        $body = curl_exec($ch);
        $elapsed = microtime(true) - $start;
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Ergebnis interpretieren, damit der Klartext klar sagt, was Sache ist.
        $extractedText = null;
        if ($errno === 0 && $httpCode === 200 && is_string($body)) {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && isset($decoded['content'][0]['text'])) {
                $extractedText = (string) $decoded['content'][0]['text'];
            }
        }

        if ($errno !== 0) {
            $summary = 'Netzwerk-/cURL-Fehler (' . $errno . '): ' . $error
                . ' – Häufigste Ursache auf Shared-Hosting: ausgehende Verbindungen sind gesperrt. Strato-Support fragen, ob HTTPS-Outbound zu api.anthropic.com erlaubt ist.';
            $ok = false;
        } elseif ($httpCode === 200 && $extractedText !== null) {
            $summary = 'Erfolg! HTTPS-Outbound funktioniert und der API-Key/das Konto sind gültig. Antwort des Modells: "' . $extractedText . '"';
            $ok = true;
        } elseif ($httpCode === 401 || $httpCode === 403) {
            $summary = 'Verbindung steht (HTTPS-Outbound OK!), aber der API-Key wurde abgelehnt (HTTP ' . $httpCode . '). Key prüfen bzw. Konto-/Abrechnungsstatus in der Anthropic-Console kontrollieren.';
            $ok = false;
        } elseif ($httpCode === 429) {
            $summary = 'Verbindung steht (HTTPS-Outbound OK!), aber Rate-Limit erreicht (HTTP 429). Kurz warten und erneut testen.';
            $ok = false;
        } else {
            $summary = 'Verbindung steht (HTTPS-Outbound OK!), aber unerwartete Antwort (HTTP ' . $httpCode . '). Siehe Rohantwort unten.';
            $ok = false;
        }

        $result = [
            'stage' => 'call',
            'ok' => $ok,
            'summary' => $summary,
            'httpCode' => $httpCode,
            'errno' => $errno,
            'error' => $error,
            'elapsed' => $elapsed,
            'body' => is_string($body) ? $body : '',
            'model' => $model,
        ];
    }
}

$csrf = admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>KI-Selbsttest (temporär) | MobOut Administration</title>
    <style>
        :root {
            --primary-color: #1a5276;
            --secondary-color: #2c7aa3;
            --accent-color: #d4722c;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
            --border-color: #e0e0e0;
            --ok: #1e7e34;
            --bad: #b02a37;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--dark-text); line-height: 1.6; background: #fff; }
        header { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: #fff; padding: 1.5rem 0; }
        .header-content { max-width: 900px; margin: 0 auto; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
        .header-content .title { font-size: 1.4rem; font-weight: 700; }
        header nav a { color: #fff; text-decoration: none; font-weight: 500; border-bottom: 2px solid transparent; padding: 0.25rem 0; }
        header nav a:hover { border-bottom-color: var(--accent-color); }
        main { max-width: 900px; margin: 0 auto; padding: 2.5rem 2rem; }
        h1 { color: var(--primary-color); font-size: 1.6rem; margin-bottom: 0.5rem; }
        .intro { color: #666; margin-bottom: 2rem; }
        .panel { background: var(--light-bg); border: 1px solid var(--border-color); border-radius: 10px; padding: 1.5rem 1.75rem; margin-bottom: 1.75rem; }
        .panel h2 { color: var(--primary-color); font-size: 1.2rem; margin-bottom: 0.75rem; }
        .warn { background: #fff3cd; border: 1px solid #ffe69c; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.75rem; color: #6a4b00; }
        table.diag { width: 100%; border-collapse: collapse; }
        table.diag td { padding: 0.4rem 0.6rem; border-bottom: 1px solid var(--border-color); vertical-align: top; }
        table.diag td:first-child { width: 45%; color: #555; }
        .badge { display: inline-block; padding: 0.1rem 0.55rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; color: #fff; }
        .badge.ok { background: var(--ok); }
        .badge.bad { background: var(--bad); }
        label { display: block; margin: 0.75rem 0 0.25rem; font-weight: 600; }
        input[type=text], input[type=password] { width: 100%; padding: 0.55rem 0.7rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; }
        .row { display: flex; gap: 0.5rem; align-items: flex-end; }
        button { margin-top: 1rem; padding: 0.6rem 1.25rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 1rem; cursor: pointer; }
        button.secondary { background: var(--secondary-color); }
        .result { border-radius: 10px; padding: 1.25rem 1.5rem; margin-bottom: 1.75rem; }
        .result.ok { background: #d4edda; border: 1px solid #a3d3af; }
        .result.bad { background: #f8d7da; border: 1px solid #eeb5ba; }
        .result h2 { margin-bottom: 0.5rem; }
        pre { background: #1e1e1e; color: #e6e6e6; padding: 1rem; border-radius: 8px; overflow-x: auto; font-size: 0.85rem; margin-top: 0.75rem; }
        .muted { color: #666; font-size: 0.9rem; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="title">🔧 KI-Selbsttest (temporär)</div>
            <nav><a href="index.php">&larr; Zurück zum Dashboard</a></nav>
        </div>
    </header>
    <main>
        <h1>Phase-0-Diagnose: Funktioniert ein KI-API-Aufruf von hier aus?</h1>
        <p class="intro">Dieses Werkzeug prüft die technischen Voraussetzungen für die geplante
            KI-Slogan-Funktion, <strong>bevor</strong> sie gebaut wird. Es speichert nichts und macht
            höchstens einen einzigen, winzigen Test-Aufruf.</p>

        <div class="warn">
            <strong>Wichtig:</strong> Auf <em>staging.mobout.de</em> läuft die Seite über <strong>HTTP
            (unverschlüsselt)</strong>. Der hier eingegebene API-Key wird beim Testen im Klartext
            übertragen. Verwende zum Test am besten einen frisch erstellten Key und
            <strong>widerrufe/erneuere ihn danach</strong> in der Anthropic-Console – oder teste über
            die HTTPS-Produktionsumgebung. Diese Datei ist ein Wegwerf-Werkzeug und wird nach dem Test
            wieder entfernt.
        </div>

        <?php if ($result !== null): ?>
            <div class="result <?= $result['ok'] ? 'ok' : 'bad' ?>">
                <h2><?= $result['ok'] ? '✅ Test bestanden' : '❌ Test nicht bestanden' ?></h2>
                <p><?= h($result['summary']) ?></p>
                <?php if (($result['stage'] ?? '') === 'call'): ?>
                    <p class="muted" style="margin-top:0.75rem;">
                        HTTP-Status: <strong><?= h((string) $result['httpCode']) ?></strong> ·
                        cURL-Fehlernummer: <strong><?= h((string) $result['errno']) ?></strong> ·
                        Dauer: <strong><?= h(number_format((float) $result['elapsed'], 2)) ?> s</strong> ·
                        Modell: <strong><?= h((string) $result['model']) ?></strong>
                    </p>
                    <?php if (!empty($result['body'])): ?>
                        <p class="muted" style="margin-top:0.75rem;">Rohantwort der API:</p>
                        <pre><?= h((string) $result['body']) ?></pre>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h2>1. Umgebungs-Diagnose (ohne Netzaufruf)</h2>
            <table class="diag">
                <tr>
                    <td>cURL verfügbar (<code>curl_init</code>)</td>
                    <td><span class="badge <?= $curlAvailable ? 'ok' : 'bad' ?>"><?= $curlAvailable ? 'ja' : 'NEIN' ?></span></td>
                </tr>
                <tr>
                    <td>cURL-Version</td>
                    <td><?= $curlVersion !== null ? h($curlVersion) : '—' ?></td>
                </tr>
                <tr>
                    <td>SSL/TLS-Backend</td>
                    <td><?= $sslVersion !== null ? h($sslVersion) : '—' ?></td>
                </tr>
                <tr>
                    <td>allow_url_fopen (Fallback-Option)</td>
                    <td><span class="badge <?= $allowUrlFopen ? 'ok' : 'bad' ?>"><?= $allowUrlFopen ? 'ja' : 'nein' ?></span></td>
                </tr>
                <tr>
                    <td>PHP-Version</td>
                    <td><?= h(PHP_VERSION) ?></td>
                </tr>
            </table>
            <p class="muted" style="margin-top:0.75rem;">Ist cURL „ja", stehen die Chancen gut. Den
                endgültigen Beweis liefert erst der Test-Aufruf unten (manche Hoster erlauben cURL,
                blockieren aber ausgehende Verbindungen).</p>
        </div>

        <div class="panel">
            <h2>2. Echter Test-Aufruf an die Anthropic-API</h2>
            <p class="muted">Macht genau einen Aufruf mit <code>max_tokens: 5</code> (Kosten: Bruchteil
                eines Cents). Der Key wird nicht gespeichert.</p>
            <form method="post" action="ai-selftest.php" autocomplete="off">
                <input type="hidden" name="action" value="test">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <label for="api_key">Anthropic API-Key</label>
                <div class="row">
                    <input type="password" id="api_key" name="api_key" placeholder="sk-ant-..." required style="flex:1;">
                    <button type="button" class="secondary" style="margin-top:0;" onclick="var f=document.getElementById('api_key'); f.type = f.type==='password' ? 'text' : 'password';">👁️ Zeigen</button>
                </div>
                <label for="model">Modell</label>
                <input type="text" id="model" name="model" value="claude-haiku-4-5">
                <button type="submit">🚀 Test-Aufruf starten</button>
            </form>
        </div>

        <div class="panel">
            <h2>Wie geht es weiter?</h2>
            <ul style="margin-left:1.25rem;">
                <li><strong>Test bestanden (✅):</strong> Die Voraussetzungen stimmen – die eigentliche
                    KI-Slogan-Funktion kann gebaut werden. Danach diese Datei wieder entfernen.</li>
                <li><strong>API-Key abgelehnt (401/403):</strong> HTTPS-Outbound funktioniert, aber
                    Key/Konto/Guthaben prüfen (console.anthropic.com).</li>
                <li><strong>Netzwerk-/cURL-Fehler:</strong> Vermutlich sperrt Strato ausgehende
                    Verbindungen. Strato-Support fragen; ggf. Stream-Context-Fallback prüfen.</li>
            </ul>
        </div>
    </main>
</body>
</html>
