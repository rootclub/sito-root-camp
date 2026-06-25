<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/edition.php';
require_once __DIR__ . '/../inc/response.php';

// =====================================================================
// Endpoint pubblico: serve TUTTE le edizioni pubblicate come JS,
// nello stesso formato del vecchio data/edizione-2026.js.
//
// Output:
//   window.TAB_EDITION_<year> = {...};      (per ogni edizione)
//   window.TAB_EDITIONS       = [...];
//   window.TAB_CURRENT_EDITION = <riferimento a quella is_current=1>;
//
// Cache: 60 secondi (cambi admin propagati entro 1 minuto).
// =====================================================================

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=60');

/**
 * Costruisce il payload (nested) di una singola edizione.
 */
function build_edition_payload(int $edId): ?array
{
    $stmt = db()->prepare('SELECT * FROM editions WHERE id = ? LIMIT 1');
    $stmt->execute([$edId]);
    $ed = $stmt->fetch();
    if (!$ed) return null;

    // Marquee words: TEXT con una frase per riga. Colonna potenzialmente assente
    // se la migration non è stata applicata; in tal caso fallback a [].
    $marqueeWords = [];
    if (array_key_exists('marquee_words', $ed) && $ed['marquee_words'] !== null && $ed['marquee_words'] !== '') {
        $marqueeWords = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', (string)$ed['marquee_words'])),
            fn($s) => $s !== ''
        ));
    }

    // Tracks: id => position + lista per nome.
    $stmt = db()->prepare('SELECT id, position, name FROM schedule_tracks WHERE edition_id = ? ORDER BY position, id');
    $stmt->execute([$edId]);
    $tracks = $stmt->fetchAll();

    $trackPosById  = [];
    $trackNameById = [];
    $trackNamesByPos = [];
    foreach ($tracks as $t) {
        $trackPosById[(int)$t['id']]          = (int)$t['position'];
        $trackNameById[(int)$t['id']]         = (string)$t['name'];
        $trackNamesByPos[(int)$t['position']] = (string)$t['name'];
    }
    ksort($trackNamesByPos);
    $trackNames = array_values($trackNamesByPos);

    // Settings dei giorni: visibilità in home preview.
    // Riga assente => default true (visibile).
    // Tabella opzionale: se non esiste ancora (pre-migration), trattiamo tutti come visibili.
    $dayShowInHome = [];
    try {
        $stmt = db()->prepare(
            'SELECT day_date, show_in_home_preview FROM schedule_day_settings WHERE edition_id = ?'
        );
        $stmt->execute([$edId]);
        foreach ($stmt->fetchAll() as $r) {
            $dayShowInHome[(string)$r['day_date']] = (int)$r['show_in_home_preview'] === 1;
        }
    } catch (\PDOException $e) {
        // Tabella non ancora creata: ignora, default sarà true.
    }

    // Schedule items raggruppati per giorno
    $stmt = db()->prepare(
        'SELECT day_date, day_label, start_time, duration_min, track_id, kind, title, speaker, description
           FROM schedule_items WHERE edition_id = ?
          ORDER BY day_date, start_time, sort, id'
    );
    $stmt->execute([$edId]);
    $itemsRaw = $stmt->fetchAll();

    $days = [];
    foreach ($itemsRaw as $r) {
        $date = (string)$r['day_date'];
        if (!isset($days[$date])) {
            $days[$date] = [
                'date'              => $date,
                'label'             => (string)$r['day_label'],
                'tracks'            => $trackNames,
                'showInHomePreview' => $dayShowInHome[$date] ?? true,
                'items'             => [],
            ];
        }
        $days[$date]['items'][] = [
            'time'        => substr((string)$r['start_time'], 0, 5),
            'duration'    => (int)$r['duration_min'],
            'track'       => $trackPosById[(int)$r['track_id']]  ?? 0,
            'trackName'   => $trackNameById[(int)$r['track_id']] ?? '',
            'kind'        => (string)$r['kind'],
            'title'       => (string)$r['title'],
            'speaker'     => $r['speaker']     !== null ? (string)$r['speaker']     : null,
            'description' => $r['description'] !== null ? (string)$r['description'] : null,
        ];
    }

    // Organizers
    $stmt = db()->prepare('SELECT name, role, photo_url, is_placeholder, link_url FROM organizers WHERE edition_id = ? ORDER BY sort, id');
    $stmt->execute([$edId]);
    $organizers = array_map(function ($o) {
        $out = ['name' => (string)$o['name'], 'role' => (string)($o['role'] ?? '')];
        if (!empty($o['photo_url'])) $out['photo'] = (string)$o['photo_url'];
        if (!empty($o['is_placeholder'])) $out['placeholder'] = true;
        if (!empty($o['link_url'])) $out['link'] = (string)$o['link_url'];
        return $out;
    }, $stmt->fetchAll());

    // Sponsors
    $stmt = db()->prepare('SELECT name, logo_url, link_url FROM sponsors WHERE edition_id = ? ORDER BY sort, id');
    $stmt->execute([$edId]);
    $sponsors = array_map(function ($s) {
        $out = ['name' => (string)$s['name']];
        if (!empty($s['logo_url'])) $out['logo'] = (string)$s['logo_url'];
        if (!empty($s['link_url'])) $out['link'] = (string)$s['link_url'];
        return $out;
    }, $stmt->fetchAll());

    // Rules
    $stmt = db()->prepare('SELECT icon, title, body FROM rules WHERE edition_id = ? ORDER BY sort, id');
    $stmt->execute([$edId]);
    $rules = array_map(fn($r) => [
        'icon'  => (string)$r['icon'],
        'title' => (string)$r['title'],
        'body'  => (string)$r['body'],
    ], $stmt->fetchAll());

    // Food
    $stmt = db()->prepare('SELECT label, note FROM food_items WHERE edition_id = ? ORDER BY sort, id');
    $stmt->execute([$edId]);
    $foodItems = array_map(fn($f) => [
        'label' => (string)$f['label'],
        'note'  => (string)($f['note'] ?? ''),
    ], $stmt->fetchAll());

    // Sleep options: le mostriamo TUTTE. is_available qui indica la
    // selezionabilità: se 0 l'opzione appare ma come "Esaurito" (non prenotabile).
    $stmt = db()->prepare(
        'SELECT kind, title, body, price_eur, is_available FROM sleep_options
          WHERE edition_id = ? ORDER BY sort, id'
    );
    $stmt->execute([$edId]);
    $sleepOpts = array_map(fn($s) => [
        'kind'  => (string)$s['kind'],
        'title' => (string)$s['title'],
        'body'  => (string)$s['body'],
        'price_eur' => (int)$s['price_eur'],
        'available' => !empty($s['is_available']),
    ], $stmt->fetchAll());

    // Meal slots (solo quelli disponibili). Tabella opzionale: se la migration
    // non è stata applicata, restituiamo array vuoto.
    $mealSlots = [];
    try {
        $stmt = db()->prepare(
            'SELECT code, label, day_date FROM meal_slots
              WHERE edition_id = ? AND is_available = 1 ORDER BY sort, id'
        );
        $stmt->execute([$edId]);
        $mealSlots = array_map(fn($m) => [
            'code'     => (string)$m['code'],
            'label'    => (string)$m['label'],
            'day_date' => (string)($m['day_date'] ?? ''),
        ], $stmt->fetchAll());
    } catch (\PDOException $e) {
        // tabella non esiste ancora
    }

    return [
        'year'     => (int)$ed['year'],
        'slug'     => (string)$ed['slug'],
        'name'     => (string)$ed['name'],
        'subtitle' => (string)($ed['subtitle'] ?? ''),
        'isCurrent'         => (bool)$ed['is_current'],
        'isPublished'       => (bool)$ed['is_published'],
        'registrationsOpen' => (bool)$ed['registrations_open'],
        'dates' => [
            'start'      => (string)$ed['date_start'],
            'end'        => (string)$ed['date_end'],
            'setup'      => (string)($ed['date_setup'] ?? ''),
            'teardown'   => (string)($ed['date_teardown'] ?? ''),
            'label'      => (string)$ed['date_label'],
            'labelShort' => (string)($ed['date_label_short'] ?? ''),
        ],
        'location' => [
            'name'      => (string)$ed['loc_name'],
            'city'      => (string)($ed['loc_city'] ?? ''),
            'region'    => (string)($ed['loc_region'] ?? ''),
            'venueNote' => (string)($ed['loc_venue_note'] ?? ''),
        ],
        'tickets' => [
            'price'     => (string)($ed['ticket_label'] ?? ($ed['ticket_price_eur'] . ' €')),
            'priceEur'  => (int)$ed['ticket_price_eur'],
            'cardEur'   => (int)$ed['card_price_eur'],
            'note'      => (string)($ed['ticket_note'] ?? ''),
            'cardsNote' => (string)($ed['ticket_cards_note'] ?? ''),
        ],
        'contacts' => [
            'email'    => (string)($ed['contact_email'] ?? ''),
            'matrix'   => (string)($ed['contact_matrix'] ?? ''),
            'telegram' => (string)($ed['contact_telegram'] ?? ''),
            'mastodon' => (string)($ed['contact_mastodon'] ?? ''),
        ],
        'hero' => [
            'video'  => (string)($ed['hero_video_url'] ?? ''),
            'poster' => (string)($ed['hero_poster_url'] ?? ''),
        ],
        'marqueeWords' => $marqueeWords,
        'organizers' => $organizers,
        'sponsors'   => $sponsors,
        'rules'      => $rules,
        'food' => [
            'intro' => (string)($ed['food_intro'] ?? ''),
            'items' => $foodItems,
        ],
        'sleep' => [
            'intro'   => (string)($ed['sleep_intro'] ?? ''),
            'options' => $sleepOpts,
        ],
        'meals' => [
            'slots' => $mealSlots,
        ],
        'schedule' => [
            'days' => array_values($days),
        ],
    ];
}

// =====================================================================
// Build payload per tutte le edizioni pubblicate (+ la corrente, se non lo è)
// =====================================================================
$pub = db()->query(
    'SELECT id, year, is_current FROM editions WHERE is_published = 1 OR is_current = 1 ORDER BY year DESC'
)->fetchAll();

$jsParts = [];
$varNames = [];
$currentVar = null;

foreach ($pub as $row) {
    $payload = build_edition_payload((int)$row['id']);
    if (!$payload) continue;
    $varName = 'TAB_EDITION_' . (int)$row['year'];
    $jsParts[] = sprintf(
        "window.%s = %s;",
        $varName,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    $varNames[] = $varName;
    if (!empty($row['is_current'])) {
        $currentVar = $varName;
    }
}

if (empty($varNames)) {
    echo "// Nessuna edizione pubblicata.\nwindow.TAB_EDITIONS = [];\nwindow.TAB_CURRENT_EDITION = null;\n";
    exit;
}

if ($currentVar === null) {
    // Fallback: prendi la più recente
    $currentVar = $varNames[0];
}

echo "/* /RooT-Camp — generato da api/edition.js.php */\n";
echo implode("\n", $jsParts) . "\n";
echo "window.TAB_EDITIONS = [" . implode(', ', array_map(fn($n) => "window.$n", $varNames)) . "];\n";
echo "window.TAB_CURRENT_EDITION = window.$currentVar;\n";
