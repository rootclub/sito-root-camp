<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';

// =====================================================================
// Sessione
// =====================================================================

function auth_boot(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function auth_user(): ?array
{
    if (empty($_SESSION['_user_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null && $cached['id'] === (int)$_SESSION['_user_id']) {
        return $cached;
    }
    $stmt = db()->prepare(
        'SELECT id, username, email, role, is_active FROM admin_users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([(int)$_SESSION['_user_id']]);
    $u = $stmt->fetch();
    if (!$u || !$u['is_active']) {
        auth_logout();
        return null;
    }
    $u['id'] = (int)$u['id'];
    $cached = $u;
    return $u;
}

function auth_check(): bool
{
    return auth_user() !== null;
}

function auth_require(?string $role = null): void
{
    $u = auth_user();
    if (!$u) {
        $next = $_SERVER['REQUEST_URI'] ?? '/admin/index.php';
        redirect('/admin/login.php?next=' . urlencode($next));
    }
    if ($role !== null && $u['role'] !== $role) {
        abort(403, 'Permesso insufficiente per questa azione.');
    }
}

// =====================================================================
// Login / logout
// =====================================================================

function auth_attempt(string $username, string $password): bool
{
    $username = trim($username);
    if ($username === '' || $password === '') {
        return false;
    }

    $stmt = db()->prepare(
        'SELECT id, username, email, password_hash, role, is_active
           FROM admin_users WHERE username = ? LIMIT 1'
    );
    $stmt->execute([$username]);
    $u = $stmt->fetch();

    // Hash dummy con costo simile per uniformare i tempi quando l'utente non esiste.
    $dummy = '$2y$10$abcdefghijklmnopqrstuuQ7P3eAcM6dIY9q5Hq0wYGZqK6GZpGv2';
    $hash = $u['password_hash'] ?? $dummy;
    $ok = password_verify($password, $hash);

    if (!$ok || !$u || !$u['is_active']) {
        audit_log('login_fail', ['username' => $username]);
        return false;
    }

    // Rotazione session id (anti-fixation).
    session_regenerate_id(true);
    $_SESSION['_user_id']     = (int)$u['id'];
    $_SESSION['_user_role']   = $u['role'];
    $_SESSION['_user_name']   = $u['username'];
    $_SESSION['_login_time']  = time();
    unset($_SESSION['_csrf']); // forza rigenerazione del token al prossimo uso

    db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')
        ->execute([(int)$u['id']]);

    audit_log('login_ok', ['entity' => 'admin_users', 'entity_id' => (int)$u['id']]);
    return true;
}

function auth_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }
    session_destroy();
}

// =====================================================================
// CRUD utenti (usato da setup + /admin/users.php)
// =====================================================================

function auth_count_users(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
}

function auth_create_user(string $username, ?string $email, string $password, string $role): int
{
    if (!in_array($role, ['admin', 'editor'], true)) {
        throw new InvalidArgumentException('Ruolo non valido.');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare(
        'INSERT INTO admin_users (username, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$username, $email, $hash, $role]);
    return (int)db()->lastInsertId();
}

// =====================================================================
// Audit
// =====================================================================

function audit_log(string $action, array $opts = []): void
{
    try {
        $u = auth_user();
        $stmt = db()->prepare(
            'INSERT INTO admin_audit (user_id, username, action, entity, entity_id, edition_id, payload, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $payload = isset($opts['payload'])
            ? json_encode($opts['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        $stmt->execute([
            $u['id'] ?? null,
            $u['username'] ?? ($opts['username'] ?? null),
            $action,
            $opts['entity'] ?? null,
            $opts['entity_id'] ?? null,
            $opts['edition_id'] ?? null,
            $payload,
            client_ip(),
        ]);
    } catch (\Throwable $e) {
        error_log('audit_log failed: ' . $e->getMessage());
    }
}
