<?php
declare(strict_types=1);

// =====================================================================
// Output <script type="application/ld+json"> con il record schema.org
// dell'edizione corrente: Event (Hackathon + Festival) con location
// Campground (PostalAddress + GeoCoordinates), organizer, offers e
// l'elenco completo dei subEvent (talk/workshop = EducationEvent,
// djset/musica/cena = SocialEvent).
//
// Da includere nel <head> di ogni pagina pubblica PHP:
//   <?php require __DIR__ . '/inc/jsonld_event.php'; ?>
// (oppure path relativo se la pagina non è in document root)
// =====================================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/edition.php';

$__ed = edition_current();
if (!$__ed) return;

$__edId = (int)$__ed['id'];

// Helpers locali --------------------------------------------------------
$__pdo = db();

$__year  = (int)$__ed['year'];
$__name  = (string)$__ed['name'];
$__sub   = (string)($__ed['subtitle'] ?? '');
$__dateStart = (string)$__ed['date_start'];
$__dateEnd   = (string)$__ed['date_end'];
$__locName   = (string)$__ed['loc_name'];
$__locCity   = (string)($__ed['loc_city'] ?? '');
$__locRegion = (string)($__ed['loc_region'] ?? 'Emilia-Romagna');
$__venueNote = (string)($__ed['loc_venue_note'] ?? '');
$__regOpen   = !empty($__ed['registrations_open']);

// Base URL: HTTPS canonico in produzione, schema/host correnti in dev.
$__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$__host   = $_SERVER['HTTP_HOST'] ?? 'rootcamp.rootclub.it';
$__origin = rtrim($__scheme . '://' . $__host, '/');
$__eventUrl = $__origin . '/';
$__registrationUrl = $__origin . '/iscrizione.php';
$__image = $__origin . '/assets/logo-camp.png';

// Date in ISO 8601 con offset CEST (luglio = +02:00).
$__startIso = $__dateStart . 'T15:00:00+02:00';
$__endIso   = $__dateEnd   . 'T23:59:00+02:00';

// PostalAddress + GeoCoordinates: coordinate da info.php
// (44.137441 N, 12.101859 E — Fratta Terme, Bertinoro FC).
$__address = [
    '@type'           => 'PostalAddress',
    'streetAddress'   => 'Via Superga 190',
    'addressLocality' => 'Fratta Terme',
    'addressRegion'   => $__locRegion,
    'postalCode'      => '47032',
    'addressCountry'  => 'IT',
];
$__geo = [
    '@type'     => 'GeoCoordinates',
    'latitude'  => 44.137441,
    'longitude' => 12.101859,
];
$__place = [
    '@type'   => 'Campground',
    'name'    => $__locName ?: 'Fratta Terme',
    'address' => $__address,
    'geo'     => $__geo,
    'url'     => $__eventUrl,
];
if ($__venueNote !== '') $__place['description'] = $__venueNote;

// Organizer: associazione root APS, con eventuale link/email dai contatti.
$__organizer = [
    '@type' => 'Organization',
    'name'  => 'Associazione Root APS',
    'url'   => $__origin . '/',
];
if (!empty($__ed['contact_email'])) {
    $__organizer['email'] = (string)$__ed['contact_email'];
}

// Offer: gratuita (price 0, isAccessibleForFree) ma con url verso la
// pagina di registrazione del biglietto.
$__offer = [
    '@type'         => 'Offer',
    'name'          => 'Iscrizione /RooT-Camp ' . $__year,
    'price'         => '0',
    'priceCurrency' => 'EUR',
    'availability'  => $__regOpen
        ? 'https://schema.org/InStock'
        : 'https://schema.org/SoldOut',
    'url'           => $__registrationUrl,
    'validFrom'     => date('Y-m-d'),
];

// Sub-event: ogni voce del palinsesto.
//   talk / workshop / kids       → EducationEvent
//   music (DJ set, live) / food  → SocialEvent
//   opening / closing / other    → Event
$__kindMap = [
    'talk'     => 'EducationEvent',
    'workshop' => 'EducationEvent',
    'kids'     => 'EducationEvent',
    'music'    => 'SocialEvent',
    'food'     => 'SocialEvent',
    'opening'  => 'Event',
    'closing'  => 'Event',
    'other'    => 'Event',
];

$__stmt = $__pdo->prepare(
    'SELECT si.day_date, si.start_time, si.duration_min, si.kind,
            si.title, si.speaker, si.description, st.name AS track_name
       FROM schedule_items si
       LEFT JOIN schedule_tracks st ON st.id = si.track_id
      WHERE si.edition_id = ?
      ORDER BY si.day_date, si.start_time, si.sort, si.id'
);
$__stmt->execute([$__edId]);
$__items = $__stmt->fetchAll();

$__subEvents = [];
foreach ($__items as $__it) {
    $__day  = (string)$__it['day_date'];
    $__time = substr((string)$__it['start_time'], 0, 5);
    $__dur  = (int)$__it['duration_min'];
    $__kind = (string)$__it['kind'];
    $__type = $__kindMap[$__kind] ?? 'Event';

    $__startTs = strtotime($__day . ' ' . $__time . ':00');
    $__endTs   = $__startTs + ($__dur * 60);
    $__subStartIso = date('Y-m-d\TH:i:s', $__startTs) . '+02:00';
    $__subEndIso   = date('Y-m-d\TH:i:s', $__endTs)   . '+02:00';

    $__subLocation = $__place;
    if (!empty($__it['track_name'])) {
        $__subLocation['name'] = $__place['name'] . ' — ' . (string)$__it['track_name'];
    }

    $__sub = [
        '@type'               => $__type,
        'name'                => (string)$__it['title'],
        'startDate'           => $__subStartIso,
        'endDate'             => $__subEndIso,
        'eventStatus'         => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'isAccessibleForFree' => true,
        'location'            => $__subLocation,
        'organizer'           => $__organizer,
        'offers'              => $__offer,
    ];
    if (!empty($__it['speaker']) && !preg_match('/TBD/i', (string)$__it['speaker'])) {
        $__sub['performer'] = [
            '@type' => 'Person',
            'name'  => (string)$__it['speaker'],
        ];
    }
    if (!empty($__it['description'])) {
        $__sub['description'] = (string)$__it['description'];
    }
    $__subEvents[] = $__sub;
}

// Description sintetica.
$__description = ($__sub !== ''
    ? ($__name . ' — ' . $__sub . '. ')
    : ($__name . '. '))
    . 'Hacker camp di tre giorni a ' . ($__locName ?: 'Fratta Terme')
    . ': talk, workshop, musica, cucina di campo. Family friendly nella prima parte '
    . 'della giornata, in the wild dopo le 23.';

$__mainEvent = [
    '@context'            => 'https://schema.org',
    '@type'               => ['Hackathon', 'Festival'],
    'name'                => $__name,
    'description'         => $__description,
    'startDate'           => $__startIso,
    'endDate'             => $__endIso,
    'eventStatus'         => 'https://schema.org/EventScheduled',
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    'isAccessibleForFree' => true,
    'image'               => [$__image],
    'url'                 => $__eventUrl,
    'location'            => $__place,
    'organizer'           => $__organizer,
    'offers'              => [$__offer],
];
if (!empty($__subEvents)) {
    $__mainEvent['subEvent'] = $__subEvents;
}

echo "\n<script type=\"application/ld+json\">"
   . json_encode(
        $__mainEvent,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
     )
   . "</script>\n";
