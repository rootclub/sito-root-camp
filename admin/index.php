<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/edition.php';
require_once __DIR__ . '/../inc/admin_layout.php';
require_once __DIR__ . '/../inc/response.php';

auth_boot();
auth_require();

$user = auth_user();
$ed   = edition_active();

// Statistiche edizione attiva
$stats = [
    'iscritti'      => 0,
    'check_in'      => 0,
    'incasso_eur'   => 0,
    'palinsesto'    => 0,
    'organizers'    => 0,
    'rules'         => 0,
];
$lastIscritti = [];
$daysToStart  = null;

if ($ed) {
    $edId = (int)$ed['id'];

    $stmt = db()->prepare(
        'SELECT
            COUNT(*) AS n,
            COALESCE(SUM(checked_in), 0) AS n_check,
            COALESCE(SUM(total_eur), 0) AS tot
         FROM iscrizioni WHERE edition_id = ?'
    );
    $stmt->execute([$edId]);
    $r = $stmt->fetch();
    $stats['iscritti']    = (int)$r['n'];
    $stats['check_in']    = (int)$r['n_check'];
    $stats['incasso_eur'] = (int)$r['tot'];

    $q = function (string $sql, array $args): int {
        $s = db()->prepare($sql);
        $s->execute($args);
        return (int)$s->fetchColumn();
    };
    $stats['palinsesto'] = $q('SELECT COUNT(*) FROM schedule_items WHERE edition_id = ?', [$edId]);
    $stats['organizers'] = $q('SELECT COUNT(*) FROM organizers     WHERE edition_id = ?', [$edId]);
    $stats['rules']      = $q('SELECT COUNT(*) FROM rules           WHERE edition_id = ?', [$edId]);

    $stmt = db()->prepare(
        'SELECT id, name, email, sleep_kind, total_eur, checked_in, created_at
         FROM iscrizioni WHERE edition_id = ? ORDER BY created_at DESC LIMIT 5'
    );
    $stmt->execute([$edId]);
    $lastIscritti = $stmt->fetchAll();

    if (!empty($ed['date_start'])) {
        $start = new DateTimeImmutable($ed['date_start']);
        $today = new DateTimeImmutable('today');
        $diff  = (int)$today->diff($start)->format('%r%a');
        $daysToStart = $diff;
    }
}

admin_layout_open('Dashboard', 'dashboard');
?>

<?php if ($ed): ?>
  <header class="page-head">
    <div>
      <div class="eyebrow">Edizione</div>
      <h1><?= e($ed['name']) ?> <span class="muted" style="font-weight:500;">· <?= e($ed['date_label']) ?></span></h1>
      <div class="page-meta">
        <span class="pill <?= $ed['is_current'] ? 'pill-ok' : 'pill-mute' ?>">
          <?= $ed['is_current'] ? '★ live sul sito' : 'non live' ?>
        </span>
        <span class="pill <?= $ed['is_published'] ? 'pill-ok' : 'pill-mute' ?>">
          <?= $ed['is_published'] ? 'pubblicata' : 'bozza' ?>
        </span>
        <span class="pill <?= $ed['registrations_open'] ? 'pill-ok' : 'pill-mute' ?>">
          iscrizioni <?= $ed['registrations_open'] ? 'aperte' : 'chiuse' ?>
        </span>
        <?php if ($daysToStart !== null): ?>
          <span class="pill pill-info">
            <?php if ($daysToStart > 0): ?>
              fra <strong><?= e((string)$daysToStart) ?></strong> giorni
            <?php elseif ($daysToStart === 0): ?>
              <strong>oggi!</strong>
            <?php else: ?>
              <?= e((string)abs($daysToStart)) ?> giorni fa
            <?php endif; ?>
          </span>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <section class="stats">
    <a href="/admin/iscrizioni.php" class="stat-card">
      <div class="stat-num"><?= e((string)$stats['iscritti']) ?></div>
      <div class="stat-lab">iscrizioni</div>
    </a>
    <a href="/admin/iscrizioni.php?filter=checked" class="stat-card">
      <div class="stat-num"><?= e((string)$stats['check_in']) ?></div>
      <div class="stat-lab">check-in fatti</div>
    </a>
    <div class="stat-card">
      <div class="stat-num"><?= e((string)$stats['incasso_eur']) ?> €</div>
      <div class="stat-lab">incasso previsto</div>
    </div>
    <a href="/admin/schedule.php" class="stat-card">
      <div class="stat-num"><?= e((string)$stats['palinsesto']) ?></div>
      <div class="stat-lab">slot in palinsesto</div>
    </a>
    <a href="/admin/organizers.php" class="stat-card">
      <div class="stat-num"><?= e((string)$stats['organizers']) ?></div>
      <div class="stat-lab">organizzazioni</div>
    </a>
    <a href="/admin/rules.php" class="stat-card">
      <div class="stat-num"><?= e((string)$stats['rules']) ?></div>
      <div class="stat-lab">regole pubblicate</div>
    </a>
  </section>

  <section class="dash-grid">
    <article class="card">
      <header class="card-head">
        <h2>Ultime iscrizioni</h2>
        <a href="/admin/iscrizioni.php" class="card-cta">vedi tutte →</a>
      </header>
      <?php if (empty($lastIscritti)): ?>
        <p class="muted">Ancora nessuna iscrizione.</p>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Quando</th>
              <th>Nome</th>
              <th>Email</th>
              <th>Sleep</th>
              <th>€</th>
              <th>Check-in</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lastIscritti as $i): ?>
              <tr>
                <td class="mono small"><?= e(date('d/m H:i', strtotime((string)$i['created_at']))) ?></td>
                <td><?= e($i['name']) ?></td>
                <td class="small"><?= e($i['email']) ?></td>
                <td class="small"><?= e($i['sleep_kind']) ?></td>
                <td class="mono"><?= e((string)$i['total_eur']) ?></td>
                <td><?= $i['checked_in'] ? '✓' : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </article>

    <article class="card">
      <header class="card-head">
        <h2>Edizione</h2>
        <a href="/admin/meta.php" class="card-cta">modifica →</a>
      </header>
      <dl class="kv">
        <dt>Date</dt>             <dd><?= e($ed['date_label']) ?></dd>
        <dt>Luogo</dt>            <dd><?= e($ed['loc_name']) ?> · <?= e((string)$ed['loc_city']) ?></dd>
        <dt>Biglietto base</dt>   <dd><?= e($ed['ticket_label'] ?: ($ed['ticket_price_eur'] . ' €')) ?></dd>
        <dt>Tessera</dt>          <dd><?= e((string)$ed['card_price_eur']) ?> € · cad.</dd>
        <dt>Email contatto</dt>   <dd><?= e((string)$ed['contact_email']) ?></dd>
      </dl>
    </article>
  </section>

<?php else: ?>
  <header class="page-head"><h1>Benvenutə</h1></header>
  <p>Nessuna edizione presente. Vai su <a href="/admin/editions.php">Edizioni</a> per crearne una.</p>
<?php endif; ?>

<?php
admin_layout_close();
