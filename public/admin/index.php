<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/_cms.php';
cms_bootstrap_storage();
cms_start_session();

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect_admin(string $suffix = ''): never
{
    header('Location: /admin/' . $suffix);
    exit;
}

function format_date(string $date, string $format = 'd/m/Y · H:i'): string
{
    try {
        $value = new DateTimeImmutable($date);
        return $value->setTimezone(new DateTimeZone('Africa/Algiers'))->format($format);
    } catch (Throwable) {
        return $date;
    }
}

$action = (string)($_POST['action'] ?? '');

if ($action === 'login') {
    cms_verify_csrf();
    if (!cms_rate_limit('admin-login', 8, 900)) {
        cms_flash('error', 'Trop de tentatives. Réessayez dans quelques minutes.');
        redirect_admin();
    }
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $valid = hash_equals(cms_admin_username(), $username)
        && cms_password_hash() !== ''
        && password_verify($password, cms_password_hash());
    if (!$valid) {
        cms_flash('error', 'Identifiant ou mot de passe incorrect.');
        redirect_admin();
    }
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['admin_username'] = $username;
    cms_flash('success', 'Connexion réussie.');
    redirect_admin();
}

if ($action === 'logout') {
    cms_verify_csrf();
    $_SESSION = [];
    session_destroy();
    redirect_admin();
}

if ($action === 'update_submission') {
    cms_require_auth();
    cms_verify_csrf();
    $id = (string)($_POST['id'] ?? '');
    $submission = cms_get_submission($id);
    if ($submission === null) {
        cms_flash('error', 'Cette demande est introuvable.');
        redirect_admin();
    }
    $status = (string)($_POST['status'] ?? '');
    if (!array_key_exists($status, CMS_STATUSES)) {
        $status = (string)($submission['status'] ?? 'new');
    }
    $submission['status'] = $status;
    $submission['notes'] = cms_clean_text($_POST['notes'] ?? '', 5000);
    $submission['updated_at'] = gmdate('c');
    cms_save_submission($submission);
    cms_flash('success', 'La demande a été mise à jour.');
    redirect_admin('?id=' . rawurlencode($id));
}

if ($action === 'change_password') {
    cms_require_auth();
    cms_verify_csrf();
    $current = (string)($_POST['current_password'] ?? '');
    $next = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (!password_verify($current, cms_password_hash())) {
        cms_flash('error', 'Le mot de passe actuel est incorrect.');
    } elseif (strlen($next) < 12) {
        cms_flash('error', 'Le nouveau mot de passe doit contenir au moins 12 caractères.');
    } elseif (!hash_equals($next, $confirm)) {
        cms_flash('error', 'La confirmation ne correspond pas.');
    } else {
        $path = cms_private_dir() . '/password.hash';
        file_put_contents($path, password_hash($next, PASSWORD_DEFAULT), LOCK_EX);
        @chmod($path, 0640);
        cms_flash('success', 'Votre mot de passe a été modifié.');
    }
    redirect_admin('?view=settings');
}

if (isset($_GET['export']) && cms_is_authenticated()) {
    $items = cms_list_submissions();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="demandes-aiouez-' . gmdate('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Référence', 'Date', 'Statut', 'Nom', 'Entreprise', 'Email', 'Téléphone', 'Besoin', 'Message', 'Notes'], ';');
    foreach ($items as $item) {
        fputcsv($output, [
            strtoupper(substr((string)$item['id'], -6)),
            format_date((string)$item['created_at']),
            CMS_STATUSES[(string)($item['status'] ?? 'new')] ?? 'Nouveau',
            $item['name'] ?? '',
            $item['company'] ?? '',
            $item['email'] ?? '',
            $item['phone'] ?? '',
            $item['need'] ?? '',
            $item['message'] ?? '',
            $item['notes'] ?? '',
        ], ';');
    }
    fclose($output);
    exit;
}

$flash = cms_take_flash();
$authenticated = cms_is_authenticated();
$csrf = cms_csrf_token();

if (!$authenticated):
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Administration · Cabinet Aiouez</title>
  <link rel="icon" href="/logo-aiouez.svg">
  <link rel="stylesheet" href="/admin/admin.css">
</head>
<body class="login-page">
  <main class="login-shell">
    <section class="login-panel" aria-labelledby="login-title">
      <a class="admin-brand" href="/" aria-label="Retour au site Cabinet Aiouez">
        <img src="/logo-aiouez.svg" alt="">
        <span>Administration</span>
      </a>
      <div class="login-copy">
        <p class="eyebrow">Espace sécurisé</p>
        <h1 id="login-title">Bienvenue.</h1>
        <p>Connectez-vous pour consulter et traiter les demandes reçues depuis le site.</p>
      </div>
      <?php if ($flash): ?>
        <div class="notice notice-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
      <?php endif; ?>
      <form method="post" class="login-form">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="login">
        <label>
          <span>Identifiant</span>
          <input name="username" autocomplete="username" required autofocus>
        </label>
        <label>
          <span>Mot de passe</span>
          <input name="password" type="password" autocomplete="current-password" required>
        </label>
        <button class="primary-button" type="submit">Se connecter <span aria-hidden="true">→</span></button>
      </form>
      <a class="back-link" href="/">← Retour au site public</a>
    </section>
    <aside class="login-visual" aria-hidden="true">
      <span>Cabinet Aiouez</span>
      <strong>Vos demandes,<br>au même endroit.</strong>
      <div class="visual-mark">A</div>
    </aside>
  </main>
</body>
</html>
<?php
exit;
endif;

$allItems = cms_list_submissions();
$query = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? 'all');
$view = (string)($_GET['view'] ?? 'inbox');
$selectedId = (string)($_GET['id'] ?? '');
$selected = $selectedId !== '' ? cms_get_submission($selectedId) : null;

$filtered = array_values(array_filter($allItems, static function (array $item) use ($query, $statusFilter): bool {
    $statusMatches = $statusFilter === 'all' || ($item['status'] ?? 'new') === $statusFilter;
    if (!$statusMatches) {
        return false;
    }
    if ($query === '') {
        return true;
    }
    $haystack = mb_strtolower(implode(' ', [
        $item['name'] ?? '',
        $item['company'] ?? '',
        $item['email'] ?? '',
        $item['phone'] ?? '',
        $item['need'] ?? '',
    ]));
    return str_contains($haystack, mb_strtolower($query));
}));

$total = count($allItems);
$newCount = count(array_filter($allItems, static fn (array $item): bool => ($item['status'] ?? 'new') === 'new'));
$contactedCount = count(array_filter($allItems, static fn (array $item): bool => ($item['status'] ?? '') === 'contacted'));
$weekAgo = new DateTimeImmutable('-7 days');
$weekCount = count(array_filter($allItems, static function (array $item) use ($weekAgo): bool {
    try {
        return new DateTimeImmutable((string)$item['created_at']) >= $weekAgo;
    } catch (Throwable) {
        return false;
    }
}));
$daily = [];
for ($offset = 6; $offset >= 0; $offset--) {
    $key = (new DateTimeImmutable("-$offset days", new DateTimeZone('Africa/Algiers')))->format('Y-m-d');
    $daily[$key] = 0;
}
foreach ($allItems as $item) {
    try {
        $key = (new DateTimeImmutable((string)$item['created_at']))
            ->setTimezone(new DateTimeZone('Africa/Algiers'))->format('Y-m-d');
        if (array_key_exists($key, $daily)) {
            $daily[$key]++;
        }
    } catch (Throwable) {
    }
}
$maxDaily = max(1, ...array_values($daily));
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Tableau de bord · Cabinet Aiouez</title>
  <link rel="icon" href="/logo-aiouez.svg">
  <link rel="stylesheet" href="/admin/admin.css">
  <script src="/admin/admin.js" defer></script>
</head>
<body>
  <div class="dashboard-shell">
    <aside class="sidebar" id="sidebar">
      <a class="admin-brand" href="/admin/">
        <img src="/logo-aiouez.svg" alt="">
        <span>Administration</span>
      </a>
      <nav aria-label="Navigation principale">
        <a class="<?= $view === 'inbox' ? 'active' : '' ?>" href="/admin/">
          <span class="nav-icon" aria-hidden="true">⌁</span>
          Demandes
          <?php if ($newCount > 0): ?><b><?= $newCount ?></b><?php endif; ?>
        </a>
        <a class="<?= $view === 'settings' ? 'active' : '' ?>" href="/admin/?view=settings">
          <span class="nav-icon" aria-hidden="true">⚙</span>
          Paramètres
        </a>
        <a href="/" target="_blank" rel="noreferrer">
          <span class="nav-icon" aria-hidden="true">↗</span>
          Voir le site
        </a>
      </nav>
      <div class="sidebar-footer">
        <div class="admin-user">
          <span><?= e(mb_strtoupper(mb_substr(cms_admin_username(), 0, 1))) ?></span>
          <div><strong><?= e(cms_admin_username()) ?></strong><small>Administrateur</small></div>
        </div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="logout">
          <button class="text-button" type="submit">Déconnexion</button>
        </form>
      </div>
    </aside>

    <main class="dashboard-main">
      <header class="topbar">
        <button class="menu-button" type="button" aria-label="Ouvrir la navigation" aria-controls="sidebar" aria-expanded="false">☰</button>
        <div>
          <p class="eyebrow">Cabinet Aiouez</p>
          <h1><?= $view === 'settings' ? 'Paramètres' : 'Demandes clients' ?></h1>
        </div>
        <?php if ($view !== 'settings'): ?>
          <a class="secondary-button" href="/admin/?export=csv">Exporter CSV <span aria-hidden="true">↓</span></a>
        <?php endif; ?>
      </header>

      <?php if ($flash): ?>
        <div class="notice notice-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
      <?php endif; ?>

      <?php if ($view === 'settings'): ?>
        <section class="settings-grid">
          <article class="settings-card">
            <div class="section-heading">
              <div><p class="eyebrow">Sécurité</p><h2>Modifier le mot de passe</h2></div>
            </div>
            <form method="post" class="settings-form">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="change_password">
              <label><span>Mot de passe actuel</span><input type="password" name="current_password" autocomplete="current-password" required></label>
              <label><span>Nouveau mot de passe</span><input type="password" name="new_password" autocomplete="new-password" minlength="12" required><small>12 caractères minimum.</small></label>
              <label><span>Confirmer le mot de passe</span><input type="password" name="confirm_password" autocomplete="new-password" minlength="12" required></label>
              <button class="primary-button" type="submit">Enregistrer le mot de passe</button>
            </form>
          </article>
          <article class="settings-card info-card">
            <p class="eyebrow">À propos</p>
            <h2>Collecte des demandes</h2>
            <p>Les données du formulaire sont enregistrées dans un espace privé du serveur, inaccessible depuis le web.</p>
            <dl><div><dt>Notifications</dt><dd>Email du cabinet</dd></div><div><dt>Protection</dt><dd>HTTPS · CSRF · limitation d’envoi</dd></div><div><dt>Export</dt><dd>CSV compatible Excel</dd></div></dl>
          </article>
        </section>
      <?php else: ?>
        <section class="metrics" aria-label="Résumé des demandes">
          <article><span>Total</span><strong><?= $total ?></strong><small>Toutes les demandes</small></article>
          <article><span>Nouvelles</span><strong><?= $newCount ?></strong><small>À traiter</small></article>
          <article><span>7 derniers jours</span><strong><?= $weekCount ?></strong><small>Demandes récentes</small></article>
          <article><span>Contactées</span><strong><?= $contactedCount ?></strong><small>Suivi engagé</small></article>
        </section>

        <section class="activity-card">
          <div class="section-heading">
            <div><p class="eyebrow">Activité</p><h2>Demandes sur 7 jours</h2></div>
            <span><?= $weekCount ?> reçue<?= $weekCount === 1 ? '' : 's' ?></span>
          </div>
          <div class="bar-chart" role="img" aria-label="<?= e('Demandes quotidiennes sur sept jours : ' . implode(', ', array_map(
              static fn (string $day, int $count): string => format_date($day, 'd/m') . " : $count",
              array_keys($daily),
              array_values($daily)
          ))) ?>">
            <?php foreach ($daily as $day => $count): ?>
              <div class="bar-column">
                <span class="bar-value"><?= $count ?></span>
                <div class="bar-track"><i style="height: <?= max(5, (int)round(($count / $maxDaily) * 100)) ?>%"></i></div>
                <small><?= e(format_date($day, 'd/m')) ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="inbox-card">
          <div class="section-heading inbox-heading">
            <div><p class="eyebrow">Boîte de réception</p><h2><?= count($filtered) ?> demande<?= count($filtered) === 1 ? '' : 's' ?></h2></div>
            <form method="get" class="filters" role="search">
              <label class="search-field">
                <span class="sr-only">Rechercher dans les demandes</span>
                <span aria-hidden="true">⌕</span>
                <input type="search" name="q" value="<?= e($query) ?>" placeholder="Nom, entreprise ou email">
              </label>
              <label>
                <span class="sr-only">Filtrer par statut</span>
                <select name="status" onchange="this.form.submit()">
                  <option value="all">Tous les statuts</option>
                  <?php foreach (CMS_STATUSES as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button class="secondary-button" type="submit">Rechercher</button>
            </form>
          </div>
          <?php if ($filtered === []): ?>
            <div class="empty-state"><span aria-hidden="true">✦</span><h3>Aucune demande trouvée</h3><p>Les nouvelles demandes apparaîtront ici dès leur réception.</p></div>
          <?php else: ?>
            <div class="submission-list">
              <?php foreach ($filtered as $item): $status = (string)($item['status'] ?? 'new'); ?>
                <a class="submission-row <?= $selectedId === $item['id'] ? 'selected' : '' ?>" href="<?= e('/admin/?id=' . rawurlencode((string)$item['id']) . ($query !== '' ? '&q=' . rawurlencode($query) : '') . ($statusFilter !== 'all' ? '&status=' . rawurlencode($statusFilter) : '')) ?>">
                  <span class="avatar"><?= e(mb_strtoupper(mb_substr((string)$item['name'], 0, 1))) ?></span>
                  <span class="person"><strong><?= e($item['name']) ?></strong><small><?= e($item['company'] ?: $item['email']) ?></small></span>
                  <span class="need"><?= e($item['need']) ?></span>
                  <span class="status status-<?= e($status) ?>"><i aria-hidden="true"></i><?= e(CMS_STATUSES[$status] ?? 'Nouveau') ?></span>
                  <time datetime="<?= e($item['created_at']) ?>"><?= e(format_date((string)$item['created_at'], 'd/m/Y')) ?></time>
                  <span class="row-arrow" aria-hidden="true">→</span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </main>
  </div>

  <?php if ($selected): $selectedStatus = (string)($selected['status'] ?? 'new'); ?>
    <div class="detail-backdrop" data-close-detail></div>
    <aside class="detail-panel" aria-labelledby="detail-title">
      <header>
        <div><p class="eyebrow">Demande #<?= e(strtoupper(substr((string)$selected['id'], -6))) ?></p><h2 id="detail-title"><?= e($selected['name']) ?></h2></div>
        <a class="close-button" href="/admin/" aria-label="Fermer le détail">×</a>
      </header>
      <div class="detail-body">
        <span class="status status-<?= e($selectedStatus) ?>"><i aria-hidden="true"></i><?= e(CMS_STATUSES[$selectedStatus] ?? 'Nouveau') ?></span>
        <dl class="contact-details">
          <div><dt>Entreprise</dt><dd><?= e($selected['company'] ?: 'Non renseignée') ?></dd></div>
          <div><dt>Email</dt><dd><a href="mailto:<?= e($selected['email']) ?>"><?= e($selected['email']) ?></a></dd></div>
          <div><dt>Téléphone</dt><dd><?= $selected['phone'] ? '<a href="tel:' . e($selected['phone']) . '">' . e($selected['phone']) . '</a>' : 'Non renseigné' ?></dd></div>
          <div><dt>Expertise</dt><dd><?= e($selected['need']) ?></dd></div>
          <div><dt>Reçue le</dt><dd><?= e(format_date((string)$selected['created_at'])) ?></dd></div>
        </dl>
        <section class="message-box"><h3>Message</h3><p><?= nl2br(e($selected['message'] ?: 'Aucun message complémentaire.')) ?></p></section>
        <form method="post" class="followup-form">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="update_submission">
          <input type="hidden" name="id" value="<?= e($selected['id']) ?>">
          <label><span>Statut</span><select name="status"><?php foreach (CMS_STATUSES as $key => $label): ?><option value="<?= e($key) ?>" <?= $selectedStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <label><span>Notes internes</span><textarea name="notes" rows="5" placeholder="Ajoutez un rappel ou le résultat de votre échange…"><?= e($selected['notes'] ?? '') ?></textarea></label>
          <button class="primary-button" type="submit">Enregistrer le suivi</button>
        </form>
      </div>
    </aside>
  <?php endif; ?>
</body>
</html>
