<?php
$pageTitle  = 'Observer Rating';
$activePage = 'scans';
ob_start();
?>
<div x-data="observerRatePage">

  <?php
  $headerTitle = 'Observer Rating';
  $headerBreadcrumbHtml = '<ol class="breadcrumb mb-0 text-sm"><li class="breadcrumb-item"><a href="/tasks" class="text-decoration-none text-muted">Tasks</a></li><li class="breadcrumb-item"><a :href="\'/scans/\' + scanId" class="text-decoration-none text-muted">Scan</a></li><li class="breadcrumb-item active">Rate</li></ol>';
  $headerActionsHtml = '<a :href="\'/scans/\' + scanId" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Scan</a>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <!-- Loading -->
  <div class="text-center py-5" x-show="loading" x-cloak>
    <div class="spinner-border text-primary"></div>
  </div>

  <div class="alert alert-danger" x-show="error && !loading" x-cloak x-text="error"></div>

  <!-- Rating form -->
  <div class="row g-4" x-show="!loading && !submitted" x-cloak>
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Scan Overview</h6></div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-5">Scan ID</dt>
            <dd class="col-7" x-text="scanId"></dd>
            <dt class="col-5">Type</dt>
            <dd class="col-7" x-text="scan?.scan_type ?? '–'"></dd>
            <dt class="col-5">Model</dt>
            <dd class="col-7 text-uppercase" x-text="scan?.model ?? '–'"></dd>
            <dt class="col-5">System Score</dt>
            <dd class="col-7" x-text="scan?.normalized_score ?? '–'"></dd>
            <dt class="col-5">System Category</dt>
            <dd class="col-7">
              <span class="badge"
                    :class="scan?.risk_category === 'high' ? 'badge-soft-danger'
                          : scan?.risk_category === 'moderate' ? 'badge-soft-warning'
                          : 'badge-soft-success'"
                    x-text="scan?.risk_category ?? '–'"></span>
            </dd>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Your Observer Assessment</h6></div>
        <div class="card-body">

          <div class="alert alert-danger" x-show="formError" x-cloak x-text="formError"></div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Observer Score</label>
            <input type="number" class="form-control" min="0" max="15" step="0.1"
                   x-model.number="form.observer_score" placeholder="e.g. 7.5">
            <div class="form-text">REBA 1–15 or RULA 1–7 depending on model.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Risk Category</label>
            <select class="form-select" x-model="form.observer_category">
              <option value="">— Select —</option>
              <option value="low">Low</option>
              <option value="moderate">Moderate</option>
              <option value="high">High</option>
            </select>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control" rows="3" x-model="form.notes" placeholder="Observations, corrections…"></textarea>
          </div>

          <button class="btn btn-primary w-100" @click="submit" :disabled="saving">
            <span class="spinner-border spinner-border-sm me-1" x-show="saving" x-cloak></span>
            <span x-text="saving ? 'Submitting…' : 'Submit Rating'"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Previous observer ratings -->
  <div class="card mt-4" x-show="!loading && ratings.length > 0" x-cloak>
    <div class="card-header"><h6 class="mb-0 fw-semibold">Previous Observer Ratings</h6></div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Observer</th>
            <th>Score</th>
            <th>Category</th>
            <th>Notes</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="r in ratings" :key="r.id">
            <tr>
              <td x-text="'#' + r.observer_id"></td>
              <td class="fw-semibold" x-text="Number(r.observer_score).toFixed(1)"></td>
              <td>
                <span class="badge"
                      :class="r.observer_category === 'high' ? 'badge-soft-danger'
                            : r.observer_category === 'moderate' ? 'badge-soft-warning'
                            : 'badge-soft-success'"
                      x-text="r.observer_category"></span>
              </td>
              <td x-text="r.notes || '–'"></td>
              <td class="text-muted" x-text="new Date(r.created_at).toLocaleDateString()"></td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Success -->
  <div class="alert alert-success d-flex align-items-center gap-2 mt-4" x-show="submitted" x-cloak>
    <i class="bi bi-check-circle-fill"></i>
    <span>Rating submitted successfully. <a :href="'/scans/' + scanId" class="alert-link">Back to scan results</a></span>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
