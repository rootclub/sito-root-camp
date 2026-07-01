<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/edition.php';
require_once __DIR__ . '/../inc/tshirt.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/admin_layout.php';
require_once __DIR__ . '/../inc/response.php';

auth_boot();
auth_require();

$ed = edition_active();
if (!$ed) { flash_set('error','Nessuna edizione disponibile.'); redirect('/admin/index.php'); }
$edId = (int)$ed['id'];

// --- Upload helpers (foto maglietta) ---
const TS_PHOTO_DIR_FS  = __DIR__ . '/../uploads/tshirt';
const TS_PHOTO_DIR_WEB = '/uploads/tshirt';
const TS_PHOTO_MAX     = 3 * 1024 * 1024; // 3 MB
const TS_PHOTO_EXT  = ['png','jpg','jpeg','webp','gif'];
const TS_PHOTO_MIME = ['image/png','image/jpeg','image/webp','image/gif'];

/** Sposta il file caricato in /uploads/tshirt e restituisce il path web (o null se assente). */
function ts_photo_store(array $file): ?string
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
    if ((int)$file['size'] <= 0 || (int)$file['size'] > TS_PHOTO_MAX) {
        throw new RuntimeException('File troppo grande (max 3 MB).');
    }

    $ext = strtolower((string)pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, TS_PHOTO_EXT, true)) {
        throw new RuntimeException('Formato non supportato. Usa PNG, JPG, WEBP o GIF.');
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) { $mime = (string)finfo_file($fi, $file['tmp_name']); finfo_close($fi); }
    }
    if ($mime !== '' && !in_array($mime, TS_PHOTO_MIME, true)) {
        throw new RuntimeException('Tipo file non riconosciuto come immagine (' . $mime . ').');
    }

    if (!is_dir(TS_PHOTO_DIR_FS)) {
        if (!@mkdir(TS_PHOTO_DIR_FS, 0755, true) && !is_dir(TS_PHOTO_DIR_FS)) {
            throw new RuntimeException('Impossibile creare la cartella ' . TS_PHOTO_DIR_FS);
        }
    }

    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    $destFs   = TS_PHOTO_DIR_FS . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destFs)) {
        throw new RuntimeException('Spostamento file fallito.');
    }
    @chmod($destFs, 0644);

    return TS_PHOTO_DIR_WEB . '/' . $filename;
}

/** Cancella dal disco una foto maglietta, se nessun'altra edizione la usa. */
function ts_photo_unlink(?string $webPath, int $excludeEditionId): void
{
    if (!$webPath) return;
    if (!str_starts_with($webPath, TS_PHOTO_DIR_WEB . '/')) return;
    $name = basename($webPath);
    if ($name === '' || $name === '.' || $name === '..') return;

    $stmt = db()->prepare('SELECT COUNT(*) FROM editions WHERE tshirt_photo_url = ? AND id <> ?');
    $stmt->execute([$webPath, $excludeEditionId]);
    if ((int)$stmt->fetchColumn() > 0) return;

    $fs = TS_PHOTO_DIR_FS . '/' . $name;
    if (is_file($fs)) @unlink($fs);
}

// =====================================================================
// POST handler
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    if ($action === 'save_settings') {
        $enabled    = post_bool('tshirt_enabled');
        $intro      = post_str('tshirt_intro');
        $priceLabel = post_str('tshirt_price_label');
        $remove     = post_bool('remove_photo');

        $errors = [];
        if (mb_strlen($priceLabel) > 60) $errors[] = 'Prezzo/etichetta troppo lungo (max 60).';
        if (mb_strlen($intro) > 2000)    $errors[] = 'Testo introduttivo troppo lungo (max 2000).';

        $newPhoto = null;
        try {
            $newPhoto = ts_photo_store($_FILES['photo'] ?? []);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        if ($errors) {
            flash_set('error', implode(' ', $errors));
            redirect('/admin/tshirt.php');
        }

        // Risoluzione path foto finale
        $current = (string)($ed['tshirt_photo_url'] ?? '');
        if ($newPhoto !== null) {
            if ($current !== '') ts_photo_unlink($current, $edId);
            $photoPath = $newPhoto;
        } elseif ($remove && $current !== '') {
            ts_photo_unlink($current, $edId);
            $photoPath = null;
        } else {
            $photoPath = $current !== '' ? $current : null;
        }

        db()->prepare(
            'UPDATE editions
                SET tshirt_enabled = ?, tshirt_photo_url = ?, tshirt_intro = ?, tshirt_price_label = ?
              WHERE id = ?'
        )->execute([
            $enabled,
            $photoPath,
            $intro !== '' ? $intro : null,
            $priceLabel !== '' ? $priceLabel : null,
            $edId,
        ]);
        audit_log('update', ['entity'=>'editions','entity_id'=>$edId,'edition_id'=>$edId,
                             'payload'=>['tshirt_enabled'=>$enabled]]);
        flash_set('ok', 'Impostazioni maglietta salvate.');
        redirect('/admin/tshirt.php');
    }

    abort(400);
}

// =====================================================================
// Render: ricarica edizione (per riflettere i salvataggi) + prenotazioni
// =====================================================================
$ed = edition_get($edId) ?? $ed;
$enabled    = !empty($ed['tshirt_enabled']);
$photoUrl   = (string)($ed['tshirt_photo_url'] ?? '');
$intro      = (string)($ed['tshirt_intro'] ?? '');
$priceLabel = (string)($ed['tshirt_price_label'] ?? '');

// Prenotazioni (chi ha scelto una taglia)
$stmt = db()->prepare(
    "SELECT id, name, email, tshirt_size, created_at
       FROM iscrizioni
      WHERE edition_id = ? AND tshirt_size IS NOT NULL AND tshirt_size <> ''
      ORDER BY created_at DESC"
);
$stmt->execute([$edId]);
$bookings = $stmt->fetchAll();

// Riepilogo per taglia
$stmt = db()->prepare(
    "SELECT tshirt_size, COUNT(*) AS n
       FROM iscrizioni
      WHERE edition_id = ? AND tshirt_size IS NOT NULL AND tshirt_size <> ''
      GROUP BY tshirt_size"
);
$stmt->execute([$edId]);
$countBySize = [];
foreach ($stmt->fetchAll() as $r) {
    $countBySize[(string)$r['tshirt_size']] = (int)$r['n'];
}
$totalShirts = array_sum($countBySize);

admin_layout_open('Maglietta', 'tshirt');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Edizione <?= e((string)$ed['year']) ?></div>
    <h1>Maglietta dell'evento</h1>
  </div>
</header>

<?= flash_render() ?>

<div class="alert info">
  Quando la prenotazione è <strong>attiva</strong>, nel form di iscrizione compare la foto della maglietta
  e la scelta della taglia (XS → 4XL). La taglia è facoltativa e il prezzo è solo informativo
  (si salda sul posto, non entra nel totale dell'iscrizione).
</div>

<section class="dash-grid">
  <!-- ============== IMPOSTAZIONI ============== -->
  <article class="card">
    <header class="card-head"><h2>Impostazioni</h2></header>
    <form method="post" class="form-grid" style="border:none;box-shadow:none;padding:0;" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_settings">

      <label class="field-check field-full">
        <input type="checkbox" name="tshirt_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
        <span>Prenotazione maglietta attiva <span class="muted">(mostra la sezione nel form pubblico)</span></span>
      </label>

      <label class="field field-full">
        <span class="field-label">Testo introduttivo <span class="muted">opzionale</span></span>
        <textarea name="tshirt_intro" rows="3" maxlength="2000" placeholder="es. Maglietta ufficiale 2026, cotone bio. Indica la taglia: la ritiri al check-in."><?= e($intro) ?></textarea>
      </label>

      <label class="field field-full">
        <span class="field-label">Prezzo / etichetta <span class="muted">opzionale, solo informativo (es. "12 € — si salda sul posto")</span></span>
        <input type="text" name="tshirt_price_label" value="<?= e($priceLabel) ?>" maxlength="60">
      </label>

      <div class="field field-full">
        <span class="field-label">Foto maglietta <span class="muted">PNG / JPG / WEBP / GIF · max 3 MB</span></span>
        <?php if ($photoUrl !== ''): ?>
          <div style="display:flex;align-items:center;gap:18px;margin:6px 0 10px;">
            <img src="<?= e($photoUrl) ?>" alt="Foto attuale" style="max-width:160px;max-height:160px;object-fit:contain;background:var(--cream);border:1px solid var(--ink);border-radius:6px;padding:4px;">
            <label class="field-check" style="margin:0;">
              <input type="checkbox" name="remove_photo" value="1">
              <span>Rimuovi la foto attuale</span>
            </label>
          </div>
        <?php endif; ?>
        <input type="file" name="photo" accept="image/png,image/jpeg,image/webp,image/gif">
        <?php if ($photoUrl !== ''): ?>
          <span class="muted small">Carica un nuovo file per sostituire la foto attuale.</span>
        <?php endif; ?>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn accent">Salva impostazioni</button>
      </div>
    </form>
  </article>

  <!-- ============== RIEPILOGO PER TAGLIA ============== -->
  <article class="card">
    <header class="card-head">
      <h2>Riepilogo per taglia</h2>
      <span class="muted small"><?= (int)$totalShirts ?> magliette totali</span>
    </header>
    <?php if ($totalShirts === 0): ?>
      <p class="muted">Ancora nessuna taglia prenotata.</p>
    <?php else: ?>
      <table class="data-table">
        <thead>
          <tr><th>Taglia</th><th style="text-align:right;">Quantità</th></tr>
        </thead>
        <tbody>
          <?php foreach (TSHIRT_SIZES as $code => $label): $n = $countBySize[$code] ?? 0; ?>
            <tr style="<?= $n === 0 ? 'opacity:.45;' : '' ?>">
              <td><span class="kbd"><?= e($label) ?></span></td>
              <td class="mono" style="text-align:right;"><strong><?= (int)$n ?></strong></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td><strong>Totale</strong></td>
            <td class="mono" style="text-align:right;"><strong><?= (int)$totalShirts ?></strong></td>
          </tr>
        </tbody>
      </table>
    <?php endif; ?>
  </article>
</section>

<!-- ============== PRENOTAZIONI ============== -->
<header class="page-head" style="margin-top:36px;">
  <div><h2 style="margin:0;">Prenotazioni (<?= count($bookings) ?>)</h2></div>
</header>

<?php if (empty($bookings)): ?>
  <div class="empty">
    <p>Nessuno ha ancora prenotato una maglietta per questa edizione.</p>
  </div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Quando</th>
        <th>Nome</th>
        <th>Email</th>
        <th style="width:90px;">Taglia</th>
        <th style="width:60px;text-align:right;">Apri</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($bookings as $b): ?>
        <tr>
          <td class="mono small"><?= e(date('d/m H:i', strtotime((string)$b['created_at']))) ?></td>
          <td><strong><?= e((string)$b['name']) ?></strong></td>
          <td class="small"><?= e((string)$b['email']) ?></td>
          <td><span class="kbd"><?= e(tshirt_size_label((string)$b['tshirt_size'])) ?: e((string)$b['tshirt_size']) ?></span></td>
          <td class="actions-cell">
            <a href="/admin/iscrizioni.php?id=<?= (int)$b['id'] ?>" class="btn-icon" title="Apri iscrizione">→</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php admin_layout_close();
