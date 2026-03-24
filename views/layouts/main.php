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
$liveFeatureEnabled = (bool) ((require dirname(__DIR__, 2) . '/app/config/live.php')['enabled'] ?? false);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WorkEddy | <?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" type="image/png" href="/assets/img/favicon.ico" />
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
      <img src="/assets/img/logo.png" alt="WorkEddy logo" class="app-brand-logo" />
      <span class="app-brand-text">WorkEddy</span>
    </a>

    <!-- Menu -->
    <ul class="menu-vertical list-unstyled mb-0">

      <li class="menu-header">Core</li>

      <?php
      $coreNav = [
        'dashboard' => ['/dashboard',        'bi-grid-1x2',  'Dashboard'],
        'tasks'     => ['/tasks',            'bi-list-task', 'Tasks'],
        'leading-indicators' => ['/leading-indicators/check-in', 'bi-heart-pulse', 'Wellbeing Check-in'],
        'copilot'   => ['/copilot',          'bi-stars',     'Copilot'],
        'scans'     => ['',                  'bi-upc-scan',  'Scans'],
      ];
      $scansActive = in_array($activePage, ['scans', 'scans-video', 'scans-live', 'scans-compare']);
      foreach ($coreNav as $key => [$href, $icon, $label]):
        if ($key === 'scans'): ?>
        <li class="menu-item" x-data="{ open: <?= $scansActive ? 'true' : 'false' ?> }">
          <button class="menu-link menu-toggle" @click="open = !open"
                  :class="{ active: open }">
            <i class="menu-icon bi bi-upc-scan"></i>
            <span>Scans</span>
            <i class="menu-chevron bi bi-chevron-right" :class="{ rotated: open }"></i>
          </button>
          <ul class="menu-sub list-unstyled" x-show="open">
            <li class="menu-sub-item">
              <a href="/scans/new-manual"
                 class="menu-sub-link<?= $activePage === 'scans' ? ' active' : '' ?>"
                 x-show="$store.auth.role === 'super_admin' || $store.auth.role === 'admin' || $store.auth.role === 'supervisor' || $store.auth.role === 'worker'"
                 x-cloak>
                <i class="bi bi-keyboard"></i> Manual Scan
              </a>
            </li>
            <li class="menu-sub-item">
              <a href="/scans/new-video"
                 class="menu-sub-link<?= $activePage === 'scans-video' ? ' active' : '' ?>"
                 x-show="$store.auth.role === 'super_admin' || $store.auth.role === 'admin' || $store.auth.role === 'supervisor' || $store.auth.role === 'worker'"
                 x-cloak>
                <i class="bi bi-camera-video"></i> Video Scan
              </a>
            </li>
            <?php if ($liveFeatureEnabled): ?>
            <li class="menu-sub-item"
                x-show="$store.auth.role === 'super_admin' || $store.auth.role === 'admin' || $store.auth.role === 'supervisor' || $store.auth.role === 'observer'"
                x-cloak>
              <a href="/scans/live-capture"
                 class="menu-sub-link<?= $activePage === 'scans-live' ? ' active' : '' ?>">
                <i class="bi bi-broadcast"></i> Live Capture
              </a>
            </li>
            <?php endif; ?>
            <li class="menu-sub-item">
              <a href="/scans/compare"
                 class="menu-sub-link<?= $activePage === 'scans-compare' ? ' active' : '' ?>">
                <i class="bi bi-arrow-left-right"></i> Compare Scans
              </a>
            </li>
          </ul>
        </li>
      <?php elseif ($key === 'copilot'):
        $cls = ($activePage === $key) ? ' active' : '';
      ?>
        <li class="menu-item"
            x-show="$store.auth.role === 'admin' || $store.auth.role === 'supervisor' || $store.auth.role === 'observer' || $store.auth.role === 'super_admin'"
            x-cloak>
          <a class="menu-link<?= $cls ?>" href="<?= $href ?>">
            <i class="menu-icon bi <?= $icon ?>"></i>
            <span><?= $label ?></span>
          </a>
        </li>
      <?php else:
        $cls = ($activePage === $key) ? ' active' : '';
      ?>
        <li class="menu-item">
          <a class="menu-link<?= $cls ?>" href="<?= $href ?>">
            <i class="menu-icon bi <?= $icon ?>"></i>
            <span><?= $label ?></span>
          </a>
        </li>
      <?php endif; endforeach; ?>

      <!-- Organisation section (supervisor +) -->
      <li class="menu-header"
          x-show="$store.auth.role === 'admin' || $store.auth.role === 'supervisor' || $store.auth.role === 'super_admin'"
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
            x-show="$store.auth.role === 'admin' || $store.auth.role === 'supervisor' || $store.auth.role === 'super_admin'"
            x-cloak>
          <a class="menu-link<?= $cls ?>" href="<?= $href ?>">
            <i class="menu-icon bi <?= $icon ?>"></i>
            <span><?= $label ?></span>
          </a>
        </li>
      <?php endforeach; ?>

      <!-- Admin section -->
      <li class="menu-header"
          x-show="$store.auth.role === 'super_admin'"
          x-cloak>Administration</li>

      <?php
      $adminNav = [
        'admin-dashboard' => ['/admin/dashboard',     'bi-speedometer2',  'System'],
        'admin-orgs'      => ['/admin/organizations', 'bi-building',      'Organizations'],
        'admin-users'     => ['/admin/users',         'bi-people-fill',   'All Users'],
        'admin-plans'     => ['/admin/plans',         'bi-tags',          'Plans'],
        'admin-feedback'  => ['/admin/feedback',      'bi-chat-square-text', 'Feedback'],
        'admin-settings'  => ['/admin/settings',      'bi-sliders',       'Settings'],
       
      ];
      foreach ($adminNav as $key => [$href, $icon, $label]):
        $cls = ($activePage === $key) ? ' active' : '';
      ?>
        <li class="menu-item"
            x-show="$store.auth.role === 'super_admin'"
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

        <!-- Notifications dropdown -->
        <div class="dropdown" x-data="notificationsDropdown" @click.outside="open = false">
          <button class="navbar-icon-btn border-0 position-relative" @click="toggle()" title="Notifications">
            <i class="bi bi-bell"></i>
            <span class="notif-badge" x-show="unreadCount > 0" x-text="unreadCount > 9 ? '9+' : unreadCount" x-cloak></span>
          </button>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-notifications shadow-lg"
               :class="{ show: open }">
            <div class="notif-header">
              <span class="fw-semibold">Notifications</span>
              <button class="btn btn-link btn-sm text-muted p-0" @click="markAllRead()" :disabled="markingAllRead"
                      x-show="unreadCount > 0" style="font-size:.8rem;text-decoration:none">
                <span class="spinner-border spinner-border-sm me-1" x-show="markingAllRead" x-cloak></span>
                <span x-text="markingAllRead ? 'Marking…' : 'Mark all read'"></span>
              </button>
            </div>
            <div class="notif-body">
              <template x-if="loading">
                <div class="text-center py-4 text-muted"><i class="bi bi-arrow-repeat spin"></i> Loading…</div>
              </template>
              <template x-if="!loading && items.length === 0">
                <div class="text-center py-4 text-muted">
                  <i class="bi bi-bell-slash d-block mb-1" style="font-size:1.5rem"></i>
                  <span class="small">No notifications yet</span>
                </div>
              </template>
              <template x-for="n in items" :key="n.id">
                <a class="notif-item" :class="{ unread: !parseInt(n.is_read) }"
                   :href="n.link || '#'" @click="markRead(n)">
                  <div class="notif-icon" :class="notifIconClass(n.type)">
                    <i class="bi" :class="notifIcon(n.type)"></i>
                  </div>
                  <div class="notif-content">
                    <p class="notif-title" x-text="n.title"></p>
                    <p class="notif-body-text" x-show="n.body" x-text="n.body"></p>
                    <span class="notif-time" x-text="timeAgo(n.created_at)"></span>
                  </div>
                </a>
              </template>
            </div>
          </div>
        </div>

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
            <li><a class="dropdown-item" href="/profile">
              <i class="bi bi-person me-2 text-muted"></i>My Profile</a></li>
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
    'scans'        => ['',                  'bi-upc-scan',  'Scans'],
    'profile'      => ['/profile',          'bi-person',    'Profile'],
  ];
  foreach ($mobileNav as $key => [$href, $icon, $label]):
    if ($key === 'scans'):
      $scanActive = in_array($activePage, ['scans', 'scans-video', 'scans-live']);
  ?>
    <div class="bottom-nav-item-wrap" x-data="{ open: false }">
      <div class="scan-sheet-backdrop" x-show="open" @click="open = false" x-cloak></div>
      <div class="scan-sheet" x-show="open" x-cloak>
        <a href="/scans/new-manual" class="scan-sheet-item">
          <i class="bi bi-keyboard"></i> Manual Scan
        </a>
        <a href="/scans/new-video" class="scan-sheet-item">
          <i class="bi bi-camera-video"></i> Video Scan
        </a>
        <?php if ($liveFeatureEnabled): ?>
        <a href="/scans/live-capture" class="scan-sheet-item"
           x-show="$store.auth.role === 'admin' || $store.auth.role === 'supervisor' || $store.auth.role === 'observer' || $store.auth.role === 'super_admin'"
           x-cloak>
          <i class="bi bi-broadcast"></i> Live Capture
        </a>
        <?php endif; ?>
      </div>
      <button class="bottom-nav-item<?= $scanActive ? ' active' : '' ?> border-0 bg-transparent p-0"
              @click.stop="open = !open">
        <i class="bi bi-upc-scan"></i>
        <span>Scans</span>
      </button>
    </div>
  <?php else:
    $cls = ($activePage === $key) ? ' active' : '';
  ?>
    <a href="<?= $href ?>" class="bottom-nav-item<?= $cls ?>">
      <i class="bi <?= $icon ?>"></i>
      <span><?= $label ?></span>
    </a>
  <?php endif; endforeach; ?>
</nav>

<!-- Toast container -->
<div class="toast-container-fixed" id="toastContainer"
     aria-live="polite" aria-atomic="true"></div>

<!-- System Dialog (appAlert / appConfirm) -->
<div class="modal fade" id="systemUxDialogModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title d-flex align-items-center gap-2">
          <i data-dialog-icon class="bi bi-info-circle text-primary"></i>
          <span data-dialog-title>Notice</span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" data-dialog-body></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dialog-cancel>Cancel</button>
        <button type="button" class="btn btn-primary" data-dialog-ok>OK</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<?php require __DIR__ . '/partials/app-scripts.php'; ?>
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
