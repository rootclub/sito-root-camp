<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/admin_layout.php';
require_once __DIR__ . '/../inc/mailer.php';
require_once __DIR__ . '/../inc/response.php';

auth_boot();
auth_require('admin');

$me = auth_user();
$result = null;
$detail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $to = post_str('to');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $result = false;
        $detail = 'Email destinatario non valida.';
    } else {
        ob_start();
        $ok = mailer_send(
            $to,
            'Test SMTP · /RooT-Camp backoffice',
            '<p>Funziona. 🎉</p><p>Inviata da <code>' . htmlspecialchars(SMTP_HOST) . '</code> alle ' . date('d/m/Y H:i:s') . '.</p>',
            "Funziona.\nInviata da " . SMTP_HOST . ' alle ' . date('d/m/Y H:i:s') . '.',
            null
        );
        $debug = ob_get_clean();
        audit_log('smtp_test', ['payload' => ['to' => $to, 'ok' => $ok]]);
        $result = $ok;
        if (!$ok) {
            $detail = 'Invio fallito. Controlla i log del server (error_log) per dettagli.';
            if ($debug !== '') $detail .= "\n\n" . $debug;
        } else {
            $detail = "Mail accettata dal server SMTP. Controlla la inbox di $to.";
        }
    }
}

admin_layout_open('Test SMTP', 'dashboard');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Diagnostica · solo admin</div>
    <h1>Test SMTP</h1>
  </div>
</header>

<?php if ($result === true): ?>
  <div class="alert ok"><?= e($detail) ?></div>
<?php elseif ($result === false): ?>
  <div class="alert error" style="white-space:pre-wrap;"><?= e($detail) ?></div>
<?php endif; ?>

<article class="card" style="max-width:560px;">
  <header class="card-head"><h2>Invia mail di prova</h2></header>

  <dl class="kv" style="margin-bottom:18px;">
    <dt>Host</dt>     <dd class="mono"><?= e(SMTP_HOST ?: '<span class="muted">non configurato</span>') ?>:<?= (int)SMTP_PORT ?></dd>
    <dt>Sicurezza</dt><dd class="mono"><?= e(SMTP_SECURE) ?></dd>
    <dt>From</dt>     <dd class="mono"><?= e(SMTP_FROM_NAME) ?> &lt;<?= e(SMTP_FROM) ?>&gt;</dd>
    <dt>Auth</dt>     <dd class="mono"><?= SMTP_USER !== '' ? e(SMTP_USER) : '<span class="muted">no auth</span>' ?></dd>
  </dl>

  <form method="post" class="form-grid" style="border:none;box-shadow:none;padding:0;">
    <?= csrf_field() ?>
    <label class="field field-full">
      <span class="field-label">Invia a</span>
      <input type="email" name="to" value="<?= e((string)$me['email']) ?>" required placeholder="tu@esempio.it">
    </label>
    <div class="form-actions">
      <button type="submit" class="btn accent">Invia mail di prova</button>
    </div>
  </form>
</article>

<?php admin_layout_close();
