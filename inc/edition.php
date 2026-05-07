<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

// =====================================================================
// Risoluzione edizioni
//
// Differenza importante:
//   - edition_current()  → l'edizione marcata is_current=1 nel DB.
//                          È quella servita al pubblico (frontend).
//   - edition_active()   → l'edizione che l'admin loggato sta editando.
//                          Sceglibile dal dropdown nel topbar admin,
//                          memorizzata in $_SESSION['_admin_edition_id'].
//                          Default = current.
// =====================================================================

function edition_get(int $id): ?array
{
    if ($id <= 0) return null;
    $stmt = db()->prepare('SELECT * FROM editions WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function edition_current(): ?array
{
    $row = db()->query('SELECT * FROM editions WHERE is_current = 1 LIMIT 1')->fetch();
    return $row ?: null;
}

function edition_latest(): ?array
{
    $row = db()->query('SELECT * FROM editions ORDER BY year DESC LIMIT 1')->fetch();
    return $row ?: null;
}

function edition_all(): array
{
    return db()->query('SELECT * FROM editions ORDER BY year DESC')->fetchAll();
}

function edition_active(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Nessuna sessione: comportamento da resolver pubblico.
        return edition_current() ?? edition_latest();
    }
    $id = (int)($_SESSION['_admin_edition_id'] ?? 0);
    if ($id > 0) {
        $ed = edition_get($id);
        if ($ed) return $ed;
        // l'edizione in sessione è stata cancellata: ricadi sul current
        unset($_SESSION['_admin_edition_id']);
    }
    $ed = edition_current() ?? edition_latest();
    if ($ed) {
        $_SESSION['_admin_edition_id'] = (int)$ed['id'];
    }
    return $ed;
}

function edition_set_active(int $id): bool
{
    $ed = edition_get($id);
    if (!$ed) return false;
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    $_SESSION['_admin_edition_id'] = (int)$ed['id'];
    return true;
}

/**
 * Marca un'edizione come current (esclusiva: tutte le altre vengono spente).
 * Wrapping in transazione perché è un invariante di dato.
 */
function edition_make_current(int $id): bool
{
    $ed = edition_get($id);
    if (!$ed) return false;
    db_tx(function (PDO $pdo) use ($id) {
        $pdo->exec('UPDATE editions SET is_current = 0 WHERE is_current = 1');
        $pdo->prepare('UPDATE editions SET is_current = 1 WHERE id = ?')->execute([$id]);
    });
    return true;
}
