<?php
require_once __DIR__ . '/../mitglieder/imap-lib.php';

$config = load_imap_config();
$test_result = null;
$test_message = '';
$test_ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  require_admin();

  if ($_POST['action'] === 'test') {
    member_check_csrf($_POST['csrf'] ?? '');
    $result = test_imap_connection(
      $_POST['host'] ?? '',
      $_POST['port'] ?? '',
      $_POST['user'] ?? '',
      $_POST['pass'] ?? ''
    );
    $test_result = $result;
    $test_ok = $result['ok'];
    $test_message = $test_ok ? $result['message'] : $result['error'];
  }
}

$csrf = member_generate_csrf();
?>

<div id="email-config-bereich" class="admin-panel">
  <h2>📧 E-Mail-Verwaltung</h2>
  <p class="admin-info">Konfigurieren Sie die IMAP-Zugangsdaten für info@mobout.de. Diese Daten werden nur im Admin-Panel sichtbar und nicht öffentlich ausgeliefert.</p>

  <form method="POST" class="admin-form">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <input type="hidden" name="action" value="test">

    <div class="form-group">
      <label for="host">IMAP-Server:</label>
      <input type="text" id="host" name="host" value="<?php echo htmlspecialchars($config['host'] ?? ''); ?>" placeholder="imap.strato.de" required>
    </div>

    <div class="form-group">
      <label for="port">Port:</label>
      <input type="number" id="port" name="port" value="<?php echo htmlspecialchars($config['port'] ?? 993); ?>" placeholder="993" required>
    </div>

    <div class="form-group">
      <label for="user">Login (E-Mail):</label>
      <input type="email" id="user" name="user" value="<?php echo htmlspecialchars($config['user'] ?? ''); ?>" placeholder="info@mobout.de" required>
    </div>

    <div class="form-group">
      <label for="pass">Passwort:</label>
      <input type="password" id="pass" name="pass" value="<?php echo htmlspecialchars($config['pass'] ?? ''); ?>" placeholder="••••••••" required>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-secondary">🔄 Verbindung prüfen</button>
    </div>
  </form>

  <?php if ($test_result !== null): ?>
    <div class="admin-message <?php echo $test_ok ? 'success' : 'error'; ?>">
      <?php if ($test_ok): ?>
        ✅ <?php echo htmlspecialchars($test_message); ?>
      <?php else: ?>
        ❌ <?php echo htmlspecialchars($test_message); ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="POST" id="save-imap-config-form" action="imap-config-save.php" class="admin-form" style="margin-top: 2rem; border-top: 1px solid var(--color-border); padding-top: 2rem;">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">

    <div class="form-group">
      <label for="host-save">IMAP-Server:</label>
      <input type="text" id="host-save" name="host" value="<?php echo htmlspecialchars($config['host'] ?? ''); ?>" placeholder="imap.strato.de" required>
    </div>

    <div class="form-group">
      <label for="port-save">Port:</label>
      <input type="number" id="port-save" name="port" value="<?php echo htmlspecialchars($config['port'] ?? 993); ?>" placeholder="993" required>
    </div>

    <div class="form-group">
      <label for="user-save">Login (E-Mail):</label>
      <input type="email" id="user-save" name="user" value="<?php echo htmlspecialchars($config['user'] ?? ''); ?>" placeholder="info@mobout.de" required>
    </div>

    <div class="form-group">
      <label for="pass-save">Passwort:</label>
      <input type="password" id="pass-save" name="pass" value="<?php echo htmlspecialchars($config['pass'] ?? ''); ?>" placeholder="••••••••" required>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">💾 Konfiguration speichern</button>
    </div>

    <p class="admin-info">Beim Speichern wird die Verbindung automatisch geprüft. Die Konfiguration wird nur gespeichert, wenn die Verbindung funktioniert.</p>
  </form>

  <hr style="margin: 2rem 0; border: none; border-top: 1px solid var(--color-border);">

  <h3>📖 E-Mail-Anleitung für andere Admins</h3>

  <h4 style="margin-top: 1.5rem;">Warum kein Forwarding mehr?</h4>
  <p>Bisher wurden E-Mails von info@mobout.de an Andre weitergeleitet. Das war unpraktisch, wenn mehrere Admins reagieren sollten. Jetzt:</p>
  <ul>
    <li><strong>Zentrale Inbox:</strong> Alle Admins sehen die gleiche Inbox mit den gleichen E-Mails</li>
    <li><strong>Live-Sichtbarkeit:</strong> Wenn Admin A eine E-Mail beantwortet, sehen das sofort Admin B und C</li>
    <li><strong>Kein Durcheinander:</strong> Keine Duplikate, keine Unsicherheit „hat das jemand schon beantwortet?"</li>
  </ul>

  <h4>Mail-Clients einrichten (Schritt-für-Schritt)</h4>
  <p>Die folgenden Daten sind notwendig. Der Admin, der oben die Konfiguration speichert, teilt diese mit den anderen Admins:</p>
  <blockquote style="background: var(--light-bg); padding: 1rem; border-left: 3px solid var(--primary-color); margin: 1rem 0;">
    <strong>IMAP-Server:</strong> <?php echo !empty($imap_config['host']) ? h($imap_config['host']) : '(nicht konfiguriert)'; ?><br>
    <strong>Port:</strong> <?php echo !empty($imap_config['port']) ? h($imap_config['port']) : '(nicht konfiguriert)'; ?><br>
    <strong>Login:</strong> <?php echo !empty($imap_config['user']) ? h($imap_config['user']) : '(nicht konfiguriert)'; ?><br>
    <strong>Passwort:</strong> (siehe Admin)
  </blockquote>

  <h5>Outlook (Windows / Mac)</h5>
  <ol>
    <li>Neue E-Mail-Adresse hinzufügen → IMAP (nicht Exchange oder automatische Erkennung)</li>
    <li>Server: obigen IMAP-Server eingeben</li>
    <li>Port: obigen Port eingeben (üblicherweise 993)</li>
    <li>Benutzername: Login (z.B. info@mobout.de)</li>
    <li>Passwort: Passwort von Admin</li>
    <li>Sicherheit: SSL/TLS</li>
  </ol>

  <h5>Apple Mail / Mac Mail</h5>
  <ol>
    <li>Mail → Einstellungen → Accounts → „+" (neuer Account)</li>
    <li>Kontotyp: IMAP</li>
    <li>Anmeldenamen / Passwort: Login + Passwort von Admin</li>
    <li>Server: IMAP-Server, Port 993, SSL</li>
  </ol>

  <h5>Thunderbird</h5>
  <ol>
    <li>Datei → Neu → E-Mail-Konto</li>
    <li>E-Mail-Adresse: info@mobout.de (oder andere E-Mail)</li>
    <li>Passwort: Passwort von Admin</li>
    <li>Manuell bearbeiten: IMAP-Server + Port 993 + SSL</li>
  </ol>

  <h5>🔴 Gmail / Gmail-App (Spezialfall!)</h5>
  <p><strong>Gmail blockiert den direkten Login!</strong> Du brauchst ein App-Passwort:</p>
  <ol>
    <li><strong>Schritt 1:</strong> In deinem Gmail-Konto: <strong>Zwei-Faktor-Authentifizierung einrichten</strong> (falls noch nicht aktiv)<br>
      <small>https://myaccount.google.com → Sicherheit → Zwei-Schritt-Verifizierung</small>
    </li>
    <li><strong>Schritt 2:</strong> App-Passwort generieren<br>
      <small>https://myaccount.google.com/apppasswords → App: „Mail" + Gerät: „Windows / Mac / iPhone" → <strong>Passwort kopieren</strong></small>
    </li>
    <li><strong>Schritt 3:</strong> Im Mail-Client:
      <ul>
        <li>Login: <code>info@mobout.de</code> (die Postfach-E-Mail, nicht deine Gmail)</li>
        <li>Passwort: <strong>Das App-Passwort</strong> (nicht dein Gmail-Passwort!)</li>
        <li>Server: obigen IMAP-Server, Port 993, SSL</li>
      </ul>
    </li>
  </ol>
  <p style="color: red;"><strong>⚠️ Häufiger Fehler:</strong> Nutzen des normalen Gmail-Passworts statt des App-Passworts → Verbindung wird abgelehnt!</p>

  <h4>📧 Fallback: Strato-Webmail (ohne Setup)</h4>
  <p>Falls Mail-Client zu kompliziert ist oder nicht verfügbar: <a href="https://webmail.strato.de" target="_blank"><strong>Strato-Webmail öffnen</strong></a> (im Browser, kein Setup nötig)</p>
</div>
