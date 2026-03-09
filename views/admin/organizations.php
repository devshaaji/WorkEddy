<?php
$pageTitle  = 'Organizations';
$activePage = 'admin-orgs';
ob_start();
?>
<div x-data="adminOrgsPage">

  <?php
  $headerTitle = 'Organizations';
  $headerBreadcrumb = 'Admin / Organizations';
  $headerActionsHtml = '<button class="btn btn-primary" @click="openCreate()"><i class="bi bi-plus-lg me-1"></i>New Organization</button>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <div class="card">

    <!-- Toolbar -->
    <div class="table-toolbar">
      <div class="search-box">
        <i class="bi bi-search"></i>
        <input class="form-control" type="search"
               placeholder="Search organizations…" x-model="search">
      </div>
      <div class="toolbar-right">
        <span class="text-muted d-none d-md-inline text-sm"
              x-text="filtered.length + ' organization' + (filtered.length === 1 ? '' : 's')"></span>
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
    <div class="empty-state" x-show="!loading && !error && filtered.length === 0" x-cloak>
      <div class="empty-state-icon"><i class="bi bi-building"></i></div>
      <h6 x-text="search ? 'No organizations match your search' : 'No organizations yet'"></h6>
      <p>Organizations are created when users sign up for a workspace.</p>
    </div>

    <!-- Table -->
    <div class="table-responsive"
         x-show="!loading && !error && filtered.length > 0" x-cloak>
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Organization</th>
            <th>Plan</th>
            <th class="d-none d-md-table-cell">Users</th>
            <th>Status</th>
            <th class="d-none d-lg-table-cell">Joined</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="org in filtered" :key="org.id">
            <tr>
              <td>
                <span class="fw-semibold" x-text="org.name"></span>
                <br>
                <span class="text-muted text-sm" x-text="org.slug"></span>
              </td>
              <td>
                <span class="badge badge-soft-primary text-capitalize"
                      x-text="org.active_plan || org.plan || '—'"></span>
              </td>
              <td class="d-none d-md-table-cell" x-text="org.user_count ?? '—'"></td>
              <td>
                <span class="badge"
                      :class="org.status === 'active'    ? 'badge-soft-success'
                            : org.status === 'suspended' ? 'badge-soft-danger'
                            : 'badge-soft-secondary'"
                      x-text="org.status"></span>
              </td>
              <td class="d-none d-lg-table-cell text-muted"
                  x-text="fmtDate(org.created_at)"></td>
              <td class="text-end">
                <div class="dropdown">
                  <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <button class="dropdown-item" @click="openEdit(org)">
                        <i class="bi bi-pencil me-2 text-muted"></i>Edit
                      </button>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                      <button class="dropdown-item"
                              :class="org.status === 'active' ? 'text-warning' : 'text-success'"
                              :disabled="togglingOrgId === org.id"
                              @click="toggleStatus(org)">
                        <span class="spinner-border spinner-border-sm me-2" x-show="togglingOrgId === org.id" x-cloak></span>
                        <i class="bi me-2" x-show="togglingOrgId !== org.id" x-cloak
                           :class="org.status === 'active' ? 'bi-pause-circle' : 'bi-play-circle'"></i>
                        <span x-text="org.status === 'active' ? 'Suspend' : 'Activate'"></span>
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
    <div class="card-footer d-flex justify-content-between align-items-center py-2"
         x-show="!loading && !error && filtered.length > 0" x-cloak>
      <span class="text-muted text-sm"
            x-text="'Showing ' + filtered.length + ' of ' + orgs.length + ' organizations'"></span>
    </div>

  </div><!-- /card -->

  <!-- Create / Edit Modal -->
  <div class="modal fade" id="orgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" x-text="editingOrg ? 'Edit Organization' : 'New Organization'"></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger py-2" x-show="formError" x-text="formError" x-cloak></div>
          <div class="mb-3">
            <label class="form-label" for="orgName">Organization Name <span class="text-danger">*</span></label>
            <input class="form-control" id="orgName" type="text"
                   x-model="form.name" placeholder="e.g. Acme Logistics">
          </div>
          <div class="mb-3">
            <label class="form-label" for="orgEmail">Contact Email</label>
            <input class="form-control" id="orgEmail" type="email"
                   x-model="form.contact_email" placeholder="billing@acme.com">
          </div>
          <div class="mb-1" x-show="editingOrg" x-cloak>
            <label class="form-label" for="orgStatus">Status</label>
            <select class="form-select" id="orgStatus" x-model="form.status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" @click="saveOrg()" :disabled="savingOrg">
            <span class="spinner-border spinner-border-sm me-1" x-show="savingOrg" x-cloak></span>
            <span x-text="savingOrg ? 'Saving…' : (editingOrg ? 'Update' : 'Create')"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
