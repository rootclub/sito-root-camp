<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/response.php';

auth_boot();

// --- Gates ---
// 1) SETUP_TOKEN deve essere impostato in .env
if (SETUP_TOKEN === '') {
    abort(404, 'Setup non disponibile. SETUP_TOKEN non impostato in .env.');
}
// 2) Token in URL deve combaciare (constant-time)
$tokenIn = $_GET['token'] ?? '';
if (!is_string($tokenIn) || $tokenIn === '' || !hash_equals(SETUP_TOKEN, $tokenIn)) {
    abort(403, 'Token di setup non valido.');
}
// 3) Setup è solo per first-run: rifiuta se esiste già qualche admin
if (auth_count_users() > 0) {
    abort(404, 'Setup già completato: esistono già utenti amministratori.');
}

$errors = [];
$created = false;
$createdUsername = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username)) {
        $errors[] = 'Username non valido (3-60 caratteri: lettere, numeri, "." "_" "-").';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email non valida.';
    }
    if (strlen($password) < 10) {
        $errors[] = 'Password troppo corta (almeno 10 caratteri).';
    }
    if ($password !== $password2) {
        $errors[] = 'Le due password non coincidono.';
    }

    // Re-check user count dentro la stessa request (race contro setup paralleli)
    if (!$errors && auth_count_users() > 0) {
        abort(409, 'Un altro setup è stato completato nel frattempo.');
    }

    if (!$errors) {
        try {
            $id = auth_create_user($username, $email !== '' ? $email : null, $password, 'admin');
            audit_log('setup_first_admin', [
                'entity'    => 'admin_users',
                'entity_id' => $id,
                'username'  => $username,
            ]);
            $created = true;
            $createdUsername = $username;
        } catch (\Throwable $e) {
            error_log('setup_first_admin failed: ' . $e->getMessage());
            $errors[] = 'Errore inatteso durante la creazione dell\'utente.';
        }
    }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Setup · /RooT-Camp backoffice</title>
  <link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body class="auth-page">
  <main class="auth-card">
    <span class="auth-brand">/RooT-Camp · admin</span>

    <?php if ($created): ?>
      <h1>Primo admin creato</h1>
      <p class="lede">Hai creato l'utente <strong><?= e($createdUsername) ?></strong> con ruolo <span class="kbd">admin</span>.</p>

      <div class="alert ok">
        Procedi al <a href="/admin/login.php"><strong>login</strong></a> per verificare le credenziali.
      </div>

      <div class="alert info">
        <strong>Importante:</strong> ora <em>svuota</em> <span class="kbd">SETUP_TOKEN</span> nel file
        <span class="kbd">.env</span> sul server (oppure cancella la riga). Finché è impostato, chiunque conosca il token può rientrare in setup quando non ci sono utenti.
      </div>
    <?php else: ?>
      <h1>Crea il primo admin</h1>
      <p class="lede">Stai inizializzando il backoffice. Questa pagina è disponibile solo perché non esiste ancora nessun amministratore.</p>

      <?php foreach ($errors as $err): ?>
        <div class="alert error"><?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="post" autocomplete="off" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($tokenIn) ?>">

        <label>Username
          <input type="text" name="username" value="<?= e($username) ?>" required autofocus
                 pattern="[A-Za-z0-9._-]{3,60}" maxlength="60">
        </label>

        <label>Email <span class="muted">(opzionale, per recuperi futuri)</span>
          <input type="email" name="email" value="<?= e($email) ?>" maxlength="180" autocomplete="off">
        </label>

        <label>Password <span class="muted">(min. 10 caratteri)</span>
          <input type="password" name="password" required minlength="10" maxlength="200" autocomplete="new-password">
        </label>

        <label>Conferma password
          <input type="password" name="password2" required minlength="10" maxlength="200" autocomplete="new-password">
        </label>

        <div class="btn-row">
          <button type="submit" class="btn accent">Crea admin</button>
        </div>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
<?php
// Nota tecnica: per evitare che l'URL con ?token=... resti nei log/history,
// dopo il primo successo l'utente dovrebbe svuotare SETUP_TOKEN e chiudere la tab.
