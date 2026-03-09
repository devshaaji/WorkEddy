<?php
$pageTitle  = 'Subscription Plans';
$activePage = 'admin-plans';
ob_start();
?>
<div x-data="adminPlansPage">

  <?php
  $headerTitle = 'Subscription Plans';
  $headerBreadcrumb = 'Admin / Plans';
  $headerActionsHtml = '<button class="btn btn-primary" @click="openCreate()"><i class="bi bi-plus-lg me-1"></i>New Plan</button>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <div class="card">

    <!-- Toolbar -->
    <div class="table-toolbar">
      <div class="search-box">
        <i class="bi bi-search"></i>
        <input class="form-control" type="search"
               placeholder="Search plans…" x-model="search">
      </div>
      <div class="toolbar-right">
        <span class="text-muted d-none d-md-inline text-sm"
              x-text="plans.length + ' plan' + (plans.length === 1 ? '' : 's')"></span>
      </div>
    </div>

    <!-- Loading -->
    <div class="card-body text-center py-5" x-show="loading" x-cloak>
      <div class="spinner-border text-primary"></div>
    </div>

    <!-- Error -->
    <div class="card-body" x-show="error && !loading" x-cloak>
      <div class="alert alert-danger mb-0" x-text="error"></div>
    </div>

    <!-- Empty state -->
    <div class="empty-state" x-show="!loading && !error && plans.length === 0" x-cloak>
      <div class="empty-state-icon"><i class="bi bi-grid"></i></div>
      <h6>No plans yet</h6>
      <p>Create your first subscription plan to start onboarding organizations.</p>
      <button class="btn btn-primary btn-sm" @click="openCreate()">
        <i class="bi bi-plus-lg me-1"></i>New Plan
      </button>
    </div>

    <!-- Table -->
    <div class="table-responsive"
         x-show="!loading && !error && plans.length > 0" x-cloak>
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Name</th>
            <th>Price</th>
            <th class="d-none d-md-table-cell">Scan Limit</th>
            <th class="d-none d-md-table-cell">Billing Cycle</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="plan in filtered" :key="plan.id">
            <tr>
              <td>
                <span class="fw-semibold" x-text="plan.name"></span>
              </td>
              <td>
                <span x-text="plan.price > 0 ? '$' + plan.price : 'Free'"></span>
              </td>
              <td class="d-none d-md-table-cell text-muted"
                  x-text="plan.scan_limit ? plan.scan_limit.toLocaleString() : 'Unlimited'"></td>
              <td class="d-none d-md-table-cell">
                <span class="text-capitalize" x-text="plan.billing_cycle || '—'"></span>
              </td>
              <td>
                <span class="badge"
                      :class="plan.status === 'active' ? 'badge-soft-success' : 'badge-soft-secondary'"
                      x-text="plan.status"></span>
              </td>
              <td class="text-end">
                <div class="dropdown">
                  <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <button class="dropdown-item" @click="openEdit(plan)">
                        <i class="bi bi-pencil me-2 text-muted"></i>Edit
                      </button>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                      <button class="dropdown-item text-danger"
                              @click="requestDelete(plan)"
                              :disabled="deleting && deletingPlanId === plan.id">
                        <span class="spinner-border spinner-border-sm me-2"
                              x-show="deleting && deletingPlanId === plan.id" x-cloak></span>
                        <i class="bi bi-trash me-2" x-show="!(deleting && deletingPlanId === plan.id)" x-cloak></i>
                        <span x-text="deleting && deletingPlanId === plan.id ? 'Deleting…' : 'Delete'"></span>
                      </button>
                    </li>
                  </ul>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Footer -->
    <div class="card-footer py-2"
         x-show="!loading && !error && plans.length > 0" x-cloak>
      <span class="text-muted text-sm"
            x-text="plans.length + ' plan' + (plans.length === 1 ? '' : 's') + ' total'"></span>
    </div>

  </div><!-- /card -->

  <!-- Create / Edit Plan Modal -->
  <div class="modal fade" id="planModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" x-text="editingPlan ? 'Edit Plan' : 'New Plan'"></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger py-2" x-show="formError" x-text="formError" x-cloak></div>
          <div class="mb-3">
            <label class="form-label" for="planName">Plan Name <span class="text-danger">*</span></label>
            <input class="form-control" id="planName" type="text"
                   x-model="form.name" placeholder="e.g. Starter, Pro, Enterprise">
          </div>
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label" for="planPrice">Price (USD) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input class="form-control" id="planPrice" type="number"
                       min="0" step="0.01" x-model="form.price" placeholder="0.00">
              </div>
            </div>
            <div class="col-sm-6">
              <label class="form-label" for="planCycle">Billing Cycle</label>
              <select class="form-select" id="planCycle" x-model="form.billing_cycle">
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="planScanLimit">Scan Limit</label>
            <input class="form-control" id="planScanLimit" type="number"
                   min="0" x-model="form.scan_limit" placeholder="Leave blank for unlimited">
            <div class="form-text">Leave empty for unlimited scans.</div>
          </div>
          <div class="mb-1">
            <label class="form-label" for="planStatus">Status</label>
            <select class="form-select" id="planStatus" x-model="form.status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" @click="savePlan()" :disabled="saving">
            <span x-show="saving" class="spinner-border spinner-border-sm me-1"></span>
            <span x-text="editingPlan ? 'Save Changes' : 'Create Plan'"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
