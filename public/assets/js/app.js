/**
 * WorkEddy – Alpine.js API client & page components.
 * Uses Fetch API under the hood. Zero inline JavaScript.
 */

/* ────────────────────────────────────────────────────────────────────────────
 * API client (private helpers)
 * ──────────────────────────────────────────────────────────────────────────── */

const API_BASE = '/api/v1';
const MAX_VIDEO_BYTES = 200 * 1024 * 1024; // 200 MB — must match server

function _token()   { return localStorage.getItem('we_token') || ''; }
function _save(t)   {
  localStorage.setItem('we_token', t);
  document.cookie = 'we_token=' + encodeURIComponent(t) + ';path=/;max-age=86400;SameSite=Lax';
}
function _clear()   {
  localStorage.removeItem('we_token');
  document.cookie = 'we_token=;path=/;max-age=0;SameSite=Lax';
}

/**
 * Silently refresh the JWT when it has less than 5 minutes left.
 * Skips refresh if the request itself is the refresh endpoint (avoid loop).
 */
async function _maybeRefresh(path) {
  if (path === '/auth/refresh') return;
  const t = _token();
  if (!t) return;
  try {
    const p = JSON.parse(atob(t.split('.')[1]));
    // Refresh when fewer than 5 minutes remain
    if (p.exp - Date.now() / 1000 < 300) {
      const res = await fetch(API_BASE + '/auth/refresh', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + t, 'Content-Type': 'application/json' },
      });
      if (res.ok) {
        const d = await res.json().catch(() => ({}));
        if (d.token) _save(d.token);
      }
    }
  } catch (_) { /* noop — expired tokens handled normally by 401 */ }
}

async function api(path, opts = {}) {
  await _maybeRefresh(path);
  const headers = { 'Content-Type': 'application/json', ...(opts.headers || {}) };
  const token = _token();
  if (token) headers['Authorization'] = 'Bearer ' + token;

  const res = await fetch(API_BASE + path, { ...opts, headers });

  if (res.status === 401) { _clear(); location.href = '/login'; return; }

  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || 'Request failed');
  return ('data' in data) ? data.data : data;
}

async function apiUpload(path, formData, onProgress) {
  await _maybeRefresh(path);
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    if (onProgress) {
      xhr.upload.addEventListener('progress', e => {
        if (e.lengthComputable) onProgress(Math.round(e.loaded / e.total * 100));
      });
    }
    xhr.onload = () => {
      if (xhr.status === 413) { reject(new Error('Video exceeds the maximum allowed size (200 MB).')); return; }
      const d = JSON.parse(xhr.responseText || '{}');
      if (xhr.status >= 200 && xhr.status < 300) resolve(('data' in d) ? d.data : d);
      else reject(new Error(d.error || 'Upload failed'));
    };
    xhr.onerror = () => reject(new Error('Network error'));
    xhr.open('POST', API_BASE + path);
    const t = _token();
    if (t) xhr.setRequestHeader('Authorization', 'Bearer ' + t);
    xhr.send(formData);
  });
}

/* global logout — used by layout nav */
function logout() { _clear(); location.href = '/login'; }

/* ────────────────────────────────────────────────────────────────────────────
 * System dialog helpers (replaces browser alert/confirm usage)
 * ──────────────────────────────────────────────────────────────────────────── */

let _systemDialog = null;

function _initSystemDialog() {
  if (_systemDialog) return _systemDialog;

  const modalEl = document.getElementById('systemUxDialogModal');
  if (!modalEl || typeof bootstrap === 'undefined') return null;

  const titleEl  = modalEl.querySelector('[data-dialog-title]');
  const bodyEl   = modalEl.querySelector('[data-dialog-body]');
  const iconEl   = modalEl.querySelector('[data-dialog-icon]');
  const cancelEl = modalEl.querySelector('[data-dialog-cancel]');
  const okEl     = modalEl.querySelector('[data-dialog-ok]');

  const bsModal = new bootstrap.Modal(modalEl, {
    backdrop: 'static',
    keyboard: false,
  });

  _systemDialog = {
    modalEl,
    bsModal,
    titleEl,
    bodyEl,
    iconEl,
    cancelEl,
    okEl,
    active: null,
  };

  cancelEl?.addEventListener('click', () => {
    if (!_systemDialog?.active) return;
    const { resolve } = _systemDialog.active;
    _systemDialog.active = null;
    resolve(false);
    _systemDialog.bsModal.hide();
  });

  okEl?.addEventListener('click', () => {
    if (!_systemDialog?.active) return;
    const { resolve } = _systemDialog.active;
    _systemDialog.active = null;
    resolve(true);
    _systemDialog.bsModal.hide();
  });

  modalEl.addEventListener('hidden.bs.modal', () => {
    if (_systemDialog?.active) {
      const { resolve } = _systemDialog.active;
      _systemDialog.active = null;
      resolve(false);
    }
  });

  return _systemDialog;
}

function _showSystemDialog({
  mode = 'alert',
  title = 'Notice',
  message = '',
  okText = 'OK',
  cancelText = 'Cancel',
  variant = 'primary',
} = {}) {
  const dlg = _initSystemDialog();

  // Fallback for pages without the shared app layout/modal.
  if (!dlg) {
    console.warn('System dialog modal not found; dialog request skipped.');
    return Promise.resolve(mode === 'confirm' ? false : true);
  }

  return new Promise(resolve => {
    dlg.active = { resolve, mode };

    if (dlg.titleEl) dlg.titleEl.textContent = title;
    if (dlg.bodyEl) dlg.bodyEl.textContent = message;
    if (dlg.okEl) {
      dlg.okEl.textContent = okText;
      dlg.okEl.className = 'btn btn-' + (variant || 'primary');
    }
    if (dlg.cancelEl) {
      dlg.cancelEl.textContent = cancelText;
      dlg.cancelEl.classList.toggle('d-none', mode !== 'confirm');
    }
    if (dlg.iconEl) {
      const iconClass = mode === 'confirm' ? 'bi bi-question-circle text-warning' : 'bi bi-info-circle text-primary';
      dlg.iconEl.className = iconClass;
    }

    dlg.bsModal.show();
  });
}

window.appAlert = function appAlert(message, options = {}) {
  return _showSystemDialog({
    mode: 'alert',
    title: options.title || 'Notice',
    message: String(message ?? ''),
    okText: options.okText || 'OK',
    variant: options.variant || 'primary',
  });
};

window.appConfirm = function appConfirm(message, options = {}) {
  return _showSystemDialog({
    mode: 'confirm',
    title: options.title || 'Please Confirm',
    message: String(message ?? ''),
    okText: options.okText || 'Confirm',
    cancelText: options.cancelText || 'Cancel',
    variant: options.variant || 'danger',
  });
};

/* ────────────────────────────────────────────────────────────────────────────
 * Alpine.js – Auth guard (runs on main layout)
 * ──────────────────────────────────────────────────────────────────────────── */

document.addEventListener('alpine:init', () => {

  /* ── Auth guard store ─────────────────────────────────────────────────── */
  Alpine.store('auth', {
    role: null,
    orgId: null,
    orgSettings: {},      // cached org settings (theme_color, default_model, etc.)
    init() {
      /* Auto-check on authenticated pages (pages using main layout) */
      const path = location.pathname;
      const publicPaths = ['/', '/login', '/register', '/forgot-password'];
      if (!publicPaths.includes(path)) this.check();
    },
    check() {
      const t = _token();
      if (!t) { location.href = '/login'; return false; }
      try {
        const p = JSON.parse(atob(t.split('.')[1]));
        if (Date.now() / 1000 > p.exp) { _clear(); location.href = '/login'; return false; }
        this.role  = p.role || null;
        this.orgId = p.org  || null;
        // Load org settings for theme binding
        this._loadOrgSettings();
      } catch (_) { /* noop */ }
      return true;
    },
    async _loadOrgSettings() {
      try {
        const res = await api('/org/settings');
        const data = res.data ?? res;
        this.orgSettings = data;
        // Apply theme color
        if (data.theme_color) {
          document.documentElement.style.setProperty('--we-primary', data.theme_color);
        }
      } catch (_) { /* non-critical */ }
    }
  });

  /* ── Login page ───────────────────────────────────────────────────────── */
  Alpine.data('loginPage', () => ({
    step: 'credentials',        // 'credentials' | '2fa'
    email: '', password: '', totpCode: '',
    tempToken: '',              // temporary JWT for 2FA verification
    error: '', loading: false,

    /* Step 1 — email & password */
    async submit() {
      this.error = ''; this.loading = true;
      try {
        const d = await api('/auth/login', {
          method: 'POST',
          body: JSON.stringify({ email: this.email, password: this.password })
        });

        if (d.requires_2fa) {
          // User has TOTP enabled — show 2FA code input
          this.tempToken = d.temp_token;
          this.step = '2fa';
          return;
        }

        // Normal login — save token and redirect
        _save(d.token);
        location.href = '/dashboard';
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },

    /* Step 2 — verify TOTP code */
    async submit2fa() {
      this.error = ''; this.loading = true;
      try {
        const d = await fetch('/api/v1/auth/2fa/verify', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + this.tempToken,
          },
          body: JSON.stringify({ code: this.totpCode }),
        });
        const json = await d.json();
        if (!d.ok) throw new Error(json.error || 'Verification failed');

        _save(json.token);
        location.href = '/dashboard';
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },

    backToLogin() {
      this.step = 'credentials';
      this.totpCode = '';
      this.tempToken = '';
      this.error = '';
    }
  }));

  /* ── Register page ────────────────────────────────────────────────────── */
  Alpine.data('registerPage', () => ({
    orgName: '', name: '', email: '', password: '', password2: '', error: '', loading: false,
    async submit() {
      this.error = '';
      if (this.password !== this.password2) { this.error = 'Passwords do not match'; return; }
      this.loading = true;
      try {
        const d = await api('/auth/signup', {
          method: 'POST',
          body: JSON.stringify({ organization_name: this.orgName, name: this.name, email: this.email, password: this.password }),
        });
        _save(d.token);
        location.href = '/dashboard';
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    }
  }));

  /* ── Forgot-password page ─────────────────────────────────────────────── */
  Alpine.data('forgotPage', () => ({
    email: '', message: '', isError: false, loading: false,
    async submit() {
      this.message = '';
      if (!this.email.trim()) { this.message = 'Please enter your email.'; this.isError = true; return; }
      this.loading = true;
      this.message = 'If that address exists, a reset link has been sent.';
      this.isError = false;
      this.loading = false;
    }
  }));

  /* ── Dashboard page ───────────────────────────────────────────────────── */
  Alpine.data('dashboardPage', () => ({
    totalScans: '–', highRisk: '–', moderateRisk: '–', avgScore: '–',
    leadingIndicators: {
      total_checkins_30d: 0,
      avg_discomfort_30d: null,
      avg_fatigue_30d: null,
      avg_micro_breaks_30d: null,
      high_psychosocial_count_30d: 0,
    },
    recentScans: [], topTasks: [], weeklyTrends: [], deptHeatmap: [],
    loading: true, error: '',
    async init() {
      try {
        const d = await api('/dashboard');
        this.totalScans   = d.total_scans ?? 0;
        this.highRisk     = d.high_risk ?? 0;
        this.moderateRisk = d.moderate_risk ?? 0;
        this.avgScore     = d.avg_score != null ? Number(d.avg_score).toFixed(1) : 'N/A';
        this.recentScans  = d.recent_scans ?? [];
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

  /* ── Tasks list page ──────────────────────────────────────────────────── */
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

  /* ── Task detail page ─────────────────────────────────────────────────── */
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

  /* ── New manual scan page ─────────────────────────────────────────────── */
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

  /* ── New video scan page ──────────────────────────────────────────────── */
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
            neck_angle: 'Neck angle (°)', trunk_angle: 'Trunk angle (°)',
            upper_arm_angle: 'Upper arm (°)', lower_arm_angle: 'Lower arm (°)',
            wrist_angle: 'Wrist (°)', leg_score: 'Leg score',
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
      if (!this.scan) return '–';
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

  /* ── Scan results page ────────────────────────────────────────────────── */
  Alpine.data('scanResultsPage', () => ({
    scanId: null, scan: null, loading: true, error: '', pending: false,
    scanInvalid: false, errorMessage: '',
    measurements: [], recommendation: '', controls: [],
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
          neck_angle: 'Neck angle (°)', trunk_angle: 'Trunk angle (°)',
          upper_arm_angle: 'Upper arm (°)', lower_arm_angle: 'Lower arm (°)',
          wrist_angle: 'Wrist (°)', leg_score: 'Leg score',
          load_weight: 'Load (kg)', coupling: 'Coupling',
          horizontal_distance: 'H. distance (cm)', vertical_start: 'V. start (cm)',
          vertical_travel: 'V. travel (cm)', twist_angle: 'Twist (°)',
          frequency: 'Frequency', shoulder_elevation_duration: 'Shoulder elev. (s)',
          repetition_count: 'Repetitions', processing_confidence: 'Confidence'
        };
        this.measurements = Object.entries(metricLabels)
          .filter(([k]) => metrics[k] != null && metrics[k] !== '')
          .map(([k, label]) => ({ label, value: metrics[k] }));

        // Recommendation from scan_results
        this.recommendation = s.recommendation || '';
        this.controls = Array.isArray(s.controls) ? s.controls : [];

        this.pending = (s.status === 'pending' || s.status === 'processing');
        if (this.pending) {
          clearTimeout(this._pollTimer);
          this._pollTimer = setTimeout(() => this.loadScan(), 5000);
        }
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    get score() {
      if (!this.scan) return '–';
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

  /* ── Observer rating page ─────────────────────────────────────────────── */
  Alpine.data('observerRatePage', () => ({
    scanId: null, scan: null, ratings: [],
    loading: true, error: '', formError: '', saving: false, submitted: false,
    form: { observer_score: '', observer_category: '', notes: '' },
    init() {
      const parts = location.pathname.split('/').filter(Boolean);
      // Supports two URL patterns:
      //   /scans/{id}/observe   → parts = ['scans', '{id}', 'observe']
      //   /observer-rating?scan_id={id}  → read from query string
      if (parts[0] === 'scans') {
        this.scanId = parts[1] || null;
      } else {
        this.scanId = new URLSearchParams(location.search).get('scan_id') || null;
      }
      if (!this.scanId) {
        // No scan context — send to scans list so the user can pick one
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

  /* ── Scan comparison page ─────────────────────────────────────────────── */
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

  /* ────────────────────────────────────────────────────────────────────────
   * ADVANCED SCAN COMPARISON  (/scans/compare)
   * Components: ScanSelector · SkeletonViewer · ScoreDeltaCard
   *             JointHeatmap · ComparisonTree · Timeline
   * ──────────────────────────────────────────────────────────────────────── */

  Alpine.data('scanAdvancedComparePage', () => ({

    /* ── State ─────────────────────────────────────────────────────── */
    loadingScans: true,
    scansError:   '',
    scans:        [],         // completed scans available for selection

    comparing:   false,
    error:       '',
    comparison:  null,        // full API response from /scans/compare

    scanAId: '',              // selected scan IDs (strings for <select> binding)
    scanBId: '',

    _chart: null,             // Chart.js instance

    /* ── Lifecycle ─────────────────────────────────────────────────── */
    async init() {
      await this.loadScans();
      // Support deep-link: /scans/compare?a=1&b=2
      const p = new URLSearchParams(location.search);
      const a = p.get('a'), b = p.get('b');
      if (a) this.scanAId = String(a);
      if (b) this.scanBId = String(b);
      if (a && b) await this.runComparison();
    },

    /* ── Data loading ──────────────────────────────────────────────── */
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

    /* ── Computed getters ──────────────────────────────────────────── */
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

    /* ── Skeleton colour builder ───────────────────────────────────── */
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

    /* ── Risk utilities ────────────────────────────────────────────── */
    riskColor(score) {
      if (score == null) return '#cbd5e1';
      if (score < 30) return '#22c55e';
      if (score < 55) return '#f59e0b';
      if (score < 75) return '#f97316';
      return '#ef4444';
    },
    riskLabel(score) {
      if (score == null) return '—';
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

    /* ── Formatting ────────────────────────────────────────────────── */
    fmtDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
      });
    },
    fmtScore(v) {
      if (v == null) return '—';
      return Number(v).toFixed(1);
    },
    prettyKey(k) {
      return k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    },
    /** Label shown in the <select> dropdowns */
    scanLabel(s) {
      const date = this.fmtDate(s.created_at);
      const risk = s.risk_category ? ' [' + s.risk_category + ']' : '';
      return '#' + s.id + ' · ' + (s.model || '').toUpperCase() + ' · ' + date + risk;
    },

    /* ── Timeline (Chart.js) ───────────────────────────────────────── */
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
                  const tag = String(s.id) === String(this.scanAId) ? ' ← Scan A'
                            : String(s.id) === String(this.scanBId) ? ' ← Scan B' : '';
                  return '#' + s.id + tag + '  ·  ' + ctx[0].label;
                },
                label: (ctx) => ' Risk Score: ' + (ctx.parsed.y != null ? ctx.parsed.y.toFixed(1) : '—'),
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

  /* ────────────────────────────────────────────────────────────────────────
   * ADMIN PAGES
   * ──────────────────────────────────────────────────────────────────────── */

  /* ── Admin Dashboard page ─────────────────────────────────────────────── */
  Alpine.data('adminDashboardPage', () => ({
    stats: {}, loading: true, error: '',
    async init() {
      try {
        const d = await api('/admin/stats');
        this.stats = d.data ?? d;
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    fmtDate(d) { return d ? new Date(d).toLocaleDateString() : '—'; },
    fmtCurrency(v) { return Number(v || 0).toFixed(2); }
  }));

  /* ── Admin Organizations page ─────────────────────────────────────────── */
  Alpine.data('adminOrgsPage', () => ({
    orgs: [], loading: true, error: '', search: '',
    editingOrg: null, form: { name: '', contact_email: '', status: 'active' }, formError: '',
    savingOrg: false,
    togglingOrgId: null,
    _modal: null,
    async init() { await this.load(); },
    async load() {
      this.loading = true; this.error = '';
      try {
        const d = await api('/admin/organizations');
        this.orgs = d.data ?? d;
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    get filtered() {
      const q = this.search.toLowerCase();
      if (!q) return this.orgs;
      return this.orgs.filter(o =>
        o.name.toLowerCase().includes(q) || (o.slug || '').toLowerCase().includes(q)
      );
    },
    openCreate() {
      this.editingOrg = null;
      this.form = { name: '', contact_email: '', status: 'active' };
      this.formError = '';
      this._getModal().show();
    },
    openEdit(org) {
      this.editingOrg = org;
      this.form = { name: org.name, contact_email: org.contact_email || '', status: org.status || 'active' };
      this.formError = '';
      this._getModal().show();
    },
    async saveOrg() {
      this.formError = '';
      this.savingOrg = true;
      try {
        if (this.editingOrg) {
          await api('/admin/organizations/' + this.editingOrg.id, { method: 'PUT', body: JSON.stringify(this.form) });
        } else {
          await api('/admin/organizations', { method: 'POST', body: JSON.stringify(this.form) });
        }
        this._getModal().hide();
        await this.load();
      } catch (e) { this.formError = e.message; }
      finally { this.savingOrg = false; }
    },
    async toggleStatus(org) {
      const newStatus = org.status === 'active' ? 'suspended' : 'active';
      const ok = await appConfirm(
        newStatus === 'suspended'
          ? 'Suspend this organization? Users in this organization may lose access.'
          : 'Activate this organization?',
        {
          title: newStatus === 'suspended' ? 'Suspend Organization' : 'Activate Organization',
          okText: newStatus === 'suspended' ? 'Suspend' : 'Activate',
          variant: newStatus === 'suspended' ? 'warning' : 'success',
        }
      );
      if (!ok) return;
      this.togglingOrgId = org.id;
      try {
        await api('/admin/organizations/' + org.id, { method: 'PUT', body: JSON.stringify({ status: newStatus }) });
        await this.load();
      } catch (e) { this.error = e.message; }
      finally { this.togglingOrgId = null; }
    },
    fmtDate(d) { return d ? new Date(d).toLocaleDateString() : '—'; },
    _getModal() {
      if (!this._modal) this._modal = new bootstrap.Modal(document.getElementById('orgModal'));
      return this._modal;
    }
  }));

  /* ── Admin Users page ─────────────────────────────────────────────────── */
  Alpine.data('adminUsersPage', () => ({
    users: [], loading: true, error: '', search: '', filterRole: '', filterStatus: '',
    editingUser: null, editForm: { name: '', email: '', role: '', status: '' }, formError: '',
    savingUser: false,
    deletingUserLoading: false,
    deletingUserId: null,
    _editModal: null,
    async init() { await this.load(); },
    async load() {
      this.loading = true; this.error = '';
      try {
        const d = await api('/admin/users');
        this.users = d.data ?? d;
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    get filtered() {
      let list = this.users;
      const q = this.search.toLowerCase();
      if (q) list = list.filter(u => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q));
      if (this.filterRole) list = list.filter(u => u.role === this.filterRole);
      if (this.filterStatus) list = list.filter(u => (u.status || 'active') === this.filterStatus);
      return list;
    },
    roleBadge(role) {
      const map = {
        super_admin: 'badge-soft-danger',
        admin: 'badge-soft-primary',
        supervisor: 'badge-soft-info',
        worker: 'badge-soft-secondary',
        observer: 'badge-soft-warning'
      };
      return map[role] || 'badge-soft-secondary';
    },
    openEdit(u) {
      this.editingUser = u;
      this.editForm = { name: u.name, email: u.email, role: u.role, status: u.status || 'active' };
      this.formError = '';
      this._getEditModal().show();
    },
    async saveUser() {
      this.formError = '';
      this.savingUser = true;
      try {
        await api('/admin/users/' + this.editingUser.id, { method: 'PUT', body: JSON.stringify(this.editForm) });
        this._getEditModal().hide();
        await this.load();
      } catch (e) { this.formError = e.message; }
      finally { this.savingUser = false; }
    },
    async requestDelete(u) {
      if (this.deletingUserLoading) return;
      const ok = await appConfirm(
        'Delete ' + (u.name || 'this user') + '? This action cannot be undone.',
        { title: 'Delete User', okText: 'Delete', variant: 'danger' }
      );
      if (!ok) return;

      this.deletingUserLoading = true;
      this.deletingUserId = u.id;
      try {
        await api('/admin/users/' + u.id, { method: 'DELETE' });
        await this.load();
      } catch (e) { this.error = e.message; }
      finally {
        this.deletingUserLoading = false;
        this.deletingUserId = null;
      }
    },
    _getEditModal() {
      if (!this._editModal) this._editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
      return this._editModal;
    },
  }));

  /* ── Admin Plans page ─────────────────────────────────────────────────── */
  Alpine.data('adminPlansPage', () => ({
    plans: [], loading: true, error: '',
    search: '',
    saving: false,
    deleting: false,
    get filtered() {
      const q = this.search.toLowerCase().trim();
      if (!q) return this.plans;
      return this.plans.filter(p => (p.name || '').toLowerCase().includes(q));
    },
    editingPlan: null,
    deletingPlanId: null,
    form: { name: '', price: '', scan_limit: '', billing_cycle: 'monthly', status: 'active' },
    formError: '',
    _modal: null,
    async init() { await this.load(); },
    async load() {
      this.loading = true; this.error = '';
      try {
        const d = await api('/admin/plans');
        this.plans = d.data ?? d;
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    openCreate() {
      this.editingPlan = null;
      this.form = { name: '', price: '', scan_limit: '', billing_cycle: 'monthly', status: 'active' };
      this.formError = '';
      this._getModal().show();
    },
    openEdit(plan) {
      this.editingPlan = plan;
      this.form = {
        name: plan.name,
        price: plan.price,
        scan_limit: plan.scan_limit ?? '',
        billing_cycle: plan.billing_cycle || 'monthly',
        status: plan.status || 'active'
      };
      this.formError = '';
      this._getModal().show();
    },
    async savePlan() {
      this.formError = '';
      this.saving = true;
      const payload = { ...this.form };
      if (payload.scan_limit === '' || payload.scan_limit === null) payload.scan_limit = null;
      else payload.scan_limit = parseInt(payload.scan_limit, 10);
      payload.price = parseFloat(payload.price);
      try {
        if (this.editingPlan) {
          await api('/admin/plans/' + this.editingPlan.id, { method: 'PUT', body: JSON.stringify(payload) });
        } else {
          await api('/admin/plans', { method: 'POST', body: JSON.stringify(payload) });
        }
        this._getModal().hide();
        await this.load();
      } catch (e) { this.formError = e.message; }
      finally { this.saving = false; }
    },
    async requestDelete(plan) {
      if (this.deleting) return;
      const ok = await appConfirm(
        'Delete ' + (plan.name || 'this plan') + '? Organizations on this plan keep access until next renewal.',
        { title: 'Delete Plan', okText: 'Delete', variant: 'danger' }
      );
      if (!ok) return;

      this.deleting = true;
      this.deletingPlanId = plan.id;
      try {
        await api('/admin/plans/' + plan.id, { method: 'DELETE' });
        await this.load();
      } catch (e) { this.error = e.message; }
      finally {
        this.deleting = false;
        this.deletingPlanId = null;
      }
    },
    _getModal() {
      if (!this._modal) this._modal = new bootstrap.Modal(document.getElementById('planModal'));
      return this._modal;
    },
  }));

  /* ────────────────────────────────────────────────────────────────────────
   * ORGANIZATION PAGES
   * ──────────────────────────────────────────────────────────────────────── */

  /* ── Org Users page ───────────────────────────────────────────────────── */
  Alpine.data('orgUsersPage', () => ({
    members: [], loading: true, error: '',
    memberSearch: '',
    get filteredMembers() {
      const q = this.memberSearch.toLowerCase().trim();
      if (!q) return this.members;
      return this.members.filter(m =>
        (m.name || '').toLowerCase().includes(q) ||
        (m.email || '').toLowerCase().includes(q)
      );
    },
    inviteForm: { name: '', email: '', password: '', role: 'worker' }, formError: '',
    editingMember: null, newRole: '',
    inviteSending: false,
    roleSaving: false,
    removeSaving: false,
    removingMemberId: null,
    _inviteModal: null, _roleModal: null,
    async init() { await this.load(); },
    async load() {
      this.loading = true; this.error = '';
      try {
        const d = await api('/org/members');
        this.members = d.data ?? d;
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    roleBadge(role) {
      const map = {
        super_admin: 'badge-soft-danger',
        admin: 'badge-soft-primary',
        supervisor: 'badge-soft-info',
        worker: 'badge-soft-secondary',
        observer: 'badge-soft-warning'
      };
      return map[role] || 'badge-soft-secondary';
    },
    openInvite() {
      this.inviteForm = { name: '', email: '', password: '', role: 'worker' };
      this.formError = '';
      this._getInviteModal().show();
    },
    async sendInvite() {
      this.formError = '';
      this.inviteSending = true;
      try {
        await api('/org/members', { method: 'POST', body: JSON.stringify(this.inviteForm) });
        this._getInviteModal().hide();
        await this.load();
      } catch (e) { this.formError = e.message; }
      finally { this.inviteSending = false; }
    },
    openRoleEdit(m) {
      this.editingMember = m;
      this.newRole = m.role;
      this._getRoleModal().show();
    },
    async saveRole() {
      this.roleSaving = true;
      try {
        await api('/org/members/' + this.editingMember.id, { method: 'PUT', body: JSON.stringify({ role: this.newRole }) });
        this._getRoleModal().hide();
        await this.load();
      } catch (e) { this.error = e.message; }
      finally { this.roleSaving = false; }
    },
    async requestRemove(m) {
      if (this.removeSaving) return;
      const ok = await appConfirm(
        'Remove ' + (m.name || 'this member') + ' from your organization? They will lose access immediately.',
        { title: 'Remove Member', okText: 'Remove', variant: 'danger' }
      );
      if (!ok) return;

      this.removeSaving = true;
      this.removingMemberId = m.id;
      try {
        await api('/org/members/' + m.id, { method: 'DELETE' });
        await this.load();
      } catch (e) { this.error = e.message; }
      finally {
        this.removeSaving = false;
        this.removingMemberId = null;
      }
    },
    _getInviteModal() {
      if (!this._inviteModal) this._inviteModal = new bootstrap.Modal(document.getElementById('inviteModal'));
      return this._inviteModal;
    },
    _getRoleModal() {
      if (!this._roleModal) this._roleModal = new bootstrap.Modal(document.getElementById('roleModal'));
      return this._roleModal;
    },
  }));

  /* ── User Profile page ──────────────────────────────────────────────── */
  Alpine.data('userProfilePage', () => ({
    profile: {}, loading: true,
    form: { name: '', email: '' },
    saving: false, saveSuccess: '', saveError: '',

    // ── 2FA state ──────────────────────────────────────────────────
    twoFaEnabled: false, twoFaLoading: true,
    twoFaSetupLoading: false, twoFaConfirmLoading: false, twoFaDisableLoading: false,
    setupStep: 'idle',        // 'idle' | 'qr' | 'done'
    twoFaSecret: '', twoFaQrUri: '',
    twoFaCode: '', twoFaError: '', twoFaMsg: '',

    async init() {
      try {
        const res = await api('/user/profile');
        this.profile = res.data ?? res;
        this.form = {
          name:  this.profile.name  || '',
          email: this.profile.email || '',
        };
      } catch (e) { this.saveError = e.message; }
      finally { this.loading = false; }

      // Load 2FA status
      try {
        const s = await api('/auth/2fa/status');
        this.twoFaEnabled = s.enabled ?? false;
      } catch (_) {}
      finally { this.twoFaLoading = false; }
    },

    async saveProfile() {
      this.saveSuccess = ''; this.saveError = ''; this.saving = true;
      try {
        const res = await api('/user/profile', { method: 'PUT', body: JSON.stringify(this.form) });
        const data = res.data ?? res;
        this.profile = data;
        this.saveSuccess = 'Profile updated successfully.';
      } catch (e) { this.saveError = e.message; }
      finally { this.saving = false; }
    },

    resetForm() {
      this.form = { name: this.profile.name || '', email: this.profile.email || '' };
      this.saveSuccess = ''; this.saveError = '';
    },

    /* Start 2FA setup */
    async start2faSetup() {
      this.twoFaError = ''; this.twoFaMsg = ''; this.twoFaSetupLoading = true;
      try {
        const d = await api('/auth/2fa/setup', { method: 'POST' });
        this.twoFaSecret = d.secret;
        this.twoFaQrUri  = d.qr_uri;
        this.setupStep   = 'qr';
      } catch (e) { this.twoFaError = e.message; }
      finally { this.twoFaSetupLoading = false; }
    },

    /* Confirm 2FA */
    async confirm2fa() {
      this.twoFaError = ''; this.twoFaConfirmLoading = true;
      if (!this.twoFaCode || this.twoFaCode.length < 6) {
        this.twoFaError = 'Please enter a 6-digit code.'; this.twoFaConfirmLoading = false; return;
      }
      try {
        await api('/auth/2fa/confirm', {
          method: 'POST',
          body: JSON.stringify({ secret: this.twoFaSecret, code: this.twoFaCode }),
        });
        this.twoFaEnabled = true;
        this.setupStep    = 'done';
        this.twoFaMsg     = 'Two-factor authentication has been enabled.';
        this.twoFaCode    = '';
      } catch (e) { this.twoFaError = e.message; }
      finally { this.twoFaConfirmLoading = false; }
    },

    /* Disable 2FA */
    async disable2fa() {
      if (this.twoFaDisableLoading) return;
      const ok = await appConfirm(
        'Disable two-factor authentication for your account? This reduces sign-in security.',
        { title: 'Disable Two-Factor Authentication', okText: 'Disable 2FA', variant: 'danger' }
      );
      if (!ok) return;

      this.twoFaError = ''; this.twoFaMsg = ''; this.twoFaDisableLoading = true;
      try {
        await api('/auth/2fa/disable', { method: 'POST' });
        this.twoFaEnabled = false;
        this.setupStep = 'idle';
        this.twoFaMsg = 'Two-factor authentication has been disabled.';
      } catch (e) { this.twoFaError = e.message; }
      finally { this.twoFaDisableLoading = false; }
    },

    cancel2faSetup() {
      this.setupStep = 'idle';
      this.twoFaSecret = '';
      this.twoFaQrUri  = '';
      this.twoFaCode   = '';
      this.twoFaError  = '';
    },

    fmtDate(d) { return d ? new Date(d).toLocaleDateString() : '—'; }
  }));

  /* ── Org Settings page ────────────────────────────────────────────────── */
  Alpine.data('orgSettingsPage', () => ({
    org: {}, subscription: {}, loading: true, error: '',
    form: {
      name: '', slug: '', contact_email: '',
      industry: '', size: '', website: '', theme_color: '',
      default_model: '', video_retention_days: 30, auto_delete_video: false,
      recommendation_policy: {
        thresholds: {
          trunk_flexion_high: 45,
          trunk_flexion_moderate: 25,
          upper_arm_elevation_high: 60,
          repetition_high: 20,
          lifting_load: 12,
        },
        ranking: {
          cost_penalty_factor: 1.1,
          impact_penalty_factor: 0.8,
          reduction_factor: 1.0,
          strict_hierarchy: true,
        },
        feasibility: {
          minimum_total_score: 60,
          minimum_policy_compliance: 55,
        },
        interim: {
          max_days_without_interim: 14,
          allow_ppe_interim: true,
        },
      },
    },
    saving: false, saveSuccess: '', saveError: '',

    async init() {
      try {
        const [settings, sub] = await Promise.all([
          api('/org/settings'),
          api('/org/subscription').catch(() => null)
        ]);
        this.org  = settings.data ?? settings;

        // Flatten subscription: API returns { plan:{name,scan_limit,...}, usage:{used,...} }
        // but the view expects flat props like subscription.plan_name, subscription.status, etc.
        if (sub) {
          const raw = sub.data ?? sub;
          const plan  = raw.plan  || {};
          const usage = raw.usage || {};
          this.subscription = {
            plan_name:     plan.name    || null,
            scan_limit:    plan.scan_limit ?? null,
            price:         plan.price   || 0,
            status:        plan.status  || 'inactive',
            billing_cycle: plan.billing_cycle || null,
            expires_at:    plan.end_date || null,
            scans_used:    usage.used   || 0,
          };
        }
        this._populateForm();
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },

    _populateForm() {
      const rp = this.org.recommendation_policy || {};
      const thresholds = rp.thresholds || {};
      const ranking = rp.ranking || {};
      const feasibility = rp.feasibility || {};
      const interim = rp.interim || {};

      this.form = {
        name:                this.org.name || '',
        slug:                this.org.slug || '',
        contact_email:       this.org.contact_email || '',
        industry:            this.org.industry || '',
        size:                this.org.size || '',
        website:             this.org.website || '',
        theme_color:         this.org.theme_color || '',
        default_model:       this.org.default_model || '',
        video_retention_days: this.org.video_retention_days ?? 30,
        auto_delete_video:   !!this.org.auto_delete_video,
        recommendation_policy: {
          thresholds: {
            trunk_flexion_high: Number(thresholds.trunk_flexion_high ?? 45),
            trunk_flexion_moderate: Number(thresholds.trunk_flexion_moderate ?? 25),
            upper_arm_elevation_high: Number(thresholds.upper_arm_elevation_high ?? 60),
            repetition_high: Number(thresholds.repetition_high ?? 20),
            lifting_load: Number(thresholds.lifting_load ?? 12),
          },
          ranking: {
            cost_penalty_factor: Number(ranking.cost_penalty_factor ?? 1.1),
            impact_penalty_factor: Number(ranking.impact_penalty_factor ?? 0.8),
            reduction_factor: Number(ranking.reduction_factor ?? 1.0),
            strict_hierarchy: ranking.strict_hierarchy !== false,
          },
          feasibility: {
            minimum_total_score: Number(feasibility.minimum_total_score ?? 60),
            minimum_policy_compliance: Number(feasibility.minimum_policy_compliance ?? 55),
          },
          interim: {
            max_days_without_interim: Number(interim.max_days_without_interim ?? 14),
            allow_ppe_interim: interim.allow_ppe_interim !== false,
          },
        },
      };
    },

    async saveSettings() {
      this.saveSuccess = ''; this.saveError = ''; this.saving = true;
      try {
        await api('/org/settings', { method: 'PUT', body: JSON.stringify(this.form) });
        this.saveSuccess = 'Settings saved successfully.';
        // Apply theme color immediately
        if (this.form.theme_color) {
          document.documentElement.style.setProperty('--we-primary', this.form.theme_color);
        }
      } catch (e) { this.saveError = e.message; }
      finally { this.saving = false; }
    },
    resetForm() {
      this._populateForm();
      this.saveSuccess = ''; this.saveError = '';
    },
    fmtDate(d) { return d ? new Date(d).toLocaleDateString() : '—'; }
  }));

  /* ── Leading Indicators Check-in page ─────────────────────────────────── */
  Alpine.data('leadingIndicatorsPage', () => ({
    tasks: [],
    summary: null,
    mine: { entries: [] },
    loadingMine: false,
    saving: false,
    success: '',
    error: '',
    form: {
      task_id: '',
      shift_date: new Date().toISOString().slice(0, 10),
      discomfort_level: 0,
      fatigue_level: 0,
      micro_breaks_taken: 0,
      recovery_minutes: 0,
      overtime_minutes: 0,
      task_rotation_quality: 'fair',
      psychosocial_load: 'moderate',
      notes: '',
    },

    async init() {
      this.error = '';
      await this.loadTasks();
      await this.loadMine();
      await this.tryLoadSummary();
    },

    async loadTasks() {
      try {
        this.tasks = await api('/tasks');
      } catch (_) {
        this.tasks = [];
      }
    },

    async loadMine() {
      this.loadingMine = true;
      try {
        this.mine = await api('/leading-indicators/mine?days=14');
      } catch (e) {
        this.error = e.message;
      } finally {
        this.loadingMine = false;
      }
    },

    async tryLoadSummary() {
      try {
        this.summary = await api('/leading-indicators/summary?days=30');
      } catch (_) {
        this.summary = null;
      }
    },

    resetForm() {
      this.form = {
        task_id: '',
        shift_date: new Date().toISOString().slice(0, 10),
        discomfort_level: 0,
        fatigue_level: 0,
        micro_breaks_taken: 0,
        recovery_minutes: 0,
        overtime_minutes: 0,
        task_rotation_quality: 'fair',
        psychosocial_load: 'moderate',
        notes: '',
      };
      this.error = '';
    },

    async submit() {
      this.saving = true;
      this.success = '';
      this.error = '';
      try {
        await api('/leading-indicators', {
          method: 'POST',
          body: JSON.stringify({
            ...this.form,
            task_id: this.form.task_id ? Number(this.form.task_id) : null,
          }),
        });

        this.success = 'Check-in submitted successfully.';
        await this.loadMine();
        await this.tryLoadSummary();
      } catch (e) {
        this.error = e.message;
      } finally {
        this.saving = false;
      }
    },
  }));

  /* ── Admin System Settings page ──────────────────────────────────────── */
  Alpine.data('adminSettingsPage', () => ({
    loading: true, saving: false,
    saveSuccess: '', saveError: '',
    showSecret: false,
    form: {
      app_name: '', support_email: '', allow_registrations: true,
      payment_gateway: '', payment_public_key: '', payment_secret_key: '',
    },
    _original: {},

    async init() {
      try {
        const res = await api('/admin/settings');
        const data = res.data ?? res;
        this.form = {
          app_name:            data.app_name || '',
          support_email:       data.support_email || '',
          allow_registrations: data.allow_registrations ?? true,
          payment_gateway:     data.payment_gateway || '',
          payment_public_key:  data.payment_public_key || '',
          payment_secret_key:  data.payment_secret_key || '',
        };
        this._original = { ...this.form };
      } catch (e) { this.saveError = e.message; }
      finally { this.loading = false; }
    },

    async saveSettings() {
      this.saveSuccess = ''; this.saveError = ''; this.saving = true;
      try {
        await api('/admin/settings', { method: 'PUT', body: JSON.stringify(this.form) });
        this.saveSuccess = 'System settings saved successfully.';
        this._original = { ...this.form };
      } catch (e) { this.saveError = e.message; }
      finally { this.saving = false; }
    },

    resetForm() {
      this.form = { ...this._original };
      this.saveSuccess = ''; this.saveError = '';
    }
  }));

  /* ── Notifications dropdown ─────────────────────────────────────────── */
  Alpine.data('notificationsDropdown', () => ({
    open: false, loading: false,
    markingAllRead: false,
    items: [], unreadCount: 0,
    _pollTimer: null,
    _loaded: false,                       // true after first full load
    init() {
      this.fetchCount();
      this._pollTimer = setInterval(() => this.fetchCount(), 60000);
    },
    destroy() { clearInterval(this._pollTimer); },
    async fetchCount() {
      try {
        const d = await api('/notifications/unread-count');
        this.unreadCount = d.count ?? 0;
      } catch (_) { /* noop */ }
    },
    async toggle() {
      this.open = !this.open;
      if (this.open) {
        if (!this._loaded) {
          await this._fullLoad();         // first open — full fetch
        } else {
          await this._refreshNew();       // subsequent — only new items
        }
      }
    },
    /* Full load — runs once on first open */
    async _fullLoad() {
      this.loading = true;
      try {
        const data = await api('/notifications');
        this.items = Array.isArray(data) ? data : (data.data ?? []);
        this.unreadCount = this.items.filter(n => !parseInt(n.is_read)).length;
        this._loaded = true;
      } catch (_) { /* noop */ }
      finally { this.loading = false; }
    },
    /* Incremental refresh — prepend new items & update count */
    async _refreshNew() {
      try {
        const data = await api('/notifications');
        const fresh = Array.isArray(data) ? data : (data.data ?? []);
        const existingIds = new Set(this.items.map(n => String(n.id)));
        const newItems = fresh.filter(n => !existingIds.has(String(n.id)));
        if (newItems.length) {
          this.items = [...newItems, ...this.items];
        }
        // Also sync read-status for existing items
        const freshMap = new Map(fresh.map(n => [String(n.id), n]));
        this.items.forEach(n => {
          const f = freshMap.get(String(n.id));
          if (f) n.is_read = f.is_read;
        });
        this.unreadCount = this.items.filter(n => !parseInt(n.is_read)).length;
      } catch (_) { /* noop */ }
    },
    async markRead(n) {
      if (parseInt(n.is_read)) return;
      n.is_read = '1';
      this.unreadCount = Math.max(0, this.unreadCount - 1);
      try { await api('/notifications/' + n.id + '/read', { method: 'PUT' }); } catch (_) {}
    },
    async markAllRead() {
      if (this.markingAllRead) return;
      this.markingAllRead = true;
      this.items.forEach(n => n.is_read = '1');
      this.unreadCount = 0;
      try { await api('/notifications/read-all', { method: 'PUT' }); } catch (_) {}
      finally { this.markingAllRead = false; }
    },
    notifIcon(type) {
      const map = { scan_complete: 'bi-check-circle', high_risk: 'bi-exclamation-triangle', observer_rated: 'bi-pencil-square', member_joined: 'bi-person-plus', plan_changed: 'bi-box', announcement: 'bi-megaphone' };
      return map[type] || 'bi-bell';
    },
    notifIconClass(type) {
      const map = { scan_complete: 'success', high_risk: 'danger', observer_rated: 'info', member_joined: 'primary', plan_changed: 'warning', announcement: 'primary' };
      return 'notif-icon-' + (map[type] || 'secondary');
    },
    timeAgo(dateStr) {
      const s = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
      if (s < 60) return 'just now';
      const m = Math.floor(s / 60);
      if (m < 60) return m + 'm ago';
      const h = Math.floor(m / 60);
      if (h < 24) return h + 'h ago';
      const d = Math.floor(h / 24);
      return d + 'd ago';
    }
  }));

  /* ── Org Billing page ─────────────────────────────────────────────────── */
  Alpine.data('orgBillingPage', () => ({
    sub: {}, plans: [], invoices: [], loading: true, error: '', changing: false,
    changingPlanId: null,
    changeSuccess: false, changeError: '',
    chargingInvoiceId: null, chargeSuccess: '', chargeError: '',
    paymentToken: '',
    async init() {
      await this.load();
    },
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const [subRes, plansRes, invoicesRes] = await Promise.all([
          api('/org/subscription'),
          api('/billing/plans'),
          api('/billing/invoices').catch(() => ({ data: [] }))
        ]);

        const rawSub = subRes.data ?? subRes;
        const plan = rawSub.plan || {};
        const usage = rawSub.usage || {};

        this.sub = {
          ...rawSub,
          plan,
          usage,
          plan_name: rawSub.plan_name || plan.name || null,
          amount: rawSub.amount ?? plan.price ?? 0,
          billing_cycle: rawSub.billing_cycle || plan.billing_cycle || usage.billing_cycle || null,
          status: rawSub.status || plan.status || 'inactive',
          scan_limit: rawSub.scan_limit ?? plan.scan_limit ?? usage.limit ?? null,
          scans_used: rawSub.scans_used ?? usage.used ?? 0,
          reserved_scans: rawSub.reserved_scans ?? usage.reserved_scans ?? 0,
          billed_scans: rawSub.billed_scans ?? usage.billed_scans ?? null,
          period_start: rawSub.period_start || usage.period_start || null,
          period_end: rawSub.period_end || usage.period_end || null,
          member_limit: rawSub.member_limit ?? plan.member_limit ?? null,
          members_used: rawSub.members_used ?? usage.members_used ?? 0,
          expires_at: rawSub.expires_at || plan.end_date || null,
        };

        this.plans = plansRes.data ?? plansRes;
        this.invoices = invoicesRes.data ?? invoicesRes ?? [];
      } catch (e) {
        this.error = e.message;
      } finally {
        this.loading = false;
      }
    },
    get usagePercent() {
      const limit = this.sub?.usage?.limit ?? this.sub?.scan_limit;
      const used  = this.sub?.usage?.used ?? this.sub?.scans_used ?? 0;
      if (!limit) return 0;
      return Math.min(100, Math.round((used / limit) * 100));
    },
    isCurrent(plan) {
      return String(plan.id) === String(this.sub?.plan?.id);
    },
    usagePeriodLabel() {
      if (!this.sub?.period_start || !this.sub?.period_end) return 'Current period';
      return this.fmtDate(this.sub.period_start) + ' - ' + this.fmtDate(this.sub.period_end);
    },
    fmtDate(d) {
      return d ? new Date(d).toLocaleDateString() : '—';
    },
    fmtDateTime(d) {
      return d ? new Date(d).toLocaleString() : '—';
    },
    fmtMoney(amount, currency = 'USD') {
      const value = Number(amount || 0);
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: (currency || 'USD').toUpperCase(),
        minimumFractionDigits: 2,
      }).format(value);
    },
    invoiceStatusClass(status) {
      const key = String(status || '').toLowerCase();
      if (key === 'paid') return 'badge-soft-success';
      if (key === 'failed') return 'badge-soft-danger';
      return 'badge-soft-warning';
    },
    async chargeInvoice(invoiceId) {
      this.chargeError = '';
      this.chargeSuccess = '';
      this.chargingInvoiceId = String(invoiceId);
      try {
        const payload = {};
        if ((this.paymentToken || '').trim() !== '') {
          payload.payment_token = this.paymentToken.trim();
        }

        const res = await api('/billing/invoices/' + invoiceId + '/charge', {
          method: 'POST',
          body: JSON.stringify(payload),
        });

        const charge = res.charge || (res.data && res.data.charge) || {};
        this.chargeSuccess = charge.message || 'Charge request sent.';
        await this.load();
      } catch (e) {
        this.chargeError = e.message;
      } finally {
        this.chargingInvoiceId = null;
      }
    },
    async changePlan(planId) {
      this.changing = true;
      this.changingPlanId = String(planId);
      this.changeError = '';
      this.changeSuccess = false;
      try {
        await api('/org/subscription', { method: 'PUT', body: JSON.stringify({ plan_id: planId }) });
        this.changeSuccess = true;
        await this.load();
        setTimeout(() => { this.changeSuccess = false; }, 2500);
      } catch (e) {
        this.changeError = e.message;
      } finally {
        this.changing = false;
        this.changingPlanId = null;
      }
    }
  }));

});

// End of Alpine.js components
