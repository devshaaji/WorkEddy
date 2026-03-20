/**
 * WorkEddy feature module.
 */

document.addEventListener('alpine:init', () => {

  Alpine.data('dashboardPage', () => ({
    totalScans: '-', highRisk: '-', moderateRisk: '-', avgScore: '-',
    dashboardMode: 'organization',
    kpiLabels: {
      total_scans: 'Total Scans',
      high_risk: 'High Risk',
      moderate_risk: 'Moderate Risk',
      avg_score: 'Avg Risk Score',
    },
    leadingIndicators: {
      total_checkins_30d: 0,
      avg_discomfort_30d: null,
      avg_fatigue_30d: null,
      avg_micro_breaks_30d: null,
      high_psychosocial_count_30d: 0,
    },
    recentScans: [], recentRatings: [], topTasks: [], weeklyTrends: [], deptHeatmap: [],
    loading: true, error: '',
    async init() {
      try {
        const d = await api('/dashboard');
        this.dashboardMode = d.dashboard_mode ?? 'organization';
        this.kpiLabels = d.kpi_labels ?? this.kpiLabels;
        this.totalScans   = d.total_scans ?? 0;
        this.highRisk     = d.high_risk ?? 0;
        this.moderateRisk = d.moderate_risk ?? 0;
        this.avgScore     = d.avg_score != null ? Number(d.avg_score).toFixed(1) : 'N/A';
        this.recentScans  = d.recent_scans ?? [];
        this.recentRatings = d.recent_ratings ?? [];
        this.topTasks     = d.top_tasks ?? [];
        this.weeklyTrends = d.weekly_trends ?? [];
        this.deptHeatmap  = d.department_heatmap ?? [];
        this.leadingIndicators = d.leading_indicators ?? this.leadingIndicators;
        this.$nextTick(() => this.renderWeeklyChart());
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    renderWeeklyChart() {
      if (!this.weeklyTrends.length) return;
      const canvas = document.getElementById('weeklyTrendsChart');
      if (!canvas || typeof Chart === 'undefined') return;
      const labels = this.weeklyTrends.map(w => w.week_start);
      new Chart(canvas, {
        type: 'bar',
        data: {
          labels,
          datasets: [
            { label: 'High',     data: this.weeklyTrends.map(w => w.high),     backgroundColor: '#dc3545' },
            { label: 'Moderate', data: this.weeklyTrends.map(w => w.moderate), backgroundColor: '#ffc107' },
            { label: 'Low',      data: this.weeklyTrends.map(w => w.low),      backgroundColor: '#198754' },
          ]
        },
        options: {
          responsive: true,
          plugins: { legend: { position: 'bottom' } },
          scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } }
        }
      });
    },
    fmtDate(d) { return new Date(d).toLocaleDateString(); },
    fmtScore(s) { return Number(s).toFixed(1); }
  }));

  /* Live capture page */

  Alpine.data('liveCapturePage', () => ({
    tasks: [],
    selectedTaskId: '',
    selectedEngine: 'yolo26',
    selectedModel: 'reba',
    engineNameById: {},
    showGuideOverlay: true,

    cameraDevices: [],
    selectedCameraId: '',
    cameraReady: false,
    cameraLoading: false,
    mediaStream: null,

    sessionLoading: false,
    activeSessionId: null,
    sessionStats: {},
    latestFrame: null,
    latestMetrics: [],
    trendPoints: [],
    pendingFrames: [],

    error: '',
    warning: '',
    _pollTimer: null,
    _sessionStream: null,
    _captureTimer: null,
    _flushTimer: null,
    _trendChart: null,
    _captureCanvas: null,
    _uploadInFlight: false,
    _frameNumber: 0,
    _streamMode: 'idle',
    _streamConnected: false,
    _droppedFramesSinceUpload: 0,
    _adaptiveCaptureIntervalMs: 0,
    _backpressurePauseUntil: 0,
    _lastQueueDepth: 0,

    async init() {
      await this.refreshCameraDevices();
      await this.loadSetup();
      await this.loadActiveSession();
    },

    async loadSetup() {
      this.error = '';
      try {
        const [tasks, engineConfig] = await Promise.all([
          api('/tasks'),
          api('/live/engines').catch(() => null),
        ]);

        this.tasks = Array.isArray(tasks) ? tasks : [];
        if (this.tasks.length && !this.selectedTaskId) {
          this.selectedTaskId = String(this.tasks[0].id);
        }

        const cfg = engineConfig && (engineConfig.data ?? engineConfig);
        if (cfg && Array.isArray(cfg.available_engines)) {
          this.selectedEngine = cfg.default_engine || this.selectedEngine;
          this.selectedModel = cfg.scoring_model || this.selectedModel;
          this.engineNameById = cfg.available_engines.reduce((acc, e) => {
            acc[e.id] = e.name || e.id;
            return acc;
          }, {});
        } else {
          this.engineNameById = {
            yolo26: 'YOLOv26 Pose',
            mediapipe: 'MediaPipe Pose Landmarker',
          };
        }
      } catch (e) {
        this.error = e.message;
      }
    },

    engineDisplayLabel() {
      return this.engineNameById[this.selectedEngine] || this.selectedEngine || 'Configured by environment';
    },

    async refreshCameraDevices() {
      if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
      const devices = await navigator.mediaDevices.enumerateDevices();
      this.cameraDevices = devices.filter(d => d.kind === 'videoinput');
    },

    async startCamera() {
      this.error = '';
      this.warning = '';
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        this.error = 'Camera access is not supported in this browser.';
        return;
      }

      this.cameraLoading = true;
      try {
        const constraints = {
          video: this.selectedCameraId
            ? { deviceId: { exact: this.selectedCameraId } }
            : { facingMode: 'user' },
          audio: false,
        };

        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        this.mediaStream = stream;
        this.$refs.previewVideo.srcObject = stream;
        this.cameraReady = true;

        await this.refreshCameraDevices();
      } catch (_e) {
        this.error = 'Unable to access camera. Please allow permissions and retry.';
      } finally {
        this.cameraLoading = false;
      }
    },

    stopCamera() {
      this.stopCaptureLoop();
      if (this.mediaStream) {
        this.mediaStream.getTracks().forEach(t => t.stop());
      }
      this.mediaStream = null;
      if (this.$refs.previewVideo) this.$refs.previewVideo.srcObject = null;
      this.cameraReady = false;
    },

    async restartCameraIfNeeded() {
      if (!this.cameraReady) return;
      this.stopCamera();
      await this.startCamera();
    },

    async togglePreview() {
      if (this.activeSessionId !== null) {
        if (this.cameraReady) return;
        await this.startCamera();
        if (this.cameraReady) {
          this.startCaptureLoop();
          this.warning = 'Camera reconnected to the active live session.';
        }
        return;
      }
      if (this.cameraReady) {
        this.stopCamera();
        this.warning = 'Camera preview stopped.';
      } else {
        await this.startCamera();
      }
    },

    async toggleLiveSession() {
      if (this.activeSessionId !== null) {
        await this.stopSession();
        return;
      }

      if (!this.cameraReady) {
        await this.startCamera();
      }

      if (!this.cameraReady) {
        this.error = 'Camera preview is required before starting live session.';
        return;
      }

      await this.startSession();
    },

    async startSession() {
      this.error = '';
      this.warning = '';

      if (!this.selectedTaskId) {
        this.error = 'Please select a task before starting live session.';
        return;
      }
      if (!this.cameraReady) {
        this.error = 'Start camera preview before starting live session.';
        return;
      }

      if (!this.preflightReady()) {
        const ok = await appConfirm(
          'Preflight checklist is not complete. Start session anyway?',
          {
            title: 'Start with Incomplete Setup',
            okText: 'Start Anyway',
            cancelText: 'Review Checklist',
            variant: 'warning',
          }
        );
        if (!ok) return;
      }

      this.sessionLoading = true;
      try {
        const session = await api('/live/sessions', {
          method: 'POST',
          body: JSON.stringify({
            task_id: Number(this.selectedTaskId),
            pose_engine: this.selectedEngine,
            model: this.selectedModel,
          }),
        });

        this.activeSessionId = Number(session.id);
        this.sessionStats = session;
        this.latestFrame = null;
        this.latestMetrics = [];
        this.trendPoints = [];
        this.pendingFrames = [];
        this._frameNumber = Number(session.analysed_frame_count || 0);
        this._droppedFramesSinceUpload = 0;
        this._adaptiveCaptureIntervalMs = 0;
        this._backpressurePauseUntil = 0;
        this.renderTrendChart();
        this.startCaptureLoop();
        this.startSessionUpdates();
      } catch (e) {
        this.error = e.message;
      } finally {
        this.sessionLoading = false;
      }
    },

    async stopSession() {
      if (!this.activeSessionId) return;
      this.sessionLoading = true;
      this.error = '';
      try {
        await this.flushCapturedFrames(true);
        await api('/live/sessions/' + this.activeSessionId + '/stop', { method: 'POST' });
        this.stopSessionUpdates();
        this.stopCaptureLoop();
        this.activeSessionId = null;
        this.sessionStats = {};
        this._droppedFramesSinceUpload = 0;
        this._adaptiveCaptureIntervalMs = 0;
        this._backpressurePauseUntil = 0;
        this.stopCamera();
        this.warning = 'Live session stopped and camera preview turned off.';
        this.renderTrendChart();
      } catch (e) {
        this.error = e.message;
      } finally {
        this.sessionLoading = false;
      }
    },

    async loadActiveSession() {
      try {
        const sessions = await api('/live/sessions?status=active');
        if (!Array.isArray(sessions) || sessions.length === 0) return;

        const first = sessions[0];
        this.activeSessionId = Number(first.id);
        this.sessionStats = first;
        this._frameNumber = Number(first.analysed_frame_count || 0);
        this._lastQueueDepth = Number(first.frame_queue_depth || first.telemetry?.current_frame_queue_depth || 0);
        if (this.cameraReady) {
          this.startCaptureLoop();
        } else {
          this.warning = 'A live session is active. Reconnect this browser camera to resume frame streaming.';
        }
        this.startSessionUpdates();
      } catch (_) {
        // non-blocking
      }
    },

    previewButtonLabel() {
      if (this.activeSessionId) {
        return this.cameraReady ? 'Preview Locked' : 'Reconnect Cam';
      }
      return this.cameraReady ? 'Preview Off' : 'Preview Cam';
    },

    captureIntervalMs() {
      const fps = Number(this.sessionStats?.target_fps || 5);
      const baseInterval = Math.max(150, Math.round(1000 / Math.max(1, fps)));
      const adaptiveInterval = Number(this._adaptiveCaptureIntervalMs || 0);
      return Math.max(baseInterval, adaptiveInterval);
    },

    captureBatchSize() {
      const fps = Number(this.sessionStats?.target_fps || 5);
      const batchWindow = Number(this.sessionStats?.batch_window_ms || 500);
      return Math.max(1, Math.min(6, Math.round((Math.max(1, fps) * Math.max(250, batchWindow)) / 1000)));
    },

    startCaptureLoop() {
      if (!this.activeSessionId || !this.cameraReady) return;
      this.stopCaptureLoop();
      this.pendingFrames = [];
      this._droppedFramesSinceUpload = 0;
      this._captureTimer = setInterval(() => this.captureFrame(), this.captureIntervalMs());
      this._flushTimer = setInterval(() => this.flushCapturedFrames(), Math.max(300, Number(this.sessionStats?.batch_window_ms || 500)));
      this.captureFrame();
    },

    stopCaptureLoop() {
      clearInterval(this._captureTimer);
      clearInterval(this._flushTimer);
      this._captureTimer = null;
      this._flushTimer = null;
      this.pendingFrames = [];
    },

    refreshCaptureTimer() {
      if (!this.activeSessionId || !this.cameraReady) return;
      clearInterval(this._captureTimer);
      this._captureTimer = setInterval(() => this.captureFrame(), this.captureIntervalMs());
    },

    captureFrame() {
      if (!this.activeSessionId || !this.cameraReady) return;
      if (Date.now() < this._backpressurePauseUntil) return;
      if (this.pendingFrames.length >= this.captureBatchSize() * 4) {
        this._droppedFramesSinceUpload += 1;
        return;
      }

      const video = this.$refs.previewVideo;
      if (!video || video.readyState < 2 || !video.videoWidth || !video.videoHeight) return;

      if (!this._captureCanvas) {
        this._captureCanvas = document.createElement('canvas');
      }

      const canvas = this._captureCanvas;
      const maxWidth = 640;
      const scale = Math.min(1, maxWidth / video.videoWidth);
      const width = Math.max(1, Math.round(video.videoWidth * scale));
      const height = Math.max(1, Math.round(video.videoHeight * scale));
      canvas.width = width;
      canvas.height = height;

      const ctx = canvas.getContext('2d', { willReadFrequently: false });
      if (!ctx) return;

      ctx.drawImage(video, 0, 0, width, height);
      const dataUrl = canvas.toDataURL('image/jpeg', 0.6);
      const base64 = String(dataUrl).split(',')[1] || '';
      if (!base64) return;

      this._frameNumber += 1;
      this.pendingFrames.push({
        frame_number: this._frameNumber,
        captured_at_ms: Date.now(),
        width,
        height,
        image_jpeg_base64: base64,
      });

      if (this.pendingFrames.length >= this.captureBatchSize()) {
        this.flushCapturedFrames();
      }
    },

    async flushCapturedFrames(forceAll = false) {
      if (!this.activeSessionId || this._uploadInFlight || this.pendingFrames.length === 0) return;
      if (!forceAll && Date.now() < this._backpressurePauseUntil) return;

      const batchSize = forceAll ? this.pendingFrames.length : this.captureBatchSize();
      const batch = this.pendingFrames.splice(0, batchSize);
      this._uploadInFlight = true;
      try {
        const dropped = this._droppedFramesSinceUpload;
        const result = await api('/live/sessions/' + this.activeSessionId + '/frames', {
          method: 'POST',
          body: JSON.stringify({
            frames: batch,
            telemetry: {
              client_dropped_frames: dropped,
            },
          }),
        });
        this._droppedFramesSinceUpload = 0;
        this.applyIngestBackpressure(result, batch.length);
      } catch (e) {
        this.error = e.message;
        this.pendingFrames = batch.concat(this.pendingFrames).slice(-this.captureBatchSize() * 4);
      } finally {
        this._uploadInFlight = false;
      }
    },

    applyIngestBackpressure(result, attemptedFrames = 0) {
      const response = result && typeof result === 'object' ? result : {};
      const queueDepth = Number(response.queue_depth ?? this.currentQueueDepth());
      const maxDepth = this.maxPendingFrameBatches();
      const status = String(response.status || '');

      this._lastQueueDepth = Number.isFinite(queueDepth) ? queueDepth : 0;

      if (status === 'dropped_backpressure' || (maxDepth > 0 && queueDepth >= Math.max(2, maxDepth - 1))) {
        const trimmed = this.pendingFrames.length;
        if (trimmed > 0) {
          this._droppedFramesSinceUpload += trimmed;
          this.pendingFrames = [];
        }

        this._adaptiveCaptureIntervalMs = Math.min(2000, Math.max(this.captureIntervalMs() * 1.5, 800));
        this._backpressurePauseUntil = Date.now() + 2000;
        this.refreshCaptureTimer();
        this.warning = status === 'dropped_backpressure'
          ? 'Live queue is saturated. Slowing capture so the worker can recover.'
          : 'Worker is falling behind. Slowing capture to protect live feedback.';
        return;
      }

      if (attemptedFrames > 0 && queueDepth <= 2) {
        const baseInterval = Math.max(150, Math.round(1000 / Math.max(1, Number(this.sessionStats?.target_fps || 5))));
        this._adaptiveCaptureIntervalMs = this._adaptiveCaptureIntervalMs > baseInterval
          ? Math.max(baseInterval, Math.round(this._adaptiveCaptureIntervalMs * 0.85))
          : baseInterval;
        this.refreshCaptureTimer();
      }
    },

    applyFrameList(list) {
      const frames = Array.isArray(list) ? list : [];
      this.latestFrame = frames.length ? frames[0] : null;
      this.latestMetrics = this._toMetricRows(this.latestFrame);

      const ordered = frames.slice().reverse();
      this.trendPoints = ordered.map((f) => {
        const m = this._metricsObject(f);
        return {
          frame_number: Number(f.frame_number || 0),
          trunk_angle: Number(m.trunk_angle ?? f.trunk_angle ?? 0),
          neck_angle: Number(m.neck_angle ?? f.neck_angle ?? 0),
        };
      }).filter(p => Number.isFinite(p.trunk_angle) && Number.isFinite(p.neck_angle));
      this.$nextTick(() => this.renderTrendChart());
    },

    applyLiveSnapshot(snapshot) {
      if (!snapshot || typeof snapshot !== 'object') return;

      this.sessionStats = snapshot.session || this.sessionStats;
      this._lastQueueDepth = Number(
        this.sessionStats?.frame_queue_depth
        ?? this.telemetry().current_frame_queue_depth
        ?? this._lastQueueDepth
        ?? 0
      );
      this.applyFrameList(snapshot.frames || []);

      const queueDepth = this.currentQueueDepth();
      const maxDepth = this.maxPendingFrameBatches();
      const workerProcessed = this.telemetryCount('worker_processed_frames');
      if (maxDepth > 0 && queueDepth >= Math.max(2, maxDepth - 1)) {
        this._backpressurePauseUntil = Date.now() + 1500;
        this._adaptiveCaptureIntervalMs = Math.min(2000, Math.max(this.captureIntervalMs() * 1.35, 700));
        this.refreshCaptureTimer();
      } else if (workerProcessed > 0 && queueDepth <= 2) {
        this._adaptiveCaptureIntervalMs = 0;
        this.refreshCaptureTimer();
      }

      if (!this.latestFrame && Number(this.sessionStats?.analysed_frame_count || 0) === 0) {
        this.warning = this.cameraReady
          ? 'Session started. Waiting for worker frame ingestion...'
          : 'Session is active, but this browser camera is not currently sending frames.';
      } else if (maxDepth > 0 && queueDepth >= Math.max(2, maxDepth - 1)) {
        this.warning = 'Worker queue is hot. Capture is being slowed automatically.';
      } else if (!this._streamConnected && this._streamMode === 'poll') {
        this.warning = 'Live update stream unavailable. Using polling fallback.';
      } else if (this.warning === 'Live updates reconnecting...') {
        this.warning = '';
      }

      const status = String(this.sessionStats?.status || '').toLowerCase();
      if (status === 'active' || status === 'paused') {
        if (this.cameraReady && !this._captureTimer) {
          this.startCaptureLoop();
        }
        return;
      }

      this.stopCaptureLoop();
      this.stopSessionUpdates();
      this.activeSessionId = null;
    },

    startSessionUpdates() {
      if (!this.activeSessionId) return;
      this.stopSessionUpdates();

      if (typeof window.EventSource === 'function') {
        this.startSessionStream();
        return;
      }

      this.startPollingFallback('EventSource is not available in this browser. Using polling fallback.');
    },

    startSessionStream() {
      if (!this.activeSessionId) return;

      const stream = new window.EventSource('/api/v1/live/sessions/' + this.activeSessionId + '/stream');
      this._sessionStream = stream;
      this._streamMode = 'sse';
      this._streamConnected = false;

      stream.onopen = () => {
        this._streamConnected = true;
        if (this.warning === 'Live updates reconnecting...' || this.warning === 'Live update stream unavailable. Using polling fallback.') {
          this.warning = '';
        }
      };

      stream.addEventListener('snapshot', (event) => {
        let snapshot = null;
        try {
          snapshot = JSON.parse(event.data || '{}');
        } catch (_) {
          return;
        }

        this._streamConnected = true;
        this.applyLiveSnapshot(snapshot);
      });

      stream.addEventListener('error', (event) => {
        let payload = null;
        if (event?.data) {
          try { payload = JSON.parse(event.data); } catch (_) { payload = null; }
        }
        if (payload && payload.error) {
          this.warning = payload.error;
        }
      });

      stream.onerror = () => {
        if (!this.activeSessionId) return;

        this._streamConnected = false;
        if (stream.readyState === window.EventSource.CLOSED) {
          this.startPollingFallback('Live update stream closed. Using polling fallback.');
          return;
        }

        this.warning = 'Live updates reconnecting...';
      };
    },

    stopSessionStream() {
      if (this._sessionStream) {
        this._sessionStream.close();
      }
      this._sessionStream = null;
      this._streamConnected = false;
      if (this._streamMode === 'sse') {
        this._streamMode = 'idle';
      }
    },

    startPollingFallback(message = '') {
      this.stopSessionStream();
      clearTimeout(this._pollTimer);
      this._streamMode = 'poll';
      this._streamConnected = false;
      if (message) {
        this.warning = message;
      }
      this.pollSession();
    },

    stopSessionUpdates() {
      clearTimeout(this._pollTimer);
      this._pollTimer = null;
      this.stopSessionStream();
      if (this._streamMode === 'poll') {
        this._streamMode = 'idle';
      }
    },

    _toMetricRows(frame) {
      if (!frame) return [];

      let metrics = frame.metrics_json;
      if (typeof metrics === 'string') {
        try { metrics = JSON.parse(metrics); } catch (_) { metrics = {}; }
      }
      if (!metrics || typeof metrics !== 'object') return [];

      const labels = {
        trunk_angle: 'Trunk angle (Â°)',
        neck_angle: 'Neck angle (Â°)',
        upper_arm_angle: 'Upper arm (Â°)',
        lower_arm_angle: 'Lower arm (Â°)',
        wrist_angle: 'Wrist angle (Â°)',
        confidence: 'Confidence',
        subject_track_id: 'Track ID',
      };

      const rows = [];
      Object.entries(labels).forEach(([k, label]) => {
        if (metrics[k] == null || metrics[k] === '') return;
        let value = metrics[k];
        if (k === 'confidence') value = Number(value).toFixed(3);
        rows.push({ key: k, label, value });
      });
      return rows;
    },

    _metricsObject(frame) {
      if (!frame) return {};
      let metrics = frame.metrics_json;
      if (typeof metrics === 'string') {
        try { metrics = JSON.parse(metrics); } catch (_) { metrics = {}; }
      }
      if (!metrics || typeof metrics !== 'object') return {};
      return metrics;
    },

    latestConfidenceLabel() {
      const m = this._metricsObject(this.latestFrame);
      const c = Number(m.confidence);
      if (!Number.isFinite(c)) return 'Confidence -';
      return 'Confidence ' + c.toFixed(3);
    },

    confidenceBadgeClass() {
      const m = this._metricsObject(this.latestFrame);
      const c = Number(m.confidence);
      if (!Number.isFinite(c)) return 'badge-soft-secondary';
      if (c >= 0.8) return 'badge-soft-success';
      if (c >= 0.5) return 'badge-soft-warning';
      return 'badge-soft-danger';
    },

    renderTrendChart() {
      const canvas = document.getElementById('liveTrendChart');
      if (!canvas || typeof Chart === 'undefined') return;

      if (this._trendChart) {
        this._trendChart.destroy();
        this._trendChart = null;
      }

      const points = Array.isArray(this.trendPoints) ? this.trendPoints : [];
      if (!points.length) return;

      const labels = points.map(p => '#' + p.frame_number);
      const trunk = points.map(p => p.trunk_angle);
      const neck = points.map(p => p.neck_angle);

      this._trendChart = new Chart(canvas, {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label: 'Trunk',
              data: trunk,
              borderColor: '#7c3aed',
              backgroundColor: 'rgba(124,58,237,0.15)',
              tension: 0.3,
              pointRadius: 2,
              fill: false,
            },
            {
              label: 'Neck',
              data: neck,
              borderColor: '#0ea5e9',
              backgroundColor: 'rgba(14,165,233,0.15)',
              tension: 0.3,
              pointRadius: 2,
              fill: false,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom' },
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { maxTicksLimit: 8 },
            },
            y: {
              beginAtZero: true,
              suggestedMax: 80,
              ticks: { callback: (v) => v + 'Â°' },
            },
          },
        },
      });
    },

    async pollSession() {
      if (!this.activeSessionId || this._streamMode === 'sse') return;

      try {
        const [session, frames] = await Promise.all([
          api('/live/sessions/' + this.activeSessionId),
          api('/live/sessions/' + this.activeSessionId + '/frames?limit=30'),
        ]);

        this.applyLiveSnapshot({ session, frames });

        const status = String(session.status || '').toLowerCase();
        if (status === 'active' || status === 'paused') {
          if (this.cameraReady && !this._captureTimer) {
            this.startCaptureLoop();
          }
          clearTimeout(this._pollTimer);
          this._pollTimer = setTimeout(() => this.pollSession(), 2500);
        } else {
          this.stopCaptureLoop();
          this.stopSessionUpdates();
          this.activeSessionId = null;
        }
      } catch (e) {
        this.error = e.message;
      }
    },

    formatLatency(v) {
      if (v == null || v === '') return '-';
      const n = Number(v);
      if (!Number.isFinite(n)) return '-';
      return n.toFixed(1) + ' ms';
    },

    telemetry() {
      return this.sessionStats && typeof this.sessionStats.telemetry === 'object' && this.sessionStats.telemetry
        ? this.sessionStats.telemetry
        : {};
    },

    telemetryCount(key) {
      const value = Number(this.telemetry()[key] ?? 0);
      return Number.isFinite(value) ? value : 0;
    },

    currentQueueDepth() {
      const runtimeDepth = Number(this.sessionStats?.frame_queue_depth ?? this.telemetry().current_frame_queue_depth ?? this._lastQueueDepth ?? 0);
      return Number.isFinite(runtimeDepth) ? runtimeDepth : 0;
    },

    maxPendingFrameBatches() {
      const value = Number(this.sessionStats?.max_pending_frame_batches ?? this.telemetry().max_pending_frame_batches ?? 0);
      return Number.isFinite(value) ? value : 0;
    },

    queueDepthLabel() {
      const maxDepth = this.maxPendingFrameBatches();
      return maxDepth > 0 ? (this.currentQueueDepth() + ' / ' + maxDepth) : String(this.currentQueueDepth());
    },

    formatLagRange(avg, max) {
      const avgNum = Number(avg);
      const maxNum = Number(max);
      if (!Number.isFinite(avgNum) && !Number.isFinite(maxNum)) return '-';

      const avgLabel = Number.isFinite(avgNum) ? avgNum.toFixed(1) + ' ms avg' : '-';
      const maxLabel = Number.isFinite(maxNum) ? maxNum.toFixed(1) + ' ms max' : '-';
      return avgLabel + ' / ' + maxLabel;
    },

    formatTimestamp(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return '-';
      return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    },

    streamTransportLabel() {
      if (!this.activeSessionId) return 'Idle';
      if (this._streamMode === 'sse') {
        return this._streamConnected ? 'SSE Live' : 'SSE Reconnecting';
      }
      if (this._streamMode === 'poll') {
        return 'Polling Fallback';
      }
      return 'Starting';
    },

    streamTransportBadgeClass() {
      if (!this.activeSessionId) return 'badge-soft-secondary';
      if (this._streamMode === 'sse') {
        return this._streamConnected ? 'badge-soft-success' : 'badge-soft-warning';
      }
      if (this._streamMode === 'poll') {
        return 'badge-soft-warning';
      }
      return 'badge-soft-secondary';
    },

    preflightChecks() {
      const hasTask = !!this.selectedTaskId;
      const hasCamera = !!this.cameraReady;
      const hasEngine = !!this.selectedEngine;
      const hasModel = !!this.selectedModel;

      return [
        {
          key: 'task',
          ok: hasTask,
          title: 'Task selected',
          hint: hasTask ? 'Task linked to this live session.' : 'Pick a task from Session Setup.',
        },
        {
          key: 'camera',
          ok: hasCamera,
          title: 'Camera preview active',
          hint: hasCamera ? 'Camera stream is ready.' : 'Start camera and verify framing.',
        },
        {
          key: 'engine',
          ok: hasEngine,
          title: 'Pose engine configured',
          hint: hasEngine ? ('Using ' + this.selectedEngine + '.') : 'Choose a pose engine.',
        },
        {
          key: 'model',
          ok: hasModel,
          title: 'Scoring model configured',
          hint: hasModel ? ('Using ' + String(this.selectedModel).toUpperCase() + '.') : 'Choose RULA or REBA.',
        },
      ];
    },

    readinessPercent() {
      const checks = this.preflightChecks();
      if (!checks.length) return 0;
      const ok = checks.filter(c => c.ok).length;
      return Math.round((ok / checks.length) * 100);
    },

    preflightReady() {
      return this.readinessPercent() === 100;
    },

    qualityScore() {
      const analysed = Number(this.sessionStats?.analysed_frame_count ?? 0);
      const latency = Number(this.sessionStats?.avg_latency_ms ?? 0);
      const m = this._metricsObject(this.latestFrame);
      const confidence = Number(m.confidence ?? 0);

      let score = 100;

      if (analysed < 10) score -= 18;
      else if (analysed < 30) score -= 10;

      if (Number.isFinite(latency) && latency > 0) {
        if (latency > 2000) score -= 35;
        else if (latency > 1200) score -= 22;
        else if (latency > 700) score -= 12;
      }

      if (Number.isFinite(confidence) && confidence > 0) {
        if (confidence < 0.35) score -= 35;
        else if (confidence < 0.55) score -= 20;
        else if (confidence < 0.75) score -= 10;
      } else if (this.activeSessionId) {
        score -= 15;
      }

      return Math.max(0, Math.min(100, Math.round(score)));
    },

    qualityLabel() {
      const s = this.qualityScore();
      if (s >= 80) return 'Good';
      if (s >= 60) return 'Fair';
      return 'Poor';
    },

    qualityBadgeClass() {
      const s = this.qualityScore();
      if (s >= 80) return 'badge-soft-success';
      if (s >= 60) return 'badge-soft-warning';
      return 'badge-soft-danger';
    },

    qualityBarColor() {
      const s = this.qualityScore();
      if (s >= 80) return '#16a34a';
      if (s >= 60) return '#d97706';
      return '#dc2626';
    },

    qualityHint() {
      const analysed = Number(this.sessionStats?.analysed_frame_count ?? 0);
      if (!this.activeSessionId) return 'Start a live session to evaluate quality.';
      if (analysed < 5) return 'Collecting initial frame quality...';
      return 'Updated from confidence, latency, and analysed frame volume.';
    },

    qualityAdvice() {
      const tips = [];
      const latency = Number(this.sessionStats?.avg_latency_ms ?? 0);
      const analysed = Number(this.sessionStats?.analysed_frame_count ?? 0);
      const m = this._metricsObject(this.latestFrame);
      const confidence = Number(m.confidence ?? 0);

      if (!this.activeSessionId) {
        return [
          'Start camera preview, then run a live session.',
          'Keep shoulders and hips inside the guide box for stable tracking.',
        ];
      }

      if (analysed < 10) {
        tips.push('Hold a steady posture for 5-10 seconds to collect enough baseline frames.');
      }
      if (Number.isFinite(confidence) && confidence > 0 && confidence < 0.55) {
        tips.push('Increase front lighting and keep full upper body visible to improve confidence.');
      }
      if (Number.isFinite(latency) && latency > 1200) {
        tips.push('Reduce camera resolution or close other heavy apps to lower analysis latency.');
      }
      if (tips.length === 0) {
        tips.push('Quality is stable. Continue capturing for more representative motion cycles.');
      }

      return tips.slice(0, 3);
    },

    destroy() {
      this.stopSessionUpdates();
      if (this._trendChart) {
        this._trendChart.destroy();
        this._trendChart = null;
      }
      this.stopCamera();
    },
  }));

  /* Tasks list page */

  Alpine.data('tasksPage', () => ({
    tasks: [], loading: true, error: '',
    search: '',
    get filtered() {
      const q = this.search.toLowerCase().trim();
      if (!q) return this.tasks;
      return this.tasks.filter(t =>
        (t.name || '').toLowerCase().includes(q) ||
        (t.department || '').toLowerCase().includes(q)
      );
    },
    form: { name: '', description: '', department: '' },
    formError: '', saving: false,
    async init() { await this.load(); },
    async load() {
      this.loading = true; this.error = '';
      try { this.tasks = await api('/tasks'); }
      catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    openModal() {
      this.form = { name: '', description: '', department: '' };
      this.formError = '';
      new bootstrap.Modal(document.getElementById('newTaskModal')).show();
    },
    async createTask() {
      this.formError = ''; this.saving = true;
      try {
        await api('/tasks', { method: 'POST', body: JSON.stringify(this.form) });
        bootstrap.Modal.getInstance(document.getElementById('newTaskModal'))?.hide();
        this.form = { name: '', description: '', department: '' };
        await this.load();
      } catch (e) { this.formError = e.message; }
      finally { this.saving = false; }
    },
    fmtDate(d) { return new Date(d).toLocaleDateString(); }
  }));

  /* Task detail page */

  Alpine.data('taskDetailPage', () => ({
    taskId: null, task: null, scans: [], loading: true, error: '',
    init() {
      // Task ID is the last segment of the path e.g. /tasks/42
      this.taskId = location.pathname.split('/').filter(Boolean).pop();
      if (!this.taskId) { location.href = '/tasks'; return; }
      this.loadData();
    },
    async loadData() {
      try {
        this.task  = await api('/tasks/' + this.taskId);
        try { this.scans = await api('/scans?task_id=' + this.taskId); } catch (_) { this.scans = []; }
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    fmtDate(d) { return new Date(d).toLocaleDateString(); },
    fmtScore(s) { return Number(s ?? 0).toFixed(1); }
  }));

  /* New manual scan page */

  Alpine.data('manualScanPage', () => ({
    tasks: [], selectedTask: '', parentScanId: null,
    model: 'reba',
    models: [],
    form: {
      neck_angle: '', trunk_angle: '', upper_arm_angle: '', lower_arm_angle: '',
      wrist_angle: '', leg_score: '1', load_weight: '', coupling: '0',
      horizontal_distance: '', vertical_start: '', vertical_travel: '',
      twist_angle: '', frequency: '', notes: ''
    },
    error: '', loading: false,
    get modelDescription() {
      const m = this.models.find(x => x.value === this.model);
      return m ? m.desc : '';
    },
    get activeFields() {
      const m = this.models.find(x => x.value === this.model);
      return m ? (m.fields || []) : [];
    },
    async init() {
      const p = new URLSearchParams(location.search);
      const pre = p.get('task_id');
      this.parentScanId = p.get('parent_scan_id');
      const parentModel = p.get('parent_model');
      try {
        const [tasks, allModels] = await Promise.all([api('/tasks'), api('/scans/models')]);
        this.tasks = tasks;
        this.models = allModels.filter(m => m.input_types.includes('manual'));
      } catch (_) { /* skip */ }
      if (parentModel && this.models.find(m => m.value === parentModel)) {
        this.model = parentModel;
      } else if (this.models.length && !this.models.find(m => m.value === this.model)) {
        this.model = this.models[0].value;
      }
      if (pre) this.selectedTask = pre;
      else if (this.tasks.length) this.selectedTask = String(this.tasks[0].id);
    },
    async submit() {
      this.error = ''; this.loading = true;
      try {
        const payload = { task_id: this.selectedTask, model: this.model, ...this.form };
        if (this.parentScanId) payload.parent_scan_id = this.parentScanId;
        const result = await api('/scans/manual', { method: 'POST', body: JSON.stringify(payload) });
        location.href = '/scans/' + result.id;
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    }
  }));

  /* New video scan page */

  Alpine.data('videoScanPage', () => ({
    tasks: [], selectedTask: '', parentScanId: null, error: '',
    model: 'reba',
    models: [],
    uploading: false, progress: 0,
    videoPreviewUrl: null,
    // Inline results state
    scanId: null, scan: null, resultLoading: false, resultPending: false,
    scanInvalid: false, errorMessage: '',
    measurements: [], recommendation: '', controls: [],
    _pollTimer: null,
    async init() {
      const p = new URLSearchParams(location.search);
      const pre = p.get('task_id');
      this.parentScanId = p.get('parent_scan_id');
      const parentModel = p.get('parent_model');
      try {
        const [tasks, allModels] = await Promise.all([api('/tasks'), api('/scans/models')]);
        this.tasks = tasks;
        this.models = allModels.filter(m => m.input_types.includes('video'));
      } catch (_) { /* skip */ }
      if (parentModel && this.models.find(m => m.value === parentModel)) {
        this.model = parentModel;
      } else if (this.models.length && !this.models.find(m => m.value === this.model)) {
        this.model = this.models[0].value;
      }
      if (pre) this.selectedTask = pre;
      else if (this.tasks.length) this.selectedTask = String(this.tasks[0].id);
    },
    onFileChange() {
      const file = this.$refs.videoFile && this.$refs.videoFile.files[0];
      if (this.videoPreviewUrl) URL.revokeObjectURL(this.videoPreviewUrl);
      this.videoPreviewUrl = file ? URL.createObjectURL(file) : null;
      this.error = '';
    },
    async submit() {
      this.error = '';
      const fileInput = this.$refs.videoFile;
      const file = fileInput && fileInput.files[0];
      if (!file) { this.error = 'Please select a video file.'; return; }

      if (file.size > MAX_VIDEO_BYTES) {
        this.error = `Video is too large (${(file.size / 1024 / 1024).toFixed(1)} MB). Maximum allowed size is 200 MB.`;
        return;
      }

      this.uploading = true; this.progress = 0;
      const fd = new FormData();
      fd.append('task_id', this.selectedTask);
      fd.append('model', this.model);
      if (this.parentScanId) fd.append('parent_scan_id', this.parentScanId);
      fd.append('video', file);

      try {
        const result = await apiUpload('/scans/video', fd, pct => { this.progress = pct; });
        this.scanId = result.scan_id || result.id;
        this.uploading = false;
        // Start inline result polling
        this.resultLoading = true;
        this.resultPending = true;
        this.pollResult();
      } catch (e) { this.error = e.message; this.uploading = false; }
    },
    resetScanFlow() {
      clearTimeout(this._pollTimer);
      this.scanId = null;
      this.scan = null;
      this.resultLoading = false;
      this.resultPending = false;
      this.scanInvalid = false;
      this.error = '';
      this.errorMessage = '';
      this.measurements = [];
      this.recommendation = '';
      this.controls = [];
      this.progress = 0;
      if (this.$refs.videoFile) this.$refs.videoFile.value = '';
    },
    async pollResult() {
      if (!this.scanId) return;
      try {
        const s = await api('/scans/' + this.scanId);
        this.scan = s;

        if (s.status === 'invalid') {
          this.scanInvalid = true;
          this.errorMessage = s.error_message || 'The video could not be analysed. Please ensure the video shows a person clearly performing a task.';
          this.resultPending = false;
          this.resultLoading = false;
          return;
        }
        this.scanInvalid = false;

        if (s.status === 'completed') {
          this.resultPending = false;
          this.resultLoading = false;
          const metrics = s.metrics || {};
          const metricLabels = {
            neck_angle: 'Neck angle (Â°)', trunk_angle: 'Trunk angle (Â°)',
            upper_arm_angle: 'Upper arm (Â°)', lower_arm_angle: 'Lower arm (Â°)',
            wrist_angle: 'Wrist (Â°)', leg_score: 'Leg score',
            shoulder_elevation_duration: 'Shoulder elev. (s)',
            repetition_count: 'Repetitions', processing_confidence: 'Confidence'
          };
          this.measurements = Object.entries(metricLabels)
            .filter(([k]) => metrics[k] != null && metrics[k] !== '')
            .map(([k, label]) => ({ label, value: metrics[k] }));
          this.recommendation = s.recommendation || '';
          this.controls = Array.isArray(s.controls) ? s.controls : [];
        } else {
          clearTimeout(this._pollTimer);
          this._pollTimer = setTimeout(() => this.pollResult(), 4000);
        }
      } catch (e) {
        this.error = e.message;
        this.resultLoading = false;
      }
    },
    get score() {
      if (!this.scan) return '-';
      return this.scoreValue.toFixed(1);
    },
    get scoreValue() {
      if (!this.scan) return 0;

      const normalized = Number(this.scan.normalized_score);
      if (Number.isFinite(normalized) && normalized >= 0) {
        return Math.min(100, normalized);
      }

      const raw = Number(this.scan.result_score ?? this.scan.raw_score ?? 0);
      if (Number.isFinite(raw) && raw >= 0) {
        return Math.min(100, raw * 10);
      }

      return 0;
    },
    get riskLevel() {
      if (!this.scan) return 'low';
      const level = String(this.scan.risk_level ?? this.scan.risk_category ?? 'low').toLowerCase();
      return level === 'medium' ? 'moderate' : level;
    },
    get riskLevelCategory() {
      const l = String(this.riskLevel || '').toLowerCase();
      if (l.includes('very high') || l.startsWith('high') || l.includes(' high ')) return 'high';
      if (l.includes('moderate') || l.includes('medium')) return 'moderate';
      if (l.includes('low')) return 'low';
      return 'low';
    },
    get barColor() {
      const l = this.riskLevelCategory;
      if (l === 'high') return '#dc3545';
      if (l === 'moderate') return '#fd7e14';
      return '#198754';
    },
    get barWidth() { return Math.min(100, Math.max(0, this.scoreValue || 0)) + '%'; },
    destroy() { clearTimeout(this._pollTimer); if (this.videoPreviewUrl) URL.revokeObjectURL(this.videoPreviewUrl); }
  }));

  /* Scan results page */

  Alpine.data('scanResultsPage', () => ({
    scanId: null, scan: null, loading: true, error: '', pending: false,
    scanInvalid: false, errorMessage: '',
    measurements: [], recommendation: '', controls: [], controlActions: [],
    actionBusy: false,
    _pollTimer: null,
    init() {
      // Support both /scans/42 (path) and /scans?id=42 (query)
      const parts = location.pathname.split('/').filter(Boolean);
      if (parts[0] === 'scans' && parts[1] && /^\d+$/.test(parts[1])) {
        this.scanId = parts[1];
      } else {
        this.scanId = new URLSearchParams(location.search).get('id');
      }
      if (!this.scanId) { location.href = '/tasks'; return; }
      this.loadScan();
    },
    async loadScan() {
      try {
        const s = await api('/scans/' + this.scanId);
        this.scan = s;

        // Handle invalid/failed scans
        if (s.status === 'invalid') {
          this.scanInvalid = true;
          this.errorMessage = s.error_message || 'The video could not be analysed. This may happen if no human pose was detected in the video. Please upload a video that clearly shows a person performing the task.';
          this.pending = false;
          this.loading = false;
          return;
        }
        this.scanInvalid = false;

        // Build measurements from the metrics sub-object
        const metrics = s.metrics || {};
        const metricLabels = {
          neck_angle: 'Neck angle (Â°)', trunk_angle: 'Trunk angle (Â°)',
          upper_arm_angle: 'Upper arm (Â°)', lower_arm_angle: 'Lower arm (Â°)',
          wrist_angle: 'Wrist (Â°)', leg_score: 'Leg score',
          load_weight: 'Load (kg)', coupling: 'Coupling',
          horizontal_distance: 'H. distance (cm)', vertical_start: 'V. start (cm)',
          vertical_travel: 'V. travel (cm)', twist_angle: 'Twist (Â°)',
          frequency: 'Frequency', shoulder_elevation_duration: 'Shoulder elev. (s)',
          repetition_count: 'Repetitions', processing_confidence: 'Confidence'
        };
        this.measurements = Object.entries(metricLabels)
          .filter(([k]) => metrics[k] != null && metrics[k] !== '')
          .map(([k, label]) => ({ label, value: metrics[k] }));

        // Recommendation from scan_results
        this.recommendation = s.recommendation || '';
        this.controls = Array.isArray(s.controls) ? s.controls : [];
        this.controlActions = Array.isArray(s.control_actions) ? s.control_actions : [];

        this.pending = (s.status === 'pending' || s.status === 'processing');
        if (this.pending) {
          clearTimeout(this._pollTimer);
          this._pollTimer = setTimeout(() => this.loadScan(), 5000);
        }
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    get score() {
      if (!this.scan) return '-';
      return this.scoreValue.toFixed(1);
    },
    get scoreValue() {
      if (!this.scan) return 0;

      const normalized = Number(this.scan.normalized_score);
      if (Number.isFinite(normalized) && normalized >= 0) {
        return Math.min(100, normalized);
      }

      const raw = Number(this.scan.result_score ?? this.scan.raw_score ?? 0);
      if (Number.isFinite(raw) && raw >= 0) {
        return Math.min(100, raw * 10);
      }

      return 0;
    },
    get riskLevel() {
      if (!this.scan) return 'low';
      const level = String(this.scan.risk_level ?? this.scan.risk_category ?? 'low').toLowerCase();
      return level === 'medium' ? 'moderate' : level;
    },
    get riskLevelCategory() {
      const l = String(this.riskLevel || '').toLowerCase();
      if (l.includes('very high') || l.startsWith('high') || l.includes(' high ')) return 'high';
      if (l.includes('moderate') || l.includes('medium')) return 'moderate';
      if (l.includes('low')) return 'low';
      return 'low';
    },
    get modelLabel() {
      if (!this.scan || !this.scan.model) return '';
      return this.scan.model.toUpperCase();
    },
    get canManageActions() {
      const role = String(Alpine.store('auth')?.role || '').toLowerCase();
      return role === 'admin' || role === 'supervisor';
    },
    get poseVideoUrl() {
      const path = String(this.scan?.video_path || '').trim();
      if (!path) return '';

      // if (path.startsWith('/storage/uploads/')) return path;
      if (path.startsWith('/storage/uploads/videos/')) return path.replace('/storage/uploads/videos/', '/storage/videos/');
      if (path.startsWith('/storage/uploads/pose/')) return path.replace('/storage/uploads/pose/', '/storage/pose/');
      return path;
    },
    actionForControl(controlId) {
      const id = Number(controlId || 0);
      if (!id || !Array.isArray(this.controlActions)) return null;
      return this.controlActions.find(a => Number(a.source_control_id || 0) === id) || null;
    },
    upsertControlAction(action) {
      if (!action || typeof action !== 'object') return;
      const id = Number(action.id || 0);
      if (!id) return;

      const idx = this.controlActions.findIndex(a => Number(a.id || 0) === id);
      if (idx >= 0) {
        this.controlActions.splice(idx, 1, action);
      } else {
        this.controlActions.unshift(action);
      }
    },
    async createControlAction(control) {
      if (!this.canManageActions || !control?.id || this.actionBusy) return;
      if (this.actionForControl(control.id)) return;

      this.actionBusy = true;
      try {
        const action = await api('/control-actions/from-control', {
          method: 'POST',
          body: JSON.stringify({
            scan_id: Number(this.scanId),
            control_recommendation_id: Number(control.id),
          }),
        });
        this.upsertControlAction(action);
      } catch (e) {
        this.error = e.message;
      } finally {
        this.actionBusy = false;
      }
    },
    async updateActionStatus(actionId, status) {
      if (!this.canManageActions || !actionId || !status || this.actionBusy) return;

      this.actionBusy = true;
      try {
        const action = await api('/control-actions/' + Number(actionId), {
          method: 'PUT',
          body: JSON.stringify({ status }),
        });
        this.upsertControlAction(action);
      } catch (e) {
        this.error = e.message;
      } finally {
        this.actionBusy = false;
      }
    },
    async verifyActionPrompt(actionId) {
      if (!this.canManageActions || !actionId || this.actionBusy) return;

      const raw = window.prompt('Enter verification scan ID');
      if (raw === null) return;

      const verificationScanId = Number(raw);
      if (!Number.isInteger(verificationScanId) || verificationScanId <= 0) {
        await window.appAlert?.('Verification scan ID must be a positive integer.', { title: 'Invalid Input', variant: 'warning' });
        return;
      }

      this.actionBusy = true;
      try {
        const action = await api('/control-actions/' + Number(actionId) + '/verify', {
          method: 'POST',
          body: JSON.stringify({ verification_scan_id: verificationScanId }),
        });
        this.upsertControlAction(action);
      } catch (e) {
        this.error = e.message;
      } finally {
        this.actionBusy = false;
      }
    },
    get barColor() {
      const l = this.riskLevelCategory;
      if (l === 'high') return '#dc3545';
      if (l === 'moderate') return '#fd7e14';
      return '#198754';
    },
    get barWidth() { return Math.min(100, Math.max(0, this.scoreValue || 0)) + '%'; },
    fmtDate(d) { return new Date(d).toLocaleString(); },
    destroy()  { clearTimeout(this._pollTimer); }
  }));

  /* Observer rating page */

  Alpine.data('observerRatePage', () => ({
    scanId: null, scan: null, ratings: [],
    loading: true, error: '', formError: '', saving: false, submitted: false,
    form: { observer_score: '', observer_category: '', notes: '' },
    init() {
      const parts = location.pathname.split('/').filter(Boolean);
      if (parts[0] === 'scans') {
        this.scanId = parts[1] || null;
      } else {
        this.scanId = new URLSearchParams(location.search).get('scan_id') || null;
      }
      if (!this.scanId) {
        // No scan context - send to scans list so the user can pick one
        location.href = '/scans/new-manual';
        return;
      }
      this.load();
    },
    async load() {
      try {
        const [s, r] = await Promise.all([
          api('/scans/' + this.scanId),
          api('/observer-rating/' + this.scanId),
        ]);
        this.scan = s;
        this.ratings = r ?? [];
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    async submit() {
      this.formError = '';
      if (!this.form.observer_score || !this.form.observer_category) {
        this.formError = 'Score and risk category are required.';
        return;
      }
      this.saving = true;
      try {
        await api('/observer-rating', {
          method: 'POST',
          body: JSON.stringify({
            scan_id: Number(this.scanId),
            observer_score: Number(this.form.observer_score),
            observer_category: this.form.observer_category,
            notes: this.form.notes || null,
          }),
        });
        this.submitted = true;
        await this.load();
      } catch (e) { this.formError = e.message; }
      finally { this.saving = false; }
    }
  }));

  /* Scan comparison page*/

  Alpine.data('scanComparePage', () => ({
    scanId: null, current: null, parent: null, improvementProof: null,
    loading: true, error: '',
    noComparisonData: false,
    init() {
      const parts = location.pathname.split('/').filter(Boolean);
      this.scanId = parts[1] || null;
      if (!this.scanId) { location.href = '/tasks'; return; }
      this.load();
    },
    async load() {
      try {
        const d = await api('/scans/' + this.scanId + '/compare');
        this.current = d.current;
        this.parent  = d.parent;
        this.improvementProof = d.improvement_proof || null;
        this.noComparisonData = !(this.current && this.parent);
        this.$nextTick(() => this.renderChart());
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    get reduction() {
      if (!this.current || !this.parent) return null;
      const before = Number(this.parent.normalized_score);
      const after  = Number(this.current.normalized_score);
      if (before === 0) return 0;
      return ((before - after) / before * 100).toFixed(1);
    },
    renderChart() {
      if (!this.current || !this.parent) return;
      const canvas = document.getElementById('compareChart');
      if (!canvas || typeof Chart === 'undefined') return;
      new Chart(canvas, {
        type: 'bar',
        data: {
          labels: ['Risk Score'],
          datasets: [
            { label: 'Before', data: [Number(this.parent.normalized_score)], backgroundColor: '#6c757d' },
            { label: 'After',  data: [Number(this.current.normalized_score)], backgroundColor: '#0d6efd' },
          ]
        },
        options: {
          responsive: true,
          plugins: { legend: { position: 'bottom' } },
          scales: { y: { beginAtZero: true } },
          indexAxis: 'y'
        }
      });
    },
    fmtDate(d) { return new Date(d).toLocaleString(); }
  }));

  /* 
   * ADVANCED SCAN COMPARISON  (/scans/compare)
   * Components: ScanSelector Â· SkeletonViewer Â· ScoreDeltaCard
   *             JointHeatmap Â· ComparisonTree Â· Timeline
   *  */

  Alpine.data('scanAdvancedComparePage', () => ({

    /* State */
    loadingScans: true,
    scansError:   '',
    scans:        [],         // completed scans available for selection

    comparing:   false,
    error:       '',
    comparison:  null,        // full API response from /scans/compare

    scanAId: '',              // selected scan IDs (strings for <select> binding)
    scanBId: '',

    _chart: null,             // Chart.js instance

    /* Lifecycle */
    async init() {
      await this.loadScans();
      // Support deep-link: /scans/compare?a=1&b=2
      const p = new URLSearchParams(location.search);
      const a = p.get('a'), b = p.get('b');
      if (a) this.scanAId = String(a);
      if (b) this.scanBId = String(b);
      if (a && b) await this.runComparison();
    },

    /* Data loading */
    async loadScans() {
      this.loadingScans = true;
      this.scansError   = '';
      try {
        const d = await api('/scans?status=completed&limit=200');
        const raw = Array.isArray(d) ? d : (d.data ?? []);
        // Keep all scans that have a score (completed or with result)
        this.scans = raw
          .filter(s => s.status === 'completed' || s.normalized_score != null)
          .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
      } catch (e) {
        this.scansError = e.message;
      } finally {
        this.loadingScans = false;
      }
    },

    async runComparison() {
      if (!this.canCompare) return;
      this.comparing  = true;
      this.error      = '';
      this.comparison = null;
      if (this._chart) { this._chart.destroy(); this._chart = null; }
      try {
        const d = await api('/scans/compare?scanA=' + this.scanAId + '&scanB=' + this.scanBId);
        this.comparison = d.data ?? d;
        this.$nextTick(() => this._renderTimeline());
      } catch (e) {
        this.error = e.message;
      } finally {
        this.comparing = false;
      }
    },

    /* Computed getters */
    get canCompare() {
      return this.scanAId && this.scanBId && this.scanAId !== this.scanBId && this.sameModel;
    },
    get scanA() {
      return this.scans.find(s => String(s.id) === String(this.scanAId)) || null;
    },
    get scanB() {
      return this.scans.find(s => String(s.id) === String(this.scanBId)) || null;
    },
    get sameModel() {
      if (!this.scanA || !this.scanB) return true;
      return String(this.scanA.model || '').toLowerCase() === String(this.scanB.model || '').toLowerCase();
    },
    get scoreA() {
      return this.comparison?.summary?.scan_a?.normalized_score ?? null;
    },
    get scoreB() {
      return this.comparison?.summary?.scan_b?.normalized_score ?? null;
    },
    get scoreDeltaVal() {
      return this.comparison?.score_delta?.normalized ?? null;
    },
    get direction() {
      return this.comparison?.summary?.direction ?? 'unchanged';
    },

    /**
     * Angle entries as an array for x-for iteration.
     * pose_delta.angles is a plain object keyed by angle name.
     */
    get anglesList() {
      const pd = this.comparison?.pose_delta;
      if (!pd?.available) return [];
      return Object.entries(pd.angles).map(([key, vals]) => ({ key, ...vals }));
    },

    /**
     * Keys of the top-3 angles with the largest absolute delta.
     * Used to apply the highlight ring on the skeleton joints.
     */
    get topDiffAngles() {
      return this.anglesList
        .map(a => ({ key: a.key, abs: Math.abs(a.delta) }))
        .sort((a, b) => b.abs - a.abs)
        .slice(0, 3)
        .map(x => x.key);
    },

    /** Precomputed joint-colour maps for each skeleton */
    get skeletonColorsA() { return this._buildColors('a'); },
    get skeletonColorsB() { return this._buildColors('b'); },

    /* Skeleton colour builder */
    _buildColors(side) {
      const def = '#cbd5e1';
      const c = {
        head: def, neck: def,
        lShoulder: def, rShoulder: def,
        lElbow: def,    rElbow: def,
        lWrist: def,    rWrist: def,
        trunk: def,
        lHip: def,  rHip: def,
        lKnee: def, rKnee: def,
        lAnkle: def, rAnkle: def,
      };
      if (!this.comparison) return c;

      const isA    = side === 'a';
      const angles = this.comparison.pose_delta?.angles ?? {};

      // Helper: angle value → risk colour
      const col = (key) => {
        const a = angles[key];
        if (!a) return null;
        const v = isA ? a.scan_a : a.scan_b;
        return this.riskColor(Math.min(100, Math.abs(v) / 90 * 100));
      };

      const neck  = col('neck_angle');
      const trunk = col('trunk_angle');
      const ua    = col('upper_arm_angle');
      const la    = col('lower_arm_angle');
      const wr    = col('wrist_angle');

      if (neck)  { c.head = neck; c.neck = neck; }
      if (trunk) { c.trunk = trunk; }
      if (ua)    { c.lShoulder = ua; c.rShoulder = ua; }
      if (la)    { c.lElbow = la;   c.rElbow = la; }
      if (wr)    { c.lWrist = wr;   c.rWrist = wr; }

      // Fall back lower body + any missing joints to overall normalised score
      const overall = isA ? this.scoreA : this.scoreB;
      if (overall !== null) {
        const oc = this.riskColor(overall);
        if (!neck)  { c.head = oc; c.neck = oc; }
        if (!trunk) { c.trunk = oc; }
        ['lHip', 'rHip', 'lKnee', 'rKnee', 'lAnkle', 'rAnkle']
          .forEach(j => { c[j] = oc; });
      }

      return c;
    },

    /* Risk utilities */
    riskColor(score) {
      if (score == null) return '#cbd5e1';
      if (score < 30) return '#22c55e';
      if (score < 55) return '#f59e0b';
      if (score < 75) return '#f97316';
      return '#ef4444';
    },
    riskLabel(score) {
      if (score == null) return '-';
      if (score < 30) return 'Low';
      if (score < 55) return 'Moderate';
      if (score < 75) return 'High';
      return 'Critical';
    },
    riskBadgeClass(score) {
      if (score == null) return 'badge-soft-secondary';
      if (score < 30) return 'badge-soft-success';
      if (score < 55) return 'badge-soft-warning';
      return 'badge-soft-danger';
    },
    deltaClass(delta) {
      if (delta == null || Math.abs(delta) < 0.05) return 'text-muted';
      return delta < 0 ? 'text-success' : 'text-danger';
    },
    deltaIcon(delta) {
      if (delta == null || Math.abs(delta) < 0.05) return 'bi-dash';
      return delta < 0 ? 'bi-arrow-down-short' : 'bi-arrow-up-short';
    },
    directionBadge() {
      const d = this.direction;
      if (d === 'improved')
        return { cls: 'alert-success', icon: 'bi-arrow-down-circle-fill', text: 'Risk Improved' };
      if (d === 'worsened')
        return { cls: 'alert-danger',  icon: 'bi-arrow-up-circle-fill',   text: 'Risk Worsened' };
      return { cls: 'alert-info', icon: 'bi-dash-circle', text: 'No Change' };
    },

    /* Formatting */
    fmtDate(d) {
      if (!d) return '-';
      return new Date(d).toLocaleDateString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
      });
    },
    fmtScore(v) {
      if (v == null) return '-';
      return Number(v).toFixed(1);
    },
    prettyKey(k) {
      return k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    },
    /** Label shown in the <select> dropdowns */
    scanLabel(s) {
      const date = this.fmtDate(s.created_at);
      const risk = s.risk_category ? ' [' + s.risk_category + ']' : '';
      return '#' + s.id + ' Â· ' + (s.model || '').toUpperCase() + ' Â· ' + date + risk;
    },

    /* Timeline (Chart.js) */
    _renderTimeline() {
      const canvas = document.getElementById('cmpTimelineChart');
      if (!canvas || typeof Chart === 'undefined') return;

      // Sort chronologically; cap at 50 scans for performance
      const sorted = this.scans
        .slice()
        .sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
        .slice(-50);

      const labels = sorted.map(s => this.fmtDate(s.created_at));
      const data   = sorted.map(s => s.normalized_score != null ? Number(s.normalized_score) : null);

      const aIdx = sorted.findIndex(s => String(s.id) === String(this.scanAId));
      const bIdx = sorted.findIndex(s => String(s.id) === String(this.scanBId));

      // Distinct point styling for selected scans
      const pointBg = sorted.map((_, i) => {
        if (i === aIdx) return '#7c3aed';
        if (i === bIdx) return '#0ea5e9';
        return 'rgba(124,58,237,0.22)';
      });
      const pointR = sorted.map((_, i) => (i === aIdx || i === bIdx) ? 9 : 4);

      if (this._chart) this._chart.destroy();
      this._chart = new Chart(canvas, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'Risk Score',
            data,
            borderColor: '#7c3aed',
            backgroundColor: 'rgba(124,58,237,0.07)',
            fill: true,
            tension: 0.35,
            pointBackgroundColor: pointBg,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: pointR,
            pointHoverRadius: 10,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                title: (ctx) => {
                  const s = sorted[ctx[0].dataIndex];
                  const tag = String(s.id) === String(this.scanAId) ? ' â† Scan A'
                            : String(s.id) === String(this.scanBId) ? ' â† Scan B' : '';
                  return '#' + s.id + tag + '  Â·  ' + ctx[0].label;
                },
                label: (ctx) => ' Risk Score: ' + (ctx.parsed.y != null ? ctx.parsed.y.toFixed(1) : '-'),
              },
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              max: 100,
              grid: { color: 'rgba(0,0,0,0.04)' },
              ticks: { font: { size: 11 }, callback: v => v },
            },
            x: {
              grid: { display: false },
              ticks: { font: { size: 11 }, maxRotation: 40, autoSkip: true, maxTicksLimit: 12 },
            },
          },
        },
      });
    },

  }));

  /* ADMIN PAGES
   * */

  /* Admin Dashboard page */

});
