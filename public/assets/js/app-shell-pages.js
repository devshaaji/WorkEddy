/**
 * WorkEddy feature module.
 */

document.addEventListener('alpine:init', () => {

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
          await this._fullLoad();         // first open - full fetch
        } else {
          await this._refreshNew();       // subsequent - only new items
        }
      }
    },
    /* Full load - runs once on first open */
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
    /* Incremental refresh - prepend new items & update count */
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

  /* Org Billing page */

});
