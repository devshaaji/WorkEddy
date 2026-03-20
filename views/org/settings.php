<?php
$pageTitle  = 'Organization Settings';
$activePage = 'org-settings';
ob_start();
?>
<div x-data="orgSettingsPage">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">Organization Settings</h1>
      <p class="page-breadcrumb">Organization / Settings</p>
    </div>
  </div>

  <!-- Loading spinner -->
  <div class="text-center py-5" x-show="loading" x-cloak>
    <div class="spinner-border text-primary"></div>
  </div>

  <div x-show="!loading" x-cloak>

    <div class="row g-4">

      <!-- Organization Profile -->
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-header">
            <h6 class="card-title mb-0">Organization Profile</h6>
          </div>
          <div class="card-body">

            <div class="alert alert-success align-items-center gap-2 py-2"
                 x-show="saveSuccess" x-cloak x-transition style="display:none"
                 :style="saveSuccess ? 'display:flex' : 'display:none'">
              <i class="bi bi-check-circle-fill"></i>
              Settings saved successfully.
            </div>
            <div class="alert alert-danger align-items-center gap-2 py-2"
                 x-show="saveError" x-cloak x-transition style="display:none"
                 :style="saveError ? 'display:flex' : 'display:none'"
                 x-text="saveError"></div>

            <div class="mb-3">
              <label class="form-label" for="orgName">Organization Name <span class="text-danger">*</span></label>
              <input class="form-control" id="orgName" type="text"
                     x-model="form.name" placeholder="Acme Corp">
            </div>
            <div class="mb-3">
              <label class="form-label" for="orgContactEmail">Contact Email</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input class="form-control" id="orgContactEmail" type="email"
                       x-model="form.contact_email" placeholder="admin@acme.com">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="orgIndustry">Industry</label>
              <select class="form-select" id="orgIndustry" x-model="form.industry">
                <option value="">Select industry…</option>
                <option value="manufacturing">Manufacturing</option>
                <option value="logistics">Logistics & Warehousing</option>
                <option value="healthcare">Healthcare</option>
                <option value="construction">Construction</option>
                <option value="retail">Retail</option>
                <option value="office">Office / White-collar</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" for="orgSize">Company Size</label>
              <select class="form-select" id="orgSize" x-model="form.size">
                <option value="">Select size…</option>
                <option value="1-10">1–10 employees</option>
                <option value="11-50">11–50 employees</option>
                <option value="51-200">51–200 employees</option>
                <option value="201-500">201–500 employees</option>
                <option value="500+">500+ employees</option>
              </select>
            </div>
            <div class="mb-4">
              <label class="form-label" for="orgWebsite">Website</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-globe2"></i></span>
                <input class="form-control" id="orgWebsite" type="url"
                       x-model="form.website" placeholder="https://example.com">
              </div>
            </div>

            <div class="d-flex gap-2">
              <button class="btn btn-primary" @click="saveSettings()"
                      :disabled="saving">
                <span x-show="saving" x-cloak class="spinner-border spinner-border-sm me-1"></span>
                <span x-text="saving ? 'Saving…' : 'Save Changes'"></span>
              </button>
              <button class="btn btn-light" @click="resetForm()">Discard</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Current Plan -->
      <div class="col-lg-4">
        <div class="card h-100 hero-gradient text-white border-0">
          <div class="card-body d-flex flex-column">
            <div class="plan-label mb-1">Current Plan</div>
            <h4 class="mb-0 fw-bold" x-text="subscription.plan_name || 'Free'"></h4>
            <p class="mb-3 opacity-75 small">
              <span x-text="subscription.billing_cycle || 'No billing cycle'"></span>
            </p>

            <div class="mb-3" x-show="subscription.scan_limit">
              <div class="d-flex justify-content-between mb-1 text-sm">
                <span class="opacity-90">Scans Used</span>
                <span class="fw-semibold">
                  <span x-text="subscription.scans_used || 0"></span> /
                  <span x-text="subscription.scan_limit || '∞'"></span>
                </span>
              </div>
              <div class="progress" style="height:6px;background:rgba(255,255,255,.25);">
                <div class="progress-bar bg-white"
                     :style="'width:' + Math.min(100, ((subscription.scans_used||0)/(subscription.scan_limit||1))*100) + '%'"></div>
              </div>
            </div>

            <div class="text-sm mb-2" x-show="subscription.expires_at">
              <span class="opacity-75">Renews</span>
              <span class="fw-semibold ms-1" x-text="fmtDate(subscription.expires_at)"></span>
            </div>

            <div class="mt-auto pt-3">
              <a href="/org/billing" class="btn btn-light btn-sm w-100">
                <i class="bi bi-credit-card me-1"></i>Manage Billing
              </a>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /row -->

    <!-- Customization Settings -->
    <div class="card mt-4">
      <div class="card-header">
        <h6 class="card-title mb-0"><i class="bi bi-palette me-2"></i>Customization</h6>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="themeColor">Theme Accent Color</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" id="themeColor"
                     x-model="form.theme_color" title="Choose accent color">
              <input class="form-control" type="text" x-model="form.theme_color"
                     placeholder="#696cff" style="max-width:140px;">
            </div>
            <small class="text-muted">Applied to buttons, links, and active elements across your portal.</small>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="defaultModel">Default Assessment Model</label>
            <select class="form-select" id="defaultModel" x-model="form.default_model">
              <option value="">Platform default (REBA)</option>
              <option value="reba">REBA</option>
              <option value="rula">RULA</option>
              <option value="niosh">NIOSH Lifting Equation</option>
            </select>
            <small class="text-muted">Pre-selects this model when creating new manual or video scans.</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Data & Privacy -->
    <div class="card mt-4">
      <div class="card-header">
        <h6 class="card-title mb-0"><i class="bi bi-shield-check me-2"></i>Data &amp; Privacy</h6>
      </div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label" for="retentionDays">Video Retention (days)</label>
            <input class="form-control" id="retentionDays" type="number" min="0" max="3650"
                   x-model.number="form.video_retention_days" placeholder="30">
            <small class="text-muted">After this period, raw video files are deleted. Use 0 to keep videos indefinitely.</small>
          </div>
          <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
              <div>
                <p class="fw-semibold mb-1">Auto-delete Videos After Processing</p>
                <p class="text-muted text-sm mb-0">Immediately delete the raw video file once analysis is complete. Only metrics and scores are kept.</p>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="autoDeleteVideo"
                       x-model="form.auto_delete_video" style="width:3em;height:1.5em;">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recommendation Policy -->
    <div class="card mt-4">
      <div class="card-header">
        <h6 class="card-title mb-0"><i class="bi bi-sliders me-2"></i>Recommendation Policy (Controls Ranking)</h6>
      </div>
      <div class="card-body">
        <p class="text-muted text-sm mb-3">
          Tune how prescriptive controls are prioritized for your organization.
        </p>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="rpCostPenalty">Cost Penalty Factor</label>
            <input class="form-control" id="rpCostPenalty" type="number" step="0.1" min="0" max="20"
                   x-model.number="form.recommendation_policy.ranking.cost_penalty_factor">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="rpImpactPenalty">Throughput Impact Penalty</label>
            <input class="form-control" id="rpImpactPenalty" type="number" step="0.1" min="0" max="20"
                   x-model.number="form.recommendation_policy.ranking.impact_penalty_factor">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="rpReductionFactor">Risk Reduction Factor</label>
            <input class="form-control" id="rpReductionFactor" type="number" step="0.1" min="0" max="20"
                   x-model.number="form.recommendation_policy.ranking.reduction_factor">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="rpMinFeasible">Minimum Feasibility Score (%)</label>
            <input class="form-control" id="rpMinFeasible" type="number" step="1" min="0" max="100"
                   x-model.number="form.recommendation_policy.feasibility.minimum_total_score">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="rpMinPolicy">Minimum Policy Compliance (%)</label>
            <input class="form-control" id="rpMinPolicy" type="number" step="1" min="0" max="100"
                   x-model.number="form.recommendation_policy.feasibility.minimum_policy_compliance">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="rpInterimDays">Max Days Without Interim Control</label>
            <input class="form-control" id="rpInterimDays" type="number" step="1" min="1" max="180"
                   x-model.number="form.recommendation_policy.interim.max_days_without_interim">
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch mt-4 pt-2">
              <input class="form-check-input" type="checkbox" role="switch" id="rpStrictHierarchy"
                     x-model="form.recommendation_policy.ranking.strict_hierarchy">
              <label class="form-check-label" for="rpStrictHierarchy">Require highest feasible hierarchy first</label>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch mt-4 pt-2">
              <input class="form-check-input" type="checkbox" role="switch" id="rpAllowPpeInterim"
                     x-model="form.recommendation_policy.interim.allow_ppe_interim">
              <label class="form-check-label" for="rpAllowPpeInterim">Allow PPE as interim only</label>
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="rpTrunkHigh">Trunk Flexion High Threshold (°)</label>
            <input class="form-control" id="rpTrunkHigh" type="number" step="1" min="0" max="180"
                   x-model.number="form.recommendation_policy.thresholds.trunk_flexion_high">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="rpTrunkModerate">Trunk Flexion Moderate Threshold (°)</label>
            <input class="form-control" id="rpTrunkModerate" type="number" step="1" min="0" max="180"
                   x-model.number="form.recommendation_policy.thresholds.trunk_flexion_moderate">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="rpUpperArmHigh">Upper Arm Elevation High Threshold (°)</label>
            <input class="form-control" id="rpUpperArmHigh" type="number" step="1" min="0" max="180"
                   x-model.number="form.recommendation_policy.thresholds.upper_arm_elevation_high">
          </div>

          <div class="col-md-4">
            <label class="form-label" for="rpRepetitionHigh">High Repetition Threshold</label>
            <input class="form-control" id="rpRepetitionHigh" type="number" step="1" min="0" max="1000"
                   x-model.number="form.recommendation_policy.thresholds.repetition_high">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="rpLiftingLoad">Lifting Load Threshold (kg)</label>
            <input class="form-control" id="rpLiftingLoad" type="number" step="0.1" min="0" max="200"
                   x-model.number="form.recommendation_policy.thresholds.lifting_load">
          </div>
        </div>
      </div>
    </div>

    <!-- Account Details -->
    <div class="card mt-4">
      <div class="card-header">
        <h6 class="card-title mb-0">Account Details</h6>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <dl class="mb-0">
              <dt class="text-muted fw-normal text-sm">Organization ID</dt>
              <dd class="fw-semibold mb-3" x-text="org.id || '—'"></dd>
              <dt class="text-muted fw-normal text-sm">Created At</dt>
              <dd class="fw-semibold mb-0" x-text="fmtDate(org.created_at) || '—'"></dd>
            </dl>
          </div>
          <div class="col-md-6">
            <dl class="mb-0">
              <dt class="text-muted fw-normal text-sm">Plan Status</dt>
              <dd class="mb-3">
                <span class="badge"
                      :class="subscription.status === 'active' ? 'badge-soft-success' : 'badge-soft-secondary'"
                      x-text="subscription.status || 'inactive'"></span>
              </dd>
              <dt class="text-muted fw-normal text-sm">Members</dt>
              <dd class="fw-semibold mb-0" x-text="(org.member_count || '0') + ' users'"></dd>
            </dl>
          </div>
        </div>
      </div>
    </div>

    <!-- Security & 2FA Info -->
    <div class="card mt-4">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
             style="width:48px;height:48px;min-width:48px;">
          <i class="bi bi-shield-lock fs-4 text-primary"></i>
        </div>
        <div class="flex-grow-1">
          <p class="fw-semibold mb-1">Two-Factor Authentication</p>
          <p class="text-muted text-sm mb-0">Manage 2FA and personal security settings from your profile page.</p>
        </div>
        <a href="/profile" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-person me-1"></i> Go to Profile
        </a>
      </div>
    </div>

  </div><!-- /x-show -->
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
