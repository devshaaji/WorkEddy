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
              &nbsp;&#183;&nbsp;
              <span x-text="sub.status === 'active' ? 'Active' : 'Inactive'"></span>
            </p>

            <div class="mb-3" x-show="sub.amount">
              <span class="display-6 fw-bold" x-text="fmtMoney(sub.amount || 0)"></span>
              <span class="opacity-75 ms-1 small"
                    x-text="'/ ' + (sub.billing_cycle || 'mo')"></span>
            </div>

            <div class="text-sm mb-2" x-show="sub.expires_at">
              <span class="opacity-75">Next renewal</span>
              <span class="fw-semibold ms-1" x-text="fmtDate(sub.expires_at)"></span>
            </div>

            <div class="text-sm mb-2">
              <span class="opacity-75">Usage period</span>
              <span class="fw-semibold ms-1" x-text="usagePeriodLabel()"></span>
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
            <div class="d-flex justify-content-between align-items-center gap-2">
              <h6 class="card-title mb-0">Usage This Period</h6>
              <span class="badge badge-soft-secondary text-capitalize" x-text="sub.billing_cycle || 'monthly'"></span>
            </div>
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

            <!-- Usage breakdown -->
            <div class="row g-3">
              <div class="col-sm-4">
                <div class="p-3 bg-light rounded-3 h-100">
                  <div class="text-xs text-muted mb-1">Billed Scans</div>
                  <div class="fw-semibold fs-5" x-text="sub.billed_scans ?? sub.scans_used ?? 0"></div>
                </div>
              </div>
              <div class="col-sm-4">
                <div class="p-3 bg-light rounded-3 h-100">
                  <div class="text-xs text-muted mb-1">Reserved Scans</div>
                  <div class="fw-semibold fs-5" x-text="sub.reserved_scans ?? 0"></div>
                </div>
              </div>
              <div class="col-sm-4">
                <div class="p-3 bg-light rounded-3 h-100">
                  <div class="text-xs text-muted mb-1">Remaining</div>
                  <div class="fw-semibold fs-5" x-text="sub.usage?.remaining ?? 'Unlimited'"></div>
                </div>
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

    <div class="alert alert-warning d-flex align-items-start gap-2 mb-4"
         x-show="violationsSummary()" x-cloak>
      <i class="bi bi-exclamation-triangle-fill mt-1"></i>
      <div>
        <div class="fw-semibold">Plan guardrails need attention</div>
        <div class="small text-muted" x-text="violationsSummary()"></div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div>
          <h6 class="card-title mb-0">Operational Watchlist</h6>
          <p class="text-muted text-sm mb-0">This is the billing surface we now track across workers, copilot usage, retention, and organization capacity.</p>
        </div>
        <span class="badge badge-soft-secondary" x-text="usagePeriodLabel()"></span>
      </div>
      <div class="card-body p-0">
        <div class="list-group list-group-flush">
          <template x-for="item in usageWatchItems()" :key="item.key">
            <div class="list-group-item py-3">
              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                  <div class="fw-semibold" x-text="item.label"></div>
                  <div class="text-muted text-sm" x-text="item.help"></div>
                </div>
                <span class="badge" :class="item.badgeClass" x-text="item.status"></span>
              </div>

              <div class="row g-3 mt-1 text-sm">
                <div class="col-sm-3">
                  <div class="text-muted text-xs mb-1">Used</div>
                  <div class="fw-semibold" x-text="item.used"></div>
                </div>
                <div class="col-sm-3">
                  <div class="text-muted text-xs mb-1">Reserved</div>
                  <div class="fw-semibold" x-text="item.reserved"></div>
                </div>
                <div class="col-sm-3">
                  <div class="text-muted text-xs mb-1">Limit</div>
                  <div class="fw-semibold" x-text="item.limit"></div>
                </div>
                <div class="col-sm-3">
                  <div class="text-muted text-xs mb-1">Remaining</div>
                  <div class="fw-semibold" x-text="item.remaining"></div>
                </div>
              </div>

              <div class="mt-3" x-show="item.showProgress">
                <div class="progress" style="height:8px;">
                  <div class="progress-bar"
                       :class="item.progressClass"
                       :style="'width:' + item.percent + '%'"
                       role="progressbar"
                       :aria-valuenow="item.percent"
                       aria-valuemin="0"
                       aria-valuemax="100"></div>
                </div>
                <div class="d-flex justify-content-end mt-1">
                  <span class="text-muted text-xs" x-text="item.percent + '% consumed'"></span>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Available Plans -->
    <h6 class="fw-semibold mb-3 text-muted">Available Plans</h6>

    <!-- Alert -->
    <div class="alert alert-success align-items-center gap-2 py-2 mb-3"
         x-show="changeSuccess" x-cloak x-transition style="display:none"
         :style="changeSuccess ? 'display:flex' : 'display:none'">
      <i class="bi bi-check-circle-fill"></i>Plan changed successfully.
    </div>
    <div class="alert alert-danger py-2 mb-3"
         x-show="changeError" x-text="changeError" x-cloak x-transition style="display:none"></div>

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
                <template x-for="item in planHighlights(plan)" :key="item">
                  <li class="mb-1">
                    <i class="bi bi-check2 text-success me-2"></i>
                    <span x-text="item"></span>
                  </li>
                </template>
              </ul>

              <button class="btn w-100"
                      :class="isCurrent(plan) ? 'btn-outline-primary' : 'btn-primary'"
                    :disabled="isCurrent(plan) || changing"
                      @click="changePlan(plan.id)">
                  <span x-show="changingPlanId === String(plan.id)"
                      class="spinner-border spinner-border-sm me-1"></span>
                  <span x-text="isCurrent(plan) ? 'Current Plan' : (changingPlanId === String(plan.id) ? 'Switching...' : 'Switch to ' + plan.name)"></span>
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

    <!-- Invoices -->
    <div class="card mt-4">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h6 class="card-title mb-0">Invoices</h6>
          <p class="text-muted text-sm mb-0" x-text="usagePeriodLabel()"></p>
        </div>
        <div class="d-flex align-items-center gap-2" x-show="$store.auth.role === 'admin'">
          <input type="text"
                 class="form-control form-control-sm"
                 style="min-width:260px"
                 placeholder="Optional payment token"
                 x-model.trim="paymentToken">
        </div>
      </div>

      <div class="card-body pt-3">
        <div class="alert alert-success py-2 mb-3"
             x-show="chargeSuccess" x-cloak x-transition style="display:none"
             x-text="chargeSuccess"></div>
        <div class="alert alert-danger py-2 mb-3"
             x-show="chargeError" x-cloak x-transition style="display:none"
             x-text="chargeError"></div>

        <div class="empty-state py-4" x-show="invoices.length === 0" x-cloak>
          <div class="empty-state-icon"><i class="bi bi-receipt"></i></div>
          <h6>No invoices yet</h6>
          <p>Invoices will appear when your plan period is created.</p>
        </div>

        <div class="table-responsive" x-show="invoices.length > 0" x-cloak>
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Invoice</th>
                <th class="d-none d-md-table-cell">Plan</th>
                <th class="d-none d-lg-table-cell">Period</th>
                <th>Amount</th>
                <th>Status</th>
                <th class="d-none d-lg-table-cell">Created</th>
                <th class="d-none d-lg-table-cell">Paid</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="inv in invoices" :key="inv.id">
                <tr>
                  <td>
                    <div class="fw-semibold" x-text="'#' + inv.id"></div>
                    <div class="text-muted text-xs d-md-none" x-text="inv.plan_name || 'Plan'"></div>
                  </td>
                  <td class="d-none d-md-table-cell">
                    <span x-text="inv.plan_name || '-'"></span>
                  </td>
                  <td class="d-none d-lg-table-cell">
                    <span x-text="fmtDate(inv.period_start)"></span>
                    <span class="mx-1 text-muted">-</span>
                    <span x-text="fmtDate(inv.period_end)"></span>
                  </td>
                  <td>
                    <span class="fw-semibold" x-text="fmtMoney(inv.amount, inv.currency)"></span>
                  </td>
                  <td>
                    <span class="badge text-capitalize" :class="invoiceStatusClass(inv.status)" x-text="inv.status || 'pending'"></span>
                  </td>
                  <td class="d-none d-lg-table-cell text-muted" x-text="fmtDateTime(inv.created_at)"></td>
                  <td class="d-none d-lg-table-cell text-muted" x-text="inv.paid_at ? fmtDateTime(inv.paid_at) : '-'"></td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-primary"
                            x-show="$store.auth.role === 'admin' && String(inv.status || '').toLowerCase() !== 'paid'"
                            :disabled="chargingInvoiceId === String(inv.id)"
                            @click="chargeInvoice(inv.id)">
                      <span class="spinner-border spinner-border-sm me-1"
                            x-show="chargingInvoiceId === String(inv.id)" x-cloak></span>
                      <span x-text="chargingInvoiceId === String(inv.id) ? 'Charging...' : 'Charge'"></span>
                    </button>
                    <span class="text-muted text-sm"
                          x-show="String(inv.status || '').toLowerCase() === 'paid'">Paid</span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /loaded -->
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
