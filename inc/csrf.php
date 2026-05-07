<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';

// Token CSRF per form admin.
// Singolo token per sessione, rigenerato al login (auth_attempt regenera la sessione).

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Errore di programmazione: la sessione deve essere già stata avviata.
        throw new RuntimeException('csrf_token() richiede una sessione attiva.');
    }
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $posted = $_POST['_csrf'] ?? '';
    $session = $_SESSION['_csrf'] ?? '';
    if (!is_string($posted) || $posted === '' || !is_string($session) || $session === '' || !hash_equals($session, $posted)) {
        abort(419, 'Token CSRF non valido. Ricarica la pagina e riprova.');
    }
}
