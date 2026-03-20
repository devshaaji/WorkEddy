/**
 * WorkEddy feature module.
 */

document.addEventListener('alpine:init', () => {
  const PLAN_BILLING_LIMIT_FIELDS = [
    { key: 'video_scan_limit', label: 'Video worker jobs', help: 'Video scan jobs allowed per billing period.' },
    { key: 'live_session_limit', label: 'Live sessions', help: 'Live video sessions allowed per billing period.' },
    { key: 'live_session_minutes_limit', label: 'Live session minutes', help: 'Total live-video minutes allowed per billing period.' },
    { key: 'llm_request_limit', label: 'LLM requests', help: 'Copilot narrative requests allowed per billing period.' },
    { key: 'llm_token_limit', label: 'LLM tokens', help: 'Total LLM tokens allowed per billing period.' },
    { key: 'max_video_retention_days', label: 'Retention days', help: 'Maximum video retention period this plan can set.' },
    { key: 'max_org_members', label: 'Organization members', help: 'Maximum active organization members.' },
    { key: 'max_live_concurrent_sessions', label: 'Concurrent live sessions', help: 'Maximum open live sessions at one time.' },
  ];

  const createEmptyPlanBillingLimits = () => PLAN_BILLING_LIMIT_FIELDS.reduce((limits, field) => {
    limits[field.key] = '';
    return limits;
  }, {});

  const createEmptyPlanForm = () => ({
    name: '',
    price: '',
    scan_limit: '',
    billing_cycle: 'monthly',
    status: 'active',
    billing_limits: createEmptyPlanBillingLimits(),
  });

  const normalizeNullableInt = (value) => {
    if (value === '' || value === null || typeof value === 'undefined') {
      return null;
    }

    const parsed = parseInt(value, 10);
    return Number.isNaN(parsed) ? null : parsed;
  };

  const formatPlanLimitValue = (value, suffix = '') => {
    if (value === null || typeof value === 'undefined' || value === '') {
      return 'Unlimited';
    }

    const number = Number(value || 0);
    return suffix ? `${number.toLocaleString()} ${suffix}` : number.toLocaleString();
  };

  Alpine.data('adminDashboardPage', () => ({
    stats: {}, loading: true, error: '',
    async init() {
      try {
        const d = await api('/admin/stats');
        this.stats = d.data ?? d;
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
    fmtDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; },
    fmtCurrency(v) { return Number(v || 0).toFixed(2); }
  }));

  /* Admin Organizations page  */

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
    fmtDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; },
    _getModal() {
      if (!this._modal) this._modal = new bootstrap.Modal(document.getElementById('orgModal'));
      return this._modal;
    }
  }));

  /* Admin Users page */

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

  /* Admin Plans page */

  Alpine.data('adminPlansPage', () => ({
    plans: [], loading: true, error: '',
    search: '',
    saving: false,
    deleting: false,
    billingLimitFields: PLAN_BILLING_LIMIT_FIELDS,
    get filtered() {
      const q = this.search.toLowerCase().trim();
      if (!q) return this.plans;
      return this.plans.filter(p => (p.name || '').toLowerCase().includes(q));
    },
    editingPlan: null,
    deletingPlanId: null,
    form: createEmptyPlanForm(),
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
      this.form = createEmptyPlanForm();
      this.formError = '';
      this._getModal().show();
    },
    openEdit(plan) {
      const limits = plan.billing_limits || {};
      this.editingPlan = plan;
      this.form = {
        name: plan.name,
        price: plan.price,
        scan_limit: plan.scan_limit ?? '',
        billing_cycle: plan.billing_cycle || 'monthly',
        status: plan.status || 'active',
        billing_limits: PLAN_BILLING_LIMIT_FIELDS.reduce((memo, field) => {
          memo[field.key] = limits[field.key] ?? '';
          return memo;
        }, createEmptyPlanBillingLimits()),
      };
      this.formError = '';
      this._getModal().show();
    },
    async savePlan() {
      this.formError = '';
      this.saving = true;
      const payload = { ...this.form, billing_limits: { ...this.form.billing_limits } };
      payload.scan_limit = normalizeNullableInt(payload.scan_limit);
      payload.billing_limits = PLAN_BILLING_LIMIT_FIELDS.reduce((memo, field) => {
        memo[field.key] = normalizeNullableInt(payload.billing_limits[field.key]);
        return memo;
      }, {});
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
    planHighlights(plan) {
      const limits = plan.billing_limits || {};
      return [
        `Video worker: ${formatPlanLimitValue(limits.video_scan_limit, 'jobs')}`,
        `Live worker: ${formatPlanLimitValue(limits.live_session_limit, 'sessions')}`,
        `LLM usage: ${formatPlanLimitValue(limits.llm_request_limit, 'requests')}`,
        `Retention: ${formatPlanLimitValue(limits.max_video_retention_days, 'days')}`,
        `Org members: ${formatPlanLimitValue(limits.max_org_members)}`,
      ];
    },
    limitPlaceholder(field) {
      return field.key === 'max_video_retention_days'
        ? 'Leave blank for default plan allowance'
        : 'Leave blank for unlimited';
    },
    _getModal() {
      if (!this._modal) this._modal = new bootstrap.Modal(document.getElementById('planModal'));
      return this._modal;
    },
  }));

  /* 
   * ORGANIZATION PAGES
   * */

  /* Organization Users page */

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

  /* Notifications dropdown */

});
