/**
 * WorkEddy feature module.
 */

document.addEventListener('alpine:init', () => {

  Alpine.data('copilotPage', () => ({
    loading: false,
    error: '',
    response: null,
    controlsPage: 1,
    draftPlanPage: 1,
    pageSize: 5,
    form: {
      persona: 'supervisor',
      window_days: 7,
      scan_id: '',
      baseline_scan_id: '',
    },

    async run() {
      this.error = '';
      this.loading = true;
      try {
        const persona = String(this.form.persona || 'supervisor').replace(/_/g, '-');
        const payload = {};

        if (this.form.window_days !== '' && this.form.window_days !== null) {
          payload.window_days = Number(this.form.window_days);
        }
        if (this.form.scan_id !== '' && this.form.scan_id !== null) {
          payload.scan_id = Number(this.form.scan_id);
        }
        if (this.form.baseline_scan_id !== '' && this.form.baseline_scan_id !== null) {
          payload.baseline_scan_id = Number(this.form.baseline_scan_id);
        }

        if (this.form.persona === 'auditor' && !payload.scan_id) {
          throw new Error('Scan ID is required for auditor persona.');
        }

        this.response = await api('/copilot/' + persona, {
          method: 'POST',
          body: JSON.stringify(payload),
        });

        this.controlsPage = 1;
        this.draftPlanPage = 1;

        if (window.innerWidth < 1200) {
          this.closeConfigDrawer();
        }
      } catch (e) {
        this.error = e.message || 'Unable to run copilot request.';
      } finally {
        this.loading = false;
      }
    },

    llmStatusClass(status) {
      const value = String(status || '').toLowerCase();
      if (value === 'success') return 'badge-soft-success';
      if (value === 'fallback') return 'badge-soft-warning';
      if (value === 'disabled') return 'badge-soft-secondary';
      return 'badge-soft-secondary';
    },

    citationKey(citation, index) {
      if (!citation || typeof citation !== 'object') return String(index);
      return [
        citation.source_type || 'source',
        citation.source_id || 'id',
        citation.metric || 'metric',
        String(index),
      ].join(':');
    },

    confidencePct(value) {
      const num = Number(value);
      if (!Number.isFinite(num)) return '0%';
      return Math.round(Math.max(0, Math.min(1, num)) * 100) + '%';
    },

    pretty(v) {
      return JSON.stringify(v ?? {}, null, 2);
    },

    closeConfigDrawer() {
      const drawerEl = document.getElementById('copilotConfigDrawer');
      if (!drawerEl || typeof bootstrap === 'undefined' || !bootstrap.Offcanvas) return;

      const instance = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
      instance.hide();
    },

    kpiTotalCitations() {
      return Array.isArray(this.response?.citations) ? this.response.citations.length : 0;
    },

    kpiHighPriorityActions() {
      const steps = Array.isArray(this.response?.result?.recommended_next_steps)
        ? this.response.result.recommended_next_steps
        : [];
      return steps.filter((s) => {
        const p = String(s?.priority || '').toLowerCase();
        return p === 'high' || p === 'critical' || p === 'urgent';
      }).length;
    },

    kpiAvgConfidencePct() {
      const cites = Array.isArray(this.response?.citations) ? this.response.citations : [];
      if (cites.length === 0) return '0%';
      const vals = cites
        .map((c) => Number(c?.confidence))
        .filter((n) => Number.isFinite(n));
      if (vals.length === 0) return '0%';
      const avg = vals.reduce((a, b) => a + b, 0) / vals.length;
      return Math.round(Math.max(0, Math.min(1, avg)) * 100) + '%';
    },

    insightBriefs() {
      const steps = Array.isArray(this.response?.result?.recommended_next_steps)
        ? this.response.result.recommended_next_steps
        : [];

      if (steps.length > 0) {
        return steps.slice(0, 4).map((s, idx) => {
          const priority = String(s?.priority || 'medium').toLowerCase();
          const icon = priority === 'high' || priority === 'critical'
            ? 'bi-exclamation-octagon-fill'
            : priority === 'medium'
              ? 'bi-exclamation-triangle-fill'
              : 'bi-check-circle-fill';
          return {
            label: `Action ${idx + 1}`,
            title: String(s?.action || 'Recommended Action'),
            detail: String(s?.action || 'No details available.'),
            icon,
            priority,
          };
        });
      }

      return [
        {
          label: 'Mon-Tue',
          title: 'Repetitive Strain',
          detail: 'High volume of repetitive lifting detected during early sorting phases.',
          icon: 'bi-exclamation-octagon-fill',
          priority: 'high',
        },
        {
          label: 'Wed',
          title: 'Static Posture',
          detail: 'Extended periods of static torso rotation identified in monitored tasks.',
          icon: 'bi-exclamation-triangle-fill',
          priority: 'medium',
        },
        {
          label: 'Thu-Fri',
          title: 'Optimal Range',
          detail: 'Recent checks show strong adherence to posture and setup recommendations.',
          icon: 'bi-check-circle-fill',
          priority: 'low',
        },
        {
          label: 'Weekend',
          title: 'Baseline Drift',
          detail: 'Minor variance from baseline noted but currently within tolerance levels.',
          icon: 'bi-info-circle-fill',
          priority: 'info',
        },
      ];
    },

    recentInsights() {
      const cites = Array.isArray(this.response?.citations) ? this.response.citations : [];
      if (cites.length > 0) {
        return cites.slice(0, 3).map((c) => ({
          title: `${c?.source_type || 'Source'} • ${c?.metric || 'Metric'}`,
          subtitle: `${c?.source_id || 'N/A'} • ${c?.time_window || 'Window'}`,
        }));
      }

      return [
        { title: 'Morning Shift A', subtitle: '2 hours ago' },
        { title: 'Loading Dock Beta', subtitle: 'Yesterday' },
        { title: 'Warehouse Zone 4', subtitle: '2 days ago' },
      ];
    },

    structuredControls() {
      return Array.isArray(this.response?.result?.recommended_next_steps)
        ? this.response.result.recommended_next_steps
        : [];
    },

    pagedStructuredControls() {
      const items = this.structuredControls();
      const start = (this.controlsPage - 1) * this.pageSize;
      return items.slice(start, start + this.pageSize);
    },

    structuredControlsTotalPages() {
      const total = this.structuredControls().length;
      return Math.max(1, Math.ceil(total / this.pageSize));
    },

    draftPlanItems() {
      return Array.isArray(this.response?.result?.draft_plan)
        ? this.response.result.draft_plan
        : [];
    },

    pagedDraftPlanItems() {
      const items = this.draftPlanItems();
      const start = (this.draftPlanPage - 1) * this.pageSize;
      return items.slice(start, start + this.pageSize);
    },

    draftPlanTotalPages() {
      const total = this.draftPlanItems().length;
      return Math.max(1, Math.ceil(total / this.pageSize));
    },

    prevControlsPage() {
      this.controlsPage = Math.max(1, this.controlsPage - 1);
    },

    nextControlsPage() {
      this.controlsPage = Math.min(this.structuredControlsTotalPages(), this.controlsPage + 1);
    },

    prevDraftPlanPage() {
      this.draftPlanPage = Math.max(1, this.draftPlanPage - 1);
    },

    nextDraftPlanPage() {
      this.draftPlanPage = Math.min(this.draftPlanTotalPages(), this.draftPlanPage + 1);
    },
  }));

  /* Admin System Settings page */

});
