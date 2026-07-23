<?php
// Änderungshistorie: rein anzeigende Admin-Seite, kein eigenes CSRF nötig (keine
// Formulare hier, analog zu help.php). Die Daten kommen ausschließlich aus
// changelog-lib.php / changelog-data.txt (siehe dort für die Herkunft).
require __DIR__ . '/auth.php';

require_admin();
require __DIR__ . '/changelog-lib.php';

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$data = changelog_read();
$entries = $data['entries'];
$productionHead = $data['productionHead'];
$ownHead = $entries[0] ?? null;

$page = max(1, (int) ($_GET['page'] ?? 1));
$paged = changelog_paginate($entries, $page, 30);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Änderungshistorie | MobOut Administration</title>
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
            max-width: 1100px; margin: 0 auto; padding: 0 2rem; display: flex;
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
        main { max-width: 1100px; margin: 0 auto; padding: 3rem 2rem; }
        h1.page-title { color: var(--primary-color); font-size: 1.8rem; margin-bottom: 0.5rem; }
        .intro { color: #666; margin-bottom: 1rem; }
        .version-info {
            background: var(--light-bg); border: 1px solid var(--border-color); border-radius: 8px;
            padding: 0.9rem 1.1rem; margin-bottom: 2rem; font-size: 0.92rem;
        }
        .version-info code { background: #eef2f5; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.92em; }
        .empty-hint { color: #666; background: var(--light-bg); border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; }
        .changelog-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .changelog-table th, .changelog-table td { text-align: left; padding: 0.55rem 0.7rem; border-bottom: 1px solid var(--border-color); vertical-align: top; }
        .changelog-table th { color: var(--secondary-color); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em; }
        .changelog-table tr.is-production { background: #fff7ee; }
        .changelog-table tr.is-production td:first-child { border-left: 3px solid var(--accent-color); }
        .hash { font-family: Consolas, Monaco, monospace; }
        .badge { display: inline-block; padding: 0.1rem 0.55rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
        .badge-feature { background: #e8f6ea; color: #1e6b2e; }
        .badge-fix { background: #fdecea; color: #b71c1c; }
        .badge-other { background: #eef2f5; color: #555; }
        .badge-production { background: var(--accent-color); color: white; margin-left: 0.4rem; }
        .branch-name { font-family: Consolas, Monaco, monospace; font-size: 0.85em; color: #666; }
        .none { color: #999; }
        .pagination { display: flex; align-items: center; gap: 1rem; margin-top: 1.25rem; font-size: 0.9rem; }
        .pagination a { color: var(--secondary-color); text-decoration: none; font-weight: 500; }
        .pagination a:hover { text-decoration: underline; }
        .pagination .disabled { color: #bbb; }
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
                <div class="subtitle">Administration &middot; Änderungshistorie</div>
            </div>
            <nav>
                <a href="index.php">&larr; Zum Dashboard</a>
                <a href="index.php?logout=1">Abmelden</a>
            </nav>
        </div>
    </header>

    <main>
        <h1 class="page-title">Änderungshistorie</h1>
        <p class="intro">Chronologischer Verlauf aller Deploys dieses Systems, automatisch aus der Git-Historie erzeugt &ndash; keine manuelle Pflege nötig. Neueste Änderungen zuerst.</p>

        <?php if ($ownHead !== null): ?>
            <div class="version-info">
                Aktuell deployte Version dieses Systems: <code><?= h($ownHead['shortHash']) ?></code>
                vom <?= h(changelog_format_date($ownHead['date'])) ?>
                &ndash; dieselbe Kurz-ID wie im Footer der öffentlichen Website.
                <?php if ($productionHead !== null && $ownHead['hash'] !== $productionHead): ?>
                    Der aktuell produktive Commit ist unten in der Tabelle mit
                    <span class="badge badge-production">Produktiv</span> markiert.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($entries)): ?>
            <p class="empty-hint">Noch keine Änderungshistorie verfügbar &ndash; die Datengrundlage wird erst beim nächsten Deploy erzeugt.</p>
        <?php else: ?>
            <table class="changelog-table">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Commit-ID</th>
                        <th>Kurzbeschreibung</th>
                        <th>Art</th>
                        <th>Feature-Branch</th>
                        <th>Autor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paged['items'] as $entry): ?>
                        <?php $isProd = changelog_is_production_head($entry, $productionHead); ?>
                        <tr class="<?= $isProd ? 'is-production' : '' ?>">
                            <td><?= h(changelog_format_date($entry['date'])) ?></td>
                            <td>
                                <span class="hash"><?= h($entry['shortHash']) ?></span>
                                <?php if ($isProd): ?><span class="badge badge-production">Produktiv</span><?php endif; ?>
                            </td>
                            <td><?= h($entry['subject']) ?></td>
                            <td><span class="badge <?= h($entry['type']['css']) ?>"><?= h($entry['type']['label']) ?></span></td>
                            <td>
                                <?php if ($entry['branch'] !== null): ?>
                                    <span class="branch-name"><?= h($entry['branch']) ?></span>
                                <?php else: ?>
                                    <span class="none">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($entry['author']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php if ($paged['page'] > 1): ?>
                    <a href="?page=<?= $paged['page'] - 1 ?>">&larr; Neuer</a>
                <?php else: ?>
                    <span class="disabled">&larr; Neuer</span>
                <?php endif; ?>
                <span>Seite <?= $paged['page'] ?> von <?= $paged['totalPages'] ?> (<?= $paged['total'] ?> Commits)</span>
                <?php if ($paged['page'] < $paged['totalPages']): ?>
                    <a href="?page=<?= $paged['page'] + 1 ?>">Älter &rarr;</a>
                <?php else: ?>
                    <span class="disabled">Älter &rarr;</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <a class="back-link" href="index.php">&larr; Zurück zum Dashboard</a>
    </main>

    <footer>
        MobOut Administration &middot; nur für den Admin
    </footer>
</body>
</html>
