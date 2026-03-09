<?php
$pageTitle  = 'New Video Scan';
$activePage = 'scans-video';
ob_start();
?>
<div x-data="videoScanPage">

  <?php
  $headerTitle = 'New Video Scan';
  $headerBreadcrumbHtml = '<ol class="breadcrumb mb-0 text-sm"><li class="breadcrumb-item"><a href="/tasks" class="text-decoration-none text-muted">Tasks</a></li><li class="breadcrumb-item active">Video Scan</li></ol>';
  $headerActionsHtml = '<a href="/tasks" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Tasks</a>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <div>

    <div class="alert alert-danger align-items-center gap-2"
         x-show="error" x-text="error" x-cloak></div>

    <!-- Upload form (hidden once a scan is submitted) -->
    <div x-show="!scanId">
      <form @submit.prevent="submit">

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
                          @click="if (!parentScanId) model = m.value"
                          :disabled="parentScanId != null"
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
                 @change="onFileChange()"
                 accept="video/mp4,video/quicktime,video/x-msvideo,video/*">
          <div class="form-text">
            Supported: MP4, MOV, AVI — max 200 MB.
            The system will extract frames and run pose estimation automatically.
          </div>

          <!-- Video Preview -->
          <div class="mt-3" x-show="videoPreviewUrl" x-cloak>
            <label class="form-label fw-semibold">Preview</label>
            <video class="w-100 rounded border" style="max-height:320px;background:#000"
                   controls preload="metadata" :src="videoPreviewUrl">
            </video>
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
        <button type="submit" class="btn btn-primary" :disabled="uploading">
          <span class="spinner-border spinner-border-sm me-2" x-show="uploading" x-cloak></span>
          <i class="bi bi-cloud-upload me-1" x-show="!uploading"></i>
          <span x-text="uploading ? 'Uploading…' : 'Upload & Analyse'"></span>
        </button>
        <a href="/tasks" class="btn btn-light">Cancel</a>
      </div>

      </form>

    </div><!-- /upload form -->

    <!-- Inline Results (shown after upload) -->
    <div x-show="scanId" x-cloak>

      <!-- Processing banner -->
      <div class="alert alert-info align-items-center gap-2"
           x-show="resultPending" x-cloak style="display:none" :style="resultPending ? 'display:flex' : 'display:none'">
        <div class="spinner-border spinner-border-sm flex-shrink-0"></div>
        <span><strong>Processing…</strong> Your video is being analysed. Results will appear automatically.</span>
      </div>

      <!-- Invalid / Error banner -->
      <div class="alert alert-danger align-items-start gap-2"
           x-show="scanInvalid" x-cloak style="display:none" :style="scanInvalid ? 'display:flex' : 'display:none'">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
        <div>
          <strong>Analysis Failed</strong>
          <p class="mb-0 mt-1" x-text="errorMessage"></p>
        </div>
      </div>

      <!-- Video Preview (from local file) -->
      <div class="card mb-4" x-show="videoPreviewUrl">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="bi bi-camera-video text-primary"></i>
          <h6 class="mb-0 fw-semibold">Uploaded Video</h6>
        </div>
        <div class="card-body p-0">
          <video class="w-100 rounded-bottom" style="max-height:400px;background:#000"
                 controls preload="metadata" :src="videoPreviewUrl">
          </video>
        </div>
      </div>

      <!-- Score + Details (only when completed) -->
      <div x-show="scan && scan.status === 'completed' && !scanInvalid" x-cloak>
        <div class="row g-4 mb-4">
          <div class="col-lg-4">
            <div class="card h-100">
              <div class="card-body text-center py-4">
                <p class="section-title mb-2">Risk Score</p>
                <div class="display-4 fw-bold mb-2" x-text="score" :style="'color:' + barColor"></div>
                <span class="badge px-3 py-2 fs-6"
                      :class="riskLevel === 'high' ? 'badge-soft-danger'
                            : riskLevel === 'moderate' ? 'badge-soft-warning'
                            : 'badge-soft-success'"
                      x-text="riskLevel"></span>
                <div class="risk-meter mt-4">
                  <div class="risk-meter-fill" :style="'background:' + barColor + ';width:' + barWidth"></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-8">
            <div class="card h-100">
              <div class="card-header"><h6 class="mb-0 fw-semibold">Scan Details</h6></div>
              <div class="card-body">
                <dl class="row mb-0">
                  <dt class="col-sm-4 text-muted text-sm">Model</dt>
                  <dd class="col-sm-8"><span class="badge badge-soft-primary text-uppercase" x-text="scan?.model ?? '—'"></span></dd>
                  <dt class="col-sm-4 text-muted text-sm">Status</dt>
                  <dd class="col-sm-8"><span class="badge badge-soft-success" x-text="scan?.status ?? '—'"></span></dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <!-- Recommendation -->
        <div class="card mb-4" x-show="recommendation" x-cloak>
          <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-lightbulb text-warning"></i>
            <h6 class="mb-0 fw-semibold">Recommendation</h6>
          </div>
          <div class="card-body"><p class="mb-0" x-text="recommendation"></p></div>
        </div>

        <!-- Measurements -->
        <div class="card mb-4" x-show="measurements.length > 0" x-cloak>
          <div class="card-header"><h6 class="mb-0 fw-semibold">Measurements</h6></div>
          <div class="card-body">
            <div class="row g-3">
              <template x-for="m in measurements" :key="m.label">
                <div class="col-6 col-md-4 col-xl-3">
                  <div class="p-3 rounded bg-light border">
                    <p class="text-muted text-xs text-uppercase fw-semibold mb-1" x-text="m.label"></p>
                    <span class="fw-bold fs-5" x-text="m.value"></span>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="d-flex gap-2 mt-3">
        <a :href="'/scans/' + scanId" class="btn btn-outline-primary" x-show="scan && scan.status === 'completed'">
          <i class="bi bi-bar-chart me-1"></i>Full Results
        </a>
        <button class="btn btn-primary" @click="resetScanFlow()">
          <i class="bi bi-plus-circle me-1"></i>New Scan
        </button>
      </div>

    </div><!-- /inline results -->

  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
