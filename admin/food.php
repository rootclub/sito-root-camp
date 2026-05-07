<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/edition.php';
require_once __DIR__ . '/../inc/crud.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/admin_layout.php';
require_once __DIR__ . '/../inc/response.php';

auth_boot();
auth_require();

$ed = edition_active();
if (!$ed) { flash_set('error','Nessuna edizione disponibile.'); redirect('/admin/index.php'); }
$edId = (int)$ed['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    switch ($action) {
        case 'create':
        case 'update': {
            $id    = post_int('id');
            $label = post_str('label');
            $note  = post_str('note');

            $errors = [];
            if ($label === '' || mb_strlen($label) > 80) $errors[] = 'Etichetta obbligatoria (max 80).';
            if (mb_strlen($note) > 255)                  $errors[] = 'Nota troppo lunga (max 255).';

            if ($errors) {
                flash_set('error', implode(' ', $errors));
                redirect('/admin/food.php' . ($action === 'update' ? "?edit=$id" : '?new=1'));
            }

            if ($action === 'create') {
                $sort = crud_next_sort('food_items', $edId);
                db()->prepare(
                    'INSERT INTO food_items (edition_id, label, note, sort) VALUES (?, ?, ?, ?)'
                )->execute([$edId, $label, $note !== '' ? $note : null, $sort]);
                $newId = (int)db()->lastInsertId();
                audit_log('create', ['entity'=>'food_items','entity_id'=>$newId,'edition_id'=>$edId]);
                flash_set('ok', 'Voce aggiunta.');
            } else {
                if (!crud_get('food_items', $id, $edId)) abort(404);
                db()->prepare(
                    'UPDATE food_items SET label = ?, note = ? WHERE id = ? AND edition_id = ?'
                )->execute([$label, $note !== '' ? $note : null, $id, $edId]);
                audit_log('update', ['entity'=>'food_items','entity_id'=>$id,'edition_id'=>$edId]);
                flash_set('ok', 'Modifiche salvate.');
            }
            redirect('/admin/food.php');
        }

        case 'delete': {
            $id = post_int('id');
            if (crud_delete('food_items', $id, $edId)) {
                audit_log('delete', ['entity'=>'food_items','entity_id'=>$id,'edition_id'=>$edId]);
                flash_set('ok', 'Voce rimossa.');
            }
            redirect('/admin/food.php');
        }

        case 'move': {
            crud_move('food_items', post_int('id'), $edId, post_str('dir'));
            redirect('/admin/food.php');
        }

        default: abort(400);
    }
}

// Intro testuale (vive su editions.food_intro). Modificabile da meta.php — qui solo informativo.

$rows = db()->prepare('SELECT * FROM food_items WHERE edition_id = ? ORDER BY sort, id');
$rows->execute([$edId]);
$rows = $rows->fetchAll();

$editId  = get_int('edit', 0);
$isNew   = get_int('new', 0) === 1;
$editing = $editId > 0 ? crud_get('food_items', $editId, $edId) : null;
if ($editId > 0 && !$editing) { flash_set('error','Voce non trovata.'); redirect('/admin/food.php'); }

admin_layout_open('Cibo & bere', 'food');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Edizione <?= e((string)$ed['year']) ?></div>
    <h1>Cibo & bere</h1>
  </div>
  <?php if (!$editing && !$isNew): ?>
    <a href="?new=1" class="btn accent">+ Aggiungi voce</a>
  <?php endif; ?>
</header>

<?= flash_render() ?>

<div class="alert info">
  L'introduzione testuale (es. <em>«Cucina da campo, birra artigianale, caffè decente.»</em>) si modifica da
  <a href="/admin/meta.php" style="text-decoration:underline;">Info edizione → food_intro</a>.
</div>

<?php if ($editing || $isNew): $r = $editing ?? []; ?>
  <article class="card" style="margin-bottom:24px;">
    <header class="card-head">
      <h2><?= $editing ? 'Modifica' : 'Nuova voce' ?></h2>
      <a href="/admin/food.php" class="card-cta">annulla</a>
    </header>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?php endif; ?>

      <label class="field">
        <span class="field-label">Etichetta <span class="muted">es. "Colazione", "Birra"</span></span>
        <input type="text" name="label" value="<?= e($r['label'] ?? '') ?>" required maxlength="80">
      </label>

      <label class="field">
        <span class="field-label">Nota <span class="muted">descrizione breve</span></span>
        <input type="text" name="note" value="<?= e((string)($r['note'] ?? '')) ?>" maxlength="255">
      </label>

      <div class="form-actions">
        <button type="submit" class="btn accent"><?= $editing ? 'Salva' : 'Aggiungi' ?></button>
        <a href="/admin/food.php" class="btn ghost">Annulla</a>
      </div>
    </form>
  </article>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="empty">
    <p>Nessuna voce per ora.</p>
    <?php if (!$isNew): ?><p><a href="?new=1" class="btn accent">+ Aggiungine una</a></p><?php endif; ?>
  </div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:30%;">Etichetta</th>
        <th>Nota</th>
        <th style="width:170px; text-align:right;">Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td><strong><?= e($r['label']) ?></strong></td>
          <td class="small"><?= e((string)$r['note']) ?: '<span class="muted">—</span>' ?></td>
          <td class="actions-cell">
            <form method="post" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" name="dir" value="up"   class="btn-icon" title="Su"  <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
              <button type="submit" name="dir" value="down" class="btn-icon" title="Giù" <?= $i === count($rows) - 1 ? 'disabled' : '' ?>>↓</button>
            </form>
            <a href="?edit=<?= (int)$r['id'] ?>" class="btn-icon" title="Modifica">✎</a>
            <form method="post" class="inline" onsubmit="return confirm('Eliminare «<?= e(addslashes($r['label'])) ?>»?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="btn-icon danger" title="Elimina">×</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php admin_layout_close();
