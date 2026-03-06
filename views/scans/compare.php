<?php
$pageTitle  = 'Scan Comparison';
$activePage = 'scans';
ob_start();
?>
<div x-data="scanComparePage">

  <div class="page-header">
    <div>
      <h1 class="page-title">Scan Comparison</h1>
      <ol class="breadcrumb mb-0 text-sm">
        <li class="breadcrumb-item"><a href="/tasks" class="text-decoration-none text-muted">Tasks</a></li>
        <li class="breadcrumb-item"><a :href="'/scans/' + scanId" class="text-decoration-none text-muted">Scan</a></li>
        <li class="breadcrumb-item active">Compare</li>
      </ol>
    </div>
    <a :href="'/scans/' + scanId" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back to Scan
    </a>
  </div>

  <!-- Loading -->
  <div class="text-center py-5" x-show="loading" x-cloak>
    <div class="spinner-border text-primary"></div>
  </div>

  <div class="alert alert-danger" x-show="error && !loading" x-cloak x-text="error"></div>

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
