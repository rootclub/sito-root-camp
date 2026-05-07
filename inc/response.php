<?php
declare(strict_types=1);

// Helper di risposta condivisi.

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url, int $code = 302): void
{
    if (!headers_sent()) {
        header('Location: ' . $url, true, $code);
    }
    exit;
}

function abort(int $code, string $message = ''): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    $titles = [
        400 => 'Richiesta non valida',
        403 => 'Accesso negato',
        404 => 'Non trovato',
        405 => 'Metodo non consentito',
        419 => 'Sessione scaduta',
        500 => 'Errore interno',
    ];
    $title = $titles[$code] ?? 'Errore';
    $msg = $message !== '' ? $message : $title;
    echo '<!doctype html><meta charset="utf-8"><title>' . e((string)$code) . ' — ' . e($title) . '</title>';
    echo '<body style="font:15px/1.5 system-ui,sans-serif;max-width:560px;margin:80px auto;padding:0 20px;color:#0f2a1a;">';
    echo '<h1 style="margin:0 0 12px;font-size:22px;">' . e((string)$code) . ' · ' . e($title) . '</h1>';
    echo '<p style="margin:0;color:#3a5a46;">' . e($msg) . '</p>';
    echo '</body>';
    exit;
}

function json_response($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function client_ip(): ?string
{
    return $_SERVER['REMOTE_ADDR'] ?? null;
}
