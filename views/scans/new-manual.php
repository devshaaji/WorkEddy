<?php
$pageTitle  = 'New Manual Scan';
$activePage = 'scans';
ob_start();
?>
<div x-data="manualScanPage">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">New Manual Scan</h1>
      <ol class="breadcrumb mb-0 text-sm">
        <li class="breadcrumb-item"><a href="/tasks" class="text-decoration-none text-muted">Tasks</a></li>
        <li class="breadcrumb-item active">Manual Scan</li>
      </ol>
    </div>
    <a href="/tasks" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Tasks
    </a>
  </div>

  <div style="max-width:760px;">

    <!-- Error -->
    <div class="alert alert-danger align-items-center gap-2"
         x-show="error" x-text="error" x-cloak></div>

    <!-- Section: Task & Model -->
    <div class="card mb-4">
      <div class="card-header">
        <h6 class="mb-0 fw-semibold">Task &amp; Assessment Model</h6>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label" for="scanTask">Task</label>
            <select class="form-select" id="scanTask" x-model="selectedTask">
              <template x-for="t in tasks" :key="t.id">
                <option :value="t.id" x-text="t.name"></option>
              </template>
            </select>
            <div class="form-text">Select the work task to assess.</div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Assessment Model</label>
            <div class="d-flex gap-2 flex-wrap mt-1">
              <template x-for="m in models" :key="m.value">
                <button type="button"
                        class="btn btn-sm"
                        :class="model === m.value ? 'btn-primary' : 'btn-outline-secondary'"
                        @click="model = m.value"
                        x-text="m.label"></button>
              </template>
            </div>
            <div class="form-text mt-1" x-text="modelDescription" x-cloak></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Section: Posture Angles (RULA/REBA) -->
    <template x-if="activeFields.includes('neck_angle')">
      <div class="card mb-4">
        <div class="card-header">
          <h6 class="mb-0 fw-semibold">Posture Angles</h6>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6 col-lg-4">
              <label class="form-label" for="neckAngle">Neck angle (°)</label>
              <input class="form-control" id="neckAngle" type="number"
                     x-model="form.neck_angle" min="-20" max="60" placeholder="0–60">
              <div class="form-text">-20° to 60°</div>
            </div>
            <div class="col-6 col-lg-4">
              <label class="form-label" for="trunkAngle">Trunk angle (°)</label>
              <input class="form-control" id="trunkAngle" type="number"
                     x-model="form.trunk_angle" min="0" max="180" placeholder="0–180">
            </div>
            <div class="col-6 col-lg-4">
              <label class="form-label" for="upperArmAngle">Upper arm (°)</label>
              <input class="form-control" id="upperArmAngle" type="number"
                     x-model="form.upper_arm_angle" min="-20" max="180" placeholder="0–180">
            </div>
            <div class="col-6 col-lg-4">
              <label class="form-label" for="lowerArmAngle">Lower arm (°)</label>
              <input class="form-control" id="lowerArmAngle" type="number"
                     x-model="form.lower_arm_angle" min="0" max="180" placeholder="60–100">
            </div>
            <div class="col-6 col-lg-4">
              <label class="form-label" for="wristAngle">Wrist angle (°)</label>
              <input class="form-control" id="wristAngle" type="number"
                     x-model="form.wrist_angle" min="-15" max="15" placeholder="-15 – 15">
            </div>
            <div class="col-6 col-lg-4" x-show="activeFields.includes('leg_score')" x-cloak>
              <label class="form-label" for="legScore">Leg score</label>
              <select class="form-select" id="legScore" x-model="form.leg_score">
                <option value="1">1 – Balanced / sitting</option>
                <option value="2">2 – Unstable / one-legged</option>
              </select>
            </div>
          </div>

          <div class="row g-3 mt-1" x-show="activeFields.includes('load_weight')" x-cloak>
            <div class="col-6 col-lg-4">
              <label class="form-label" for="loadWeightR">Load weight (kg)</label>
              <input class="form-control" id="loadWeightR" type="number"
                     x-model="form.load_weight" min="0" placeholder="e.g. 10">
            </div>
            <div class="col-6 col-lg-4" x-show="activeFields.includes('coupling')" x-cloak>
              <label class="form-label" for="coupling">Coupling</label>
              <select class="form-select" id="coupling" x-model="form.coupling">
                <option value="0">0 – Good</option>
                <option value="1">1 – Fair</option>
                <option value="2">2 – Poor</option>
                <option value="3">3 – Unacceptable</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Section: Lifting Parameters (NIOSH) -->
    <template x-if="activeFields.includes('horizontal_distance')">
      <div class="card mb-4">
        <div class="card-header">
          <h6 class="mb-0 fw-semibold">Lifting Parameters</h6>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6 col-lg-4">
              <label class="form-label" for="loadWeight">Load weight (kg)</label>
              <input class="form-control" id="loadWeight" type="number"
                     x-model="form.load_weight" min="0" placeholder="e.g. 15">
            </div>
            <div class="col-6 col-lg-4">
              <label class="form-label" for="hDist">Horizontal dist. (cm)</label>
              <input class="form-control" id="hDist" type="number"
                     x-model="form.horizontal_distance" min="25" max="63" placeholder="25–63">
            </div>
            <div class="col-6 col-lg-4">
              <label class="form-label" for="vStart">Vertical start (cm)</label>
              <input class="form-control" id="vStart" type="number"
                     x-model="form.vertical_start" min="0" max="175" placeholder="0–175">
            </div>
            <div class="col-6 col-lg-4">
              <label class="form-label" for="vTravel">Vertical travel (cm)</label>
              <input class="form-control" id="vTravel" type="number"
                     x-model="form.vertical_travel" min="0" max="175" placeholder="0–175">
            </div>
            <div class="col-6 col-lg-4">
              <label class="form-label" for="twistAngle">Twist angle (°)</label>
              <input class="form-control" id="twistAngle" type="number"
                     x-model="form.twist_angle" min="0" max="135" placeholder="0–135">
            </div>
            <div class="col-6 col-lg-4">
              <label class="form-label" for="freq">Frequency (lifts/min)</label>
              <input class="form-control" id="freq" type="number"
                     x-model="form.frequency" min="0" max="15" step="0.5" placeholder="e.g. 3">
            </div>
            <div class="col-6 col-lg-4">
              <label class="form-label" for="nioshCoupling">Coupling quality</label>
              <select class="form-select" id="nioshCoupling" x-model="form.coupling">
                <option value="0">Good</option>
                <option value="1">Fair</option>
                <option value="2">Poor</option>
              </select>
            </div>
            <div class="col-6 col-lg-4">
              <label class="form-label" for="duration">Duration</label>
              <select class="form-select" id="duration">
                <option value="1">≤ 1 hour</option>
                <option value="2">1–2 hours</option>
                <option value="3">&gt; 2 hours</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Section: Notes -->
    <div class="card mb-4">
      <div class="card-header">
        <h6 class="mb-0 fw-semibold">Notes</h6>
      </div>
      <div class="card-body">
        <textarea class="form-control" rows="2"
                  x-model="form.notes"
                  placeholder="Any additional observations about the work environment or posture…"></textarea>
      </div>
    </div>

    <!-- Submit -->
    <div class="d-flex gap-2">
      <button class="btn btn-primary" @click="submit" :disabled="loading">
        <span class="spinner-border spinner-border-sm me-2" x-show="loading" x-cloak></span>
        <i class="bi bi-play-fill me-1" x-show="!loading"></i>
        <span x-text="loading ? 'Analysing…' : 'Run Assessment'"></span>
      </button>
      <a href="/tasks" class="btn btn-light">Cancel</a>
    </div>

  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
