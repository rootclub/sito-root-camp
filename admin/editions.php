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
auth_require('admin');

// =====================================================================
// Helpers
// =====================================================================
function clone_edition(int $sourceId, array $newData): int
{
    return db_tx(function (PDO $pdo) use ($sourceId, $newData): int {
        $src = $pdo->prepare('SELECT * FROM editions WHERE id = ?');
        $src->execute([$sourceId]);
        $s = $src->fetch();
        if (!$s) {
            throw new RuntimeException('Edizione di origine non trovata.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO editions
              (year, slug, name, subtitle, is_current, is_published, registrations_open,
               date_start, date_end, date_setup, date_teardown, date_label, date_label_short,
               loc_name, loc_city, loc_region, loc_venue_note,
               ticket_price_eur, ticket_label, ticket_note, ticket_cards_note, card_price_eur,
               contact_email, contact_matrix, contact_telegram, contact_mastodon,
               food_intro, sleep_intro,
               hero_video_url, hero_poster_url)
             VALUES
              (?, ?, ?, ?, 0, 0, 0,
               ?, ?, ?, ?, ?, ?,
               ?, ?, ?, ?,
               ?, ?, ?, ?, ?,
               ?, ?, ?, ?,
               ?, ?,
               ?, ?)'
        );
        $insert->execute([
            $newData['year'], $newData['slug'], $newData['name'], $s['subtitle'],
            $newData['date_start'], $newData['date_end'],
            $s['date_setup'], $s['date_teardown'], $newData['date_label'], $s['date_label_short'],
            $s['loc_name'], $s['loc_city'], $s['loc_region'], $s['loc_venue_note'],
            $s['ticket_price_eur'], $s['ticket_label'], $s['ticket_note'], $s['ticket_cards_note'], $s['card_price_eur'],
            $s['contact_email'], $s['contact_matrix'], $s['contact_telegram'], $s['contact_mastodon'],
            $s['food_intro'], $s['sleep_intro'],
            $s['hero_video_url'], $s['hero_poster_url'],
        ]);
        $newId = (int)$pdo->lastInsertId();

        // Copia tracks (id devono essere remappati per gli items, ma non copiamo gli items, quindi non serve la mappa)
        $tracks = $pdo->prepare('SELECT position, name FROM schedule_tracks WHERE edition_id = ? ORDER BY position');
        $tracks->execute([$sourceId]);
        $insTrack = $pdo->prepare('INSERT INTO schedule_tracks (edition_id, position, name) VALUES (?, ?, ?)');
        foreach ($tracks->fetchAll() as $t) {
            $insTrack->execute([$newId, (int)$t['position'], $t['name']]);
        }

        // organizers
        $orgs = $pdo->prepare('SELECT name, role, is_placeholder, link_url, sort FROM organizers WHERE edition_id = ? ORDER BY sort, id');
        $orgs->execute([$sourceId]);
        $insOrg = $pdo->prepare('INSERT INTO organizers (edition_id, name, role, is_placeholder, link_url, sort) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($orgs->fetchAll() as $o) {
            $insOrg->execute([$newId, $o['name'], $o['role'], (int)$o['is_placeholder'], $o['link_url'], (int)$o['sort']]);
        }

        // rules
        $rl = $pdo->prepare('SELECT icon, title, body, sort FROM rules WHERE edition_id = ? ORDER BY sort, id');
        $rl->execute([$sourceId]);
        $insRl = $pdo->prepare('INSERT INTO rules (edition_id, icon, title, body, sort) VALUES (?, ?, ?, ?, ?)');
        foreach ($rl->fetchAll() as $r) {
            $insRl->execute([$newId, $r['icon'], $r['title'], $r['body'], (int)$r['sort']]);
        }

        // food_items
        $fd = $pdo->prepare('SELECT label, note, sort FROM food_items WHERE edition_id = ? ORDER BY sort, id');
        $fd->execute([$sourceId]);
        $insFd = $pdo->prepare('INSERT INTO food_items (edition_id, label, note, sort) VALUES (?, ?, ?, ?)');
        foreach ($fd->fetchAll() as $f) {
            $insFd->execute([$newId, $f['label'], $f['note'], (int)$f['sort']]);
        }

        // sleep_options
        $sl = $pdo->prepare('SELECT kind, title, body, price_eur, is_available, sort FROM sleep_options WHERE edition_id = ? ORDER BY sort, id');
        $sl->execute([$sourceId]);
        $insSl = $pdo->prepare('INSERT INTO sleep_options (edition_id, kind, title, body, price_eur, is_available, sort) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($sl->fetchAll() as $sleep) {
            $insSl->execute([$newId, $sleep['kind'], $sleep['title'], $sleep['body'], (int)$sleep['price_eur'], (int)$sleep['is_available'], (int)$sleep['sort']]);
        }

        // meal_slots: copiamo struttura ma azzeriamo le date (cambia il calendario fra edizioni)
        $ml = $pdo->prepare('SELECT code, label, is_available, sort FROM meal_slots WHERE edition_id = ? ORDER BY sort, id');
        $ml->execute([$sourceId]);
        $insMl = $pdo->prepare('INSERT INTO meal_slots (edition_id, code, label, day_date, is_available, sort) VALUES (?, ?, ?, NULL, ?, ?)');
        foreach ($ml->fetchAll() as $meal) {
            $insMl->execute([$newId, $meal['code'], $meal['label'], (int)$meal['is_available'], (int)$meal['sort']]);
        }

        // NB: schedule_items, iscrizioni e iscrizione_meals NON copiati per scelta.
        return $newId;
    });
}

// =====================================================================
// POST handler
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    switch ($action) {
        case 'create': {
            $year       = post_int('year');
            $name       = post_str('name');
            $dateStart  = post_str('date_start');
            $dateEnd    = post_str('date_end');
            $dateLabel  = post_str('date_label');
            $locName    = post_str('loc_name');

            $errors = [];
            if ($year < 2000 || $year > 2100)                            $errors[] = 'Anno non valido (2000–2100).';
            if ($name === '' || mb_strlen($name) > 120)                  $errors[] = 'Nome obbligatorio (max 120).';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart))        $errors[] = 'Data inizio non valida.';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd))          $errors[] = 'Data fine non valida.';
            if ($dateStart && $dateEnd && $dateEnd < $dateStart)         $errors[] = 'La fine deve essere ≥ inizio.';
            if ($dateLabel === '' || mb_strlen($dateLabel) > 80)         $errors[] = 'Etichetta date obbligatoria.';
            if ($locName === '' || mb_strlen($locName) > 120)            $errors[] = 'Nome location obbligatorio.';

            // Unicità anno
            if (!$errors) {
                $check = db()->prepare('SELECT 1 FROM editions WHERE year = ? LIMIT 1');
                $check->execute([$year]);
                if ($check->fetchColumn()) $errors[] = 'Esiste già un\'edizione per questo anno.';
            }

            if ($errors) {
                flash_set('error', implode(' ', $errors));
                redirect('/admin/editions.php');
            }

            db()->prepare(
                'INSERT INTO editions
                   (year, slug, name, date_start, date_end, date_label, loc_name)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$year, (string)$year, $name, $dateStart, $dateEnd, $dateLabel, $locName]);
            $newId = (int)db()->lastInsertId();

            audit_log('create', ['entity'=>'editions', 'entity_id'=>$newId, 'edition_id'=>$newId,
                                 'payload'=>['mode'=>'from_scratch','year'=>$year]]);
            edition_set_active($newId);
            flash_set('ok', "Edizione $year creata. La stai modificando ora — completa i dettagli qui sotto.");
            redirect('/admin/meta.php');
        }

        case 'clone': {
            $sourceId  = post_int('source_id');
            $year      = post_int('year');
            $name      = post_str('name');
            $dateStart = post_str('date_start');
            $dateEnd   = post_str('date_end');
            $dateLabel = post_str('date_label');

            $errors = [];
            if (!edition_get($sourceId))                                 $errors[] = 'Edizione di origine non valida.';
            if ($year < 2000 || $year > 2100)                            $errors[] = 'Anno non valido.';
            if ($name === '' || mb_strlen($name) > 120)                  $errors[] = 'Nome obbligatorio.';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart))        $errors[] = 'Data inizio non valida.';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd))          $errors[] = 'Data fine non valida.';
            if ($dateStart && $dateEnd && $dateEnd < $dateStart)         $errors[] = 'La fine deve essere ≥ inizio.';
            if ($dateLabel === '' || mb_strlen($dateLabel) > 80)         $errors[] = 'Etichetta date obbligatoria.';

            if (!$errors) {
                $check = db()->prepare('SELECT 1 FROM editions WHERE year = ? LIMIT 1');
                $check->execute([$year]);
                if ($check->fetchColumn()) $errors[] = 'Esiste già un\'edizione per questo anno.';
            }

            if ($errors) {
                flash_set('error', implode(' ', $errors));
                redirect('/admin/editions.php');
            }

            try {
                $newId = clone_edition($sourceId, [
                    'year'       => $year,
                    'slug'       => (string)$year,
                    'name'       => $name,
                    'date_start' => $dateStart,
                    'date_end'   => $dateEnd,
                    'date_label' => $dateLabel,
                ]);
                audit_log('clone', ['entity'=>'editions', 'entity_id'=>$newId, 'edition_id'=>$newId,
                                    'payload'=>['source_id'=>$sourceId,'year'=>$year]]);
                edition_set_active($newId);
                flash_set('ok', "Edizione $year clonata da #{$sourceId}. Modifica i dettagli qui sotto.");
                redirect('/admin/meta.php');
            } catch (\Throwable $e) {
                error_log('clone_edition failed: ' . $e->getMessage());
                flash_set('error', 'Clonazione fallita: ' . $e->getMessage());
                redirect('/admin/editions.php');
            }
        }

        case 'make_current': {
            $id = post_int('id');
            if (!edition_get($id)) abort(404);
            edition_make_current($id);
            audit_log('make_current', ['entity'=>'editions','entity_id'=>$id,'edition_id'=>$id]);
            flash_set('ok', 'Edizione impostata come live sul sito.');
            redirect('/admin/editions.php');
        }

        case 'delete': {
            $id = post_int('id');
            $ed = edition_get($id);
            if (!$ed) abort(404);

            // Blocco se ci sono iscrizioni: per non perdere dati per errore
            $stmt = db()->prepare('SELECT COUNT(*) FROM iscrizioni WHERE edition_id = ?');
            $stmt->execute([$id]);
            $nIscr = (int)$stmt->fetchColumn();
            if ($nIscr > 0) {
                flash_set('error', "Impossibile eliminare: ci sono $nIscr iscrizioni. Esportale e cancellale prima.");
                redirect('/admin/editions.php');
            }

            db()->prepare('DELETE FROM editions WHERE id = ?')->execute([$id]);
            audit_log('delete', ['entity'=>'editions','entity_id'=>$id,
                                 'payload'=>['year'=>$ed['year'],'name'=>$ed['name']]]);
            // Se era l'edizione attiva in sessione, edition_active() farà fallback al prossimo accesso
            flash_set('ok', "Edizione {$ed['year']} eliminata.");
            redirect('/admin/editions.php');
        }

        default: abort(400);
    }
}

// =====================================================================
// GET / Render
// =====================================================================
$rows = db()->query(
    "SELECT e.*,
       (SELECT COUNT(*) FROM schedule_items WHERE edition_id = e.id) AS n_slot,
       (SELECT COUNT(*) FROM organizers     WHERE edition_id = e.id) AS n_org,
       (SELECT COUNT(*) FROM iscrizioni     WHERE edition_id = e.id) AS n_iscr
     FROM editions e
     ORDER BY year DESC"
)->fetchAll();

admin_layout_open('Edizioni', 'editions');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Solo admin</div>
    <h1>Edizioni</h1>
  </div>
</header>

<?= flash_render() ?>

<?php if (empty($rows)): ?>
  <div class="empty">
    <p>Nessuna edizione presente. Creane una qui sotto.</p>
  </div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:60px;">Anno</th>
        <th>Nome</th>
        <th>Date</th>
        <th>Stato</th>
        <th>Numeri</th>
        <th style="width:300px;text-align:right;">Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono"><strong><?= e((string)$r['year']) ?></strong></td>
          <td>
            <strong><?= e($r['name']) ?></strong>
            <?php if (!empty($r['subtitle'])): ?>
              <div class="small muted"><?= e($r['subtitle']) ?></div>
            <?php endif; ?>
          </td>
          <td class="small mono"><?= e($r['date_start']) ?> → <?= e($r['date_end']) ?></td>
          <td>
            <?php if ($r['is_current']): ?>
              <span class="pill pill-ok">★ live</span>
            <?php endif; ?>
            <?php if ($r['is_published']): ?>
              <span class="pill pill-info">pubblicata</span>
            <?php else: ?>
              <span class="pill pill-mute">bozza</span>
            <?php endif; ?>
            <?php if ($r['registrations_open']): ?>
              <span class="pill pill-ok">iscriz. ON</span>
            <?php endif; ?>
          </td>
          <td class="small">
            <?= (int)$r['n_slot'] ?> slot · <?= (int)$r['n_org'] ?> org · <strong><?= (int)$r['n_iscr'] ?></strong> iscritti
          </td>
          <td class="actions-cell">
            <form method="post" action="/admin/_switch_edition.php" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="edition_id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="back" value="/admin/meta.php">
              <button type="submit" class="btn-icon" title="Modifica dettagli">✎</button>
            </form>
            <?php if (!$r['is_current']): ?>
              <form method="post" class="inline" onsubmit="return confirm('Impostare l\'edizione <?= (int)$r['year'] ?> come LIVE sul sito?\n\nL\'edizione corrente verrà disattivata.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="make_current">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn-icon" title="Imposta come live">★</button>
              </form>
            <?php endif; ?>
            <form method="post" class="inline"
                  onsubmit="return confirm('Eliminare definitivamente l\'edizione <?= (int)$r['year'] ?>?\n\nVerranno cancellati: tutto il palinsesto, le organizzazioni, le regole, food/sleep.\nLe iscrizioni bloccano la cancellazione (esportale prima).');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="btn-icon danger" title="Elimina edizione">×</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<section class="dash-grid" style="margin-top: 36px;">

  <!-- ============== CLONA ============== -->
  <article class="card">
    <header class="card-head">
      <h2>Clona da edizione esistente</h2>
      <span class="muted small">copia tracks, organizers, rules, food, pasti, sleep · NON copia iscrizioni e palinsesto</span>
    </header>
    <?php if (empty($rows)): ?>
      <p class="muted">Nessuna edizione da cui clonare.</p>
    <?php else: ?>
      <form method="post" class="form-grid" style="border:none;box-shadow:none;padding:0;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="clone">

        <label class="field">
          <span class="field-label">Origine</span>
          <select name="source_id" required>
            <?php foreach ($rows as $r): ?>
              <option value="<?= (int)$r['id'] ?>"><?= e((string)$r['year']) ?> — <?= e($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="field">
          <span class="field-label">Nuovo anno</span>
          <input type="number" name="year" value="<?= e((string)((int)$rows[0]['year'] + 1)) ?>" min="2000" max="2100" required>
        </label>

        <label class="field field-full">
          <span class="field-label">Nuovo nome</span>
          <input type="text" name="name" placeholder="es. RooT-Camp 2027" required maxlength="120">
        </label>

        <label class="field">
          <span class="field-label">Data inizio</span>
          <input type="date" name="date_start" required>
        </label>
        <label class="field">
          <span class="field-label">Data fine</span>
          <input type="date" name="date_end" required>
        </label>

        <label class="field field-full">
          <span class="field-label">Etichetta date <span class="muted">es. "9 — 11 luglio 2027"</span></span>
          <input type="text" name="date_label" required maxlength="80">
        </label>

        <div class="form-actions">
          <button type="submit" class="btn accent">Clona edizione</button>
        </div>
      </form>
    <?php endif; ?>
  </article>

  <!-- ============== DA ZERO ============== -->
  <article class="card">
    <header class="card-head">
      <h2>Crea da zero</h2>
      <span class="muted small">crea un'edizione vuota, completi i dettagli dopo</span>
    </header>
    <form method="post" class="form-grid" style="border:none;box-shadow:none;padding:0;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <label class="field">
        <span class="field-label">Anno</span>
        <input type="number" name="year" value="<?= e((string)((int)date('Y') + 1)) ?>" min="2000" max="2100" required>
      </label>

      <label class="field field-full">
        <span class="field-label">Nome</span>
        <input type="text" name="name" placeholder="es. RooT-Camp 2027" required maxlength="120">
      </label>

      <label class="field">
        <span class="field-label">Data inizio</span>
        <input type="date" name="date_start" required>
      </label>
      <label class="field">
        <span class="field-label">Data fine</span>
        <input type="date" name="date_end" required>
      </label>

      <label class="field field-full">
        <span class="field-label">Etichetta date</span>
        <input type="text" name="date_label" required maxlength="80">
      </label>

      <label class="field field-full">
        <span class="field-label">Nome location</span>
        <input type="text" name="loc_name" required maxlength="120">
      </label>

      <div class="form-actions">
        <button type="submit" class="btn">Crea edizione</button>
      </div>
    </form>
  </article>
</section>

<?php admin_layout_close();
