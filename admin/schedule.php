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

const SCHED_KINDS = ['opening','talk','workshop','music','kids','food','closing','other'];

const GIORNI_IT = [
    0 => 'Domenica', 1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì',
    4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato',
];
function day_label_for(string $date): string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return $date;
    $d = new DateTimeImmutable($date);
    return GIORNI_IT[(int)$d->format('w')] . ' ' . (int)$d->format('j');
}
function fmt_end(string $time, int $dur): string
{
    $parts = explode(':', $time);
    $h = (int)($parts[0] ?? 0);
    $m = (int)($parts[1] ?? 0);
    $tot = $h * 60 + $m + $dur;
    return sprintf('%02d:%02d', intdiv($tot, 60) % 24, $tot % 60);
}

// Set di track_id validi per l'edizione attiva (per validazione form item).
function tracks_for_edition(int $edId): array
{
    $stmt = db()->prepare('SELECT id, position, name FROM schedule_tracks WHERE edition_id = ? ORDER BY position, id');
    $stmt->execute([$edId]);
    return $stmt->fetchAll();
}

// =====================================================================
// POST handler
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    switch ($action) {

        // -------------- TRACKS (sale) --------------
        case 'track_create': {
            $name = post_str('name');
            if ($name === '' || mb_strlen($name) > 80) {
                flash_set('error', 'Nome sala obbligatorio (max 80).');
                redirect('/admin/schedule.php');
            }
            $stmt = db()->prepare('SELECT COALESCE(MAX(position), -1) FROM schedule_tracks WHERE edition_id = ?');
            $stmt->execute([$edId]);
            $pos = (int)$stmt->fetchColumn() + 1;
            db()->prepare(
                'INSERT INTO schedule_tracks (edition_id, position, name) VALUES (?, ?, ?)'
            )->execute([$edId, $pos, $name]);
            $newId = (int)db()->lastInsertId();
            audit_log('create', ['entity'=>'schedule_tracks','entity_id'=>$newId,'edition_id'=>$edId]);
            flash_set('ok', 'Sala aggiunta.');
            redirect('/admin/schedule.php');
        }

        case 'track_update': {
            $id   = post_int('id');
            $name = post_str('name');
            if ($name === '' || mb_strlen($name) > 80) {
                flash_set('error', 'Nome sala obbligatorio.');
                redirect('/admin/schedule.php');
            }
            if (!crud_get('schedule_tracks', $id, $edId)) abort(404);
            db()->prepare('UPDATE schedule_tracks SET name = ? WHERE id = ? AND edition_id = ?')
                ->execute([$name, $id, $edId]);
            audit_log('update', ['entity'=>'schedule_tracks','entity_id'=>$id,'edition_id'=>$edId]);
            flash_set('ok', 'Sala rinominata.');
            redirect('/admin/schedule.php');
        }

        case 'track_delete': {
            $id = post_int('id');
            // Verifica: nessuno slot deve usare questa sala.
            $stmt = db()->prepare('SELECT COUNT(*) FROM schedule_items WHERE track_id = ? AND edition_id = ?');
            $stmt->execute([$id, $edId]);
            $used = (int)$stmt->fetchColumn();
            if ($used > 0) {
                flash_set('error', "Impossibile eliminare: $used slot stanno ancora usando questa sala.");
                redirect('/admin/schedule.php');
            }
            if (crud_delete('schedule_tracks', $id, $edId)) {
                audit_log('delete', ['entity'=>'schedule_tracks','entity_id'=>$id,'edition_id'=>$edId]);
                flash_set('ok', 'Sala rimossa.');
            }
            redirect('/admin/schedule.php');
        }

        case 'track_move': {
            $id  = post_int('id');
            $dir = post_str('dir');
            if (!in_array($dir, ['up','down'], true)) abort(400);
            db_tx(function (PDO $pdo) use ($id, $edId, $dir) {
                $cur = $pdo->prepare('SELECT id, position FROM schedule_tracks WHERE id = ? AND edition_id = ?');
                $cur->execute([$id, $edId]);
                $row = $cur->fetch();
                if (!$row) return;
                $op = $dir === 'up' ? '<' : '>';
                $od = $dir === 'up' ? 'DESC' : 'ASC';
                $nb = $pdo->prepare(
                    "SELECT id, position FROM schedule_tracks WHERE edition_id = ? AND position $op ? ORDER BY position $od LIMIT 1"
                );
                $nb->execute([$edId, (int)$row['position']]);
                $n = $nb->fetch();
                if (!$n) return;
                // Swap position. La colonna è TINYINT UNSIGNED (0-255) ed esiste UNIQUE (edition_id, position):
                // uso come tampone MAX(position)+1, sempre libero entro il limite pratico (≤10 sale).
                $maxPos = (int)$pdo->query(
                    'SELECT COALESCE(MAX(position), 0) FROM schedule_tracks WHERE edition_id = ' . (int)$edId
                )->fetchColumn();
                $temp = $maxPos + 1;
                if ($temp > 255) {
                    throw new RuntimeException('Troppe sale per gestire lo swap (>255).');
                }
                $upd = $pdo->prepare('UPDATE schedule_tracks SET position = ? WHERE id = ?');
                $upd->execute([$temp,                  (int)$row['id']]);
                $upd->execute([(int)$row['position'],  (int)$n['id']]);
                $upd->execute([(int)$n['position'],    (int)$row['id']]);
            });
            redirect('/admin/schedule.php');
        }

        // -------------- ITEMS (slot) --------------
        case 'item_create':
        case 'item_update': {
            $id          = post_int('id');
            $dayDate     = post_str('day_date');
            $startTime   = post_str('start_time');
            $durationMin = post_int('duration_min', 50);
            $trackId     = post_int('track_id');
            $kind        = post_str('kind', 'talk');
            $title       = post_str('title');
            $speaker     = post_str('speaker');
            $description = post_str('description');
            $notes       = post_str('notes');

            $tracks = tracks_for_edition($edId);
            $validTrackIds = array_map(fn($t) => (int)$t['id'], $tracks);

            $errors = [];
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayDate))                 $errors[] = 'Data non valida.';
            if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime))            $errors[] = 'Ora non valida (HH:MM).';
            if ($durationMin < 1 || $durationMin > 1440)                       $errors[] = 'Durata fuori range (1–1440 min).';
            if (!in_array($trackId, $validTrackIds, true))                     $errors[] = 'Sala non valida per questa edizione.';
            if (!in_array($kind, SCHED_KINDS, true))                           $errors[] = 'Tipo non valido.';
            if ($title === '' || mb_strlen($title) > 200)                      $errors[] = 'Titolo obbligatorio (max 200).';
            if (mb_strlen($speaker) > 200)                                     $errors[] = 'Speaker troppo lungo.';
            if (mb_strlen($description) > 4000)                                $errors[] = 'Descrizione troppo lunga (max 4000 caratteri).';

            if ($errors) {
                flash_set('error', implode(' ', $errors));
                redirect('/admin/schedule.php' . ($action === 'item_update' ? "?edit=$id" : '?new=1'));
            }

            // day_label sempre derivata: l'utente non la imposta (vedi day_label_for()).
            $dayLabel = day_label_for($dayDate);
            // Normalizza HH:MM a HH:MM:00
            if (strlen($startTime) === 5) $startTime .= ':00';

            if ($action === 'item_create') {
                $sort = crud_next_sort('schedule_items', $edId);
                db()->prepare(
                    'INSERT INTO schedule_items
                       (edition_id, day_date, day_label, start_time, duration_min, track_id, kind, title, speaker, description, notes, sort)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([$edId, $dayDate, $dayLabel, $startTime, $durationMin, $trackId, $kind, $title,
                            $speaker !== '' ? $speaker : null,
                            $description !== '' ? $description : null,
                            $notes !== '' ? $notes : null, $sort]);
                $newId = (int)db()->lastInsertId();
                audit_log('create', ['entity'=>'schedule_items','entity_id'=>$newId,'edition_id'=>$edId]);
                flash_set('ok', 'Slot aggiunto.');
            } else {
                if (!crud_get('schedule_items', $id, $edId)) abort(404);
                db()->prepare(
                    'UPDATE schedule_items SET
                        day_date = ?, day_label = ?, start_time = ?, duration_min = ?, track_id = ?,
                        kind = ?, title = ?, speaker = ?, description = ?, notes = ?
                     WHERE id = ? AND edition_id = ?'
                )->execute([$dayDate, $dayLabel, $startTime, $durationMin, $trackId, $kind, $title,
                            $speaker !== '' ? $speaker : null,
                            $description !== '' ? $description : null,
                            $notes !== '' ? $notes : null, $id, $edId]);
                audit_log('update', ['entity'=>'schedule_items','entity_id'=>$id,'edition_id'=>$edId]);
                flash_set('ok', 'Slot aggiornato.');
            }
            redirect('/admin/schedule.php');
        }

        case 'item_delete': {
            $id = post_int('id');
            if (crud_delete('schedule_items', $id, $edId)) {
                audit_log('delete', ['entity'=>'schedule_items','entity_id'=>$id,'edition_id'=>$edId]);
                flash_set('ok', 'Slot eliminato.');
            }
            redirect('/admin/schedule.php');
        }

        // -------------- DAY SETTINGS (visibilità in home) --------------
        case 'day_toggle_home_preview': {
            $dayDate = post_str('day_date');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayDate)) abort(400);
            $show = post_int('show', 1) === 1 ? 1 : 0;
            // UPSERT su (edition_id, day_date).
            db()->prepare(
                'INSERT INTO schedule_day_settings (edition_id, day_date, show_in_home_preview)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE show_in_home_preview = VALUES(show_in_home_preview)'
            )->execute([$edId, $dayDate, $show]);
            audit_log('update', [
                'entity' => 'schedule_day_settings',
                'edition_id' => $edId,
                'payload' => ['day_date' => $dayDate, 'show_in_home_preview' => $show],
            ]);
            flash_set('ok', $show ? 'Giorno reso visibile in home.' : 'Giorno nascosto dalla home.');
            redirect('/admin/schedule.php');
        }

        default: abort(400, 'Action non valida.');
    }
}

// =====================================================================
// GET / Render
// =====================================================================
$tracks = tracks_for_edition($edId);
$tracksById = [];
foreach ($tracks as $t) { $tracksById[(int)$t['id']] = $t; }

$stmt = db()->prepare(
    'SELECT * FROM schedule_items WHERE edition_id = ? ORDER BY day_date, start_time, sort, id'
);
$stmt->execute([$edId]);
$items = $stmt->fetchAll();

// Raggruppa per day_date
$byDay = [];
foreach ($items as $it) {
    $byDay[$it['day_date']][] = $it;
}

// Settings dei giorni (visibilità home preview): mappa day_date => bool.
// Riga assente => default visibile.
$dayShowInHome = [];
$stmt = db()->prepare(
    'SELECT day_date, show_in_home_preview FROM schedule_day_settings WHERE edition_id = ?'
);
$stmt->execute([$edId]);
foreach ($stmt->fetchAll() as $row) {
    $dayShowInHome[(string)$row['day_date']] = (int)$row['show_in_home_preview'] === 1;
}

$editId  = get_int('edit', 0);
$isNew   = get_int('new', 0) === 1;
$editing = $editId > 0 ? crud_get('schedule_items', $editId, $edId) : null;
if ($editId > 0 && !$editing) {
    flash_set('error', 'Slot non trovato.');
    redirect('/admin/schedule.php');
}

// Suggerimento giorno per nuovo slot: ultimo giorno con slot, o date_start dell'edizione
$suggestedDate = $ed['date_start'];
if (empty($editing) && $isNew && !empty($items)) {
    $suggestedDate = end($items)['day_date'];
}

admin_layout_open('Palinsesto', 'schedule');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Edizione <?= e((string)$ed['year']) ?></div>
    <h1>Palinsesto</h1>
  </div>
  <?php if (!$editing && !$isNew && !empty($tracks)): ?>
    <a href="?new=1" class="btn accent">+ Aggiungi slot</a>
  <?php endif; ?>
</header>

<?= flash_render() ?>

<?php if (empty($tracks) && !$editing): ?>
  <div class="alert info">
    Per inserire slot devi prima creare almeno una <strong>sala</strong> qui sotto.
  </div>
<?php endif; ?>

<?php if (($editing || $isNew) && !empty($tracks)): $r = $editing ?? []; ?>
  <article class="card" style="margin-bottom:24px;">
    <header class="card-head">
      <h2><?= $editing ? 'Modifica slot' : 'Nuovo slot' ?></h2>
      <a href="/admin/schedule.php" class="card-cta">annulla</a>
    </header>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editing ? 'item_update' : 'item_create' ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?php endif; ?>

      <label class="field">
        <span class="field-label">Giorno</span>
        <input type="date" name="day_date"
               value="<?= e($r['day_date'] ?? $suggestedDate) ?>"
               min="<?= e($ed['date_setup'] ?: $ed['date_start']) ?>"
               max="<?= e($ed['date_teardown'] ?: $ed['date_end']) ?>" required>
      </label>

      <label class="field">
        <span class="field-label">Ora inizio</span>
        <input type="time" name="start_time"
               value="<?= e(substr((string)($r['start_time'] ?? '17:00'), 0, 5)) ?>" required>
      </label>

      <label class="field">
        <span class="field-label">Durata <span class="muted">minuti</span></span>
        <input type="number" name="duration_min"
               value="<?= e((string)($r['duration_min'] ?? 50)) ?>"
               min="1" max="1440" required>
      </label>

      <label class="field">
        <span class="field-label">Sala</span>
        <select name="track_id" required>
          <?php foreach ($tracks as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= (int)($r['track_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
              <?= e($t['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="field">
        <span class="field-label">Tipo</span>
        <select name="kind">
          <?php foreach (SCHED_KINDS as $k): ?>
            <option value="<?= e($k) ?>" <?= ($r['kind'] ?? 'talk') === $k ? 'selected' : '' ?>><?= e($k) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="field field-full">
        <span class="field-label">Titolo</span>
        <input type="text" name="title" value="<?= e($r['title'] ?? '') ?>" required maxlength="200">
      </label>

      <label class="field">
        <span class="field-label">Speaker <span class="muted">opzionale</span></span>
        <input type="text" name="speaker" value="<?= e((string)($r['speaker'] ?? '')) ?>" maxlength="200">
      </label>

      <label class="field field-full">
        <span class="field-label">Descrizione <span class="muted">pubblica · plain text · gli URL diventano link · max 4000 caratteri</span></span>
        <textarea name="description" rows="4" maxlength="4000" placeholder="Breve descrizione visibile sulla pagina pubblica del palinsesto."><?= e((string)($r['description'] ?? '')) ?></textarea>
      </label>

      <label class="field field-full">
        <span class="field-label">Note interne <span class="muted">non pubblicate</span></span>
        <textarea name="notes" rows="2"><?= e((string)($r['notes'] ?? '')) ?></textarea>
      </label>

      <div class="form-actions">
        <button type="submit" class="btn accent"><?= $editing ? 'Salva' : 'Aggiungi' ?></button>
        <a href="/admin/schedule.php" class="btn ghost">Annulla</a>
      </div>
    </form>
  </article>
<?php endif; ?>

<?php if (empty($byDay)): ?>
  <div class="empty">
    <p>Nessuno slot ancora.</p>
    <?php if (!empty($tracks) && !$isNew): ?>
      <p><a href="?new=1" class="btn accent">+ Aggiungi il primo</a></p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <?php foreach ($byDay as $date => $rows):
        $showInHome = $dayShowInHome[$date] ?? true;
  ?>
    <section class="day-block">
      <header class="day-head">
        <h2><?= e(day_label_for($date)) ?> <span class="muted mono"><?= e($date) ?></span></h2>
        <div style="display:flex;gap:14px;align-items:center;">
          <span class="muted"><?= count($rows) ?> slot</span>
          <form method="post" class="inline" title="<?= $showInHome ? 'Visibile nella preview della home — clicca per nascondere' : 'Nascosto dalla preview della home — clicca per mostrare' ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="day_toggle_home_preview">
            <input type="hidden" name="day_date" value="<?= e($date) ?>">
            <input type="hidden" name="show" value="<?= $showInHome ? 0 : 1 ?>">
            <button type="submit" class="btn-toggle <?= $showInHome ? 'on' : 'off' ?>">
              <?= $showInHome ? '👁 in home' : '🚫 nascosto in home' ?>
            </button>
          </form>
        </div>
      </header>
      <table class="data-table sched-table">
        <thead>
          <tr>
            <th style="width:120px;">Ora</th>
            <th style="width:120px;">Sala</th>
            <th style="width:100px;">Tipo</th>
            <th>Titolo</th>
            <th>Speaker</th>
            <th style="width:120px; text-align:right;">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $it):
              $start = substr((string)$it['start_time'], 0, 5);
              $end   = fmt_end($it['start_time'], (int)$it['duration_min']);
              $trk   = $tracksById[(int)$it['track_id']] ?? null;
          ?>
            <tr>
              <td class="mono small">
                <?= e($start) ?>
                <span class="muted">→ <?= e($end) ?></span>
                <div class="muted" style="font-size:11px;"><?= e((string)$it['duration_min']) ?> min</div>
              </td>
              <td><?= $trk ? e($trk['name']) : '<span class="muted">?</span>' ?></td>
              <td><span class="kbd"><?= e($it['kind']) ?></span></td>
              <td>
                <strong><?= e($it['title']) ?></strong>
                <?php if (!empty($it['description'])): ?>
                  <div class="small" style="margin-top:4px;" title="Descrizione pubblica">📄 <?= e(mb_strimwidth((string)$it['description'], 0, 140, '…')) ?></div>
                <?php endif; ?>
                <?php if (!empty($it['notes'])): ?>
                  <div class="muted small" style="margin-top:4px;" title="Note interne">📝 <?= e($it['notes']) ?></div>
                <?php endif; ?>
              </td>
              <td class="small"><?= e((string)$it['speaker']) ?: '<span class="muted">—</span>' ?></td>
              <td class="actions-cell">
                <a href="?edit=<?= (int)$it['id'] ?>" class="btn-icon" title="Modifica">✎</a>
                <form method="post" class="inline" onsubmit="return confirm('Eliminare «<?= e(addslashes($it['title'])) ?>»?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="item_delete">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <button type="submit" class="btn-icon danger" title="Elimina">×</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<!-- ================== SALE / TRACKS ================== -->
<section class="card" style="margin-top: 36px;">
  <header class="card-head">
    <h2>Sale & spazi</h2>
    <span class="muted small">Ogni slot deve essere assegnato a una sala.</span>
  </header>

  <?php if (empty($tracks)): ?>
    <p class="muted">Nessuna sala definita.</p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:60px;">#</th>
          <th>Nome</th>
          <th style="width:200px;text-align:right;">Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tracks as $i => $t): ?>
          <tr>
            <td class="mono"><?= (int)$t['position'] ?></td>
            <td>
              <form method="post" class="track-rename">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="track_update">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <input type="text" name="name" value="<?= e($t['name']) ?>" maxlength="80" required>
                <button type="submit" class="btn-icon" title="Salva">✓</button>
              </form>
            </td>
            <td class="actions-cell">
              <form method="post" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="track_move">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button type="submit" name="dir" value="up"   class="btn-icon" title="Su"  <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
                <button type="submit" name="dir" value="down" class="btn-icon" title="Giù" <?= $i === count($tracks) - 1 ? 'disabled' : '' ?>>↓</button>
              </form>
              <form method="post" class="inline" onsubmit="return confirm('Eliminare la sala «<?= e(addslashes($t['name'])) ?>»? (Se è in uso da qualche slot non sarà possibile.)');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="track_delete">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button type="submit" class="btn-icon danger" title="Elimina">×</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <form method="post" class="track-add" style="margin-top:14px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="track_create">
    <input type="text" name="name" maxlength="80" placeholder="Nome nuova sala (es. «Tendone»)" required>
    <button type="submit" class="btn">+ Aggiungi sala</button>
  </form>
</section>

<?php admin_layout_close();
