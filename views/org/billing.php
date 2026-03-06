<?php
$pageTitle  = 'Billing';
$activePage = 'billing';
ob_start();
?>
<div x-data="orgBillingPage">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">Billing & Plans</h1>
      <p class="page-breadcrumb">Organization / Billing</p>
    </div>
  </div>

  <!-- Loading -->
  <div class="text-center py-5" x-show="loading" x-cloak>
    <div class="spinner-border text-primary"></div>
  </div>

  <!-- Error -->
  <div x-show="error && !loading" x-cloak>
    <div class="alert alert-danger" x-text="error"></div>
  </div>

  <div x-show="!loading && !error" x-cloak>

    <!-- Current Plan + Usage Row -->
    <div class="row g-4 mb-4">

      <!-- Active Plan Card -->
      <div class="col-md-5">
        <div class="card hero-gradient text-white border-0 h-100">
          <div class="card-body d-flex flex-column">
            <div class="plan-label mb-1">Active Plan</div>
            <h3 class="fw-bold mb-0" x-text="sub.plan_name || 'Free'"></h3>
            <p class="opacity-75 mt-1 mb-3 small">
              <span class="text-capitalize" x-text="sub.billing_cycle || 'Free tier'"></span>
              &nbsp;·&nbsp;
              <span x-text="sub.status === 'active' ? 'Active' : 'Inactive'"></span>
            </p>

            <div class="mb-3" x-show="sub.amount">
              <span class="display-6 fw-bold" x-text="'$' + (sub.amount || '0')"></span>
              <span class="opacity-75 ms-1 small"
                    x-text="'/ ' + (sub.billing_cycle || 'mo')"></span>
            </div>

            <div class="text-sm mb-2" x-show="sub.expires_at">
              <span class="opacity-75">Next renewal</span>
              <span class="fw-semibold ms-1" x-text="fmtDate(sub.expires_at)"></span>
            </div>

            <div class="mt-auto pt-3">
              <button class="btn btn-light btn-sm" disabled>
                <i class="bi bi-check-circle me-1"></i>Current Plan
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Usage Stats -->
      <div class="col-md-7">
        <div class="card h-100">
          <div class="card-header">
            <h6 class="card-title mb-0">Usage This Period</h6>
          </div>
          <div class="card-body d-flex flex-column justify-content-center gap-4">

            <!-- Scans Usage -->
            <div>
              <div class="d-flex justify-content-between align-items-baseline mb-2">
                <span class="fw-medium">Scans</span>
                <span class="text-muted small">
                  <span x-text="sub.scans_used || 0"></span>
                  <span class="mx-1 opacity-50">/</span>
                  <span x-text="sub.scan_limit ? sub.scan_limit : 'Unlimited'"></span>
                </span>
              </div>
              <div class="progress" style="height:8px;">
                <div class="progress-bar"
                     :class="usagePercent >= 90 ? 'bg-danger' : usagePercent >= 70 ? 'bg-warning' : 'bg-primary'"
                     :style="'width:' + usagePercent + '%'"
                     role="progressbar" :aria-valuenow="usagePercent"
                     aria-valuemin="0" aria-valuemax="100"></div>
              </div>
              <div class="d-flex justify-content-end mt-1">
                <span class="text-muted text-xs"
                      x-text="usagePercent + '% used'"></span>
              </div>
            </div>

            <!-- Team members -->
            <div x-show="sub.member_limit">
              <div class="d-flex justify-content-between align-items-baseline mb-2">
                <span class="fw-medium">Team Members</span>
                <span class="text-muted small">
                  <span x-text="sub.members_used || 0"></span> /
                  <span x-text="sub.member_limit || '∞'"></span>
                </span>
              </div>
              <div class="progress" style="height:8px;">
                <div class="progress-bar bg-info"
                     :style="'width:' + Math.min(100,(sub.members_used||0)/(sub.member_limit||1)*100) + '%'"
                     role="progressbar"></div>
              </div>
            </div>

          </div>
        </div>
      </div>

    </div><!-- /row -->

    <!-- Available Plans -->
    <h6 class="fw-semibold mb-3 text-muted">Available Plans</h6>

    <!-- Alert -->
    <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3"
         x-show="changeSuccess" x-cloak x-transition>
      <i class="bi bi-check-circle-fill"></i>Plan changed successfully.
    </div>
    <div class="alert alert-danger py-2 mb-3"
         x-show="changeError" x-text="changeError" x-cloak x-transition></div>

    <!-- Plans grid -->
    <div class="row g-3" x-show="plans.length > 0">
      <template x-for="plan in plans" :key="plan.id">
        <div class="col-md-4">
          <div class="card h-100 position-relative"
               :class="isCurrent(plan) ? 'border-primary shadow-sm' : ''">

            <!-- Current badge -->
            <div x-show="isCurrent(plan)"
                 class="position-absolute top-0 end-0 m-2">
              <span class="badge badge-soft-primary">Current</span>
            </div>

            <div class="card-body d-flex flex-column">
              <h6 class="fw-bold mb-1" x-text="plan.name"></h6>
              <div class="mb-3">
                <span class="fs-4 fw-bold" x-text="plan.price > 0 ? '$' + plan.price : 'Free'"></span>
                <span class="text-muted text-sm"
                      x-show="plan.price > 0" x-text="' / ' + (plan.billing_cycle || 'mo')"></span>
              </div>

              <ul class="list-unstyled mb-4 flex-grow-1 small">
                <li class="mb-1">
                  <i class="bi bi-check2 text-success me-2"></i>
                  <span x-text="plan.scan_limit ? plan.scan_limit + ' scans/period' : 'Unlimited scans'"></span>
                </li>
                <li class="mb-1" x-show="plan.features?.members">
                  <i class="bi bi-check2 text-success me-2"></i>
                  <span x-text="plan.features.members + ' team members'"></span>
                </li>
                <li class="mb-1" x-show="plan.features?.video_processing">
                  <i class="bi bi-check2 text-success me-2"></i>Video processing
                </li>
                <li class="mb-1" x-show="plan.features?.reports">
                  <i class="bi bi-check2 text-success me-2"></i>PDF reports
                </li>
                <li class="mb-1" x-show="plan.features?.api_access">
                  <i class="bi bi-check2 text-success me-2"></i>API access
                </li>
              </ul>

              <button class="btn w-100"
                      :class="isCurrent(plan) ? 'btn-outline-primary' : 'btn-primary'"
                      :disabled="isCurrent(plan) || changing"
                      @click="changePlan(plan.id)">
                <span x-show="changing && !isCurrent(plan)"
                      class="spinner-border spinner-border-sm me-1"></span>
                <span x-text="isCurrent(plan) ? 'Current Plan' : 'Switch to ' + plan.name"></span>
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Empty plans state -->
    <div class="empty-state" x-show="plans.length === 0" x-cloak>
      <div class="empty-state-icon"><i class="bi bi-grid"></i></div>
      <h6>No plans available</h6>
      <p>Contact support to discuss plan options.</p>
    </div>

  </div><!-- /loaded -->
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
