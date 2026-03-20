/**
 * WorkEddy feature module.
 */

document.addEventListener('alpine:init', () => {
  const BILLING_METRIC_META = [
    { key: 'manual_scans', label: 'Manual scans', help: 'Manual ergonomic scans counted in this billing period.', unit: 'scans' },
    { key: 'video_scans', label: 'Video worker jobs', help: 'Video processing jobs executed by the video worker this billing period.', unit: 'jobs' },
    { key: 'live_sessions', label: 'Live worker sessions', help: 'Live video sessions started during this billing period.', unit: 'sessions' },
    { key: 'live_session_minutes', label: 'Live session minutes', help: 'Total streamed live-video minutes this billing period.', unit: 'minutes' },
    { key: 'llm_requests', label: 'LLM requests', help: 'Copilot narrative requests made this billing period.', unit: 'requests' },
    { key: 'llm_tokens', label: 'LLM tokens', help: 'Total prompt and completion tokens consumed by the copilot.', unit: 'tokens' },
    { key: 'org_members', label: 'Organization members', help: 'Active members currently attached to this organization.', unit: 'members' },
    { key: 'video_retention_days', label: 'Retention period', help: 'Configured video retention window allowed on this plan.', unit: 'days' },
    { key: 'live_concurrent_sessions', label: 'Concurrent live sessions', help: 'Open live sessions that can run at the same time.', unit: 'sessions' },
  ];

  const formatLimitValue = (value, unit = '') => {
    if (value === null || typeof value === 'undefined' || value === '') {
      return 'Unlimited';
    }

    const amount = Number(value || 0).toLocaleString();
    return unit ? `${amount} ${unit}` : amount;
  };

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

  /* User Profile page */

  Alpine.data('userProfilePage', () => ({
    profile: {}, loading: true,
    form: { name: '', email: '' },
    saving: false, saveSuccess: '', saveError: '',

    // 2FA state
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

    fmtDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
  }));

  /* Org Settings page */

  Alpine.data('orgSettingsPage', () => ({
    org: {}, subscription: {}, loading: true, error: '',
    form: {
      name: '', slug: '', contact_email: '',
      industry: '', size: '', website: '', theme_color: '',
      default_model: '', video_retention_days: 30, auto_delete_video: false,
      recommendation_policy: {
        thresholds: {},
        ranking: {},
        feasibility: {},
        interim: {},
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
      const defaults = this.policyDefaults();
      const defaultThresholds = defaults.thresholds || {};
      const defaultRanking = defaults.ranking || {};
      const defaultFeasibility = defaults.feasibility || {};
      const defaultInterim = defaults.interim || {};
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
            trunk_flexion_high: Number(thresholds.trunk_flexion_high ?? defaultThresholds.trunk_flexion_high ?? 45),
            trunk_flexion_moderate: Number(thresholds.trunk_flexion_moderate ?? defaultThresholds.trunk_flexion_moderate ?? 25),
            upper_arm_elevation_high: Number(thresholds.upper_arm_elevation_high ?? defaultThresholds.upper_arm_elevation_high ?? 60),
            repetition_high: Number(thresholds.repetition_high ?? defaultThresholds.repetition_high ?? 20),
            lifting_load: Number(thresholds.lifting_load ?? defaultThresholds.lifting_load ?? 12),
          },
          ranking: {
            cost_penalty_factor: Number(ranking.cost_penalty_factor ?? defaultRanking.cost_penalty_factor ?? 1.1),
            impact_penalty_factor: Number(ranking.impact_penalty_factor ?? defaultRanking.impact_penalty_factor ?? 0.8),
            reduction_factor: Number(ranking.reduction_factor ?? defaultRanking.reduction_factor ?? 1.0),
            strict_hierarchy: ranking.strict_hierarchy ?? defaultRanking.strict_hierarchy ?? true,
          },
          feasibility: {
            minimum_total_score: Number(feasibility.minimum_total_score ?? defaultFeasibility.minimum_total_score ?? 60),
            minimum_policy_compliance: Number(feasibility.minimum_policy_compliance ?? defaultFeasibility.minimum_policy_compliance ?? 55),
          },
          interim: {
            max_days_without_interim: Number(interim.max_days_without_interim ?? defaultInterim.max_days_without_interim ?? 14),
            allow_ppe_interim: interim.allow_ppe_interim ?? defaultInterim.allow_ppe_interim ?? true,
          },
        },
      };
    },

    policyDefaults() {
      return this.org.recommendation_policy_defaults || {};
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
    fmtDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
  }));

  /* Leading Indicators Check-in page */

  Alpine.data('leadingIndicatorsPage', () => ({
    tasks: [],
    summary: {
      total_checkins: 0,
      avg_discomfort: null,
      avg_fatigue: null,
      high_psychosocial_count: 0,
      pre_shift_count: 0,
      post_shift_count: 0,
    },
    hasSummary: false,
    coachingLanguage: 'en',
    coaching: {
      language: 'en',
      language_label: 'English',
      personalized_tips: [],
      pre_shift_self_checks: [],
    },
    loadingCoaching: false,
    mine: { entries: [] },
    loadingMine: false,
    saving: false,
    success: '',
    error: '',
    form: {
      task_id: '',
      shift_date: new Date().toISOString().slice(0, 10),
      checkin_type: 'pre_shift',
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
      await this.loadCoaching();
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
        const data = await api('/leading-indicators/summary?days=30');
        this.summary = {
          ...this.summary,
          ...(data || {}),
        };
        this.hasSummary = true;
      } catch (_) {
        // Keep defaults for non-privileged roles or unavailable summary endpoint.
        this.hasSummary = false;
      }
    },

    async loadCoaching() {
      this.loadingCoaching = true;
      try {
        const lang = encodeURIComponent(this.coachingLanguage || 'en');
        const data = await api(`/worker/coaching?lang=${lang}`);
        this.coaching = {
          ...this.coaching,
          ...(data || {}),
          personalized_tips: Array.isArray(data?.personalized_tips) ? data.personalized_tips : [],
          pre_shift_self_checks: Array.isArray(data?.pre_shift_self_checks) ? data.pre_shift_self_checks : [],
        };
      } catch (e) {
        // Avoid breaking page rendering if coaching endpoint is unavailable.
        this.error = this.error || e.message;
      } finally {
        this.loadingCoaching = false;
      }
    },

    resetForm() {
      this.form = {
        task_id: '',
        shift_date: new Date().toISOString().slice(0, 10),
        checkin_type: 'pre_shift',
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

  /* Copilot page */

  Alpine.data('orgBillingPage', () => ({
    sub: {}, plans: [], invoices: [], loading: true, error: '', changing: false,
    changingPlanId: null,
    changeSuccess: false, changeError: '',
    chargingInvoiceId: null, chargeSuccess: '', chargeError: '',
    paymentToken: '',
    billingMetricMeta: BILLING_METRIC_META,
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
        const metrics = usage.metrics || {};
        const memberMetric = metrics.org_members || {};

        this.sub = {
          ...rawSub,
          plan,
          usage,
          metrics,
          violations: Array.isArray(usage.violations) ? usage.violations : [],
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
          member_limit: rawSub.member_limit ?? plan.member_limit ?? memberMetric.limit ?? null,
          members_used: rawSub.members_used ?? usage.members_used ?? memberMetric.used ?? 0,
          expires_at: rawSub.expires_at || plan.end_date || null,
        };

        this.plans = (plansRes.data ?? plansRes ?? []).map((availablePlan) => ({
          ...availablePlan,
          billing_limits: availablePlan.billing_limits || {},
        }));
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
    usageWatchItems() {
      return this.billingMetricMeta.map((meta) => {
        const metric = this.sub?.metrics?.[meta.key] || {};
        const billed = Number((typeof metric.billed !== 'undefined' ? metric.billed : metric.used) || 0);
        const reserved = Number(metric.reserved || 0);
        const consumption = Number(metric.used || 0);
        const limit = metric.limit ?? null;
        const overLimit = limit !== null && consumption > limit;
        const atLimit = limit !== null && consumption === limit;
        const percent = limit === null
          ? 0
          : limit <= 0
            ? (consumption > 0 ? 100 : 0)
          : Math.min(100, Math.round((consumption / limit) * 100));
        const nearLimit = !overLimit && !atLimit && limit !== null && percent >= 85;

        return {
          key: meta.key,
          label: meta.label,
          help: meta.help,
          used: this.metricValue(meta, billed),
          reserved: this.metricValue(meta, reserved),
          limit: limit === null ? 'Unlimited' : this.metricValue(meta, limit),
          remaining: limit === null ? 'Unlimited' : this.metricValue(meta, Math.max(0, limit - consumption)),
          status: overLimit ? 'Exceeded' : (atLimit ? 'At limit' : (nearLimit ? 'Near limit' : (limit === null ? 'Open' : 'Healthy'))),
          badgeClass: overLimit
            ? 'badge-soft-danger'
            : ((atLimit || nearLimit) ? 'badge-soft-warning' : (limit === null ? 'badge-soft-secondary' : 'badge-soft-success')),
          progressClass: overLimit ? 'bg-danger' : ((atLimit || nearLimit) ? 'bg-warning' : 'bg-primary'),
          percent,
          showProgress: limit !== null,
        };
      });
    },
    violationsSummary() {
      const labels = this.usageWatchItems()
        .filter((item) => item.status === 'Exceeded' || item.status === 'At limit')
        .map((item) => item.label);
      return labels.length > 0 ? labels.join(', ') : '';
    },
    metricValue(meta, value) {
      const amount = Number(value || 0).toLocaleString();
      return meta.unit ? `${amount} ${meta.unit}` : amount;
    },
    planHighlights(plan) {
      const limits = plan.billing_limits || {};
      return [
        `Video worker: ${formatLimitValue(limits.video_scan_limit, 'jobs')}`,
        `Live worker: ${formatLimitValue(limits.live_session_limit, 'sessions')}`,
        `Live minutes: ${formatLimitValue(limits.live_session_minutes_limit, 'minutes')}`,
        `LLM requests: ${formatLimitValue(limits.llm_request_limit, 'requests')}`,
        `Retention: ${formatLimitValue(limits.max_video_retention_days, 'days')}`,
        `Members: ${formatLimitValue(limits.max_org_members)}`,
      ];
    },
    fmtDate(d) {
      return d ? new Date(d).toLocaleDateString() : '-';
    },
    fmtDateTime(d) {
      return d ? new Date(d).toLocaleString() : '-';
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
