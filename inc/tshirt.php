<?php
declare(strict_types=1);

// =====================================================================
// Taglie maglietta evento — sorgente unica condivisa.
//
// Codice (stabile, salvato a DB) => etichetta visualizzata.
// L'ordine dell'array è l'ordine di visualizzazione e di riepilogo.
// Usato da: api/edition.js.php, api/iscrizione.php, modifica.php,
//           admin/tshirt.php, admin/iscrizioni.php.
// =====================================================================
const TSHIRT_SIZES = [
    'xs'  => 'XS',
    's'   => 'S',
    'm'   => 'M',
    'l'   => 'L',
    'xl'  => 'XL',
    '2xl' => '2XL',
    '3xl' => '3XL',
    '4xl' => '4XL',
];

/** True se $code è una taglia valida (non vuota). */
function tshirt_size_valid(string $code): bool
{
    return $code !== '' && array_key_exists($code, TSHIRT_SIZES);
}

/** Etichetta per un codice taglia; '' se assente/non valido. */
function tshirt_size_label(?string $code): string
{
    $code = (string)$code;
    return TSHIRT_SIZES[$code] ?? '';
}
