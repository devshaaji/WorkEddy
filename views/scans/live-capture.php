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

  <div class="row g-4 live-capture-layout">
    <div class="col-12 d-grid gap-4">
      <div class="card live-stage-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h6 class="mb-0 fw-semibold">Live Capture Stage</h6>
          <div class="d-flex align-items-center gap-2">
            <span class="badge" :class="streamTransportBadgeClass()" x-text="streamTransportLabel()"></span>
            <div class="dropdown">
              <button class="btn btn-sm live-readiness-btn"
                      :class="preflightReady() ? 'btn-success' : (readinessPercent() >= 60 ? 'btn-warning' : 'btn-danger')"
                      type="button"
                      data-bs-toggle="dropdown"
                      aria-expanded="false"
                      title="Live Utilities">
                <i class="bi" :class="preflightReady() ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'"></i>
                <span class="ms-1" x-text="preflightReady() ? (readinessPercent() + '%') : '!'" style="min-width:1.2rem;display:inline-block;text-align:center;"></span>
              </button>
              <div class="dropdown-menu dropdown-menu-end p-0 live-utils-dropdown">
                <div class="p-3 border-bottom bg-light-subtle">
                  <div class="d-flex justify-content-between align-items-center">
                    <strong>Live Utilities</strong>
                    <span class="badge" :class="preflightReady() ? 'badge-soft-success' : 'badge-soft-warning'" x-text="readinessPercent() + '% ready'"></span>
                  </div>
                </div>
                <div class="p-3">
                  <div class="d-grid gap-2 mb-3">
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
                </div>
              </div>
            </div>
            <button class="btn btn-sm btn-outline-secondary" @click="showGuideOverlay = !showGuideOverlay">
              <i class="bi" :class="showGuideOverlay ? 'bi-grid-3x3-gap-fill' : 'bi-grid-3x3-gap'"></i>
              <span class="ms-1" x-text="showGuideOverlay ? 'Hide Guide' : 'Show Guide'"></span>
            </button>
          </div>
        </div>
        <div class="card-body">
          <div class="rounded border overflow-hidden live-preview-shell" style="background:#0b1020;min-height:360px;position:relative;">
            <video x-ref="previewVideo" autoplay muted playsinline class="w-100" style="max-height:560px;object-fit:cover;"></video>

            <div class="live-preview-overlay" x-show="cameraReady && showGuideOverlay" x-cloak>
              <div class="live-overlay-grid"></div>
              <div class="live-overlay-safezone"></div>

              <div class="live-overlay-top d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge" :class="activeSessionId ? 'badge-soft-danger' : 'badge-soft-secondary'">
                    <span class="me-1" x-show="activeSessionId">●</span>
                    <span x-text="activeSessionId ? 'LIVE' : 'Preview'"></span>
                  </span>
                </div>
                <span class="badge" :class="confidenceBadgeClass()" x-text="latestConfidenceLabel()"></span>
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

          <div class="d-flex flex-wrap gap-2 mt-3 align-items-center">
            <button :class="activeSessionId ? 'btn btn-danger' : 'btn btn-primary'"
                    @click="toggleLiveSession()"
                    :disabled="sessionLoading || !selectedTaskId">
              <span class="spinner-border spinner-border-sm me-1" x-show="sessionLoading" x-cloak></span>
              <i class="bi me-1" :class="activeSessionId ? 'bi-stop-circle' : 'bi-broadcast'" x-show="!sessionLoading"></i>
              <span x-text="activeSessionId ? 'Stop Live Session' : 'Start Live Session'"></span>
            </button>
            <button class="btn btn-outline-primary btn-sm live-preview-toggle" @click="togglePreview()" :disabled="cameraLoading || sessionLoading || (activeSessionId !== null && cameraReady)">
              <span class="spinner-border spinner-border-sm me-1" x-show="cameraLoading" x-cloak></span>
              <i class="bi me-1" :class="cameraReady ? 'bi-camera-video-off' : 'bi-camera-video'" x-show="!cameraLoading"></i>
              <span x-text="previewButtonLabel()"></span>
            </button>
          </div>

          <div class="live-now-strip mt-3">
            <div class="live-now-item">
              <div class="text-muted text-xs text-uppercase">Session</div>
              <div class="fw-semibold" x-text="activeSessionId || '—'"></div>
            </div>
            <div class="live-now-item">
              <div class="text-muted text-xs text-uppercase">Status</div>
              <div class="fw-semibold text-capitalize" x-text="sessionStats.status || 'idle'"></div>
            </div>
            <div class="live-now-item">
              <div class="text-muted text-xs text-uppercase">Analysed</div>
              <div class="fw-semibold" x-text="sessionStats.analysed_frame_count ?? 0"></div>
            </div>
            <div class="live-now-item">
              <div class="text-muted text-xs text-uppercase">Avg Latency</div>
              <div class="fw-semibold" x-text="formatLatency(sessionStats.avg_latency_ms)"></div>
            </div>
            <div class="live-now-item">
              <div class="text-muted text-xs text-uppercase">Quality</div>
              <div class="fw-semibold" x-text="qualityScore() + '/100'"></div>
            </div>
            <div class="live-now-item">
              <div class="text-muted text-xs text-uppercase">Upload</div>
              <div class="fw-semibold" x-text="_uploadInFlight ? 'Uploading' : 'Idle'"></div>
            </div>
          </div>

          <div class="small text-muted mt-3">
            Tip: Keep full upper body in frame, stable lighting, and minimal background movement.
          </div>
        </div>
      </div>

      <div class="card live-insights-card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0 fw-semibold">Live Insights</h6>
          <span class="badge badge-soft-secondary" x-text="trendPoints.length + ' points'"></span>
        </div>
        <div class="card-body">
          <div class="row g-3 align-items-stretch">
            <div class="col-12 col-lg-8">
              <div style="height:240px;" class="border rounded p-2 bg-light-subtle position-relative">
                <canvas id="liveTrendChart"></canvas>
                <div x-show="!trendPoints.length" x-cloak class="position-absolute top-50 start-50 translate-middle text-center text-muted px-3">
                  <i class="bi bi-graph-up"></i>
                  <div class="mt-1">Trend will appear after analysed frames arrive.</div>
                </div>
              </div>
              <div class="small text-muted mt-2">Tracks trunk and neck angles from the latest analysed frame batch.</div>
            </div>
            <div class="col-12 col-lg-4">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="text-muted text-xs text-uppercase">Latest Metrics</div>
                <span class="badge badge-soft-info" x-text="latestFrame ? ('#' + latestFrame.frame_number) : 'No frame yet'"></span>
              </div>

              <div x-show="!latestMetrics.length" class="text-muted text-sm mb-3" x-cloak>
                Waiting for analysed frames from live worker…
              </div>

              <div class="d-grid gap-2 mb-3" x-show="latestMetrics.length" x-cloak>
                <template x-for="m in latestMetrics" :key="m.key">
                  <div class="d-flex justify-content-between border rounded px-2 py-1 bg-light">
                    <span class="text-muted" x-text="m.label"></span>
                    <strong x-text="m.value"></strong>
                  </div>
                </template>
              </div>

              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="text-muted text-xs text-uppercase">Session Quality</div>
                <span class="badge" :class="qualityBadgeClass()" x-text="qualityLabel()"></span>
              </div>
              <div class="progress mb-2" style="height:8px;">
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

  </div>

  <div class="offcanvas offcanvas-end" tabindex="-1" id="liveSetupDrawer" aria-labelledby="liveSetupDrawerLabel">
    <div class="offcanvas-header border-bottom">
      <h5 class="offcanvas-title" id="liveSetupDrawerLabel">Session Setup</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
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

      <div>
        <label class="form-label">Scoring Model</label>
        <select class="form-select" x-model="selectedModel">
          <option value="reba">REBA</option>
          <option value="rula">RULA</option>
        </select>
      </div>
    </div>
  </div>

  <div class="offcanvas offcanvas-end" tabindex="-1" id="liveOpsDrawer" aria-labelledby="liveOpsDrawerLabel">
    <div class="offcanvas-header border-bottom">
      <h5 class="offcanvas-title" id="liveOpsDrawerLabel">Live Status & Telemetry</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      <div class="row g-3 mb-3">
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
          <div class="text-muted text-xs text-uppercase">Model</div>
          <div class="fw-semibold text-uppercase" x-text="sessionStats.model || selectedModel || '—'"></div>
        </div>
      </div>

      <hr>

      <div class="row g-3">
        <div class="col-6">
          <div class="text-muted text-xs text-uppercase">Queued Raw Frames</div>
          <div class="fw-semibold" x-text="sessionStats.frame_count ?? 0"></div>
        </div>
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Current Queue Depth</div>
              <div class="fw-semibold" x-text="queueDepthLabel()"></div>
            </div>
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Queued Batches (Total)</div>
              <div class="fw-semibold" x-text="telemetryCount('queued_frame_batches')"></div>
            </div>
        <div class="col-6">
          <div class="text-muted text-xs text-uppercase">Pending Browser Frames</div>
          <div class="fw-semibold" x-text="pendingFrames.length"></div>
        </div>
        <div class="col-6">
          <div class="text-muted text-xs text-uppercase">Upload State</div>
          <div class="fw-semibold" x-text="_uploadInFlight ? 'Uploading' : 'Idle'"></div>
        </div>
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Client Dropped Frames</div>
              <div class="fw-semibold" x-text="telemetryCount('client_dropped_frames')"></div>
            </div>
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Server Dropped Frames</div>
              <div class="fw-semibold" x-text="telemetryCount('server_dropped_frames')"></div>
            </div>
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Worker Skipped Frames</div>
              <div class="fw-semibold" x-text="telemetryCount('worker_skipped_frames')"></div>
            </div>
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Decode Failures</div>
              <div class="fw-semibold" x-text="telemetryCount('worker_decode_failures')"></div>
            </div>
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Worker Processed</div>
              <div class="fw-semibold" x-text="telemetryCount('worker_processed_frames')"></div>
            </div>
            <div class="col-6">
              <div class="text-muted text-xs text-uppercase">Stale Batches Dropped</div>
              <div class="fw-semibold" x-text="telemetryCount('stale_frame_batches_dropped')"></div>
            </div>
          </div>

      <hr>

      <div class="d-grid gap-2">
        <div class="d-flex justify-content-between border rounded px-2 py-1 bg-light">
          <span class="text-muted">Upload Lag</span>
          <strong x-text="formatLagRange(telemetry().upload_lag_ms_avg, telemetry().upload_lag_ms_max)"></strong>
        </div>
        <div class="d-flex justify-content-between border rounded px-2 py-1 bg-light">
          <span class="text-muted">Worker Lag</span>
          <strong x-text="formatLagRange(telemetry().worker_lag_ms_avg, telemetry().worker_lag_ms_max)"></strong>
        </div>
            <div class="d-flex justify-content-between border rounded px-2 py-1 bg-light">
              <span class="text-muted">Last Upload</span>
              <strong x-text="formatTimestamp(telemetry().last_ingest_at)"></strong>
            </div>
            <div class="d-flex justify-content-between border rounded px-2 py-1 bg-light">
              <span class="text-muted">Last Backpressure Drop</span>
              <strong x-text="formatTimestamp(telemetry().last_backpressure_at)"></strong>
            </div>
            <div class="d-flex justify-content-between border rounded px-2 py-1 bg-light">
              <span class="text-muted">Last Worker Callback</span>
              <strong x-text="formatTimestamp(telemetry().last_worker_at)"></strong>
            </div>
      </div>

      <div class="small text-muted mt-3">
        Watch client drops, upload lag, and worker lag together. High lag with rising skips usually means the stream needs backpressure tuning.
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
