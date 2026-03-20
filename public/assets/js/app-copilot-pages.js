/**
 * WorkEddy feature module.
 */

document.addEventListener('alpine:init', () => {

  Alpine.data('copilotPage', () => ({
    loading: false,
    error: '',
    response: null,
    scopeLoading: false,
    scopeLoaded: false,
    scopeError: '',
    scanOptions: [],
    taskLookup: {},
    controlsPage: 1,
    draftPlanPage: 1,
    pageSize: 5,
    form: {
      persona: 'supervisor',
      window_days: 7,
      scan_id: '',
      baseline_scan_id: '',
      target_scan_query: '',
      baseline_scan_query: '',
    },

    init() {
      this.loadScopeData();
    },

    async loadScopeData(force = false) {
      if (this.scopeLoading || (this.scopeLoaded && !force)) return;

      this.scopeLoading = true;
      this.scopeError = '';
      try {
        const [tasks, scans] = await Promise.all([
          api('/tasks'),
          api('/scans?status=completed&limit=100'),
        ]);

        const taskRows = Array.isArray(tasks) ? tasks : [];
        this.taskLookup = Object.fromEntries(
          taskRows.map((task) => [String(task?.id || ''), String(task?.name || `Task #${task?.id || ''}`)])
        );

        const scanRows = Array.isArray(scans) ? scans : [];
        this.scanOptions = scanRows
          .map((scan) => ({
            ...scan,
            id: Number(scan?.id || 0),
            task_id: Number(scan?.task_id || 0),
            parent_scan_id: scan?.parent_scan_id ? Number(scan.parent_scan_id) : null,
          }))
          .filter((scan) => scan.id > 0);

        this.scopeLoaded = true;
      } catch (e) {
        this.scopeError = e?.message || 'Unable to load completed scans.';
        this.scanOptions = [];
        this.taskLookup = {};
      } finally {
        this.scopeLoading = false;
      }
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
          throw new Error('Target scan is required for auditor persona.');
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

    onPersonaChange() {
      if (this.form.persona !== 'auditor') {
        this.form.baseline_scan_id = '';
        this.form.baseline_scan_query = '';
      }
    },

    onTargetScanChange() {
      const targetId = Number(this.form.scan_id || 0);
      const selected = this.selectedTargetScan();

      if (targetId > 0 && Number(this.form.baseline_scan_id || 0) === targetId) {
        this.form.baseline_scan_id = '';
      }

      if (
        this.form.persona === 'auditor'
        && selected?.parent_scan_id
        && !Number(this.form.baseline_scan_id || 0)
      ) {
        this.form.baseline_scan_id = Number(selected.parent_scan_id);
      }
    },

    normalizeSearchValue(value) {
      return String(value || '').trim().toLowerCase();
    },

    scanMatchesQuery(scan, query) {
      const normalized = this.normalizeSearchValue(query);
      if (!normalized) return true;

      const haystack = [
        scan?.id,
        this.taskName(scan?.task_id),
        scan?.risk_category,
        scan?.risk_level,
        scan?.status,
        scan?.scan_type,
        scan?.model,
        scan?.created_at,
      ]
        .map((value) => this.normalizeSearchValue(value))
        .join(' ');

      return haystack.includes(normalized);
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

    humanizeKey(value) {
      return String(value || '')
        .replace(/[_\-]+/g, ' ')
        .replace(/\b\w/g, (match) => match.toUpperCase())
        .trim();
    },

    personaLabel() {
      return this.humanizeKey(this.response?.persona || this.form.persona || 'supervisor');
    },

    showsTargetScanSelector() {
      return this.form.persona === 'engineer' || this.form.persona === 'auditor';
    },

    showsBaselineScanSelector() {
      return this.form.persona === 'auditor';
    },

    targetScanRequired() {
      return this.form.persona === 'auditor';
    },

    taskName(taskId) {
      const key = String(taskId || '');
      return this.taskLookup[key] || (taskId ? `Task #${taskId}` : 'Unassigned Task');
    },

    formatDateShort(value) {
      if (!value) return 'Unknown date';
      const parsed = new Date(value);
      if (Number.isNaN(parsed.getTime())) return String(value);
      return parsed.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    },

    scanDisplayLabel(scan) {
      if (!scan || typeof scan !== 'object') return 'Unknown scan';
      const task = this.taskName(scan.task_id);
      const risk = this.humanizeKey(scan.risk_category || scan.risk_level || scan.status || 'completed');
      const type = this.humanizeKey(scan.scan_type || 'scan');
      return `#${scan.id} - ${task} - ${risk} - ${type} - ${this.formatDateShort(scan.created_at)}`;
    },

    targetScanOptions() {
      return this.scanOptions.filter((scan) => this.scanMatchesQuery(scan, this.form.target_scan_query));
    },

    baselineScanOptions() {
      const targetId = Number(this.form.scan_id || 0);
      return this.scanOptions.filter((scan) => scan.id !== targetId && this.scanMatchesQuery(scan, this.form.baseline_scan_query));
    },

    selectedTargetScan() {
      const targetId = Number(this.form.scan_id || 0);
      return this.scanOptions.find((scan) => scan.id === targetId) || null;
    },

    selectedBaselineScan() {
      const baselineId = Number(this.form.baseline_scan_id || 0);
      return this.scanOptions.find((scan) => scan.id === baselineId) || null;
    },

    selectedTargetScanMeta() {
      const scan = this.selectedTargetScan();
      if (!scan) return '';
      return `${this.taskName(scan.task_id)} - ${this.humanizeKey(scan.risk_category || scan.status || 'completed')} - ${this.humanizeKey(scan.scan_type || 'scan')}`;
    },

    selectedBaselineScanMeta() {
      const scan = this.selectedBaselineScan();
      if (!scan) return '';
      return `${this.taskName(scan.task_id)} - ${this.humanizeKey(scan.risk_category || scan.status || 'completed')} - ${this.humanizeKey(scan.scan_type || 'scan')}`;
    },

    resultTitle() {
      return String(this.response?.result?.title || 'Scoped Copilot Response');
    },

    summaryText() {
      const executiveSummary = String(this.response?.narrative?.executive_summary || '').trim();
      if (executiveSummary) {
        return executiveSummary;
      }

      const summary = this.response?.result?.summary;
      if (typeof summary === 'string' && summary.trim() !== '') {
        return summary.trim();
      }

      if (summary && typeof summary === 'object' && !Array.isArray(summary)) {
        const preferredOrder = ['total_scans', 'high_risk_scans', 'moderate_risk_scans', 'open_control_actions'];
        const entries = Object.entries(summary)
          .filter(([, value]) => ['string', 'number', 'boolean'].includes(typeof value))
          .sort(([a], [b]) => {
            const aIndex = preferredOrder.indexOf(a);
            const bIndex = preferredOrder.indexOf(b);
            return (aIndex === -1 ? 999 : aIndex) - (bIndex === -1 ? 999 : bIndex);
          });

        if (entries.length > 0) {
          return entries
            .map(([key, value]) => `${this.humanizeKey(key)}: ${value}`)
            .join(' | ');
        }
      }

      return 'No narrative summary available.';
    },

    coreInsightText() {
      return String(
        this.response?.narrative?.why_this_matters
        || this.response?.narrative?.executive_summary
        || this.summaryText()
      );
    },

    actionGuidanceText() {
      const narrativeText = String(this.response?.narrative?.recommended_actions_text || '').trim();
      if (narrativeText) {
        return narrativeText;
      }

      const items = this.recommendationItems();
      if (items.length > 0) {
        return items.map((item, idx) => `${idx + 1}. ${item.title}`).join(' ');
      }

      return 'No action guidance returned by model.';
    },

    normalizeRecommendation(item, index) {
      const priority = String(
        item?.priority
        || (String(item?.hierarchy_level || '').toLowerCase() === 'elimination' ? 'high' : 'medium')
        || 'medium'
      ).toLowerCase();
      const title = String(item?.action || item?.control_title || item?.option || `Recommendation ${index + 1}`);
      const reduction = Number(item?.expected_risk_reduction_pct);
      const deployDays = Number(item?.time_to_deploy_days);
      const detailParts = [];

      if (item?.control_code) detailParts.push(String(item.control_code));
      if (item?.hierarchy_level) detailParts.push(this.humanizeKey(item.hierarchy_level));
      if (Number.isFinite(reduction) && reduction > 0) detailParts.push(`Expected reduction ${reduction.toFixed(1)}%`);
      if (Number.isFinite(deployDays) && deployDays > 0) detailParts.push(`Deploy in ${deployDays}d`);
      if (item?.throughput_impact) detailParts.push(`Throughput ${String(item.throughput_impact)}`);
      if (item?.source_scan_id) detailParts.push(`Scan ${item.source_scan_id}`);

      return {
        title,
        action: title,
        detail: detailParts.join(' | ') || title,
        priority,
      };
    },

    recommendationItems() {
      const topLevel = Array.isArray(this.response?.recommendations) ? this.response.recommendations : [];
      const fallback = Array.isArray(this.response?.result?.recommended_next_steps)
        ? this.response.result.recommended_next_steps
        : Array.isArray(this.response?.result?.draft_plan)
          ? this.response.result.draft_plan
          : Array.isArray(this.response?.result?.options)
            ? this.response.result.options
            : [];
      const items = topLevel.length > 0 ? topLevel : fallback;
      return items.map((item, index) => this.normalizeRecommendation(item, index));
    },

    hasRecommendations() {
      return this.recommendationItems().length > 0;
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
      return this.recommendationItems().filter((s) => {
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
      const steps = this.recommendationItems();

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
            title: String(s?.title || s?.action || 'Recommended Action'),
            detail: String(s?.detail || s?.title || s?.action || 'No details available.'),
            icon,
            priority,
          };
        });
      }

      return [];
    },

    recentInsights() {
      const cites = Array.isArray(this.response?.citations) ? this.response.citations : [];
      if (cites.length > 0) {
        return cites.slice(0, 3).map((c) => ({
          title: `${this.humanizeKey(c?.source_type || 'source')} - ${this.humanizeKey(c?.metric || 'metric')}`,
          subtitle: `${c?.source_id || 'N/A'} - ${c?.time_window || 'Window'}`,
        }));
      }

      return this.scanOptions.slice(0, 3).map((scan) => ({
        title: `Scan #${scan.id} - ${this.taskName(scan.task_id)}`,
        subtitle: `${this.humanizeKey(scan.risk_category || scan.status || 'completed')} - ${this.humanizeKey(scan.scan_type || 'scan')} - ${this.formatDateShort(scan.created_at)}`,
      }));
    },

    recentInsightsHeading() {
      return this.response ? 'Recent Evidence' : 'Recent Completed Scans';
    },

    recentInsightsEmptyText() {
      if (this.scopeLoading) return 'Loading completed scans...';
      if (this.scopeError) return this.scopeError;
      if (this.response) return 'No structured citations returned for this response.';
      return 'No completed scans available yet.';
    },

    structuredControls() {
      return this.recommendationItems();
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
