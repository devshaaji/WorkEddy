<?php
$pageTitle = 'Team Members';
$activePage = 'org-users';
ob_start();
?>
<div x-data="orgUsersPage">

  <?php
  $headerTitle = 'Team Members';
  $headerBreadcrumb = 'Organization / Team';
  $headerActionsHtml = '<button class="btn btn-primary" @click="openInvite()"><i class="bi bi-person-plus me-1"></i>Invite Member</button>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <div class="card">

    <!-- Toolbar -->
    <div class="table-toolbar">
      <div class="search-box">
        <i class="bi bi-search"></i>
        <input class="form-control" type="search" placeholder="Search members…" x-model="memberSearch">
      </div>
      <div class="toolbar-right">
        <span class="text-muted d-none d-md-inline text-sm"
          x-text="members.length + ' member' + (members.length === 1 ? '' : 's')"></span>
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
    <div class="empty-state" x-show="!loading && !error && members.length === 0" x-cloak>
      <div class="empty-state-icon"><i class="bi bi-people"></i></div>
      <h6>No team members yet</h6>
      <p>Invite colleagues to collaborate on ergonomic assessments.</p>
      <button class="btn btn-primary btn-sm" @click="openInvite()">
        <i class="bi bi-person-plus me-1"></i>Invite Member
      </button>
    </div>

    <!-- Table -->
    <div class="table-responsive" x-show="!loading && !error && members.length > 0" x-cloak>
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Member</th>
            <th class="d-none d-md-table-cell">Email</th>
            <th>Role</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="m in filteredMembers" :key="m.id">
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar avatar-sm" :class="'avatar-' + (m.role === 'admin' ? 'primary'
                                          : m.role === 'supervisor' ? 'info'
                                          : 'secondary')"
                    x-text="(m.name||'?').split(' ').map(w=>w[0]).join('').toUpperCase().slice(0,2)">
                  </div>
                  <span class="fw-medium" x-text="m.name"></span>
                </div>
              </td>
              <td class="d-none d-md-table-cell text-muted" x-text="m.email"></td>
              <td>
                <span class="badge text-capitalize" :class="roleBadge(m.role)" x-text="m.role"></span>
              </td>
              <td>
                <span class="badge"
                  :class="(m.status||'active') === 'active' ? 'badge-soft-success' : 'badge-soft-secondary'"
                  x-text="m.status || 'active'"></span>
              </td>
              <td class="text-end">
                <div class="dropdown">
                  <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <button class="dropdown-item" @click="openRoleEdit(m)">
                        <i class="bi bi-shield me-2 text-muted"></i>Change Role
                      </button>
                    </li>
                    <li>
                      <hr class="dropdown-divider my-1">
                    </li>
                    <li>
                      <button class="dropdown-item text-danger" @click="requestRemove(m)"
                        :disabled="removeSaving && removingMemberId === m.id">
                        <span class="spinner-border spinner-border-sm me-2"
                          x-show="removeSaving && removingMemberId === m.id" x-cloak></span>
                        <i class="bi bi-person-dash me-2" x-show="!(removeSaving && removingMemberId === m.id)"
                          x-cloak></i>
                        <span x-text="removeSaving && removingMemberId === m.id ? 'Removing…' : 'Remove'"></span>
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
    <div class="card-footer py-2" x-show="!loading && !error && members.length > 0" x-cloak>
      <span class="text-muted text-sm" x-text="members.length + ' member' + (members.length === 1 ? '' : 's')"></span>
    </div>

  </div><!-- /card -->

  <!-- Invite Modal -->
  <div class="modal fade" id="inviteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Invite Team Member</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger py-2" x-show="formError" x-text="formError" x-cloak></div>
          <div class="mb-3">
            <label class="form-label" for="invName">Full Name <span class="text-danger">*</span></label>
            <input class="form-control" id="invName" type="text" x-model="inviteForm.name" placeholder="John Smith">
          </div>
          <div class="mb-3">
            <label class="form-label" for="invEmail">Email <span class="text-danger">*</span></label>
            <input class="form-control" id="invEmail" type="email" x-model="inviteForm.email"
              placeholder="john@company.com">
          </div>
          <div class="mb-3">
            <label class="form-label" for="invPass">Temporary Password <span class="text-danger">*</span></label>
            <input class="form-control" id="invPass" type="text" x-model="inviteForm.password"
              placeholder="Min 8 characters">
            <div class="form-text">Member should change this after first login.</div>
          </div>
          <div class="mb-1">
            <label class="form-label" for="invRole">Role</label>
            <select class="form-select" id="invRole" x-model="inviteForm.role">
              <option value="supervisor">Supervisor</option>
              <option value="worker">Worker</option>
              <option value="observer">Observer</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" @click="sendInvite()" :disabled="inviteSending">
            <span class="spinner-border spinner-border-sm me-1" x-show="inviteSending" x-cloak></span>
            <i class="bi bi-send me-1" x-show="!inviteSending" x-cloak></i>
            <span x-text="inviteSending ? 'Sending…' : 'Send Invite'"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Change Role Modal -->
  <div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">Change Role</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2 text-muted small">
            Update role for <strong x-text="editingMember?.name"></strong>:
          </p>
          <select class="form-select" x-model="newRole">
            <option value="admin">Admin</option>
            <option value="supervisor">Supervisor</option>
            <option value="worker">Worker</option>
            <option value="observer">Observer</option>
          </select>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" @click="saveRole()" :disabled="roleSaving">
            <span class="spinner-border spinner-border-sm me-1" x-show="roleSaving" x-cloak></span>
            <span x-text="roleSaving ? 'Updating…' : 'Update'"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
