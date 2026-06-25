<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/edition.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/admin_layout.php';
require_once __DIR__ . '/../inc/response.php';

auth_boot();
auth_require();

$ed = edition_active();
if (!$ed) { flash_set('error','Nessuna edizione disponibile.'); redirect('/admin/index.php'); }
$edId = (int)$ed['id'];

// =====================================================================
// POST handler
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    switch ($action) {

        case 'toggle_checkin': {
            $id = post_int('id');
            // MariaDB valuta i SET left-to-right: al momento del CASE checked_in è già
            // il NUOVO valore. Quindi: nuovo=1 ⇒ NOW(), nuovo=0 ⇒ NULL.
            $stmt = db()->prepare(
                'UPDATE iscrizioni
                    SET checked_in = 1 - checked_in,
                        checked_in_at = CASE WHEN checked_in = 1 THEN NOW() ELSE NULL END
                  WHERE id = ? AND edition_id = ?'
            );
            $stmt->execute([$id, $edId]);
            audit_log('toggle_checkin', ['entity'=>'iscrizioni','entity_id'=>$id,'edition_id'=>$edId]);
            redirect($_POST['back'] ?? '/admin/iscrizioni.php');
        }

        case 'update': {
            $id = post_int('id');
            $name  = post_str('name');
            $email = post_str('email');
            $phone = post_str('phone');
            $age   = post_str('age');
            $diet  = post_str('diet');
            $notes = post_str('notes');

            $errors = [];
            if ($name === '' || mb_strlen($name) > 160) $errors[] = 'Nome obbligatorio (max 160).';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email non valida.';
            if (mb_strlen($phone) > 40) $errors[] = 'Telefono troppo lungo.';
            if (!in_array($age, ['adult', 'minor'], true)) $errors[] = 'Età indicativa non valida.';
            if (mb_strlen($diet) > 255) $errors[] = 'Dieta troppo lunga (max 255).';

            $stmt = db()->prepare('SELECT id FROM iscrizioni WHERE id = ? AND edition_id = ? LIMIT 1');
            $stmt->execute([$id, $edId]);
            if (!$stmt->fetchColumn()) abort(404);

            if ($errors) {
                flash_set('error', implode(' ', $errors));
                redirect("/admin/iscrizioni.php?id=$id");
            }

            db()->prepare(
                'UPDATE iscrizioni SET name = ?, email = ?, phone = ?, age = ?, diet = ?, notes = ?
                  WHERE id = ? AND edition_id = ?'
            )->execute([
                $name, $email,
                $phone !== '' ? $phone : null,
                $age,
                $diet  !== '' ? $diet  : null,
                $notes !== '' ? $notes : null,
                $id, $edId
            ]);
            audit_log('update', ['entity'=>'iscrizioni','entity_id'=>$id,'edition_id'=>$edId]);
            flash_set('ok', 'Iscrizione aggiornata.');
            redirect("/admin/iscrizioni.php?id=$id");
        }

        case 'regen_token': {
            $id = post_int('id');
            $stmt = db()->prepare('SELECT id FROM iscrizioni WHERE id = ? AND edition_id = ? LIMIT 1');
            $stmt->execute([$id, $edId]);
            if (!$stmt->fetchColumn()) abort(404);
            $newToken = bin2hex(random_bytes(16));
            db()->prepare('UPDATE iscrizioni SET edit_token = ? WHERE id = ? AND edition_id = ?')
                ->execute([$newToken, $id, $edId]);
            audit_log('regen_token', ['entity'=>'iscrizioni','entity_id'=>$id,'edition_id'=>$edId]);
            flash_set('ok', 'Token modifica rigenerato. Il link precedente non è più valido.');
            redirect("/admin/iscrizioni.php?id=$id");
        }

        case 'delete': {
            $id = post_int('id');
            $stmt = db()->prepare('SELECT name FROM iscrizioni WHERE id = ? AND edition_id = ? LIMIT 1');
            $stmt->execute([$id, $edId]);
            $row = $stmt->fetch();
            if (!$row) abort(404);
            db()->prepare('DELETE FROM iscrizioni WHERE id = ? AND edition_id = ?')->execute([$id, $edId]);
            audit_log('delete', ['entity'=>'iscrizioni','entity_id'=>$id,'edition_id'=>$edId,
                                 'payload'=>['name'=>$row['name']]]);
            flash_set('ok', "Iscrizione di «{$row['name']}» eliminata.");
            redirect('/admin/iscrizioni.php');
        }

        default: abort(400);
    }
}

// =====================================================================
// Build filter (usato sia per HTML che per CSV)
// =====================================================================
$filter = [
    'q'     => trim((string)($_GET['q'] ?? '')),
    'sleep' => trim((string)($_GET['sleep'] ?? '')),
    'check' => trim((string)($_GET['check'] ?? '')),  // ''=tutti, '0'=non check, '1'=check
    'from'  => trim((string)($_GET['from'] ?? '')),   // YYYY-MM-DD
    'to'    => trim((string)($_GET['to'] ?? '')),     // YYYY-MM-DD
];

$where = ['edition_id = ?'];
$args  = [$edId];

if ($filter['q'] !== '') {
    $where[] = '(name LIKE ? OR email LIKE ?)';
    $args[]  = '%' . $filter['q'] . '%';
    $args[]  = '%' . $filter['q'] . '%';
}
if ($filter['sleep'] !== '') {
    $where[] = 'sleep_kind = ?';
    $args[]  = $filter['sleep'];
}
if ($filter['check'] === '0' || $filter['check'] === '1') {
    $where[] = 'checked_in = ?';
    $args[]  = (int)$filter['check'];
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter['from'])) {
    $where[] = 'created_at >= ?';
    $args[]  = $filter['from'] . ' 00:00:00';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter['to'])) {
    $where[] = 'created_at <= ?';
    $args[]  = $filter['to'] . ' 23:59:59';
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

// =====================================================================
// CSV export (output prima di qualunque HTML)
// =====================================================================
if (get_str('format') === 'csv') {
    $sql = "SELECT id, created_at, name, email, phone, age, sleep_kind, n_cards,
                   ticket_eur, sleep_eur, cards_eur, total_eur,
                   checked_in, checked_in_at, diet, notes, ip
              FROM iscrizioni $whereSql ORDER BY created_at";
    $stmt = db()->prepare($sql);
    $stmt->execute($args);

    // Prefetch pasti per tutti gli iscritti dell'edizione
    $mealStmt = db()->prepare(
        'SELECT im.iscrizione_id, m.code
           FROM iscrizione_meals im
           JOIN meal_slots m ON m.id = im.meal_slot_id
          WHERE m.edition_id = ?
          ORDER BY m.sort, m.id'
    );
    $mealStmt->execute([$edId]);
    $mealsByIscr = [];
    foreach ($mealStmt->fetchAll() as $mr) {
        $mealsByIscr[(int)$mr['iscrizione_id']][] = (string)$mr['code'];
    }

    $filename = sprintf('iscrizioni-%d-%s.csv', (int)$ed['year'], date('Ymd-His'));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    // BOM UTF-8 per Excel
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'id','created_at','name','email','phone','age','sleep_kind','meals','n_meals',
        'ticket_eur','sleep_eur','total_eur',
        'checked_in','checked_in_at','diet','notes','ip',
    ]);
    while ($r = $stmt->fetch()) {
        $mealsList = $mealsByIscr[(int)$r['id']] ?? [];
        fputcsv($out, [
            $r['id'], $r['created_at'], $r['name'], $r['email'], $r['phone'],
            $r['age'] === 'minor' ? 'minorenne' : 'adulto',
            $r['sleep_kind'], implode(',', $mealsList), count($mealsList),
            $r['ticket_eur'], $r['sleep_eur'], $r['total_eur'],
            $r['checked_in'] ? 'yes' : 'no', $r['checked_in_at'],
            $r['diet'], $r['notes'], $r['ip'],
        ]);
    }
    fclose($out);
    audit_log('export_csv', ['entity'=>'iscrizioni','edition_id'=>$edId,
                             'payload'=>['filters'=>$filter]]);
    exit;
}

// =====================================================================
// Detail view (?id=N)
// =====================================================================
$detailId = get_int('id', 0);
$detail = null;
$detailMeals = [];
if ($detailId > 0) {
    $stmt = db()->prepare('SELECT * FROM iscrizioni WHERE id = ? AND edition_id = ? LIMIT 1');
    $stmt->execute([$detailId, $edId]);
    $detail = $stmt->fetch();
    if (!$detail) {
        flash_set('error', 'Iscrizione non trovata.');
        redirect('/admin/iscrizioni.php');
    }
    // Pasti prenotati per questa iscrizione
    $stmt = db()->prepare(
        'SELECT m.code, m.label, m.day_date
           FROM iscrizione_meals im
           JOIN meal_slots m ON m.id = im.meal_slot_id
          WHERE im.iscrizione_id = ?
          ORDER BY m.sort, m.id'
    );
    $stmt->execute([$detailId]);
    $detailMeals = $stmt->fetchAll();
}

// =====================================================================
// List + stats (vista normale)
// =====================================================================
$stmt = db()->prepare(
    "SELECT i.*,
            (SELECT COUNT(*) FROM iscrizione_meals im WHERE im.iscrizione_id = i.id) AS n_meals
       FROM iscrizioni i
       $whereSql
   ORDER BY created_at DESC"
);
$stmt->execute($args);
$rows = $stmt->fetchAll();

// Sommari sull'intera edizione (non filtrati)
$stats = db()->prepare(
    "SELECT
        COUNT(*) AS n,
        COALESCE(SUM(checked_in), 0) AS n_check,
        COALESCE(SUM(total_eur), 0) AS tot_all,
        COALESCE(SUM(CASE WHEN checked_in = 1 THEN total_eur ELSE 0 END), 0) AS tot_check
     FROM iscrizioni WHERE edition_id = ?"
);
$stats->execute([$edId]);
$stats = $stats->fetch();

$sleepMix = db()->prepare(
    "SELECT sleep_kind, COUNT(*) AS n FROM iscrizioni WHERE edition_id = ? GROUP BY sleep_kind ORDER BY n DESC"
);
$sleepMix->execute([$edId]);
$sleepMix = $sleepMix->fetchAll();

// Lista sleep_kind possibili (per il filtro)
$sleepStmt = db()->prepare("SELECT kind FROM sleep_options WHERE edition_id = ? ORDER BY sort, id");
$sleepStmt->execute([$edId]);
$sleepKinds = array_column($sleepStmt->fetchAll(), 'kind');

admin_layout_open($detail ? 'Iscrizione · ' . $detail['name'] : 'Iscrizioni', 'iscrizioni');
?>

<?php if ($detail): // ============= DETAIL ============= ?>
  <header class="page-head">
    <div>
      <div class="eyebrow"><a href="/admin/iscrizioni.php" style="color:inherit;">← tutte le iscrizioni</a></div>
      <h1>
        <?= e($detail['name']) ?>
        <?php if ($detail['checked_in']): ?>
          <span class="pill pill-ok" style="font-size:14px;vertical-align:middle;">✓ check-in fatto</span>
        <?php endif; ?>
      </h1>
    </div>
    <div style="display:flex;gap:10px;">
      <form method="post" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_checkin">
        <input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">
        <input type="hidden" name="back" value="/admin/iscrizioni.php?id=<?= (int)$detail['id'] ?>">
        <button type="submit" class="btn <?= $detail['checked_in'] ? 'ghost' : 'accent' ?>">
          <?= $detail['checked_in'] ? '↺ Annulla check-in' : '✓ Check-in' ?>
        </button>
      </form>
      <form method="post" class="inline" onsubmit="return confirm('Eliminare definitivamente l\'iscrizione di «<?= e(addslashes($detail['name'])) ?>»?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">
        <button type="submit" class="btn ghost" style="border-color:var(--error);color:var(--error);">Elimina</button>
      </form>
    </div>
  </header>

  <?= flash_render() ?>

  <section class="dash-grid">
    <article class="card">
      <header class="card-head"><h2>Dati anagrafici</h2></header>
      <form method="post" class="form-grid" style="border:none;box-shadow:none;padding:0;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">

        <label class="field">
          <span class="field-label">Nome</span>
          <input type="text" name="name" value="<?= e($detail['name']) ?>" required maxlength="160">
        </label>
        <label class="field">
          <span class="field-label">Email</span>
          <input type="email" name="email" value="<?= e($detail['email']) ?>" maxlength="180">
        </label>
        <label class="field">
          <span class="field-label">Telefono</span>
          <input type="text" name="phone" value="<?= e((string)$detail['phone']) ?>" maxlength="40">
        </label>
        <label class="field">
          <span class="field-label">Età indicativa</span>
          <select name="age">
            <option value="adult"<?= ($detail['age'] ?? 'adult') === 'adult' ? ' selected' : '' ?>>adulto</option>
            <option value="minor"<?= ($detail['age'] ?? '') === 'minor' ? ' selected' : '' ?>>minorenne accompagnato</option>
          </select>
        </label>
        <label class="field field-full">
          <span class="field-label">Note</span>
          <input type="text" name="diet" value="<?= e((string)($detail['diet'] ?? '')) ?>" maxlength="255">
        </label>
        <label class="field field-full">
          <span class="field-label">Richieste Aggiuntive</span>
          <textarea name="notes" rows="3"><?= e((string)$detail['notes']) ?></textarea>
        </label>
        <div class="form-actions">
          <button type="submit" class="btn accent">Salva</button>
        </div>
      </form>
    </article>

    <article class="card">
      <header class="card-head"><h2>Pasti prenotati</h2></header>
      <?php if (empty($detailMeals)): ?>
        <p class="muted">Nessun pasto selezionato.</p>
      <?php else: ?>
        <ul style="margin:0;padding-left:18px;">
          <?php foreach ($detailMeals as $m): ?>
            <li><?= e($m['label']) ?>
              <?php if (!empty($m['day_date'])): ?>
                <span class="muted small mono"><?= e((string)$m['day_date']) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p class="muted small" style="margin-top:14px;">L'iscritto può cambiare le sue scelte dal link in email.</p>
    </article>

    <article class="card">
      <header class="card-head"><h2>Link di modifica</h2></header>
      <?php
        $editUrl = (defined('APP_URL') && APP_URL !== '' ? APP_URL : '') . '/modifica.php?t=' . urlencode((string)($detail['edit_token'] ?? ''));
      ?>
      <p class="small">Questo è il link che l'utente ha ricevuto via email per modificare la sua iscrizione:</p>
      <p class="mono small" style="word-break:break-all;background:#f5f3e8;padding:10px;border-radius:6px;border:1px solid rgba(15,42,26,.15);">
        <?= e($editUrl) ?>
      </p>
      <form method="post" class="inline" onsubmit="return confirm('Rigenerare il token? Il link precedente smetterà di funzionare immediatamente.');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="regen_token">
        <input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">
        <button type="submit" class="btn ghost">Rigenera token</button>
      </form>
    </article>

    <article class="card">
      <header class="card-head"><h2>Riepilogo iscrizione</h2></header>
      <dl class="kv">
        <dt>Iscritto il</dt>
        <dd class="mono small"><?= e(date('d/m/Y H:i', strtotime((string)$detail['created_at']))) ?></dd>

        <dt>Pernotto</dt>
        <dd><?= e($detail['sleep_kind']) ?></dd>

        <dt>N. pasti</dt>
        <dd><strong><?= count($detailMeals) ?></strong></dd>

        <dt>Biglietto</dt>
        <dd><?= (int)$detail['ticket_eur'] ?> €</dd>

        <dt>Sleep</dt>
        <dd><?= (int)$detail['sleep_eur'] ?> €</dd>

        <dt>Totale</dt>
        <dd><strong><?= (int)$detail['total_eur'] ?> €</strong></dd>

        <?php if ((int)$detail['n_cards'] > 0): ?>
          <dt>Tessere <span class="muted small">(legacy)</span></dt>
          <dd><?= (int)$detail['n_cards'] ?> × <?= (int)$detail['cards_eur'] ?> €</dd>
        <?php endif; ?>

        <?php if ($detail['checked_in']): ?>
          <dt>Check-in</dt>
          <dd class="small mono">
            <?php if (!empty($detail['checked_in_at'])): ?>
              <?= e(date('d/m/Y H:i', strtotime((string)$detail['checked_in_at']))) ?>
            <?php else: ?>
              <span class="muted">orario non registrato</span>
            <?php endif; ?>
          </dd>
        <?php endif; ?>

        <?php if (!empty($detail['ip'])): ?>
          <dt>IP iscrizione</dt>
          <dd class="small mono"><?= e($detail['ip']) ?></dd>
        <?php endif; ?>
      </dl>
    </article>
  </section>

<?php else: // ============= LIST ============= ?>

  <header class="page-head">
    <div>
      <div class="eyebrow">Edizione <?= e((string)$ed['year']) ?></div>
      <h1>Iscrizioni</h1>
    </div>
    <div style="display:flex;gap:10px;">
      <a href="?<?= http_build_query(array_merge($filter, ['format'=>'csv'])) ?>" class="btn">Esporta CSV</a>
    </div>
  </header>

  <?= flash_render() ?>

  <section class="stats">
    <div class="stat-card">
      <div class="stat-num"><?= (int)$stats['n'] ?></div>
      <div class="stat-lab">iscritti totali</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= (int)$stats['n_check'] ?></div>
      <div class="stat-lab">check-in fatti</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= (int)$stats['tot_all'] ?> €</div>
      <div class="stat-lab">incasso previsto</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= (int)$stats['tot_check'] ?> €</div>
      <div class="stat-lab">incasso da check-in</div>
    </div>
  </section>

  <?php if (!empty($sleepMix)): ?>
    <p style="margin: -8px 0 18px; font-size: 13px; color: var(--ink-dim);">
      <strong>Sleep mix:</strong>
      <?php foreach ($sleepMix as $s): ?>
        <span class="pill" style="margin-left:6px;"><?= e($s['sleep_kind']) ?> · <?= (int)$s['n'] ?></span>
      <?php endforeach; ?>
    </p>
  <?php endif; ?>

  <form method="get" class="filters-bar">
    <input type="text" name="q" value="<?= e($filter['q']) ?>" placeholder="cerca nome o email…">

    <select name="sleep">
      <option value="">tutti i pernotti</option>
      <?php foreach ($sleepKinds as $k): ?>
        <option value="<?= e($k) ?>" <?= $filter['sleep'] === $k ? 'selected' : '' ?>><?= e($k) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="check">
      <option value=""  <?= $filter['check'] === '' ? 'selected' : '' ?>>tutti</option>
      <option value="0" <?= $filter['check'] === '0' ? 'selected' : '' ?>>non checkin</option>
      <option value="1" <?= $filter['check'] === '1' ? 'selected' : '' ?>>checkin fatto</option>
    </select>

    <label class="filter-date">da <input type="date" name="from" value="<?= e($filter['from']) ?>"></label>
    <label class="filter-date">a  <input type="date" name="to"   value="<?= e($filter['to']) ?>"></label>

    <button type="submit" class="btn">Filtra</button>
    <a href="/admin/iscrizioni.php" class="btn ghost">Reset</a>
  </form>

  <?php if (empty($rows)): ?>
    <div class="empty">
      <p>Nessuna iscrizione corrispondente ai filtri.</p>
      <?php if ((int)$stats['n'] === 0): ?>
        <p class="muted">Quando il form pubblico riceverà la prima iscrizione, comparirà qui.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Quando</th>
          <th>Nome</th>
          <th>Email</th>
          <th>Sleep</th>
          <th>Pasti</th>
          <th>Note</th>
          <th>€</th>
          <th>Check-in</th>
          <th style="width:130px;text-align:right;">Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="mono small"><?= e(date('d/m H:i', strtotime((string)$r['created_at']))) ?></td>
            <td>
              <a href="?id=<?= (int)$r['id'] ?>"><strong><?= e($r['name']) ?></strong></a>
              <?php if (!empty($r['phone'])): ?>
                <div class="small muted mono"><?= e($r['phone']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small"><?= e($r['email']) ?></td>
            <td class="small"><?= e($r['sleep_kind']) ?></td>
            <td class="mono"><?= (int)($r['n_meals'] ?? 0) ?></td>
            <td class="note-dots">
              <?php if (!empty($r['diet'])): ?>
                <span class="note-dot note-dot-diet" tabindex="0">N<span class="note-tip"><strong>Note:</strong> <?= e((string)$r['diet']) ?></span></span>
              <?php endif; ?>
              <?php if (!empty($r['notes'])): ?>
                <span class="note-dot note-dot-req" tabindex="0">R<span class="note-tip"><strong>Richieste:</strong> <?= e((string)$r['notes']) ?></span></span>
              <?php endif; ?>
              <?php if (empty($r['diet']) && empty($r['notes'])): ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td class="mono"><strong><?= (int)$r['total_eur'] ?></strong></td>
            <td>
              <?php if ($r['checked_in']): ?>
                <span class="pill pill-ok">✓</span>
                <?php if (!empty($r['checked_in_at'])): ?>
                  <span class="muted small mono"><?= e(date('d/m H:i', strtotime((string)$r['checked_in_at']))) ?></span>
                <?php endif; ?>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td class="actions-cell">
              <form method="post" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_checkin">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '/admin/iscrizioni.php') ?>">
                <button type="submit" class="btn-icon" title="<?= $r['checked_in'] ? 'Annulla check-in' : 'Check-in' ?>">
                  <?= $r['checked_in'] ? '↺' : '✓' ?>
                </button>
              </form>
              <a href="?id=<?= (int)$r['id'] ?>" class="btn-icon" title="Apri">→</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted small" style="margin-top:14px;">
      Mostrati <strong><?= count($rows) ?></strong> risultati su <?= (int)$stats['n'] ?> totali nell'edizione.
    </p>
  <?php endif; ?>

<?php endif; ?>

<?php admin_layout_close();
