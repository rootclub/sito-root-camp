<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND =>
                "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci, "
                . "sql_mode='STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION', "
                . "time_zone='+00:00'",
        ]);
    } catch (PDOException $e) {
        if (APP_DEBUG) {
            throw $e;
        }
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Database non disponibile.');
    }
    return $pdo;
}

function db_tx(callable $fn)
{
    $pdo = db();
    $owns = !$pdo->inTransaction();
    if ($owns) $pdo->beginTransaction();
    try {
        $r = $fn($pdo);
        if ($owns) $pdo->commit();
        return $r;
    } catch (\Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
