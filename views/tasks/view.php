<?php
$pageTitle  = 'Task Detail';
$activePage = 'tasks';
ob_start();
?>
<div x-data="taskDetailPage">

  <?php
  $headerTitleHtml = '<h1 class="page-title" x-text="task ? task.name : \"Task Detail\"">Task Detail</h1>';
  $headerBreadcrumbHtml = '<ol class="breadcrumb mb-0 text-sm"><li class="breadcrumb-item"><a href="/tasks" class="text-decoration-none text-muted">Tasks</a></li><li class="breadcrumb-item active" x-text="task ? task.name : \"…\"">…</li></ol>';
  $headerActionsHtml = '
    <a :href="\'/scans/new-video?task_id=\' + taskId" class="btn btn-outline-primary">
      <i class="bi bi-camera-video me-1"></i>Video Scan
    </a>
    <a :href="\'/scans/new-manual?task_id=\' + taskId" class="btn btn-primary">
      <i class="bi bi-upc-scan me-1"></i>Manual Scan
    </a>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <!-- Loading -->
  <div class="text-center py-5" x-show="loading" x-cloak>
    <div class="spinner-border text-primary"></div>
  </div>

  <div class="alert alert-danger" x-show="error && !loading" x-cloak x-text="error"></div>

  <!-- Task info -->
  <div class="row g-4 mb-4" x-show="task && !loading" x-cloak>
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3 mb-3">
            <div class="stat-icon stat-icon-primary flex-shrink-0"><i class="bi bi-list-task"></i></div>
            <div>
              <h5 class="mb-1 fw-bold" x-text="task?.name ?? '—'"></h5>
              <p class="text-muted mb-2" x-text="task?.description ?? ''"></p>
              <div class="d-flex flex-wrap gap-2">
                <span class="badge badge-soft-secondary" x-show="task?.department" x-cloak>
                  <i class="bi bi-building me-1"></i><span x-text="task?.department"></span>
                </span>
                <span class="badge badge-soft-info">
                  <i class="bi bi-upc-scan me-1"></i><span x-text="scans.length + ' scan' + (scans.length === 1 ? '' : 's')"></span>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-body">
          <h6 class="section-title mb-3">Details</h6>
          <dl class="mb-0">
            <dt class="text-muted text-sm">Created</dt>
            <dd class="fw-medium mb-3" x-text="task ? fmtDate(task.created_at) : '—'"></dd>
            <dt class="text-muted text-sm">Department</dt>
            <dd class="fw-medium mb-0" x-text="task?.department || '—'"></dd>
          </dl>
        </div>
      </div>
    </div>
  </div>

  <!-- Scan History -->
  <div class="card" x-show="task && !loading" x-cloak>
    <div class="card-header d-flex justify-content-between align-items-center">
      <h6 class="mb-0 fw-semibold">Scan History</h6>
      <a :href="'/scans/new-manual?task_id=' + taskId" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Scan
      </a>
    </div>

    <div class="empty-state" x-show="scans.length === 0" x-cloak>
      <div class="empty-state-icon"><i class="bi bi-upc-scan"></i></div>
      <h6>No scans yet</h6>
      <p>Run a manual or video scan to evaluate this task.</p>
    </div>

    <div class="table-responsive" x-show="scans.length > 0" x-cloak>
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Type</th>
            <th>Model</th>
            <th>Score</th>
            <th>Risk</th>
            <th class="d-none d-md-table-cell">Date</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="s in scans" :key="s.id">
            <tr>
              <td><span class="badge badge-soft-secondary text-uppercase" x-text="s.scan_type"></span></td>
              <td class="text-muted" x-text="(s.model || '—').toUpperCase()"></td>
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
                <div class="dropdown">
                  <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item" :href="'/scans/' + s.id">
                        <i class="bi bi-eye me-2 text-muted"></i>View Results
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" :href="'/observer-rating?scan_id=' + s.id">
                        <i class="bi bi-person-check me-2 text-muted"></i>Observer Rating
                      </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <template x-if="s.scan_type === 'video'">
                      <li>
                        <a class="dropdown-item" :href="'/scans/new-video?task_id=' + taskId + '&parent_scan_id=' + s.id + '&parent_model=' + (s.model || '')">
                          <i class="bi bi-camera-video me-2 text-muted"></i>Repeat Scan (Video)
                        </a>
                      </li>
                    </template>
                    <template x-if="s.scan_type === 'manual'">
                      <li>
                        <a class="dropdown-item" :href="'/scans/new-manual?task_id=' + taskId + '&parent_scan_id=' + s.id + '&parent_model=' + (s.model || '')">
                          <i class="bi bi-upc-scan me-2 text-muted"></i>Repeat Scan (Manual)
                        </a>
                      </li>
                    </template>
                    <li><hr class="dropdown-divider"></li>
                    <template x-if="s.parent_scan_id != null">
                      <li>
                        <a class="dropdown-item" :href="'/scans/' + s.id + '/compare'">
                          <i class="bi bi-bar-chart-steps me-2 text-primary"></i>Simple Compare
                        </a>
                      </li>
                    </template>
                    <template x-if="s.parent_scan_id == null">
                      <li>
                        <span class="dropdown-item-text text-muted">
                          <i class="bi bi-bar-chart-steps me-2"></i>Simple Compare (repeat scan required)
                        </span>
                      </li>
                    </template>
                  </ul>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div class="card-footer py-2" x-show="scans.length > 0" x-cloak>
      <span class="text-muted text-sm" x-text="scans.length + ' scan record' + (scans.length === 1 ? '' : 's')"></span>
    </div>
  </div>

</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
