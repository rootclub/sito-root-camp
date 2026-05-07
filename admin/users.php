<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/admin_layout.php';
require_once __DIR__ . '/../inc/response.php';

auth_boot();
auth_require('admin');

$me = auth_user();
$myId = (int)$me['id'];

// Helper: numero admin attivi attualmente.
function count_active_admins(): int
{
    return (int)db()->query("SELECT COUNT(*) FROM admin_users WHERE role='admin' AND is_active=1")->fetchColumn();
}

// =====================================================================
// POST handler
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    switch ($action) {

        case 'create': {
            $username  = post_str('username');
            $email     = post_str('email');
            $role      = post_str('role', 'editor');
            $password  = (string)($_POST['password'] ?? '');
            $password2 = (string)($_POST['password2'] ?? '');

            $errors = [];
            if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) $errors[] = 'Username non valido (3-60 caratteri: lettere, numeri, "." "_" "-").';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email non valida.';
            if (!in_array($role, ['admin', 'editor'], true))         $errors[] = 'Ruolo non valido.';
            if (strlen($password) < 10)                              $errors[] = 'Password troppo corta (min 10).';
            if ($password !== $password2)                            $errors[] = 'Le due password non coincidono.';

            if (!$errors) {
                $check = db()->prepare('SELECT 1 FROM admin_users WHERE username = ? LIMIT 1');
                $check->execute([$username]);
                if ($check->fetchColumn()) $errors[] = "Esiste già un utente con username «$username».";
            }

            if ($errors) {
                flash_set('error', implode(' ', $errors));
                redirect('/admin/users.php?new=1');
            }

            $id = auth_create_user($username, $email !== '' ? $email : null, $password, $role);
            audit_log('create', ['entity'=>'admin_users','entity_id'=>$id,
                                 'payload'=>['username'=>$username,'role'=>$role]]);
            flash_set('ok', "Utente «$username» creato con ruolo $role.");
            redirect('/admin/users.php');
        }

        case 'update': {
            $id        = post_int('id');
            $email     = post_str('email');
            $role      = post_str('role', 'editor');
            $isActive  = post_bool('is_active');
            $password  = (string)($_POST['password'] ?? '');
            $password2 = (string)($_POST['password2'] ?? '');

            $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $u = $stmt->fetch();
            if (!$u) abort(404);

            $errors = [];
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email non valida.';
            if (!in_array($role, ['admin', 'editor'], true))                 $errors[] = 'Ruolo non valido.';

            // Self-protection: non posso degradarmi né disattivarmi.
            if ($id === $myId) {
                if ($role !== 'admin')  $errors[] = 'Non puoi cambiare il tuo stesso ruolo. Chiedi a un altro admin.';
                if (!$isActive)         $errors[] = 'Non puoi disattivare te stessə.';
            }

            // Last-admin protection.
            if ((int)$u['is_active'] === 1 && $u['role'] === 'admin') {
                $degrade   = $role !== 'admin';
                $deactivate = !$isActive;
                if (($degrade || $deactivate) && count_active_admins() <= 1) {
                    $errors[] = 'Sei l\'ultimə admin attivə: non puoi rimuoverti il ruolo o disattivarti senza prima creare/promuovere un altro admin.';
                }
            }

            if ($password !== '' || $password2 !== '') {
                if (strlen($password) < 10)    $errors[] = 'Nuova password troppo corta (min 10).';
                if ($password !== $password2)  $errors[] = 'Le nuove password non coincidono.';
            }

            if ($errors) {
                flash_set('error', implode(' ', $errors));
                redirect('/admin/users.php?edit=' . $id);
            }

            db()->prepare('UPDATE admin_users SET email = ?, role = ?, is_active = ? WHERE id = ?')
                ->execute([$email !== '' ? $email : null, $role, $isActive, $id]);

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
                    ->execute([$hash, $id]);
                audit_log('password_reset', ['entity'=>'admin_users','entity_id'=>$id,
                                             'payload'=>['by_self'=>$id===$myId]]);
            }

            audit_log('update', ['entity'=>'admin_users','entity_id'=>$id,
                                 'payload'=>['role'=>$role,'is_active'=>$isActive]]);
            flash_set('ok', 'Modifiche salvate.');
            redirect('/admin/users.php');
        }

        case 'delete': {
            $id = post_int('id');
            if ($id === $myId) {
                flash_set('error', 'Non puoi eliminare te stessə.');
                redirect('/admin/users.php');
            }
            $stmt = db()->prepare('SELECT username, role, is_active FROM admin_users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $u = $stmt->fetch();
            if (!$u) abort(404);

            if ($u['role'] === 'admin' && (int)$u['is_active'] === 1 && count_active_admins() <= 1) {
                flash_set('error', 'È l\'ultimo admin attivo: non posso eliminarlo. Crea o attiva prima un altro admin.');
                redirect('/admin/users.php');
            }

            db()->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
            audit_log('delete', ['entity'=>'admin_users','entity_id'=>$id,
                                 'payload'=>['username'=>$u['username']]]);
            flash_set('ok', "Utente «{$u['username']}» eliminato.");
            redirect('/admin/users.php');
        }

        default: abort(400);
    }
}

// =====================================================================
// GET / Render
// =====================================================================
$rows = db()->query(
    'SELECT id, username, email, role, is_active, last_login_at, created_at
     FROM admin_users ORDER BY role, username'
)->fetchAll();

$editId  = get_int('edit', 0);
$isNew   = get_int('new', 0) === 1;
$editing = null;
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash_set('error', 'Utente non trovato.');
        redirect('/admin/users.php');
    }
}

admin_layout_open('Utenti', 'users');
?>

<header class="page-head">
  <div>
    <div class="eyebrow">Solo admin</div>
    <h1>Utenti backoffice</h1>
  </div>
  <?php if (!$editing && !$isNew): ?>
    <a href="?new=1" class="btn accent">+ Nuovo utente</a>
  <?php endif; ?>
</header>

<?= flash_render() ?>

<?php if ($isNew): ?>
  <article class="card" style="margin-bottom:24px;">
    <header class="card-head">
      <h2>Nuovo utente</h2>
      <a href="/admin/users.php" class="card-cta">annulla</a>
    </header>
    <form method="post" class="form-grid" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <label class="field">
        <span class="field-label">Username</span>
        <input type="text" name="username" required maxlength="60" pattern="[A-Za-z0-9._-]{3,60}">
      </label>
      <label class="field">
        <span class="field-label">Ruolo</span>
        <select name="role">
          <option value="editor">editor</option>
          <option value="admin">admin</option>
        </select>
      </label>

      <label class="field field-full">
        <span class="field-label">Email <span class="muted">opzionale</span></span>
        <input type="email" name="email" maxlength="180">
      </label>

      <label class="field">
        <span class="field-label">Password <span class="muted">min 10 caratteri</span></span>
        <input type="password" name="password" required minlength="10" maxlength="200" autocomplete="new-password">
      </label>
      <label class="field">
        <span class="field-label">Conferma</span>
        <input type="password" name="password2" required minlength="10" maxlength="200" autocomplete="new-password">
      </label>

      <div class="form-actions">
        <button type="submit" class="btn accent">Crea utente</button>
        <a href="/admin/users.php" class="btn ghost">Annulla</a>
      </div>
    </form>
  </article>
<?php endif; ?>

<?php if ($editing): $isSelf = (int)$editing['id'] === $myId; ?>
  <article class="card" style="margin-bottom:24px;">
    <header class="card-head">
      <h2>Modifica utente · <?= e($editing['username']) ?> <?php if ($isSelf): ?><span class="pill pill-info">tu</span><?php endif; ?></h2>
      <a href="/admin/users.php" class="card-cta">annulla</a>
    </header>
    <form method="post" class="form-grid" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">

      <label class="field">
        <span class="field-label">Username <span class="muted">non modificabile</span></span>
        <input type="text" value="<?= e($editing['username']) ?>" disabled>
      </label>

      <label class="field">
        <span class="field-label">Ruolo <?php if ($isSelf): ?><span class="muted">non puoi cambiare il tuo</span><?php endif; ?></span>
        <select name="role" <?= $isSelf ? 'disabled' : '' ?>>
          <option value="editor" <?= $editing['role'] === 'editor' ? 'selected' : '' ?>>editor</option>
          <option value="admin"  <?= $editing['role'] === 'admin'  ? 'selected' : '' ?>>admin</option>
        </select>
        <?php if ($isSelf): ?>
          <input type="hidden" name="role" value="admin">
        <?php endif; ?>
      </label>

      <label class="field field-full">
        <span class="field-label">Email</span>
        <input type="email" name="email" value="<?= e((string)$editing['email']) ?>" maxlength="180">
      </label>

      <label class="field-check">
        <input type="checkbox" name="is_active" value="1"
               <?= !empty($editing['is_active']) ? 'checked' : '' ?>
               <?= $isSelf ? 'disabled' : '' ?>>
        <span>Attivo <?php if ($isSelf): ?><span class="muted">non puoi disattivare te stessə</span><?php endif; ?></span>
        <?php if ($isSelf): ?>
          <input type="hidden" name="is_active" value="1">
        <?php endif; ?>
      </label>

      <h2 class="form-section">Password (opzionale)</h2>
      <label class="field">
        <span class="field-label">Nuova password <span class="muted">lascia vuoto per non cambiare</span></span>
        <input type="password" name="password" minlength="10" maxlength="200" autocomplete="new-password">
      </label>
      <label class="field">
        <span class="field-label">Conferma</span>
        <input type="password" name="password2" minlength="10" maxlength="200" autocomplete="new-password">
      </label>

      <div class="form-actions">
        <button type="submit" class="btn accent">Salva</button>
        <a href="/admin/users.php" class="btn ghost">Annulla</a>
      </div>
    </form>
  </article>
<?php endif; ?>

<table class="data-table">
  <thead>
    <tr>
      <th>Username</th>
      <th>Email</th>
      <th>Ruolo</th>
      <th>Stato</th>
      <th>Ultimo accesso</th>
      <th style="width:140px;text-align:right;">Azioni</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r): $isSelf = (int)$r['id'] === $myId; ?>
      <tr>
        <td>
          <strong><?= e($r['username']) ?></strong>
          <?php if ($isSelf): ?> <span class="pill pill-info">tu</span><?php endif; ?>
        </td>
        <td class="small"><?= e((string)$r['email']) ?: '<span class="muted">—</span>' ?></td>
        <td><span class="role role-<?= e($r['role']) ?>"><?= e($r['role']) ?></span></td>
        <td>
          <?= !empty($r['is_active'])
                ? '<span class="pill pill-ok">attivo</span>'
                : '<span class="pill pill-mute">disattivato</span>' ?>
        </td>
        <td class="small mono">
          <?= !empty($r['last_login_at'])
                ? e(date('d/m/Y H:i', strtotime((string)$r['last_login_at'])))
                : '<span class="muted">mai</span>' ?>
        </td>
        <td class="actions-cell">
          <a href="?edit=<?= (int)$r['id'] ?>" class="btn-icon" title="Modifica">✎</a>
          <?php if (!$isSelf): ?>
            <form method="post" class="inline" onsubmit="return confirm('Eliminare l\'utente «<?= e(addslashes($r['username'])) ?>»?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="btn-icon danger" title="Elimina">×</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php admin_layout_close();
