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

            <div class="alert alert-success d-flex align-items-center gap-2 py-2"
                 x-show="saveSuccess" x-cloak x-transition>
              <i class="bi bi-check-circle-fill"></i>
              Settings saved successfully.
            </div>
            <div class="alert alert-danger align-items-center gap-2 py-2"
                 x-show="saveError" x-text="saveError" x-cloak x-transition></div>

            <div class="mb-3">
              <label class="form-label" for="orgName">Organization Name <span class="text-danger">*</span></label>
              <input class="form-control" id="orgName" type="text"
                     x-model="form.name" placeholder="Acme Corp">
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
                <span x-show="saving" class="spinner-border spinner-border-sm me-1"></span>
                Save Changes
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
              <a href="/billing" class="btn btn-light btn-sm w-100">
                <i class="bi bi-credit-card me-1"></i>Manage Billing
              </a>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /row -->

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

  </div><!-- /x-show -->
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
