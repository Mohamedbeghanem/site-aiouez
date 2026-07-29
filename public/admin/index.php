<?php
declare(strict_types=1);

require __DIR__ . '/_admin.php';

cms_security_headers();
cms_start_session();
crm_db();
admin_handle_actions();

$flash = cms_take_flash();
$csrf = cms_csrf_token();
$user = crm_current_user();
$locale = admin_locale();

function render_language_switcher(): void
{
    $locale = admin_locale();
    $current = ADMIN_LOCALES[$locale];
    ?>
    <details class="topbar-menu language-menu">
      <summary class="language-trigger" aria-label="<?= e(admin_t('Changer la langue')) ?>">
        <span class="language-flag" aria-hidden="true"><?= e($current['flag']) ?></span>
        <span class="language-code"><?= e(mb_strtoupper($locale)) ?></span>
        <span class="language-caret" aria-hidden="true">⌄</span>
      </summary>
      <div class="popover-menu language-popover" aria-label="<?= e(admin_t('Langue de l’interface')) ?>">
        <span class="popover-title"><?= e(admin_t('Langue de l’interface')) ?></span>
        <?php foreach (ADMIN_LOCALES as $code => $language): ?>
          <a href="<?= e(admin_language_url($code)) ?>" lang="<?= e($code) ?>" dir="<?= e($language['dir']) ?>" class="<?= $locale === $code ? 'active' : '' ?>" <?= $locale === $code ? 'aria-current="true"' : '' ?>>
            <span class="language-flag" aria-hidden="true"><?= e($language['flag']) ?></span>
            <span><strong><?= e($language['native']) ?></strong><small><?= e(mb_strtoupper($code)) ?></small></span>
            <?php if ($locale === $code): ?><b aria-hidden="true">✓</b><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </details>
    <?php
}

if ($user === null):
?>
<!doctype html>
<html lang="<?= e($locale) ?>" dir="<?= e(admin_direction($locale)) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e(admin_t('Connexion CRM · Cabinet Aiouez')) ?></title>
  <link rel="icon" href="/logo-aiouez.svg">
  <link rel="stylesheet" href="/admin/admin.css?v=<?= e((string)filemtime(__DIR__ . '/admin.css')) ?>">
</head>
<body class="login-page">
  <main class="login-shell">
    <section class="login-panel" aria-labelledby="login-title">
      <div class="login-language"><?php render_language_switcher(); ?></div>
      <a class="admin-brand" href="/" aria-label="Retour au site Cabinet Aiouez">
        <img src="/logo-aiouez.svg" alt="">
        <span>CRM sécurisé</span>
      </a>
      <div class="login-copy">
        <p class="eyebrow">Espace équipe</p>
        <h1 id="login-title">Bienvenue.</h1>
        <p>Retrouvez vos prospects, clients, opportunités et prochaines actions dans un seul espace.</p>
      </div>
      <?php if ($flash): ?><div class="notice notice-<?= e($flash['type']) ?>" role="alert"><?= e(admin_t($flash['message'])) ?></div><?php endif; ?>
      <form method="post" class="login-form">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="login">
        <label><span>Identifiant</span><input name="username" autocomplete="username" required autofocus></label>
        <label><span>Mot de passe</span><input name="password" type="password" autocomplete="current-password" required></label>
        <button class="primary-button" type="submit">Se connecter <span aria-hidden="true">→</span></button>
      </form>
      <a class="back-link" href="/">← Retour au site public</a>
    </section>
    <aside class="login-visual" aria-hidden="true">
      <span>Cabinet Aiouez</span>
      <strong>Votre relation client,<br>clairement organisée.</strong>
      <div class="visual-mark">A</div>
    </aside>
  </main>
  <script>window.AIOUEZ_I18N=<?= json_encode(['locale' => $locale, 'dir' => admin_direction($locale), 'catalog' => admin_catalog($locale)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
  <script src="/admin/admin-i18n.js?v=<?= e((string)filemtime(__DIR__ . '/admin-i18n.js')) ?>"></script>
</body>
</html>
<?php
exit;
endif;

admin_handle_downloads($user);
crm_require_permission('dashboard.view');

$view = (string)($_GET['view'] ?? 'dashboard');
$allowedViews = ['dashboard','leads','contacts','companies','pipeline','tasks','activities','documents','reports','notifications','settings'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'dashboard';
}
$id = max(0, (int)($_GET['id'] ?? 0));
$query = trim((string)($_GET['q'] ?? ''));
$like = '%' . $query . '%';
$users = crm_fetch_all('SELECT id,full_name,username,role FROM users WHERE is_active=1 AND deleted_at IS NULL ORDER BY full_name');
$companies = crm_fetch_all('SELECT id,name FROM companies WHERE deleted_at IS NULL ORDER BY name');
$contacts = crm_fetch_all('SELECT id,first_name,last_name FROM contacts WHERE deleted_at IS NULL ORDER BY first_name,last_name');
$stages = crm_fetch_all('SELECT * FROM pipeline_stages WHERE is_active=1 ORDER BY position');
$unread = (int)(crm_fetch_one('SELECT COUNT(*) AS total FROM notifications WHERE user_id=? AND read_at IS NULL', [$user['id']])['total'] ?? 0);

function options(array $items, mixed $selected, string $valueKey, string $labelKey, string $empty = '— Non attribué —'): void
{
    if ($empty !== '') {
        echo '<option value="">' . e(admin_t($empty)) . '</option>';
    }
    foreach ($items as $item) {
        echo '<option value="' . e($item[$valueKey]) . '"' . ((string)$selected === (string)$item[$valueKey] ? ' selected' : '') . '>' . e($item[$labelKey]) . '</option>';
    }
}

function select_map(array $items, mixed $selected): void
{
    foreach ($items as $value => $label) {
        echo '<option value="' . e($value) . '"' . ((string)$selected === (string)$value ? ' selected' : '') . '>' . e(admin_t((string)$label)) . '</option>';
    }
}

function crm_status(string $label, string $key = 'neutral'): string
{
    return '<span class="status status-' . e($key) . '"><i aria-hidden="true"></i>' . e(admin_t($label)) . '</span>';
}

function admin_icon(string $name, string $class = 'ui-icon'): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'leads' => '<path d="M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/><path d="M4 21a8 8 0 0 1 16 0"/><path d="M19 8h4M21 6v4"/>',
        'contacts' => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.5a4 4 0 0 1 0 8M17 14a7 7 0 0 1 5 7"/>',
        'companies' => '<path d="M3 21h18M5 21V5l7-3v19M19 21V9l-7-3M8 7h1M8 11h1M8 15h1M15 11h1M15 15h1"/>',
        'pipeline' => '<path d="M4 5h16M4 12h10M4 19h6"/><circle cx="18" cy="12" r="2"/><circle cx="14" cy="19" r="2"/>',
        'tasks' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="m8 12 3 3 6-7"/>',
        'activities' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'documents' => '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M9 12h6M9 16h6"/>',
        'reports' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'notifications' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'email' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.8 1.8 0 0 0 .36 1.98l.06.06-2.83 2.83-.06-.06A1.8 1.8 0 0 0 15 19.4a1.8 1.8 0 0 0-1.08 1.65V21h-4v-.09A1.8 1.8 0 0 0 8.8 19.3a1.8 1.8 0 0 0-1.98.36l-.06.06-2.83-2.83.06-.06A1.8 1.8 0 0 0 4.6 15a1.8 1.8 0 0 0-1.65-1.08H3v-4h.09A1.8 1.8 0 0 0 4.7 8.8a1.8 1.8 0 0 0-.36-1.98l-.06-.06 2.83-2.83.06.06A1.8 1.8 0 0 0 9 4.6a1.8 1.8 0 0 0 1.08-1.65V3h4v.09A1.8 1.8 0 0 0 15.2 4.7a1.8 1.8 0 0 0 1.98-.36l.06-.06 2.83 2.83-.06.06A1.8 1.8 0 0 0 19.4 9a1.8 1.8 0 0 0 1.65 1.08H21v4h-.09A1.8 1.8 0 0 0 19.4 15Z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'download' => '<path d="M12 3v12m0 0 5-5m-5 5-5-5M5 21h14"/>',
        'upload' => '<path d="M12 16V4m0 0-5 5m5-5 5 5M5 20h14"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'chevron-down' => '<path d="m7 10 5 5 5-5"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>',
        'filter' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
        'more' => '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
        'empty-leads' => '<path d="M4 19V5a2 2 0 0 1 2-2h8l6 6v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/><path d="M14 3v6h6M8 14h8M8 17h5"/>',
        'empty-contacts' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'empty-companies' => '<path d="M4 21V6l8-3 8 3v15M2 21h20M9 9h1M14 9h1M9 13h1M14 13h1"/>',
        'empty-tasks' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="m8 12 3 3 6-7"/>',
        'empty-activities' => '<path d="M4 12h4l2-6 4 12 2-6h4"/>',
        'empty-documents' => '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
    ];
    $content = $paths[$name] ?? $paths['more'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $content . '</svg>';
}

function empty_state(string $title, string $body, string $icon = 'empty-leads', ?string $actionLabel = null, ?string $actionHref = null): void
{
    echo '<div class="empty-state"><span class="empty-icon">' . admin_icon($icon) . '</span><h3>' . e(admin_t($title)) . '</h3><p>' . e(admin_t($body)) . '</p>';
    if ($actionLabel !== null && $actionHref !== null) {
        echo '<a class="secondary-button" href="' . e($actionHref) . '">' . admin_icon('plus') . e(admin_t($actionLabel)) . '</a>';
    }
    echo '</div>';
}

$titles = [
    'dashboard' => 'Vue d’ensemble', 'leads' => 'Leads', 'contacts' => 'Contacts',
    'companies' => 'Entreprises', 'pipeline' => 'Pipeline commercial', 'tasks' => 'Tâches & calendrier',
    'activities' => 'Activités', 'documents' => 'Documents', 'reports' => 'Rapports',
    'notifications' => 'Notifications', 'settings' => 'Réglages',
];
$subtitles = [
    'dashboard' => 'Pilotez l’activité commerciale et les priorités du cabinet.',
    'leads' => 'Qualifiez les demandes et transformez-les en missions.',
    'contacts' => 'Centralisez les interlocuteurs et leur historique.',
    'companies' => 'Suivez les organisations, contacts et opportunités associées.',
    'pipeline' => 'Visualisez l’avancement et la valeur des opportunités.',
    'tasks' => 'Organisez les échéances, relances et responsabilités.',
    'activities' => 'Conservez une trace claire des échanges et rendez-vous.',
    'documents' => 'Gérez les fichiers clients dans un espace privé.',
    'reports' => 'Analysez la conversion, le pipeline et les sources d’acquisition.',
    'notifications' => 'Retrouvez les alertes qui nécessitent votre attention.',
    'settings' => 'Configurez l’espace, l’équipe et les règles du CRM.',
];
$titles = array_map(static fn(string $value): string => admin_t($value), $titles);
$subtitles = array_map(static fn(string $value): string => admin_t($value), $subtitles);
$nav = [
    'dashboard' => ['dashboard','Vue d’ensemble'], 'leads' => ['leads','Leads'], 'contacts' => ['contacts','Contacts'],
    'companies' => ['companies','Entreprises'], 'pipeline' => ['pipeline','Pipeline'], 'tasks' => ['tasks','Tâches'],
    'activities' => ['activities','Activités'], 'documents' => ['documents','Documents'], 'reports' => ['reports','Rapports'],
];
?>
<!doctype html>
<html lang="<?= e($locale) ?>" dir="<?= e(admin_direction($locale)) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($titles[$view]) ?> · <?= e(crm_setting('crm_name', 'Cabinet Aiouez CRM')) ?></title>
  <link rel="icon" href="/logo-aiouez.svg">
  <link rel="stylesheet" href="/admin/admin.css?v=<?= e((string)filemtime(__DIR__ . '/admin.css')) ?>">
</head>
<body>
<div class="dashboard-shell">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <a class="admin-brand" href="/admin/?view=dashboard">
        <img src="/logo-aiouez.svg" alt="">
        <span class="brand-copy"><strong>AIOUEZ CRM</strong><small>Cabinet Aiouez</small></span>
      </a>
      <button class="sidebar-close" type="button" data-sidebar-close aria-label="Fermer la navigation"><?= admin_icon('close') ?></button>
    </div>
    <nav aria-label="Navigation principale">
      <?php foreach ($nav as $key => [$icon, $label]): ?>
        <a href="?view=<?= e($key) ?>" class="<?= $view === $key ? 'active' : '' ?>" <?= $view === $key ? 'aria-current="page"' : '' ?>>
          <span class="nav-icon"><?= admin_icon($icon) ?></span><span><?= e(admin_t($label)) ?></span>
          <?php if ($key === 'leads'): ?><b><?= (int)(crm_fetch_one('SELECT COUNT(*) AS total FROM leads WHERE status="new" AND deleted_at IS NULL')['total'] ?? 0) ?></b><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-secondary">
      <a href="?view=notifications" class="<?= $view === 'notifications' ? 'active' : '' ?>"><span class="nav-icon"><?= admin_icon('notifications') ?></span><span>Notifications</span><?php if ($unread): ?><b><?= $unread ?></b><?php endif; ?></a>
      <?php if (($user['role'] ?? '') === 'admin'): ?><a href="?view=settings" class="<?= $view === 'settings' ? 'active' : '' ?>"><span class="nav-icon"><?= admin_icon('settings') ?></span><span>Réglages</span></a><?php endif; ?>
    </div>
    <div class="sidebar-footer">
      <div class="admin-user"><span class="avatar avatar-sm"><?= e(mb_strtoupper(mb_substr((string)$user['full_name'], 0, 1))) ?></span><div><strong><?= e($user['full_name']) ?></strong><small><?= e(CRM_ROLES[$user['role']] ?? $user['role']) ?></small></div></div>
      <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="logout"><button class="text-button"><?= admin_icon('logout') ?><span>Se déconnecter</span></button></form>
    </div>
  </aside>
  <button class="sidebar-scrim" type="button" data-sidebar-close aria-label="Fermer la navigation"></button>

  <main class="dashboard-main">
    <header class="topbar">
      <button class="menu-button" type="button" aria-controls="sidebar" aria-expanded="false"><span class="sr-only">Ouvrir le menu</span><?= admin_icon('menu') ?></button>
      <div class="topbar-context"><span>CRM</span><strong><?= e($titles[$view]) ?></strong></div>
      <form class="global-search" method="get">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <label><span class="sr-only"><?= e(admin_t('Rechercher')) ?> <?= e(mb_strtolower($titles[$view])) ?></span><?= admin_icon('search') ?><input name="q" value="<?= e($query) ?>" placeholder="<?= e(admin_t('Rechercher')) ?> <?= e(mb_strtolower($titles[$view])) ?>…"></label>
      </form>
      <?php render_language_switcher(); ?>
      <details class="topbar-menu quick-add">
        <summary class="icon-button primary-icon-button" aria-label="Créer un élément"><?= admin_icon('plus') ?></summary>
        <div class="popover-menu" role="menu">
          <span class="popover-title">Créer rapidement</span>
          <a role="menuitem" href="?view=leads&new=1"><?= admin_icon('leads') ?><span><strong>Nouveau lead</strong><small>Ajouter une demande</small></span></a>
          <a role="menuitem" href="?view=contacts&new=1"><?= admin_icon('contacts') ?><span><strong>Nouveau contact</strong><small>Créer une fiche contact</small></span></a>
          <a role="menuitem" href="?view=tasks&new=1"><?= admin_icon('tasks') ?><span><strong>Nouvelle tâche</strong><small>Planifier une action</small></span></a>
          <a role="menuitem" href="?view=pipeline&new=1"><?= admin_icon('pipeline') ?><span><strong>Nouvelle opportunité</strong><small>Alimenter le pipeline</small></span></a>
        </div>
      </details>
      <a class="notification-button icon-button" href="?view=notifications" aria-label="<?= $unread ?> notification(s) non lue(s)"><?= admin_icon('notifications') ?><?php if ($unread): ?><b><?= $unread ?></b><?php endif; ?></a>
      <details class="topbar-menu profile-menu">
        <summary class="profile-trigger"><span class="avatar avatar-sm"><?= e(mb_strtoupper(mb_substr((string)$user['full_name'], 0, 1))) ?></span><span class="profile-trigger-copy"><strong><?= e($user['full_name']) ?></strong><small><?= e(CRM_ROLES[$user['role']] ?? $user['role']) ?></small></span><?= admin_icon('chevron-down') ?></summary>
        <div class="popover-menu profile-popover">
          <div class="profile-popover-head"><span class="avatar"><?= e(mb_strtoupper(mb_substr((string)$user['full_name'], 0, 1))) ?></span><div><strong><?= e($user['full_name']) ?></strong><small><?= e($user['email'] ?: $user['username']) ?></small></div></div>
          <?php if (($user['role'] ?? '') === 'admin'): ?><a href="?view=settings"><?= admin_icon('settings') ?><span>Réglages</span></a><?php endif; ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="logout"><button><?= admin_icon('logout') ?><span>Se déconnecter</span></button></form>
        </div>
      </details>
    </header>
    <?php if ($flash): ?><div class="notice notice-<?= e($flash['type']) ?>" role="status"><?= e(admin_t($flash['message'])) ?></div><?php endif; ?>
    <section class="page-header">
      <div><p class="page-kicker"><?= e($view === 'dashboard' ? 'Aujourd’hui' : 'Espace de travail') ?></p><h1><?= e($titles[$view]) ?></h1><p><?= e($subtitles[$view]) ?></p></div>
      <div class="page-header-actions">
        <?php if ($view === 'dashboard' || $view === 'activities'): ?><a class="primary-button" href="?view=activities#new-activity"><?= admin_icon('plus') ?>Ajouter une activité</a>
        <?php elseif ($view === 'leads'): ?><a class="secondary-button" href="?export=leads"><?= admin_icon('download') ?>Exporter</a><a class="primary-button" href="?view=leads&new=1"><?= admin_icon('plus') ?>Nouveau lead</a>
        <?php elseif ($view === 'contacts'): ?><a class="secondary-button" href="?export=contacts"><?= admin_icon('download') ?>Exporter</a><a class="primary-button" href="?view=contacts&new=1"><?= admin_icon('plus') ?>Nouveau contact</a>
        <?php elseif ($view === 'companies'): ?><a class="secondary-button" href="?export=companies"><?= admin_icon('download') ?>Exporter</a><a class="primary-button" href="?view=companies&new=1"><?= admin_icon('plus') ?>Nouvelle entreprise</a>
        <?php elseif ($view === 'pipeline'): ?><a class="secondary-button" href="?export=opportunities"><?= admin_icon('download') ?>Exporter</a><a class="primary-button" href="?view=pipeline&new=1"><?= admin_icon('plus') ?>Nouvelle opportunité</a>
        <?php elseif ($view === 'tasks'): ?><a class="primary-button" href="?view=tasks&new=1"><?= admin_icon('plus') ?>Nouvelle tâche</a>
        <?php elseif ($view === 'documents'): ?><a class="primary-button" href="#upload-document"><?= admin_icon('upload') ?>Téléverser</a>
        <?php elseif ($view === 'reports'): ?><a class="secondary-button" href="?export=opportunities"><?= admin_icon('download') ?>Exporter les opportunités</a>
        <?php endif; ?>
      </div>
    </section>
    <form class="mobile-search" method="get">
      <input type="hidden" name="view" value="<?= e($view) ?>">
      <label><span class="sr-only"><?= e(admin_t('Rechercher')) ?> <?= e(mb_strtolower($titles[$view])) ?></span><?= admin_icon('search') ?><input name="q" value="<?= e($query) ?>" placeholder="<?= e(admin_t('Rechercher…')) ?>"></label>
      <button class="secondary-button" type="submit">Rechercher</button>
    </form>

<?php if ($view === 'dashboard'):
    $metrics = [
        'new_leads' => (int)(crm_fetch_one('SELECT COUNT(*) AS total FROM leads WHERE status="new" AND deleted_at IS NULL')['total'] ?? 0),
        'open_pipeline' => (float)(crm_fetch_one('SELECT COALESCE(SUM(o.value),0) AS total FROM opportunities o JOIN pipeline_stages s ON s.id=o.stage_id WHERE o.deleted_at IS NULL AND s.is_won=0 AND s.is_lost=0')['total'] ?? 0),
        'due_tasks' => (int)(crm_fetch_one('SELECT COUNT(*) AS total FROM tasks WHERE status NOT IN ("completed","cancelled") AND deleted_at IS NULL AND due_at <= ?', [gmdate('Y-m-d 23:59:59')])['total'] ?? 0),
        'won_month' => (float)(crm_fetch_one('SELECT COALESCE(SUM(o.value),0) AS total FROM opportunities o JOIN pipeline_stages s ON s.id=o.stage_id WHERE o.deleted_at IS NULL AND s.is_won=1 AND o.closed_at >= ?', [gmdate('Y-m-01 00:00:00')])['total'] ?? 0),
    ];
    $recentActivities = crm_fetch_all('SELECT a.*,u.full_name,c.first_name,c.last_name,co.name AS company_name,l.name AS lead_name,o.name AS opportunity_name FROM activities a LEFT JOIN users u ON u.id=a.created_by LEFT JOIN contacts c ON c.id=a.contact_id LEFT JOIN companies co ON co.id=a.company_id LEFT JOIN leads l ON l.id=a.lead_id LEFT JOIN opportunities o ON o.id=a.opportunity_id WHERE a.deleted_at IS NULL ORDER BY a.activity_at DESC LIMIT 6');
    $tasks = crm_fetch_all('SELECT t.*,u.full_name FROM tasks t LEFT JOIN users u ON u.id=t.assigned_to WHERE t.deleted_at IS NULL AND t.status NOT IN ("completed","cancelled") ORDER BY CASE WHEN t.due_at IS NULL THEN 1 ELSE 0 END,t.due_at LIMIT 6');
    $stageTotals = crm_fetch_all('SELECT s.name,s.color,COUNT(o.id) AS count,COALESCE(SUM(o.value),0) AS value FROM pipeline_stages s LEFT JOIN opportunities o ON o.stage_id=s.id AND o.deleted_at IS NULL WHERE s.is_active=1 GROUP BY s.id ORDER BY s.position');
?>
    <section class="metrics" aria-label="Indicateurs clés">
      <article><span>Nouveaux leads</span><strong><?= $metrics['new_leads'] ?></strong><small>À qualifier</small></article>
      <article><span>Pipeline ouvert</span><strong><?= admin_money($metrics['open_pipeline']) ?></strong><small>Valeur commerciale</small></article>
      <article><span>Tâches dues</span><strong><?= $metrics['due_tasks'] ?></strong><small>Aujourd’hui ou en retard</small></article>
      <article><span>Gagné ce mois</span><strong><?= admin_money($metrics['won_month']) ?></strong><small>Revenu signé</small></article>
    </section>
    <section class="dashboard-grid">
      <article class="crm-card">
        <div class="section-heading"><div><p class="eyebrow">Pipeline</p><h2>Répartition des opportunités</h2></div><a href="?view=pipeline">Ouvrir →</a></div>
        <div class="stage-bars">
          <?php $maxStage = max(1, ...array_map(static fn($s) => (int)$s['count'], $stageTotals)); foreach ($stageTotals as $stage): ?>
            <div><header><span><?= e($stage['name']) ?></span><b><?= (int)$stage['count'] ?> · <?= admin_money($stage['value']) ?></b></header><span class="stage-track"><i style="width:<?= max(3, round((int)$stage['count'] / $maxStage * 100)) ?>%;background:<?= e($stage['color']) ?>"></i></span></div>
          <?php endforeach; ?>
        </div>
      </article>
      <article class="crm-card">
        <div class="section-heading"><div><p class="eyebrow">Priorités</p><h2>Prochaines tâches</h2></div><a href="?view=tasks">Tout voir →</a></div>
        <div class="compact-list">
          <?php foreach ($tasks as $task): ?><a href="?view=tasks&id=<?= $task['id'] ?>"><span class="priority-dot priority-<?= e($task['priority']) ?>"></span><div><strong><?= e($task['title']) ?></strong><small><?= e($task['full_name'] ?? 'Non attribuée') ?></small></div><time><?= admin_date($task['due_at'], 'd M') ?></time></a><?php endforeach; ?>
          <?php if (!$tasks) empty_state('Rien d’urgent', 'Les prochaines tâches apparaîtront ici.'); ?>
        </div>
      </article>
    </section>
    <section class="crm-card recent-card">
      <div class="section-heading"><div><p class="eyebrow">Journal du cabinet</p><h2>Activités récentes</h2></div><a href="?view=activities">Tout voir →</a></div>
      <div class="timeline-card">
        <?php foreach ($recentActivities as $activity): $related = $activity['company_name'] ?: ($activity['lead_name'] ?: ($activity['opportunity_name'] ?: trim($activity['first_name'].' '.$activity['last_name']))); ?><article class="timeline-item"><i></i><div><header><strong><?= e($activity['subject']) ?></strong><?= crm_status(CRM_ACTIVITY_TYPES[$activity['type']] ?? $activity['type'], $activity['type']) ?></header><p><?= nl2br(e($activity['body'])) ?></p><small><?= admin_date($activity['activity_at']) ?> · <?= e($activity['full_name'] ?? 'Système') ?><?= $related ? ' · '.e($related) : '' ?></small></div></article><?php endforeach; ?>
        <?php if (!$recentActivities) empty_state('Aucune activité récente', 'Ajoutez un appel, une note ou un rendez-vous pour alimenter le journal.', 'empty-activities', 'Ajouter une activité', '?view=activities#new-activity'); ?>
      </div>
    </section>

<?php elseif ($view === 'leads'):
    $statusFilter = (string)($_GET['status'] ?? '');
    $params = []; $where = ['l.deleted_at IS NULL'];
    if ($query !== '') { $where[] = '(l.name LIKE ? OR l.company_name LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR l.service LIKE ?)'; array_push($params, $like,$like,$like,$like,$like); }
    if (isset(CRM_LEAD_STATUSES[$statusFilter])) { $where[] = 'l.status=?'; $params[] = $statusFilter; }
    $items = crm_fetch_all('SELECT l.*,u.full_name AS owner_name FROM leads l LEFT JOIN users u ON u.id=l.owner_id WHERE ' . implode(' AND ', $where) . ' ORDER BY l.created_at DESC LIMIT 300', $params);
    $selected = $id ? crm_fetch_one('SELECT * FROM leads WHERE id=? AND deleted_at IS NULL', [$id]) : null;
    $editing = isset($_GET['new']) || isset($_GET['edit']);
?>
    <div class="page-actions"><div class="filter-chips" aria-label="Filtrer par statut"><a class="<?= $statusFilter===''?'active':'' ?>" href="?view=leads">Tous</a><?php foreach (CRM_LEAD_STATUSES as $key=>$label): ?><a class="<?= $statusFilter===$key?'active':'' ?>" href="?view=leads&status=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?></div></div>
    <section class="crm-card table-card"><div class="table-summary"><strong><?= count($items) ?> lead(s)</strong><span>Prospects issus du site, des imports et des ajouts manuels.</span></div><div class="data-list">
      <?php foreach ($items as $lead): ?><a class="data-row" href="?view=leads&id=<?= $lead['id'] ?>"><span class="avatar"><?= e(mb_strtoupper(mb_substr($lead['name'],0,1))) ?></span><div class="primary-cell"><strong><?= e($lead['name']) ?></strong><small><?= e($lead['email'] ?: $lead['phone']) ?></small></div><span><?= e($lead['company_name'] ?: 'Particulier') ?></span><span><?= e($lead['service'] ?: '—') ?></span><?= crm_status(CRM_LEAD_STATUSES[$lead['status']] ?? $lead['status'],$lead['status']) ?><time><?= admin_date($lead['created_at'],'d/m/Y') ?></time><b>›</b></a><?php endforeach; ?>
      <?php if (!$items) empty_state('Aucun lead', 'Modifiez vos filtres ou ajoutez un premier prospect.', 'empty-leads', 'Nouveau lead', '?view=leads&new=1'); ?>
    </div></section>
    <?php if ($selected || $editing): $record = $selected ?? ['id'=>'','name'=>'','company_name'=>'','email'=>'','phone'=>'','service'=>'','message'=>'','status'=>'new','priority'=>'normal','source'=>'manual','estimated_value'=>'','owner_id'=>$user['id']]; ?>
      <div class="detail-backdrop" data-close-panel></div><aside class="detail-panel" aria-label="<?= $selected ? 'Fiche lead' : 'Nouveau lead' ?>"><header><div><p class="eyebrow">Lead</p><h2><?= e($record['name'] ?: 'Nouveau prospect') ?></h2></div><a class="close-button" href="?view=leads" aria-label="Fermer">×</a></header><div class="detail-body">
        <form method="post" class="record-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_lead"><input type="hidden" name="return_view" value="leads"><input type="hidden" name="id" value="<?= e($record['id']) ?>">
          <div class="field-grid"><label class="wide"><span>Nom complet *</span><input name="name" value="<?= e($record['name']) ?>" required></label><label><span>Entreprise</span><input name="company_name" value="<?= e($record['company_name']) ?>"></label><label><span>Email</span><input type="email" name="email" value="<?= e($record['email']) ?>"></label><label><span>Téléphone</span><input name="phone" value="<?= e($record['phone']) ?>"></label><label><span>Service</span><input name="service" value="<?= e($record['service']) ?>"></label><label><span>Statut</span><select name="status"><?php select_map(CRM_LEAD_STATUSES,$record['status']); ?></select></label><label><span>Priorité</span><select name="priority"><?php select_map(CRM_PRIORITIES,$record['priority']); ?></select></label><label><span>Source</span><input name="source" value="<?= e($record['source']) ?>"></label><label><span>Valeur estimée (DZD)</span><input type="number" min="0" step="1" name="estimated_value" value="<?= e($record['estimated_value']) ?>"></label><label class="wide"><span>Responsable</span><select name="owner_id"><?php options($users,$record['owner_id'],'id','full_name'); ?></select></label><label class="wide"><span>Message / besoin</span><textarea name="message"><?= e($record['message']) ?></textarea></label></div><div class="form-actions"><button class="primary-button">Enregistrer</button><?php if ($selected): ?><a class="secondary-button" href="?view=leads&id=<?= $selected['id'] ?>">Annuler</a><?php endif; ?></div>
        </form>
        <?php if ($selected): ?>
          <?php if (!$selected['converted_at']): ?><details class="action-box"><summary>Convertir en client & opportunité</summary><form method="post" class="record-form compact"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="convert_lead"><input type="hidden" name="return_view" value="leads"><input type="hidden" name="id" value="<?= $selected['id'] ?>"><label><span>Nom de l’opportunité</span><input name="opportunity_name" value="<?= e('Mission · '.$selected['name']) ?>"></label><label><span>Valeur</span><input type="number" name="value" min="0" value="<?= e($selected['estimated_value']) ?>"></label><label><span>Clôture prévue</span><input type="date" name="expected_close_date"></label><label><span>Prochaine action</span><input name="next_action" value="Qualifier le besoin"></label><label class="check-label"><input type="checkbox" name="create_opportunity" checked> Créer l’opportunité</label><button class="primary-button">Convertir</button></form></details><?php endif; ?>
          <?php render_email_composer($csrf,'lead',$selected); ?>
          <?php render_related($selected,'lead'); ?>
          <details class="action-box"><summary>Ajouter une note ou activité</summary><?php render_activity_form($csrf,'lead',(int)$selected['id'],$users); ?></details>
          <?php render_tags($csrf, 'lead', (int)$selected['id']); ?>
          <form method="post" class="danger-zone" data-confirm="Archiver ce lead ?"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="archive_record"><input type="hidden" name="record_type" value="lead"><input type="hidden" name="id" value="<?= $selected['id'] ?>"><input type="hidden" name="return_view" value="leads"><button>Archiver ce lead</button></form>
        <?php endif; ?>
      </div></aside>
    <?php endif; ?>

<?php elseif ($view === 'contacts'):
    $params=[];$where=['c.deleted_at IS NULL']; if($query!==''){ $where[]='(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)'; array_push($params,$like,$like,$like,$like); }
    $items=crm_fetch_all('SELECT c.*,co.name AS company_name,u.full_name AS owner_name,(SELECT MAX(a.activity_at) FROM activities a WHERE a.contact_id=c.id AND a.deleted_at IS NULL) AS last_activity_at FROM contacts c LEFT JOIN companies co ON co.id=c.company_id LEFT JOIN users u ON u.id=c.owner_id WHERE '.implode(' AND ',$where).' ORDER BY c.first_name,c.last_name LIMIT 500',$params);
    $selected=$id?crm_fetch_one('SELECT * FROM contacts WHERE id=? AND deleted_at IS NULL',[$id]):null;$editing=isset($_GET['new'])||isset($_GET['edit']);
?>
    <div class="page-actions"><p><strong><?= count($items) ?></strong> contact(s) dans votre base relationnelle</p></div>
    <section class="crm-card table-card"><div class="table-summary"><strong>Contacts</strong><span>Coordonnées, rôle, entreprise, responsable et dernière activité.</span></div><div class="data-list"><?php foreach($items as $item): ?><a class="data-row contact-row" href="?view=contacts&id=<?= $item['id'] ?>"><span class="avatar"><?= e(mb_strtoupper(mb_substr($item['first_name'],0,1).mb_substr($item['last_name'],0,1))) ?></span><div class="primary-cell"><strong><?= e(trim($item['first_name'].' '.$item['last_name'])) ?></strong><small><?= e(trim(($item['job_title']?:'Contact').' · '.($item['email']?:($item['phone']?:$item['mobile'])),' ·')) ?></small></div><span><?= e($item['company_name'] ?: 'Indépendant') ?></span><span><?= e($item['phone'] ?: $item['mobile']) ?></span><span><?= e($item['owner_name']??'Non attribué') ?></span><?= crm_status(ucfirst($item['status']),$item['status']) ?><time><?= admin_date($item['last_activity_at']?:$item['updated_at'],'d/m/Y') ?></time><b>›</b></a><?php endforeach; ?><?php if(!$items)empty_state('Aucun contact','Convertissez un lead ou créez votre premier contact.','empty-contacts','Nouveau contact','?view=contacts&new=1'); ?></div></section>
    <?php if($selected||$editing):$record=$selected??['id'=>'','first_name'=>'','last_name'=>'','email'=>'','phone'=>'','mobile'=>'','job_title'=>'','preferred_language'=>'fr','address'=>'','city'=>'','country'=>'Algérie','source'=>'manual','status'=>'prospect','owner_id'=>$user['id'],'company_id'=>'','notes'=>'']; ?>
      <div class="detail-backdrop" data-close-panel></div><aside class="detail-panel"><header><div><p class="eyebrow">Contact</p><h2><?= e(trim($record['first_name'].' '.$record['last_name']) ?: 'Nouveau contact') ?></h2></div><a class="close-button" href="?view=contacts">×</a></header><div class="detail-body"><form method="post" class="record-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_contact"><input type="hidden" name="return_view" value="contacts"><input type="hidden" name="id" value="<?= e($record['id']) ?>"><div class="field-grid"><label><span>Prénom *</span><input name="first_name" required value="<?= e($record['first_name']) ?>"></label><label><span>Nom</span><input name="last_name" value="<?= e($record['last_name']) ?>"></label><label><span>Email</span><input type="email" name="email" value="<?= e($record['email']) ?>"></label><label><span>Téléphone</span><input name="phone" value="<?= e($record['phone']) ?>"></label><label><span>Mobile</span><input name="mobile" value="<?= e($record['mobile']) ?>"></label><label><span>Fonction</span><input name="job_title" value="<?= e($record['job_title']) ?>"></label><label class="wide"><span>Entreprise</span><select name="company_id"><?php options($companies,$record['company_id'],'id','name'); ?></select></label><label><span>Ville</span><input name="city" value="<?= e($record['city']) ?>"></label><label><span>Pays</span><input name="country" value="<?= e($record['country']) ?>"></label><label><span>Langue</span><select name="preferred_language"><option value="fr" <?= $record['preferred_language']==='fr'?'selected':'' ?>>Français</option><option value="ar" <?= $record['preferred_language']==='ar'?'selected':'' ?>>Arabe</option><option value="en" <?= $record['preferred_language']==='en'?'selected':'' ?>>Anglais</option></select></label><label><span>Statut</span><select name="status"><?php select_map(['prospect'=>'Prospect','client'=>'Client','inactive'=>'Inactif'],$record['status']); ?></select></label><label><span>Source</span><input name="source" value="<?= e($record['source']) ?>"></label><label><span>Responsable</span><select name="owner_id"><?php options($users,$record['owner_id'],'id','full_name'); ?></select></label><label class="wide"><span>Adresse</span><textarea name="address"><?= e($record['address']) ?></textarea></label><label class="wide"><span>Notes</span><textarea name="notes"><?= e($record['notes']) ?></textarea></label></div><button class="primary-button">Enregistrer</button></form><?php if($selected): ?><?php render_email_composer($csrf,'contact',$selected); ?><details class="action-box"><summary>Ajouter une activité</summary><?php render_activity_form($csrf,'contact',(int)$selected['id'],$users); ?></details><?php render_tags($csrf,'contact',(int)$selected['id']); ?><?php render_related($selected,'contact'); ?><?php endif; ?></div></aside>
    <?php endif; ?>

<?php elseif ($view === 'companies'):
    $params=[];$where=['co.deleted_at IS NULL'];if($query!==''){$where[]='(co.name LIKE ? OR co.legal_name LIKE ? OR co.email LIKE ? OR co.city LIKE ?)';array_push($params,$like,$like,$like,$like);}
    $items=crm_fetch_all('SELECT co.*,u.full_name AS owner_name,COUNT(DISTINCT c.id) AS contact_count,COUNT(DISTINCT o.id) AS opportunity_count,(SELECT COALESCE(SUM(ox.value),0) FROM opportunities ox WHERE ox.company_id=co.id AND ox.deleted_at IS NULL) AS pipeline_value,(SELECT MAX(a.activity_at) FROM activities a WHERE a.company_id=co.id AND a.deleted_at IS NULL) AS last_activity_at FROM companies co LEFT JOIN users u ON u.id=co.owner_id LEFT JOIN contacts c ON c.company_id=co.id AND c.deleted_at IS NULL LEFT JOIN opportunities o ON o.company_id=co.id AND o.deleted_at IS NULL WHERE '.implode(' AND ',$where).' GROUP BY co.id,u.full_name ORDER BY co.name LIMIT 500',$params);
    $selected=$id?crm_fetch_one('SELECT * FROM companies WHERE id=? AND deleted_at IS NULL',[$id]):null;$editing=isset($_GET['new'])||isset($_GET['edit']);
?>
    <div class="page-actions"><p><strong><?= count($items) ?></strong> entreprise(s) suivie(s)</p></div>
    <section class="crm-card table-card"><div class="table-summary"><strong>Portefeuille entreprises</strong><span>Contacts, valeur commerciale, responsable et dernière activité.</span></div><div class="data-list"><?php foreach($items as $item): ?><a class="data-row company-row" href="?view=companies&id=<?= $item['id'] ?>"><span class="avatar square"><?= e(mb_strtoupper(mb_substr($item['name'],0,2))) ?></span><div class="primary-cell"><strong><?= e($item['name']) ?></strong><small><?= e($item['industry'] ?: $item['city']) ?></small></div><span><?= (int)$item['contact_count'] ?> contact(s) · <?= (int)$item['opportunity_count'] ?> opportunité(s)</span><span><?= admin_money($item['pipeline_value']) ?></span><span><?= e($item['owner_name']??'Non attribuée') ?></span><?= crm_status(ucfirst($item['status']),$item['status']) ?><time><?= admin_date($item['last_activity_at']?:$item['updated_at'],'d/m/Y') ?></time><b>›</b></a><?php endforeach; ?><?php if(!$items)empty_state('Aucune entreprise','Créez une organisation ou convertissez un lead entreprise.','empty-companies','Nouvelle entreprise','?view=companies&new=1'); ?></div></section>
    <?php if($selected||$editing):$record=$selected??['id'=>'','name'=>'','legal_name'=>'','industry'=>'','website'=>'','email'=>'','phone'=>'','address'=>'','city'=>'','country'=>'Algérie','tax_id'=>'','registration_number'=>'','status'=>'prospect','owner_id'=>$user['id'],'notes'=>'']; ?>
      <div class="detail-backdrop" data-close-panel></div><aside class="detail-panel"><header><div><p class="eyebrow">Entreprise</p><h2><?= e($record['name'] ?: 'Nouvelle entreprise') ?></h2></div><a class="close-button" href="?view=companies">×</a></header><div class="detail-body"><form method="post" class="record-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_company"><input type="hidden" name="return_view" value="companies"><input type="hidden" name="id" value="<?= e($record['id']) ?>"><div class="field-grid"><label class="wide"><span>Nom *</span><input name="name" required value="<?= e($record['name']) ?>"></label><label><span>Raison sociale</span><input name="legal_name" value="<?= e($record['legal_name']) ?>"></label><label><span>Secteur</span><input name="industry" value="<?= e($record['industry']) ?>"></label><label><span>Email</span><input type="email" name="email" value="<?= e($record['email']) ?>"></label><label><span>Téléphone</span><input name="phone" value="<?= e($record['phone']) ?>"></label><label><span>Site web</span><input type="url" name="website" value="<?= e($record['website']) ?>"></label><label><span>Ville</span><input name="city" value="<?= e($record['city']) ?>"></label><label><span>Pays</span><input name="country" value="<?= e($record['country']) ?>"></label><label><span>NIF</span><input name="tax_id" value="<?= e($record['tax_id']) ?>"></label><label><span>Registre commerce</span><input name="registration_number" value="<?= e($record['registration_number']) ?>"></label><label><span>Statut</span><select name="status"><?php select_map(['prospect'=>'Prospect','client'=>'Client','partner'=>'Partenaire','inactive'=>'Inactif'],$record['status']); ?></select></label><label><span>Responsable</span><select name="owner_id"><?php options($users,$record['owner_id'],'id','full_name'); ?></select></label><label class="wide"><span>Adresse</span><textarea name="address"><?= e($record['address']) ?></textarea></label><label class="wide"><span>Notes</span><textarea name="notes"><?= e($record['notes']) ?></textarea></label></div><button class="primary-button">Enregistrer</button></form><?php if($selected): ?><?php render_onboarding($csrf,(int)$selected['id'],$users); ?><?php render_email_composer($csrf,'company',$selected); ?><details class="action-box"><summary>Ajouter une activité</summary><?php render_activity_form($csrf,'company',(int)$selected['id'],$users); ?></details><?php render_tags($csrf,'company',(int)$selected['id']); ?><?php render_related($selected,'company'); ?><?php endif; ?></div></aside>
    <?php endif; ?>

<?php elseif ($view === 'pipeline'):
    $items=crm_fetch_all('SELECT o.*,s.name AS stage_name,s.color,c.first_name,c.last_name,co.name AS company_name,u.full_name AS owner_name FROM opportunities o JOIN pipeline_stages s ON s.id=o.stage_id LEFT JOIN contacts c ON c.id=o.contact_id LEFT JOIN companies co ON co.id=o.company_id LEFT JOIN users u ON u.id=o.owner_id WHERE o.deleted_at IS NULL AND (?="" OR o.name LIKE ? OR co.name LIKE ?) ORDER BY s.position,o.expected_close_date,o.created_at',[$query,$like,$like]);
    $selected=$id?crm_fetch_one('SELECT * FROM opportunities WHERE id=? AND deleted_at IS NULL',[$id]):null;$editing=isset($_GET['new'])||isset($_GET['edit']);
?>
    <div class="page-actions"><p><strong><?= count($items) ?></strong> opportunité(s) · <strong><?= admin_money(array_sum(array_column($items,'value'))) ?></strong> de valeur totale</p></div>
    <section class="kanban" aria-label="Pipeline commercial"><?php foreach($stages as $stage):$stageItems=array_filter($items,static fn($o)=>(int)$o['stage_id']===(int)$stage['id']); ?><article class="kanban-column"><header><span style="--stage-color:<?= e($stage['color']) ?>"><?= e($stage['name']) ?></span><b><?= count($stageItems) ?></b><small><?= admin_money(array_sum(array_column($stageItems,'value'))) ?></small></header><div><?php foreach($stageItems as $opp): ?><a class="opportunity-card" href="?view=pipeline&id=<?= $opp['id'] ?>"><strong><?= e($opp['name']) ?></strong><span><?= e($opp['company_name'] ?: trim($opp['first_name'].' '.$opp['last_name'])) ?></span><b><?= admin_money($opp['value'],$opp['currency']) ?></b><?php if($opp['next_action']): ?><p><?= e($opp['next_action']) ?></p><?php endif; ?><small class="opportunity-owner"><?= e($opp['owner_name']??'Non attribuée') ?></small><footer><small><?= (int)$opp['probability'] ?>% de probabilité</small><time><?= admin_date($opp['expected_close_date'],'d M') ?></time></footer></a><?php endforeach; ?><?php if(!$stageItems): ?><p class="kanban-empty">Aucune opportunité</p><?php endif; ?></div></article><?php endforeach; ?></section>
    <?php if($selected||$editing):$record=$selected??['id'=>'','name'=>'','service'=>'','description'=>'','value'=>0,'currency'=>'DZD','probability'=>10,'expected_close_date'=>'','next_action'=>'','source'=>'manual','stage_id'=>$stages[0]['id']??'','owner_id'=>$user['id'],'contact_id'=>'','company_id'=>'']; ?>
      <div class="detail-backdrop" data-close-panel></div><aside class="detail-panel"><header><div><p class="eyebrow">Opportunité</p><h2><?= e($record['name'] ?: 'Nouvelle opportunité') ?></h2></div><a class="close-button" href="?view=pipeline">×</a></header><div class="detail-body"><form method="post" class="record-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_opportunity"><input type="hidden" name="return_view" value="pipeline"><input type="hidden" name="id" value="<?= e($record['id']) ?>"><div class="field-grid"><label class="wide"><span>Nom *</span><input name="name" required value="<?= e($record['name']) ?>"></label><label><span>Étape</span><select name="stage_id"><?php options($stages,$record['stage_id'],'id','name',''); ?></select></label><label><span>Probabilité (%)</span><input type="number" min="0" max="100" name="probability" value="<?= e($record['probability']) ?>"></label><label><span>Valeur</span><input type="number" min="0" name="value" value="<?= e($record['value']) ?>"></label><label><span>Devise</span><input name="currency" value="<?= e($record['currency']) ?>"></label><label><span>Service</span><input name="service" value="<?= e($record['service']) ?>"></label><label><span>Clôture prévue</span><input type="date" name="expected_close_date" value="<?= e(substr((string)$record['expected_close_date'],0,10)) ?>"></label><label class="wide"><span>Prochaine action</span><input name="next_action" value="<?= e($record['next_action']) ?>"></label><label><span>Entreprise</span><select name="company_id"><?php options($companies,$record['company_id'],'id','name'); ?></select></label><label><span>Contact</span><select name="contact_id"><option value="">— Aucun —</option><?php foreach($contacts as $c): ?><option value="<?= $c['id'] ?>" <?= (string)$record['contact_id']===(string)$c['id']?'selected':'' ?>><?= e(trim($c['first_name'].' '.$c['last_name'])) ?></option><?php endforeach; ?></select></label><label><span>Responsable</span><select name="owner_id"><?php options($users,$record['owner_id'],'id','full_name'); ?></select></label><label><span>Source</span><input name="source" value="<?= e($record['source']) ?>"></label><label class="wide"><span>Description</span><textarea name="description"><?= e($record['description']) ?></textarea></label></div><button class="primary-button">Enregistrer</button></form><?php if($selected): ?><details class="action-box"><summary>Déplacer rapidement</summary><form method="post" class="inline-action"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="move_opportunity"><input type="hidden" name="return_view" value="pipeline"><input type="hidden" name="id" value="<?= $selected['id'] ?>"><select name="stage_id"><?php options($stages,$selected['stage_id'],'id','name',''); ?></select><button class="secondary-button">Déplacer</button></form></details><?php render_email_composer($csrf,'opportunity',$selected); ?><details class="action-box"><summary>Ajouter une activité</summary><?php render_activity_form($csrf,'opportunity',(int)$selected['id'],$users); ?></details><?php render_tags($csrf,'opportunity',(int)$selected['id']); ?><?php render_related($selected,'opportunity'); ?><?php endif; ?></div></aside>
    <?php endif; ?>

<?php elseif ($view === 'tasks'):
    $statusFilter=(string)($_GET['status']??'open');$mode=(string)($_GET['mode']??'list');$params=[];$where=['t.deleted_at IS NULL'];if($statusFilter!=='all'){$where[]=$statusFilter==='open'?'t.status IN ("open","in_progress")':'t.status=?';if($statusFilter!=='open')$params[]=$statusFilter;}if($query!==''){$where[]='(t.title LIKE ? OR t.description LIKE ?)';array_push($params,$like,$like);}
    $items=crm_fetch_all('SELECT t.*,u.full_name,c.first_name,c.last_name,co.name AS company_name FROM tasks t LEFT JOIN users u ON u.id=t.assigned_to LEFT JOIN contacts c ON c.id=t.contact_id LEFT JOIN companies co ON co.id=t.company_id WHERE '.implode(' AND ',$where).' ORDER BY CASE WHEN t.due_at IS NULL THEN 1 ELSE 0 END,t.due_at DESC LIMIT 500',$params);$selected=$id?crm_fetch_one('SELECT * FROM tasks WHERE id=? AND deleted_at IS NULL',[$id]):null;$editing=isset($_GET['new'])||isset($_GET['edit']);
    $monthValue=preg_match('/^\d{4}-\d{2}$/',(string)($_GET['month']??''))?(string)$_GET['month']:gmdate('Y-m');
    $monthStart=new DateTimeImmutable($monthValue.'-01');$monthEnd=$monthStart->modify('last day of this month');$calendarItems=crm_fetch_all('SELECT t.*,u.full_name FROM tasks t LEFT JOIN users u ON u.id=t.assigned_to WHERE t.deleted_at IS NULL AND t.due_at>=? AND t.due_at<? ORDER BY t.due_at',[$monthStart->format('Y-m-d 00:00:00'),$monthEnd->modify('+1 day')->format('Y-m-d 00:00:00')]);
    $calendarByDay=[];foreach($calendarItems as $calendarTask){$calendarByDay[substr((string)$calendarTask['due_at'],0,10)][]=$calendarTask;}
    $monthNames=[1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
?>
    <div class="page-actions"><div class="task-toolbar"><div class="filter-chips" aria-label="Filtrer les tâches"><?php foreach(['open'=>'À faire','completed'=>'Terminées','all'=>'Toutes'] as $key=>$label): ?><a class="<?= $statusFilter===$key?'active':'' ?>" href="?view=tasks&status=<?= $key ?>&mode=<?= e($mode) ?>"><?= $label ?></a><?php endforeach; ?></div><div class="view-switch" aria-label="Mode d’affichage"><a class="<?= $mode==='list'?'active':'' ?>" href="?view=tasks&mode=list&status=<?= e($statusFilter) ?>">Liste</a><a class="<?= $mode==='calendar'?'active':'' ?>" href="?view=tasks&mode=calendar&status=<?= e($statusFilter) ?>">Calendrier</a></div></div></div>
    <?php if($mode==='calendar'): ?>
      <section class="crm-card calendar-card"><header class="calendar-header"><a href="?view=tasks&mode=calendar&month=<?= $monthStart->modify('-1 month')->format('Y-m') ?>" aria-label="Mois précédent">←</a><h2><?= e($monthNames[(int)$monthStart->format('n')].' '.$monthStart->format('Y')) ?></h2><a href="?view=tasks&mode=calendar&month=<?= $monthStart->modify('+1 month')->format('Y-m') ?>" aria-label="Mois suivant">→</a></header><div class="calendar-weekdays"><?php foreach(['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'] as $weekday): ?><span><?= $weekday ?></span><?php endforeach; ?></div><div class="calendar-grid"><?php $leading=(int)$monthStart->format('N')-1;for($blank=0;$blank<$leading;$blank++): ?><div class="calendar-day outside" aria-hidden="true"></div><?php endfor; ?><?php for($day=1;$day<=(int)$monthEnd->format('j');$day++):$date=$monthStart->setDate((int)$monthStart->format('Y'),(int)$monthStart->format('m'),$day);$dateKey=$date->format('Y-m-d'); ?><article class="calendar-day <?= $dateKey===gmdate('Y-m-d')?'today':'' ?>"><header><time datetime="<?= $dateKey ?>"><?= $day ?></time></header><div><?php foreach($calendarByDay[$dateKey]??[] as $task): ?><a href="?view=tasks&id=<?= $task['id'] ?>" class="calendar-task priority-border-<?= e($task['priority']) ?>"><strong><?= e($task['title']) ?></strong><small><?= admin_date($task['due_at'],'H:i') ?></small></a><?php endforeach; ?></div></article><?php endfor; ?></div></section>
    <?php else: ?>
      <section class="crm-card task-board"><div class="task-date-groups"><?php $lastDate='';foreach($items as $task):$date=$task['due_at']?admin_date($task['due_at'],'d F Y'):'Sans échéance';$related=$task['company_name']?:trim($task['first_name'].' '.$task['last_name']);if($date!==$lastDate): ?><h3><?= e($date) ?></h3><?php $lastDate=$date;endif; ?><a class="task-row" href="?view=tasks&id=<?= $task['id'] ?>"><span class="task-check <?= $task['status']==='completed'?'done':'' ?>">✓</span><div><strong><?= e($task['title']) ?></strong><small><?= e($related?:$task['description']) ?></small></div><?= crm_status(CRM_PRIORITIES[$task['priority']]??$task['priority'],$task['priority']) ?><span><?= e($task['full_name']??'Non attribuée') ?></span><time><?= admin_date($task['due_at'],'H:i') ?></time></a><?php endforeach; ?><?php if(!$items)empty_state('Aucune tâche','Votre liste est à jour.','empty-tasks','Nouvelle tâche','?view=tasks&new=1'); ?></div></section>
    <?php endif; ?>
    <?php if($selected||$editing):$record=$selected??['id'=>'','title'=>'','description'=>'','status'=>'open','priority'=>'normal','due_at'=>'','recurrence'=>'','assigned_to'=>$user['id'],'contact_id'=>'','company_id'=>'','lead_id'=>'','opportunity_id'=>'']; ?>
      <div class="detail-backdrop" data-close-panel></div><aside class="detail-panel"><header><div><p class="eyebrow">Tâche</p><h2><?= e($record['title'] ?: 'Nouvelle tâche') ?></h2></div><a class="close-button" href="?view=tasks">×</a></header><div class="detail-body"><form method="post" class="record-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_task"><input type="hidden" name="return_view" value="tasks"><input type="hidden" name="id" value="<?= e($record['id']) ?>"><div class="field-grid"><label class="wide"><span>Titre *</span><input name="title" required value="<?= e($record['title']) ?>"></label><label><span>Statut</span><select name="status"><?php select_map(CRM_TASK_STATUSES,$record['status']); ?></select></label><label><span>Priorité</span><select name="priority"><?php select_map(CRM_PRIORITIES,$record['priority']); ?></select></label><label><span>Échéance</span><input type="datetime-local" name="due_at" value="<?= e($record['due_at']?str_replace(' ','T',substr($record['due_at'],0,16)):'') ?>"></label><label><span>Récurrence</span><select name="recurrence"><?php select_map([''=>'Aucune','daily'=>'Quotidienne','weekly'=>'Hebdomadaire','monthly'=>'Mensuelle'],$record['recurrence']); ?></select></label><label class="wide"><span>Responsable</span><select name="assigned_to"><?php options($users,$record['assigned_to'],'id','full_name'); ?></select></label><label><span>Contact lié</span><select name="contact_id"><option value="">— Aucun —</option><?php foreach($contacts as $c): ?><option value="<?= $c['id'] ?>" <?= (string)$record['contact_id']===(string)$c['id']?'selected':'' ?>><?= e(trim($c['first_name'].' '.$c['last_name'])) ?></option><?php endforeach; ?></select></label><label><span>Entreprise liée</span><select name="company_id"><?php options($companies,$record['company_id'],'id','name'); ?></select></label><label class="wide"><span>Description</span><textarea name="description"><?= e($record['description']) ?></textarea></label></div><button class="primary-button">Enregistrer</button></form></div></aside>
    <?php endif; ?>

<?php elseif ($view === 'activities'):
    $typeFilter=(string)($_GET['type']??'');$params=[$query,$like,$like];$activityWhere='a.deleted_at IS NULL AND (?="" OR a.subject LIKE ? OR a.body LIKE ?)';if(isset(CRM_ACTIVITY_TYPES[$typeFilter])){$activityWhere.=' AND a.type=?';$params[]=$typeFilter;}
    $items=crm_fetch_all('SELECT a.*,u.full_name,c.first_name,c.last_name,co.name AS company_name,l.name AS lead_name,o.name AS opportunity_name FROM activities a LEFT JOIN users u ON u.id=a.created_by LEFT JOIN contacts c ON c.id=a.contact_id LEFT JOIN companies co ON co.id=a.company_id LEFT JOIN leads l ON l.id=a.lead_id LEFT JOIN opportunities o ON o.id=a.opportunity_id WHERE '.$activityWhere.' ORDER BY a.activity_at DESC LIMIT 500',$params);
?>
    <div class="page-actions"><div class="filter-chips" aria-label="Filtrer par type"><a class="<?= $typeFilter===''?'active':'' ?>" href="?view=activities">Toutes</a><?php foreach(CRM_ACTIVITY_TYPES as $key=>$label): ?><a class="<?= $typeFilter===$key?'active':'' ?>" href="?view=activities&type=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?></div><p><?= count($items) ?> activité(s)</p></div>
    <div class="activity-layout"><section class="crm-card timeline-card"><?php foreach($items as $a): ?><article class="timeline-item large"><i></i><div><header><strong><?= e($a['subject']) ?></strong><?= crm_status(CRM_ACTIVITY_TYPES[$a['type']]??$a['type'],$a['type']) ?></header><p><?= nl2br(e($a['body'])) ?></p><small><?= admin_date($a['activity_at']) ?> · <?= e($a['full_name']??'Système') ?><?php $related=$a['company_name']?:($a['lead_name']?:($a['opportunity_name']?:trim($a['first_name'].' '.$a['last_name'])));if($related): ?> · <?= e($related) ?><?php endif; ?></small></div></article><?php endforeach; ?><?php if(!$items)empty_state('Aucune activité','Les appels, notes, emails et réunions apparaîtront ici.','empty-activities','Ajouter une activité','#new-activity'); ?></section><aside class="crm-card" id="new-activity"><div class="section-heading"><div><p class="eyebrow">Journal</p><h2>Nouvelle activité</h2></div></div><?php render_activity_form($csrf,'dashboard',null,$users,true,$contacts,$companies); ?></aside></div>

<?php elseif ($view === 'documents'):
    $items=crm_fetch_all('SELECT d.*,u.full_name,c.first_name,c.last_name,co.name AS company_name,l.name AS lead_name,o.name AS opportunity_name FROM documents d LEFT JOIN users u ON u.id=d.uploaded_by LEFT JOIN contacts c ON c.id=d.contact_id LEFT JOIN companies co ON co.id=d.company_id LEFT JOIN leads l ON l.id=d.lead_id LEFT JOIN opportunities o ON o.id=d.opportunity_id WHERE d.deleted_at IS NULL ORDER BY d.created_at DESC LIMIT 500');
?>
    <div class="document-layout"><section class="crm-card table-card"><div class="table-summary"><strong><?= count($items) ?> document(s)</strong><span>Fichiers privés, accessibles uniquement après connexion.</span></div><div class="document-list"><?php foreach($items as $doc): ?><a href="?document=<?= $doc['id'] ?>"><span class="file-icon"><?= e(strtoupper(pathinfo($doc['original_name'],PATHINFO_EXTENSION)) ?: 'DOC') ?></span><div><strong><?= e($doc['original_name']) ?></strong><small><?= e($doc['category']) ?> · <?= e(crm_human_bytes((int)$doc['size_bytes'])) ?></small></div><span><?= e($doc['company_name']?:($doc['lead_name']?:trim($doc['first_name'].' '.$doc['last_name']))) ?></span><time><?= admin_date($doc['created_at'],'d/m/Y') ?></time><b aria-hidden="true">↓</b></a><?php endforeach; ?><?php if(!$items)empty_state('Aucun document','Ajoutez une pièce client, une proposition ou un contrat.','empty-documents','Téléverser un document','#upload-document'); ?></div></section><aside class="crm-card upload-card" id="upload-document"><div class="section-heading"><div><p class="eyebrow">Fichier privé</p><h2>Ajouter un document</h2></div></div><form method="post" enctype="multipart/form-data" class="record-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="upload_document"><input type="hidden" name="return_view" value="documents"><label class="upload-dropzone"><?= admin_icon('upload') ?><strong>Choisir un fichier</strong><small data-file-name>PDF, image, DOCX, XLSX ou CSV · 15 Mo max.</small><input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx,.csv,.txt"></label><label><span>Catégorie</span><select name="category"><?php select_map(['contract'=>'Contrat','proposal'=>'Proposition','identity'=>'Identité','accounting'=>'Comptabilité','other'=>'Autre'],'other'); ?></select></label><label><span>Contact</span><select name="contact_id"><option value="">— Aucun —</option><?php foreach($contacts as $c): ?><option value="<?= $c['id'] ?>"><?= e(trim($c['first_name'].' '.$c['last_name'])) ?></option><?php endforeach; ?></select></label><label><span>Entreprise</span><select name="company_id"><?php options($companies,'','id','name'); ?></select></label><button class="primary-button"><?= admin_icon('upload') ?>Téléverser</button></form></aside></div>

<?php elseif ($view === 'reports'):
    $dateFrom=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['from']??''))?(string)$_GET['from']:gmdate('Y-m-01');
    $dateTo=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['to']??''))?(string)$_GET['to']:gmdate('Y-m-d');
    $fromAt=$dateFrom.' 00:00:00';$toAt=(new DateTimeImmutable($dateTo))->modify('+1 day')->format('Y-m-d 00:00:00');
    $sourceRows=crm_fetch_all('SELECT source,COUNT(*) AS count FROM leads WHERE deleted_at IS NULL AND created_at>=? AND created_at<? GROUP BY source ORDER BY count DESC',[$fromAt,$toAt]);
    $conversion=(int)(crm_fetch_one('SELECT COUNT(*) AS total FROM leads WHERE status="converted" AND deleted_at IS NULL AND created_at>=? AND created_at<?',[$fromAt,$toAt])['total']??0);$leadTotal=(int)(crm_fetch_one('SELECT COUNT(*) AS total FROM leads WHERE deleted_at IS NULL AND created_at>=? AND created_at<?',[$fromAt,$toAt])['total']??0);
    $forecast=crm_fetch_all('SELECT s.name,s.color,COUNT(o.id) AS count,COALESCE(SUM(o.value),0) AS value,COALESCE(SUM(o.value*o.probability/100),0) AS weighted FROM pipeline_stages s LEFT JOIN opportunities o ON o.stage_id=s.id AND o.deleted_at IS NULL AND o.created_at>=? AND o.created_at<? GROUP BY s.id ORDER BY s.position',[$fromAt,$toAt]);
    $winLoss=crm_fetch_one('SELECT SUM(CASE WHEN s.is_won=1 THEN 1 ELSE 0 END) AS won,SUM(CASE WHEN s.is_lost=1 THEN 1 ELSE 0 END) AS lost FROM opportunities o JOIN pipeline_stages s ON s.id=o.stage_id WHERE o.deleted_at IS NULL AND o.created_at>=? AND o.created_at<?',[$fromAt,$toAt]);
?>
    <form class="report-filters crm-card" method="get"><input type="hidden" name="view" value="reports"><label><span>Du</span><input type="date" name="from" value="<?= e($dateFrom) ?>"></label><label><span>Au</span><input type="date" name="to" value="<?= e($dateTo) ?>"></label><button class="secondary-button" type="submit"><?= admin_icon('filter') ?>Appliquer</button></form>
    <section class="metrics report-metrics"><article><span>Taux de conversion</span><strong><?= $leadTotal?round($conversion/$leadTotal*100):0 ?>%</strong><small><?= $conversion ?> sur <?= $leadTotal ?> leads</small></article><article><span>Prévision pondérée</span><strong><?= admin_money(array_sum(array_column($forecast,'weighted'))) ?></strong><small>Valeur × probabilité</small></article><article><span>Contacts actifs</span><strong><?= (int)(crm_fetch_one('SELECT COUNT(*) AS total FROM contacts WHERE status!="inactive" AND deleted_at IS NULL')['total']??0) ?></strong><small>Base relationnelle</small></article><article><span>Entreprises clientes</span><strong><?= (int)(crm_fetch_one('SELECT COUNT(*) AS total FROM companies WHERE status="client" AND deleted_at IS NULL')['total']??0) ?></strong><small>Portefeuille</small></article></section>
    <section class="report-grid"><article class="crm-card"><div class="section-heading"><div><p class="eyebrow">Prévision</p><h2>Pipeline pondéré</h2></div></div><div class="report-table"><header><span>Étape</span><span>Dossiers</span><span>Valeur</span><span>Pondérée</span></header><?php foreach($forecast as $row): ?><div><span><i style="background:<?= e($row['color']) ?>"></i><?= e($row['name']) ?></span><b><?= (int)$row['count'] ?></b><span><?= admin_money($row['value']) ?></span><strong><?= admin_money($row['weighted']) ?></strong></div><?php endforeach; ?></div></article><div class="report-side"><article class="crm-card"><div class="section-heading"><div><p class="eyebrow">Acquisition</p><h2>Origine des leads</h2></div></div><div class="source-chart"><?php $sourceCounts=array_map(static fn($r)=>(int)$r['count'],$sourceRows);$maxSource=$sourceCounts?max($sourceCounts):1;foreach($sourceRows as $row): ?><div><header><span><?= e($row['source']?:'Non définie') ?></span><b><?= (int)$row['count'] ?></b></header><span><i style="width:<?= round((int)$row['count']/$maxSource*100) ?>%"></i></span></div><?php endforeach; ?><?php if(!$sourceRows)empty_state('Aucune donnée','Les sources apparaîtront après réception des premiers leads.'); ?></div></article><article class="crm-card win-loss-card"><div class="section-heading"><div><p class="eyebrow">Résultats</p><h2>Gagnées / perdues</h2></div></div><div><span><b><?= (int)($winLoss['won']??0) ?></b> gagnée(s)</span><span><b><?= (int)($winLoss['lost']??0) ?></b> perdue(s)</span></div></article></div></section>

<?php elseif ($view === 'notifications'):
    $items=crm_fetch_all('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 200',[$user['id']]);
?>
    <div class="page-actions"><p><?= $unread ?> non lue(s)</p><?php if($unread): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="mark_notifications"><input type="hidden" name="return_view" value="notifications"><button class="secondary-button">Tout marquer comme lu</button></form><?php endif; ?></div><section class="crm-card notification-list"><?php foreach($items as $note): ?><a href="<?= e($note['link']?:'?view=dashboard') ?>" class="<?= !$note['read_at']?'unread':'' ?>"><i></i><div><strong><?= e($note['title']) ?></strong><p><?= e($note['body']) ?></p><small><?= admin_date($note['created_at']) ?></small></div><b>›</b></a><?php endforeach; ?><?php if(!$items)empty_state('Aucune notification','Les nouveaux leads et rappels apparaîtront ici.'); ?></section>

<?php elseif ($view === 'settings'):
    if (($user['role'] ?? '') !== 'admin') { http_response_code(403); exit('Seul un administrateur peut accéder aux réglages.'); } $section=(string)($_GET['section']??'general');$settingsNav=['general'=>'Général','integrations'=>'Intégrations','users'=>'Équipe & rôles','tags'=>'Étiquettes','templates'=>'Modèles email','automation'=>'Automatisations','data'=>'Données & sécurité','audit'=>'Journal d’audit'];
?>
    <div class="settings-layout"><nav class="settings-nav"><?php foreach($settingsNav as $key=>$label): ?><a class="<?= $section===$key?'active':'' ?>" href="?view=settings&section=<?= $key ?>"><?= e($label) ?></a><?php endforeach; ?></nav><section class="crm-card settings-content">
      <?php if($section==='general'): ?><div class="section-heading"><div><p class="eyebrow">Configuration</p><h2>Préférences du CRM</h2></div></div><form method="post" class="record-form settings-form-wide"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_settings"><input type="hidden" name="return_view" value="settings"><div class="field-grid"><label class="wide"><span>Nom de l’espace</span><input name="crm_name" value="<?= e(crm_setting('crm_name')) ?>"></label><label><span>Devise</span><input name="currency" value="<?= e(crm_setting('currency')) ?>"></label><label><span>Fuseau horaire</span><input name="timezone" value="<?= e(crm_setting('timezone')) ?>"></label><label><span>Conservation (jours)</span><input type="number" min="30" name="retention_days" value="<?= e(crm_setting('retention_days')) ?>"></label><label><span>Attribution des leads</span><select name="lead_assignment"><?php select_map(['admin'=>'Administrateur','round_robin'=>'Tour de rôle'],'admin'); ?></select></label><label class="check-label wide"><input type="hidden" name="email_notifications" value="0"><input type="checkbox" name="email_notifications" value="1" <?= crm_setting('email_notifications')==='1'?'checked':'' ?>> Notifications par email</label></div><button class="primary-button">Enregistrer</button></form><hr><div class="section-heading"><div><p class="eyebrow">Compte</p><h2>Changer mon mot de passe</h2></div></div><form method="post" class="record-form settings-form-wide"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="change_password"><input type="hidden" name="return_view" value="settings"><label><span>Mot de passe actuel</span><input type="password" name="current_password" required></label><label><span>Nouveau mot de passe</span><input type="password" name="new_password" minlength="12" required></label><label><span>Confirmation</span><input type="password" name="confirm_password" minlength="12" required></label><button class="secondary-button">Modifier</button></form>
      <?php elseif($section==='integrations'):
        $calendarToken=(string)crm_setting('calendar_feed_token','');
        $calendarEnabled=crm_setting('calendar_feed_enabled','0')==='1';
        $emailEnabled=crm_setting('email_delivery_enabled','0')==='1';
        $calendarUrl=$calendarToken!==''?'https://aiouez.com/admin/calendar.ics.php?token='.rawurlencode($calendarToken):'';
      ?>
        <div class="section-heading"><div><p class="eyebrow">Connexions</p><h2>Email & calendrier</h2></div></div>
        <div class="integration-grid">
          <article class="integration-card"><span class="integration-icon"><?= admin_icon('email') ?></span><div><header><h3>Messagerie du cabinet</h3><?= crm_status($emailEnabled?'Active':'À configurer',$emailEnabled?'client':'inactive') ?></header><p>Envoyez ou consignez des emails depuis les fiches clients. Chaque message rejoint automatiquement la chronologie.</p></div></article>
          <article class="integration-card"><span class="integration-icon"><?= admin_icon('calendar') ?></span><div><header><h3>Agenda Google / Outlook</h3><?= crm_status($calendarEnabled&&$calendarUrl!==''?'Actif':'À configurer',$calendarEnabled&&$calendarUrl!==''?'client':'inactive') ?></header><p>Abonnez votre agenda aux tâches et rendez-vous du CRM avec un lien ICS privé et révocable.</p></div></article>
        </div>
        <form method="post" class="record-form settings-form-wide integration-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_integrations"><input type="hidden" name="return_view" value="settings">
          <fieldset><legend>Expéditeur email</legend><div class="field-grid"><label><span>Nom d’expéditeur</span><input name="email_from_name" value="<?= e(crm_setting('email_from_name','Cabinet Aiouez')) ?>" placeholder="Cabinet Aiouez"></label><label><span>Adresse d’expédition</span><input type="email" name="email_from" value="<?= e(crm_setting('email_from','')) ?>" placeholder="contact@aiouez.com"></label><label class="check-label wide"><input type="checkbox" name="email_delivery_enabled" value="1" <?= $emailEnabled?'checked':'' ?>> Autoriser l’envoi par le serveur</label></div><p class="field-help">Si l’envoi reste désactivé, les emails peuvent être consignés dans la chronologie sans être expédiés.</p></fieldset>
          <fieldset><legend>Abonnement calendrier</legend><label class="check-label"><input type="checkbox" name="calendar_feed_enabled" value="1" <?= $calendarEnabled?'checked':'' ?>> Activer le flux calendrier privé</label><?php if($calendarUrl!==''): ?><label><span>URL d’abonnement privée</span><span class="copy-field"><input value="<?= e($calendarUrl) ?>" readonly data-calendar-url><button class="secondary-button" type="button" data-copy-calendar>Copier</button></span></label><p class="field-help">Dans Google Calendar ou Outlook, choisissez « Ajouter un calendrier par URL ». Toute personne possédant ce lien peut lire les événements : renouvelez-le en cas de doute.</p><?php else: ?><p class="field-help">Générez d’abord un lien privé, puis ajoutez-le par URL dans Google Calendar ou Outlook.</p><?php endif; ?></fieldset>
          <button class="primary-button">Enregistrer les intégrations</button>
        </form>
        <form method="post" class="integration-security"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="regenerate_calendar_feed"><button class="secondary-button"><?= $calendarToken===''?'Générer le lien calendrier':'Renouveler le lien privé' ?></button></form>
      <?php elseif($section==='users'):$allUsers=crm_fetch_all('SELECT * FROM users WHERE deleted_at IS NULL ORDER BY full_name'); ?><div class="section-heading"><div><p class="eyebrow">Accès</p><h2>Équipe & rôles</h2></div></div><div class="user-list"><?php foreach($allUsers as $member): ?><div><span class="avatar"><?= e(mb_strtoupper(mb_substr($member['full_name'],0,1))) ?></span><div><strong><?= e($member['full_name']) ?></strong><small><?= e($member['email']) ?></small></div><?= crm_status(CRM_ROLES[$member['role']]??$member['role'],$member['is_active']?'client':'inactive') ?></div><?php endforeach; ?></div><details class="action-box" open><summary>Ajouter un utilisateur</summary><form method="post" class="record-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_user"><input type="hidden" name="return_view" value="settings"><label><span>Nom complet</span><input name="full_name" required></label><label><span>Identifiant</span><input name="username" required></label><label><span>Email</span><input type="email" name="email"></label><label><span>Rôle</span><select name="role"><?php select_map(CRM_ROLES,'collaborator'); ?></select></label><label><span>Mot de passe initial</span><input type="password" name="password" minlength="12" required></label><button class="primary-button">Créer l’utilisateur</button></form></details>
      <?php elseif($section==='tags'):$tags=crm_fetch_all('SELECT * FROM tags ORDER BY name'); ?><div class="section-heading"><div><p class="eyebrow">Organisation</p><h2>Étiquettes</h2></div></div><div class="tag-cloud"><?php foreach($tags as $tag): ?><span style="--tag-color:<?= e($tag['color']) ?>"><?= e($tag['name']) ?></span><?php endforeach; ?></div><form method="post" class="inline-action"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_tag"><input type="hidden" name="return_view" value="settings"><input name="name" placeholder="Nouvelle étiquette" required><input type="color" name="color" value="#0f7fa6"><button class="primary-button">Ajouter</button></form>
      <?php elseif($section==='templates'):$templates=crm_fetch_all('SELECT * FROM email_templates ORDER BY name'); ?><div class="section-heading"><div><p class="eyebrow">Communication</p><h2>Modèles email</h2></div></div><div class="template-list"><?php foreach($templates as $template): ?><article><strong><?= e($template['name']) ?></strong><span><?= e($template['subject']) ?></span><p><?= nl2br(e(mb_strimwidth($template['body'],0,180,'…'))) ?></p></article><?php endforeach; ?></div><details class="action-box"><summary>Nouveau modèle</summary><form method="post" class="record-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_template"><input type="hidden" name="return_view" value="settings"><label><span>Nom</span><input name="name" required></label><label><span>Objet</span><input name="subject" required></label><label><span>Corps</span><textarea name="body" required></textarea></label><button class="primary-button">Créer</button></form></details>
      <?php elseif($section==='automation'):$rules=crm_fetch_all('SELECT * FROM automation_rules ORDER BY created_at DESC'); ?><div class="section-heading"><div><p class="eyebrow">Flux de travail</p><h2>Automatisations</h2></div></div><div class="automation-list"><?php foreach($rules as $rule):$ruleAction=json_decode((string)$rule['actions_json'],true)?:[]; ?><article><span class="automation-state <?= $rule['is_active']?'active':'' ?>"><?= admin_icon('activities') ?></span><div><strong><?= e($rule['name']) ?></strong><small><?= e(['lead.created'=>'Nouveau lead','task.overdue'=>'Tâche en retard','opportunity.won'=>'Opportunité gagnée'][$rule['trigger_event']]??$rule['trigger_event']) ?> → <?= e(['notify_owner'=>'Notifier le responsable','create_followup'=>'Créer une relance','notify_admin'=>'Notifier l’administrateur'][$ruleAction['action']??'']??($ruleAction['action']??'Action')) ?><?php if(($ruleAction['action']??'')==='create_followup'): ?> · J+<?= (int)($ruleAction['delay_days']??2) ?><?php endif; ?></small></div><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="toggle_automation"><input type="hidden" name="id" value="<?= (int)$rule['id'] ?>"><button class="ghost-button"><?= $rule['is_active']?'Suspendre':'Activer' ?></button></form></article><?php endforeach; ?><?php if(!$rules): ?><div class="mini-empty"><strong>Aucune automatisation</strong><span>Créez une règle pour fiabiliser les relances du cabinet.</span></div><?php endif; ?></div><details class="action-box" open><summary>Nouvelle règle</summary><form method="post" class="record-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_automation"><input type="hidden" name="return_view" value="settings"><div class="field-grid"><label class="wide"><span>Nom de la règle</span><input name="name" required placeholder="Relancer chaque nouveau lead"></label><label><span>Déclencheur</span><select name="trigger_event"><?php select_map(['lead.created'=>'Nouveau lead','task.overdue'=>'Tâche en retard','opportunity.won'=>'Opportunité gagnée'],'lead.created'); ?></select></label><label><span>Action</span><select name="rule_action" data-automation-action><?php select_map(['notify_owner'=>'Notifier le responsable','create_followup'=>'Créer une relance','notify_admin'=>'Notifier l’administrateur'],'create_followup'); ?></select></label><label class="wide automation-followup-field"><span>Titre de la tâche</span><input name="task_title" value="Relancer · {{name}}" placeholder="Relancer · {{name}}"><small>Utilisez {{name}} pour insérer automatiquement le nom de la fiche.</small></label><label class="automation-followup-field"><span>Délai (jours)</span><input type="number" name="delay_days" min="0" max="365" value="2"></label><label class="automation-followup-field"><span>Priorité</span><select name="priority"><?php select_map(CRM_PRIORITIES,'normal'); ?></select></label></div><button class="primary-button">Créer la règle</button></form></details>
      <?php elseif($section==='data'): ?><div class="section-heading"><div><p class="eyebrow">Portabilité</p><h2>Import, export & conservation</h2></div></div><div class="privacy-note"><strong>Données protégées</strong><p>La base et les fichiers sont stockés hors du dossier public. Les exports nécessitent une session et une autorisation CRM.</p></div><h3>Importer des leads</h3><p>CSV séparé par des points-virgules. Colonnes reconnues : Nom, Entreprise, Email, Téléphone, Service. Limite : 5 000 lignes.</p><form method="post" enctype="multipart/form-data" class="inline-action"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="import_csv"><input type="hidden" name="return_view" value="settings"><input type="file" name="csv" accept=".csv,text/csv" required><button class="primary-button">Importer</button></form><h3>Exports</h3><div class="export-buttons"><a class="secondary-button" href="?export=leads">Leads CSV</a><a class="secondary-button" href="?export=contacts">Contacts CSV</a><a class="secondary-button" href="?export=companies">Entreprises CSV</a><a class="secondary-button" href="?export=opportunities">Opportunités CSV</a></div><p class="retention-copy">Conservation configurée : <strong><?= e(crm_setting('retention_days')) ?> jours</strong>. L’archivage reste manuel pour éviter toute suppression accidentelle.</p>
      <?php else:$logs=crm_fetch_all('SELECT a.*,u.full_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 300'); ?><div class="section-heading"><div><p class="eyebrow">Traçabilité</p><h2>Journal d’audit</h2></div></div><div class="audit-list"><?php foreach($logs as $log): ?><article><time><?= admin_date($log['created_at']) ?></time><div><strong><?= e($log['summary']) ?></strong><small><?= e($log['full_name']??'Système') ?> · <?= e($log['record_type']) ?> <?= e($log['record_id']) ?></small></div><span><?= e($log['action']) ?></span></article><?php endforeach; ?></div>
      <?php endif; ?>
    </section></div>
<?php endif; ?>
  </main>
</div>
<dialog class="confirm-dialog" id="confirm-dialog" aria-labelledby="confirm-title" aria-describedby="confirm-copy">
  <form method="dialog">
    <span class="confirm-icon"><?= admin_icon('empty-tasks') ?></span>
    <h2 id="confirm-title">Confirmer l’action</h2>
    <p id="confirm-copy">Cette action modifiera définitivement l’état de cet élément.</p>
    <div><button class="secondary-button" value="cancel">Annuler</button><button class="danger-button" value="confirm">Confirmer</button></div>
  </form>
</dialog>
<script>window.AIOUEZ_I18N=<?= json_encode(['locale' => $locale, 'dir' => admin_direction($locale), 'catalog' => admin_catalog($locale)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="/admin/admin-i18n.js?v=<?= e((string)filemtime(__DIR__ . '/admin-i18n.js')) ?>" defer></script>
<script src="/admin/admin.js?v=<?= e((string)filemtime(__DIR__ . '/admin.js')) ?>" defer></script>
</body>
</html>
<?php

function render_activity_form(string $csrf, string $returnType, ?int $returnId, array $users, bool $global = false, array $contacts = [], array $companies = []): void
{
    ?>
    <form method="post" class="record-form compact">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_activity">
      <input type="hidden" name="return_view" value="<?= e($returnType === 'dashboard' ? 'activities' : $returnType . 's') ?>">
      <input type="hidden" name="return_type" value="<?= e($returnType) ?>"><input type="hidden" name="return_id" value="<?= e($returnId) ?>">
      <?php if ($returnId && in_array($returnType, ['lead','contact','company','opportunity'], true)): ?><input type="hidden" name="<?= e($returnType) ?>_id" value="<?= $returnId ?>"><?php endif; ?>
      <label><span>Type</span><select name="type"><?php select_map(CRM_ACTIVITY_TYPES, 'note'); ?></select></label>
      <label><span>Objet *</span><input name="subject" required></label>
      <label><span>Date</span><input type="datetime-local" name="activity_at" value="<?= e(gmdate('Y-m-d\TH:i')) ?>"></label>
      <label><span>Responsable</span><select name="assigned_to"><?php options($users, '', 'id', 'full_name'); ?></select></label>
      <?php if ($global): ?><label><span>Contact</span><select name="contact_id"><option value="">— Aucun —</option><?php foreach ($contacts as $contact): ?><option value="<?= $contact['id'] ?>"><?= e(trim($contact['first_name'].' '.$contact['last_name'])) ?></option><?php endforeach; ?></select></label><label><span>Entreprise</span><select name="company_id"><?php options($companies, '', 'id', 'name'); ?></select></label><?php endif; ?>
      <label><span>Compte rendu</span><textarea name="body"></textarea></label><button class="primary-button">Ajouter</button>
    </form>
    <?php
}

function render_related(array $record, string $type): void
{
    $items = crm_timeline($type, (int)$record['id']);
    echo '<section class="timeline-section unified-timeline"><div class="subsection-heading"><div><p class="eyebrow">Vue à 360°</p><h3>Chronologie unifiée</h3></div><span>' . count($items) . ' élément(s)</span></div>';
    foreach ($items as $item) {
        $statusLabels = CRM_TASK_STATUSES + ['logged' => 'Consigné', 'sent' => 'Envoyé', 'failed' => 'Échec'];
        $status = $item['status'] !== ''
            ? crm_status($statusLabels[(string)$item['status']] ?? ucfirst((string)$item['status']), (string)$item['status'])
            : '';
        echo '<article class="timeline-item timeline-' . e($item['kind']) . '"><span class="timeline-kind">' . admin_icon(
            in_array($item['kind'], ['email','meeting','task','document'], true)
                ? ($item['kind'] === 'task' ? 'tasks' : ($item['kind'] === 'meeting' ? 'calendar' : $item['kind']))
                : 'activities'
        ) . '</span><div><header><strong>' . e($item['title']) . '</strong>' . $status . '</header>';
        if ($item['body'] !== '') {
            echo '<p>' . nl2br(e($item['body'])) . '</p>';
        }
        echo '<small>' . e($item['meta']) . ' · ' . admin_date($item['occurred_at']) . ' · ' . e($item['actor']) . '</small></div></article>';
    }
    if ($items === []) {
        echo '<div class="mini-empty"><strong>Aucun historique</strong><span>Emails, activités, tâches et documents apparaîtront ici.</span></div>';
    }
    echo '</section>';
}

function render_email_composer(string $csrf, string $type, array $record): void
{
    $recordId = (int)$record['id'];
    $recipient = (string)($record['email'] ?? '');
    if ($type === 'opportunity') {
        $related = crm_fetch_one(
            'SELECT c.email AS contact_email,co.email AS company_email FROM opportunities o
             LEFT JOIN contacts c ON c.id=o.contact_id LEFT JOIN companies co ON co.id=o.company_id WHERE o.id=?',
            [$recordId]
        );
        $recipient = (string)(($related['contact_email'] ?? '') ?: ($related['company_email'] ?? ''));
    }
    $deliveryEnabled = crm_setting('email_delivery_enabled', '0') === '1';
    $relationField = $type . '_id';
    ?>
    <details class="action-box communication-composer">
      <summary><span><?= admin_icon('email') ?>Rédiger un email</span><small><?= $deliveryEnabled ? 'Envoi et journalisation' : 'Journalisation disponible' ?></small></summary>
      <form method="post" class="record-form">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_communication">
        <input type="hidden" name="return_type" value="<?= e($type) ?>"><input type="hidden" name="return_id" value="<?= $recordId ?>">
        <input type="hidden" name="<?= e($relationField) ?>" value="<?= $recordId ?>">
        <label><span>Destinataire *</span><input type="email" name="recipient" value="<?= e($recipient) ?>" placeholder="client@entreprise.dz" required></label>
        <label><span>Objet *</span><input name="subject" required placeholder="Objet du message"></label>
        <label><span>Message *</span><textarea name="body" required placeholder="Rédigez un message clair et concis…"></textarea></label>
        <?php if($deliveryEnabled): ?><label class="check-label"><input type="checkbox" name="send_now" value="1" checked> Envoyer maintenant depuis <?= e(crm_setting('email_from','le cabinet')) ?></label><?php else: ?><p class="form-note">L’envoi serveur est désactivé. Le message sera uniquement consigné. <a href="?view=settings&section=integrations">Configurer l’envoi</a></p><?php endif; ?>
        <button class="primary-button"><?= admin_icon('email') ?><?= $deliveryEnabled ? 'Envoyer et enregistrer' : 'Enregistrer dans la chronologie' ?></button>
      </form>
    </details>
    <?php
}

function render_onboarding(string $csrf, int $companyId, array $users): void
{
    $checklistId = crm_ensure_company_onboarding($companyId, (int)(crm_current_user()['id'] ?? 0));
    $items = crm_fetch_all(
        'SELECT i.*,u.full_name FROM onboarding_items i LEFT JOIN users u ON u.id=i.assigned_to
         WHERE i.checklist_id=? ORDER BY i.position,i.id',
        [$checklistId]
    );
    $total = count($items);
    $completed = count(array_filter($items, static fn(array $item): bool => (int)$item['is_completed'] === 1));
    $percent = $total > 0 ? (int)round($completed / $total * 100) : 0;
    ?>
    <section class="onboarding-section">
      <div class="subsection-heading"><div><p class="eyebrow">Dossier client</p><h3>Onboarding comptable</h3></div><strong><?= $percent ?>%</strong></div>
      <div class="onboarding-progress" role="progressbar" aria-label="Progression de l’onboarding" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percent ?>"><i style="width:<?= $percent ?>%"></i></div>
      <p class="onboarding-summary"><?= $completed ?> étape(s) terminée(s) sur <?= $total ?></p>
      <div class="onboarding-list">
        <?php foreach($items as $item): ?><form method="post" class="<?= $item['is_completed']?'completed':'' ?>"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="toggle_onboarding_item"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><button type="submit" aria-label="<?= $item['is_completed']?'Rouvrir':'Terminer' ?> : <?= e($item['title']) ?>"><span class="task-check <?= $item['is_completed']?'done':'' ?>">✓</span><span><strong><?= e($item['title']) ?></strong><small><?= e(ucfirst($item['category'])) ?> · <?= e($item['full_name']??'Non attribué') ?><?= $item['due_at']?' · '.admin_date($item['due_at'],'d/m/Y'):'' ?></small></span></button></form><?php endforeach; ?>
      </div>
      <details class="onboarding-add"><summary>Ajouter une étape</summary><form method="post" class="record-form compact"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_onboarding_item"><input type="hidden" name="company_id" value="<?= $companyId ?>"><label><span>Étape *</span><input name="title" required placeholder="Document ou action attendue"></label><div class="field-grid"><label><span>Catégorie</span><select name="category"><?php select_map(['administratif'=>'Administratif','juridique'=>'Juridique','fiscal'=>'Fiscal','comptabilite'=>'Comptabilité','mission'=>'Mission','organisation'=>'Organisation'],'administratif'); ?></select></label><label><span>Échéance</span><input type="date" name="due_at"></label><label class="wide"><span>Responsable</span><select name="assigned_to"><?php options($users,'','id','full_name'); ?></select></label></div><button class="secondary-button">Ajouter l’étape</button></form></details>
    </section>
    <?php
}

function render_tags(string $csrf, string $type, int $recordId): void
{
    $tags = crm_fetch_all('SELECT * FROM tags ORDER BY name');
    if ($tags === []) {
        return;
    }
    $assigned = array_map(
        'intval',
        array_column(crm_fetch_all('SELECT tag_id FROM record_tags WHERE record_type=? AND record_id=?', [$type, $recordId]), 'tag_id')
    );
    echo '<details class="action-box"><summary>Étiquettes</summary><form method="post" class="record-form compact">';
    echo '<input type="hidden" name="csrf" value="' . e($csrf) . '"><input type="hidden" name="action" value="assign_tags">';
    echo '<input type="hidden" name="return_view" value="' . e(admin_record_link($type, $recordId)) . '">';
    echo '<input type="hidden" name="record_type" value="' . e($type) . '"><input type="hidden" name="record_id" value="' . $recordId . '">';
    echo '<div class="tag-selector">';
    foreach ($tags as $tag) {
        echo '<label style="--tag-color:' . e($tag['color']) . '"><input type="checkbox" name="tag_ids[]" value="' . (int)$tag['id'] . '"' . (in_array((int)$tag['id'], $assigned, true) ? ' checked' : '') . '><span>' . e($tag['name']) . '</span></label>';
    }
    echo '</div><button class="secondary-button">Mettre à jour</button></form></details>';
}
