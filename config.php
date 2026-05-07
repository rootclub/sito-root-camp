<?php
declare(strict_types=1);

// Bootstrap globale: carica .env, definisce costanti, prepara la sessione.
// Tutti i punti di ingresso PHP (admin/*, api/*, bin/*) devono iniziare con:
//   require_once __DIR__ . '/../config.php';   (o path relativo equivalente)
//
// NB: l'hosting di rootclub.it NON consente la creazione della directory /lib/
// in document root. Per questo le librerie sono in /inc/ (PHP "include" convention).

if (defined('CONFIG_LOADED')) {
    return;
}
define('CONFIG_LOADED', true);

require_once __DIR__ . '/inc/env.php';

if (!is_readable(__DIR__ . '/.env')) {
    http_response_code(500);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "ERRORE: file .env mancante in " . __DIR__ . PHP_EOL);
    }
    exit('Configurazione mancante: copia .env.example in .env e compilalo.');
}

env_load(__DIR__ . '/.env');

// --- App ---
define('APP_ENV',          env('APP_ENV', 'production'));
define('APP_DEBUG',        APP_ENV !== 'production');
define('APP_URL',          rtrim((string)env('APP_URL', ''), '/'));
define('APP_TIMEZONE',     env('APP_TIMEZONE', 'Europe/Rome'));
define('SESSION_LIFETIME', (int)env('SESSION_LIFETIME', '7200'));
define('SETUP_TOKEN',      (string)env('SETUP_TOKEN', ''));

date_default_timezone_set(APP_TIMEZONE);

// --- Database ---
define('DB_HOST',    env('DB_HOST', 'localhost'));
define('DB_PORT',    (int)env('DB_PORT', '3306'));
define('DB_NAME',    env('DB_NAME', ''));
define('DB_USER',    env('DB_USER', ''));
define('DB_PASS',    env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// --- SMTP ---
define('SMTP_HOST',      env('SMTP_HOST', ''));
define('SMTP_PORT',      (int)env('SMTP_PORT', '587'));
define('SMTP_USER',      env('SMTP_USER', ''));
define('SMTP_PASS',      env('SMTP_PASS', ''));
define('SMTP_FROM',      env('SMTP_FROM', ''));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', '/RooT-Camp'));
define('SMTP_SECURE',    env('SMTP_SECURE', 'tls'));

// --- Errori ---
error_reporting(E_ALL);
if (APP_DEBUG) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// --- Sessione: parametri sicuri (ma session_start() è on-demand) ---
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => !APP_DEBUG,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('rootcamp_admin');
}
