/**
 * WorkEddy - Alpine.js API client & page components.
 * Uses Fetch API under the hood. Zero inline JavaScript.
 */

// API client (private helpers)

const API_BASE = '/api/v1';
const MAX_VIDEO_BYTES = 200 * 1024 * 1024; // 200 MB - must match server

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
  } catch (_) { /* noop expired tokens handled normally by 401 */ }
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

/* global logout - used by layout nav */
function logout() { _clear(); location.href = '/login'; }

/* System dialog helpers (replaces browser alert/confirm usage) */

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

//  Alpine.js - Auth guard (runs on main layout)

document.addEventListener('alpine:init', () => {

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

  // Initialize auth store immediately to enforce auth check on protected pages

});
