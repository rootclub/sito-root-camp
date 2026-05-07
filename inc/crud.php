<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Whitelist di tabelle gestite da queste utility (anti SQL-injection sul nome).
const CRUD_TABLES = [
    'organizers',
    'rules',
    'food_items',
    'sleep_options',
    'meal_slots',
    'schedule_items',
    'schedule_tracks',
];

function crud_assert_table(string $table): void
{
    if (!in_array($table, CRUD_TABLES, true)) {
        throw new InvalidArgumentException("Tabella CRUD non consentita: $table");
    }
}

/**
 * Restituisce il prossimo valore di sort (max + 10) per una tabella scope-edizione.
 */
function crud_next_sort(string $table, int $editionId): int
{
    crud_assert_table($table);
    $stmt = db()->prepare("SELECT COALESCE(MAX(sort), 0) FROM `$table` WHERE edition_id = ?");
    $stmt->execute([$editionId]);
    return ((int)$stmt->fetchColumn()) + 10;
}

/**
 * Sposta un record in alto o in basso scambiando il campo `sort` col vicino.
 * Direzione: 'up' (verso valori più piccoli) oppure 'down'.
 */
function crud_move(string $table, int $id, int $editionId, string $direction): void
{
    crud_assert_table($table);
    if (!in_array($direction, ['up', 'down'], true)) return;

    db_tx(function (PDO $pdo) use ($table, $id, $editionId, $direction) {
        $cur = $pdo->prepare("SELECT id, sort FROM `$table` WHERE id = ? AND edition_id = ?");
        $cur->execute([$id, $editionId]);
        $row = $cur->fetch();
        if (!$row) return;

        if ($direction === 'up') {
            $sql = "SELECT id, sort FROM `$table`
                    WHERE edition_id = ? AND (sort < ? OR (sort = ? AND id < ?))
                    ORDER BY sort DESC, id DESC LIMIT 1";
        } else {
            $sql = "SELECT id, sort FROM `$table`
                    WHERE edition_id = ? AND (sort > ? OR (sort = ? AND id > ?))
                    ORDER BY sort ASC, id ASC LIMIT 1";
        }
        $nb = $pdo->prepare($sql);
        $nb->execute([$editionId, (int)$row['sort'], (int)$row['sort'], (int)$row['id']]);
        $neighbor = $nb->fetch();
        if (!$neighbor) return;

        // Swap dei sort. Se erano uguali, dopo lo scambio sono ancora uguali:
        // in quel caso forziamo neighbor.sort = row.sort - 1 (o + 1) per ottenere un ordine stabile.
        $a = (int)$row['sort'];
        $b = (int)$neighbor['sort'];
        if ($a === $b) {
            $b = $direction === 'up' ? $a - 1 : $a + 1;
        }
        $upd = $pdo->prepare("UPDATE `$table` SET sort = ? WHERE id = ?");
        $upd->execute([$b, (int)$row['id']]);
        $upd->execute([$a, (int)$neighbor['id']]);
    });
}

/**
 * Cancella un record verificando che appartenga all'edizione.
 */
function crud_delete(string $table, int $id, int $editionId): bool
{
    crud_assert_table($table);
    $stmt = db()->prepare("DELETE FROM `$table` WHERE id = ? AND edition_id = ?");
    $stmt->execute([$id, $editionId]);
    return $stmt->rowCount() > 0;
}

/**
 * Fetch di un record verificando edition_id (per evitare cross-edition tampering via ?id).
 */
function crud_get(string $table, int $id, int $editionId): ?array
{
    crud_assert_table($table);
    $stmt = db()->prepare("SELECT * FROM `$table` WHERE id = ? AND edition_id = ? LIMIT 1");
    $stmt->execute([$id, $editionId]);
    $r = $stmt->fetch();
    return $r ?: null;
}
