<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/edition.php';
require_once __DIR__ . '/../inc/mailer.php';
require_once __DIR__ . '/../inc/response.php';

// =====================================================================
// Endpoint pubblico: POST /api/iscrizione.php
//
// Accetta sia application/json che application/x-www-form-urlencoded.
// Body atteso:
//   { name, email, phone?, sleep_kind, meals?: [code], diet?, notes?, _hp? }
//
// Risposta JSON:
//   200 { ok: true, id, total_eur, edit_token }
//   422 { ok: false, errors: [...], code: "validation_failed" }
//   403 { ok: false, code: "registrations_closed" }
//   405 { ok: false, code: "method_not_allowed" }
// =====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    header('Allow: POST, OPTIONS');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'code' => 'method_not_allowed'], 405);
}

// ---- Body parsing (json o form) ----
$ctype = strtolower($_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '');
$body = [];
if (str_starts_with($ctype, 'application/json')) {
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    if (is_array($j)) $body = $j;
} else {
    $body = $_POST;
}
$get = fn(string $k, $default = '') => isset($body[$k]) ? $body[$k] : $default;

// ---- Honeypot: silently drop ----
$honeypot = trim((string)$get('_hp', ''));
if ($honeypot !== '') {
    json_response(['ok' => true, 'id' => 0, 'total_eur' => 0]);
}

// ---- Edizione corrente + iscrizioni aperte? ----
$ed = edition_current();
if (!$ed) {
    json_response(['ok' => false, 'code' => 'no_current_edition'], 503);
}
if (empty($ed['registrations_open'])) {
    json_response(['ok' => false, 'code' => 'registrations_closed'], 403);
}
$edId = (int)$ed['id'];

// ---- Validazione ----
$name      = trim((string)$get('name'));
$email     = trim((string)$get('email'));
$phone     = trim((string)$get('phone'));
$age       = trim((string)$get('age'));
$sleepKind = trim((string)$get('sleep_kind'));
$diet      = trim((string)$get('diet'));
$notes     = trim((string)$get('notes'));

$mealsRaw = $get('meals', []);
if (!is_array($mealsRaw)) $mealsRaw = [];
$meals = array_values(array_unique(array_filter(
    array_map(fn($m) => trim((string)$m), $mealsRaw),
    fn($s) => $s !== ''
)));

$privacyRaw = $get('privacy_accepted', false);
$privacyAccepted = filter_var($privacyRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;

// Consenso art. 9 (salute/dieta). Rilevante solo se l'iscritto ha compilato il
// campo allergie/dieta: in quel caso è obbligatorio. Se il campo è vuoto, nessun
// dato di salute viene trattato e il consenso è irrilevante.
$healthConsentRaw = $get('health_consent', false);
$healthConsent = filter_var($healthConsentRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
$hasDiet = $diet !== '';

$errors = [];
if ($name === '' || mb_strlen($name) > 160)             $errors[] = 'Nome obbligatorio.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) $errors[] = 'Email non valida.';
if (mb_strlen($phone) > 40)                             $errors[] = 'Telefono troppo lungo.';
if (!in_array($age, ['adult', 'minor'], true))          $errors[] = 'Età indicativa non valida.';
if (mb_strlen($diet) > 255)                             $errors[] = 'Dieta troppo lunga (max 255).';
if (mb_strlen($notes) > 1000)                           $errors[] = 'Note troppo lunghe (max 1000).';
if (count($meals) > 50)                                 $errors[] = 'Troppi pasti selezionati.';
if (!$privacyAccepted)                                  $errors[] = 'È necessario dichiarare di aver preso visione dell\'informativa privacy.';
if ($hasDiet && !$healthConsent)                        $errors[] = 'Per trattare allergie/regime alimentare è necessario il consenso dedicato.';

// Verifica sleep_kind. is_available = selezionabile: solo le opzioni con
// is_available = 1 sono prenotabili. Le altre esistono ma sono "esaurite".
$stmt = db()->prepare(
    'SELECT kind, price_eur, is_available FROM sleep_options
      WHERE edition_id = ?'
);
$stmt->execute([$edId]);
$availSleep = [];
$soldOutKinds = [];
foreach ($stmt->fetchAll() as $r) {
    if (!empty($r['is_available'])) {
        $availSleep[(string)$r['kind']] = (int)$r['price_eur'];
    } else {
        $soldOutKinds[(string)$r['kind']] = true;
    }
}
if (!isset($availSleep[$sleepKind])) {
    $errors[] = isset($soldOutKinds[$sleepKind])
        ? 'L\'opzione di pernottamento scelta è esaurita.'
        : 'Opzione di pernottamento non valida.';
}

// Verifica meal codes contro meal_slots dell'edizione
$mealIdsByCode = [];
if ($meals) {
    $stmt = db()->prepare(
        'SELECT id, code FROM meal_slots
          WHERE edition_id = ? AND is_available = 1'
    );
    $stmt->execute([$edId]);
    foreach ($stmt->fetchAll() as $r) {
        $mealIdsByCode[(string)$r['code']] = (int)$r['id'];
    }
    foreach ($meals as $code) {
        if (!isset($mealIdsByCode[$code])) {
            $errors[] = 'Pasto non valido: ' . htmlspecialchars($code, ENT_QUOTES);
            break;
        }
    }
}

if ($errors) {
    json_response(['ok' => false, 'code' => 'validation_failed', 'errors' => $errors], 422);
}

// ---- Calcolo prezzi (canonical lato server) ----
// I pasti sono solo headcount, non concorrono al totale.
$ticketEur = (int)$ed['ticket_price_eur'];
$sleepEur  = $availSleep[$sleepKind];
$totalEur  = $ticketEur + $sleepEur;

// ---- Token modifica (32 hex). UNIQUE in DB → ritento in caso di collisione (improbabile). ----
$editToken = bin2hex(random_bytes(16));

// ---- Consenso art. 9: valorizzato SOLO se ci sono dati di salute/dieta.
// Si salva il testo canonico lato server (non quello inviato dal client) per
// l'onere della prova ex art. 7.1: è esattamente ciò che la pagina mostra. ----
$healthConsentAt   = $hasDiet ? date('Y-m-d H:i:s') : null;
$healthConsentText = $hasDiet ? HEALTH_CONSENT_TEXT : null;
$privacyVersion    = PRIVACY_VERSION;

// ---- Insert in transazione: iscrizioni + iscrizione_meals ----
try {
    $newId = db_tx(function (PDO $pdo) use (
        $edId, $name, $email, $phone, $age, $sleepKind, $diet, $notes,
        $ticketEur, $sleepEur, $totalEur, $editToken, $meals, $mealIdsByCode
        $privacyVersion, $healthConsentAt, $healthConsentText
    ): int {
        $pdo->prepare(
            'INSERT INTO iscrizioni
              (edition_id, name, email, phone, age, sleep_kind, n_cards,
               ticket_eur, sleep_eur, cards_eur, total_eur, diet, notes, edit_token,
               ip, user_agent, privacy_consent_at, privacy_version,
               health_consent_at, health_consent_text)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, 0, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)'
        )->execute([
            $edId, $name, $email, $phone !== '' ? $phone : null,
            $age,
            $sleepKind,
            $ticketEur, $sleepEur, $totalEur,
            $diet  !== '' ? $diet  : null,
            $notes !== '' ? $notes : null,
            $editToken,
            client_ip(),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $privacyVersion,
            $healthConsentAt,
            $healthConsentText,
        ]);
        $id = (int)$pdo->lastInsertId();

        if ($meals) {
            $insMeal = $pdo->prepare(
                'INSERT INTO iscrizione_meals (iscrizione_id, meal_slot_id) VALUES (?, ?)'
            );
            foreach ($meals as $code) {
                $insMeal->execute([$id, $mealIdsByCode[$code]]);
            }
        }

        return $id;
    });
} catch (\PDOException $e) {
    error_log('iscrizione insert failed: ' . $e->getMessage());
    json_response(['ok' => false, 'code' => 'db_error'], 500);
}

// ---- Mail di conferma (best effort, non blocca la risposta) ----
$mealLabels = [];
if ($meals) {
    $in  = implode(',', array_fill(0, count($meals), '?'));
    $stmt = db()->prepare(
        "SELECT label FROM meal_slots
          WHERE edition_id = ? AND code IN ($in)
          ORDER BY sort, id"
    );
    $stmt->execute(array_merge([$edId], $meals));
    foreach ($stmt->fetchAll() as $r) {
        $mealLabels[] = (string)$r['label'];
    }
}

$iscrizione = [
    'id'         => $newId,
    'name'       => $name,
    'email'      => $email,
    'sleep_kind' => $sleepKind,
    'total_eur'  => $totalEur,
    'edit_token' => $editToken,
    'meals'      => $mealLabels,
];
@mailer_send_iscrizione_confirm($iscrizione, $ed);

// ---- Response ----
json_response([
    'ok'         => true,
    'id'         => $newId,
    'total_eur'  => $totalEur,
    'edit_token' => $editToken,
]);
