<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';

// =====================================================================
// Flash messages (one-shot, via sessione)
// =====================================================================

function flash_set(string $type, string $msg): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $_SESSION['_flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) return null;
    if (empty($_SESSION['_flash'])) return null;
    $f = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $f;
}

function flash_render(): string
{
    $f = flash_get();
    if (!$f) return '';
    $cls = ['ok','error','info'];
    $type = in_array($f['type'], $cls, true) ? $f['type'] : 'info';
    return '<div class="alert ' . $type . '">' . e($f['msg']) . '</div>';
}

// =====================================================================
// Helpers di input/validazione
// =====================================================================

function post_str(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function post_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? null;
    if ($v === null || $v === '') return $default;
    return (int)$v;
}

function post_bool(string $key): int
{
    return !empty($_POST[$key]) ? 1 : 0;
}

function get_int(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? null;
    if ($v === null || $v === '') return $default;
    return (int)$v;
}

function get_str(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}
