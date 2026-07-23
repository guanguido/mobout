<?php
// Änderungshistorie: rein lesende Lib, keine Schreibfunktionen. Die Datengrundlage
// (changelog-data.txt) wird nicht zur Laufzeit erzeugt, sondern bei jedem Deploy von
// der GitHub-Actions-Pipeline aus "git log" exportiert (siehe deploy-strato.yml,
// Step "Export Git-Änderungshistorie") - das deployte Server-Filesystem hat kein
// .git-Verzeichnis und PHP hat in diesem Projekt noch nie shell_exec()/exec()
// verwendet, daher kann hier nicht zur Laufzeit "git" gefragt werden.

define('CHANGELOG_DATA_FILE', __DIR__ . '/changelog-data.txt');
define('CHANGELOG_FIELD_SEP', "\x1f");

// Reihenfolge der Felder pro Zeile in changelog-data.txt, siehe deploy-strato.yml:
// %H (voller Hash) | %an (Autor) | %aI (ISO-Datum) | %P (Parent-Hashes) | %s (Subject)

function changelog_read(): array
{
    if (!is_file(CHANGELOG_DATA_FILE)) {
        return ['productionHead' => null, 'entries' => []];
    }

    $lines = file(CHANGELOG_DATA_FILE, FILE_IGNORE_NEW_LINES);
    if ($lines === false || count($lines) === 0) {
        return ['productionHead' => null, 'entries' => []];
    }

    $productionHead = null;
    $firstLine = array_shift($lines);
    if (str_starts_with($firstLine, 'PRODUCTION_HEAD=')) {
        $productionHead = trim(substr($firstLine, strlen('PRODUCTION_HEAD='))) ?: null;
    } else {
        // Erste Zeile war unerwarteterweise schon ein Commit - nicht verwerfen.
        array_unshift($lines, $firstLine);
    }

    $entries = [];
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $fields = explode(CHANGELOG_FIELD_SEP, $line);
        if (count($fields) < 5) {
            continue;
        }
        [$hash, $author, $date, $parents, $subject] = $fields;
        $hash = trim($hash);
        if ($hash === '') {
            continue;
        }
        $parentCount = trim($parents) === '' ? 0 : count(preg_split('/\s+/', trim($parents)));

        $entries[] = [
            'hash' => $hash,
            'shortHash' => substr($hash, 0, 7),
            'author' => $author,
            'date' => $date,
            'subject' => $subject,
            'parentCount' => $parentCount,
            'type' => changelog_classify_type($subject),
            'branch' => changelog_extract_branch($subject, $parentCount),
        ];
    }

    return [
        'productionHead' => $productionHead,
        'entries' => $entries,
    ];
}

// Klassifiziert anhand der seit Mitte 2026 genutzten Commit-Konvention
// ("Feat: ...", "Fix: ...", "Chore: ...", "Docs: ..."). Ältere bzw. unpräfixierte
// Commits (vor dieser Konvention) fallen bewusst in "Sonstige" statt geraten zu
// werden - siehe CLAUDE.md "Änderungshistorie (Admin)".
function changelog_classify_type(string $subject): array
{
    if (preg_match('/^Feat(?:ure)?:/i', $subject)) {
        return ['key' => 'feature', 'label' => 'Feature', 'css' => 'badge-feature'];
    }
    if (preg_match('/^Fix:/i', $subject)) {
        return ['key' => 'fix', 'label' => 'Fix', 'css' => 'badge-fix'];
    }
    return ['key' => 'other', 'label' => 'Sonstige', 'css' => 'badge-other'];
}

// Liefert den Feature-Branch-Namen nur für echte Merge-Commits (>= 2 Parents) mit dem
// Muster "Merge <branch> into develop". "Merge develop into master" ist eine
// Staging->Production-Promotion, kein Feature-Branch, und wird bewusst ausgeschlossen
// (siehe CLAUDE.md "Arbeitsweise"). Der Großteil der Historie ist linear (direkte
// Commits auf develop) und liefert hier immer null - kein Raten.
function changelog_extract_branch(string $subject, int $parentCount): ?string
{
    if ($parentCount < 2) {
        return null;
    }
    if (preg_match('/^Merge\s+(?:branch\s+)?[\'"]?([A-Za-z0-9._\/-]+)[\'"]?\s+into\s+develop\b/i', $subject, $m)) {
        return $m[1];
    }
    return null;
}

function changelog_is_production_head(array $entry, ?string $productionHead): bool
{
    return $productionHead !== null && $entry['hash'] === $productionHead;
}

// Anzeige in Berlin-Zeit, gleiche Zeitzonen-Konvention wie BUILD_TS im Deploy-Workflow.
function changelog_format_date(string $iso): string
{
    try {
        $dt = new DateTimeImmutable($iso);
        $dt = $dt->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $dt->format('d.m.Y H:i');
    } catch (Exception $e) {
        return $iso;
    }
}

function changelog_paginate(array $entries, int $page, int $perPage = 30): array
{
    $total = count($entries);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    return [
        'items' => array_slice($entries, $offset, $perPage),
        'page' => $page,
        'totalPages' => $totalPages,
        'total' => $total,
    ];
}
