<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/edition.php';
require_once __DIR__ . '/../inc/response.php';

auth_boot();
auth_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort(405, 'Metodo non consentito.');
}
csrf_check();

$id = (int)($_POST['edition_id'] ?? 0);
$ok = edition_set_active($id);
if (!$ok) {
    abort(400, 'Edizione non trovata.');
}
audit_log('switch_edition', ['edition_id' => $id]);

$back = $_POST['back'] ?? '/admin/index.php';
if (!is_string($back) || !preg_match('#^/admin/[A-Za-z0-9._/?&=%-]*$#', $back)) {
    $back = '/admin/index.php';
}
redirect($back);
