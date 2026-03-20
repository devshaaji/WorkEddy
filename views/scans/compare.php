<?php
$pageTitle  = 'Scan Comparison';
$activePage = 'scans';
ob_start();
?>
<div x-data="scanComparePage">

  <?php
  $headerTitle = 'Scan Comparison';
  $headerBreadcrumbHtml = '<ol class="breadcrumb mb-0 text-sm"><li class="breadcrumb-item"><a href="/tasks" class="text-decoration-none text-muted">Tasks</a></li><li class="breadcrumb-item"><a :href="\'/scans/\' + scanId" class="text-decoration-none text-muted">Scan</a></li><li class="breadcrumb-item active">Compare</li></ol>';
  $headerActionsHtml = '<a :href="\'/scans/\' + scanId" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Scan</a>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <!-- Loading -->
  <div class="text-center py-5" x-show="loading" x-cloak>
    <div class="spinner-border text-primary"></div>
  </div>

  <div class="alert alert-danger" x-show="error && !loading" x-cloak x-text="error"></div>

  <div class="empty-state" x-show="!loading && !error && noComparisonData" x-cloak>
    <div class="empty-state-icon"><i class="bi bi-bar-chart"></i></div>
    <h6>No comparison baseline found</h6>
    <p>This scan does not have a linked previous scan yet. Choose two scans in advanced comparison instead.</p>
    <a href="/scans/compare" class="btn btn-primary btn-sm">
      <i class="bi bi-sliders me-1"></i>Open Advanced Compare
    </a>
  </div>

  <!-- Comparison content -->
  <div x-show="current && parent && !loading" x-cloak>

    <!-- Risk reduction banner -->
    <div class="alert d-flex align-items-center gap-2 mb-4"
         :class="reduction > 0 ? 'alert-success' : 'alert-warning'">
      <i class="bi" :class="reduction > 0 ? 'bi-arrow-down-circle-fill' : 'bi-arrow-up-circle-fill'"></i>
      <span x-show="reduction > 0">
        Risk score <strong>reduced by <span x-text="reduction"></span>%</strong> since the previous scan.
      </span>
      <span x-show="reduction <= 0">
        Risk score has <strong>not improved</strong> since the previous scan.
      </span>
    </div>

    <div class="row g-3 mb-4" x-show="improvementProof" x-cloak>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <p class="text-muted text-sm mb-1">Risk Reduction</p>
          <div class="h5 mb-0" x-text="(improvementProof?.risk_reduction_percent ?? 0) + '%' "></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <p class="text-muted text-sm mb-1">Score Points Improved</p>
          <div class="h5 mb-0" x-text="improvementProof?.risk_reduction_points ?? 0"></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <p class="text-muted text-sm mb-1">Time Savings / Shift</p>
          <div class="h5 mb-0" x-text="(improvementProof?.estimated_time_savings_minutes_per_shift ?? 0) + ' min'"></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <p class="text-muted text-sm mb-1">Avoided Injury Cost (Est.)</p>
          <div class="h5 mb-0" x-text="'$' + Number(improvementProof?.estimated_avoided_injury_cost_usd_annual ?? 0).toLocaleString()"></div>
        </div></div>
      </div>
    </div>

    <!-- Side-by-side cards -->
    <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header bg-secondary-subtle">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-1"></i> Before (Parent Scan)</h6>
          </div>
          <div class="card-body text-center">
            <div class="display-5 fw-bold mb-2" x-text="Number(parent.normalized_score).toFixed(1)"></div>
            <span class="badge px-3 py-2 fs-6"
                  :class="parent.risk_category === 'high' ? 'badge-soft-danger'
                        : parent.risk_category === 'moderate' ? 'badge-soft-warning'
                        : 'badge-soft-success'"
                  x-text="parent.risk_category"></span>
            <div class="text-muted mt-2 text-sm" x-text="'Model: ' + (parent.model || '–').toUpperCase()"></div>
            <div class="text-muted text-sm" x-text="'Date: ' + fmtDate(parent.created_at)"></div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100 border-primary">
          <div class="card-header bg-primary-subtle">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-arrow-repeat me-1"></i> After (Current Scan)</h6>
          </div>
          <div class="card-body text-center">
            <div class="display-5 fw-bold mb-2" x-text="Number(current.normalized_score).toFixed(1)"></div>
            <span class="badge px-3 py-2 fs-6"
                  :class="current.risk_category === 'high' ? 'badge-soft-danger'
                        : current.risk_category === 'moderate' ? 'badge-soft-warning'
                        : 'badge-soft-success'"
                  x-text="current.risk_category"></span>
            <div class="text-muted mt-2 text-sm" x-text="'Model: ' + (current.model || '–').toUpperCase()"></div>
            <div class="text-muted text-sm" x-text="'Date: ' + fmtDate(current.created_at)"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Chart -->
    <div class="card">
      <div class="card-header"><h6 class="mb-0 fw-semibold">Score Comparison</h6></div>
      <div class="card-body">
        <canvas id="compareChart" height="120"></canvas>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
