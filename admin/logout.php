<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/response.php';

auth_boot();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    audit_log('logout');
    auth_logout();
    redirect('/admin/login.php');
}

// GET: pagina di conferma con form CSRF-safe.
if (!auth_check()) {
    redirect('/admin/login.php');
}
$user = auth_user();
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Esci · /RooT-Camp backoffice</title>
  <link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body class="auth-page">
  <main class="auth-card">
    <span class="auth-brand">/RooT-Camp · admin</span>
    <h1>Vuoi uscire?</h1>
    <p class="lede">Sei loggato come <strong><?= e($user['username']) ?></strong>.</p>
    <form method="post">
      <?= csrf_field() ?>
      <div class="btn-row">
        <button type="submit" class="btn accent">Esci</button>
        <a href="/admin/index.php" class="btn ghost">Annulla</a>
      </div>
    </form>
  </main>
</body>
</html>
