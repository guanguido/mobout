<?php

const IMAP_CONFIG_FILE = __DIR__ . '/data/imap-config.json';
const IMAP_CONFIG_SEED = __DIR__ . '/imap-config-seed.json';

function load_imap_config() {
  if (file_exists(IMAP_CONFIG_FILE)) {
    return json_decode(file_get_contents(IMAP_CONFIG_FILE), true) ?? [];
  }
  if (file_exists(IMAP_CONFIG_SEED)) {
    return json_decode(file_get_contents(IMAP_CONFIG_SEED), true) ?? [];
  }
  return [
    'host' => 'imap.strato.de',
    'port' => 993,
    'user' => 'info@mobout.de',
    'pass' => '',
  ];
}

function save_imap_config($config) {
  $data = [
    'host' => trim($config['host'] ?? ''),
    'port' => (int)($config['port'] ?? 993),
    'user' => trim($config['user'] ?? ''),
    'pass' => trim($config['pass'] ?? ''),
  ];
  file_put_contents(IMAP_CONFIG_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function test_imap_connection($host, $port, $user, $pass) {
  $host = trim($host);
  $port = (int)$port;
  $user = trim($user);
  $pass = trim($pass);

  if (empty($host) || empty($port) || empty($user) || empty($pass)) {
    return ['ok' => false, 'error' => 'Alle Felder erforderlich'];
  }

  if (!function_exists('imap_open')) {
    return ['ok' => false, 'error' => 'IMAP-Extension nicht aktiviert. Kontaktiere deinen Hosting-Provider (Strato).'];
  }

  $mailbox = "{" . $host . ":" . $port . "/imap/ssl}INBOX";

  $errors = [];
  set_error_handler(function($errno, $errstr) use (&$errors) {
    $errors[] = $errstr;
  });

  $stream = @imap_open($mailbox, $user, $pass);
  restore_error_handler();

  if ($stream === false) {
    $errorMsg = !empty($errors) ? implode(' | ', $errors) : 'Verbindung fehlgeschlagen';
    return ['ok' => false, 'error' => $errorMsg . ' (Prüfe: Server-Adresse, Port, Login, Passwort)'];
  }

  @imap_close($stream);
  return ['ok' => true, 'message' => 'Verbindung erfolgreich! ✓'];
}

function get_unread_count($host, $port, $user, $pass) {
  if (!function_exists('imap_open')) {
    return 0;
  }

  $mailbox = "{" . trim($host) . ":" . (int)$port . "/imap/ssl}INBOX";

  set_error_handler(function() {});
  $stream = @imap_open($mailbox, trim($user), trim($pass));
  restore_error_handler();

  if ($stream === false) {
    return -1;
  }

  $unseen = @imap_search($stream, 'UNSEEN', SE_UID);
  @imap_close($stream);

  return $unseen === false ? 0 : count($unseen);
}

function get_mail_preview($host, $port, $user, $pass, $limit = 5) {
  if (!function_exists('imap_open')) {
    return [];
  }

  $mailbox = "{" . trim($host) . ":" . (int)$port . "/imap/ssl}INBOX";

  set_error_handler(function() {});
  $stream = @imap_open($mailbox, trim($user), trim($pass));
  restore_error_handler();

  if ($stream === false) {
    return [];
  }

  $total = @imap_num_msg($stream);
  $start = max(1, $total - $limit + 1);

  $mails = [];
  for ($i = $total; $i >= $start && count($mails) < $limit; $i--) {
    $header = @imap_headerinfo($stream, $i);
    if ($header === false) {
      continue;
    }

    $from = isset($header->from[0]) ? $header->from[0]->mailbox . '@' . $header->from[0]->host : '(unbekannt)';
    $subject = isset($header->subject) ? $header->subject : '(kein Betreff)';
    $subject = mb_decode_mimeheader($subject);

    $date = '';
    if (isset($header->date)) {
      $ts = strtotime($header->date);
      if ($ts !== false) {
        $today = strtotime('today');
        $yesterday = strtotime('yesterday');
        if ($ts >= $today) {
          $date = date('H:i', $ts);
        } elseif ($ts >= $yesterday) {
          $date = 'gestern ' . date('H:i', $ts);
        } else {
          $date = date('d.m.Y', $ts);
        }
      }
    }

    $mails[] = [
      'from' => $from,
      'subject' => $subject,
      'date' => $date,
    ];
  }

  @imap_close($stream);
  return $mails;
}
