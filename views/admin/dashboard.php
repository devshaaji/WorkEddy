<?php
$pageTitle  = 'System Dashboard';
$activePage = 'admin-dashboard';
ob_start();
?>
<div x-data="adminDashboardPage">

  <?php
  $headerTitle = 'System Dashboard';
  $headerBreadcrumb = 'Admin / System';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <!-- Loading -->
  <div class="text-center py-5" x-show="loading" x-cloak>
    <div class="spinner-border text-primary"></div>
  </div>

  <div class="alert alert-danger" x-show="error && !loading" x-cloak x-text="error"></div>

  <div x-show="!loading && !error" x-cloak>

    <!-- KPI Cards -->
    <div class="row g-4 mb-4">
      <div class="col-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon stat-icon-primary"><i class="bi bi-building"></i></div>
          <div>
            <div class="stat-value" x-text="stats.total_organizations ?? 0">0</div>
            <div class="stat-label">Organizations</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon stat-icon-info"><i class="bi bi-people"></i></div>
          <div>
            <div class="stat-value" x-text="stats.total_users ?? 0">0</div>
            <div class="stat-label">Total Users</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon stat-icon-success"><i class="bi bi-activity"></i></div>
          <div>
            <div class="stat-value" x-text="stats.total_scans ?? 0">0</div>
            <div class="stat-label">Total Scans</div>
            <div class="stat-trend up" x-show="stats.scans_this_month > 0" x-cloak>
              <i class="bi bi-arrow-up-short"></i>
              <span x-text="stats.scans_this_month"></span> this month
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon stat-icon-warning"><i class="bi bi-currency-dollar"></i></div>
          <div>
            <div class="stat-value">$<span x-text="fmtCurrency(stats.monthly_revenue)">0</span></div>
            <div class="stat-label">Monthly Revenue</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Risk Breakdown + Activity -->
    <div class="row g-4 mb-4">
      <div class="col-md-5">
        <div class="card h-100">
          <div class="card-header"><h6 class="mb-0 fw-semibold">Risk Breakdown</h6></div>
          <div class="card-body">
            <div class="d-flex justify-content-around text-center"
                 x-show="stats.risk_breakdown && stats.risk_breakdown.length > 0" x-cloak>
              <template x-for="r in stats.risk_breakdown" :key="r.risk_category">
                <div>
                  <div class="mb-2">
                    <span class="badge px-3 py-2"
                          :class="r.risk_category === 'high' ? 'badge-soft-danger'
                                : r.risk_category === 'moderate' ? 'badge-soft-warning'
                                : 'badge-soft-success'"
                          x-text="r.risk_category"></span>
                  </div>
                  <div class="fs-4 fw-bold" x-text="r.cnt">0</div>
                  <div class="text-muted text-xs">scans</div>
                </div>
              </template>
            </div>
            <div class="empty-state py-3"
                 x-show="!stats.risk_breakdown || stats.risk_breakdown.length === 0" x-cloak>
              <p class="mb-0 text-muted">No scan data yet.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-7">
        <div class="card h-100">
          <div class="card-header"><h6 class="mb-0 fw-semibold">Activity Overview</h6></div>
          <div class="card-body">
            <div class="row g-3 text-center">
              <div class="col-6">
                <p class="text-muted text-sm mb-1">Scans This Month</p>
                <h3 class="fw-bold mb-0 text-primary" x-text="stats.scans_this_month ?? 0">0</h3>
              </div>
              <div class="col-6">
                <p class="text-muted text-sm mb-1">Avg per Org</p>
                <h3 class="fw-bold mb-0"
                    x-text="stats.total_organizations
                              ? Math.round((stats.total_scans || 0) / stats.total_organizations)
                              : 0">0</h3>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent tables -->
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">Recent Organizations</h6>
            <a href="/admin/organizations" class="btn btn-sm btn-link p-0 text-decoration-none text-primary">View all &rarr;</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr><th>Organization</th><th>Status</th><th>Joined</th></tr>
              </thead>
              <tbody>
                <template x-for="org in (stats.recent_organizations || [])" :key="org.id">
                  <tr>
                    <td class="fw-medium" x-text="org.name"></td>
                    <td>
                      <span class="badge"
                            :class="org.status === 'active' ? 'badge-soft-success' : 'badge-soft-secondary'"
                            x-text="org.status"></span>
                    </td>
                    <td class="text-muted" x-text="fmtDate(org.created_at)"></td>
                  </tr>
                </template>
                <tr x-show="!stats.recent_organizations || stats.recent_organizations.length === 0" x-cloak>
                  <td colspan="3" class="text-center text-muted py-4">No organizations yet.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">Recent Users</h6>
            <a href="/admin/users" class="btn btn-sm btn-link p-0 text-decoration-none text-primary">View all &rarr;</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr><th>Name</th><th>Organization</th><th>Role</th></tr>
              </thead>
              <tbody>
                <template x-for="u in (stats.recent_users || [])" :key="u.id">
                  <tr>
                    <td class="fw-medium" x-text="u.name"></td>
                    <td class="text-muted text-sm" x-text="u.org_name"></td>
                    <td><span class="badge badge-soft-primary text-capitalize" x-text="u.role"></span></td>
                  </tr>
                </template>
                <tr x-show="!stats.recent_users || stats.recent_users.length === 0" x-cloak>
                  <td colspan="3" class="text-center text-muted py-4">No users yet.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
