<?php
$pageTitle  = 'Tasks';
$activePage = 'tasks';
ob_start();
?>
<div x-data="tasksPage">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">Tasks</h1>
      <p class="page-breadcrumb">Home / Tasks</p>
    </div>
    <button class="btn btn-primary" @click="openModal()">
      <i class="bi bi-plus-lg me-1"></i>New Task
    </button>
  </div>

  <!-- Card with toolbar + table -->
  <div class="card">

    <!-- Toolbar -->
    <div class="table-toolbar">
      <div class="search-box">
        <i class="bi bi-search"></i>
        <input class="form-control" type="search"
               placeholder="Search tasks…" x-model="search">
      </div>
      <div class="toolbar-right">
        <span class="text-muted d-none d-md-inline text-sm"
              x-text="filtered.length + ' task' + (filtered.length === 1 ? '' : 's')"></span>
      </div>
    </div>

    <!-- Loading -->
    <div class="text-center py-5" x-show="loading" x-cloak>
      <div class="spinner-border text-primary"></div>
    </div>

    <!-- Error -->
    <div class="card-body" x-show="error && !loading" x-cloak>
      <div class="alert alert-danger mb-0" x-text="error"></div>
    </div>

    <!-- Empty state -->
    <div class="empty-state" x-show="!loading && !error && filtered.length === 0" x-cloak>
      <div class="empty-state-icon"><i class="bi bi-list-task"></i></div>
      <h6 x-text="search ? 'No tasks match your search' : 'No tasks yet'"></h6>
      <p x-text="search ? 'Try a different keyword.' : 'Create your first ergonomic task to start scanning.'"></p>
      <button class="btn btn-primary btn-sm" @click="openModal()" x-show="!search">
        <i class="bi bi-plus-lg me-1"></i>New Task
      </button>
    </div>

    <!-- Table -->
    <div class="table-responsive"
         x-show="!loading && !error && filtered.length > 0" x-cloak>
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Task name</th>
            <th class="d-none d-md-table-cell">Department</th>
            <th class="d-none d-lg-table-cell">Created</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="t in filtered" :key="t.id">
            <tr>
              <td>
                <a :href="'/tasks/' + t.id"
                   class="fw-semibold text-decoration-none"
                   x-text="t.name"></a>
                <div class="text-muted text-sm" x-text="t.description"></div>
              </td>
              <td class="d-none d-md-table-cell">
                <span class="badge badge-soft-secondary" x-text="t.department || '—'"></span>
              </td>
              <td class="d-none d-lg-table-cell text-muted" x-text="fmtDate(t.created_at)"></td>
              <td class="text-end">
                <div class="dropdown">
                  <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item" :href="'/tasks/' + t.id">
                        <i class="bi bi-eye me-2 text-muted"></i>View details
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" :href="'/scans/new-manual?task_id=' + t.id">
                        <i class="bi bi-upc-scan me-2 text-muted"></i>Manual Scan
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" :href="'/scans/new-video?task_id=' + t.id">
                        <i class="bi bi-camera-video me-2 text-muted"></i>Video Scan
                      </a>
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
            x-text="'Showing ' + filtered.length + ' of ' + tasks.length + ' tasks'"></span>
    </div>

  </div><!-- /card -->

  <!-- ── New Task Modal ── -->
  <div class="modal fade" id="newTaskModal" tabindex="-1" aria-labelledby="newTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="newTaskModalLabel">New Task</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger py-2" x-show="formError" x-text="formError" x-cloak></div>
          <div class="mb-3">
            <label class="form-label" for="taskName">Task name <span class="text-danger">*</span></label>
            <input class="form-control" id="taskName" type="text"
                   x-model="form.name" placeholder="e.g. Pallet Lifting">
          </div>
          <div class="mb-3">
            <label class="form-label" for="taskDesc">Description</label>
            <textarea class="form-control" id="taskDesc" rows="2"
                      x-model="form.description"
                      placeholder="Brief description of the physical activity…"></textarea>
          </div>
          <div class="mb-1">
            <label class="form-label" for="taskDept">Department</label>
            <input class="form-control" id="taskDept" type="text"
                   x-model="form.department" placeholder="e.g. Warehouse">
            <div class="form-text">Used to group and filter tasks.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" @click="createTask()" :disabled="saving">
            <span class="spinner-border spinner-border-sm me-1" x-show="saving" x-cloak></span>
            <span x-text="saving ? 'Creating…' : 'Create task'"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
