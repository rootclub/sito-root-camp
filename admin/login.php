<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/response.php';

auth_boot();

if (auth_check()) {
    redirect('/admin/index.php');
}

// "next" deve essere un path relativo dentro /admin/
$nextRaw = $_GET['next'] ?? $_POST['next'] ?? '/admin/index.php';
$next = is_string($nextRaw) && preg_match('#^/admin/[A-Za-z0-9._/?&=%-]*$#', $nextRaw)
    ? $nextRaw : '/admin/index.php';

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (auth_attempt($username, $password)) {
        redirect($next);
    } else {
        $error = 'Credenziali non valide.';
        usleep(800000); // soft delay anti brute-force
    }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Login · /RooT-Camp backoffice</title>
  <link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body class="auth-page">
  <main class="auth-card">
    <span class="auth-brand">/RooT-Camp · admin</span>
    <h1>Accedi</h1>
    <p class="lede">Backoffice riservato. Se hai dimenticato la password, chiedi a un admin.</p>

    <?php if ($error): ?>
      <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate autocomplete="on">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">

      <label>Username
        <input type="text" name="username" value="<?= e($username) ?>" required autofocus
               autocomplete="username" maxlength="60" inputmode="latin">
      </label>

      <label>Password
        <input type="password" name="password" required autocomplete="current-password" maxlength="200">
      </label>

      <div class="btn-row">
        <button type="submit" class="btn accent">Entra</button>
        <a href="/" class="muted" style="margin-left:auto;">← torna al sito</a>
      </div>
    </form>
  </main>
</body>
</html>
