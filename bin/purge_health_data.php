<?php
declare(strict_types=1);

// =====================================================================
// bin/purge_health_data.php — cancellazione dei dati di salute/dieta.
//
// L'informativa privacy (privacy.php, sezione art. 9) dichiara che i dati
// relativi ad allergie e regime alimentare hanno un termine di conservazione
// breve e separato: cancellazione ALLA CONCLUSIONE DELL'EVENTO. Questo script
// rende effettiva quella promessa azzerando, sulle iscrizioni interessate:
//   - diet                (il dato art. 9 vero e proprio)
//   - health_consent_at   (timestamp del consenso)
//   - health_consent_text (testo del consenso)
//
// Le altre informazioni dell'iscrizione (nome, email, pasti, ecc.) restano:
// la loro conservazione è di 12 mesi ed è retta da basi giuridiche diverse.
//
// USO (solo CLI — l'.htaccess di bin/ nega ogni accesso HTTP):
//   php bin/purge_health_data.php                 # tutte le edizioni concluse (date_end < oggi)
//   php bin/purge_health_data.php --edition=3     # una specifica edizione, a prescindere dalla data
//   php bin/purge_health_data.php --days=7        # solo edizioni concluse da più di 7 giorni
//   php bin/purge_health_data.php --dry-run       # mostra cosa farebbe, senza scrivere
//
// Pensato per essere lanciato a mano dopo l'evento, oppure da un cron giornaliero.
// =====================================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Questo script è eseguibile solo da CLI.\n");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/response.php';
require_once __DIR__ . '/../inc/auth.php';

// ---- Parsing argomenti ----
$opts = getopt('', ['edition::', 'days::', 'dry-run', 'help']);
if (isset($opts['help'])) {
    fwrite(STDOUT,
        "Cancella i dati di salute/dieta (art. 9) dalle iscrizioni di edizioni concluse.\n\n" .
        "Opzioni:\n" .
        "  --edition=<id>  Limita a una specifica edizione (ignora il filtro sulla data).\n" .
        "  --days=<n>      Considera concluse solo le edizioni terminate da più di <n> giorni.\n" .
        "  --dry-run       Mostra il conteggio senza modificare nulla.\n" .
        "  --help          Questo messaggio.\n"
    );
    exit(0);
}

$editionId = isset($opts['edition']) && $opts['edition'] !== '' ? (int)$opts['edition'] : 0;
$graceDays = isset($opts['days']) && $opts['days'] !== '' ? max(0, (int)$opts['days']) : 0;
$dryRun    = isset($opts['dry-run']);

$pdo = db();

// ---- Selezione delle edizioni bersaglio ----
// Una riga è "da ripulire" se appartiene a un'edizione bersaglio e ha ancora
// almeno uno dei tre campi valorizzato.
$where = [];
$args  = [];

if ($editionId > 0) {
    $where[] = 'i.edition_id = ?';
    $args[]  = $editionId;
} else {
    // Edizioni concluse: date_end anteriore a (oggi - graceDays).
    // $graceDays è un intero già validato: inlinabile in sicurezza (i prepared
    // statement nativi non accettano un placeholder dentro INTERVAL ? DAY).
    $where[] = "e.date_end < (CURDATE() - INTERVAL $graceDays DAY)";
}

$where[] = '(i.diet IS NOT NULL OR i.health_consent_at IS NOT NULL OR i.health_consent_text IS NOT NULL)';
$whereSql = implode(' AND ', $where);

// ---- Anteprima: quante righe e su quali edizioni ----
$preview = $pdo->prepare(
    "SELECT e.id, e.year, e.name, e.date_end, COUNT(*) AS n
       FROM iscrizioni i
       JOIN editions e ON e.id = i.edition_id
      WHERE $whereSql
   GROUP BY e.id, e.year, e.name, e.date_end
   ORDER BY e.date_end"
);
$preview->execute($args);
$groups = $preview->fetchAll();

$total = 0;
foreach ($groups as $g) {
    $total += (int)$g['n'];
}

if ($total === 0) {
    fwrite(STDOUT, "Nessun dato di salute/dieta da cancellare con i criteri indicati.\n");
    exit(0);
}

fwrite(STDOUT, sprintf("Iscrizioni con dati art. 9 da cancellare: %d\n", $total));
foreach ($groups as $g) {
    fwrite(STDOUT, sprintf(
        "  · edizione %s «%s» (id %d, conclusa il %s): %d iscrizioni\n",
        $g['year'], $g['name'], (int)$g['id'], $g['date_end'], (int)$g['n']
    ));
}

if ($dryRun) {
    fwrite(STDOUT, "\n[dry-run] Nessuna modifica effettuata.\n");
    exit(0);
}

// ---- Cancellazione effettiva ----
// Stessa clausola WHERE dell'anteprima, applicata via UPDATE multi-tabella con
// JOIN su editions per riusare il filtro sulla data di fine evento.
$update = $pdo->prepare(
    "UPDATE iscrizioni i
       JOIN editions e ON e.id = i.edition_id
        SET i.diet = NULL,
            i.health_consent_at = NULL,
            i.health_consent_text = NULL
      WHERE $whereSql"
);
$update->execute($args);
$affected = $update->rowCount();

fwrite(STDOUT, sprintf("\nFatto. Righe aggiornate: %d.\n", $affected));

// ---- Audit ----
audit_log('purge_health_data', [
    'entity'   => 'iscrizioni',
    'username' => 'cli',
    'payload'  => [
        'edition_id' => $editionId ?: null,
        'grace_days' => $graceDays,
        'affected'   => $affected,
        'editions'   => array_map(static fn($g) => (int)$g['id'], $groups),
    ],
]);

exit(0);
