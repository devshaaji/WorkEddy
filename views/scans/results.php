<?php
$pageTitle  = 'Scan Results';
$activePage = 'scans';
ob_start();
?>
<div x-data="scanResultsPage">

  <?php
  $headerTitle = 'Scan Results';
  $headerBreadcrumbHtml = '<ol class="breadcrumb mb-0 text-sm"><li class="breadcrumb-item"><a href="/tasks" class="text-decoration-none text-muted">Tasks</a></li><li class="breadcrumb-item active">Results</li></ol>';
  $headerActionsHtml = '
    <a :href="\'/observer-rating?scan_id=\' + scanId" class="btn btn-outline-primary">
      <i class="bi bi-eye me-1"></i>Observer Rating
    </a>
    <a href="/tasks" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <!-- Processing banner -->
  <div class="alert alert-info align-items-center gap-2"
       x-show="pending" x-cloak style="display:none" :style="pending ? 'display:flex' : 'display:none'">
    <div class="spinner-border spinner-border-sm flex-shrink-0"></div>
    <span><strong>Processing…</strong> Your video is being analysed. Results will appear automatically.</span>
  </div>

  <!-- Invalid / Error banner -->
  <div class="alert alert-danger align-items-start gap-2"
       x-show="scanInvalid" x-cloak style="display:none" :style="scanInvalid ? 'display:flex' : 'display:none'">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
    <div>
      <strong>Analysis Failed</strong>
      <p class="mb-0 mt-1" x-text="errorMessage"></p>
    </div>
  </div>

  <!-- Loading -->
  <div class="text-center py-5" x-show="loading" x-cloak>
    <div class="spinner-border text-primary"></div>
  </div>

  <div class="alert alert-danger" x-show="error && !loading" x-cloak x-text="error"></div>

  <!-- Video Preview -->
  <div class="card mb-4" x-show="scan && scan.video_path && !loading" x-cloak>
    <div class="card-header d-flex align-items-center gap-2">
      <i class="bi bi-camera-video text-primary"></i>
      <h6 class="mb-0 fw-semibold">Uploaded Video</h6>
    </div>
    <div class="card-body p-0">
      <video class="w-100 rounded-bottom" style="max-height:400px;background:#000" controls preload="metadata"
             :src="'/storage/videos/' + scan.video_path.split('/').pop()">
        Your browser does not support video playback.
      </video>
    </div>
  </div>

  <!-- Results -->
  <div x-show="scan && !loading && !scanInvalid" x-cloak>

    <div class="row g-4 mb-4">

      <!-- Score -->
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-body text-center py-4">
            <p class="section-title mb-2">Risk Score</p>
            <div class="display-4 fw-bold mb-2" x-text="score" :style="'color:' + barColor"></div>
            <span class="badge px-3 py-2 fs-6"
                  :class="riskLevel === 'high' ? 'badge-soft-danger'
                        : riskLevel === 'moderate' ? 'badge-soft-warning'
                        : 'badge-soft-success'"
                  x-text="riskLevel"></span>
            <div class="risk-meter mt-4">
              <div class="risk-meter-fill" :style="'background:' + barColor + ';width:' + barWidth"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Details -->
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-header"><h6 class="mb-0 fw-semibold">Scan Details</h6></div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-sm-4 text-muted text-sm">Task</dt>
              <dd class="col-sm-8 fw-medium" x-text="scan?.task_id ?? '—'"></dd>
              <dt class="col-sm-4 text-muted text-sm">Type</dt>
              <dd class="col-sm-8"><span class="badge badge-soft-secondary text-uppercase" x-text="scan?.scan_type ?? '—'"></span></dd>
              <dt class="col-sm-4 text-muted text-sm">Model</dt>
              <dd class="col-sm-8"><span class="badge badge-soft-primary" x-text="modelLabel"></span></dd>
              <dt class="col-sm-4 text-muted text-sm">Status</dt>
              <dd class="col-sm-8">
                <span class="badge"
                      :class="scan?.status === 'completed' ? 'badge-soft-success'
                            : scan?.status === 'processing' ? 'badge-soft-warning'
                            : 'badge-soft-secondary'"
                      x-text="scan?.status ?? '—'"></span>
              </dd>
              <dt class="col-sm-4 text-muted text-sm">Analysed</dt>
              <dd class="col-sm-8 mb-0 text-muted" x-text="scan ? fmtDate(scan.created_at) : '—'"></dd>
            </dl>
          </div>
        </div>
      </div>
    </div>

    <!-- Recommendation -->
    <div class="card mb-4" x-show="recommendation" x-cloak>
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-lightbulb text-warning"></i>
        <h6 class="mb-0 fw-semibold">Recommendation</h6>
      </div>
      <div class="card-body">
        <p class="mb-0" x-text="recommendation"></p>
      </div>
    </div>

    <!-- Measurements -->
    <div class="card" x-show="measurements.length > 0" x-cloak>
      <div class="card-header"><h6 class="mb-0 fw-semibold">Measurements</h6></div>
      <div class="card-body">
        <div class="row g-3">
          <template x-for="m in measurements" :key="m.label">
            <div class="col-6 col-md-4 col-xl-3">
              <div class="p-3 rounded bg-light border">
                <p class="text-muted text-xs text-uppercase fw-semibold mb-1" x-text="m.label"></p>
                <span class="fw-bold fs-5" x-text="m.value"></span>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
