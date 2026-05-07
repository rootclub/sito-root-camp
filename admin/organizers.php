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
    flash_set('error', 'Nessuna edizione disponibile. Crea prima un\'edizione.');
    redirect('/admin/index.php');
}
$edId = (int)$ed['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    switch ($action) {
        case 'create':
        case 'update': {
            $id   = post_int('id');
            $name = post_str('name');
            $role = post_str('role');
            $url  = post_str('link_url');
            $isPh = post_bool('is_placeholder');

            $errors = [];
            if ($name === '' || mb_strlen($name) > 160)  $errors[] = 'Nome obbligatorio (max 160 caratteri).';
            if (mb_strlen($role) > 160)                  $errors[] = 'Ruolo troppo lungo (max 160 caratteri).';
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) $errors[] = 'URL non valido.';
            if (mb_strlen($url) > 255)                   $errors[] = 'URL troppo lungo.';

            if ($errors) {
                flash_set('error', implode(' ', $errors));
                redirect('/admin/organizers.php' . ($action === 'update' ? "?edit=$id" : '?new=1'));
            }

            if ($action === 'create') {
                $sort = crud_next_sort('organizers', $edId);
                db()->prepare(
                    'INSERT INTO organizers (edition_id, name, role, is_placeholder, link_url, sort)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$edId, $name, $role !== '' ? $role : null, $isPh, $url !== '' ? $url : null, $sort]);
                $newId = (int)db()->lastInsertId();
                audit_log('create', ['entity' => 'organizers', 'entity_id' => $newId, 'edition_id' => $edId]);
                flash_set('ok', 'Organizzazione aggiunta.');
            } else {
                $row = crud_get('organizers', $id, $edId);
                if (!$row) abort(404, 'Record non trovato.');
                db()->prepare(
                    'UPDATE organizers SET name = ?, role = ?, is_placeholder = ?, link_url = ?
                     WHERE id = ? AND edition_id = ?'
                )->execute([$name, $role !== '' ? $role : null, $isPh, $url !== '' ? $url : null, $id, $edId]);
                audit_log('update', ['entity' => 'organizers', 'entity_id' => $id, 'edition_id' => $edId]);
                flash_set('ok', 'Modifiche salvate.');
            }
            redirect('/admin/organizers.php');
        }

        case 'delete': {
            $id = post_int('id');
            if (crud_delete('organizers', $id, $edId)) {
                audit_log('delete', ['entity' => 'organizers', 'entity_id' => $id, 'edition_id' => $edId]);
                flash_set('ok', 'Organizzazione rimossa.');
            }
            redirect('/admin/organizers.php');
        }

        case 'move': {
            crud_move('organizers', post_int('id'), $edId, post_str('dir'));
            redirect('/admin/organizers.php');
        }

        default:
            abort(400, 'Action non valida.');
    }
}

// --- Render ---
$rows = db()->prepare('SELECT * FROM organizers WHERE edition_id = ? ORDER BY sort, id');
$rows->execute([$edId]);
$rows = $rows->fetchAll();

$editId  = get_int('edit', 0);
$isNew   = get_int('new', 0) === 1;
$editing = $editId > 0 ? crud_get('organizers', $editId, $edId) : null;
if ($editId > 0 && !$editing) {
    flash_set('error', 'Record non trovato.');
    redirect('/admin/organizers.php');
}

admin_layout_open('Organizzazioni', 'organizers');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Edizione <?= e((string)$ed['year']) ?></div>
    <h1>Chi organizza</h1>
  </div>
  <?php if (!$editing && !$isNew): ?>
    <a href="?new=1" class="btn accent">+ Aggiungi</a>
  <?php endif; ?>
</header>

<?= flash_render() ?>

<?php if ($editing || $isNew): $r = $editing ?? []; ?>
  <article class="card" style="margin-bottom:24px;">
    <header class="card-head">
      <h2><?= $editing ? 'Modifica' : 'Nuova organizzazione' ?></h2>
      <a href="/admin/organizers.php" class="card-cta">annulla</a>
    </header>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?php endif; ?>

      <label class="field">
        <span class="field-label">Nome <span class="muted">obbligatorio</span></span>
        <input type="text" name="name" value="<?= e($r['name'] ?? '') ?>" required maxlength="160">
      </label>

      <label class="field">
        <span class="field-label">Ruolo <span class="muted">es. "patrocinio", "partner"</span></span>
        <input type="text" name="role" value="<?= e($r['role'] ?? '') ?>" maxlength="160">
      </label>

      <label class="field">
        <span class="field-label">Link <span class="muted">opzionale (sito web)</span></span>
        <input type="url" name="link_url" value="<?= e($r['link_url'] ?? '') ?>" maxlength="255" placeholder="https://...">
      </label>

      <label class="field-check">
        <input type="checkbox" name="is_placeholder" value="1" <?= !empty($r['is_placeholder']) ? 'checked' : '' ?>>
        <span>Placeholder <span class="muted">(card tratteggiata, opacità ridotta)</span></span>
      </label>

      <div class="form-actions">
        <button type="submit" class="btn accent"><?= $editing ? 'Salva modifiche' : 'Aggiungi' ?></button>
        <a href="/admin/organizers.php" class="btn ghost">Annulla</a>
      </div>
    </form>
  </article>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="empty">
    <p>Ancora nessuna organizzazione per questa edizione.</p>
    <?php if (!$isNew): ?><p><a href="?new=1" class="btn accent">+ Aggiungine una</a></p><?php endif; ?>
  </div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:40%;">Nome</th>
        <th>Ruolo</th>
        <th>Stato</th>
        <th style="width:170px; text-align:right;">Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td>
            <strong><?= e($r['name']) ?></strong>
            <?php if (!empty($r['link_url'])): ?>
              <br><a href="<?= e($r['link_url']) ?>" target="_blank" rel="noopener" class="small mono"><?= e($r['link_url']) ?></a>
            <?php endif; ?>
          </td>
          <td class="small"><?= e((string)$r['role']) ?: '<span class="muted">—</span>' ?></td>
          <td><?= !empty($r['is_placeholder']) ? '<span class="pill pill-mute">placeholder</span>' : '<span class="pill pill-ok">visibile</span>' ?></td>
          <td class="actions-cell">
            <form method="post" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" name="dir" value="up"   class="btn-icon" title="Su"  <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
              <button type="submit" name="dir" value="down" class="btn-icon" title="Giù" <?= $i === count($rows) - 1 ? 'disabled' : '' ?>>↓</button>
            </form>
            <a href="?edit=<?= (int)$r['id'] ?>" class="btn-icon" title="Modifica">✎</a>
            <form method="post" class="inline" onsubmit="return confirm('Eliminare «<?= e(addslashes($r['name'])) ?>»?');">
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
