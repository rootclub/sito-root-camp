<?php
declare(strict_types=1);

// =====================================================================
// Migration one-shot: crea la tabella schedule_day_settings
// (visibilità per giorno nella preview home).
// Carica via FTP, apri /admin/_migrate_day_settings.php, premi
// "Esegui migrazione", poi CANCELLA il file dal server.
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

function table_exists(string $table): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

$DDL = <<<SQL
CREATE TABLE schedule_day_settings (
  id                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  edition_id             INT UNSIGNED  NOT NULL,
  day_date               DATE          NOT NULL,
  show_in_home_preview   TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_edition_day (edition_id, day_date),
  CONSTRAINT fk_dsett_edition FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$alreadyDone = table_exists('schedule_day_settings');
$result = null;
$detail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($alreadyDone) {
        $result = true;
        $detail = 'La tabella esiste già: nessuna modifica necessaria.';
    } else {
        try {
            db()->exec($DDL);
            audit_log('migrate', ['payload' => ['migration' => 'schedule_day_settings']]);
            $result = true;
            $detail = 'Tabella schedule_day_settings creata. Adesso CANCELLA questo file dal server.';
            $alreadyDone = true;
        } catch (Throwable $e) {
            $result = false;
            $detail = 'CREATE TABLE fallito: ' . $e->getMessage();
        }
    }
}

admin_layout_open('Migrazione schedule_day_settings', 'dashboard');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Migrazione one-shot · solo admin</div>
    <h1>Crea tabella <code>schedule_day_settings</code></h1>
  </div>
</header>

<?php if ($result === true): ?>
  <div class="alert ok"><?= e($detail) ?></div>
<?php elseif ($result === false): ?>
  <div class="alert error" style="white-space:pre-wrap;"><?= e($detail) ?></div>
<?php endif; ?>

<article class="card" style="max-width:720px;">
  <header class="card-head"><h2>Stato</h2></header>
  <p>
    <?php if ($alreadyDone): ?>
      ✅ La tabella <code>schedule_day_settings</code> è già presente.
      Puoi cancellare questo file dal server.
    <?php else: ?>
      ⚠️ La tabella <code>schedule_day_settings</code> non esiste ancora.
      Premi il pulsante per crearla.
    <?php endif; ?>
  </p>

  <?php if (!$alreadyDone): ?>
    <form method="post" style="margin-top:16px;">
      <?= csrf_field() ?>
      <button type="submit" class="btn accent">Esegui migrazione</button>
    </form>
  <?php endif; ?>

  <hr style="margin:22px 0;border:none;border-top:1px dashed currentColor;opacity:.3;">

  <p class="muted small">SQL eseguita:</p>
  <pre class="mono small" style="background:#0001;padding:10px;border-radius:6px;overflow:auto;"><?= e($DDL) ?>;</pre>
</article>

<?php admin_layout_close();
