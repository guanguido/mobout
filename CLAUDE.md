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
├── mitglieder/         # Passwortgeschützter Mitgliederbereich (Basic Auth)
│   ├── index.php       # Eigenständige Seite (eigenes CSS, Logo als Base64); rendert MOTD-Formular serverseitig
│   ├── motd-save.php   # Schreib-Endpunkt für die MOTD, geschützt durch .htaccess der Umgebung
│   ├── data/           # Nur serverseitig, git-ignoriert (motd.txt) – überlebt Deploys
│   ├── .htaccess       # Basic-Auth-Konfiguration (Auth-Pfad wird beim Deploy injiziert)
│   └── .htpasswd       # Ein Nutzer, bcrypt-Hash des gemeinsamen Passworts
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
- Punktuell PHP (Strato-Hosting, PHP 8.3) für die "Nachricht des Tages" und das Kontaktformular –
  beschränkt auf `mitglieder/index.php`, `mitglieder/motd-save.php`, `motd.php` und `contact.php`,
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

Die Excel-Dateien in `assets/data/` sind die Quelle der Wahrheit für:
- **MobOut_Teilnehmer.xlsx**: Name, Spitzname, Rolle (Kern-Crew/Gastangler/Anwärter), Dabei seit, Expeditionsjahre, Beschreibung
- **MobOut_Expeditionen.xlsx**: Jahr, Datum, Zielort, Teilnehmer, Besonderheiten, Fänge

Aktuell sind diese Daten als hartcodierter Text in `index.html` eingebaut (kein dynamisches Laden).

## Mitgliederbereich (`mitglieder/`)

Passwortgeschützter interner Bereich unter `mobout.de/mitglieder/` (eigene URL, nicht Teil der
Single-Page). Verlinkt aus der Hauptnavigation in `index.html` (Link `.nav-members`).

- **Schutz:** HTTP Basic Auth über `.htaccess` (Strato = Apache). Ein Nutzer, **ein Passwort für alle**.
- **Passwort:** `.htpasswd` mit bcrypt-Hash, **im Repo** eingecheckt (Repo ist privat).
  Ändern: neuen Hash erzeugen (`htpasswd -nbB mitglied '<PASSWORT>'`), committen, deployen.
- **Auth-Pfad:** `.htaccess` enthält Platzhalter `__HTPASSWD_PATH__`, der beim Deploy pro Branch
  durch den absoluten Serverpfad ersetzt wird (analog zu `__BUILD_INFO__`).
- **Seite:** `mitglieder/index.php` ist eigenständig (eigenes CSS, Logo als Base64), da `assets/`
  nicht auf den Server deployt wird.
- **Grenzen:** nativer Browser-Login (nicht gestaltbar), Logout browserabhängig; nur über HTTPS sicher.
- **Inhalt:** `mitglieder/index.php` zeigt Info-Karten für die Crew. Neben Platzhalter-Karten
  (interne Infos, Bilder posten, Downloads) gibt es die Karte "Navionics Account" mit den
  Zugangsdaten für den gemeinsamen Navionics-Account (Boating HD App, Tiefenkarten). Die Karte
  selbst in `mitglieder/index.php` ist die Quelle der Wahrheit für diese Zugangsdaten (nicht
  hier duplizieren). Abo läuft aktuell bis 14.05.2027 – bei Verlängerung/Änderung die Karte
  entsprechend aktualisieren.

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
- Übertragen werden `index.html` + `motd.php` + `mitglieder/` (wenn `assets/` deployrelevant wird: Workflow anpassen)

## Arbeitsweise

- Ein Entwickler: direkt auf `develop` arbeiten, dann nach `master` mergen für Produktion
- Feature-Branches nur lokal bei Bedarf, lösen kein Deployment aus

## Leitplanken

- `doitexcellent.de` niemals anfassen
- Schreibweise durchgängig englisch: `production` / `staging`
