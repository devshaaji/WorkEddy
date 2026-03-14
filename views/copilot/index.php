<?php
$pageTitle = 'Ergonomics Copilot';
$activePage = 'copilot';
ob_start();
?>
<div x-data="copilotPage">

  <?php
  $headerTitle = 'Ergonomics Copilot';
  $headerBreadcrumbHtml = '<ol class="breadcrumb mb-0 text-sm"><li class="breadcrumb-item"><a href="/dashboard" class="text-decoration-none text-muted">Dashboard</a></li><li class="breadcrumb-item active">Copilot</li></ol>';
  $headerActionsHtml = '<button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#copilotConfigDrawer" aria-controls="copilotConfigDrawer"><i class="bi bi-magic me-1"></i>Run Copilot</button><a href="/scans/compare" class="btn btn-outline-secondary"><i class="bi bi-arrow-left-right me-1"></i>Compare Scans</a>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <div class="row g-4 copilot-shell">
    <div class="col-12 d-grid gap-4">
      <div class="card copilot-hero-card">
        <div class="card-body p-4 p-lg-5 d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
          <div>
            <div class="d-inline-flex align-items-center gap-2 text-primary fw-semibold text-sm mb-2">
              <i class="bi bi-stars"></i>
              <span>Copilot Analysis</span>
            </div>
            <h2 class="mb-1 fw-bold">Executive Insight Report</h2>
            <p class="text-muted mb-0">Automated assessment for the selected analysis window.</p>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge badge-soft-secondary text-capitalize" x-text="response?.persona || form.persona"></span>
            <span class="badge text-capitalize" :class="llmStatusClass(response?.llm?.status)" x-text="response?.llm?.status || 'n/a'"></span>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-md-4">
          <div class="card copilot-kpi-card h-100">
            <div class="card-body">
              <p class="text-muted mb-1">Structured Citations</p>
              <div class="d-flex align-items-end gap-2">
                <span class="display-6 fw-bold mb-0" x-text="kpiTotalCitations()"></span>
                <span class="text-success text-xs fw-semibold"><i class="bi bi-graph-up-arrow me-1"></i>Evidence</span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card copilot-kpi-card h-100 border-warning-subtle">
            <div class="card-body">
              <p class="text-muted mb-1">High Priority Actions</p>
              <div class="d-flex align-items-end gap-2">
                <span class="display-6 fw-bold text-warning-emphasis mb-0" x-text="kpiHighPriorityActions()"></span>
                <span class="text-warning-emphasis text-xs fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>Focus</span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card copilot-kpi-card h-100">
            <div class="card-body">
              <p class="text-muted mb-1">Avg Confidence</p>
              <div class="d-flex align-items-end gap-2">
                <span class="display-6 fw-bold mb-0" x-text="kpiAvgConfidencePct()"></span>
                <span class="text-success text-xs fw-semibold"><i class="bi bi-check-circle me-1"></i>Reliable</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card" x-show="!response && !loading" x-cloak>
        <div class="card-body py-5 text-center">
          <i class="bi bi-robot fs-1 text-muted d-block mb-2"></i>
          <h5 class="mb-1">Awaiting Scoped Request</h5>
          <p class="text-muted mb-0">Run a scoped copilot request to generate an evidence-backed response.</p>
        </div>
      </div>

      <div class="card" x-show="response" x-cloak>
        <div class="card-header bg-transparent border-0 pb-0">
          <h6 class="mb-0 fw-semibold d-flex align-items-center gap-2">
            <i class="bi bi-calendar3 text-primary"></i>
            Shift Risk Brief
          </h6>
        </div>
        <div class="card-body pt-3">
          <div class="row g-3">
            <template x-for="(brief, idx) in insightBriefs()" :key="idx">
              <div class="col-12 col-md-6">
                <div class="copilot-brief-card h-100">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge badge-soft-secondary" x-text="brief.label"></span>
                    <i :class="'bi ' + brief.icon + ' ' + (
                      brief.priority === 'high' ? 'text-danger' :
                      brief.priority === 'medium' ? 'text-warning' :
                      brief.priority === 'low' ? 'text-success' :
                      'text-info'
                    )"></i>
                  </div>
                  <h6 class="mb-1" x-text="brief.title"></h6>
                  <p class="text-muted text-sm mb-0" x-text="brief.detail"></p>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>

      <div class="copilot-narrative-card" x-show="response" x-cloak>
        <div class="copilot-narrative-glow"></div>
        <div class="copilot-narrative-body">
          <div class="d-flex align-items-center gap-3 mb-4">
            <div class="copilot-narrative-icon"><i class="bi bi-cpu"></i></div>
            <div>
              <h5 class="mb-0 text-white">Narrative Analysis</h5>
              <p class="mb-0 text-info-emphasis small">Powered by ErgoIntelligence™</p>
            </div>
          </div>

          <p class="lead text-white mb-4" x-text="response?.result?.summary || 'No narrative summary available.'"></p>

          <div class="row g-4">
            <div class="col-12 col-lg-6">
              <h6 class="text-uppercase text-xs text-info mb-2"><i class="bi bi-lightbulb me-1"></i>Core Insight</h6>
              <p class="mb-0 text-light" x-text="response?.narrative?.why_this_matters || response?.narrative?.executive_summary || 'Narrative insight unavailable.'"></p>
            </div>
            <div class="col-12 col-lg-6">
              <h6 class="text-uppercase text-xs text-info mb-2"><i class="bi bi-flag-fill me-1"></i>Action Required</h6>
              <p class="mb-0 text-light" x-text="response?.narrative?.recommended_actions_text || 'No action guidance returned by model.'"></p>
            </div>
          </div>

          <div class="copilot-narrative-footer mt-4 pt-3">
            <div class="d-flex flex-wrap gap-3 text-xs text-light-emphasis">
              <span x-show="response?.audit_id" x-cloak><strong>Scan Reference:</strong> <span x-text="response?.audit_id"></span></span>
              <span><strong>Confidence:</strong> <span x-text="kpiAvgConfidencePct()"></span></span>
            </div>
          </div>
        </div>
      </div>

      <div class="card" x-show="response" x-cloak>
        <div class="card-header">
          <h6 class="mb-0 fw-semibold">Evidence & Plans</h6>
        </div>
        <div class="card-body">
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

          <div class="mb-3" x-show="structuredControls().length > 0" x-cloak>
            <p class="text-muted text-xs text-uppercase mb-2">Structured Control</p>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead>
                  <tr>
                    <th style="width: 56px">#</th>
                    <th>Action</th>
                    <th style="width: 130px">Priority</th>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="(step, idx) in pagedStructuredControls()" :key="(step.action || 'action') + '-' + idx">
                    <tr>
                      <td x-text="((controlsPage - 1) * pageSize) + idx + 1"></td>
                      <td x-text="step.action"></td>
                      <td><span class="badge badge-soft-warning text-capitalize" x-text="step.priority || 'n/a'"></span></td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-end align-items-center gap-2 mt-2" x-show="structuredControls().length > pageSize" x-cloak>
              <button type="button" class="btn btn-sm btn-outline-secondary" @click="prevControlsPage()" :disabled="controlsPage <= 1">
                <i class="bi bi-chevron-left"></i>
              </button>
              <span class="text-muted text-xs" x-text="'Page ' + controlsPage + ' of ' + structuredControlsTotalPages()"></span>
              <button type="button" class="btn btn-sm btn-outline-secondary" @click="nextControlsPage()" :disabled="controlsPage >= structuredControlsTotalPages()">
                <i class="bi bi-chevron-right"></i>
              </button>
            </div>
          </div>

          <div class="mb-3" x-show="draftPlanItems().length > 0" x-cloak>
            <p class="text-muted text-xs text-uppercase mb-2">Draft Plan</p>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead><tr><th>Control</th><th>Hierarchy</th><th>Expected Reduction</th></tr></thead>
                <tbody>
                  <template x-for="(p, idx) in pagedDraftPlanItems()" :key="(p.control_code || 'control') + '-' + (p.source_scan_id || 'scan') + '-' + idx">
                    <tr>
                      <td x-text="p.control_title"></td>
                      <td class="text-capitalize" x-text="p.hierarchy_level"></td>
                      <td x-text="Number(p.expected_risk_reduction_pct || 0).toFixed(1) + '%' "></td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-end align-items-center gap-2 mt-2" x-show="draftPlanItems().length > pageSize" x-cloak>
              <button type="button" class="btn btn-sm btn-outline-secondary" @click="prevDraftPlanPage()" :disabled="draftPlanPage <= 1">
                <i class="bi bi-chevron-left"></i>
              </button>
              <span class="text-muted text-xs" x-text="'Page ' + draftPlanPage + ' of ' + draftPlanTotalPages()"></span>
              <button type="button" class="btn btn-sm btn-outline-secondary" @click="nextDraftPlanPage()" :disabled="draftPlanPage >= draftPlanTotalPages()">
                <i class="bi bi-chevron-right"></i>
              </button>
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

  <div class="offcanvas offcanvas-end copilot-config-drawer" tabindex="-1" id="copilotConfigDrawer" aria-labelledby="copilotConfigDrawerLabel">
    <div class="offcanvas-header border-bottom">
      <h5 class="offcanvas-title d-flex align-items-center gap-2" id="copilotConfigDrawerLabel">
        <i class="bi bi-sliders text-primary"></i>
        Scoped Request
      </h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-grid gap-4">
      <div class="card copilot-scope-card mb-0">
        <div class="card-body">
          <div class="alert alert-danger" x-show="error" x-cloak x-text="error"></div>

          <div class="mb-3">
            <label class="form-label text-uppercase text-xs fw-semibold text-muted">Persona</label>
            <select class="form-select form-select-lg" x-model="form.persona">
              <option value="supervisor">Supervisor</option>
              <option value="safety_manager">Safety Manager</option>
              <option value="engineer">Engineer</option>
              <option value="auditor">Auditor</option>
            </select>
          </div>

          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label text-uppercase text-xs fw-semibold text-muted">Window Days</label>
              <input type="number" min="1" max="90" class="form-control form-control-lg" x-model.number="form.window_days">
            </div>
            <div class="col-sm-6">
              <label class="form-label text-uppercase text-xs fw-semibold text-muted">Target Scan ID</label>
              <input type="number" min="1" class="form-control form-control-lg" x-model.number="form.scan_id" placeholder="Optional">
            </div>
            <div class="col-12">
              <label class="form-label text-uppercase text-xs fw-semibold text-muted">Baseline Scan ID</label>
              <input type="number" min="1" class="form-control form-control-lg" x-model.number="form.baseline_scan_id" placeholder="Required for auditor compare mode">
            </div>
          </div>

          <button class="btn btn-primary w-100 mt-4 py-2 fw-bold" @click="run()" :disabled="loading">
            <span class="spinner-border spinner-border-sm me-1" x-show="loading" x-cloak></span>
            <i class="bi bi-magic me-1" x-show="!loading" x-cloak></i>
            <span x-text="loading ? 'Running Copilot...' : 'Run Copilot'"></span>
          </button>
        </div>
      </div>

      <div class="card mb-0">
        <div class="card-header bg-light-subtle border-bottom">
          <p class="mb-0 text-xs fw-bold text-uppercase text-muted">Recent Insights</p>
        </div>
        <div class="list-group list-group-flush">
          <template x-for="(insight, idx) in recentInsights()" :key="idx">
            <div class="list-group-item py-3 d-flex align-items-start gap-3">
              <i class="bi bi-clock-history text-muted mt-1"></i>
              <div>
                <div class="fw-semibold text-sm" x-text="insight.title"></div>
                <div class="text-muted text-xs" x-text="insight.subtitle"></div>
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

