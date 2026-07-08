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
│   ├── index.php       # Login + Dashboard: Mitglieder-CRUD + Mitglied-Login setzen (Logo als Base64)
│   ├── auth.php        # Session-Auth, hardcodierter Admin (User "Admin" + bcrypt-Hash), CSRF-Helfer
│   ├── account-lib.php # Lesen/Schreiben des geteilten Mitglied-Logins (data/.htpasswd)
│   ├── members-save.php # CRUD-Schreib-Endpunkt für Mitglieder (action=create/update/delete/delete-image)
│   └── account-save.php # Setzt Benutzername + Passwort des Mitglied-Logins (schreibt data/.htpasswd)
├── mitglieder/         # Passwortgeschützter Mitgliederbereich (Basic Auth)
│   ├── index.php       # Eigenständige Seite (eigenes CSS, Logo als Base64); rendert MOTD- und Expeditionen-Formulare serverseitig
│   ├── motd-save.php   # Schreib-Endpunkt für die MOTD, geschützt durch .htaccess der Umgebung
│   ├── expeditions-save.php  # CRUD-Schreib-Endpunkt für Expeditionen (action=create/update/delete/delete-image)
│   ├── expeditions-lib.php   # Gemeinsame load/save-Funktionen inkl. Seeding-Logik
│   ├── expeditions-seed.json # Git-getrackter Ausgangsbestand der 8 historischen Expeditionen
│   ├── members-lib.php       # Gemeinsame load/save-Funktionen für Mitglieder inkl. Seeding-Logik
│   ├── members-seed.json     # Git-getrackter Ausgangsbestand der 17 migrierten Mitglieder
│   ├── members-seed-images/  # Git-getrackte Startfotos der Mitglieder (Fallback von member-image.php)
│   ├── data/           # Nur serverseitig, git-ignoriert (motd.txt, expeditions.json, expeditions-images/, members.json, members-images/, .htpasswd) – überlebt Deploys
│   ├── .htaccess       # Basic-Auth-Konfiguration (Auth-Pfad wird beim Deploy injiziert → data/.htpasswd)
│   └── .htpasswd       # Git-getrackter Default/Seed des Mitglied-Logins (aktive Datei liegt in data/.htpasswd)
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
  dynamische Expeditionen-Verwaltung und die Mitglieder-Verwaltung (Admin) – beschränkt auf
  `mitglieder/index.php`, `mitglieder/motd-save.php`, `motd.php`, `contact.php`, `expeditions.php`,
  `expedition-image.php`, `mitglieder/expeditions-save.php`, `mitglieder/expeditions-lib.php`,
  `members.php`, `member-image.php`, `mitglieder/members-lib.php` und `admin/*.php`,
  siehe eigene Abschnitte unten

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

Passwortgeschützter interner Bereich unter `mobout.de/mitglieder/` (eigene URL, nicht Teil der
Single-Page). Verlinkt aus der Hauptnavigation in `index.html` (Link `.nav-members`).

- **Schutz:** HTTP Basic Auth über `.htaccess` (Strato = Apache). Ein Nutzer, **ein Passwort für alle**.
- **Passwort (aktiv):** Die aktive `.htpasswd` liegt deploy-sicher unter `mitglieder/data/.htpasswd`
  (git-ignoriert, überlebt `rsync --delete`). Sie wird über die **Admin-Seite** gesetzt/geändert
  (siehe „Mitglieder-Verwaltung (Admin)"), damit Änderungen keinen Deploy brauchen und Deploys überstehen.
- **Passwort (Seed/Default):** `mitglieder/.htpasswd` mit bcrypt-Hash bleibt **im Repo** eingecheckt
  (Repo ist privat), dient aber nur noch als Ausgangsbestand: Der Deploy kopiert ihn einmalig nach
  `data/.htpasswd`, falls dort noch keiner existiert (`test -f data/.htpasswd || cp .htpasswd data/.htpasswd`).
- **Auth-Pfad:** `.htaccess` enthält Platzhalter `__HTPASSWD_PATH__`, der beim Deploy pro Branch durch den
  absoluten Serverpfad `…/mitglieder/data/.htpasswd` ersetzt wird (analog zu `__BUILD_INFO__`).
- **Seite:** `mitglieder/index.php` ist eigenständig (eigenes CSS, Logo als Base64), da `assets/`
  nicht auf den Server deployt wird.
- **Grenzen:** nativer Browser-Login (nicht gestaltbar), Logout browserabhängig; nur über HTTPS sicher.
- **Inhalt:** `mitglieder/index.php` zeigt Info-Karten für die Crew: "Expeditionen" (Verwaltung,
  siehe eigener Abschnitt), "Instagram", "Navionics Account" und "Nachricht des Tages".
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

## Nachricht des Tages (MOTD)

Kleines PHP-Testfeature, um den Mechanismus "Mitglied editiert im geschützten Bereich → Inhalt
erscheint automatisch auf der öffentlichen Website" zu validieren:

- Mitglied trägt im Mitgliederbereich (Karte "Nachricht des Tages") einen Text ein und speichert
  über `mitglieder/motd-save.php` (liegt im geschützten Verzeichnis, erbt den Basic-Auth-Schutz
  automatisch). Der Text landet in `mitglieder/data/motd.txt` auf dem Server.
- `mitglieder/data/` ist **git-ignoriert** (server-only). Der Deploy-Workflow nutzt `rsync --delete`
  (damit umbenannte/gelöschte Dateien wie alte `.html`-Versionen wirklich vom Server verschwinden),
  schließt `mitglieder/data/` aber explizit per `--exclude=data/` von der Löschung aus, damit
  `motd.txt` Deploys übersteht.
- `motd.php` (Repo-Root, **nicht** durch Basic Auth geschützt) liest die Datei serverseitig vom
  Dateisystem aus und liefert sie unverändert als `text/plain` aus – funktioniert trotz Apache-Auth
  auf `mitglieder/`, weil diese nur HTTP-Requests durch Apache betrifft, nicht lokale
  Dateisystemzugriffe. Kein HTML-Escaping nötig/gewollt, da der Text nie als HTML interpretiert wird.
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
- **Schreiben:** `mitglieder/expeditions-save.php` (geschützt durch `.htaccess`) verarbeitet alle
  Mutationen über einen `action`-Parameter (`create` / `update` / `delete` / `delete-image`),
  inklusive Bild-Upload-Validierung (Whitelist jpg/jpeg/png/webp, `getimagesize()`-Check, max. 5 MB
  pro Bild, max. 8 Bilder pro Expedition, serverseitig generierte Dateinamen statt Original-Namen).
- **Lesen:** `expeditions.php` (Repo-Root, **nicht** durch Basic Auth geschützt) liest die Daten
  serverseitig vom Dateisystem – funktioniert wie `motd.php` trotz Apache-Auth auf `mitglieder/`,
  da reiner Dateisystemzugriff keine HTTP-Requests auf das geschützte Verzeichnis sind.
- **Bilder ausliefern:** `expedition-image.php` (Repo-Root, ebenfalls kein Basic Auth) liest eine
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
  Login-Seite. Verwaltet Mitglieder und den Mitglied-Login.
- **Mitglied-Login** = *ein* geteilter Basic-Auth-Zugang (Benutzername + Passwort) für
  `mobout.de/mitglieder/`, für alle Teilnehmer gleich. Wird vom Admin gesetzt.
- **Mitglieder/Teilnehmer** = die angezeigten Personen (Datensätze). Es gibt **keine** individuellen
  Mitglieder-Logins und keine Selbstbearbeitung – alles läuft über den Admin.

**Datenmodell:** `mitglieder/data/members.json` (git-ignoriert, server-only), JSON-Array. Ein Mitglied:
`id`, `name` (Anzeigename), `role` (`team` | `supporter` | `anwaerter`), `text` (Kurztext),
`icon` (Kürzel/Emoji, Ersatz-Avatar **wenn kein Foto**), `emoji` (kleines Icon **nach dem Text**,
optional, unabhängig vom Foto), `image` (Dateiname, optional, ein Foto), `createdAt`/`updatedAt`.
Die beiden Icon-Felder sind bewusst getrennt: `icon` ersetzt das Foto, `emoji` ist ein dekoratives
Symbol hinter dem Beschreibungstext. **Reihenfolge = Anzeige-Reihenfolge** (kein Sortieren; neue
Einträge ans Ende).

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
- `admin/index.php`: Login-Formular bzw. Dashboard mit Mitglieder-CRUD (nach Rolle gruppiert, Foto-Upload
  und -Entfernen) und Panel „Mitglied-Login" (Benutzername + Passwort setzen).
- `admin/members-save.php`: `action=create|update|delete|delete-image`, Session- + CSRF-geschützt,
  ein Foto pro Mitglied (Bild-Validierung wie Expeditionen: Whitelist jpg/jpeg/png/webp,
  `getimagesize()`, max. 5 MB, serverseitiger Dateiname `<id>.<ext>`).
- `admin/account-save.php` + `admin/account-lib.php`: schreiben `mitglieder/data/.htpasswd`
  (bcrypt via `password_hash`), Benutzername-Validierung (kein `:`), Passwort min. 6 Zeichen.
- **Versteckter Zugang:** dezenter Link (unauffälliges `·`) im Footer von `index.html` → `/admin/`.
  Kein Menüeintrag; echte Absicherung ist der PHP-Login, nicht die Obskurität.

**Lesen/Bilder (Repo-Root, kein Basic Auth, wie bei Expeditionen):**
- `members.php` liefert `data/members.json` (bzw. Seed) als JSON.
- `member-image.php` streamt ein Foto mit Pfad-Traversal-Schutz; sucht zuerst in
  `mitglieder/data/members-images/`, dann als Fallback in `mitglieder/members-seed-images/`.
- `index.html` lädt per `fetch('/members.php')`, gruppiert nach Rolle und baut die `.team-member`-Karten
  per DOM auf (`textContent`, nie `innerHTML`); Foto via `member-image.php`, sonst Gradient-Avatar mit
  `icon`-Text.

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
- Nach dem rsync seedet ein SSH-Schritt einmalig `mitglieder/data/.htpasswd` aus `mitglieder/.htpasswd`, falls noch nicht vorhanden (Mitglied-Login überlebt so Deploys)

## Arbeitsweise

- Ein Entwickler: direkt auf `develop` arbeiten, dann nach `master` mergen für Produktion
- Feature-Branches nur lokal bei Bedarf, lösen kein Deployment aus

## Leitplanken

- `doitexcellent.de` niemals anfassen
- Schreibweise durchgängig englisch: `production` / `staging`

---

# Offene Punkte / Bekannte Einschränkungen

## HTTPS/SSL fehlt auf mobout.de (Sicherheitsrisiko) — wird im nächsten Feature gelöst

Auf `mobout.de` (und `staging.mobout.de`) ist **kein SSL aktiv** (der Server bricht den TLS-Handshake
ab). Konsequenz: Beide Passwörter gehen **im Klartext** übers Netz:
- **Mitglied-Login** (Basic Auth `mitglieder/`): nur Base64-kodiert → mitlesbar.
- **Admin-Login** (`admin/`): POST-Body `password=…` unverschlüsselt.
Der Admin ist der kritischere Fall (verwaltet alle Mitglieder + setzt den Mitglied-Login).

**Lösungsweg (Infrastruktur, nicht Code):**
- Selbst-signiertes/eigenes Zertifikat ist **kein** gangbarer Weg: auf Strato-Shared-Hosting nicht
  installierbar, außerdem Browser-Warnseiten + keine Echtheitsgarantie.
- Bevorzugt: im Strato-Panel prüfen, ob **kostenloses SSL (Let's Encrypt) pro Domain** verfügbar ist
  (viele aktuelle Pakete bieten das für alle Domains, nicht nur eine — das eine bezahlte/genutzte Cert
  liegt derzeit auf `doitexcellent.de`). Alternativ **Cloudflare Free** als vertrauenswürdiges
  Edge-Zertifikat davorschalten.

**Repo-seitige TODOs, sobald ein Zertifikat live ist:**
- HTTP→HTTPS-Redirect + HSTS (z. B. in einer Root-`.htaccess`) — erst **nach** aktivem Cert, sonst legt
  der Redirect die Seite lahm.
- Session-Cookie in `admin/auth.php` härten: `HttpOnly` + `SameSite=Lax` (jederzeit gefahrlos),
  `Secure` sobald HTTPS anliegt.
