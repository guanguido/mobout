# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# MobOut – Angelgruppe Website

Statische Website für die Angelgruppe MobOut (www.mobout.de). Die Gruppe fährt jedes Jahr gemeinsam zum Angeln an wechselnde Orte (z. B. Schweden, Havel, Müritz).

## Struktur

```
mobout/
├── index.html          # Haupt-HTML (Single-Page, enthält CSS + JS, Bilder als Base64 eingebettet)
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

## Sprache

Deutsch (de)

## Kontakt / Domain

- Domain: www.mobout.de
- E-Mail: info@mobout.de

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
- Aktuell wird nur `index.html` übertragen (wenn `assets/` deployrelevant wird: Workflow anpassen)

## Arbeitsweise

- Ein Entwickler: direkt auf `develop` arbeiten, dann nach `master` mergen für Produktion
- Feature-Branches nur lokal bei Bedarf, lösen kein Deployment aus

## Leitplanken

- `doitexcellent.de` niemals anfassen
- Schreibweise durchgängig englisch: `production` / `staging`
