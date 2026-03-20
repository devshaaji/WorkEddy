<?php
/**
 * Advanced Scan Comparison View
 *
 * Route: GET /scans/compare
 * Alpine component: scanAdvancedComparePage
 *
 * Features:
 *   - ScanSelector     – free-form A/B scan selection
 *   - ScoreDeltaCard   – normalised score + delta
 *   - SkeletonViewer   – SVG skeleton coloured by risk heatmap
 *   - JointHeatmap     – per-angle risk bars
 *   - ComparisonTree   – node-level metric breakdown
 *   - Timeline         – Chart.js history trend
 */
$pageTitle  = 'Scan Comparison';
$activePage = 'scans-compare';
ob_start();
?>
<div x-data="scanAdvancedComparePage">

  <?php
  $headerTitle = 'Scan Comparison';
  $headerBreadcrumbHtml = '<ol class="breadcrumb mb-0 text-sm"><li class="breadcrumb-item"><a href="/tasks" class="text-decoration-none text-muted">Tasks</a></li><li class="breadcrumb-item active">Compare</li></ol>';
  $headerActionsHtml = '<a href="/tasks" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Tasks</a>';
  require __DIR__ . '/../partials/page-header.php';
  ?>

  <!-- ── ScanSelector ─────────────────────────────────────────────────── -->
  <div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
      <i class="bi bi-sliders text-primary"></i>
      <h6 class="mb-0 fw-semibold">Select Scans to Compare</h6>
    </div>
    <div class="card-body">
      <!-- Loading state -->
      <div class="text-center py-3 text-muted" x-show="loadingScans" x-cloak>
        <div class="spinner-border spinner-border-sm me-2"></div>Loading your scans…
      </div>

      <div x-show="!loadingScans" x-cloak>
        <div class="row g-3 align-items-end">

          <!-- Scan A -->
          <div class="col-12 col-md-5">
            <label class="form-label">
              Scan A
              <span class="badge badge-soft-secondary ms-1" style="font-size:.65rem">Baseline</span>
            </label>
            <select class="form-select" x-model="scanAId">
              <option value="">— Select a scan —</option>
              <template x-for="s in scans" :key="'a-' + s.id">
                <option :value="String(s.id)" x-text="scanLabel(s)"></option>
              </template>
            </select>
          </div>

          <!-- VS divider -->
          <div class="col-12 col-md-2 text-center d-flex align-items-end justify-content-center pb-1">
            <div class="compare-vs-badge"><i class="bi bi-arrow-left-right me-1"></i>VS</div>
          </div>

          <!-- Scan B -->
          <div class="col-12 col-md-5">
            <label class="form-label">
              Scan B
              <span class="badge badge-soft-primary ms-1" style="font-size:.65rem">Target</span>
            </label>
            <select class="form-select" x-model="scanBId">
              <option value="">— Select a scan —</option>
              <template x-for="s in scans" :key="'b-' + s.id">
                <option :value="String(s.id)" x-text="scanLabel(s)"></option>
              </template>
            </select>
          </div>

          <!-- Run button -->
          <div class="col-12 d-flex align-items-center gap-3">
            <button class="btn btn-primary"
                    :disabled="!canCompare || comparing"
                    @click="runComparison()">
              <template x-if="!comparing">
                <span><i class="bi bi-play-circle me-1"></i>Run Comparison</span>
              </template>
              <template x-if="comparing">
                <span><span class="spinner-border spinner-border-sm me-1"></span>Comparing…</span>
              </template>
            </button>
            <span class="text-sm text-muted" x-show="!canCompare && !comparing && (scanAId && scanBId) && sameModel" x-cloak>
              Select two different scans.
            </span>
            <span class="text-sm text-danger" x-show="!sameModel && scanAId && scanBId" x-cloak>
              Selected scans use different models. Pick two scans with the same assessment model.
            </span>
          </div>
        </div><!-- /row -->

        <!-- Error / scans warning -->
        <div class="alert alert-danger mt-3 mb-0 compare-alert" x-show="error" x-cloak>
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
          <span x-text="error"></span>
        </div>
        <div class="alert alert-warning mt-3 mb-0 compare-alert" x-show="scansError && !scans.length" x-cloak>
          <i class="bi bi-exclamation-circle flex-shrink-0 mt-1"></i>
          <span x-text="scansError"></span>
        </div>
        <div class="alert alert-info mt-3 mb-0 compare-alert" x-show="!scansError && scans.length === 0" x-cloak>
          <i class="bi bi-info-circle flex-shrink-0 mt-1"></i>
          <span>No completed scans found. Run a scan first.</span>
        </div>
      </div>
    </div>
  </div><!-- /ScanSelector -->

  <!-- ── Comparison Results (shown once data arrives) ────────────────── -->
  <template x-if="comparison">
  <div>

    <!-- Direction banner -->
    <div class="alert d-flex align-items-center gap-2 mb-4"
         :class="directionBadge().cls">
      <i class="bi flex-shrink-0 fs-5" :class="directionBadge().icon"></i>
      <div>
        <strong x-text="directionBadge().text"></strong>
        <span class="ms-2 text-sm" x-show="scoreDeltaVal !== null">
          — normalised score changed by
          <strong x-text="(scoreDeltaVal > 0 ? '+' : '') + fmtScore(scoreDeltaVal)"></strong> pts
        </span>
      </div>
      <span class="ms-auto badge badge-soft-secondary text-uppercase"
            x-text="(comparison?.model || '').toUpperCase()"></span>
    </div>

    <!-- ── ScoreDeltaCard ─────────────────────────────────────────────── -->
    <div class="compare-score-grid mb-4">

      <!-- Scan A -->
      <div class="score-card score-card-a">
        <div class="score-card-label">
          <span class="compare-dot dot-a"></span> Scan A · Baseline
        </div>
        <div class="score-card-value" :style="'color:' + riskColor(scoreA)"
             x-text="fmtScore(scoreA)"></div>
        <span class="badge mt-2 px-3 py-1"
              :class="riskBadgeClass(scoreA)"
              x-text="riskLabel(scoreA)"></span>
        <div class="score-card-meta text-muted text-xs mt-2">
          <span x-text="'Scan #' + comparison.summary.scan_a.id"></span> ·
          <span class="text-uppercase" x-text="comparison.summary.scan_a.model"></span> ·
          <span x-text="fmtDate(comparison.summary.scan_a.created_at)"></span>
        </div>
      </div>

      <!-- Delta -->
      <div class="score-card-delta">
        <div class="delta-icon" :class="deltaClass(scoreDeltaVal)">
          <i class="bi" :class="deltaIcon(scoreDeltaVal)"></i>
        </div>
        <div class="delta-value" :class="deltaClass(scoreDeltaVal)"
             x-text="scoreDeltaVal !== null ? (scoreDeltaVal > 0 ? '+' : '') + fmtScore(scoreDeltaVal) : '—'">
        </div>
        <div class="text-xs text-muted mt-1">Risk Delta</div>
      </div>

      <!-- Scan B -->
      <div class="score-card score-card-b">
        <div class="score-card-label">
          <span class="compare-dot dot-b"></span> Scan B · Target
        </div>
        <div class="score-card-value" :style="'color:' + riskColor(scoreB)"
             x-text="fmtScore(scoreB)"></div>
        <span class="badge mt-2 px-3 py-1"
              :class="riskBadgeClass(scoreB)"
              x-text="riskLabel(scoreB)"></span>
        <div class="score-card-meta text-muted text-xs mt-2">
          <span x-text="'Scan #' + comparison.summary.scan_b.id"></span> ·
          <span class="text-uppercase" x-text="comparison.summary.scan_b.model"></span> ·
          <span x-text="fmtDate(comparison.summary.scan_b.created_at)"></span>
        </div>
      </div>
    </div><!-- /ScoreDeltaCard -->

    <!-- Improvement Proof (from comparison.improvement_proof) -->
    <div class="row g-3 mb-4" x-show="comparison?.improvement_proof" x-cloak>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <p class="text-muted text-sm mb-1">Risk Reduction</p>
          <div class="h5 mb-0" x-text="(comparison?.improvement_proof?.risk_reduction_percent ?? 0) + '%' "></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <p class="text-muted text-sm mb-1">Score Points Improved</p>
          <div class="h5 mb-0" x-text="comparison?.improvement_proof?.risk_reduction_points ?? 0"></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <p class="text-muted text-sm mb-1">Time Savings / Shift</p>
          <div class="h5 mb-0" x-text="(comparison?.improvement_proof?.estimated_time_savings_minutes_per_shift ?? 0) + ' min'"></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
          <p class="text-muted text-sm mb-1">Avoided Injury Cost (Est.)</p>
          <div class="h5 mb-0" x-text="'$' + Number(comparison?.improvement_proof?.estimated_avoided_injury_cost_usd_annual ?? 0).toLocaleString()"></div>
        </div></div>
      </div>
    </div>

    <!-- ── SkeletonViewer + JointHeatmap ─────────────────────────────── -->
    <div class="row g-4 mb-4">

      <!-- SkeletonViewer -->
      <div class="col-12 col-lg-7">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-person-bounding-box text-primary"></i>
              <h6 class="mb-0 fw-semibold">Skeleton Comparison</h6>
            </div>
            <!-- Risk legend -->
            <div class="d-flex gap-3 text-xs align-items-center">
              <span class="d-flex align-items-center gap-1">
                <span class="risk-legend-dot" style="background:#22c55e"></span>Low
              </span>
              <span class="d-flex align-items-center gap-1">
                <span class="risk-legend-dot" style="background:#f59e0b"></span>Moderate
              </span>
              <span class="d-flex align-items-center gap-1">
                <span class="risk-legend-dot" style="background:#f97316"></span>High
              </span>
              <span class="d-flex align-items-center gap-1">
                <span class="risk-legend-dot" style="background:#ef4444"></span>Critical
              </span>
            </div>
          </div>
          <div class="card-body">
            <div class="skeleton-pair-wrap">

              <!-- Scan A skeleton -->
              <div class="skeleton-wrap">
                <div class="skeleton-label">
                  <span class="compare-dot dot-a"></span> Scan A
                </div>
                <div class="skeleton-svg-container">
                  <svg viewBox="0 0 200 320" class="skeleton-svg" xmlns="http://www.w3.org/2000/svg">
                    <!-- Bones -->
                    <g stroke="#e2e8f0" stroke-width="3.5" stroke-linecap="round" fill="none">
                      <line x1="100" y1="41" x2="100" y2="58"/>
                      <line x1="100" y1="58" x2="62"  y2="80"/>
                      <line x1="100" y1="58" x2="138" y2="80"/>
                      <line x1="62"  y1="80" x2="45"  y2="130"/>
                      <line x1="138" y1="80" x2="155" y2="130"/>
                      <line x1="45"  y1="130" x2="35" y2="170"/>
                      <line x1="155" y1="130" x2="165" y2="170"/>
                      <line x1="100" y1="58"  x2="100" y2="175"/>
                      <line x1="100" y1="175" x2="78"  y2="190"/>
                      <line x1="100" y1="175" x2="122" y2="190"/>
                      <line x1="78"  y1="190" x2="70"  y2="255"/>
                      <line x1="122" y1="190" x2="130" y2="255"/>
                      <line x1="70"  y1="255" x2="65"  y2="305"/>
                      <line x1="130" y1="255" x2="135" y2="305"/>
                    </g>
                    <!-- Head -->
                    <circle cx="100" cy="25" r="16"
                            :fill="skeletonColorsA.head"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('neck_angle')}"/>
                    <!-- Neck -->
                    <circle cx="100" cy="58" r="7"
                            :fill="skeletonColorsA.neck"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('neck_angle')}"/>
                    <!-- Shoulders -->
                    <circle cx="62" cy="80" r="7"
                            :fill="skeletonColorsA.lShoulder"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('upper_arm_angle')}"/>
                    <circle cx="138" cy="80" r="7"
                            :fill="skeletonColorsA.rShoulder"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('upper_arm_angle')}"/>
                    <!-- Elbows -->
                    <circle cx="45" cy="130" r="7"
                            :fill="skeletonColorsA.lElbow"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('lower_arm_angle')}"/>
                    <circle cx="155" cy="130" r="7"
                            :fill="skeletonColorsA.rElbow"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('lower_arm_angle')}"/>
                    <!-- Wrists -->
                    <circle cx="35" cy="170" r="7"
                            :fill="skeletonColorsA.lWrist"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('wrist_angle')}"/>
                    <circle cx="165" cy="170" r="7"
                            :fill="skeletonColorsA.rWrist"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('wrist_angle')}"/>
                    <!-- Trunk midpoint -->
                    <circle cx="100" cy="120" r="6"
                            :fill="skeletonColorsA.trunk"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('trunk_angle')}"/>
                    <!-- Hips -->
                    <circle cx="78"  cy="190" r="7" :fill="skeletonColorsA.lHip"   class="skeleton-joint"/>
                    <circle cx="122" cy="190" r="7" :fill="skeletonColorsA.rHip"   class="skeleton-joint"/>
                    <!-- Knees -->
                    <circle cx="70"  cy="255" r="6" :fill="skeletonColorsA.lKnee"  class="skeleton-joint"/>
                    <circle cx="130" cy="255" r="6" :fill="skeletonColorsA.rKnee"  class="skeleton-joint"/>
                    <!-- Ankles -->
                    <circle cx="65"  cy="305" r="5" :fill="skeletonColorsA.lAnkle" class="skeleton-joint"/>
                    <circle cx="135" cy="305" r="5" :fill="skeletonColorsA.rAnkle" class="skeleton-joint"/>
                  </svg>
                </div>
              </div><!-- /Scan A skeleton -->

              <!-- Scan B skeleton -->
              <div class="skeleton-wrap">
                <div class="skeleton-label">
                  <span class="compare-dot dot-b"></span> Scan B
                </div>
                <div class="skeleton-svg-container">
                  <svg viewBox="0 0 200 320" class="skeleton-svg" xmlns="http://www.w3.org/2000/svg">
                    <g stroke="#e2e8f0" stroke-width="3.5" stroke-linecap="round" fill="none">
                      <line x1="100" y1="41"  x2="100" y2="58"/>
                      <line x1="100" y1="58"  x2="62"  y2="80"/>
                      <line x1="100" y1="58"  x2="138" y2="80"/>
                      <line x1="62"  y1="80"  x2="45"  y2="130"/>
                      <line x1="138" y1="80"  x2="155" y2="130"/>
                      <line x1="45"  y1="130" x2="35"  y2="170"/>
                      <line x1="155" y1="130" x2="165" y2="170"/>
                      <line x1="100" y1="58"  x2="100" y2="175"/>
                      <line x1="100" y1="175" x2="78"  y2="190"/>
                      <line x1="100" y1="175" x2="122" y2="190"/>
                      <line x1="78"  y1="190" x2="70"  y2="255"/>
                      <line x1="122" y1="190" x2="130" y2="255"/>
                      <line x1="70"  y1="255" x2="65"  y2="305"/>
                      <line x1="130" y1="255" x2="135" y2="305"/>
                    </g>
                    <circle cx="100" cy="25"  r="16"
                            :fill="skeletonColorsB.head"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('neck_angle')}"/>
                    <circle cx="100" cy="58"  r="7"
                            :fill="skeletonColorsB.neck"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('neck_angle')}"/>
                    <circle cx="62"  cy="80"  r="7"
                            :fill="skeletonColorsB.lShoulder"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('upper_arm_angle')}"/>
                    <circle cx="138" cy="80"  r="7"
                            :fill="skeletonColorsB.rShoulder"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('upper_arm_angle')}"/>
                    <circle cx="45"  cy="130" r="7"
                            :fill="skeletonColorsB.lElbow"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('lower_arm_angle')}"/>
                    <circle cx="155" cy="130" r="7"
                            :fill="skeletonColorsB.rElbow"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('lower_arm_angle')}"/>
                    <circle cx="35"  cy="170" r="7"
                            :fill="skeletonColorsB.lWrist"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('wrist_angle')}"/>
                    <circle cx="165" cy="170" r="7"
                            :fill="skeletonColorsB.rWrist"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('wrist_angle')}"/>
                    <circle cx="100" cy="120" r="6"
                            :fill="skeletonColorsB.trunk"
                            class="skeleton-joint"
                            :class="{'skeleton-joint-highlight': topDiffAngles.includes('trunk_angle')}"/>
                    <circle cx="78"  cy="190" r="7" :fill="skeletonColorsB.lHip"   class="skeleton-joint"/>
                    <circle cx="122" cy="190" r="7" :fill="skeletonColorsB.rHip"   class="skeleton-joint"/>
                    <circle cx="70"  cy="255" r="6" :fill="skeletonColorsB.lKnee"  class="skeleton-joint"/>
                    <circle cx="130" cy="255" r="6" :fill="skeletonColorsB.rKnee"  class="skeleton-joint"/>
                    <circle cx="65"  cy="305" r="5" :fill="skeletonColorsB.lAnkle" class="skeleton-joint"/>
                    <circle cx="135" cy="305" r="5" :fill="skeletonColorsB.rAnkle" class="skeleton-joint"/>
                  </svg>
                </div>
              </div><!-- /Scan B skeleton -->

            </div><!-- /skeleton-pair-wrap -->

            <p class="text-xs text-muted text-center mt-3 mb-0"
               x-show="topDiffAngles.length > 0" x-cloak>
              <i class="bi bi-record-circle text-warning me-1"></i>
              Outlined joints indicate the <strong>top differences</strong> between scans.
            </p>
            <p class="text-xs text-muted text-center mt-2 mb-0"
               x-show="!comparison.pose_delta.available" x-cloak>
              <i class="bi bi-info-circle me-1"></i>
              Pose angle data unavailable — skeleton coloured from overall risk score.
            </p>
          </div><!-- /card-body -->
        </div><!-- /card -->
      </div><!-- /SkeletonViewer col -->

      <!-- JointHeatmap -->
      <div class="col-12 col-lg-5">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-thermometer-half text-primary"></i>
            <h6 class="mb-0 fw-semibold">Joint Heatmap</h6>
            <span class="ms-auto badge badge-soft-secondary text-xs"
                  x-show="comparison.pose_delta.available"
                  x-text="anglesList.length + ' joints'"></span>
          </div>
          <div class="card-body p-0">

            <!-- No pose data -->
            <div class="p-4 text-center text-muted text-sm"
                 x-show="!comparison.pose_delta.available" x-cloak>
              <i class="bi bi-camera-off d-block mb-2 fs-4"></i>
              <span x-text="comparison.pose_delta.reason || 'Pose angle data not available'"></span>
            </div>

            <!-- Per-joint rows -->
            <template x-if="comparison.pose_delta.available">
              <div>
                <template x-for="item in anglesList" :key="item.key">
                  <div class="joint-heatmap-row"
                       :class="topDiffAngles.includes(item.key) ? 'joint-heatmap-row-highlighted' : ''">

                    <!-- Label -->
                    <div class="joint-heatmap-label">
                      <span class="joint-dot"
                            :style="'background:' + riskColor(Math.min(100, Math.abs(item.scan_a) / 90 * 100))"></span>
                      <span x-text="prettyKey(item.key)"></span>
                      <i class="bi bi-star-fill text-warning ms-1"
                         style="font-size:.6rem"
                         x-show="topDiffAngles.includes(item.key)" x-cloak
                         title="Largest difference"></i>
                    </div>

                    <!-- Bars -->
                    <div class="joint-heatmap-bars">
                      <div class="heatmap-bar-wrap">
                        <span class="heatmap-bar-label"
                              x-text="'A: ' + item.scan_a.toFixed(1) + '°'"></span>
                        <div class="heatmap-bar-track">
                          <div class="heatmap-bar"
                               :style="'width:' + Math.min(100, Math.abs(item.scan_a)) + '%;background:' + riskColor(Math.min(100, Math.abs(item.scan_a) / 90 * 100))">
                          </div>
                        </div>
                      </div>
                      <div class="heatmap-bar-wrap">
                        <span class="heatmap-bar-label"
                              x-text="'B: ' + item.scan_b.toFixed(1) + '°'"></span>
                        <div class="heatmap-bar-track">
                          <div class="heatmap-bar"
                               :style="'width:' + Math.min(100, Math.abs(item.scan_b)) + '%;background:' + riskColor(Math.min(100, Math.abs(item.scan_b) / 90 * 100))">
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Delta -->
                    <div class="joint-heatmap-delta" :class="deltaClass(item.delta)">
                      <i class="bi" :class="deltaIcon(item.delta)"></i>
                      <span x-text="(item.delta > 0 ? '+' : '') + item.delta.toFixed(1) + '°'"></span>
                    </div>

                  </div><!-- /row -->
                </template>
              </div>
            </template>
          </div>
        </div>
      </div><!-- /JointHeatmap col -->

    </div><!-- /row: skeleton + heatmap -->

    <!-- ── ComparisonTree ─────────────────────────────────────────────── -->
    <div class="card mb-4">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-diagram-3 text-primary"></i>
        <h6 class="mb-0 fw-semibold">Node Comparison</h6>
        <span class="ms-auto badge badge-soft-secondary"
              x-show="comparison.nodes.length > 0"
              x-text="comparison.nodes.length + ' metrics'"></span>
      </div>
      <div class="card-body p-0">

        <!-- Empty state -->
        <div class="p-4 text-center text-muted text-sm"
             x-show="comparison.nodes.length === 0" x-cloak>
          <i class="bi bi-bar-chart d-block mb-2 fs-4"></i>
          No node-level metric data available for these scans.
        </div>

        <!-- Tree table -->
        <template x-if="comparison.nodes.length > 0">
          <div>
            <div class="compare-tree-header">
              <span>Metric</span>
              <span class="text-center">Scan A</span>
              <span class="text-center">Scan B</span>
              <span class="text-center">Delta</span>
            </div>
            <template x-for="node in comparison.nodes" :key="node.key">
              <div class="compare-tree-row">
                <span class="compare-tree-name" x-text="node.node"></span>
                <span class="text-center">
                  <span class="compare-tree-pill" x-text="node.scan_a.toFixed(1)"></span>
                </span>
                <span class="text-center">
                  <span class="compare-tree-pill compare-tree-pill-b" x-text="node.scan_b.toFixed(1)"></span>
                </span>
                <div class="compare-tree-delta" :class="deltaClass(node.delta)">
                  <i class="bi" :class="deltaIcon(node.delta)"></i>
                  <span x-text="(node.delta > 0 ? '+' : '') + node.delta.toFixed(1)"></span>
                </div>
              </div>
            </template>
          </div>
        </template>
      </div>
    </div><!-- /ComparisonTree -->

    <!-- ── Timeline ──────────────────────────────────────────────────── -->
    <div class="card mb-4">
      <div class="card-header d-flex align-items-center gap-2 flex-wrap">
        <i class="bi bi-graph-up text-primary"></i>
        <h6 class="mb-0 fw-semibold">Score Timeline</h6>
        <div class="ms-auto d-flex gap-3 text-xs align-items-center">
          <span class="d-flex align-items-center gap-1">
            <span style="width:10px;height:10px;border-radius:50%;background:#7c3aed;display:inline-block"></span>
            Scan A
          </span>
          <span class="d-flex align-items-center gap-1">
            <span style="width:10px;height:10px;border-radius:50%;background:#0ea5e9;display:inline-block"></span>
            Scan B
          </span>
          <span class="text-muted" x-text="'(' + scans.length + ' scans)'"></span>
        </div>
      </div>
      <div class="card-body">
        <div class="timeline-chart-wrap">
          <canvas id="cmpTimelineChart"></canvas>
        </div>
      </div>
    </div><!-- /Timeline -->

  </div><!-- /comparison results -->
  </template>

</div><!-- /x-data -->

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
