<?php
$pageTitle = 'Live Capture';
$activePage = 'scans-live';
ob_start();
?>
<div x-data="liveCapturePage">

  <?php
  $headerTitle = 'Live Capture';
  $headerBreadcrumbHtml = '<ol class="breadcrumb mb-0 text-sm"><li class="breadcrumb-item"><a href="/tasks" class="text-decoration-none text-muted">Tasks</a></li><li class="breadcrumb-item active">Live Capture</li></ol>';
  $headerActionsHtml = '<a href="/scans/new-video" class="btn btn-outline-secondary"><i class="bi bi-camera-video me-1"></i>Video Scan</a>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <div class="alert alert-danger" x-show="error" x-cloak x-text="error"></div>
  <div class="alert alert-warning" x-show="warning" x-cloak x-text="warning"></div>

  <div class="row g-4">
    <div class="col-12 col-xl-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0 fw-semibold">Camera Preview</h6>
          <div class="d-flex align-items-center gap-2">
            <span class="badge" :class="cameraReady ? 'badge-soft-success' : 'badge-soft-secondary'" x-text="cameraReady ? 'Camera Ready' : 'Camera Off'"></span>
          </div>
        </div>
        <div class="card-body">
          <div class="rounded border overflow-hidden live-preview-shell" style="background:#0b1020;min-height:320px;position:relative;">
            <video x-ref="previewVideo" autoplay muted playsinline class="w-100" style="max-height:520px;object-fit:cover;"></video>

            <div class="live-preview-overlay" x-show="cameraReady" x-cloak>
              <div class="live-overlay-grid"></div>
              <div class="live-overlay-safezone"></div>

              <div class="live-overlay-top d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge" :class="activeSessionId ? 'badge-soft-danger' : 'badge-soft-secondary'">
                    <span class="me-1" x-show="activeSessionId">●</span>
                    <span x-text="activeSessionId ? 'LIVE' : 'Preview'"></span>
                  </span>
                  <span class="badge badge-soft-info" x-text="sessionStats.pose_engine || selectedEngine || 'engine'"></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge" :class="confidenceBadgeClass()" x-text="latestConfidenceLabel()"></span>
                </div>
              </div>

              <div class="live-overlay-bottom">
                <small class="text-light-50">Align shoulders and hips inside the guide box. Avoid camera tilt.</small>
              </div>
            </div>

            <div x-show="!cameraReady" x-cloak class="position-absolute top-50 start-50 translate-middle text-center text-light px-3">
              <i class="bi bi-camera-video-off" style="font-size:2rem;"></i>
              <p class="mb-0 mt-2">Start camera preview to verify framing before live analysis.</p>
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-outline-primary" @click="togglePreview()" :disabled="cameraLoading || sessionLoading || activeSessionId !== null">
              <span class="spinner-border spinner-border-sm me-1" x-show="cameraLoading" x-cloak></span>
              <i class="bi me-1" :class="cameraReady ? 'bi-camera-video-off' : 'bi-camera-video'" x-show="!cameraLoading"></i>
              <span x-text="cameraReady ? 'Stop Preview' : 'Preview / Test Cam'"></span>
            </button>
            <button :class="activeSessionId ? 'btn btn-danger' : 'btn btn-primary'"
                    @click="toggleLiveSession()"
                    :disabled="sessionLoading || !selectedTaskId">
              <span class="spinner-border spinner-border-sm me-1" x-show="sessionLoading" x-cloak></span>
              <i class="bi me-1" :class="activeSessionId ? 'bi-stop-circle' : 'bi-broadcast'" x-show="!sessionLoading"></i>
              <span x-text="activeSessionId ? 'Stop Live Session' : 'Start Live Session'"></span>
            </button>
          </div>

          <div class="small text-muted mt-3">
            Tip: Keep full upper body in frame, stable lighting, and minimal background movement.
          </div>
        </div>
      </div>

      <div class="card mt-4" x-show="activeSessionId || trendPoints.length" x-cloak>
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0 fw-semibold">Posture Trend (Recent Frames)</h6>
          <span class="badge badge-soft-secondary" x-text="trendPoints.length + ' points'"></span>
        </div>
        <div class="card-body">
          <div style="height:220px;">
            <canvas id="liveTrendChart"></canvas>
          </div>
          <div class="small text-muted mt-2">Tracks trunk and neck angles from the latest analysed frame batch.</div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0 fw-semibold">Preflight Checklist</h6>
          <span class="badge" :class="preflightReady() ? 'badge-soft-success' : 'badge-soft-warning'" x-text="readinessPercent() + '% ready'"></span>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <template x-for="c in preflightChecks()" :key="c.key">
              <div class="d-flex align-items-start gap-2 p-2 border rounded" :class="c.ok ? 'bg-light' : 'bg-white'">
                <i class="bi mt-1" :class="c.ok ? 'bi-check-circle-fill text-success' : 'bi-exclamation-circle-fill text-warning'"></i>
                <div>
                  <div class="fw-semibold" x-text="c.title"></div>
                  <div class="text-muted text-sm" x-text="c.hint"></div>
                </div>
              </div>
            </template>
          </div>
          <div class="small text-muted mt-2">
            Recommended: wait until checklist is 100% for best tracking stability.
          </div>

        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Session Setup</h6></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Task</label>
            <select class="form-select" x-model="selectedTaskId">
              <template x-for="t in tasks" :key="t.id">
                <option :value="String(t.id)" x-text="t.name"></option>
              </template>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Camera Device</label>
            <select class="form-select" x-model="selectedCameraId" @change="restartCameraIfNeeded()">
              <option value="">Default camera</option>
              <template x-for="d in cameraDevices" :key="d.deviceId">
                <option :value="d.deviceId" x-text="d.label || 'Camera ' + d.deviceId.slice(0, 6)"></option>
              </template>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Pose Engine</label>
            <input type="text" class="form-control" :value="engineDisplayLabel()" readonly>
            <div class="form-text">Engine is controlled by deployment configuration.</div>
          </div>

          <div>
            <label class="form-label">Scoring Model</label>
            <select class="form-select" x-model="selectedModel">
              <option value="reba">REBA</option>
              <option value="rula">RULA</option>
            </select>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0 fw-semibold">Live Status</h6>
          <span class="badge" :class="activeSessionId ? 'badge-soft-primary' : 'badge-soft-secondary'" x-text="activeSessionId ? 'Running' : 'Idle'"></span>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Session ID</div>
              <div class="fw-semibold" x-text="activeSessionId || '—'"></div>
            </div>
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Analysed Frames</div>
              <div class="fw-semibold" x-text="sessionStats.analysed_frame_count ?? 0"></div>
            </div>
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Avg Latency</div>
              <div class="fw-semibold" x-text="formatLatency(sessionStats.avg_latency_ms)"></div>
            </div>
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Status</div>
              <div class="fw-semibold text-capitalize" x-text="sessionStats.status || 'idle'"></div>
            </div>
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Engine</div>
              <div class="fw-semibold text-capitalize" x-text="sessionStats.pose_engine || selectedEngine || '—'"></div>
            </div>
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Model</div>
              <div class="fw-semibold text-uppercase" x-text="sessionStats.model || selectedModel || '—'"></div>
            </div>
          </div>

          <hr>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="text-muted text-xs text-uppercase">Latest Metrics</div>
            <span class="badge badge-soft-info" x-text="latestFrame ? ('#' + latestFrame.frame_number) : 'No frame yet'"></span>
          </div>

          <div x-show="!latestMetrics.length" class="text-muted text-sm" x-cloak>
            Waiting for analysed frames from live worker…
          </div>

          <div class="d-grid gap-2" x-show="latestMetrics.length" x-cloak>
            <template x-for="m in latestMetrics" :key="m.key">
              <div class="d-flex justify-content-between border rounded px-2 py-1 bg-light">
                <span class="text-muted" x-text="m.label"></span>
                <strong x-text="m.value"></strong>
              </div>
            </template>
          </div>

          <hr>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="text-muted text-xs text-uppercase">Session Quality</div>
            <span class="badge" :class="qualityBadgeClass()" x-text="qualityLabel()"></span>
          </div>

          <div class="d-flex align-items-end gap-2 mb-2">
            <div class="h4 mb-0" x-text="qualityScore() + '/100'"></div>
            <small class="text-muted" x-text="qualityHint()"></small>
          </div>

          <div class="progress mb-3" style="height:8px;">
            <div class="progress-bar" role="progressbar"
                 :style="'width:' + qualityScore() + '%; background:' + qualityBarColor()"></div>
          </div>

          <ul class="mb-0 ps-3 text-sm">
            <template x-for="tip in qualityAdvice()" :key="tip">
              <li class="mb-1" x-text="tip"></li>
            </template>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
