<?php
$pageTitle = 'Ergonomics Copilot';
$activePage = 'copilot';
ob_start();
?>
<div x-data="copilotPage">

  <?php
  $headerTitle = 'Ergonomics Copilot';
  $headerBreadcrumbHtml = '<ol class="breadcrumb mb-0 text-sm"><li class="breadcrumb-item"><a href="/dashboard" class="text-decoration-none text-muted">Dashboard</a></li><li class="breadcrumb-item active">Copilot</li></ol>';
  $headerActionsHtml = '<a href="/scans/compare" class="btn btn-outline-secondary"><i class="bi bi-arrow-left-right me-1"></i>Compare Scans</a>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <div class="row g-4">
    <div class="col-12 col-xl-5">
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0 fw-semibold">Scoped Request</h6>
        </div>
        <div class="card-body">
          <div class="alert alert-danger" x-show="error" x-cloak x-text="error"></div>

          <div class="mb-3">
            <label class="form-label">Persona</label>
            <select class="form-select" x-model="form.persona">
              <option value="supervisor">Supervisor</option>
              <option value="safety_manager">Safety Manager</option>
              <option value="engineer">Engineer</option>
              <option value="auditor">Auditor</option>
            </select>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Window Days</label>
              <input type="number" min="1" max="90" class="form-control" x-model.number="form.window_days">
            </div>
            <div class="col-md-6">
              <label class="form-label">Scan ID (optional)</label>
              <input type="number" min="1" class="form-control" x-model.number="form.scan_id">
            </div>
            <div class="col-md-6">
              <label class="form-label">Baseline Scan ID (auditor)</label>
              <input type="number" min="1" class="form-control" x-model.number="form.baseline_scan_id">
            </div>
          </div>

          <button class="btn btn-primary mt-4" @click="run()" :disabled="loading">
            <span class="spinner-border spinner-border-sm me-1" x-show="loading" x-cloak></span>
            <span x-text="loading ? 'Running...' : 'Run Copilot'"></span>
          </button>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-7">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
          <h6 class="mb-0 fw-semibold">Copilot Output</h6>
          <div class="d-flex align-items-center gap-2">
            <span class="badge badge-soft-secondary text-capitalize" x-text="response?.persona || '-'"></span>
            <span class="badge text-capitalize" :class="llmStatusClass(response?.llm?.status)" x-text="response?.llm?.status || 'n/a'"></span>
          </div>
        </div>

        <div class="card-body" x-show="!response && !loading" x-cloak>
          <p class="text-muted mb-0">Run a scoped copilot request to generate an evidence-backed response.</p>
        </div>

        <div class="card-body" x-show="response" x-cloak>
          <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
            <div>
              <h5 class="mb-1" x-text="response?.result?.title || 'Result'"></h5>
              <p class="text-muted mb-0" x-text="response?.result?.summary || ''"></p>
            </div>
            <small class="text-muted" x-show="response?.llm">
              <span x-text="'Model: ' + (response?.llm?.model || '-') + ' | ' + (response?.llm?.latency_ms || 0) + 'ms'"></span>
            </small>
          </div>

          <div class="alert alert-warning" x-show="response?.llm?.status === 'fallback'" x-cloak>
            Narrative is running in deterministic fallback mode. Evidence and ranked actions remain deterministic.
          </div>
          <div class="alert alert-secondary" x-show="response?.llm?.status === 'disabled'" x-cloak>
            Narrative generation is disabled. Showing deterministic output only.
          </div>

          <div class="mb-3" x-show="response?.audit_id" x-cloak>
            <span class="text-muted text-xs text-uppercase">Audit Reference</span>
            <div><code x-text="response?.audit_id"></code></div>
          </div>

          <div class="mb-3" x-show="response?.narrative" x-cloak>
            <p class="text-muted text-xs text-uppercase mb-2">Narrative</p>
            <div class="border rounded p-3 bg-light">
              <p class="mb-2"><strong>Executive Summary:</strong> <span x-text="response?.narrative?.executive_summary || ''"></span></p>
              <p class="mb-2"><strong>Why This Matters:</strong> <span x-text="response?.narrative?.why_this_matters || ''"></span></p>
              <p class="mb-0"><strong>Recommended Actions:</strong> <span x-text="response?.narrative?.recommended_actions_text || ''"></span></p>
            </div>
          </div>

          <div class="mb-3" x-show="(response?.citations || []).length > 0" x-cloak>
            <p class="text-muted text-xs text-uppercase mb-2">Structured Citations</p>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead>
                  <tr>
                    <th>Source</th>
                    <th>Metric</th>
                    <th>Value</th>
                    <th>Window</th>
                    <th>Confidence</th>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="(cite, idx) in (response?.citations || [])" :key="citationKey(cite, idx)">
                    <tr>
                      <td>
                        <div class="fw-semibold" x-text="cite.source_type"></div>
                        <div class="text-muted text-xs" x-text="cite.source_id"></div>
                      </td>
                      <td x-text="cite.metric"></td>
                      <td x-text="cite.value"></td>
                      <td x-text="cite.time_window"></td>
                      <td x-text="confidencePct(cite.confidence)"></td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>

          <div class="mb-3" x-show="response?.result?.recommended_next_steps">
            <p class="text-muted text-xs text-uppercase mb-2">Recommended Next Steps</p>
            <div class="d-grid gap-2">
              <template x-for="step in (response?.result?.recommended_next_steps || [])" :key="step.action">
                <div class="border rounded p-2 bg-light d-flex justify-content-between align-items-center gap-2">
                  <span class="text-sm" x-text="step.action"></span>
                  <span class="badge badge-soft-warning text-capitalize" x-text="step.priority"></span>
                </div>
              </template>
            </div>
          </div>

          <div class="mb-3" x-show="response?.result?.draft_plan">
            <p class="text-muted text-xs text-uppercase mb-2">Draft Plan</p>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead><tr><th>Control</th><th>Hierarchy</th><th>Expected Reduction</th></tr></thead>
                <tbody>
                  <template x-for="p in (response?.result?.draft_plan || [])" :key="p.control_code + '-' + p.source_scan_id">
                    <tr>
                      <td x-text="p.control_title"></td>
                      <td class="text-capitalize" x-text="p.hierarchy_level"></td>
                      <td x-text="Number(p.expected_risk_reduction_pct || 0).toFixed(1) + '%' "></td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>

          <div x-show="response?.result?.options">
            <p class="text-muted text-xs text-uppercase mb-2">Engineering Options</p>
            <div class="d-grid gap-2">
              <template x-for="opt in (response?.result?.options || [])" :key="opt.option">
                <div class="border rounded p-2">
                  <div class="fw-semibold" x-text="opt.option"></div>
                  <div class="text-muted text-xs">
                    <span class="text-capitalize" x-text="opt.hierarchy_level"></span> |
                    <span x-text="Number(opt.expected_risk_reduction_pct || 0).toFixed(1) + '%' "></span> |
                    <span x-text="(opt.time_to_deploy_days || 0) + 'd deploy'"></span>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <details class="mt-3">
            <summary class="text-muted text-sm">Raw Response</summary>
            <pre class="bg-light border rounded p-3 mt-2 mb-0" x-text="pretty(response)"></pre>
          </details>
        </div>
      </div>
    </div>
  </div>

</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';

