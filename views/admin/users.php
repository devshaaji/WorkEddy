<?php
$pageTitle = 'Global Users';
$activePage = 'admin-users';
ob_start();
?>
<div x-data="adminUsersPage">

  <?php
  $headerTitle = 'Global Users';
  $headerBreadcrumb = 'Admin / Users';
  $headerActionsHtml = '<span class="badge badge-soft-secondary px-3 py-2 text-sm" x-text="users.length + \' total\'"></span>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <div class="card">

    <!-- Toolbar -->
    <div class="table-toolbar">
      <div class="search-box">
        <i class="bi bi-search"></i>
        <input class="form-control" type="search" placeholder="Search by name or email…" x-model="search">
      </div>
      <div class="toolbar-right">
        <select class="form-select form-select-sm" x-model="filterRole" style="width:auto;min-width:110px;">
          <option value="">All Roles</option>
          <option value="super_admin">Super Admin</option>
          <option value="admin">Admin</option>
          <option value="supervisor">Supervisor</option>
          <option value="worker">Worker</option>
          <option value="observer">Observer</option>
        </select>
        <select class="form-select form-select-sm" x-model="filterStatus" style="width:auto;min-width:110px;">
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
          <option value="invited">Invited</option>
        </select>
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
      <div class="empty-state-icon"><i class="bi bi-people"></i></div>
      <h6>No users found</h6>
      <p>Try adjusting your search or filter criteria.</p>
    </div>

    <!-- Table -->
    <div class="table-responsive" x-show="!loading && !error && filtered.length > 0" x-cloak>
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Name</th>
            <th class="d-none d-md-table-cell">Email</th>
            <th class="d-none d-lg-table-cell">Organization</th>
            <th>Role</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="u in filtered" :key="u.id">
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar avatar-sm" :class="'avatar-' + (u.role === 'admin' ? 'primary'
                                          : u.role === 'supervisor' ? 'info'
                                          : u.role === 'worker' ? 'secondary'
                                          : 'warning')"
                    x-text="(u.name || '?').split(' ').map(w => w[0]).join('').toUpperCase().slice(0,2)">
                  </div>
                  <span class="fw-medium" x-text="u.name"></span>
                </div>
              </td>
              <td class="d-none d-md-table-cell text-muted" x-text="u.email"></td>
              <td class="d-none d-lg-table-cell">
                <span class="badge badge-soft-secondary" x-text="u.org_name"></span>
              </td>
              <td>
                <span class="badge text-capitalize" :class="roleBadge(u.role)" x-text="u.role"></span>
              </td>
              <td>
                <span class="badge" :class="(u.status || 'active') === 'active'   ? 'badge-soft-success'
                            : (u.status || 'active') === 'invited'  ? 'badge-soft-info'
                            : 'badge-soft-secondary'" x-text="u.status || 'active'"></span>
              </td>
              <td class="text-end">
                <div class="dropdown">
                  <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <button class="dropdown-item" @click="openEdit(u)">
                        <i class="bi bi-pencil me-2 text-muted"></i>Edit
                      </button>
                    </li>
                    <li>
                      <hr class="dropdown-divider my-1">
                    </li>
                    <li>
                      <button class="dropdown-item text-danger" @click="requestDelete(u)"
                        :disabled="deletingUserLoading && deletingUserId === u.id">
                        <span class="spinner-border spinner-border-sm me-2"
                          x-show="deletingUserLoading && deletingUserId === u.id" x-cloak></span>
                        <i class="bi bi-trash me-2" x-show="!(deletingUserLoading && deletingUserId === u.id)"
                          x-cloak></i>
                        <span x-text="deletingUserLoading && deletingUserId === u.id ? 'Deleting…' : 'Delete'"></span>
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
      <span class="text-muted text-sm" x-text="'Showing ' + filtered.length + ' of ' + users.length + ' users'"></span>
    </div>

  </div><!-- /card -->

  <!-- Edit Modal -->
  <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger py-2" x-show="formError" x-text="formError" x-cloak></div>
          <div class="mb-3">
            <label class="form-label" for="editName">Full Name</label>
            <input class="form-control" id="editName" type="text" x-model="editForm.name">
          </div>
          <div class="mb-3">
            <label class="form-label" for="editEmail">Email</label>
            <input class="form-control" id="editEmail" type="email" x-model="editForm.email">
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label" for="editRole">Role</label>
              <select class="form-select" id="editRole" x-model="editForm.role">
                <option value="super_admin">Super Admin</option>
                <option value="admin">Admin</option>
                <option value="supervisor">Supervisor</option>
                <option value="worker">Worker</option>
                <option value="observer">Observer</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label" for="editStatus">Status</label>
              <select class="form-select" id="editStatus" x-model="editForm.status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="invited">Invited</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" @click="saveUser()" :disabled="savingUser">
            <span class="spinner-border spinner-border-sm me-1" x-show="savingUser" x-cloak></span>
            <span x-text="savingUser ? 'Updating…' : 'Update'"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
