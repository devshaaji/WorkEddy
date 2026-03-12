<?php
$pageTitle  = 'Wellbeing Check-in';
$activePage = 'leading-indicators';
ob_start();
?>
<div x-data="leadingIndicatorsPage">

  <?php
  $headerTitle = 'Wellbeing Check-in';
  $headerBreadcrumbHtml = '<ol class="breadcrumb mb-0 text-sm"><li class="breadcrumb-item"><a href="/dashboard" class="text-decoration-none text-muted">Dashboard</a></li><li class="breadcrumb-item active">Check-in</li></ol>';
  $headerActionsHtml = '<a href="/tasks" class="btn btn-outline-secondary"><i class="bi bi-list-task me-1"></i>Tasks</a>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <div class="row g-4">
    <div class="col-12 col-xl-7">
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0 fw-semibold">Submit Shift Check-in</h6>
        </div>
        <div class="card-body">

          <div class="alert alert-success" x-show="success" x-cloak x-text="success"></div>
          <div class="alert alert-danger" x-show="error" x-cloak x-text="error"></div>

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Shift Date</label>
              <input type="date" class="form-control" x-model="form.shift_date">
            </div>
            <div class="col-md-4">
              <label class="form-label">Check-in Type</label>
              <select class="form-select" x-model="form.checkin_type">
                <option value="pre_shift">Pre-shift</option>
                <option value="mid_shift">Mid-shift</option>
                <option value="post_shift">Post-shift</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Task (optional)</label>
              <select class="form-select" x-model="form.task_id">
                <option value="">No specific task</option>
                <template x-for="t in tasks" :key="t.id">
                  <option :value="String(t.id)" x-text="t.name"></option>
                </template>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Discomfort Level (0-10)</label>
              <input type="number" min="0" max="10" class="form-control" x-model.number="form.discomfort_level">
            </div>
            <div class="col-md-6">
              <label class="form-label">Fatigue Level (0-10)</label>
              <input type="number" min="0" max="10" class="form-control" x-model.number="form.fatigue_level">
            </div>

            <div class="col-md-4">
              <label class="form-label">Micro-breaks Taken</label>
              <input type="number" min="0" max="100" class="form-control" x-model.number="form.micro_breaks_taken">
            </div>
            <div class="col-md-4">
              <label class="form-label">Recovery (minutes)</label>
              <input type="number" min="0" max="1440" class="form-control" x-model.number="form.recovery_minutes">
            </div>
            <div class="col-md-4">
              <label class="form-label">Overtime (minutes)</label>
              <input type="number" min="0" max="1440" class="form-control" x-model.number="form.overtime_minutes">
            </div>

            <div class="col-md-6">
              <label class="form-label">Task Rotation Quality</label>
              <select class="form-select" x-model="form.task_rotation_quality">
                <option value="poor">Poor</option>
                <option value="fair">Fair</option>
                <option value="good">Good</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Psychosocial Load</label>
              <select class="form-select" x-model="form.psychosocial_load">
                <option value="low">Low</option>
                <option value="moderate">Moderate</option>
                <option value="high">High</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Notes (optional)</label>
              <textarea class="form-control" rows="3" maxlength="2000" x-model="form.notes" placeholder="Anything affecting comfort/fatigue today..."></textarea>
            </div>
          </div>

          <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary" @click="submit()" :disabled="saving">
              <span class="spinner-border spinner-border-sm me-1" x-show="saving" x-cloak></span>
              <span x-text="saving ? 'Submitting…' : 'Submit Check-in'"></span>
            </button>
            <button class="btn btn-light" @click="resetForm()">Reset</button>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-5">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0 fw-semibold">Personalized Coaching</h6>
          <select class="form-select form-select-sm" style="max-width:130px"
                  x-model="coachingLanguage" @change="loadCoaching()">
            <option value="en">English</option>
            <option value="es">Español</option>
            <option value="zh">中文</option>
            <option value="ar">العربية</option>
          </select>
        </div>
        <div class="card-body" x-show="loadingCoaching" x-cloak>
          <div class="text-center text-muted"><div class="spinner-border spinner-border-sm"></div></div>
        </div>
        <div class="card-body" x-show="!loadingCoaching && coaching">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="text-muted text-xs text-uppercase">Language</span>
            <span class="badge badge-soft-primary" x-text="coaching.language_label || coaching.language"></span>
          </div>

          <p class="text-muted text-xs text-uppercase mb-2">Today’s Tips</p>
          <div class="d-grid gap-2 mb-3">
            <template x-for="tip in (coaching.personalized_tips || [])" :key="tip.code">
              <div class="border rounded p-2 bg-light">
                <div class="fw-semibold text-sm" x-text="tip.title"></div>
                <div class="text-muted text-xs" x-text="tip.message"></div>
              </div>
            </template>
          </div>

          <p class="text-muted text-xs text-uppercase mb-2">Pre-shift Self-check</p>
          <ol class="mb-0 ps-3">
            <template x-for="q in (coaching.pre_shift_self_checks || [])" :key="q">
              <li class="text-sm mb-1" x-text="q"></li>
            </template>
          </ol>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0 fw-semibold">My Recent Check-ins</h6>
          <span class="badge badge-soft-secondary" x-text="(mine.entries || []).length + ' entries'"></span>
        </div>
        <div class="card-body" x-show="loadingMine" x-cloak>
          <div class="text-center text-muted"><div class="spinner-border spinner-border-sm"></div></div>
        </div>
        <div class="card-body" x-show="!loadingMine && (!mine.entries || mine.entries.length === 0)" x-cloak>
          <p class="text-muted mb-0">No check-ins yet.</p>
        </div>
        <div class="table-responsive" x-show="!loadingMine && mine.entries && mine.entries.length > 0" x-cloak>
          <table class="table table-sm mb-0 align-middle">
            <thead>
              <tr>
                <th>Date</th><th>Discomfort</th><th>Fatigue</th><th>Load</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="e in mine.entries" :key="e.id">
                <tr>
                  <td>
                    <div x-text="e.shift_date"></div>
                    <div class="text-muted text-xs text-capitalize" x-text="String(e.checkin_type || '').replace('_', ' ')"></div>
                  </td>
                  <td x-text="e.discomfort_level"></td>
                  <td x-text="e.fatigue_level"></td>
                  <td><span class="text-capitalize" x-text="e.psychosocial_load"></span></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card" x-show="summary" x-cloak>
        <div class="card-header"><h6 class="mb-0 fw-semibold">Team Snapshot (Admin/Supervisor)</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6">
              <p class="text-muted text-xs text-uppercase mb-1">Check-ins</p>
              <p class="h5 mb-0" x-text="summary.total_checkins ?? 0"></p>
            </div>
            <div class="col-6">
              <p class="text-muted text-xs text-uppercase mb-1">Avg Discomfort</p>
              <p class="h5 mb-0" x-text="summary.avg_discomfort != null ? Number(summary.avg_discomfort).toFixed(2) : '—'"></p>
            </div>
            <div class="col-6">
              <p class="text-muted text-xs text-uppercase mb-1">Avg Fatigue</p>
              <p class="h5 mb-0" x-text="summary.avg_fatigue != null ? Number(summary.avg_fatigue).toFixed(2) : '—'"></p>
            </div>
            <div class="col-6">
              <p class="text-muted text-xs text-uppercase mb-1">High Psychosocial</p>
              <p class="h5 mb-0" x-text="summary.high_psychosocial_count ?? 0"></p>
            </div>
            <div class="col-6">
              <p class="text-muted text-xs text-uppercase mb-1">Pre-shift Check-ins</p>
              <p class="h5 mb-0" x-text="summary.pre_shift_count ?? 0"></p>
            </div>
            <div class="col-6">
              <p class="text-muted text-xs text-uppercase mb-1">Post-shift Check-ins</p>
              <p class="h5 mb-0" x-text="summary.post_shift_count ?? 0"></p>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
