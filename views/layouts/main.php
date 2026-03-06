<?php
/**
 * Main application layout – Sneat sidebar + fixed top navbar.
 *
 * Variables:
 *   $pageTitle   string  – browser <title>
 *   $activePage  string  – nav key for active state
 *   $content     string  – rendered page HTML
 */
$pageTitle  = $pageTitle  ?? 'WorkEddy';
$activePage = $activePage ?? '';
$content    = $content    ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WorkEddy | <?= htmlspecialchars($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="/assets/css/core.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>

<div class="layout-wrapper">

  <!-- ═══════════════ SIDEBAR ═══════════════ -->
  <aside class="layout-sidebar" id="layoutSidebar" x-data>

    <!-- Brand -->
    <a class="app-brand" href="/dashboard">
      <div class="app-brand-logo"><i class="bi bi-activity"></i></div>
      <span class="app-brand-text">WorkEddy</span>
    </a>

    <!-- Menu -->
    <ul class="menu-vertical list-unstyled mb-0">

      <li class="menu-header">Core</li>

      <?php
      $coreNav = [
        'dashboard' => ['/dashboard',        'bi-grid-1x2',  'Dashboard'],
        'tasks'     => ['/tasks',            'bi-list-task', 'Tasks'],
        'scans'     => ['/scans/new-manual', 'bi-upc-scan',  'Scans'],
        'observer'  => ['/observer-rating',  'bi-eye',       'Observer'],
      ];
      foreach ($coreNav as $key => [$href, $icon, $label]):
        $cls = ($activePage === $key) ? ' active' : '';
      ?>
        <li class="menu-item">
          <a class="menu-link<?= $cls ?>" href="<?= $href ?>">
            <i class="menu-icon bi <?= $icon ?>"></i>
            <span><?= $label ?></span>
          </a>
        </li>
      <?php endforeach; ?>

      <!-- Organisation section (supervisor +) -->
      <li class="menu-header"
          x-show="$store.auth.role === 'admin' || $store.auth.role === 'supervisor'"
          x-cloak>Organization</li>

      <?php
      $orgNav = [
        'org-settings' => ['/org/settings', 'bi-gear',    'Settings'],
        'org-users'    => ['/org/users',    'bi-people',  'Team'],
        'org-billing'  => ['/org/billing',  'bi-receipt', 'Billing'],
      ];
      foreach ($orgNav as $key => [$href, $icon, $label]):
        $cls = ($activePage === $key) ? ' active' : '';
      ?>
        <li class="menu-item"
            x-show="$store.auth.role === 'admin' || $store.auth.role === 'supervisor'"
            x-cloak>
          <a class="menu-link<?= $cls ?>" href="<?= $href ?>">
            <i class="menu-icon bi <?= $icon ?>"></i>
            <span><?= $label ?></span>
          </a>
        </li>
      <?php endforeach; ?>

      <!-- Admin section -->
      <li class="menu-header"
          x-show="$store.auth.role === 'admin'"
          x-cloak>Administration</li>

      <?php
      $adminNav = [
        'admin-dashboard' => ['/admin/dashboard',     'bi-speedometer2',  'System'],
        'admin-orgs'      => ['/admin/organizations', 'bi-building',      'Organizations'],
        'admin-users'     => ['/admin/users',         'bi-people-fill',   'All Users'],
        'admin-plans'     => ['/admin/plans',         'bi-tags',          'Plans'],
      ];
      foreach ($adminNav as $key => [$href, $icon, $label]):
        $cls = ($activePage === $key) ? ' active' : '';
      ?>
        <li class="menu-item"
            x-show="$store.auth.role === 'admin'"
            x-cloak>
          <a class="menu-link<?= $cls ?>" href="<?= $href ?>">
            <i class="menu-icon bi <?= $icon ?>"></i>
            <span><?= $label ?></span>
          </a>
        </li>
      <?php endforeach; ?>

    </ul><!-- /.menu-vertical -->

  </aside><!-- /.layout-sidebar -->

  <!-- Mobile overlay -->
  <div class="layout-overlay" id="layoutOverlay"></div>

  <!-- ═══════════════ MAIN PAGE ═══════════════ -->
  <div class="layout-page">

    <!-- Top Navbar -->
    <nav class="layout-navbar" x-data>

      <!-- Hamburger (mobile) -->
      <button class="navbar-icon-btn d-lg-none border-0 bg-transparent me-2"
              id="sidebarToggle" aria-label="Toggle menu">
        <i class="bi bi-list fs-5"></i>
      </button>

      <!-- Search -->
      <div class="navbar-search d-none d-md-block">
        <i class="bi bi-search navbar-search-icon"></i>
        <input class="form-control" type="search" placeholder="Search tasks, scans…">
      </div>

      <!-- Right actions -->
      <div class="navbar-actions">

        <span class="plan-chip d-none d-sm-inline" id="planBadge">—</span>

        <a class="navbar-icon-btn" href="#" title="Notifications">
          <i class="bi bi-bell"></i>
        </a>

        <!-- User dropdown -->
        <div class="dropdown">
          <button class="user-avatar border-0 p-0" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="userInitials">U</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-user shadow-lg">
            <li class="px-3 pt-2 pb-1">
              <p class="fw-semibold mb-0 small" id="ddUserName">—</p>
              <p class="mb-1 text-capitalize text-muted text-xs" id="ddUserRole">—</p>
            </li>
            <li><hr class="dropdown-divider my-1"></li>
            <li><a class="dropdown-item" href="/org/settings">
              <i class="bi bi-gear me-2 text-muted"></i>Settings</a></li>
            <li><a class="dropdown-item" href="/org/billing">
              <i class="bi bi-credit-card me-2 text-muted"></i>Billing</a></li>
            <li><hr class="dropdown-divider my-1"></li>
            <li>
              <button class="dropdown-item text-danger" onclick="logout()">
                <i class="bi bi-box-arrow-right me-2"></i>Sign out
              </button>
            </li>
          </ul>
        </div><!-- /user dropdown -->

      </div><!-- /.navbar-actions -->
    </nav><!-- /.layout-navbar -->

    <!-- Page content -->
    <div class="content-wrapper">
      <?= $content ?>
    </div>

  </div><!-- /.layout-page -->

</div><!-- /.layout-wrapper -->

<!-- Mobile bottom nav -->
<nav class="bottom-nav d-lg-none">
  <?php
  $mobileNav = [
    'dashboard'    => ['/dashboard',        'bi-grid-1x2',  'Home'],
    'tasks'        => ['/tasks',            'bi-list-task', 'Tasks'],
    'scans'        => ['/scans/new-manual', 'bi-upc-scan',  'Scans'],
    'org-settings' => ['/org/settings',     'bi-gear',      'Settings'],
  ];
  foreach ($mobileNav as $key => [$href, $icon, $label]):
    $cls = ($activePage === $key) ? ' active' : '';
  ?>
    <a href="<?= $href ?>" class="bottom-nav-item<?= $cls ?>">
      <i class="bi <?= $icon ?>"></i>
      <span><?= $label ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<!-- Toast container -->
<div class="toast-container-fixed" id="toastContainer"
     aria-live="polite" aria-atomic="true"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="/assets/js/app.js"></script>
<script>
/* Sidebar toggle (mobile) */
(function () {
  const sidebar = document.getElementById('layoutSidebar');
  const overlay = document.getElementById('layoutOverlay');
  const toggle  = document.getElementById('sidebarToggle');
  function closeSidebar() {
    sidebar.classList.remove('sidebar-open');
    overlay.classList.remove('show');
  }
  if (toggle)  toggle.addEventListener('click', function () {
    sidebar.classList.toggle('sidebar-open');
    overlay.classList.toggle('show');
  });
  if (overlay) overlay.addEventListener('click', closeSidebar);
}());

/* Populate navbar from JWT */
(function () {
  try {
    var t = localStorage.getItem('we_token');
    if (!t) return;
    var p = JSON.parse(atob(t.split('.')[1]));
    var initials = (p.name || '?').split(' ').map(function(w){ return w[0]; }).join('').toUpperCase().slice(0, 2);
    function $(id){ return document.getElementById(id); }
    if ($('userInitials')) $('userInitials').textContent = initials;
    if ($('ddUserName'))   $('ddUserName').textContent   = p.name || '—';
    if ($('ddUserRole'))   $('ddUserRole').textContent   = (p.role || '').replace(/\b\w/g, function(c){ return c.toUpperCase(); });
    if ($('planBadge') && p.plan) $('planBadge').textContent = p.plan;
  } catch (_) {}
}());
</script>
</body>
</html>
