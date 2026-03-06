<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
ob_start();
?>
<div x-data="dashboardPage">

  <div class="page-header">
    <div>
      <h1 class="page-title">Dashboard</h1>
      <p class="page-breadcrumb">Home / Dashboard</p>
    </div>
    <div class="d-flex gap-2">
      <a href="/scans/new-video" class="btn btn-outline-primary">
        <i class="bi bi-camera-video me-1"></i>Video Scan
      </a>
      <a href="/scans/new-manual" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Scan
      </a>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="row g-4 mb-4">
    <div class="col-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-icon stat-icon-primary"><i class="bi bi-activity"></i></div>
        <div>
          <div class="stat-value" x-text="totalScans">—</div>
          <div class="stat-label">Total Scans</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-icon stat-icon-danger"><i class="bi bi-exclamation-triangle"></i></div>
        <div>
          <div class="stat-value" x-text="highRisk">—</div>
          <div class="stat-label">High Risk</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-icon stat-icon-warning"><i class="bi bi-exclamation-circle"></i></div>
        <div>
          <div class="stat-value" x-text="moderateRisk">—</div>
          <div class="stat-label">Moderate Risk</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-icon stat-icon-info"><i class="bi bi-bar-chart-line"></i></div>
        <div>
          <div class="stat-value" x-text="avgScore">—</div>
          <div class="stat-label">Avg Risk Score</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Weekly Trends Chart -->
  <div class="row g-4 mb-4" x-show="weeklyTrends.length > 0" x-cloak>
    <div class="col-12 col-xl-8">
      <div class="card">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Weekly Scan Trends (12 weeks)</h6></div>
        <div class="card-body">
          <canvas id="weeklyTrendsChart" height="220"></canvas>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-4">
      <div class="card h-100">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Department Risk Heatmap</h6></div>
        <div class="card-body p-0" x-show="deptHeatmap.length > 0" x-cloak>
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead>
                <tr>
                  <th class="ps-3">Department</th>
                  <th class="text-center">Scans</th>
                  <th class="text-center">Avg</th>
                  <th class="text-end pe-3">Risk</th>
                </tr>
              </thead>
              <tbody>
                <template x-for="d in deptHeatmap" :key="d.department">
                  <tr>
                    <td class="ps-3 fw-medium" x-text="d.department"></td>
                    <td class="text-center" x-text="d.scan_count"></td>
                    <td class="text-center" x-text="Number(d.avg_score).toFixed(1)"></td>
                    <td class="text-end pe-3">
                      <span class="badge badge-soft-danger me-1" x-show="d.high > 0" x-text="d.high + ' H'"></span>
                      <span class="badge badge-soft-warning me-1" x-show="d.moderate > 0" x-text="d.moderate + ' M'"></span>
                      <span class="badge badge-soft-success" x-show="d.low > 0" x-text="d.low + ' L'"></span>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
        <div class="empty-state py-4" x-show="deptHeatmap.length === 0" x-cloak>
          <p class="mb-0 text-muted">No department data yet.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">

    <!-- Recent Scans -->
    <div class="col-12 col-xl-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0 fw-semibold">Recent Scans</h6>
          <a href="/scans/new-manual" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New
          </a>
        </div>

        <div class="text-center py-5" x-show="loading" x-cloak>
          <div class="spinner-border text-primary"></div>
        </div>

        <div class="card-body" x-show="error && !loading" x-cloak>
          <div class="alert alert-danger mb-0" x-text="error"></div>
        </div>

        <div class="empty-state" x-show="!loading && !error && recentScans.length === 0" x-cloak>
          <div class="empty-state-icon"><i class="bi bi-upc-scan"></i></div>
          <h6>No scans yet</h6>
          <p>Run your first ergonomic assessment to see results here.</p>
          <a href="/scans/new-manual" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Scan
          </a>
        </div>

        <div class="table-responsive" x-show="!loading && !error && recentScans.length > 0" x-cloak>
          <table class="table table-hover mb-0 align-middle">
            <thead>
              <tr>
                <th>Task</th>
                <th>Type</th>
                <th>Score</th>
                <th>Risk</th>
                <th class="d-none d-md-table-cell">Date</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="s in recentScans" :key="s.id">
                <tr>
                  <td class="fw-medium" x-text="s.task_name ?? s.task_id"></td>
                  <td><span class="badge badge-soft-secondary text-uppercase" x-text="s.scan_type"></span></td>
                  <td class="fw-semibold" x-text="fmtScore(s.normalized_score)"></td>
                  <td>
                    <span class="badge"
                          :class="s.risk_category === 'high' ? 'badge-soft-danger'
                                : s.risk_category === 'moderate' ? 'badge-soft-warning'
                                : 'badge-soft-success'"
                          x-text="s.risk_category"></span>
                  </td>
                  <td class="d-none d-md-table-cell text-muted" x-text="fmtDate(s.created_at)"></td>
                  <td class="text-end">
                    <a :href="'/scans/' + s.id" class="btn btn-sm btn-outline-primary btn-icon" title="View">
                      <i class="bi bi-arrow-right"></i>
                    </a>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center py-2"
             x-show="!loading && !error && recentScans.length > 0" x-cloak>
          <span class="text-muted text-sm" x-text="recentScans.length + ' recent scans'"></span>
          <a href="/tasks" class="btn btn-sm btn-link p-0 text-decoration-none text-primary">View all tasks →</a>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="col-12 col-xl-4">

      <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Quick Actions</h6></div>
        <div class="card-body d-grid gap-2">
          <a href="/scans/new-manual" class="btn btn-outline-primary text-start">
            <i class="bi bi-upc-scan me-2"></i>Manual Scan
          </a>
          <a href="/scans/new-video" class="btn btn-outline-primary text-start">
            <i class="bi bi-camera-video me-2"></i>Video Scan
          </a>
          <a href="/tasks" class="btn btn-outline-secondary text-start">
            <i class="bi bi-list-task me-2"></i>Manage Tasks
          </a>
          <a href="/org/users" class="btn btn-outline-secondary text-start">
            <i class="bi bi-people me-2"></i>Team Members
          </a>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Tasks by Risk</h6></div>

        <div class="text-center py-4" x-show="loading" x-cloak>
          <div class="spinner-border spinner-border-sm text-primary"></div>
        </div>

        <div class="empty-state py-4" x-show="!loading && topTasks.length === 0" x-cloak>
          <div class="empty-state-icon" style="width:48px;height:48px;font-size:1.25rem;">
            <i class="bi bi-list-task"></i>
          </div>
          <p class="mb-0">No tasks recorded yet.</p>
        </div>

        <ul class="list-group list-group-flush" x-show="!loading && topTasks.length > 0" x-cloak>
          <template x-for="t in topTasks" :key="t.id ?? t.name">
            <li class="list-group-item d-flex justify-content-between align-items-center px-4 py-3">
              <span class="fw-medium text-truncate me-2" x-text="t.name"></span>
              <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <span class="badge"
                      :class="t.highest_risk === 'high'     ? 'badge-soft-danger'
                            : t.highest_risk === 'moderate' ? 'badge-soft-warning'
                            : 'badge-soft-success'"
                      x-text="t.highest_risk"></span>
                <span class="text-muted text-xs" x-text="t.scan_count + ' scans'"></span>
              </div>
            </li>
          </template>
        </ul>
      </div>

    </div>
  </div>

</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
