<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$text = trim(substr($_POST['motd'] ?? '', 0, 500));
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}
file_put_contents($dataDir . '/motd.txt', $text);

header('Location: index.php');
exit;
