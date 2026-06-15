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

// =====================================================================
// Export CSV: elenco prenotati di un singolo pasto, con dieta/allergie
// (output prima di qualunque HTML)
// =====================================================================
if (get_str('format') === 'csv') {
    $mealId = get_int('meal', 0);
    $meal = $mealId > 0 ? crud_get('meal_slots', $mealId, $edId) : null;
    if (!$meal) { flash_set('error','Pasto non trovato.'); redirect('/admin/meals.php'); }

    $stmt = db()->prepare(
        'SELECT i.name, i.email, i.phone, i.diet, i.notes, i.sleep_kind, i.checked_in
           FROM iscrizione_meals im
           JOIN iscrizioni i ON i.id = im.iscrizione_id
          WHERE im.meal_slot_id = ? AND i.edition_id = ?
          ORDER BY i.name'
    );
    $stmt->execute([$mealId, $edId]);

    $filename = sprintf('pasto-%s-%d-%s.csv', $meal['code'], (int)$ed['year'], date('Ymd-His'));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 per Excel
    fputcsv($out, ['nome','email','telefono','dieta_allergie','note','pernottamento','check_in']);
    while ($r = $stmt->fetch()) {
        fputcsv($out, [
            $r['name'], $r['email'], $r['phone'],
            $r['diet'], $r['notes'], $r['sleep_kind'],
            $r['checked_in'] ? 'sì' : 'no',
        ]);
    }
    fclose($out);
    audit_log('export_csv', ['entity'=>'meal_slots','entity_id'=>$mealId,'edition_id'=>$edId,
                             'payload'=>['code'=>$meal['code']]]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    switch ($action) {
        case 'create':
        case 'update': {
            $id      = post_int('id');
            $code    = post_str('code');
            $label   = post_str('label');
            $dayDate = post_str('day_date');
            $isAvail = post_bool('is_available');

            $errors = [];
            if ($code === '' || mb_strlen($code) > 40)        $errors[] = 'Codice obbligatorio (max 40).';
            if (!preg_match('/^[a-z0-9_\-]+$/', $code))       $errors[] = 'Il codice può contenere solo a-z, 0-9, _ e -.';
            if ($label === '' || mb_strlen($label) > 160)     $errors[] = 'Etichetta obbligatoria (max 160).';
            if ($dayDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayDate)) {
                $errors[] = 'Data non valida (formato YYYY-MM-DD).';
            }

            // Unicità del code per edizione
            if (!$errors) {
                $sql = 'SELECT id FROM meal_slots WHERE edition_id = ? AND code = ? LIMIT 1';
                $check = db()->prepare($sql);
                $check->execute([$edId, $code]);
                $existing = (int)($check->fetchColumn() ?: 0);
                if ($existing > 0 && $existing !== $id) {
                    $errors[] = 'Esiste già un pasto con questo codice in questa edizione.';
                }
            }

            if ($errors) {
                flash_set('error', implode(' ', $errors));
                redirect('/admin/meals.php' . ($action === 'update' ? "?edit=$id" : '?new=1'));
            }

            $dayDateSql = $dayDate !== '' ? $dayDate : null;

            if ($action === 'create') {
                $sort = crud_next_sort('meal_slots', $edId);
                db()->prepare(
                    'INSERT INTO meal_slots (edition_id, code, label, day_date, is_available, sort)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$edId, $code, $label, $dayDateSql, $isAvail, $sort]);
                $newId = (int)db()->lastInsertId();
                audit_log('create', ['entity'=>'meal_slots','entity_id'=>$newId,'edition_id'=>$edId]);
                flash_set('ok', 'Pasto aggiunto.');
            } else {
                if (!crud_get('meal_slots', $id, $edId)) abort(404);
                db()->prepare(
                    'UPDATE meal_slots SET code = ?, label = ?, day_date = ?, is_available = ?
                      WHERE id = ? AND edition_id = ?'
                )->execute([$code, $label, $dayDateSql, $isAvail, $id, $edId]);
                audit_log('update', ['entity'=>'meal_slots','entity_id'=>$id,'edition_id'=>$edId]);
                flash_set('ok', 'Modifiche salvate.');
            }
            redirect('/admin/meals.php');
        }

        case 'delete': {
            $id = post_int('id');
            if (crud_delete('meal_slots', $id, $edId)) {
                audit_log('delete', ['entity'=>'meal_slots','entity_id'=>$id,'edition_id'=>$edId]);
                flash_set('ok', 'Pasto rimosso.');
            }
            redirect('/admin/meals.php');
        }

        case 'move': {
            crud_move('meal_slots', post_int('id'), $edId, post_str('dir'));
            redirect('/admin/meals.php');
        }

        default: abort(400);
    }
}

$rows = db()->prepare('SELECT * FROM meal_slots WHERE edition_id = ? ORDER BY sort, id');
$rows->execute([$edId]);
$rows = $rows->fetchAll();

// Conteggio iscritti per pasto (utile per la cucina)
$counts = db()->prepare(
    'SELECT meal_slot_id, COUNT(*) AS n FROM iscrizione_meals
       WHERE meal_slot_id IN (SELECT id FROM meal_slots WHERE edition_id = ?)
       GROUP BY meal_slot_id'
);
$counts->execute([$edId]);
$countByMeal = [];
foreach ($counts->fetchAll() as $r) {
    $countByMeal[(int)$r['meal_slot_id']] = (int)$r['n'];
}

$editId  = get_int('edit', 0);
$isNew   = get_int('new', 0) === 1;
$editing = $editId > 0 ? crud_get('meal_slots', $editId, $edId) : null;
if ($editId > 0 && !$editing) { flash_set('error','Pasto non trovato.'); redirect('/admin/meals.php'); }

admin_layout_open('Pasti', 'meals');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Edizione <?= e((string)$ed['year']) ?></div>
    <h1>Pasti prenotabili</h1>
  </div>
  <?php if (!$editing && !$isNew): ?>
    <a href="?new=1" class="btn accent">+ Aggiungi pasto</a>
  <?php endif; ?>
</header>

<?= flash_render() ?>

<div class="alert info">
  Questi pasti compaiono nel form pubblico di iscrizione come checkbox. Servono solo a contare le presenze per la cucina, non incidono sul totale.
  Eventuali prezzi vanno scritti dentro l'<em>etichetta</em>, es. "<em>Cena · venerdì sera (10 €)</em>".
</div>

<?php if ($editing || $isNew): $r = $editing ?? []; ?>
  <article class="card" style="margin-bottom:24px;">
    <header class="card-head">
      <h2><?= $editing ? 'Modifica pasto' : 'Nuovo pasto' ?></h2>
      <a href="/admin/meals.php" class="card-cta">annulla</a>
    </header>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?php endif; ?>

      <label class="field">
        <span class="field-label">Codice <span class="muted">slug stabile, es. "fri_dinner"</span></span>
        <input type="text" name="code" value="<?= e($r['code'] ?? '') ?>" required maxlength="40" pattern="[a-z0-9_\-]+">
      </label>

      <label class="field">
        <span class="field-label">Giorno <span class="muted">opzionale, raggruppa nel form</span></span>
        <input type="date" name="day_date" value="<?= e((string)($r['day_date'] ?? '')) ?>">
      </label>

      <label class="field field-full">
        <span class="field-label">Etichetta <span class="muted">testo mostrato all'utente</span></span>
        <input type="text" name="label" value="<?= e($r['label'] ?? '') ?>" required maxlength="160" placeholder='es. "Cena · venerdì 10 luglio (10 €)"'>
      </label>

      <label class="field">
        <span class="field-label">Disponibile</span>
        <select name="is_available">
          <option value="1" <?= ($r['is_available'] ?? 1) ? 'selected' : '' ?>>sì — visibile nel form</option>
          <option value="0" <?= isset($r['is_available']) && !$r['is_available'] ? 'selected' : '' ?>>no — nascosto</option>
        </select>
      </label>

      <div class="form-actions">
        <button type="submit" class="btn accent"><?= $editing ? 'Salva' : 'Aggiungi' ?></button>
        <a href="/admin/meals.php" class="btn ghost">Annulla</a>
      </div>
    </form>
  </article>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="empty">
    <p>Nessun pasto configurato per questa edizione.</p>
    <?php if (!$isNew): ?><p><a href="?new=1" class="btn accent">+ Aggiungine uno</a></p><?php endif; ?>
  </div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Etichetta</th>
        <th style="width:140px;">Codice</th>
        <th style="width:120px;">Giorno</th>
        <th style="width:100px;">Iscritti</th>
        <th style="width:100px;">Stato</th>
        <th style="width:170px; text-align:right;">Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td><strong><?= e($r['label']) ?></strong></td>
          <td class="mono small"><?= e($r['code']) ?></td>
          <td class="small mono"><?= e((string)($r['day_date'] ?? '')) ?: '<span class="muted">—</span>' ?></td>
          <td class="mono"><strong><?= (int)($countByMeal[(int)$r['id']] ?? 0) ?></strong></td>
          <td>
            <?php if ($r['is_available']): ?>
              <span class="pill pill-ok">visibile</span>
            <?php else: ?>
              <span class="pill pill-mute">nascosto</span>
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
            <a href="?meal=<?= (int)$r['id'] ?>&amp;format=csv" class="btn-icon" title="Scarica elenco prenotati (CSV)">⤓</a>
            <a href="?edit=<?= (int)$r['id'] ?>" class="btn-icon" title="Modifica">✎</a>
            <form method="post" class="inline" onsubmit="return confirm('Eliminare «<?= e(addslashes($r['label'])) ?>»?\n\nLe prenotazioni esistenti verranno rimosse.');">
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
