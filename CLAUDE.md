# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# MobOut – Angelgruppe Website

Statische Website für die Angelgruppe MobOut (www.mobout.de). Die Gruppe fährt jedes Jahr gemeinsam zum Angeln an wechselnde Orte (z. B. Schweden, Havel, Müritz).

## Struktur

```
mobout/
├── index.html          # Haupt-HTML (Single-Page, enthält CSS + JS, Bilder als Base64 eingebettet)
├── motd.php            # Öffentlicher Lese-Endpunkt für die "Nachricht des Tages" (kein Basic Auth)
├── contact.php          # Öffentlicher Endpunkt für das Kontaktformular (kein Basic Auth), versendet per mail()
├── expeditions.php      # Öffentlicher Lese-Endpunkt für die Expeditionsliste als JSON (kein Basic Auth)
├── expedition-image.php # Öffentlicher Bild-Streaming-Endpunkt für Expeditionsfotos (kein Basic Auth)
├── members.php          # Öffentlicher Lese-Endpunkt für die Mitgliederliste als JSON (kein Basic Auth)
├── member-image.php     # Öffentlicher Bild-Streaming-Endpunkt für Mitglieder-Fotos (kein Basic Auth)
├── admin/               # Versteckter Admin-Bereich (eigene PHP-Session-Auth, NICHT Basic Auth)
│   ├── index.php       # Login + Dashboard: Mitglieder-CRUD, Zustimmungs-Übersicht, E-Mail-Templates, Datenübertragung (Logo als Base64)
│   ├── auth.php        # Session-Auth, hardcodierter Admin (User "Admin" + bcrypt-Hash), CSRF-Helfer
│   ├── members-save.php # CRUD-Schreib-Endpunkt für Mitglieder (action=create/update/delete/delete-image/grant-consent), legt bei E-Mail einen Account an
│   ├── email-templates-save.php # Speichert Betreff/Text der editierbaren E-Mail-Templates
│   ├── data-transfer-lib.php    # Modul-Registry für Export/Import der dynamischen data/-Inhalte (MOTD, Mitglieder, Expeditionen, Accounts, E-Mail-Templates, Zustimmungs-Audit-Log), Backup-Rotation
│   ├── data-transfer-export.php # Baut ein ZIP-Bundle aller Module und liefert es als Download
│   ├── data-transfer-import.php # Nimmt ein ZIP-Bundle entgegen, ersetzt ausgewählte Module vollständig (mit Backup)
│   ├── data-transfer-backup-delete.php # Löscht eine einzelne Sicherung aus mitglieder/data/backups/
│   ├── member-image.php # Admin-geschützte Foto-Vorschau (ohne Consent-Filter, im Unterschied zum öffentlichen member-image.php)
│   └── help.php        # Admin-only Hilfeseite: Prozess Benutzeranlage, Bestands-Zustimmung, Templates, Anzeige-Regel, Audit, Datensicherung
├── mitglieder/         # Mitgliederbereich (PHP-Session-Login, individuelle E-Mail-Accounts)
│   ├── index.php       # Eigenständige Seite (eigenes CSS, Logo als Base64); Login-Gate + Crew-Karten + persönliche Konto-Sektion (eigenes Profil, Passwort, Zustimmung), Begrüßung mit Mitgliedsnamen
│   ├── member-auth.php # Session-Auth (E-Mail-Login), CSRF-Helfer, erzwungener Passwortwechsel, data/-Härtung
│   ├── accounts-lib.php # Lesen/Schreiben individueller Accounts (data/accounts.json), OTP-Ausgabe, Mail-Helfer
│   ├── email-templates-lib.php # Editierbare E-Mail-Templates (Registry + Rendering mit Platzhaltern)
│   ├── email-templates-seed.json # Git-getrackte Default-Texte der vier E-Mail-Templates
│   ├── reset-request.php # Öffentlich: "Passwort vergessen", stellt Einmalpasswort aus und verschickt es
│   ├── password-change.php # Passwort selbst ändern (verlangt aktuelles Passwort)
│   ├── consent-save.php # Mitglied stimmt der öffentlichen Anzeige selbst zu (nur nach E-Mail-Verifizierung)
│   ├── profile-save.php # Mitglied bearbeitet eigenen Kurztext/Icon/Foto (Name/Rolle/E-Mail bleiben admin-only)
│   ├── profile-image.php # Session-geschützte eigene Foto-Vorschau ohne f-Parameter und ohne Consent-Gate
│   ├── motd-save.php   # Schreib-Endpunkt für die MOTD, PHP-Session-geschützt
│   ├── expeditions-save.php  # CRUD-Schreib-Endpunkt für Expeditionen (action=create/update/delete/delete-image), PHP-Session-geschützt
│   ├── expeditions-lib.php   # Gemeinsame load/save-Funktionen inkl. Seeding-Logik
│   ├── expeditions-seed.json # Git-getrackter Ausgangsbestand der 8 historischen Expeditionen
│   ├── members-lib.php       # Gemeinsame load/save-Funktionen für Mitglieder inkl. Seeding-Logik + Consent-Feld-Normalisierung
│   ├── members-seed.json     # Git-getrackter Ausgangsbestand der 17 migrierten Mitglieder
│   ├── members-seed-images/  # Git-getrackte Startfotos der Mitglieder (Fallback von member-image.php)
│   ├── data/           # Nur serverseitig, git-ignoriert (motd.txt, expeditions.json, expeditions-images/, members.json, members-images/, accounts.json, email-templates.json, consent-log/, backups/) – überlebt Deploys; per .htaccess ("Require all denied") gegen Direktzugriff gesperrt
│   └── .htaccess       # Sperrt Direktzugriff auf data/ (RedirectMatch 403); KEIN Basic Auth mehr
├── assets/
│   ├── images/         # Originalfotos (Personenbilder, Logos)
│   └── data/           # Excel-Tabellen (MobOut_Teilnehmer.xlsx, MobOut_Expeditionen.xlsx)
├── .github/workflows/  # GitHub-Actions-Deployment
└── CLAUDE.md
```

## Tech Stack

- Reines HTML/CSS/JavaScript (kein Framework, kein Build-System)
- Single-Page mit Smooth-Scroll-Navigation
- Responsive Design mit Hamburger-Menü für Mobile (≤768px)
- Punktuell PHP (Strato-Hosting, PHP 8.3) für die "Nachricht des Tages", das Kontaktformular, die
  dynamische Expeditionen-Verwaltung, individuelle Mitglieder-Logins und die Mitglieder-Verwaltung
  (Admin) – beschränkt auf `mitglieder/index.php`, `mitglieder/member-auth.php`,
  `mitglieder/accounts-lib.php`, `mitglieder/email-templates-lib.php`, `mitglieder/reset-request.php`,
  `mitglieder/password-change.php`, `mitglieder/consent-save.php`, `mitglieder/motd-save.php`,
  `motd.php`, `contact.php`, `expeditions.php`, `expedition-image.php`,
  `mitglieder/expeditions-save.php`, `mitglieder/expeditions-lib.php`, `members.php`,
  `member-image.php`, `mitglieder/members-lib.php` und `admin/*.php`, siehe eigene Abschnitte unten

## Sprache

Deutsch (de)

## Kontakt / Domain

- Domain: www.mobout.de
- E-Mail: info@mobout.de
- Instagram: @mobout.de (https://www.instagram.com/mobout.de) – gemeinsames Gruppenkonto, verlinkt aus dem Bereich „Expeditionen" in index.html

---

# Architektur

`index.html` ist die einzige Quelldatei (~570 KB). Sie enthält:
- CSS (ca. Zeilen 7–600): Variablen, Layout, Komponenten, Media Queries
- HTML (ca. Zeilen 600–1030): Header, Hero, Über uns, Expeditionen, Team, Galerie, Kontakt, Footer
- JS (Ende der Datei): Smooth Scroll, Hamburger-Toggle, Kontaktformular-Handler
- Alle Bilder als Base64 direkt im `<img src="data:image/...">` eingebettet (daher große Dateigröße)

**Wichtig:** Die Datei ist wegen Base64 zu groß für das Edit-Tool (~328k Tokens). Änderungen immer über Python-Skripte vornehmen:

```python
with open('index.html', 'r', encoding='utf-8') as f:
    content = f.read()
content = content.replace('alter Text', 'neuer Text')
with open('index.html', 'w', encoding='utf-8') as f:
    f.write(content)
```

Skripte im Scratchpad ablegen, nie direkt in PowerShell inline schreiben (Quoting-Probleme mit CSS-Werten).

## Inhaltsdaten

Die Teilnehmer-/Mitgliederdaten (Anzeigename, Rolle, Kurztext, Foto/Icon) sind seit der Mitglieder-
Verwaltung (Admin) **nicht mehr hartcodiert** in `index.html`, sondern werden dynamisch geladen, siehe
Abschnitt „Mitglieder-Verwaltung (Admin)" unten. `assets/data/MobOut_Teilnehmer.xlsx` ist dadurch nicht
mehr Quelle der Wahrheit, sondern nur noch optionale historische Referenz.

Die Expeditionsdaten (Jahr, Ort/Angelplatz, Beschreibung, Instagram-Link, Fotogalerie) sind seit der
dynamischen Expeditionen-Verwaltung **nicht mehr hartcodiert**, siehe Abschnitt „Expeditionen
(dynamisch)" unten. `assets/data/MobOut_Expeditionen.xlsx` ist dadurch nicht mehr Quelle der Wahrheit,
sondern nur noch optionale historische Referenz.

## Mitgliederbereich (`mitglieder/`)

Interner Bereich unter `mobout.de/mitglieder/` (eigene URL, nicht Teil der Single-Page). Verlinkt aus
der Hauptnavigation in `index.html` (Link `.nav-members`).

- **Schutz:** PHP-Session-Login (`mitglieder/member-auth.php`), analog zum Admin-Bereich – **kein**
  Apache Basic Auth mehr. Jedes Mitglied hat einen **individuellen Account** (E-Mail als Loginname),
  siehe „Individuelle Logins & Anzeige-Zustimmung" unten.
- **Seite:** `mitglieder/index.php` ist eigenständig (eigenes CSS, Logo als Base64), da `assets/`
  nicht auf den Server deployt wird. Rendert das Login-/Passwort-vergessen-Formular, bei
  ausstehendem Passwortwechsel ein reduziertes Passwort-Formular, sonst die vollen Crew-Karten
  plus eine persönliche Konto-Sektion (`#konto-bereich`).
- **Session-Cookies:** `secure` nur bei echtem HTTPS gesetzt (erkannt über `$_SERVER['HTTPS']` /
  `X-Forwarded-Proto` / Port 443) – Production läuft über HTTPS (Cookie hart `Secure`), die
  Testumgebung **staging.mobout.de bewusst über HTTP** (kein Zertifikat für die Subdomain), dort
  ohne `Secure`-Flag, sonst würde der Browser das Session-Cookie verwerfen und der Login griffe nicht.
- **Inhalt:** `mitglieder/index.php` zeigt Info-Karten für die Crew: "Expeditionen" (Verwaltung,
  siehe eigener Abschnitt), "Instagram", "Navionics Account", "Nachricht des Tages" und "Konto"
  (eigenes Profil: Kurztext/Icon/Foto, Passwort ändern, Zustimmung zur Anzeige). Die
  Begrüßungsüberschrift zeigt den Namen des eingeloggten Mitglieds.
  - Karte "Navionics Account": Zugangsdaten für den gemeinsamen Navionics-Account (Boating HD App,
    Tiefenkarten). Die Karte selbst in `mitglieder/index.php` ist die Quelle der Wahrheit für diese
    Zugangsdaten (nicht hier duplizieren). Abo läuft aktuell bis 14.05.2027 – bei
    Verlängerung/Änderung die Karte entsprechend aktualisieren.
  - Karte "Instagram" (Section `#instagram-bereich`): Zugangsdaten des geteilten `@mobout.de`-
    Instagram-Accounts plus Kurzanleitung zum Posten. Die Karte ist die Quelle der Wahrheit für
    diese Zugangsdaten (Benutzername, Passwort, hinterlegte E-Mail/Mobilnummer, Privatsphäre-Status)
    – nicht hier duplizieren; bei Änderungen die Karte aktualisieren. Erklärt bewusst, dass Bilder
    nur über den geteilten Account (nicht via `@`/`#` aus eigenen Accounts) auf der Website landen.
    Reiner statischer Karteninhalt – kein Schreib-Endpunkt. Der öffentliche Website-Link führt zu
    `instagram.com/mobout.de`; ist der Account privat, sind Beiträge für Nicht-Follower nicht
    sichtbar.

## Individuelle Logins & Anzeige-Zustimmung

Jedes Mitglied hat einen eigenen Account (E-Mail als Loginname) statt des früheren einen geteilten
Basic-Auth-Zugangs. Zusätzlich wird ein Mitglied auf der öffentlichen Website **nur** angezeigt, wenn
es der Anzeige selbst (nach verifizierter E-Mail) zugestimmt hat – ein DSGVO-naher Selbst-Zustimmungs-
Mechanismus.

- **Datenmodell (Secrets ≠ Anzeigedaten, zwei getrennte Speicher):**
  - `mitglieder/data/accounts.json` (git-ignoriert, per `.htaccess` gegen Direktzugriff gesperrt):
    pro Mitglied `memberId`, `email`, `emailVerified`, `passwordHash`, `mustChangePassword`,
    `createdAt`/`updatedAt`. Wird **nie** öffentlich ausgeliefert.
  - `mitglieder/data/members.json`: bekommt zusätzlich die nicht-geheimen Felder `consentGiven`
    (bool), `consentAt` (ISO-8601 oder `null`), `consentSource` (`self` | `admin` | `null`).
    `mitglieder/members-lib.php`s `load_members()` normalisiert fehlende Consent-Felder beim Lesen
    (rückwärtskompatibel zu Altbeständen ohne Migrationsskript).
- **OTP = Passwort-Hash:** Ein Einmalpasswort wird nicht separat gespeichert. `issue_otp()` in
  `mitglieder/accounts-lib.php` setzt `passwordHash = bcrypt(OTP)` und `mustChangePassword = true`.
  Der Login mit dem OTP verifiziert gegen genau diesen Hash; ein **erfolgreicher Login setzt
  `emailVerified = true`** (beweist Zugriff auf das Postfach) und erzwingt wegen
  `mustChangePassword` sofort einen Passwortwechsel (`mitglieder/password-change.php`). Derselbe Weg
  deckt Erst-Einrichtung **und** „Passwort vergessen" (`mitglieder/reset-request.php`, öffentlich,
  generische Antwort gegen User-Enumeration) ab. **Bewusst ohne Ablauf und ohne Rate-Limit**
  (Einfachheit vor Robustheit).
- **Zustimmung:** `mitglieder/consent-save.php` erlaubt die Selbst-Zustimmung nur, wenn `emailVerified`
  **und** kein Passwortwechsel mehr aussteht. Schreibt zusätzlich eine unveränderliche JSON-Datei je
  Zustimmung unter `mitglieder/data/consent-log/` (Zeitpunkt **inhaltlich im JSON**, nicht als
  Datei-mtime – die überlebt Kopieren/Backup/Transfer nicht zuverlässig) und verschickt eine
  Info-/Audit-Mail an `info@mobout.de` (Template `consent-notice`). Es gibt einen zweiten Einstieg für
  dieselbe Selbst-Zustimmung: eine vorangehakte Checkbox direkt im reduzierten Formular von
  `mitglieder/index.php`, das bei ausstehendem `mustChangePassword` (siehe „OTP = Passwort-Hash"
  oben) statt der vollen Seite gezeigt wird. Die Zustimmung passiert dabei inline in
  `mitglieder/password-change.php` (kein Redirect zu `consent-save.php`), aber mit identischer
  Nebenwirkung (Audit-Log-Eintrag, Info-Mail, `consentSource='self'`). Wird die Checkbox abgewählt,
  bleibt der bestehende Button „Der Anzeige zustimmen" im Konto-Bereich weiterhin verfügbar. Der
  Admin kann stellvertretend eine **Bestands-Zustimmung** setzen (`admin/members-save.php`,
  `action=grant-consent`,
  `consentSource=admin`) – z. B. für Mitglieder aus der Zeit vor den individuellen Accounts, damit
  die öffentliche Seite nicht leer ist. Beide Wege sind im Admin-Panel „Zustimmungs-Übersicht"
  (`#zustimmungen-bereich`) transparent einsehbar (sonst nur im Postfach bzw. in Server-Dateien sichtbar).
- **Öffentliche Filterung:** `members.php` liefert nur Mitglieder mit `consentGiven === true` und
  nur eine Feld-Whitelist (keine internen Zustimmungs-/Account-Metadaten). `member-image.php` prüft
  ebenfalls `consentGiven`, damit ein Foto nicht per direkter URL trotz fehlender Zustimmung abrufbar
  ist.
- **Datentrennung/Berechtigungen:** Passwort ändern, Zustimmung und die eigenen Profilfelder (siehe
  „Eigenes Profil" unten) sind streng auf das eingeloggte Mitglied selbst begrenzt (`memberId` kommt
  ausschließlich aus der Session, nie aus einem Request-Parameter) – ein Mitglied kann so technisch
  nie ein fremdes Profil erreichen. **Name, Rolle und E-Mail** bearbeitet weiterhin ausschließlich der
  Admin (`admin/members-save.php`); **Kurztext, das kleine Icon (Emoji) und Foto** kann seit dem
  Self-Service-Profil auch das Mitglied selbst ändern (`mitglieder/profile-save.php`). Gruppeninhalte
  (Expeditionen, MOTD) bleiben wie bisher für jedes eingeloggte Mitglied bearbeitbar.
- **Eigenes Profil (`#konto-bereich` → „Mein Profil"):** Ein Mitglied sieht dort Name und Rolle
  (nur Anzeige) sowie Kurztext, kleines Icon und Foto (bearbeitbar) des eigenen Datensatzes.
  `mitglieder/profile-save.php` validiert Foto-Uploads wie beim Admin-CRUD (Whitelist
  jpg/jpeg/png/webp, `getimagesize()`, max. 5 MB). Die eigene Foto-Vorschau läuft über einen
  **dritten** Bild-Endpunkt, `mitglieder/profile-image.php`: Session-geschützt, akzeptiert **keinen**
  `f`-Parameter (Dateiname kommt ausschließlich aus dem eigenen Datensatz), damit ein Mitglied
  niemals das Foto einer anderen Person abrufen kann – und liefert das eigene Foto unabhängig von der
  eigenen `consentGiven`-Zustimmung aus (sonst könnte ein Mitglied ohne Zustimmung sein Foto nicht
  mal selbst sehen, da das öffentliche `member-image.php` genau das voraussetzt). Die
  Begrüßung in `mitglieder/index.php` zeigt den Namen des eingeloggten Mitglieds
  ( „Willkommen NAME im Mitgliederbereich von MobOut").
- **Hilfe für den Admin:** `admin/help.php` (verlinkt im Admin-Dashboard-Nav) dokumentiert den
  kompletten Benutzeranlage-Prozess sowie Bestands-Zustimmung, Templates, Anzeige-Regel und Audit
  direkt im Tool.

## E-Mail-Templates

Alle vom Mitgliederbereich ausgelösten Mails (Willkommen, Einmalpasswort, Passwort geändert,
Zustimmungs-Info) ziehen Betreff und Text aus **admin-editierbaren Templates** statt festem Code-Text –
gleiches `data/`-Seed-Muster wie MOTD/Expeditionen.

- **Speicher:** `mitglieder/data/email-templates.json` (git-ignoriert, überlebt Deploys), Seed-Fallback
  `mitglieder/email-templates-seed.json` (git-getrackt, deutsche Default-Texte).
- **Lib:** `mitglieder/email-templates-lib.php` mit der Registry `email_template_defs()` (vier
  Templates + je eine Whitelist gültiger Platzhalter) und `render_email_template()` (ersetzt nur
  Platzhalter im Format `{{PLATZHALTER}}`, die für das jeweilige Template erlaubt sind).
- **Die vier Templates:**
  - `welcome` (an das Mitglied): `{{NAME}}`, `{{EMAIL}}`, `{{MEMBER_AREA_URL}}`
  - `otp` (an das Mitglied, Passwort-Zurückgesetzt-Mail): `{{NAME}}`, `{{ONETIMEPASSWORD}}`,
    `{{MEMBER_AREA_URL}}`
  - `password-changed` (an das Mitglied): `{{NAME}}`, `{{CHANGE_DATE}}`, `{{MEMBER_AREA_URL}}`
  - `consent-notice` (an `info@mobout.de`, Audit): `{{NAME}}`, `{{EMAIL}}`, `{{CONSENT_DATE}}`,
    `{{CONSENT_SOURCE}}`
- **Versand:** `mitglieder/accounts-lib.php` (`send_welcome_mail()`, `send_otp_mail()`,
  `send_password_changed_mail()`, `send_consent_notice_mail()`) nutzt das `contact.php`-Muster
  (PHP `mail()`, `From`/`Reply-To`, CR/LF-Strip gegen Header-Injection, `Content-Type: text/plain;
  charset=utf-8`), zusätzlich mit `mb_encode_mimeheader()` für den Betreff (Umlaute/Namen).
- **Admin-UI:** Panel „E-Mail-Templates" in `admin/index.php` (`#email-templates-bereich`) –
  Betreff-Feld + Textarea je Template, darunter die für dieses Template gültigen Platzhalter.
  Speichert über `admin/email-templates-save.php`.

## Nachricht des Tages (MOTD)

Kleines PHP-Testfeature, um den Mechanismus "Mitglied editiert im geschützten Bereich → Inhalt
erscheint automatisch auf der öffentlichen Website" zu validieren:

- Mitglied trägt im Mitgliederbereich (Karte "Nachricht des Tages") einen Text ein und speichert
  über `mitglieder/motd-save.php` (PHP-Session-geschützt: `require_member()` + CSRF-Check). Der Text
  landet in `mitglieder/data/motd.txt` auf dem Server.
- `mitglieder/data/` ist **git-ignoriert** (server-only). Der Deploy-Workflow nutzt `rsync --delete`
  (damit umbenannte/gelöschte Dateien wie alte `.html`-Versionen wirklich vom Server verschwinden),
  schließt `mitglieder/data/` aber explizit per `--exclude=data/` von der Löschung aus, damit
  `motd.txt` Deploys übersteht.
- `motd.php` (Repo-Root, öffentlich, kein Login nötig) liest die Datei serverseitig vom
  Dateisystem aus und liefert sie unverändert als `text/plain` aus – reiner Dateisystemzugriff,
  unabhängig vom Session-Login auf `mitglieder/`. Kein HTML-Escaping nötig/gewollt, da der Text nie
  als HTML interpretiert wird.
- `index.html` lädt `motd.php` per `fetch()` und blendet die Nachricht als Banner im Hero-Bereich
  (unter dem "Kontaktiere uns"-Button) ein – nur wenn ein Text gesetzt ist, sonst nichts. Einfügung
  ausschließlich über `textContent` (nie `innerHTML`), um XSS auszuschließen.

## Kontaktformular

Das Formular im Abschnitt "Kontakt & Informationen" sendet per `fetch()` an `contact.php`
(Repo-Root, **nicht** durch Basic Auth geschützt, wie `motd.php`):

- `contact.php` validiert die Pflichtfelder (Name, E-Mail, Betreff, Nachricht), prüft die
  E-Mail-Adresse mit `filter_var(..., FILTER_VALIDATE_EMAIL)`, entfernt Zeilenumbrüche aus den
  einzeiligen Feldern (Header-Injection-Schutz) und verschickt die Nachricht per PHP `mail()`
  an `info@mobout.de` mit `Reply-To` auf die Absenderadresse. Antwort ist JSON (`{ok: true}` /
  `{ok: false, error: "..."}`).
- Voraussetzung: Der Strato-Webspace muss `mail()` unterstützen (Standard bei Strato-Hosting).
  Es gibt kein SMTP-Fallback und keine Logdatei für gescheiterte Zustellungen.
- `index.html` deaktiviert den Submit-Button während des Requests und zeigt je nach Ergebnis
  eine Erfolgs- oder Fehlermeldung per `alert()` (Fehlermeldung verweist auf `info@mobout.de`
  als Ausweichkontakt).
- Der Deploy-Workflow überträgt `contact.php` zusätzlich zu `index.html` und `motd.php`.

## Expeditionen (dynamisch)

Erweitert den MOTD-Mechanismus um volle CRUD-Verwaltung mit Bild-Uploads: Mitglieder können
Expeditionen (Jahr, Ort/Angelplatz, Beschreibung, optionaler Instagram-Link, Foto-Galerie) im
Mitgliederbereich anlegen, bearbeiten und löschen – die öffentliche Website zeigt das automatisch,
ohne neuen Deploy.

- **Daten:** `mitglieder/data/expeditions.json` (git-ignoriert, server-only, wie `motd.txt`), ein
  JSON-Array aller Expeditionen. Bilder liegen als echte Dateien in
  `mitglieder/data/expeditions-images/` (ebenfalls git-ignoriert, gleicher `--exclude=data/`-Schutz
  beim Deploy).
- **Automatisches Seeding:** Solange `mitglieder/data/expeditions.json` noch nicht existiert (z. B.
  frisch aufgesetzte Umgebung), liefert `mitglieder/expeditions-lib.php`s `load_expeditions()`
  stattdessen den Ausgangsbestand aus der **git-getrackten** `mitglieder/expeditions-seed.json`
  (die 8 historischen Expeditionen 2019–2026). Sobald ein Mitglied zum ersten Mal etwas
  anlegt/bearbeitet/löscht, schreibt `save_expeditions()` die echte Datendatei – ab dann ist die
  Seed-Datei für den laufenden Betrieb irrelevant, bleibt aber als dokumentierter Startbestand im
  Repo. Kein manueller Schritt auf Staging/Produktion nötig.
- **Schreiben:** `mitglieder/expeditions-save.php` (PHP-Session-geschützt: `require_member()` +
  CSRF-Check) verarbeitet alle Mutationen über einen `action`-Parameter (`create` / `update` /
  `delete` / `delete-image`), inklusive Bild-Upload-Validierung (Whitelist jpg/jpeg/png/webp,
  `getimagesize()`-Check, max. 5 MB pro Bild, max. 8 Bilder pro Expedition, serverseitig generierte
  Dateinamen statt Original-Namen).
- **Lesen:** `expeditions.php` (Repo-Root, öffentlich) liest die Daten serverseitig vom Dateisystem
  – funktioniert wie `motd.php` unabhängig vom Session-Login auf `mitglieder/`, da reiner
  Dateisystemzugriff keine HTTP-Requests auf den geschützten Bereich sind.
- **Bilder ausliefern:** `expedition-image.php` (Repo-Root, öffentlich) liest eine
  einzelne Bilddatei aus `mitglieder/data/expeditions-images/` und streamt sie mit passendem
  `Content-Type`. Enthält strikten Pfad-Traversal-Schutz (`basename()`-Check + `realpath()`-
  Containment-Prüfung), damit über den `f`-Parameter keine beliebigen Dateien ausgelesen werden
  können.
- **Instagram-Link:** pro Expedition **optional** und individuell (Story-Highlight-Link, Format
  `https://www.instagram.com/stories/highlights/<id>/`), nicht mehr der frühere feste Link zum
  Gruppenprofil. Fehlt der Link, wird im Kartenfooter einfach kein Instagram-Link angezeigt.
- `index.html` lädt die Liste per `fetch('/expeditions.php')` und baut die `.exp-card`-Elemente
  dynamisch per DOM auf (`textContent`/Property-Zuweisungen, nie `innerHTML`), inklusive Galerie-
  Thumbnails über `expedition-image.php`.

## Mitglieder-Verwaltung (Admin)

Verwaltung der auf der Website angezeigten **Mitglieder/Teilnehmer** (Personen) durch **einen** dedizierten
Admin. Übernimmt das Seed-/`data/`-Prinzip der Expeditionen. **Begriffe strikt trennen:**

- **Admin** = hardcodierter Einzel-Account (Benutzer `Admin` + festes Passwort), eigene versteckte
  Login-Seite. Verwaltet Mitglieder, deren individuelle Accounts und die Zustimmungs-Übersicht.
- **Mitglied-Login** = seit den individuellen Accounts (siehe „Individuelle Logins &
  Anzeige-Zustimmung") **kein geteilter Zugang mehr** – jedes Mitglied hat seinen eigenen Login
  (E-Mail + selbst gesetztes Passwort). Der Admin legt nur die E-Mail an; das Passwort setzt sich
  jedes Mitglied über „Passwort vergessen" selbst.
- **Mitglieder/Teilnehmer** = die angezeigten Personen (Datensätze). Profile (Name, Rolle, Text,
  Icon, Foto, E-Mail) bearbeitet weiterhin ausschließlich der Admin – kein Mitglied bearbeitet ein
  Profil. Passwort und Zustimmung sind die einzigen Selbstbearbeitungen, jeweils strikt auf das
  eigene Mitglied begrenzt.

**Datenmodell:** `mitglieder/data/members.json` (git-ignoriert, server-only), JSON-Array. Ein Mitglied:
`id`, `name` (Anzeigename), `role` (`team` | `supporter` | `anwaerter`), `text` (Kurztext),
`icon` (Kürzel/Emoji, Ersatz-Avatar **wenn kein Foto**), `emoji` (kleines Icon **nach dem Text**,
optional, unabhängig vom Foto), `image` (Dateiname, optional, ein Foto), `consentGiven`/`consentAt`/
`consentSource` (Zustimmung zur öffentlichen Anzeige, siehe „Individuelle Logins &
Anzeige-Zustimmung"), `createdAt`/`updatedAt`. Die beiden Icon-Felder sind bewusst getrennt: `icon`
ersetzt das Foto, `emoji` ist ein dekoratives Symbol hinter dem Beschreibungstext. Die E-Mail-Adresse
und alle Login-Felder liegen **nicht** hier, sondern getrennt in `mitglieder/data/accounts.json`
(siehe unten). **Reihenfolge = Anzeige-Reihenfolge** (kein Sortieren; neue Einträge ans Ende).

**Rollen → Sektion in `index.html`:** `team` → „Das Team", `supporter` → „Dabei waren auch",
`anwaerter` → „Anwärter" (Grid oberhalb der Expeditionen). Container-IDs: `#team-grid`,
`#supporter-grid`, `#anwaerter-grid`.

**Seeding:** wie bei Expeditionen liefert `mitglieder/members-lib.php`s `load_members()` den
git-getrackten Ausgangsbestand `mitglieder/members-seed.json` (17 aus der alten index.html migrierte
Mitglieder), solange `data/members.json` fehlt. Startfotos liegen git-getrackt in
`mitglieder/members-seed-images/`.

**Admin-Bereich (`admin/`, außerhalb `mitglieder/`, daher KEIN Basic Auth):**
- Schutz über **PHP-Session-Login** (`admin/auth.php`): Benutzer `Admin`, Passwort nur als bcrypt-Hash
  (`ADMIN_PASS_HASH`, nie Klartext). Login/Logout, CSRF-Token auf allen mutierenden Formularen. Bewusst
  unabhängig vom Mitglied-Login, damit dessen Änderung den Admin nie aussperrt. Passwort ändern: neuen
  Hash erzeugen (`php -r "echo password_hash('NEUES_PW', PASSWORD_BCRYPT);"`) und in `admin/auth.php`
  eintragen, committen, deployen.
- `admin/index.php`: Login-Formular bzw. Dashboard mit Mitglieder-CRUD (nach Rolle gruppiert,
  E-Mail-Feld, Foto-Upload/-Entfernen), Panel „Zustimmungs-Übersicht" (`#zustimmungen-bereich`,
  Tabelle aller Mitglieder mit E-Mail, Verifiziert-Status, Zustimmungs-Status/-Datum/-Quelle und
  Button zur Bestands-Zustimmung) und Panel „E-Mail-Templates" (`#email-templates-bereich`).
- `admin/members-save.php`: `action=create|update|delete|delete-image|grant-consent`, Session- +
  CSRF-geschützt, ein Foto pro Mitglied (Bild-Validierung wie Expeditionen: Whitelist
  jpg/jpeg/png/webp, `getimagesize()`, max. 5 MB, serverseitiger Dateiname `<id>.<ext>`). Bei
  Angabe/Änderung einer E-Mail wird über `mitglieder/accounts-lib.php` ein Account angelegt/
  aktualisiert und die Willkommensmail verschickt; `grant-consent` setzt die Bestands-Zustimmung
  (siehe „Individuelle Logins & Anzeige-Zustimmung").
- `admin/email-templates-save.php`: speichert Betreff/Text der vier E-Mail-Templates, Session- +
  CSRF-geschützt.
- **Versteckter Zugang:** dezenter Link (unauffälliges `·`) im Footer von `index.html` → `/admin/`.
  Kein Menüeintrag; echte Absicherung ist der PHP-Login, nicht die Obskurität.

**Lesen/Bilder (Repo-Root, öffentlich, wie bei Expeditionen):**
- `members.php` liefert `data/members.json` (bzw. Seed) als JSON – **gefiltert auf
  `consentGiven === true`** und nur eine Feld-Whitelist (`id,name,role,text,icon,emoji,image`), damit
  weder nicht zustimmende Mitglieder noch interne Zustimmungs-Metadaten öffentlich sichtbar werden.
- `member-image.php` streamt ein Foto mit Pfad-Traversal-Schutz; sucht zuerst in
  `mitglieder/data/members-images/`, dann als Fallback in `mitglieder/members-seed-images/` – liefert
  nur aus, wenn das zugehörige Mitglied `consentGiven === true` hat.
- **`admin/member-image.php`** ist ein bewusst separater, admin-geschützter Zwilling (gleiche
  Pfad-Traversal-Logik, aber **ohne** Consent-Prüfung): Der Admin muss beim Verwalten immer alle
  Fotos sehen, unabhängig von der öffentlichen Zustimmung. `admin/index.php` nutzt für seine
  Foto-Vorschau in den Mitglieder-Formularen diesen Endpunkt, nicht den öffentlichen.
- `index.html` lädt per `fetch('/members.php')`, gruppiert nach Rolle und baut die `.team-member`-Karten
  per DOM auf (`textContent`, nie `innerHTML`); Foto via `member-image.php`, sonst Gradient-Avatar mit
  `icon`-Text.

## Datenübertragung (Admin)

Alle dynamischen, git-ignorierten Datenbestände (MOTD, Mitglieder, Expeditionen, Accounts,
E-Mail-Templates, Zustimmungs-Audit-Log – jeweils `mitglieder/data/...`, siehe oben) existieren pro
Umgebung (production/staging) getrennt und entstehen
ausschließlich durch Nutzereingaben in der App, nicht durch Deploys. Damit sie sich trotzdem sichern,
zwischen Umgebungen übertragen und für lokale Migrationen/Transformationen bearbeiten lassen, gibt es im
Admin-Bereich ein Export/Import als ein ZIP-Bundle. **Drei Zwecke in einem Mechanismus:** Backup
(Download als Sicherung), Übertragung zwischen Umgebungen (z. B. Staging → Production) und
Migration/Transformation (ZIP herunterladen, enthaltene JSON-Dateien lokal bei Bedarf anpassen,
anschließend wieder hochladen).

**Redaktions-Workflow Staging → Production:** Der Mechanismus ermöglicht, Inhaltsänderungen (neue
Mitglieder, neue Expedition, MOTD) zuerst auf Staging einzupflegen, dort in Ruhe auf der Website
anzusehen und zu prüfen, und die geprüften Daten dann gebündelt per Export/Import nach Production zu
übernehmen – analog zum bestehenden Code-Deployment (`develop` → `master`, siehe
„Deployment-Kontext" unten), nur für die dynamischen Inhalte statt für Code. Ohne diesen Mechanismus
müssten Inhalte doppelt von Hand in beiden Umgebungen gepflegt werden, mit dem Risiko voneinander
abweichender Stände.

- **Architektur:** `admin/data-transfer-lib.php` definiert eine zentrale Modul-Registry
  (`data_transfer_modules()`) mit den Modulen `motd`, `members`, `expeditions`, `accounts`,
  `email-templates`, `consent-log`. Jedes Modul hat eine `export`- und eine `import`-Funktion;
  Export-/Import-Endpunkt sowie die Admin-UI iterieren generisch über die Registry (jedes Modul im
  UI einzeln per Checkbox wähl-/abwählbar). **Erweiterbar:** ein künftiger weiterer dynamischer
  Datentyp nach demselben `data/`-Prinzip wird durch zwei neue Funktionen plus einen weiteren
  Registry-Eintrag ergänzt – die Endpunkte und die UI müssen dafür nicht angefasst werden. Bestehende
  `load_*()`/`save_*()`-Funktionen aus `mitglieder/members-lib.php`/`mitglieder/expeditions-lib.php`/
  `mitglieder/accounts-lib.php`/`mitglieder/email-templates-lib.php` werden wiederverwendet; für MOTD
  (bisher ohne eigene Lib-Datei) gibt es kleine `read_motd()`/`write_motd()`-Helfer in derselben Datei.
  **`consent-log` ist additiv statt vollständig ersetzend:** ein Import ergänzt nur fehlende
  Audit-Dateien, überschreibt/löscht nie vorhandene – ein Audit-Log darf durch einen Import keine
  Nachweise verlieren.
- **Sensibel:** das Modul `accounts` enthält Passwort-Hashes und E-Mail-Adressen – wie alle Module nur
  per Admin-Login herunterladbar, aber beim Umgang mit der ZIP-Datei besonders vorsichtig behandeln.
- **Bundle-Format:** ein ZIP-Archiv (`ZipArchive`) mit `manifest.json` (Version, Zeitstempel, Host,
  enthaltene Module) sowie je Modul der JSON-Datei und den referenzierten Bildern/Dateien
  (`members/members.json` + `members/images/...`, `expeditions/expeditions.json` +
  `expeditions/images/...`, `motd/motd.txt`, `accounts/accounts.json`,
  `email-templates/email-templates.json`, `consent-log/<datei>.json` je Zustimmung).
- **Export:** `admin/data-transfer-export.php` (Session- + CSRF-geschützt) baut das ZIP aus allen
  Modulen und liefert es als Download (`mobout-data-<host>-<Zeitstempel>.zip`).
- **Import:** `admin/data-transfer-import.php` (Session- + CSRF-geschützt) validiert das hochgeladene
  ZIP (Manifest-Version, Zip-Slip-Schutz für alle Eintragspfade, Bild-Validierung wie bei normalen
  Uploads: Whitelist jpg/jpeg/png/webp, `getimagesize()`, 5 MB/Bild, 50 MB/ZIP). Der Admin wählt per
  Checkbox, welche Module importiert werden sollen; **jedes ausgewählte Modul wird vollständig ersetzt**
  (keine Zusammenführung, Ausnahme `consent-log`: additiv, siehe oben) – vor dem Überschreiben wird
  automatisch ein Backup des Vorzustands nach
  `mitglieder/data/backups/<Zeitstempel>/<modul>/` geschrieben (git-ignoriert, wie `data/` insgesamt,
  vom Deploy-`--exclude=data/` mitgeschützt). Module sind unabhängig voneinander: schlägt die
  Validierung eines Moduls fehl, bleiben die anderen ausgewählten Module unberührt.
- **Backup-Rotation & Anzeige:** Damit `mitglieder/data/backups/` nicht unbegrenzt wächst, behält
  `data_transfer_prune_backups()` je Datentyp automatisch nur die letzten `DATA_TRANSFER_BACKUP_KEEP`
  (5) Sicherungen – ältere werden nach jedem Import gelöscht, kein Cron nötig. Damit die Sicherungen
  nicht unsichtbar auf dem Server liegen, listet das Admin-Panel „Datenübertragung" sie zusätzlich auf
  (Zeitstempel, enthaltene Module, Größe) mit einem Lösch-Button pro Eintrag
  (`admin/data-transfer-backup-delete.php`), falls früher aufgeräumt werden soll.
- **UI:** Panel „Datenübertragung" in `admin/index.php` (`#data-bereich`) mit Download-Button,
  Upload-Formular (Datei + Checkboxen je Modul, Standard: alle angehakt, JS-Bestätigung vor dem Absenden
  wegen der ersetzenden Wirkung) und der Sicherungsliste inkl. Lösch-Buttons.
- **Voraussetzung:** PHP-Erweiterung `ZipArchive` auf dem Strato-Webspace (Standard bei PHP 8.3).
- **Bekannte Lücke:** Es gibt noch keine automatisierte Wiederherstellung ("Restore") einer Sicherung
  aus dem Admin-UI – nur Anzeige und Löschen. Ein Zurückspielen ist aktuell nur manuell per SSH/FTP
  möglich (Datei aus `mitglieder/data/backups/<Zeitstempel>/<modul>/` an die Zielstelle kopieren). Falls
  das künftig gebraucht wird: eigener „Wiederherstellen"-Button neben „Löschen", der intern wie ein
  Import funktioniert (gleiche Backup-vorher-Logik, damit auch ein Restore rückgängig gemacht werden
  kann).

## Datenmigration & Cutover (Basic Auth → individuelle Logins)

Der Umstieg auf individuelle Accounts + Zustimmungs-Filterung ist **rückwärtskompatibel by design** –
kein destruktives Migrationsskript nötig: `load_members()` normalisiert fehlende Consent-Felder beim
Lesen (alte Datensätze bekommen sichere Defaults, werden erst beim nächsten Speichern persistiert);
`accounts.json`/`email-templates.json` sind additiv (Seed-Fallback bis zum ersten Schreiben). Cutover
pro Umgebung (erst Staging, dann Production): (1) Backup-ZIP über „Datenübertragung" ziehen, (2)
Code deployen, (3) verifizieren (öffentliche Seite lädt weiter, zeigt aber erstmal niemanden ohne
Zustimmung), (4) pro Mitglied im Admin die E-Mail nachtragen (Account + Willkommensmail) – alternativ
per Export→Bearbeiten→Import, falls Bestands-E-Mails vorliegen, (5) Bestands-Zustimmung für die
gewünschten Mitglieder setzen, damit die Seite nicht leer bleibt.

---

# Deployment-Kontext

## Ziel: Zwei Umgebungen

- `master`  → production → `/htdocs/mobout.de/production/`  (mobout.de)
- `develop` → staging    → `/htdocs/mobout.de/staging/`     (staging.mobout.de)

## Pipeline (bereits eingerichtet)

- Deployment über GitHub Actions, rsync über SSH zu Strato
- Secrets vorhanden: `STRATO_SSH_KEY`, `STRATO_HOST`, `STRATO_USER`
- Zwei-Ziel-Push: `git push origin <branch>` geht an NAS (Master-Backup) + GitHub
- Pull nur vom NAS (`origin` fetch = NAS, push = NAS + GitHub)
- Übertragen werden `index.html` + `motd.php` + `contact.php` + `expeditions.php` + `expedition-image.php` + `members.php` + `member-image.php` + `admin/` + `mitglieder/` (wenn `assets/` deployrelevant wird: Workflow anpassen)
- Kein Basic-Auth-Seed-Schritt mehr nötig (individuelle Accounts statt geteiltem Login): `accounts.json`, `email-templates.json`, `consent-log/` und die `data/`-Härtung entstehen zur Laufzeit (`member_ensure_data_hardening()` in `mitglieder/member-auth.php`) und sind über `--exclude=data/` bereits deploy-sicher

## Arbeitsweise

- Ein Entwickler: direkt auf `develop` arbeiten, dann nach `master` mergen für Produktion
- Feature-Branches nur lokal bei Bedarf, lösen kein Deployment aus

## Leitplanken

- `doitexcellent.de` niemals anfassen
- Schreibweise durchgängig englisch: `production` / `staging`

---

# Offene Punkte / Bekannte Einschränkungen

## HTTPS/SSL auf mobout.de — ✅ Gelöst für Production (2026-07-08)

**Status:** HTTPS ist **aktiv und erzwungen auf Production** (mobout.de). **staging.mobout.de läuft
bewusst nur über HTTP** (kein SSL-Zertifikat für die Subdomain, kein Redirect) – so gewollt, siehe
„Arbeitsweise": Staging ist reine Testumgebung, Production trägt das Zertifikat. Der Code muss daher
für beide Fälle funktionieren, siehe nächster Absatz.

**Was gemacht wurde:**
- **SSL-Zertifikat:** STRATO SSL Starter (DV) für mobout.de aktiviert (kostenlos für erste 6 Monate: 0,50 €/Monat, danach 3,50 €/Monat) – **nur für die Production-Domain**, nicht für staging.mobout.de
- **HTTP→HTTPS-Redirect:** Automatisch aktiviert durch Strato ("301-Weiterleitung" im SSL-Panel), gilt nur für mobout.de
- **HSTS-Header:** Root `.htaccess` mit HTTP Strict-Transport-Security (max-age=31536000; includeSubDomains; preload)
- **Session-Cookie-Sicherheit:** `admin/auth.php` und `mitglieder/member-auth.php` setzen die
  Cookie-Parameter **vor** `session_start()` und ermitteln `secure` dynamisch über
  `admin_request_is_https()` / `member_request_is_https()` (prüft `$_SERVER['HTTPS']`,
  `X-Forwarded-Proto`, Port 443): auf Production (HTTPS) hart `Secure`, auf der HTTP-Testumgebung
  staging ohne `Secure` – sonst würde der Browser das Session-Cookie über HTTP verwerfen und kein
  Login (weder Admin noch Mitglieder) würde dort funktionieren. Ein gefälschter
  `X-Forwarded-Proto`-Header kann das Cookie nur strenger machen (Secure), nie unsicherer – kein
  Downgrade-Risiko für Production. `HttpOnly` und `SameSite=Lax` gelten in beiden Umgebungen hart.

**Sicherheitsauswirkung (Production):**
- ✅ Admin-Passwort wird verschlüsselt übertragen (TLS in Strato 301-Redirect + Browser HTTPS)
- ✅ Mitglieder-Login (individuelle E-Mail-Accounts, PHP-Session) wird verschlüsselt übertragen
- ✅ Session-Cookies können nicht von JavaScript gelesen werden (XSS-Schutz)
- ✅ Cross-Site-Request-Forgery (CSRF) durch SameSite=Lax Cookies gemindert
- ✅ Browser merkt sich HTTPS für zukünftige Besuche (HSTS); Preload-Liste schützt auch erste Besuche
- ✅ Alte HTTP-Links werden automatisch zu HTTPS umgeleitet (nutzerfreundlich)

**Bewusste Einschränkung (Staging):** Auf staging.mobout.de laufen Logins unverschlüsselt (HTTP), da
keine Produktivdaten/-passwörter dort dauerhaft schützenswert sind und die Testumgebung explizit ohne
Zertifikat betrieben wird.

## Bekannte Falle: „Secure"-Altcookie blockiert Login auf Staging (Browser-seitig)

Wurde `staging.mobout.de` irgendwann versehentlich einmal über `https://` statt `http://` aufgerufen
(z. B. durch Browser-Autovervollständigung, HTTPS-Zwang des Browsers oder eine weggeklickte
Zertifikatswarnung), setzt PHP dabei korrekt ein `PHPSESSID`-Cookie mit `Secure`-Flag (siehe
`admin_request_is_https()`/`member_request_is_https()` oben – die Erkennung war in diesem Moment
technisch richtig, die Verbindung war ja wirklich HTTPS). Da Staging aber sonst nur über HTTP läuft,
kann der Browser dieses alte `Secure`-Cookie bei allen folgenden HTTP-Logins nicht mehr durch das
neue, korrekte (nicht-Secure) Cookie ersetzen – Browser verbieten grundsätzlich, ein `Secure`-Cookie
von einer unverschlüsselten Verbindung aus zu überschreiben. Die Session bleibt dadurch dauerhaft
leer: Login-Formular zeigt 302-Redirect, aber **keine** Fehlermeldung (weder „Passwort falsch" noch
sonst etwas) und man landet einfach wieder auf dem leeren Formular – sieht wie ein stiller Bug aus,
ist aber ein reines Browser-Cookie-Problem, kein Server-/Code-Fehler.

**Erkennung:** Entwickler-Tools → Netzwerk-Tab beim Login-Versuch prüfen – Chrome/Firefox zeigen bei
einer blockierten Cookie-Überschreibung eine explizite Warnung an der betroffenen Anfrage.

**Fix (nur im betroffenen Browser nötig):** Cookies/Website-Daten für `staging.mobout.de` löschen
(oder ein privates/Inkognito-Fenster nutzen) – danach wird das neue, nicht-Secure Cookie ohne
Konflikt gesetzt und der Login funktioniert wieder normal. Betrifft gleichermaßen Admin- und
Mitglieder-Login, da beide dasselbe Erkennungsmuster nutzen.
