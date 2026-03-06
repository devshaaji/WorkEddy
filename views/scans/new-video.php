<?php
$pageTitle  = 'New Video Scan';
$activePage = 'scans';
ob_start();
?>
<div x-data="videoScanPage">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">New Video Scan</h1>
      <ol class="breadcrumb mb-0 text-sm">
        <li class="breadcrumb-item"><a href="/tasks" class="text-decoration-none text-muted">Tasks</a></li>
        <li class="breadcrumb-item active">Video Scan</li>
      </ol>
    </div>
    <a href="/tasks" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Tasks
    </a>
  </div>

  <div style="max-width:640px;">

    <div class="alert alert-danger align-items-center gap-2"
         x-show="error" x-text="error" x-cloak></div>

    <!-- Card: Task & Model -->
    <div class="card mb-4">
      <div class="card-header">
        <h6 class="mb-0 fw-semibold">Task &amp; Assessment Model</h6>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label" for="videoTask">Task</label>
            <select class="form-select" id="videoTask" x-model="selectedTask">
              <template x-for="t in tasks" :key="t.id">
                <option :value="t.id" x-text="t.name"></option>
              </template>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Assessment Model</label>
            <div class="d-flex gap-2 flex-wrap mt-1">
              <template x-for="m in models" :key="m.value">
                <button type="button"
                        class="btn btn-sm"
                        :class="model === m.value ? 'btn-primary' : 'btn-outline-secondary'"
                        @click="model = m.value"
                        x-text="m.label"></button>
              </template>
            </div>
            <div class="form-text mt-1">NIOSH requires manual input only.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Card: Upload -->
    <div class="card mb-4">
      <div class="card-header">
        <h6 class="mb-0 fw-semibold">Video File</h6>
      </div>
      <div class="card-body">
        <label class="form-label" for="videoFile">Select video</label>
        <input class="form-control" id="videoFile" type="file"
               x-ref="videoFile"
               accept="video/mp4,video/quicktime,video/x-msvideo,video/*">
        <div class="form-text">
          Supported: MP4, MOV, AVI — max 200 MB.
          The system will extract frames and run pose estimation automatically.
        </div>

        <!-- Upload Progress -->
        <div class="mt-4" x-show="uploading" x-cloak>
          <div class="d-flex justify-content-between mb-1 text-sm">
            <span class="text-muted">Uploading…</span>
            <span class="fw-semibold" x-text="progress + '%'"></span>
          </div>
          <div class="progress" style="height:8px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated"
                 :style="'width:' + progress + '%'"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Submit -->
    <div class="d-flex gap-2">
      <button class="btn btn-primary" @click="submit" :disabled="uploading">
        <span class="spinner-border spinner-border-sm me-2" x-show="uploading" x-cloak></span>
        <i class="bi bi-cloud-upload me-1" x-show="!uploading"></i>
        <span x-text="uploading ? 'Uploading…' : 'Upload & Analyse'"></span>
      </button>
      <a href="/tasks" class="btn btn-light">Cancel</a>
    </div>

  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
