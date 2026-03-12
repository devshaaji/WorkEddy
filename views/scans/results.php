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

  <!-- Results -->
  <div x-show="scan && !loading && !scanInvalid" x-cloak>

    <div class="row g-4 mb-4">

      <!-- Score -->
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-body text-center py-4">
            <p class="section-title mb-2">Risk Score</p>
            <div class="display-4 fw-bold mb-2" x-text="score" :style="'color:' + barColor"></div>
            <span class="badge px-3 py-2 text-wrap fs-10"
              :class="riskLevelCategory === 'high' ? 'badge-soft-danger'
                : riskLevelCategory === 'moderate' ? 'badge-soft-warning'
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

    <!-- Ranked Controls -->
    <div class="card mb-4" x-show="controls && controls.length > 0" x-cloak>
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-list-check text-primary"></i>
        <h6 class="mb-0 fw-semibold">Prescriptive Controls (Ranked)</h6>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
              <th>#</th>
              <th>Control</th>
              <th>Hierarchy</th>
              <th>Type</th>
              <th>Expected Risk Reduction</th>
              <th>Feasibility</th>
              <th>Cost</th>
              <th>Deploy</th>
              <th>Throughput Impact</th>
              <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <template x-for="c in controls" :key="c.id || c.rank_order">
              <tr>
                <td><span class="badge badge-soft-primary" x-text="c.rank_order"></span></td>
                <td>
                  <div class="fw-semibold" x-text="c.title"></div>
                  <div class="text-muted text-sm" x-text="c.rationale"></div>
                  <div class="text-xs text-muted mt-1" x-show="c.control_type === 'interim' && c.interim_for_control_code">
                    Interim while higher-order control is deployed:
                    <span class="fw-semibold" x-text="c.interim_for_control_code"></span>
                  </div>
                  <div class="text-xs text-muted mt-1" x-show="c.evidence && c.evidence.pros && c.evidence.pros.length">
                    <strong>Pros:</strong> <span x-text="c.evidence.pros.join(', ')"></span>
                  </div>
                  <div class="text-xs text-muted" x-show="c.evidence && c.evidence.cons && c.evidence.cons.length">
                    <strong>Cons:</strong> <span x-text="c.evidence.cons.join(', ')"></span>
                  </div>
                </td>
                <td><span class="badge badge-soft-secondary text-capitalize" x-text="c.hierarchy_level"></span></td>
                <td>
                  <span class="badge text-capitalize"
                        :class="c.control_type === 'interim' ? 'badge-soft-warning' : 'badge-soft-success'"
                        x-text="c.control_type || 'permanent'"></span>
                </td>
                <td x-text="Number(c.expected_risk_reduction_pct).toFixed(1) + '%' "></td>
                <td>
                  <span class="badge text-capitalize"
                        :class="c.feasibility_status === 'feasible' ? 'badge-soft-success' : (c.feasibility_status === 'not_feasible' ? 'badge-soft-danger' : 'badge-soft-warning')"
                        x-text="(Number(c.feasibility_score || 0).toFixed(1)) + '% ' + (c.feasibility_status || 'conditional')"></span>
                </td>
                <td class="text-capitalize" x-text="c.implementation_cost"></td>
                <td x-text="c.time_to_deploy_days + 'd'"></td>
                <td class="text-capitalize" x-text="c.throughput_impact"></td>
                <td>
                  <template x-if="actionForControl(c.id)">
                    <span class="badge text-capitalize"
                          :class="actionForControl(c.id).status === 'verified' ? 'badge-soft-success' : 'badge-soft-secondary'"
                          x-text="actionForControl(c.id).status"></span>
                  </template>
                  <template x-if="!actionForControl(c.id)">
                    <button class="btn btn-outline-primary btn-sm"
                            x-show="canManageActions"
                            @click="createControlAction(c)"
                            :disabled="actionBusy">
                      <span x-show="actionBusy" class="spinner-border spinner-border-sm me-1"></span>
                      Create Action
                    </button>
                  </template>
                </td>
              </tr>
            </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card mb-4" x-show="controlActions && controlActions.length > 0" x-cloak>
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-link-45deg text-primary"></i>
          <h6 class="mb-0 fw-semibold">Control Action Evidence Chain</h6>
        </div>
        <span class="badge badge-soft-secondary" x-text="controlActions.length + ' actions'"></span>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
              <th>Control</th>
              <th>Status</th>
              <th>Owner</th>
              <th>Due</th>
              <th>Verification</th>
              <th>Workflow</th>
            </tr>
            </thead>
            <tbody>
            <template x-for="a in controlActions" :key="a.id">
              <tr>
                <td>
                  <div class="fw-semibold" x-text="a.control_title"></div>
                  <div class="text-muted text-xs" x-text="'Code: ' + a.control_code"></div>
                </td>
                <td>
                  <span class="badge text-capitalize"
                        :class="a.status === 'verified' ? 'badge-soft-success' : (a.status === 'cancelled' ? 'badge-soft-danger' : 'badge-soft-warning')"
                        x-text="a.status"></span>
                </td>
                <td x-text="a.assigned_to_name || 'Unassigned'"></td>
                <td x-text="a.target_due_date || '—'"></td>
                <td>
                  <template x-if="a.verification_summary && a.verification_summary.improvement_proof">
                    <div class="text-xs">
                      <div>
                        Risk delta:
                        <strong x-text="(a.verification_summary.improvement_proof.risk_reduction_percent ?? 0) + '%'"></strong>
                      </div>
                      <div class="text-muted">
                        Scan:
                        <span x-text="a.verification_scan_id || '—'"></span>
                      </div>
                    </div>
                  </template>
                  <template x-if="!a.verification_summary || !a.verification_summary.improvement_proof">
                    <span class="text-muted text-xs">Pending verification</span>
                  </template>
                </td>
                <td>
                  <div class="d-flex flex-wrap gap-1" x-show="canManageActions">
                    <button class="btn btn-outline-secondary btn-sm"
                            x-show="a.status === 'planned'"
                            @click="updateActionStatus(a.id, 'in_progress')">Start</button>
                    <button class="btn btn-outline-primary btn-sm"
                            x-show="a.status === 'in_progress'"
                            @click="updateActionStatus(a.id, 'implemented')">Implemented</button>
                    <button class="btn btn-outline-success btn-sm"
                            x-show="a.status === 'implemented' || a.status === 'in_progress'"
                            @click="verifyActionPrompt(a.id)">Verify</button>
                    <button class="btn btn-outline-danger btn-sm"
                            x-show="a.status !== 'verified' && a.status !== 'cancelled'"
                            @click="updateActionStatus(a.id, 'cancelled')">Cancel</button>
                  </div>
                  <span class="text-muted text-xs" x-show="!canManageActions">View only</span>
                </td>
              </tr>
            </template>
            </tbody>
          </table>
        </div>
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

    <!-- Video Preview -->
  <div class="card mb-4" x-show="scan && scan.video_path && !loading" x-cloak>
    <div class="card-header d-flex align-items-center gap-2">
      <i class="bi bi-camera-video text-primary"></i>
      <h6 class="mb-0 fw-semibold">Pose Detection Video</h6>
    </div>
    <div class="card-body p-0">
      <video class="w-100 rounded-bottom" style="max-height:400px;background:#000" controls preload="metadata"
             :src="(scan && scan.video_path)
                ? (String(scan.video_path).startsWith('/storage/uploads/videos/')
                    ? String(scan.video_path).replace('/storage/uploads/videos/', '/storage/videos/')
                    : String(scan.video_path))
                : ''">
        Your browser does not support video playback.
      </video>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
