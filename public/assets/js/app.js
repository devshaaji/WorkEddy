/**
 * WorkEddy – Alpine.js API client & page components.
 * Uses Fetch API under the hood. Zero inline JavaScript.
 */

/* ────────────────────────────────────────────────────────────────────────────
 * API client (private helpers)
 * ──────────────────────────────────────────────────────────────────────────── */

const API_BASE = '/api/v1';

function _token()   { return localStorage.getItem('we_token') || ''; }
function _save(t)   {
  localStorage.setItem('we_token', t);
  document.cookie = 'we_token=' + encodeURIComponent(t) + ';path=/;max-age=86400;SameSite=Lax';
}
function _clear()   {
  localStorage.removeItem('we_token');
  document.cookie = 'we_token=;path=/;max-age=0;SameSite=Lax';
}

async function api(path, opts = {}) {
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
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    if (onProgress) {
      xhr.upload.addEventListener('progress', e => {
        if (e.lengthComputable) onProgress(Math.round(e.loaded / e.total * 100));
      });
    }
    xhr.onload = () => {
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
 * Alpine.js – Auth guard (runs on main layout)
 * ──────────────────────────────────────────────────────────────────────────── */

document.addEventListener('alpine:init', () => {

  /* ── Auth guard store ─────────────────────────────────────────────────── */
  Alpine.store('auth', {
    role: null,
    orgId: null,
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
      } catch (_) { /* noop */ }
      return true;
    }
  });

  /* ── Login page ───────────────────────────────────────────────────────── */
  Alpine.data('loginPage', () => ({
    email: '', password: '', error: '', loading: false,
    async submit() {
      this.error = ''; this.loading = true;
      try {
        const d = await api('/auth/login', { method: 'POST', body: JSON.stringify({ email: this.email, password: this.password }) });
        _save(d.token);
        location.href = '/dashboard';
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
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
    tasks: [], selectedTask: '',
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
      try {
        const [tasks, allModels] = await Promise.all([api('/tasks'), api('/scans/models')]);
        this.tasks = tasks;
        this.models = allModels.filter(m => m.input_types.includes('manual'));
      } catch (_) { /* skip */ }
      if (this.models.length && !this.models.find(m => m.value === this.model)) {
        this.model = this.models[0].value;
      }
      if (pre) this.selectedTask = pre;
      else if (this.tasks.length) this.selectedTask = String(this.tasks[0].id);
    },
    async submit() {
      this.error = ''; this.loading = true;
      try {
        const payload = { task_id: this.selectedTask, model: this.model, ...this.form };
        const result = await api('/scans/manual', { method: 'POST', body: JSON.stringify(payload) });
        location.href = '/scans/' + result.id;
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    }
  }));

  /* ── New video scan page ──────────────────────────────────────────────── */
  Alpine.data('videoScanPage', () => ({
    tasks: [], selectedTask: '', error: '',
    model: 'reba',
    models: [],
    uploading: false, progress: 0,
    async init() {
      const p = new URLSearchParams(location.search);
      const pre = p.get('task_id');
      try {
        const [tasks, allModels] = await Promise.all([api('/tasks'), api('/scans/models')]);
        this.tasks = tasks;
        this.models = allModels.filter(m => m.input_types.includes('video'));
      } catch (_) { /* skip */ }
      if (this.models.length && !this.models.find(m => m.value === this.model)) {
        this.model = this.models[0].value;
      }
      if (pre) this.selectedTask = pre;
      else if (this.tasks.length) this.selectedTask = String(this.tasks[0].id);
    },
    async submit() {
      this.error = '';
      const fileInput = this.$refs.videoFile;
      const file = fileInput && fileInput.files[0];
      if (!file) { this.error = 'Please select a video file.'; return; }

      this.uploading = true; this.progress = 0;
      const fd = new FormData();
      fd.append('task_id', this.selectedTask);
      fd.append('model', this.model);
      fd.append('video', file);

      try {
        const result = await apiUpload('/scans/video', fd, pct => { this.progress = pct; });
        location.href = '/scans/' + result.id;
      } catch (e) { this.error = e.message; this.uploading = false; }
    }
  }));

  /* ── Scan results page ────────────────────────────────────────────────── */
  Alpine.data('scanResultsPage', () => ({
    scanId: null, scan: null, loading: true, error: '', pending: false,
    measurements: [], recommendation: '',
    _pollTimer: null,
    init() {
      const p = new URLSearchParams(location.search);
      this.scanId = p.get('id');
      if (!this.scanId) { location.href = '/tasks'; return; }
      this.loadScan();
    },
    async loadScan() {
      try {
        const s = await api('/scans/' + this.scanId);
        this.scan = s;

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
      return Number(this.scan.result_score ?? this.scan.normalized_score ?? 0).toFixed(1);
    },
    get riskLevel() {
      if (!this.scan) return 'low';
      return (this.scan.risk_level ?? this.scan.risk_category ?? 'low').toLowerCase();
    },
    get modelLabel() {
      if (!this.scan || !this.scan.model) return '';
      return this.scan.model.toUpperCase();
    },
    get barColor() {
      const l = this.riskLevel;
      if (l.includes('very high') || l === 'high') return '#dc3545';
      if (l === 'moderate' || l === 'medium') return '#fd7e14';
      return '#198754';
    },
    get barWidth() { return Math.min(100, parseFloat(this.score) * 10 || 0) + '%'; },
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
      // path: /scans/{id}/observe
      this.scanId = parts[1] || null;
      if (!this.scanId) { location.href = '/tasks'; return; }
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
    scanId: null, current: null, parent: null,
    loading: true, error: '',
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
      try {
        if (this.editingOrg) {
          await api('/admin/organizations/' + this.editingOrg.id, { method: 'PUT', body: JSON.stringify(this.form) });
        } else {
          await api('/admin/organizations', { method: 'POST', body: JSON.stringify(this.form) });
        }
        this._getModal().hide();
        await this.load();
      } catch (e) { this.formError = e.message; }
    },
    async toggleStatus(org) {
      const newStatus = org.status === 'active' ? 'suspended' : 'active';
      try {
        await api('/admin/organizations/' + org.id, { method: 'PUT', body: JSON.stringify({ status: newStatus }) });
        await this.load();
      } catch (e) { this.error = e.message; }
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
    deletingUser: null,
    _editModal: null, _deleteModal: null,
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
      const map = { admin: 'bg-primary', supervisor: 'bg-info text-dark', worker: 'bg-secondary', observer: 'bg-warning text-dark' };
      return map[role] || 'bg-secondary';
    },
    openEdit(u) {
      this.editingUser = u;
      this.editForm = { name: u.name, email: u.email, role: u.role, status: u.status || 'active' };
      this.formError = '';
      this._getEditModal().show();
    },
    async saveUser() {
      this.formError = '';
      try {
        await api('/admin/users/' + this.editingUser.id, { method: 'PUT', body: JSON.stringify(this.editForm) });
        this._getEditModal().hide();
        await this.load();
      } catch (e) { this.formError = e.message; }
    },
    confirmDelete(u) {
      this.deletingUser = u;
      this._getDeleteModal().show();
    },
    async doDelete() {
      try {
        await api('/admin/users/' + this.deletingUser.id, { method: 'DELETE' });
        this._getDeleteModal().hide();
        await this.load();
      } catch (e) { this.error = e.message; }
    },
    _getEditModal() {
      if (!this._editModal) this._editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
      return this._editModal;
    },
    _getDeleteModal() {
      if (!this._deleteModal) this._deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
      return this._deleteModal;
    }
  }));

  /* ── Admin Plans page ─────────────────────────────────────────────────── */
  Alpine.data('adminPlansPage', () => ({
    plans: [], loading: true, error: '',
    search: '',
    get filtered() {
      const q = this.search.toLowerCase().trim();
      if (!q) return this.plans;
      return this.plans.filter(p => (p.name || '').toLowerCase().includes(q));
    },
    editingPlan: null, deletingPlan: null,
    form: { name: '', price: '', scan_limit: '', billing_cycle: 'monthly', status: 'active' },
    formError: '',
    _modal: null, _delModal: null,
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
    },
    confirmDelete(plan) {
      this.deletingPlan = plan;
      this._getDelModal().show();
    },
    async doDelete() {
      try {
        await api('/admin/plans/' + this.deletingPlan.id, { method: 'DELETE' });
        this._getDelModal().hide();
        await this.load();
      } catch (e) { this.error = e.message; }
    },
    _getModal() {
      if (!this._modal) this._modal = new bootstrap.Modal(document.getElementById('planModal'));
      return this._modal;
    },
    _getDelModal() {
      if (!this._delModal) this._delModal = new bootstrap.Modal(document.getElementById('deletePlanModal'));
      return this._delModal;
    }
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
    editingMember: null, newRole: '', removingMember: null,
    _inviteModal: null, _roleModal: null, _removeModal: null,
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
      const map = { admin: 'bg-primary', supervisor: 'bg-info text-dark', worker: 'bg-secondary', observer: 'bg-warning text-dark' };
      return map[role] || 'bg-secondary';
    },
    openInvite() {
      this.inviteForm = { name: '', email: '', password: '', role: 'worker' };
      this.formError = '';
      this._getInviteModal().show();
    },
    async sendInvite() {
      this.formError = '';
      try {
        await api('/org/members', { method: 'POST', body: JSON.stringify(this.inviteForm) });
        this._getInviteModal().hide();
        await this.load();
      } catch (e) { this.formError = e.message; }
    },
    openRoleEdit(m) {
      this.editingMember = m;
      this.newRole = m.role;
      this._getRoleModal().show();
    },
    async saveRole() {
      try {
        await api('/org/members/' + this.editingMember.id, { method: 'PUT', body: JSON.stringify({ role: this.newRole }) });
        this._getRoleModal().hide();
        await this.load();
      } catch (e) { this.error = e.message; }
    },
    confirmRemove(m) {
      this.removingMember = m;
      this._getRemoveModal().show();
    },
    async doRemove() {
      try {
        await api('/org/members/' + this.removingMember.id, { method: 'DELETE' });
        this._getRemoveModal().hide();
        await this.load();
      } catch (e) { this.error = e.message; }
    },
    _getInviteModal() {
      if (!this._inviteModal) this._inviteModal = new bootstrap.Modal(document.getElementById('inviteModal'));
      return this._inviteModal;
    },
    _getRoleModal() {
      if (!this._roleModal) this._roleModal = new bootstrap.Modal(document.getElementById('roleModal'));
      return this._roleModal;
    },
    _getRemoveModal() {
      if (!this._removeModal) this._removeModal = new bootstrap.Modal(document.getElementById('removeModal'));
      return this._removeModal;
    }
  }));

  /* ── Org Settings page ────────────────────────────────────────────────── */
  Alpine.data('orgSettingsPage', () => ({
    org: {}, subscription: {}, loading: true, error: '',
    form: { name: '', slug: '', contact_email: '' },
    saving: false, saveSuccess: '', saveError: '',
    async init() {
      try {
        const [settings, sub] = await Promise.all([
          api('/org/settings'),
          api('/org/subscription').catch(() => ({ data: {} }))
        ]);
        this.org  = settings.data ?? settings;
        this.subscription = sub.data ?? sub;
        this.form = {
          name:          this.org.name || '',
          slug:          this.org.slug || '',
          contact_email: this.org.contact_email || ''
        };
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    async saveSettings() {
      this.saveSuccess = ''; this.saveError = ''; this.saving = true;
      try {
        await api('/org/settings', { method: 'PUT', body: JSON.stringify({ name: this.form.name, contact_email: this.form.contact_email }) });
        this.saveSuccess = 'Settings saved successfully.';
      } catch (e) { this.saveError = e.message; }
      finally { this.saving = false; }
    },
    resetForm() {
      this.form = {
        name:          this.org.name || '',
        slug:          this.org.slug || '',
        contact_email: this.org.contact_email || ''
      };
      this.saveSuccess = ''; this.saveError = '';
    },
    fmtDate(d) { return d ? new Date(d).toLocaleDateString() : '—'; }
  }));

  /* ── Org Billing page ─────────────────────────────────────────────────── */
  Alpine.data('orgBillingPage', () => ({
    sub: {}, plans: [], loading: true, error: '', changing: false,
    changeSuccess: false, changeError: '',
    async init() {
      try {
        const [subRes, plansRes] = await Promise.all([
          api('/org/subscription'),
          api('/billing/plans')
        ]);
        this.sub   = subRes.data  ?? subRes;
        this.plans = plansRes.data ?? plansRes;
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    get usagePercent() {
      const limit = this.sub?.usage?.limit;
      const used  = this.sub?.usage?.used ?? 0;
      if (!limit) return 0;
      return Math.min(100, Math.round(used / limit * 100));
    },
    isCurrent(plan) {
      return String(plan.id) === String(this.sub?.plan?.id);
    },
    async changePlan(planId) {
      this.changing = true; this.changeError = ''; this.changeSuccess = false;
      try {
        await api('/org/subscription', { method: 'PUT', body: JSON.stringify({ plan_id: planId }) });
        this.changeSuccess = true;
        setTimeout(() => location.reload(), 1500);
      } catch (e) { this.changeError = e.message; this.changing = false; }
    }
  }));

});

// End of Alpine.js components
