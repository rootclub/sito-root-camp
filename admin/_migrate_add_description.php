<?php
declare(strict_types=1);

// =====================================================================
// Migration one-shot: aggiunge schedule_items.description (TEXT NULL).
// Carica il file via FTP, apri /admin/_migrate_add_description.php,
// premi "Esegui migrazione", poi CANCELLA il file dal server.
// =====================================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/admin_layout.php';
require_once __DIR__ . '/../inc/response.php';

auth_boot();
auth_require('admin');

function column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

$alreadyDone = column_exists('schedule_items', 'description');
$result = null;
$detail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($alreadyDone) {
        $result = true;
        $detail = 'La colonna esiste già: nessuna modifica necessaria.';
    } else {
        try {
            db()->exec('ALTER TABLE schedule_items ADD COLUMN description TEXT DEFAULT NULL AFTER speaker');
            audit_log('migrate', ['payload' => ['migration' => 'schedule_items.description']]);
            $result = true;
            $detail = 'Colonna description aggiunta a schedule_items. Adesso CANCELLA questo file dal server.';
            $alreadyDone = true;
        } catch (Throwable $e) {
            $result = false;
            $detail = 'ALTER TABLE fallito: ' . $e->getMessage();
        }
    }
}

admin_layout_open('Migrazione description', 'dashboard');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Migrazione one-shot · solo admin</div>
    <h1>Aggiungi <code>schedule_items.description</code></h1>
  </div>
</header>

<?php if ($result === true): ?>
  <div class="alert ok"><?= e($detail) ?></div>
<?php elseif ($result === false): ?>
  <div class="alert error" style="white-space:pre-wrap;"><?= e($detail) ?></div>
<?php endif; ?>

<article class="card" style="max-width:640px;">
  <header class="card-head"><h2>Stato</h2></header>
  <p>
    <?php if ($alreadyDone): ?>
      ✅ La colonna <code>description</code> è già presente in <code>schedule_items</code>.
      Puoi cancellare questo file dal server.
    <?php else: ?>
      ⚠️ La colonna <code>description</code> non esiste ancora.
      Premi il pulsante per eseguire l'<code>ALTER TABLE</code>.
    <?php endif; ?>
  </p>

  <?php if (!$alreadyDone): ?>
    <form method="post" style="margin-top:16px;">
      <?= csrf_field() ?>
      <button type="submit" class="btn accent">Esegui migrazione</button>
    </form>
  <?php endif; ?>

  <hr style="margin:22px 0;border:none;border-top:1px dashed currentColor;opacity:.3;">

  <p class="muted small">
    SQL eseguita:
  </p>
  <pre class="mono small" style="background:#0001;padding:10px;border-radius:6px;overflow:auto;">ALTER TABLE schedule_items
  ADD COLUMN description TEXT DEFAULT NULL AFTER speaker;</pre>
</article>

<?php admin_layout_close();
