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
if (!$ed) {
    flash_set('error', 'Nessuna edizione disponibile.');
    redirect('/admin/index.php');
}
$edId = (int)$ed['id'];

// Icone supportate dal frontend (vedi scripts/regolamento.js).
// Se aggiungi una nuova chiave qui, devi anche aggiungere lo SVG in scripts/regolamento.js.
const RULE_ICONS = ['ticket', 'card', 'clock', 'moon', 'volume', 'tent',
    'terminal', 'code', 'monitor', 'beer', 'coffee', 'food', 'wifi', 'sun',
    'music', 'heart', 'info', 'warning', 'lock', 'key', 'map'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    switch ($action) {
        case 'create':
        case 'update': {
            $id    = post_int('id');
            $icon  = post_str('icon', 'ticket');
            $title = post_str('title');
            $body  = post_str('body');

            $errors = [];
            if (!in_array($icon, RULE_ICONS, true))     $errors[] = 'Icona non valida.';
            if ($title === '' || mb_strlen($title) > 160) $errors[] = 'Titolo obbligatorio (max 160 caratteri).';
            if ($body === '')                            $errors[] = 'Testo obbligatorio.';

            if ($errors) {
                flash_set('error', implode(' ', $errors));
                redirect('/admin/rules.php' . ($action === 'update' ? "?edit=$id" : '?new=1'));
            }

            if ($action === 'create') {
                $sort = crud_next_sort('rules', $edId);
                db()->prepare(
                    'INSERT INTO rules (edition_id, icon, title, body, sort) VALUES (?, ?, ?, ?, ?)'
                )->execute([$edId, $icon, $title, $body, $sort]);
                $newId = (int)db()->lastInsertId();
                audit_log('create', ['entity' => 'rules', 'entity_id' => $newId, 'edition_id' => $edId]);
                flash_set('ok', 'Regola aggiunta.');
            } else {
                if (!crud_get('rules', $id, $edId)) abort(404);
                db()->prepare(
                    'UPDATE rules SET icon = ?, title = ?, body = ? WHERE id = ? AND edition_id = ?'
                )->execute([$icon, $title, $body, $id, $edId]);
                audit_log('update', ['entity' => 'rules', 'entity_id' => $id, 'edition_id' => $edId]);
                flash_set('ok', 'Modifiche salvate.');
            }
            redirect('/admin/rules.php');
        }

        case 'delete': {
            $id = post_int('id');
            if (crud_delete('rules', $id, $edId)) {
                audit_log('delete', ['entity' => 'rules', 'entity_id' => $id, 'edition_id' => $edId]);
                flash_set('ok', 'Regola rimossa.');
            }
            redirect('/admin/rules.php');
        }

        case 'move': {
            crud_move('rules', post_int('id'), $edId, post_str('dir'));
            redirect('/admin/rules.php');
        }

        default:
            abort(400);
    }
}

$rows = db()->prepare('SELECT * FROM rules WHERE edition_id = ? ORDER BY sort, id');
$rows->execute([$edId]);
$rows = $rows->fetchAll();

$editId  = get_int('edit', 0);
$isNew   = get_int('new', 0) === 1;
$editing = $editId > 0 ? crud_get('rules', $editId, $edId) : null;
if ($editId > 0 && !$editing) {
    flash_set('error', 'Regola non trovata.');
    redirect('/admin/rules.php');
}

admin_layout_open('Regolamento', 'rules');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Edizione <?= e((string)$ed['year']) ?></div>
    <h1>Regolamento</h1>
  </div>
  <?php if (!$editing && !$isNew): ?>
    <a href="?new=1" class="btn accent">+ Aggiungi regola</a>
  <?php endif; ?>
</header>

<?= flash_render() ?>

<?php if ($editing || $isNew): $r = $editing ?? []; ?>
  <article class="card" style="margin-bottom:24px;">
    <header class="card-head">
      <h2><?= $editing ? 'Modifica' : 'Nuova regola' ?></h2>
      <a href="/admin/rules.php" class="card-cta">annulla</a>
    </header>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?php endif; ?>

      <label class="field">
        <span class="field-label">Icona</span>
        <select name="icon">
          <?php foreach (RULE_ICONS as $ic): ?>
            <option value="<?= e($ic) ?>" <?= ($r['icon'] ?? 'ticket') === $ic ? 'selected' : '' ?>><?= e($ic) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="field">
        <span class="field-label">Titolo <span class="muted">obbligatorio</span></span>
        <input type="text" name="title" value="<?= e($r['title'] ?? '') ?>" required maxlength="160">
      </label>

      <label class="field field-full">
        <span class="field-label">Testo <span class="muted">verrà mostrato nel corpo della card</span></span>
        <textarea name="body" required rows="4"><?= e($r['body'] ?? '') ?></textarea>
      </label>

      <div class="form-actions">
        <button type="submit" class="btn accent"><?= $editing ? 'Salva modifiche' : 'Aggiungi' ?></button>
        <a href="/admin/rules.php" class="btn ghost">Annulla</a>
      </div>
    </form>
  </article>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="empty">
    <p>Ancora nessuna regola.</p>
    <?php if (!$isNew): ?><p><a href="?new=1" class="btn accent">+ Aggiungine una</a></p><?php endif; ?>
  </div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:80px;">Icona</th>
        <th>Regola</th>
        <th style="width:170px; text-align:right;">Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td><span class="kbd"><?= e($r['icon']) ?></span></td>
          <td>
            <strong><?= e($r['title']) ?></strong>
            <div class="small" style="margin-top:4px; color: var(--ink-dim);"><?= e($r['body']) ?></div>
          </td>
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
