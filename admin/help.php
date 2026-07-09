<?php
// Admin-only Hilfeseite: dokumentiert die Abläufe rund um die individuellen
// Mitglieder-Logins direkt im Tool (Schwerpunkt: Prozess Benutzeranlage), damit der
// Admin sie nicht extern nachschlagen muss. Rein statischer Inhalt, kein Datenspeicher.
// Session-geschützt wie admin/index.php, kein eigenes CSRF nötig (keine Formulare hier).
require __DIR__ . '/auth.php';

require_admin();

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Hilfe | MobOut Administration</title>
    <style>
        :root {
            --primary-color: #1a5276;
            --secondary-color: #2c7aa3;
            --accent-color: #d4722c;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
            --border-color: #e0e0e0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark-text); line-height: 1.6; background-color: #fff;
        }
        header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white; padding: 1.5rem 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header-content {
            max-width: 1000px; margin: 0 auto; padding: 0 2rem; display: flex;
            align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;
        }
        .header-content .title { font-size: 1.4rem; font-weight: 700; }
        .header-content .subtitle { font-size: 0.85rem; opacity: 0.85; }
        header nav a {
            color: white; text-decoration: none; font-weight: 500;
            border-bottom: 2px solid transparent; padding: 0.25rem 0; transition: border-color 0.3s ease;
        }
        header nav a:hover { border-bottom-color: var(--accent-color); }
        header nav a + a { margin-left: 1.25rem; }
        main { max-width: 1000px; margin: 0 auto; padding: 3rem 2rem; }
        h1.page-title { color: var(--primary-color); font-size: 1.8rem; margin-bottom: 0.5rem; }
        .intro { color: #666; margin-bottom: 2rem; }
        .panel {
            background: var(--light-bg); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 1.75rem; margin-bottom: 2rem;
        }
        .panel > h2 { color: var(--primary-color); font-size: 1.35rem; margin-bottom: 0.75rem; }
        .panel > p.hint { color: #666; margin-bottom: 1rem; font-size: 0.95rem; }
        .panel h3 { color: var(--secondary-color); font-size: 1.05rem; margin: 1.25rem 0 0.5rem; }
        .panel ol, .panel ul { margin: 0 0 1rem 1.4rem; }
        .panel li { margin-bottom: 0.4rem; }
        .panel code { background: #eef2f5; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.88em; }
        .flow {
            display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;
            margin: 1rem 0 1.5rem; font-size: 0.9rem;
        }
        .flow-step {
            background: white; border: 1px solid var(--border-color); border-radius: 8px;
            padding: 0.6rem 0.9rem; font-weight: 500; color: var(--primary-color);
        }
        .flow-arrow { color: var(--secondary-color); font-weight: 700; }
        .state-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; margin: 0.5rem 0 1rem; }
        .state-table th, .state-table td { text-align: left; padding: 0.5rem 0.7rem; border-bottom: 1px solid var(--border-color); }
        .state-table th { color: var(--secondary-color); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em; }
        .badge { display: inline-block; padding: 0.1rem 0.55rem; border-radius: 999px; font-size: 0.78rem; font-weight: 600; }
        .badge-off { background: #fdecea; color: #b71c1c; }
        .badge-on { background: #e8f6ea; color: #1e6b2e; }
        .back-link { display: inline-block; margin-top: 1.5rem; color: var(--secondary-color); text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
        footer { background: var(--dark-text); color: rgba(255,255,255,0.75); text-align: center; padding: 1.5rem; font-size: 0.85rem; margin-top: 2rem; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div>
                <div class="title">MobOut</div>
                <div class="subtitle">Administration &middot; Hilfe</div>
            </div>
            <nav>
                <a href="index.php">&larr; Zum Dashboard</a>
                <a href="index.php?logout=1">Abmelden</a>
            </nav>
        </div>
    </header>

    <main>
        <h1 class="page-title">Hilfe</h1>
        <p class="intro">Kurzreferenz für die Abläufe rund um individuelle Mitglieder-Logins und die Anzeige-Zustimmung. Bei Prozessänderungen bitte hier mitpflegen.</p>

        <section class="panel" id="benutzeranlage">
            <h2>1. Prozess Benutzeranlage (Kernprozess)</h2>
            <p class="hint">So kommt ein Mitglied von „existiert nur als Datensatz" zu einem funktionierenden, individuellen Login.</p>
            <ol>
                <li>Admin legt das Mitglied an (oder bearbeitet ein bestehendes) und trägt eine <strong>E-Mail-Adresse</strong> ein &rarr; im Hintergrund entsteht ein Account, die <strong>Willkommensmail</strong> (Template <code>welcome</code>) wird verschickt. E-Mail gilt zu diesem Zeitpunkt als <em>unverifiziert</em>.</li>
                <li>Das Mitglied öffnet den Mitgliederbereich und klickt auf <strong>„Passwort vergessen / Zugang einrichten"</strong>, gibt seine E-Mail-Adresse ein.</li>
                <li>Das Mitglied erhält ein <strong>Einmalpasswort</strong> per Mail (Template <code>otp</code>).</li>
                <li>Login mit E-Mail + Einmalpasswort &rarr; die E-Mail gilt jetzt als <strong>verifiziert</strong> (der Login beweist, dass die Mail tatsächlich ankam).</li>
                <li>Da nach einem Einmalpasswort immer ein Passwortwechsel aussteht, wird das Mitglied direkt zur Passwort-Ändern-Ansicht gezwungen &rarr; eigenes Passwort setzen &rarr; Bestätigungsmail (Template <code>password-changed</code>).</li>
                <li>Erst jetzt kann das Mitglied im Bereich „Konto" der <strong>Anzeige auf der öffentlichen Website zustimmen</strong> (siehe Abschnitt 4).</li>
            </ol>
            <div class="flow">
                <div class="flow-step">E-Mail angelegt</div>
                <div class="flow-arrow">&rarr;</div>
                <div class="flow-step">Passwort-Reset angefordert</div>
                <div class="flow-arrow">&rarr;</div>
                <div class="flow-step">Login mit Einmalpasswort</div>
                <div class="flow-arrow">&rarr;</div>
                <div class="flow-step">Eigenes Passwort gesetzt</div>
                <div class="flow-arrow">&rarr;</div>
                <div class="flow-step">Zustimmung möglich</div>
            </div>
            <h3>Zustände auf einen Blick</h3>
            <table class="state-table">
                <thead><tr><th>Zustand</th><th>Bedeutung</th></tr></thead>
                <tbody>
                    <tr><td><span class="badge badge-off">unverifiziert</span></td><td>E-Mail hinterlegt, aber noch nicht durch einen erfolgreichen Login bestätigt.</td></tr>
                    <tr><td><span class="badge badge-on">verifiziert</span></td><td>Mitglied hat sich mindestens einmal erfolgreich eingeloggt.</td></tr>
                    <tr><td><span class="badge badge-off">keine Zustimmung</span></td><td>Mitglied erscheint <strong>nicht</strong> auf der öffentlichen Website.</td></tr>
                    <tr><td><span class="badge badge-on">Zustimmung erteilt</span></td><td>Mitglied ist auf der öffentlichen Website sichtbar.</td></tr>
                </tbody>
            </table>
            <p class="hint">Das Einmalpasswort hat bewusst <strong>keine Ablaufzeit und kein Rate-Limit</strong> (Einfachheit). Ein Mitglied kann jederzeit erneut „Passwort vergessen" nutzen, falls die Mail verloren geht.</p>
        </section>

        <section class="panel" id="bestandszustimmung">
            <h2>2. Bestands-Zustimmung</h2>
            <p class="hint">Für Mitglieder, die noch keinen eigenen Account/Login-Durchlauf hatten (z. B. beim Umstieg von früher), kann der Admin die Zustimmung <strong>stellvertretend</strong> setzen, damit die öffentliche Seite nicht leer bleibt.</p>
            <ul>
                <li>Im Panel <strong>„Mitglieder"</strong> oder <strong>„Zustimmungs-Übersicht"</strong> auf <em>„Bestands-Zustimmung erteilen"</em> klicken.</li>
                <li>Wird als Quelle <code>admin</code> gespeichert (im Unterschied zu <code>self</code> bei einer Zustimmung durch das Mitglied selbst) &ndash; im Audit und in der Übersicht immer nachvollziehbar.</li>
                <li>Ein Mitglied muss eine per Admin gesetzte Zustimmung <strong>nicht</strong> selbst bestätigen; ein Widerruf durch das Mitglied ist bewusst nicht vorgesehen.</li>
            </ul>
        </section>

        <section class="panel" id="templates">
            <h2>3. E-Mail-Templates</h2>
            <p class="hint">Betreff und Text aller automatisch versendeten Mails sind im Panel „E-Mail-Templates" editierbar (kein Deploy nötig). Vier Templates, jeweils an einen festen Auslöser gebunden:</p>
            <table class="state-table">
                <thead><tr><th>Template</th><th>Auslöser</th><th>Empfänger</th><th>Wichtigster Platzhalter</th></tr></thead>
                <tbody>
                    <tr><td><code>welcome</code></td><td>Admin trägt E-Mail bei einem Mitglied ein</td><td>Mitglied</td><td><code>{{MEMBER_AREA_URL}}</code></td></tr>
                    <tr><td><code>otp</code></td><td>„Passwort vergessen" angefordert</td><td>Mitglied</td><td><code>{{ONETIMEPASSWORD}}</code></td></tr>
                    <tr><td><code>password-changed</code></td><td>Passwort geändert (auch beim erzwungenen ersten Mal)</td><td>Mitglied</td><td><code>{{CHANGE_DATE}}</code></td></tr>
                    <tr><td><code>consent-notice</code></td><td>Zustimmung erteilt (self oder admin)</td><td><code>info@mobout.de</code></td><td><code>{{CONSENT_SOURCE}}</code></td></tr>
                </tbody>
            </table>
            <p class="hint">Nur die im Panel je Template aufgelisteten Platzhalter werden ersetzt &ndash; ein Tippfehler im Platzhalternamen bleibt als Text stehen, statt einen Fehler zu werfen.</p>
        </section>

        <section class="panel" id="anzeige-regel">
            <h2>4. Anzeige-Regel</h2>
            <p class="hint">Ein Mitglied erscheint auf der öffentlichen Website (<code>mobout.de</code>) <strong>ausschließlich</strong>, wenn die Zustimmung erteilt ist (Quelle <code>self</code> oder <code>admin</code>).</p>
            <ul>
                <li>Ohne Zustimmung: das Mitglied ist trotzdem ganz normal im <strong>Admin</strong> und im <strong>Mitgliederbereich</strong> sichtbar/verwaltbar &ndash; nur eben nicht auf der öffentlichen Seite.</li>
                <li>Der Filter sitzt zentral in <code>members.php</code> (liefert nur zustimmende Mitglieder, nur eine Feld-Whitelist) und in <code>member-image.php</code> (liefert auch das Foto nur bei Zustimmung aus, sonst kein Zugriff über die direkte Bild-URL).</li>
                <li>Ein Widerruf der Zustimmung durch das Mitglied selbst ist bewusst nicht vorgesehen.</li>
            </ul>
        </section>

        <section class="panel" id="audit">
            <h2>5. Audit / Nachweis</h2>
            <p class="hint">Jede Zustimmung wird doppelt belegt:</p>
            <ul>
                <li><strong>Datei-Nachweis:</strong> eine unveränderliche JSON-Datei je Zustimmung unter <code>mitglieder/data/consent-log/</code> (Name, E-Mail, Zeitpunkt, Quelle, IP) &ndash; der Zeitpunkt steht dabei <strong>im Dateiinhalt</strong>, nicht nur im Datei-Zeitstempel.</li>
                <li><strong>Mail-Nachweis:</strong> eine Info-Mail an <code>info@mobout.de</code> bei jeder Zustimmung (Template <code>consent-notice</code>).</li>
                <li><strong>Übersicht im Admin:</strong> das Panel „Zustimmungs-Übersicht" zeigt auf einen Blick, wer zugestimmt hat, wann und über welche Quelle &ndash; ohne im Postfach oder auf dem Server suchen zu müssen.</li>
            </ul>
        </section>

        <section class="panel" id="sicherung">
            <h2>6. Datensicherung, Übertragung &amp; Rollback</h2>
            <p class="hint">Alle dynamischen Daten (auch Accounts, E-Mail-Templates und das Zustimmungs-Audit-Log) lassen sich im Panel <strong>„Datenübertragung"</strong> als ein ZIP-Bundle sichern, zwischen Staging und Production übertragen oder für eine lokale Anpassung exportieren/wieder importieren.</p>
            <ul>
                <li>Vor größeren Änderungen (z. B. einem Import) lohnt sich immer erst ein frischer Export als Sicherung.</li>
                <li>Jeder Import legt automatisch ein Backup des Vorzustands an (Ausnahme: das Zustimmungs-Audit-Log wird nur ergänzt, nie überschrieben).</li>
                <li>Ein Rollback des <strong>Codes</strong> (z. B. zurück auf den vorherigen Stand) berührt die Laufzeitdaten nicht automatisch &ndash; alte Konten/Zustimmungen bleiben einfach ungenutzt liegen, nichts geht verloren.</li>
            </ul>
        </section>

        <a class="back-link" href="index.php">&larr; Zurück zum Dashboard</a>
    </main>

    <footer>
        MobOut Administration &middot; nur für den Admin
    </footer>
</body>
</html>
