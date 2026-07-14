<?php
// Gemeinsame Lese-/Schreibfunktionen für den Besucherzähler. Zwei Dateien:
// visitor-counter.json (dauerhaft: Gesamtzahlen) und visitor-counter-today.json
// (transient: SHA-256-Hashes aus Datum+IP+User-Agent des laufenden Tages, dienen
// ausschließlich dem Entduplizieren "eindeutiger" Besucher und rotieren automatisch
// beim ersten Request eines neuen Tages - es wird nie eine IP-Adresse dauerhaft
// gespeichert, nur die beiden aggregierten Zahlen).

define('COUNTER_DATA_FILE', __DIR__ . '/data/visitor-counter.json');
define('COUNTER_TODAY_FILE', __DIR__ . '/data/visitor-counter-today.json');

function counter_defaults(): array
{
    return ['totalViews' => 0, 'uniqueVisitors' => 0, 'updatedAt' => null];
}

function read_visitor_counter(): array
{
    if (!is_file(COUNTER_DATA_FILE)) {
        return counter_defaults();
    }
    $data = json_decode((string) file_get_contents(COUNTER_DATA_FILE), true);
    return is_array($data) ? array_merge(counter_defaults(), $data) : counter_defaults();
}

function write_visitor_counter(array $data): void
{
    $dir = dirname(COUNTER_DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(COUNTER_DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// Erhöht bei jedem Aufruf totalViews; uniqueVisitors nur, wenn der (Datum+IP+UA)-Hash
// heute noch nicht gesehen wurde. Die gesamte Lese-Ändern-Schreiben-Operation (inkl.
// der Tagesdatei) läuft innerhalb eines einzigen flock(LOCK_EX) auf COUNTER_DATA_FILE,
// damit gleichzeitige Besucher sich nicht gegenseitig Updates überschreiben.
function record_visit(string $ip, string $userAgent): void
{
    $dir = dirname(COUNTER_DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $handle = fopen(COUNTER_DATA_FILE, 'c+');
    if ($handle === false) {
        return;
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return;
    }

    $raw = stream_get_contents($handle);
    $counter = json_decode((string) $raw, true);
    $counter = is_array($counter) ? array_merge(counter_defaults(), $counter) : counter_defaults();

    $today = date('Y-m-d');
    $hash = hash('sha256', $today . '|' . $ip . '|' . $userAgent);

    $todayData = json_decode((string) @file_get_contents(COUNTER_TODAY_FILE), true);
    if (!is_array($todayData) || ($todayData['date'] ?? '') !== $today || !is_array($todayData['hashes'] ?? null)) {
        $todayData = ['date' => $today, 'hashes' => []];
    }

    $counter['totalViews']++;
    if (!in_array($hash, $todayData['hashes'], true)) {
        $todayData['hashes'][] = $hash;
        $counter['uniqueVisitors']++;
    }
    $counter['updatedAt'] = date('c');

    // Tagesdatei innerhalb derselben kritischen Sektion schreiben, bevor die Sperre
    // auf COUNTER_DATA_FILE wieder freigegeben wird.
    file_put_contents(COUNTER_TODAY_FILE, json_encode($todayData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($counter, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}
