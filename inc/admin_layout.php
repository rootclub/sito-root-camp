<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/edition.php';
require_once __DIR__ . '/response.php';

// Le voci di nav. Chiavi = "active" che le pagine passano a admin_layout_open().
const ADMIN_NAV = [
    'dashboard'   => ['Dashboard',    '/admin/index.php',      null],
    'schedule'    => ['Palinsesto',   '/admin/schedule.php',   null],
    'organizers'  => ['Organizzano',  '/admin/organizers.php', null],
    'rules'       => ['Regolamento',  '/admin/rules.php',      null],
    'food'        => ['Cibo & bere',  '/admin/food.php',       null],
    'meals'       => ['Pasti',        '/admin/meals.php',      null],
    'sleep'       => ['Dormire',      '/admin/sleep.php',      null],
    'meta'        => ['Info edizione','/admin/meta.php',       null],
    'iscrizioni'  => ['Iscrizioni',   '/admin/iscrizioni.php', null],
    'editions'    => ['Edizioni',     '/admin/editions.php',   'admin'],
    'users'       => ['Utenti',       '/admin/users.php',      'admin'],
];

function admin_layout_open(string $title, string $active = 'dashboard'): void
{
    $user      = auth_user();          // garantito non-null perché auth_require() è già stato chiamato
    $editions  = edition_all();
    $activeEd  = edition_active();     // può essere null (nessuna edizione)
    $activeId  = $activeEd['id'] ?? 0;
    ?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($title) ?> · /RooT-Camp admin</title>
  <link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body class="admin-body">

<header class="admin-top">
  <div class="admin-top-inner">
    <a href="/admin/index.php" class="admin-brand-mark">
      <span class="brand-tag">/RooT-Camp</span>
      <span class="brand-sub">admin</span>
    </a>

    <nav class="admin-nav" aria-label="Navigazione backoffice">
      <?php foreach (ADMIN_NAV as $key => [$label, $url, $needsRole]): ?>
        <?php if ($needsRole !== null && $user['role'] !== $needsRole) continue; ?>
        <a href="<?= e($url) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="admin-tools">
      <?php if (count($editions) > 0): ?>
        <form method="post" action="/admin/_switch_edition.php" class="ed-switch" data-autosubmit>
          <?= csrf_field() ?>
          <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '/admin/index.php') ?>">
          <label class="ed-switch-label">
            <span class="ed-switch-cap">edizione</span>
            <select name="edition_id" aria-label="Edizione su cui stai lavorando">
              <?php foreach ($editions as $ed): ?>
                <option value="<?= (int)$ed['id'] ?>" <?= (int)$ed['id'] === $activeId ? 'selected' : '' ?>>
                  <?= e((string)$ed['year']) ?>
                  <?= $ed['is_current'] ? ' ★' : '' ?>
                  <?= !$ed['is_published'] ? ' (bozza)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        </form>
      <?php endif; ?>

      <span class="user-pill" title="<?= e($user['email'] ?? '') ?>">
        <strong><?= e($user['username']) ?></strong>
        <span class="role role-<?= e($user['role']) ?>"><?= e($user['role']) ?></span>
      </span>
      <a href="/admin/logout.php" class="logout-link">esci</a>
    </div>
  </div>
</header>

<main class="admin-main">
  <div class="admin-wrap">
    <?php if ($activeEd === null): ?>
      <div class="alert error" style="margin-bottom:24px;">
        Nessuna edizione presente nel database.
        <a href="/admin/editions.php" style="color:inherit;text-decoration:underline;">Crea la prima edizione</a> per cominciare.
      </div>
    <?php elseif ($activeEd && !$activeEd['is_current']): ?>
      <div class="alert info" style="margin-bottom:24px;">
        Stai modificando l'edizione <strong><?= e((string)$activeEd['year']) ?></strong>, che <strong>non è</strong> quella attualmente live sul sito.
      </div>
    <?php endif; ?>
<?php
}

function admin_layout_close(): void
{
    ?>
  </div>
</main>

<script>
// Auto-submit del select edizione al change
document.querySelectorAll('form[data-autosubmit] select').forEach(sel => {
  sel.addEventListener('change', () => sel.closest('form').submit());
});
</script>
</body>
</html>
<?php
}
