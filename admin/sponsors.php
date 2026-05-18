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

// --- Upload helpers (logo sponsor) ---
const SPONSOR_LOGO_DIR_FS  = __DIR__ . '/../uploads/sponsors';
const SPONSOR_LOGO_DIR_WEB = '/uploads/sponsors';
const SPONSOR_LOGO_MAX     = 2 * 1024 * 1024; // 2 MB
const SPONSOR_LOGO_EXT = ['png','jpg','jpeg','webp','gif','svg'];
const SPONSOR_LOGO_MIME = [
    'image/png', 'image/jpeg', 'image/webp', 'image/gif',
    'image/svg+xml', 'image/svg',
];

/**
 * Sposta un file caricato in /uploads/sponsors e restituisce il path web.
 * Restituisce null se non c'è file; lancia RuntimeException se invalido.
 */
function sponsor_logo_store(array $file): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload fallito (codice ' . (int)$file['error'] . ').');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('File non valido.');
    }
    if ((int)$file['size'] <= 0 || (int)$file['size'] > SPONSOR_LOGO_MAX) {
        throw new RuntimeException('File troppo grande (max 2 MB).');
    }

    $origName = (string)($file['name'] ?? '');
    $ext = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, SPONSOR_LOGO_EXT, true)) {
        throw new RuntimeException('Formato non supportato. Usa PNG, JPG, WEBP, GIF o SVG.');
    }

    // MIME effettivo (vincolo addizionale)
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) { $mime = (string)finfo_file($fi, $file['tmp_name']); finfo_close($fi); }
    }
    if ($mime !== '' && !in_array($mime, SPONSOR_LOGO_MIME, true)) {
        throw new RuntimeException('Tipo file non riconosciuto come immagine (' . $mime . ').');
    }

    if (!is_dir(SPONSOR_LOGO_DIR_FS)) {
        if (!@mkdir(SPONSOR_LOGO_DIR_FS, 0755, true) && !is_dir(SPONSOR_LOGO_DIR_FS)) {
            throw new RuntimeException('Impossibile creare la cartella ' . SPONSOR_LOGO_DIR_FS);
        }
    }

    $base     = bin2hex(random_bytes(8));
    $filename = $base . '.' . $ext;
    $destFs   = SPONSOR_LOGO_DIR_FS . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destFs)) {
        throw new RuntimeException('Spostamento file fallito.');
    }
    @chmod($destFs, 0644);

    return SPONSOR_LOGO_DIR_WEB . '/' . $filename;
}

/**
 * Cancella dal disco un logo precedentemente memorizzato (path web).
 * No-op se il path è vuoto, esterno, non sotto /uploads/sponsors/, oppure
 * se altri sponsor (es. cloni di edizioni) puntano allo stesso file.
 * Se $excludeSponsorId è passato, quel record viene ignorato nel conteggio
 * (utile in update prima di salvare il nuovo path).
 */
function sponsor_logo_unlink(?string $webPath, ?int $excludeSponsorId = null): void
{
    if (!$webPath) return;
    if (!str_starts_with($webPath, SPONSOR_LOGO_DIR_WEB . '/')) return;
    $name = basename($webPath);
    if ($name === '' || $name === '.' || $name === '..') return;

    // Conta gli altri sponsor che usano lo stesso file: se >0, non cancellare.
    $sql  = 'SELECT COUNT(*) FROM sponsors WHERE logo_url = ?';
    $args = [$webPath];
    if ($excludeSponsorId !== null) {
        $sql   .= ' AND id <> ?';
        $args[] = $excludeSponsorId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    if ((int)$stmt->fetchColumn() > 0) return;

    $fs = SPONSOR_LOGO_DIR_FS . '/' . $name;
    if (is_file($fs)) @unlink($fs);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    switch ($action) {
        case 'create':
        case 'update': {
            $id      = post_int('id');
            $name    = post_str('name');
            $linkUrl = post_str('link_url');
            $remove  = post_bool('remove_logo');

            $errors = [];
            if ($name === '' || mb_strlen($name) > 160)                          $errors[] = 'Nome obbligatorio (max 160 caratteri).';
            if ($linkUrl !== '' && !filter_var($linkUrl, FILTER_VALIDATE_URL))   $errors[] = 'URL link non valido.';
            if (mb_strlen($linkUrl) > 255)                                       $errors[] = 'URL link troppo lungo.';

            $existing = null;
            if ($action === 'update') {
                $existing = crud_get('sponsors', $id, $edId);
                if (!$existing) abort(404, 'Record non trovato.');
            }

            $newLogoPath = null;
            try {
                $newLogoPath = sponsor_logo_store($_FILES['logo'] ?? []);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }

            if ($errors) {
                flash_set('error', implode(' ', $errors));
                redirect('/admin/sponsors.php' . ($action === 'update' ? "?edit=$id" : '?new=1'));
            }

            // Risoluzione path logo finale
            if ($newLogoPath !== null) {
                // Sostituito: rimuovo il vecchio (se update e non usato altrove)
                if ($existing && !empty($existing['logo_url'])) {
                    sponsor_logo_unlink($existing['logo_url'], (int)$existing['id']);
                }
                $logoPath = $newLogoPath;
            } elseif ($remove && $existing && !empty($existing['logo_url'])) {
                sponsor_logo_unlink($existing['logo_url'], (int)$existing['id']);
                $logoPath = null;
            } else {
                $logoPath = $existing['logo_url'] ?? null;
            }

            if ($action === 'create') {
                $sort = crud_next_sort('sponsors', $edId);
                db()->prepare(
                    'INSERT INTO sponsors (edition_id, name, logo_url, link_url, sort)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([$edId, $name, $logoPath, $linkUrl !== '' ? $linkUrl : null, $sort]);
                $newId = (int)db()->lastInsertId();
                audit_log('create', ['entity' => 'sponsors', 'entity_id' => $newId, 'edition_id' => $edId]);
                flash_set('ok', 'Sponsor aggiunto.');
            } else {
                db()->prepare(
                    'UPDATE sponsors SET name = ?, logo_url = ?, link_url = ?
                     WHERE id = ? AND edition_id = ?'
                )->execute([$name, $logoPath, $linkUrl !== '' ? $linkUrl : null, $id, $edId]);
                audit_log('update', ['entity' => 'sponsors', 'entity_id' => $id, 'edition_id' => $edId]);
                flash_set('ok', 'Modifiche salvate.');
            }
            redirect('/admin/sponsors.php');
        }

        case 'delete': {
            $id  = post_int('id');
            $row = crud_get('sponsors', $id, $edId);
            if ($row && crud_delete('sponsors', $id, $edId)) {
                sponsor_logo_unlink($row['logo_url'] ?? null);
                audit_log('delete', ['entity' => 'sponsors', 'entity_id' => $id, 'edition_id' => $edId]);
                flash_set('ok', 'Sponsor rimosso.');
            }
            redirect('/admin/sponsors.php');
        }

        case 'move': {
            crud_move('sponsors', post_int('id'), $edId, post_str('dir'));
            redirect('/admin/sponsors.php');
        }

        default:
            abort(400, 'Action non valida.');
    }
}

// --- Render ---
$rows = db()->prepare('SELECT * FROM sponsors WHERE edition_id = ? ORDER BY sort, id');
$rows->execute([$edId]);
$rows = $rows->fetchAll();

$editId  = get_int('edit', 0);
$isNew   = get_int('new', 0) === 1;
$editing = $editId > 0 ? crud_get('sponsors', $editId, $edId) : null;
if ($editId > 0 && !$editing) {
    flash_set('error', 'Record non trovato.');
    redirect('/admin/sponsors.php');
}

admin_layout_open('Sponsor', 'sponsors');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Edizione <?= e((string)$ed['year']) ?></div>
    <h1>Sponsor tecnici</h1>
  </div>
  <?php if (!$editing && !$isNew): ?>
    <a href="?new=1" class="btn accent">+ Aggiungi</a>
  <?php endif; ?>
</header>

<?= flash_render() ?>

<?php if ($editing || $isNew): $r = $editing ?? []; ?>
  <article class="card" style="margin-bottom:24px;">
    <header class="card-head">
      <h2><?= $editing ? 'Modifica' : 'Nuovo sponsor' ?></h2>
      <a href="/admin/sponsors.php" class="card-cta">annulla</a>
    </header>
    <form method="post" class="form-grid" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?php endif; ?>

      <label class="field">
        <span class="field-label">Nome <span class="muted">obbligatorio</span></span>
        <input type="text" name="name" value="<?= e($r['name'] ?? '') ?>" required maxlength="160">
      </label>

      <label class="field">
        <span class="field-label">Link <span class="muted">sito web sponsor</span></span>
        <input type="url" name="link_url" value="<?= e($r['link_url'] ?? '') ?>" maxlength="255" placeholder="https://...">
      </label>

      <div class="field field-full">
        <span class="field-label">Logo <span class="muted">PNG / JPG / WEBP / GIF / SVG · max 2 MB</span></span>
        <?php if (!empty($r['logo_url'])): ?>
          <div style="display:flex;align-items:center;gap:18px;margin:6px 0 10px;">
            <img src="<?= e($r['logo_url']) ?>" alt="Logo attuale" style="max-width:140px;max-height:80px;object-fit:contain;background:var(--cream);border:1px solid var(--ink);border-radius:6px;padding:4px;">
            <label class="field-check" style="margin:0;">
              <input type="checkbox" name="remove_logo" value="1">
              <span>Rimuovi il logo attuale</span>
            </label>
          </div>
        <?php endif; ?>
        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">
        <?php if (!empty($r['logo_url'])): ?>
          <span class="muted small">Carica un nuovo file per sostituire il logo attuale.</span>
        <?php endif; ?>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn accent"><?= $editing ? 'Salva modifiche' : 'Aggiungi' ?></button>
        <a href="/admin/sponsors.php" class="btn ghost">Annulla</a>
      </div>
    </form>
  </article>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="empty">
    <p>Ancora nessuno sponsor per questa edizione.</p>
    <?php if (!$isNew): ?><p><a href="?new=1" class="btn accent">+ Aggiungine uno</a></p><?php endif; ?>
  </div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:90px;">Logo</th>
        <th>Nome</th>
        <th>Link</th>
        <th style="width:170px; text-align:right;">Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td>
            <?php if (!empty($r['logo_url'])): ?>
              <img src="<?= e($r['logo_url']) ?>" alt="<?= e($r['name']) ?>" style="max-width:72px;max-height:48px;object-fit:contain;">
            <?php else: ?>
              <span class="muted small">—</span>
            <?php endif; ?>
          </td>
          <td><strong><?= e($r['name']) ?></strong></td>
          <td class="small">
            <?php if (!empty($r['link_url'])): ?>
              <a href="<?= e($r['link_url']) ?>" target="_blank" rel="noopener" class="mono"><?= e($r['link_url']) ?></a>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
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
