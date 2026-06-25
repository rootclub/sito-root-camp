<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/edition.php';
require_once __DIR__ . '/inc/response.php';

// =====================================================================
// Pagina pubblica per modificare la propria iscrizione tramite token.
// URL: /modifica.php?t=<32 hex>
//
// GET  → render form pre-compilato
// POST → applica modifiche e re-renderizza
//
// Auth: il token in URL fa da credenziale. Niente CSRF: chi conosce il token
// è già autorizzato; un attaccante che lo conoscesse potrebbe modificare
// direttamente, non serve un secondo livello.
// =====================================================================

$token = isset($_GET['t']) && is_string($_GET['t']) ? trim($_GET['t']) : '';
if ($token === '' && isset($_POST['t']) && is_string($_POST['t'])) {
    $token = trim($_POST['t']);
}

// Token validation: 32 hex
$tokenValid = (bool)preg_match('/^[a-f0-9]{32}$/i', $token);

$iscrizione = null;
$edition    = null;
if ($tokenValid) {
    $stmt = db()->prepare('SELECT * FROM iscrizioni WHERE edit_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $iscrizione = $stmt->fetch();
    if ($iscrizione) {
        $edition = edition_get((int)$iscrizione['edition_id']);
    }
}

$errors = [];
$ok     = false;

// ---- Carica le opzioni dell'edizione (sleep + meals) ----
$sleepOpts = [];
$mealSlots = [];
$selectedMealIds = [];

if ($iscrizione && $edition) {
    // is_available = selezionabile: le opzioni esaurite sono comunque mostrate
    // (disabilitate, marcate "Esaurito"), allineato a iscrizione.html.
    $stmt = db()->prepare(
        'SELECT kind, title, body, price_eur, is_available FROM sleep_options
          WHERE edition_id = ? ORDER BY sort, id'
    );
    $stmt->execute([(int)$edition['id']]);
    $sleepOpts = $stmt->fetchAll();

    $stmt = db()->prepare(
        'SELECT id, code, label, day_date FROM meal_slots
          WHERE edition_id = ? AND is_available = 1 ORDER BY sort, id'
    );
    $stmt->execute([(int)$edition['id']]);
    $mealSlots = $stmt->fetchAll();

    $stmt = db()->prepare(
        'SELECT meal_slot_id FROM iscrizione_meals WHERE iscrizione_id = ?'
    );
    $stmt->execute([(int)$iscrizione['id']]);
    foreach ($stmt->fetchAll() as $r) {
        $selectedMealIds[(int)$r['meal_slot_id']] = true;
    }
}

// ---- Submit ----
if ($iscrizione && $edition && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = isset($_POST['phone']) && is_string($_POST['phone']) ? trim($_POST['phone']) : '';
    $age   = isset($_POST['age'])   && is_string($_POST['age'])   ? trim($_POST['age'])   : '';
    $sleepKind = isset($_POST['sleep_kind']) && is_string($_POST['sleep_kind']) ? trim($_POST['sleep_kind']) : '';
    $diet  = isset($_POST['diet'])  && is_string($_POST['diet'])  ? trim($_POST['diet'])  : '';
    $notes = isset($_POST['notes']) && is_string($_POST['notes']) ? trim($_POST['notes']) : '';

    $mealsRaw = $_POST['meals'] ?? [];
    if (!is_array($mealsRaw)) $mealsRaw = [];
    $mealsPosted = array_values(array_unique(array_filter(
        array_map(fn($m) => trim((string)$m), $mealsRaw),
        fn($s) => $s !== ''
    )));

    if (mb_strlen($phone) > 40)  $errors[] = 'Telefono troppo lungo.';
    if (!in_array($age, ['adult', 'minor'], true)) $errors[] = 'Età indicativa non valida.';
    if (mb_strlen($diet)  > 255) $errors[] = 'Dieta troppo lunga (max 255).';
    if (mb_strlen($notes) > 1000) $errors[] = 'Note troppo lunghe (max 1000).';

    // Verifica sleep_kind: solo le opzioni con is_available = 1 sono selezionabili,
    // ma si può MANTENERE l'opzione già scelta anche se nel frattempo è esaurita.
    $availSleep = [];
    foreach ($sleepOpts as $s) {
        if (!empty($s['is_available'])) {
            $availSleep[(string)$s['kind']] = (int)$s['price_eur'];
        }
    }
    $currentKind = (string)$iscrizione['sleep_kind'];
    if (!isset($availSleep[$currentKind])) {
        foreach ($sleepOpts as $s) {
            if ((string)$s['kind'] === $currentKind) {
                $availSleep[$currentKind] = (int)$s['price_eur'];
                break;
            }
        }
    }
    if (!isset($availSleep[$sleepKind])) {
        $errors[] = 'Opzione di pernottamento non valida.';
    }

    // Verifica meal codes
    $mealIdsByCode = [];
    foreach ($mealSlots as $m) {
        $mealIdsByCode[(string)$m['code']] = (int)$m['id'];
    }
    $newMealIds = [];
    foreach ($mealsPosted as $code) {
        if (!isset($mealIdsByCode[$code])) {
            $errors[] = 'Pasto non valido: ' . htmlspecialchars($code, ENT_QUOTES);
            break;
        }
        $newMealIds[] = $mealIdsByCode[$code];
    }
    if (count($newMealIds) > 50) $errors[] = 'Troppi pasti selezionati.';

    if (!$errors) {
        $sleepEur  = $availSleep[$sleepKind];
        $ticketEur = (int)$iscrizione['ticket_eur']; // mantieni il prezzo storico
        $cardsEur  = (int)$iscrizione['cards_eur'];
        $totalEur  = $ticketEur + $sleepEur + $cardsEur;

        try {
            db_tx(function (PDO $pdo) use (
                $iscrizione, $phone, $age, $sleepKind, $sleepEur, $totalEur,
                $diet, $notes, $newMealIds
            ) {
                $pdo->prepare(
                    'UPDATE iscrizioni
                        SET phone = ?, age = ?, sleep_kind = ?, sleep_eur = ?, total_eur = ?,
                            diet = ?, notes = ?
                      WHERE id = ?'
                )->execute([
                    $phone !== '' ? $phone : null,
                    $age,
                    $sleepKind, $sleepEur, $totalEur,
                    $diet  !== '' ? $diet  : null,
                    $notes !== '' ? $notes : null,
                    (int)$iscrizione['id'],
                ]);

                $pdo->prepare('DELETE FROM iscrizione_meals WHERE iscrizione_id = ?')
                    ->execute([(int)$iscrizione['id']]);
                if ($newMealIds) {
                    $ins = $pdo->prepare(
                        'INSERT INTO iscrizione_meals (iscrizione_id, meal_slot_id) VALUES (?, ?)'
                    );
                    foreach ($newMealIds as $mid) {
                        $ins->execute([(int)$iscrizione['id'], $mid]);
                    }
                }
            });
            $ok = true;
            // Ricarica stato per il render
            $stmt = db()->prepare('SELECT * FROM iscrizioni WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$iscrizione['id']]);
            $iscrizione = $stmt->fetch();
            $selectedMealIds = [];
            foreach ($newMealIds as $mid) $selectedMealIds[$mid] = true;
        } catch (\Throwable $e) {
            error_log('modifica.php update failed: ' . $e->getMessage());
            $errors[] = 'Errore nel salvataggio. Riprova fra poco.';
        }
    }
} else {
    // GET: pre-compila i POST con i valori esistenti per la form
    if ($iscrizione) {
        $_POST['phone']      = (string)($iscrizione['phone'] ?? '');
        $_POST['age']        = (string)($iscrizione['age'] ?? 'adult');
        $_POST['sleep_kind'] = (string)($iscrizione['sleep_kind'] ?? '');
        $_POST['diet']       = (string)($iscrizione['diet'] ?? '');
        $_POST['notes']      = (string)($iscrizione['notes'] ?? '');
    }
}

// ---- Helpers locali ----
function mh(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function mday_label(string $iso): string {
    if ($iso === '') return 'Senza data';
    $days   = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
    $months = ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
    $t = strtotime($iso . ' 12:00:00');
    if ($t === false) return $iso;
    return $days[(int)date('w', $t)] . ' ' . (int)date('j', $t) . ' ' . $months[(int)date('n', $t) - 1];
}

// Raggruppa i pasti per giorno per il render
$mealsByDay = [];
foreach ($mealSlots as $m) {
    $key = (string)($m['day_date'] ?? '');
    $mealsByDay[$key][] = $m;
}
ksort($mealsByDay);

?><!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title>Modifica iscrizione · /RooT-Camp</title>
  <link rel="stylesheet" href="styles/global.css">
  <style>
    .page-hero {
      background: linear-gradient(180deg, var(--hot) 0%, var(--sun) 100%);
      color: var(--cream);
      padding: 80px 0 60px;
    }
    .page-hero .sec-eyebrow { color: var(--cream); }
    .page-hero .sec-eyebrow::before { background: var(--cream); }

    .form-card {
      background: var(--cream);
      border: 2px solid var(--ink);
      border-radius: var(--r-lg);
      padding: 36px;
      box-shadow: 6px 6px 0 var(--ink);
      max-width: 760px;
      margin: 48px auto 0;
    }
    .form-row { margin-bottom: 22px; }
    .form-row label.lbl {
      display: block;
      font-family: var(--font-ui);
      font-size: 11px;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: var(--ink-dim);
      margin-bottom: 8px;
      font-weight: 700;
    }
    .form-row input[type="text"],
    .form-row input[type="email"],
    .form-row input[type="tel"],
    .form-row textarea,
    .form-row select {
      width: 100%;
      font-family: var(--font-display);
      font-size: 16px;
      padding: 12px 14px;
      border: 2px solid var(--ink);
      border-radius: var(--r-sm);
      background: var(--cream);
      box-shadow: 2px 2px 0 var(--ink);
    }
    .form-row textarea { min-height: 90px; resize: vertical; font-family: var(--font-body); }
    .form-row .hint { font-family: var(--font-ui); font-size: 12px; color: var(--ink-dim); margin-top: 6px; }
    .lock-row {
      background: rgba(15,42,26,.04);
      border: 1.5px dashed rgba(15,42,26,.25);
      border-radius: var(--r-sm);
      padding: 14px 16px;
      margin-bottom: 22px;
    }
    .lock-row .lbl {
      display: block;
      font-family: var(--font-ui);
      font-size: 11px;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: var(--ink-dim);
      margin-bottom: 4px;
      font-weight: 700;
    }
    .lock-row .val { font-family: var(--font-display); font-size: 17px; font-weight: 700; }

    .radio-cards { display: grid; gap: 12px; }
    .radio-cards label {
      display: grid;
      grid-template-columns: 24px 1fr auto;
      gap: 14px;
      padding: 16px;
      border: 2px solid var(--ink);
      border-radius: var(--r-md);
      cursor: pointer;
      background: var(--cream);
    }
    .radio-cards input[type="radio"] {
      appearance: none;
      width: 22px; height: 22px;
      border: 2px solid var(--ink);
      border-radius: 50%;
      background: var(--cream);
      cursor: pointer;
      margin: 2px 0 0;
      position: relative;
    }
    .radio-cards input[type="radio"]:checked {
      background: var(--hot);
      box-shadow: inset 0 0 0 4px var(--cream);
    }
    .radio-cards label:has(input:checked) { background: var(--sun); }
    .radio-cards label.sold-out { opacity: .55; cursor: not-allowed; background: #f3efe6; }
    .radio-cards label.sold-out input[type="radio"] { cursor: not-allowed; }
    .rc-text strong { display: block; font-family: var(--font-display); font-size: 17px; margin-bottom: 4px; }
    .rc-text span { font-size: 14px; color: var(--ink-dim); }
    .rc-price {
      font-family: var(--font-ui); font-weight: 700; font-size: 13px;
      align-self: center; padding: 4px 10px;
      background: var(--ink); color: var(--cream); border-radius: 999px;
    }
    .rc-price.free { background: var(--grass-3); }
    .rc-price.sold { background: transparent; color: var(--ink-dim); border: 2px solid var(--ink-dim); }

    .meal-grid { display: grid; gap: 18px; }
    .meal-day-head {
      font-family: var(--font-ui); font-size: 11px; letter-spacing: .14em;
      text-transform: uppercase; color: var(--ink-dim); font-weight: 700; margin-bottom: 8px;
    }
    .meal-day-items {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 8px;
    }
    .meal-day-items label {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px;
      border: 2px solid var(--ink);
      border-radius: var(--r-sm);
      cursor: pointer;
      background: var(--cream);
      font-family: var(--font-ui);
      font-size: 13px;
    }
    .meal-day-items label:has(input:checked) { background: var(--grass-1); font-weight: 700; }
    .meal-day-items input[type="checkbox"] {
      appearance: none;
      width: 18px; height: 18px;
      border: 2px solid var(--ink);
      background: var(--cream);
      cursor: pointer;
      border-radius: 4px;
      position: relative;
      flex-shrink: 0;
    }
    .meal-day-items input[type="checkbox"]:checked { background: var(--hot); }
    .meal-day-items input[type="checkbox"]:checked::after {
      content: "✓";
      position: absolute;
      color: var(--cream);
      font-weight: 700;
      font-size: 14px;
      top: -3px; left: 2px;
    }

    .alert { padding: 16px 18px; border-radius: var(--r-md); margin-bottom: 22px; font-family: var(--font-ui); font-size: 14px; line-height: 1.5; }
    .alert.error   { background: #ffe0d6; border: 2px solid var(--hot);     color: var(--hot); }
    .alert.success { background: var(--grass-1); border: 2px solid var(--grass-3); }
    .alert.info    { background: var(--sun); border: 2px solid var(--ink); color: var(--ink); }

    .divider { height: 2px; background: rgba(15,42,26,.12); margin: 28px 0; border: none; }

    .submit-row {
      display: flex; gap: 16px; flex-wrap: wrap; align-items: center;
      margin-top: 12px;
    }
  </style>
</head>
<body>
  <div data-slot="topbar"></div>

  <section class="page-hero">
    <div class="wrap">
      <div class="sec-eyebrow">modifica iscrizione</div>
      <?php if ($iscrizione && $edition): ?>
        <h1 class="h-1" style="max-width:18ch;font-size:clamp(40px,6vw,72px);">
          Cambia <em class="hand" style="font-style:normal;color:var(--ink);">le tue scelte</em>.
        </h1>
        <p style="max-width:54ch;margin-top:18px;font-size:18px;">
          Hai cambiato programma su qualche pasto, sulla dormita o un contatto? Aggiorna qui sotto.
          Nome ed email non si toccano: se devi cambiarli scrivi a
          <a href="mailto:<?= mh((string)($edition['contact_email'] ?? SMTP_FROM)) ?>" style="color:var(--ink);"><?= mh((string)($edition['contact_email'] ?? SMTP_FROM)) ?></a>.
        </p>
      <?php else: ?>
        <h1 class="h-1" style="font-size:clamp(40px,6vw,72px);">Link non valido</h1>
        <p style="max-width:54ch;margin-top:18px;font-size:18px;">
          Il link che hai usato non corrisponde a nessuna iscrizione. Controlla l'indirizzo o
          scrivi al contatto che trovi nella mail di conferma.
        </p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($iscrizione && $edition): ?>

  <section style="padding-top:40px;padding-bottom:80px;">
    <div class="wrap">
      <form class="form-card" method="post" action="modifica.php">
        <input type="hidden" name="t" value="<?= mh($token) ?>">

        <?php if ($ok): ?>
          <div class="alert success">Salvato. Le tue scelte sono aggiornate.</div>
        <?php endif; ?>
        <?php if ($errors): ?>
          <div class="alert error"><?= mh(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <h2 class="h-2" style="margin-bottom:18px;">I tuoi dati</h2>

        <div class="lock-row">
          <span class="lbl">Nome</span>
          <span class="val"><?= mh((string)$iscrizione['name']) ?></span>
        </div>
        <div class="lock-row">
          <span class="lbl">Email</span>
          <span class="val"><?= mh((string)$iscrizione['email']) ?></span>
        </div>

        <div class="form-row">
          <label class="lbl" for="phone">Telefono</label>
          <input type="tel" id="phone" name="phone" value="<?= mh((string)($_POST['phone'] ?? '')) ?>" maxlength="40" placeholder="opzionale, per contattarti se serve">
        </div>

        <?php $curAge = (string)($_POST['age'] ?? 'adult'); ?>
        <div class="form-row">
          <label class="lbl" for="age">Età indicativa</label>
          <select id="age" name="age">
            <option value="adult"<?= $curAge === 'adult' ? ' selected' : '' ?>>adulto</option>
            <option value="minor"<?= $curAge === 'minor' ? ' selected' : '' ?>>minorenne accompagnato</option>
          </select>
        </div>

        <hr class="divider">

        <h2 class="h-2" style="margin-bottom:14px;">Dove dormi</h2>
        <div class="form-row">
          <div class="radio-cards">
            <?php
              $currentSleep = (string)($_POST['sleep_kind'] ?? $iscrizione['sleep_kind']);
              foreach ($sleepOpts as $o):
                $kind    = (string)$o['kind'];
                $checked = ($kind === $currentSleep);
                // Esaurito = non selezionabile, tranne se è già l'opzione scelta
                // (la si può mantenere, non si perde la prenotazione).
                $soldOut = empty($o['is_available']) && !$checked;
            ?>
              <label class="<?= $soldOut ? 'sold-out' : '' ?>">
                <input type="radio" name="sleep_kind" value="<?= mh($kind) ?>" <?= $checked ? 'checked' : '' ?> <?= $soldOut ? 'disabled' : '' ?>>
                <div class="rc-text">
                  <strong><?= mh((string)$o['title']) ?></strong>
                  <span><?= mh((string)$o['body']) ?></span>
                </div>
                <?= $soldOut ? '<span class="rc-price sold">Esaurito</span>' : '' ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <hr class="divider">

        <h2 class="h-2" style="margin-bottom:14px;">Pasti</h2>
        <p class="dim" style="margin-bottom:18px;">Spunta i pasti a cui pensi di esserci. Servono solo per la cucina; eventuali prezzi si saldano sul posto.</p>

        <div class="form-row">
          <?php if (empty($mealSlots)): ?>
            <p class="dim" style="font-size:14px;">Nessun pasto configurato per ora.</p>
          <?php else: ?>
            <?php
              // Ricava le selezioni correnti dal POST se in errore, altrimenti dalle attuali
              $postedMeals = isset($_POST['meals']) && is_array($_POST['meals'])
                ? array_map('strval', $_POST['meals'])
                : null;
              $mealCodeChecked = function(array $m) use ($postedMeals, $selectedMealIds): bool {
                  if ($postedMeals !== null) return in_array((string)$m['code'], $postedMeals, true);
                  return isset($selectedMealIds[(int)$m['id']]);
              };
            ?>
            <div class="meal-grid">
              <?php foreach ($mealsByDay as $day => $items): ?>
                <div class="meal-day">
                  <div class="meal-day-head"><?= mh(mday_label((string)$day)) ?></div>
                  <div class="meal-day-items">
                    <?php foreach ($items as $m): ?>
                      <label>
                        <input type="checkbox" name="meals[]" value="<?= mh((string)$m['code']) ?>" <?= $mealCodeChecked($m) ? 'checked' : '' ?>>
                        <span><?= mh((string)$m['label']) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <hr class="divider">

        <div class="form-row">
          <label class="lbl" for="diet">Note</label>
          <input type="text" id="diet" name="diet" value="<?= mh((string)($_POST['diet'] ?? '')) ?>" maxlength="255" placeholder="">
        </div>

        <div class="form-row">
          <label class="lbl" for="notes">Richieste Aggiuntive</label>
          <textarea id="notes" name="notes" maxlength="1000"><?= mh((string)($_POST['notes'] ?? '')) ?></textarea>
        </div>

        <div class="submit-row">
          <button type="submit" class="btn accent">Salva modifiche <span class="arr">→</span></button>
          <span class="dim mono" style="font-size:12px;">le modifiche sono immediate</span>
        </div>
      </form>
    </div>
  </section>

  <?php endif; ?>

  <div data-slot="footer"></div>

  <script src="api/edition.js.php"></script>
  <script src="scripts/partials.js"></script>
  <script src="scripts/runtime.js"></script>
  <script>
    // Topbar / footer presi dal pattern partials.
    if (window.TAB_mountPartials) window.TAB_mountPartials('iscrizione');
  </script>
</body>
</html>
