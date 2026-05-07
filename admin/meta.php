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

// NB: is_current si modifica da /admin/editions.php (richiede ruolo admin).

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f = [
        'name'              => post_str('name'),
        'subtitle'          => post_str('subtitle'),
        'date_start'        => post_str('date_start'),
        'date_end'          => post_str('date_end'),
        'date_setup'        => post_str('date_setup'),
        'date_teardown'     => post_str('date_teardown'),
        'date_label'        => post_str('date_label'),
        'date_label_short'  => post_str('date_label_short'),
        'loc_name'          => post_str('loc_name'),
        'loc_city'          => post_str('loc_city'),
        'loc_region'        => post_str('loc_region'),
        'loc_venue_note'    => post_str('loc_venue_note'),
        'ticket_price_eur'  => post_int('ticket_price_eur'),
        'ticket_label'      => post_str('ticket_label'),
        'ticket_note'       => post_str('ticket_note'),
        'ticket_cards_note' => post_str('ticket_cards_note'),
        'card_price_eur'    => post_int('card_price_eur'),
        'contact_email'     => post_str('contact_email'),
        'contact_matrix'    => post_str('contact_matrix'),
        'contact_telegram'  => post_str('contact_telegram'),
        'contact_mastodon'  => post_str('contact_mastodon'),
        'food_intro'        => post_str('food_intro'),
        'sleep_intro'       => post_str('sleep_intro'),
        'hero_video_url'    => post_str('hero_video_url'),
        'hero_poster_url'   => post_str('hero_poster_url'),
        'marquee_words'     => post_str('marquee_words'),
        'is_published'      => post_bool('is_published'),
        'registrations_open'=> post_bool('registrations_open'),
    ];

    // Normalizza il marquee: trim per riga, scarta righe vuote, ricompone con \n.
    $mqLines = preg_split('/\r\n|\r|\n/', $f['marquee_words']);
    $mqLines = array_filter(array_map('trim', $mqLines), fn($s) => $s !== '');
    $f['marquee_words'] = implode("\n", $mqLines);

    $errors = [];
    if ($f['name'] === '' || mb_strlen($f['name']) > 120)        $errors[] = 'Nome edizione obbligatorio (max 120).';
    if ($f['date_start'] === '' || $f['date_end'] === '')        $errors[] = 'Date di inizio e fine obbligatorie.';
    foreach (['date_start','date_end','date_setup','date_teardown'] as $dk) {
        if ($f[$dk] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$dk])) {
            $errors[] = "Data $dk non valida (atteso AAAA-MM-GG).";
        }
    }
    if ($f['date_start'] !== '' && $f['date_end'] !== '' && $f['date_end'] < $f['date_start']) {
        $errors[] = 'La data di fine deve essere ≥ inizio.';
    }
    if ($f['date_label'] === '' || mb_strlen($f['date_label']) > 80) $errors[] = 'Etichetta date obbligatoria (max 80).';
    if ($f['loc_name'] === '' || mb_strlen($f['loc_name']) > 120)    $errors[] = 'Nome location obbligatorio (max 120).';
    if ($f['ticket_price_eur'] < 0 || $f['ticket_price_eur'] > 9999) $errors[] = 'Prezzo biglietto fuori range.';
    if ($f['card_price_eur']   < 0 || $f['card_price_eur']   > 9999) $errors[] = 'Prezzo tessera fuori range.';
    if ($f['contact_email'] !== '' && !filter_var($f['contact_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email contatto non valida.';

    if ($errors) {
        flash_set('error', implode(' ', $errors));
        redirect('/admin/meta.php');
    }

    $sql = 'UPDATE editions SET
              name = ?, subtitle = ?,
              date_start = ?, date_end = ?, date_setup = ?, date_teardown = ?,
              date_label = ?, date_label_short = ?,
              loc_name = ?, loc_city = ?, loc_region = ?, loc_venue_note = ?,
              ticket_price_eur = ?, ticket_label = ?, ticket_note = ?, ticket_cards_note = ?, card_price_eur = ?,
              contact_email = ?, contact_matrix = ?, contact_telegram = ?, contact_mastodon = ?,
              food_intro = ?, sleep_intro = ?,
              hero_video_url = ?, hero_poster_url = ?,
              marquee_words = ?,
              is_published = ?, registrations_open = ?
            WHERE id = ?';
    db()->prepare($sql)->execute([
        $f['name'], $f['subtitle'] ?: null,
        $f['date_start'], $f['date_end'],
        $f['date_setup'] ?: null, $f['date_teardown'] ?: null,
        $f['date_label'], $f['date_label_short'] ?: null,
        $f['loc_name'], $f['loc_city'] ?: null, $f['loc_region'] ?: null, $f['loc_venue_note'] ?: null,
        $f['ticket_price_eur'], $f['ticket_label'] ?: null, $f['ticket_note'] ?: null, $f['ticket_cards_note'] ?: null, $f['card_price_eur'],
        $f['contact_email'] ?: null, $f['contact_matrix'] ?: null, $f['contact_telegram'] ?: null, $f['contact_mastodon'] ?: null,
        $f['food_intro'] ?: null, $f['sleep_intro'] ?: null,
        $f['hero_video_url'] ?: null, $f['hero_poster_url'] ?: null,
        $f['marquee_words'] ?: null,
        $f['is_published'], $f['registrations_open'],
        $edId
    ]);
    audit_log('update', ['entity'=>'editions','entity_id'=>$edId,'edition_id'=>$edId]);
    flash_set('ok', 'Info edizione salvate.');
    redirect('/admin/meta.php');
}

// Re-fetch dopo eventuale update
$ed = edition_get($edId);

admin_layout_open('Info edizione', 'meta');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Edizione <?= e((string)$ed['year']) ?></div>
    <h1>Info edizione</h1>
  </div>
</header>

<?= flash_render() ?>

<form method="post" class="form-grid">
  <?= csrf_field() ?>

  <h2 class="form-section">Identità</h2>
  <label class="field">
    <span class="field-label">Nome</span>
    <input type="text" name="name" value="<?= e($ed['name']) ?>" required maxlength="120">
  </label>
  <label class="field">
    <span class="field-label">Sottotitolo <span class="muted">es. "prima edizione"</span></span>
    <input type="text" name="subtitle" value="<?= e((string)$ed['subtitle']) ?>" maxlength="120">
  </label>

  <h2 class="form-section">Date</h2>
  <label class="field">
    <span class="field-label">Inizio</span>
    <input type="date" name="date_start" value="<?= e($ed['date_start']) ?>" required>
  </label>
  <label class="field">
    <span class="field-label">Fine</span>
    <input type="date" name="date_end" value="<?= e($ed['date_end']) ?>" required>
  </label>
  <label class="field">
    <span class="field-label">Setup</span>
    <input type="date" name="date_setup" value="<?= e((string)$ed['date_setup']) ?>">
  </label>
  <label class="field">
    <span class="field-label">Teardown</span>
    <input type="date" name="date_teardown" value="<?= e((string)$ed['date_teardown']) ?>">
  </label>
  <label class="field">
    <span class="field-label">Etichetta lunga <span class="muted">es. "10 — 12 luglio 2026"</span></span>
    <input type="text" name="date_label" value="<?= e($ed['date_label']) ?>" required maxlength="80">
  </label>
  <label class="field">
    <span class="field-label">Etichetta breve <span class="muted">es. "10–11 lug 2026"</span></span>
    <input type="text" name="date_label_short" value="<?= e((string)$ed['date_label_short']) ?>" maxlength="60">
  </label>

  <h2 class="form-section">Luogo</h2>
  <label class="field">
    <span class="field-label">Nome luogo</span>
    <input type="text" name="loc_name" value="<?= e($ed['loc_name']) ?>" required maxlength="120">
  </label>
  <label class="field">
    <span class="field-label">Città</span>
    <input type="text" name="loc_city" value="<?= e((string)$ed['loc_city']) ?>" maxlength="120">
  </label>
  <label class="field">
    <span class="field-label">Regione</span>
    <input type="text" name="loc_region" value="<?= e((string)$ed['loc_region']) ?>" maxlength="120">
  </label>
  <label class="field field-full">
    <span class="field-label">Nota location</span>
    <textarea name="loc_venue_note" rows="2"><?= e((string)$ed['loc_venue_note']) ?></textarea>
  </label>

  <h2 class="form-section">Biglietti & tessere</h2>
  <label class="field">
    <span class="field-label">Prezzo biglietto base €</span>
    <input type="number" name="ticket_price_eur" value="<?= e((string)$ed['ticket_price_eur']) ?>" min="0" max="9999" required>
  </label>
  <label class="field">
    <span class="field-label">Etichetta prezzo <span class="muted">es. "15 €"</span></span>
    <input type="text" name="ticket_label" value="<?= e((string)$ed['ticket_label']) ?>" maxlength="40">
  </label>
  <label class="field">
    <span class="field-label">Prezzo tessera € <span class="muted">una unità</span></span>
    <input type="number" name="card_price_eur" value="<?= e((string)$ed['card_price_eur']) ?>" min="0" max="9999" required>
  </label>
  <label class="field field-full">
    <span class="field-label">Nota biglietto</span>
    <textarea name="ticket_note" rows="2"><?= e((string)$ed['ticket_note']) ?></textarea>
  </label>
  <label class="field field-full">
    <span class="field-label">Nota tessere</span>
    <textarea name="ticket_cards_note" rows="2"><?= e((string)$ed['ticket_cards_note']) ?></textarea>
  </label>

  <h2 class="form-section">Contatti</h2>
  <label class="field">
    <span class="field-label">Email</span>
    <input type="email" name="contact_email" value="<?= e((string)$ed['contact_email']) ?>" maxlength="180">
  </label>
  <label class="field">
    <span class="field-label">Matrix</span>
    <input type="text" name="contact_matrix" value="<?= e((string)$ed['contact_matrix']) ?>" maxlength="180">
  </label>
  <label class="field">
    <span class="field-label">Telegram</span>
    <input type="text" name="contact_telegram" value="<?= e((string)$ed['contact_telegram']) ?>" maxlength="180">
  </label>
  <label class="field">
    <span class="field-label">Mastodon</span>
    <input type="text" name="contact_mastodon" value="<?= e((string)$ed['contact_mastodon']) ?>" maxlength="180">
  </label>

  <h2 class="form-section">Sezioni con intro testuale</h2>
  <label class="field field-full">
    <span class="field-label">Intro food <span class="muted">testo breve sopra la lista cibo/bere</span></span>
    <textarea name="food_intro" rows="2"><?= e((string)$ed['food_intro']) ?></textarea>
  </label>
  <label class="field field-full">
    <span class="field-label">Intro sleep</span>
    <textarea name="sleep_intro" rows="2"><?= e((string)$ed['sleep_intro']) ?></textarea>
  </label>

  <h2 class="form-section">Hero (video di apertura)</h2>
  <label class="field">
    <span class="field-label">URL video <span class="muted">relativa al sito o assoluta</span></span>
    <input type="text" name="hero_video_url" value="<?= e((string)$ed['hero_video_url']) ?>" maxlength="255">
  </label>
  <label class="field">
    <span class="field-label">URL poster <span class="muted">opzionale</span></span>
    <input type="text" name="hero_poster_url" value="<?= e((string)$ed['hero_poster_url']) ?>" maxlength="255">
  </label>
  <label class="field field-full">
    <span class="field-label">Marquee sotto la hero <span class="muted">una frase per riga (separate da ✦ nel rendering). Vuoto = default.</span></span>
    <textarea name="marquee_words" rows="6" placeholder="/RooT-Camp 2026&#10;10-11 LUGLIO&#10;FRATTA TERME&#10;HACKER CAMP"><?= e((string)($ed['marquee_words'] ?? '')) ?></textarea>
  </label>

  <h2 class="form-section">Pubblicazione</h2>
  <label class="field-check">
    <input type="checkbox" name="is_published" value="1" <?= $ed['is_published'] ? 'checked' : '' ?>>
    <span>Pubblicata <span class="muted">(visibile nell'archivio)</span></span>
  </label>
  <label class="field-check">
    <input type="checkbox" name="registrations_open" value="1" <?= $ed['registrations_open'] ? 'checked' : '' ?>>
    <span>Iscrizioni aperte <span class="muted">(form pubblico accetta nuove iscrizioni)</span></span>
  </label>
  <p class="muted" style="grid-column: 1 / -1;">
    Per impostare questa edizione come <strong>live sul sito</strong> (is_current) usa la pagina
    <a href="/admin/editions.php" style="text-decoration:underline;">Edizioni</a>.
  </p>

  <div class="form-actions">
    <button type="submit" class="btn accent">Salva tutto</button>
  </div>
</form>

<?php admin_layout_close();
