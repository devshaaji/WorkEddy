<?php
$pageTitle  = 'User Feedback';
$activePage = 'admin-feedback';
ob_start();
?>
<div x-data="adminFeedbackPage">

  <?php
  $headerTitle        = 'User Feedback';
  $headerBreadcrumb   = 'Admin / Feedback';
  $headerActionsHtml  = '
    <span class="badge badge-soft-secondary px-3 py-2 text-sm" x-text="total + \' total\'"></span>
    <select class="form-select form-select-sm ms-2" x-model="filterStatus" @change="load()" style="width:auto;min-width:120px;">
      <option value="">All</option>
      <option value="new">New</option>
      <option value="reviewed">Reviewed</option>
      <option value="actioned">Actioned</option>
    </select>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <div class="card">

    <!-- Loading -->
    <div class="card-body text-center py-5" x-show="loading" x-cloak>
      <div class="spinner-border text-primary"></div>
    </div>

    <!-- Error -->
    <div class="card-body" x-show="error && !loading" x-cloak>
      <div class="alert alert-danger mb-0" x-text="error"></div>
    </div>

    <!-- Empty -->
    <div class="empty-state" x-show="!loading && !error && items.length === 0" x-cloak>
      <div class="empty-state-icon"><i class="bi bi-chat-square-text"></i></div>
      <h6>No feedback yet</h6>
      <p>Responses submitted through the landing page will appear here.</p>
    </div>

    <!-- Table -->
    <div class="table-responsive" x-show="!loading && !error && items.length > 0" x-cloak>
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Type</th>
            <th>Message</th>
            <th class="d-none d-md-table-cell">From</th>
            <th class="d-none d-lg-table-cell">Submitted</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="f in items" :key="f.id">
            <tr>
              <td class="text-muted fw-mono small" x-text="'#' + f.id"></td>
              <td>
                <span class="badge text-capitalize"
                      :class="f.type === 'issue'       ? 'badge-soft-danger'
                            : f.type === 'feature'     ? 'badge-soft-info'
                            : f.type === 'improvement' ? 'badge-soft-warning'
                            : 'badge-soft-secondary'"
                      x-text="f.type">
                </span>
              </td>
              <td style="max-width:320px;">
                <span class="text-truncate d-block" style="max-width:300px;" x-text="f.message"></span>
              </td>
              <td class="d-none d-md-table-cell text-muted">
                <span x-text="f.name ?? '—'"></span>
                <span class="d-block text-xs" x-text="f.email ?? ''"></span>
              </td>
              <td class="d-none d-lg-table-cell text-muted small" x-text="fmtDate(f.created_at)"></td>
              <td>
                <span class="badge"
                      :class="f.status === 'new'      ? 'badge-soft-primary'
                            : f.status === 'reviewed' ? 'badge-soft-warning'
                            : 'badge-soft-success'"
                      x-text="f.status">
                </span>
              </td>
              <td class="text-end">
                <div class="dropdown">
                  <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <button class="dropdown-item" @click="openDetail(f)">
                        <i class="bi bi-eye me-2 text-muted"></i>View Full
                      </button>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                      <button class="dropdown-item" @click="setStatus(f, 'reviewed')" x-show="f.status !== 'reviewed'">
                        <i class="bi bi-check me-2 text-muted"></i>Mark Reviewed
                      </button>
                    </li>
                    <li>
                      <button class="dropdown-item" @click="setStatus(f, 'actioned')" x-show="f.status !== 'actioned'">
                        <i class="bi bi-check2-all me-2 text-muted"></i>Mark Actioned
                      </button>
                    </li>
                    <li>
                      <button class="dropdown-item" @click="setStatus(f, 'new')" x-show="f.status !== 'new'">
                        <i class="bi bi-arrow-counterclockwise me-2 text-muted"></i>Reset to New
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

    <!-- Pagination footer -->
    <div class="card-footer d-flex justify-content-between align-items-center py-2"
         x-show="!loading && !error && items.length > 0" x-cloak>
      <span class="text-muted text-sm" x-text="'Showing ' + items.length + ' of ' + total + ' responses'"></span>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-light" :disabled="offset === 0" @click="prev()">← Prev</button>
        <button class="btn btn-sm btn-light" :disabled="offset + limit >= total" @click="next()">Next →</button>
      </div>
    </div>

  </div><!-- /card -->

  <!-- Detail Modal -->
  <div class="modal fade" id="feedbackDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content" x-show="selected">
        <div class="modal-header">
          <h5 class="modal-title">Feedback Detail</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" x-show="selected">
          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <p class="text-muted fw-bold text-xs text-uppercase mb-1">Type</p>
              <span class="badge text-capitalize"
                    :class="selected?.type === 'issue' ? 'badge-soft-danger'
                          : selected?.type === 'feature' ? 'badge-soft-info'
                          : selected?.type === 'improvement' ? 'badge-soft-warning'
                          : 'badge-soft-secondary'"
                    x-text="selected?.type"></span>
            </div>
            <div class="col-md-3">
              <p class="text-muted fw-bold text-xs text-uppercase mb-1">Status</p>
              <span class="badge"
                    :class="selected?.status === 'new' ? 'badge-soft-primary'
                          : selected?.status === 'reviewed' ? 'badge-soft-warning'
                          : 'badge-soft-success'"
                    x-text="selected?.status"></span>
            </div>
            <div class="col-md-3">
              <p class="text-muted fw-bold text-xs text-uppercase mb-1">From</p>
              <p class="mb-0 small" x-text="selected?.name ?? 'Anonymous'"></p>
              <p class="mb-0 text-muted text-xs" x-text="selected?.email ?? ''"></p>
            </div>
            <div class="col-md-3">
              <p class="text-muted fw-bold text-xs text-uppercase mb-1">Submitted</p>
              <p class="mb-0 small" x-text="selected ? fmtDate(selected.created_at) : ''"></p>
            </div>
          </div>
          <div class="bg-light rounded p-3">
            <p class="mb-0" style="white-space: pre-wrap; word-break: break-word;" x-text="selected?.message"></p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-warning btn-sm" @click="setStatus(selected, 'reviewed'); bootstrap.Modal.getInstance(document.getElementById('feedbackDetailModal')).hide();"
                  x-show="selected?.status === 'new'">Mark Reviewed</button>
          <button class="btn btn-success btn-sm" @click="setStatus(selected, 'actioned'); bootstrap.Modal.getInstance(document.getElementById('feedbackDetailModal')).hide();"
                  x-show="selected?.status !== 'actioned'">Mark Actioned</button>
        </div>
      </div>
    </div>
  </div>

</div>
<script>
  document.addEventListener('alpine:init', () => {
    Alpine.data('adminFeedbackPage', () => ({
      items:        [],
      total:        0,
      loading:      true,
      error:        null,
      filterStatus: '',
      selected:     null,
      limit:        50,
      offset:       0,

      async init() { await this.load(); },

      async load() {
        this.loading = true;
        this.error   = null;
        try {
          const params = new URLSearchParams({ limit: this.limit, offset: this.offset });
          if (this.filterStatus) params.set('status', this.filterStatus);
          const r = await fetch('/api/admin/feedback?' + params.toString(), {
            headers: { Authorization: 'Bearer ' + (localStorage.getItem('workeddy_token') ?? '') }
          });
          if (!r.ok) throw new Error(await r.text());
          const j    = await r.json();
          this.items = j.data ?? [];
          this.total = j.total ?? 0;
        } catch (e) {
          this.error = e.message ?? 'Failed to load feedback';
        } finally {
          this.loading = false;
        }
      },

      async setStatus(f, status) {
        try {
          const r = await fetch('/api/admin/feedback/' + f.id + '/status', {
            method:  'PUT',
            headers: {
              Authorization:  'Bearer ' + (localStorage.getItem('workeddy_token') ?? ''),
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({ status }),
          });
          if (!r.ok) throw new Error(await r.text());
          f.status = status;
          if (this.selected && this.selected.id === f.id) this.selected.status = status;
        } catch (e) {
          alert('Failed to update status: ' + e.message);
        }
      },

      openDetail(f) {
        this.selected = f;
        new bootstrap.Modal(document.getElementById('feedbackDetailModal')).show();
      },

      prev() { this.offset = Math.max(0, this.offset - this.limit); this.load(); },
      next() { this.offset += this.limit; this.load(); },

      fmtDate(s) {
        if (!s) return '—';
        return new Date(s).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
      },
    }));
  });
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
