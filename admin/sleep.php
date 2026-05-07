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

const SLEEP_KINDS = ['camping','indoor','offsite','other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    switch ($action) {
        case 'create':
        case 'update': {
            $id    = post_int('id');
            $kind  = post_str('kind', 'camping');
            $title = post_str('title');
            $body  = post_str('body');
            $price = post_int('price_eur', 0);
            $avail = post_bool('is_available');

            $errors = [];
            if (!in_array($kind, SLEEP_KINDS, true))    $errors[] = 'Tipologia non valida.';
            if ($title === '' || mb_strlen($title) > 120) $errors[] = 'Titolo obbligatorio (max 120).';
            if ($body === '')                            $errors[] = 'Descrizione obbligatoria.';
            if ($price < 0 || $price > 9999)             $errors[] = 'Prezzo non valido (0–9999).';

            if ($errors) {
                flash_set('error', implode(' ', $errors));
                redirect('/admin/sleep.php' . ($action === 'update' ? "?edit=$id" : '?new=1'));
            }

            if ($action === 'create') {
                $sort = crud_next_sort('sleep_options', $edId);
                db()->prepare(
                    'INSERT INTO sleep_options (edition_id, kind, title, body, price_eur, is_available, sort)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([$edId, $kind, $title, $body, $price, $avail, $sort]);
                $newId = (int)db()->lastInsertId();
                audit_log('create', ['entity'=>'sleep_options','entity_id'=>$newId,'edition_id'=>$edId]);
                flash_set('ok', 'Opzione aggiunta.');
            } else {
                if (!crud_get('sleep_options', $id, $edId)) abort(404);
                db()->prepare(
                    'UPDATE sleep_options SET kind = ?, title = ?, body = ?, price_eur = ?, is_available = ?
                     WHERE id = ? AND edition_id = ?'
                )->execute([$kind, $title, $body, $price, $avail, $id, $edId]);
                audit_log('update', ['entity'=>'sleep_options','entity_id'=>$id,'edition_id'=>$edId]);
                flash_set('ok', 'Modifiche salvate.');
            }
            redirect('/admin/sleep.php');
        }

        case 'delete': {
            $id = post_int('id');
            if (crud_delete('sleep_options', $id, $edId)) {
                audit_log('delete', ['entity'=>'sleep_options','entity_id'=>$id,'edition_id'=>$edId]);
                flash_set('ok', 'Opzione rimossa.');
            }
            redirect('/admin/sleep.php');
        }

        case 'move': {
            crud_move('sleep_options', post_int('id'), $edId, post_str('dir'));
            redirect('/admin/sleep.php');
        }

        default: abort(400);
    }
}

$rows = db()->prepare('SELECT * FROM sleep_options WHERE edition_id = ? ORDER BY sort, id');
$rows->execute([$edId]);
$rows = $rows->fetchAll();

$editId  = get_int('edit', 0);
$isNew   = get_int('new', 0) === 1;
$editing = $editId > 0 ? crud_get('sleep_options', $editId, $edId) : null;
if ($editId > 0 && !$editing) { flash_set('error','Opzione non trovata.'); redirect('/admin/sleep.php'); }

admin_layout_open('Dormire', 'sleep');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Edizione <?= e((string)$ed['year']) ?></div>
    <h1>Opzioni di pernottamento</h1>
  </div>
  <?php if (!$editing && !$isNew): ?>
    <a href="?new=1" class="btn accent">+ Aggiungi</a>
  <?php endif; ?>
</header>

<?= flash_render() ?>

<div class="alert info">
  L'introduzione testuale (es. <em>«Due modi per dormire.»</em>) si modifica da
  <a href="/admin/meta.php" style="text-decoration:underline;">Info edizione → sleep_intro</a>.
  Il <code>kind</code> determina l'icona e l'etichetta sul form di iscrizione.
</div>

<?php if ($editing || $isNew): $r = $editing ?? []; ?>
  <article class="card" style="margin-bottom:24px;">
    <header class="card-head">
      <h2><?= $editing ? 'Modifica' : 'Nuova opzione' ?></h2>
      <a href="/admin/sleep.php" class="card-cta">annulla</a>
    </header>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?php endif; ?>

      <label class="field">
        <span class="field-label">Tipo</span>
        <select name="kind">
          <?php foreach (SLEEP_KINDS as $k): ?>
            <option value="<?= e($k) ?>" <?= ($r['kind'] ?? 'camping') === $k ? 'selected' : '' ?>><?= e($k) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="field">
        <span class="field-label">Prezzo €</span>
        <input type="number" name="price_eur" value="<?= e((string)($r['price_eur'] ?? 0)) ?>" min="0" max="9999">
      </label>

      <label class="field field-full">
        <span class="field-label">Titolo</span>
        <input type="text" name="title" value="<?= e($r['title'] ?? '') ?>" required maxlength="120">
      </label>

      <label class="field field-full">
        <span class="field-label">Descrizione</span>
        <textarea name="body" required rows="4"><?= e($r['body'] ?? '') ?></textarea>
      </label>

      <label class="field-check">
        <input type="checkbox" name="is_available" value="1" <?= ($editing ? !empty($r['is_available']) : true) ? 'checked' : '' ?>>
        <span>Disponibile <span class="muted">(visibile sul form di iscrizione)</span></span>
      </label>

      <div class="form-actions">
        <button type="submit" class="btn accent"><?= $editing ? 'Salva' : 'Aggiungi' ?></button>
        <a href="/admin/sleep.php" class="btn ghost">Annulla</a>
      </div>
    </form>
  </article>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="empty">
    <p>Nessuna opzione per ora.</p>
    <?php if (!$isNew): ?><p><a href="?new=1" class="btn accent">+ Aggiungi la prima</a></p><?php endif; ?>
  </div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:90px;">Tipo</th>
        <th>Titolo</th>
        <th style="width:80px;">€</th>
        <th>Stato</th>
        <th style="width:170px; text-align:right;">Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td><span class="kbd"><?= e($r['kind']) ?></span></td>
          <td>
            <strong><?= e($r['title']) ?></strong>
            <div class="small" style="margin-top:4px; color: var(--ink-dim);"><?= e($r['body']) ?></div>
          </td>
          <td class="mono"><?= (int)$r['price_eur'] === 0 ? '—' : e((string)$r['price_eur']) ?></td>
          <td><?= !empty($r['is_available']) ? '<span class="pill pill-ok">attiva</span>' : '<span class="pill pill-mute">nascosta</span>' ?></td>
          <td class="actions-cell">
            <form method="post" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" name="dir" value="up"   class="btn-icon" title="Su"  <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
              <button type="submit" name="dir" value="down" class="btn-icon" title="Giù" <?= $i === count($rows) - 1 ? 'disabled' : '' ?>>↓</button>
            </form>
            <a href="?edit=<?= (int)$r['id'] ?>" class="btn-icon" title="Modifica">✎</a>
            <form method="post" class="inline" onsubmit="return confirm('Eliminare «<?= e(addslashes($r['title'])) ?>»?');">
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
