<?php
$title = 'Home';
include __DIR__ . '/../partials/site/v2_header.php';
?>
<!-- Hero Section -->
<section class="landing-hero">
  <div class="grid-bg"></div>
  <div class="container position-relative z-1">
    <div class="row align-items-center">
      <div class="col-lg-6 mb-5 mb-lg-0">
        <div class="badge bg-info bg-opacity-10 text-cyan border border-info border-opacity-25 rounded-pill px-3 py-2 mb-4 fw-bold font-mono">
          ✨ AI Ergonomics for MSD Prevention
        </div>
        <h1 class="hero-title">Prevent musculoskeletal injuries <br /><span>before they happen</span></h1>
        <p class="hero-subtitle">WorkEddy transforms everyday task videos into ergonomic risk scores, prioritized interventions, and reports your team can act on within minutes. Identify harmful movement sooner and guide safer work design.</p>

        <div class="d-flex gap-3 hero-buttons flex-wrap">
          <a href="/register" class="btn btn-tech btn-lg px-5 shadow font-mono text-uppercase" style="font-size:1rem;">Start for Free</a>
          <a href="#how-it-works" class="btn btn-outline-tech btn-lg px-4 bg-transparent font-mono text-uppercase" style="font-size:1rem;">View Sample Report</a>
        </div>

        <div class="micro-value-tags mt-4">
          <div class="micro-value-tag"><i class="bi bi-camera-video-fill text-cyan opacity-75"></i> Video based analysis</div>
          <div class="micro-value-tag"><i class="bi bi-shield-lock-fill text-cyan opacity-75"></i> Privacy first posture detection</div>
          <div class="micro-value-tag"><i class="bi bi-arrow-left-right text-cyan opacity-75"></i> Before and after risk comparison</div>
        </div>

        <p class="small text-muted mt-3 mb-4"><i class="bi bi-shield-check text-success"></i> Built with secure access and privacy conscious task analysis.</p>
      </div>

      <div class="col-lg-6">
        <!-- Industry Tabs -->
        <div class="mb-3 d-flex flex-wrap justify-content-between align-items-center">
          <div class="industry-tabs d-flex flex-wrap flex-grow-1" id="mock-tabs">
            <button class="ind-tab active" onclick="switchContext('WH', this)">[WH] WAREHOUSE</button>
            <button class="ind-tab" onclick="switchContext('LOG', this)">[LOG] LOGISTICS</button>
            <button class="ind-tab" onclick="switchContext('MFG', this)">[MFG] MANUFACTURING</button>
            <button class="ind-tab" onclick="switchContext('HC', this)">[HC] HEALTHCARE</button>
            <button class="ind-tab" onclick="switchContext('CON', this)">[CN] CONSTRUCTION</button>
          </div>
        </div>

        <!-- Artificial Intelligence Dashboard Mockup -->
        <div class="tech-panel shadow-lg">

          <div class="tech-panel-header text-muted justify-content-between">
              <div>
                <span><i class="bi bi-record-circle text-danger me-1"></i> PID: 8092_THD</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <div class="form-check form-switch m-0">
                  <input class="form-check-input mt-0 bg-dark border-secondary" type="checkbox" role="switch" id="beforeAfterToggle" style="cursor:pointer; width:2.2em; height:1.05em;">
                  <label class="form-check-label small ms-2 text-muted" for="beforeAfterToggle">Compare B/A</label>
                </div>
              </div>
          </div>

          <div class="p-3">
            <div class="row g-3">
              <!-- Video Feed -->
              <div class="col-12">
                <div class="video-analyzer position-relative overflow-hidden border border-secondary border-opacity-25 rounded-3" style="height: 250px; background: #000;">
                  <div id="three-pose-container" class="w-100 h-100 position-absolute top-0 start-0 z-0"></div>
                  <div class="bounding-box z-2" style="pointer-events:none;">
                    <div class="corner-tl"></div>
                    <div class="corner-tr"></div>
                    <div class="corner-bl"></div>
                    <div class="corner-br"></div>
                  </div>
                </div>
              </div>

              <!-- Telemetry Readout -->
              <div class="col-sm-6">
                <div class="p-3 bg-black bg-opacity-50 border border-secondary border-opacity-25 rounded-2 h-100">
                  <p class="font-mono text-muted mb-2 text-uppercase fw-bold" style="font-size:0.7rem;">Body Area Risk</p>
                  <div class="data-row">
                    <span class="text-light fw-medium" id="term-risk-area-1">Lower Back</span>
                    <span class="text-danger font-mono" id="term-risk-val-1">> 60° [HIGH]</span>
                  </div>
                  <div class="data-row">
                    <span class="text-light fw-medium" id="term-risk-area-2">Shoulder</span>
                    <span class="text-warning font-mono" id="term-risk-val-2">42° [MED]</span>
                  </div>
                  <div class="data-row">
                    <span class="text-light fw-medium" id="term-risk-area-3">Knee</span>
                    <span class="text-success font-mono" id="term-risk-val-3">OK [LOW]</span>
                  </div>
                </div>
              </div>

              <!-- Engine Decision -->
              <div class="col-sm-6">
                <div class="p-3 bg-black bg-opacity-50 border border-info border-opacity-25 rounded-2 h-100 position-relative">
                  <div class="position-absolute top-0 start-0 w-100 h-100 bg-info bg-opacity-10 pointer-events-none"></div>
                  <p class="font-mono text-cyan mb-2 text-uppercase fw-bold" style="font-size:0.7rem;"><i class="bi bi-lightning-charge-fill text-warning"></i> Priority Action</p>
                  <div class="d-flex align-items-end justify-content-between mb-2">
                    <h6 class="text-white m-0 fw-bold" style="font-size: 0.95rem; line-height:1.2;" id="term-action">Reduce trunk flexion by raising pallet height</h6>
                  </div>
                  <p class="text-emerald fw-bold small mb-0 lh-sm font-mono mt-2" style="font-size: 0.8rem;" id="term-score">
                    <i class="bi bi-arrow-down"></i> -3.2 Score Impact
                  </p>
                </div>
              </div>

            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>

<!-- How it works -->
<section id="how-it-works" class="py-3 bg-black border-top border-bottom border-secondary border-opacity-25">
  <div class="container py-3">
    <div class="row align-items-center">
      <div class="col-lg-6 mb-5 mb-lg-0 pe-lg-5">
        <h2 class="fw-bold mb-4 text-white" style="font-size:3rem; letter-spacing: -1px; line-height: 1.1;">See risk early and act faster in 3 steps</h2>
        <p class="text-light opacity-75 fs-5 mb-3">Capture real work, detect ergonomic risk early, and get clear actions your team can use to prevent strain.</p>
        <p class="fw-bold text-cyan mb-5 font-mono text-uppercase" style="font-size: 0.85rem;"><i class="bi bi-clock-history"></i> > From upload to risk report in minutes</p>

        <div class="d-flex mb-4">
          <div class="me-4">
            <div class="tech-badge border border-cyan text-cyan font-mono bg-dark px-2 py-1 fs-5">1</div>
          </div>
          <div>
            <h5 class="text-white fw-bold" style="font-size:1.25rem;">Capture the task</h5>
            <p class="text-light opacity-75">Record a real job task on any smartphone and upload it securely to WorkEddy.</p>
          </div>
        </div>

        <div class="d-flex mb-4">
          <div class="me-4">
            <div class="tech-badge border border-cyan text-cyan font-mono bg-dark px-2 py-1 fs-5">2</div>
          </div>
          <div>
            <h5 class="text-white fw-bold" style="font-size:1.25rem;">Detect posture risk</h5>
            <p class="text-light opacity-75">WorkEddy analyzes movement, body position, and strain exposure using trusted ergonomic methods such as REBA, RULA, and NIOSH.</p>
          </div>
        </div>

        <div class="d-flex mb-4">
          <div class="me-4">
            <div class="tech-badge border border-cyan text-cyan font-mono bg-dark px-2 py-1 fs-5">3</div>
          </div>
          <div>
            <h5 class="text-white fw-bold" style="font-size:1.25rem;">Fix what needs attention first</h5>
            <p class="text-light opacity-75">Get prioritized interventions, export reports, and compare tasks over time to see whether redesign efforts are reducing risk.</p>
          </div>
        </div>

        <div class="mt-4 pt-4 border-top border-secondary border-opacity-25">
          <p class="small text-muted mb-0 fw-medium d-flex align-items-center"><i class="bi bi-shield-check text-success me-2 fs-5"></i> Secure upload. Trusted ergonomic methods. Action ready outputs.</p>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="tech-panel p-4 h-100 mx-auto position-relative" style="max-width: 500px;">
          <div class="font-mono text-muted mb-4 border-bottom border-secondary border-opacity-25 pb-2 text-uppercase d-flex justify-content-between" style="font-size:0.75rem;">
            <span>System Output Logs</span>
            <span class="text-success">[COMPLETED]</span>
          </div>

          <!-- Command Line Mock -->
          <div class="font-mono text-light" style="font-size: 0.85rem; line-height: 1.8;">
            <div class="text-muted">> Uploading task_video.mp4... <span class="text-success">[100%]</span></div>
            <div class="text-muted">> Analyzing movement patterns...</div>
            <div><span class="text-info">[ACT]</span> applying RULA framework...</div>
            <div><span class="text-info">[ACT]</span> applying REBA framework...</div>
            <div class="mt-2 text-warning">> ALERT: High risk detected in lower back</div>
            <div class="mt-2 text-muted">> compiling intervention report</div>
            <div class="px-3 py-2 bg-dark border border-danger my-3">
              <div class="text-danger fw-bold d-flex justify-content-between">
                <span>REBA Score</span>
                <span>8.5 (HIGH RISK)</span>
              </div>
            </div>
            <div class="text-success blink-cursor">_</div>
          </div>
          <style>
            .blink-cursor {
              animation: blink 1s step-end infinite;
            }

            @keyframes blink {
              50% {
                opacity: 0;
              }
            }
          </style>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Features -->
<section id="features" class="py-2 position-relative">
  <div class="container py-5 z-1 position-relative">
    <div class="text-center mb-5 max-w-2xl mx-auto">
      <h2 class="fw-bold text-white mb-3" style="font-size: 2.5rem; letter-spacing: -1px; font-family:'Outfit', sans-serif;">Everything you need to detect risk early and prevent strain</h2>
      <p class="text-light opacity-75 fs-5">Replace manual guesswork with video based ergonomic analysis, trusted scoring, and action ready insights that help teams reduce musculoskeletal risk.</p>
    </div>

    <div class="row g-4">
      <!-- Feature 1 -->
      <div class="col-md-6 col-lg-3">
        <div class="tech-panel p-4 rounded-4 h-100 d-flex flex-column transition-all hover-border-cyan">
          <div class="bg-dark rounded-3 p-3 mb-4 d-flex align-items-center justify-content-center position-relative overflow-hidden border border-secondary border-opacity-25" style="height: 140px; background: linear-gradient(180deg, rgba(255,255,255,0.05) 0%, rgba(0,0,0,0) 100%);">
            <svg viewBox="0 0 100 100" class="w-100 h-100 opacity-75">
              <line x1="50" y1="20" x2="50" y2="50" stroke="var(--tech-cyan, #22d3ee)" stroke-width="4" stroke-linecap="round"/>
              <line x1="50" y1="25" x2="35" y2="40" stroke="var(--tech-cyan, #22d3ee)" stroke-width="4" stroke-linecap="round"/>
              <line x1="50" y1="25" x2="65" y2="40" stroke="var(--tech-cyan, #22d3ee)" stroke-width="4" stroke-linecap="round"/>
              <line x1="50" y1="50" x2="40" y2="75" stroke="var(--tech-cyan, #22d3ee)" stroke-width="4" stroke-linecap="round"/>
              <line x1="50" y1="50" x2="60" y2="75" stroke="var(--tech-cyan, #22d3ee)" stroke-width="4" stroke-linecap="round"/>
              <circle cx="50" cy="12" r="7" fill="var(--tech-cyan, #22d3ee)"/>
              <circle cx="50" cy="45" r="5" fill="#ef4444"/>
              <circle cx="40" cy="75" r="4" fill="#f59e0b"/>
              <line x1="20" y1="35" x2="80" y2="35" stroke="#10b981" stroke-width="1.5" stroke-dasharray="2,2"/>
            </svg>
          </div>
          <h5 class="text-white fw-bold mb-3">See risk from real task video</h5>
          <p class="text-light opacity-75 small mb-0 mt-auto">Upload a task video and let WorkEddy detect posture risk, joint movement, and strain exposure in minutes.</p>
        </div>
      </div>

      <!-- Feature 2 -->
      <div class="col-md-6 col-lg-3">
        <div class="tech-panel p-4 rounded-4 h-100 d-flex flex-column transition-all hover-border-cyan">
          <div class="bg-dark rounded-3 p-3 mb-4 d-flex flex-column align-items-center justify-content-center gap-3 border border-secondary border-opacity-25" style="height: 140px; background: linear-gradient(180deg, rgba(245, 158, 11, 0.1) 0%, rgba(0,0,0,0) 100%);">
            <div class="d-flex w-100 justify-content-between align-items-center px-1 border-bottom border-secondary border-opacity-50 pb-2">
              <span class="fw-bold text-light small"><i class="bi bi-file-earmark-code me-1 text-muted"></i>REBA</span>
              <span class="badge bg-danger rounded-pill shadow-sm font-mono" style="font-size: 0.75rem;">8.5 High</span>
            </div>
            <div class="d-flex w-100 justify-content-between align-items-center px-1">
              <span class="fw-bold text-light small"><i class="bi bi-file-earmark-code me-1 text-muted"></i>RULA</span>
              <span class="badge bg-warning text-dark rounded-pill shadow-sm font-mono" style="font-size: 0.75rem;">6.0 Med</span>
            </div>
          </div>
          <h5 class="text-white fw-bold mb-3">Score with trusted methods</h5>
          <p class="text-light opacity-75 small mb-0 mt-auto">Map findings to recognized ergonomic frameworks such as REBA, RULA, and NIOSH for more consistent review.</p>
        </div>
      </div>

      <!-- Feature 3 -->
      <div class="col-md-6 col-lg-3">
        <div class="tech-panel p-4 rounded-4 h-100 d-flex flex-column transition-all hover-border-cyan">
          <div class="bg-dark rounded-3 p-3 mb-4 d-flex align-items-center justify-content-center flex-column gap-3 border border-secondary border-opacity-25" style="height: 140px; background: linear-gradient(180deg, rgba(239, 68, 68, 0.1) 0%, rgba(0,0,0,0) 100%);">
            <div class="w-100">
              <div class="d-flex justify-content-between small mb-1 fw-bold"><span class="text-light font-mono" style="font-size: 0.75rem;">Lower Back</span> <span class="text-danger font-mono" style="font-size: 0.75rem;">High</span></div>
              <div class="progress shadow-sm bg-secondary bg-opacity-25" style="height: 6px;">
                <div class="progress-bar bg-danger rounded-pill" style="width: 85%"></div>
              </div>
            </div>
            <div class="w-100">
              <div class="d-flex justify-content-between small mb-1 fw-bold"><span class="text-light font-mono" style="font-size: 0.75rem;">Shoulder</span> <span class="text-warning font-mono" style="font-size: 0.75rem;">Med</span></div>
              <div class="progress shadow-sm bg-secondary bg-opacity-25" style="height: 6px;">
                <div class="progress-bar bg-warning rounded-pill" style="width: 55%"></div>
              </div>
            </div>
          </div>
          <h5 class="text-white fw-bold mb-3">Know what to fix first</h5>
          <p class="text-light opacity-75 small mb-0 mt-auto">Highlight the tasks, body regions, and movement patterns that deserve immediate intervention.</p>
        </div>
      </div>

      <!-- Feature 4 -->
      <div class="col-md-6 col-lg-3">
        <div class="tech-panel p-4 rounded-4 h-100 d-flex flex-column transition-all hover-border-cyan">
          <div class="bg-dark rounded-3 p-3 mb-4 d-flex flex-column align-items-center justify-content-center gap-2 border border-secondary border-opacity-25" style="height: 140px; background: linear-gradient(180deg, rgba(16, 185, 129, 0.1) 0%, rgba(0,0,0,0) 100%);">
             <div class="d-flex align-items-center gap-1 w-100 justify-content-center mb-1">
               <div class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-2 py-1 font-mono" style="font-size: 0.7rem;">Bef: 8.2</div>
               <i class="bi bi-chevron-right text-muted" style="font-size: 0.75rem;"></i>
               <div class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-1 font-mono" style="font-size: 0.7rem;">Aft: 3.4</div>
             </div>
             <div class="d-inline-flex border border-success border-opacity-25 bg-success bg-opacity-10 rounded-pill px-2 py-2 mt-2">
               <span class="small fw-bold text-success m-0 font-mono" style="font-size: 0.7rem;"><i class="bi bi-graph-down-arrow me-1"></i> 58% Reduction</span>
             </div>
          </div>
          <h5 class="text-white fw-bold mb-3">Prove improvement over time</h5>
          <p class="text-light opacity-75 small mb-0 mt-auto">Compare tasks before and after changes, track exposure trends, and show whether redesign efforts are reducing risk.</p>
        </div>
      </div>
    </div>
    <style>
      .hover-border-cyan:hover {
        border-color: var(--tech-accent);
        box-shadow: 0 0 10px rgba(56, 189, 248, 0.1);
      }
    </style>
  </div>
</section>

<!-- Pricing -->
<section id="pricing" class="py-2 bg-black border-top border-secondary border-opacity-25">
  <div class="container py-5">
    <div class="text-center mb-5 mx-auto" style="max-width: 800px;">
      <h2 class="fw-bold text-white mb-3" style="font-family:'Outfit', sans-serif; font-size: 2.5rem; letter-spacing: -1px;">Start free. Scale when your team needs more visibility and action.</h2>
      <p class="text-light opacity-75 fs-5">Choose the plan that fits your ergonomic risk workflow today, then upgrade as your team needs more assessments, reporting, and prevention insight.</p>
    </div>

    <div class="row justify-content-center g-4">
      <!-- Free Plan -->
      <div class="col-md-6 col-lg-4">
        <div class="pricing-card p-4 rounded-3 h-100 d-flex flex-column text-center">
          <h4 class="text-white fw-bold mb-3">Free</h4>
          <h2 class="display-5 text-white fw-bold mb-2">$0<span class="fs-6 text-muted fw-normal">/mo</span></h2>
          <p class="text-cyan mb-4 small fw-bold text-uppercase font-mono" style="letter-spacing: 0.5px; font-size: 0.7rem;">Best for early pilots and smaller teams</p>

          <ul class="list-unstyled text-start mb-4 mt-auto">
            <li class="mb-3 text-light small fw-medium"><i class="bi bi-check-circle-fill text-success me-2"></i> 10 completed assessments each month</li>
            <li class="mb-3 text-light small fw-medium"><i class="bi bi-check-circle-fill text-success me-2"></i> Manual and video assessments</li>
            <li class="mb-3 text-light small fw-medium"><i class="bi bi-check-circle-fill text-success me-2"></i> 1 organization</li>
            <li class="mb-3 text-light small fw-medium"><i class="bi bi-check-circle-fill text-success me-2"></i> Core reporting</li>
          </ul>
          <a href="/register" class="btn btn-outline-tech w-100 mt-auto text-uppercase">Start Free</a>
        </div>
      </div>

      <!-- Professional Plan -->
      <div class="col-md-6 col-lg-4">
        <div class="pricing-card pro p-4 rounded-3 h-100 d-flex flex-column position-relative text-center">
          <div class="position-absolute top-0 start-50 translate-middle tech-badge bg-info text-dark fw-bold border border-info border-opacity-50">MOST CHOSEN BY MULTI-SITE TEAMS</div>
          <h4 class="text-white fw-bold mt-2 mb-3">Professional</h4>
          <h2 class="display-5 text-white fw-bold mb-2">$99<span class="fs-6 text-muted fw-normal">/mo</span></h2>
          <p class="text-cyan mb-4 small fw-bold text-uppercase font-mono" style="letter-spacing: 0.5px; font-size: 0.7rem;">Best for growing safety and operations teams</p>

          <ul class="list-unstyled text-start mb-4 mt-auto">
            <li class="mb-3 text-light small fw-medium"><i class="bi bi-check-circle-fill text-cyan me-2"></i> 500 completed assessments each month</li>
            <li class="mb-3 text-light small fw-medium"><i class="bi bi-check-circle-fill text-cyan me-2"></i> Unlimited team members</li>
            <li class="mb-3 text-light small fw-medium"><i class="bi bi-check-circle-fill text-cyan me-2"></i> Exportable reports</li>
            <li class="mb-3 text-light small fw-medium"><i class="bi bi-check-circle-fill text-cyan me-2"></i> Dashboard visibility</li>
            <li class="mb-3 text-light small fw-medium"><i class="bi bi-check-circle-fill text-cyan me-2"></i> Prioritized intervention insights</li>
          </ul>
          <a href="/register" class="btn btn-tech w-100 mt-auto text-uppercase">Start Professional Trial</a>
        </div>
      </div>

      <!-- Enterprise Plan -->
      <div class="col-md-6 col-lg-4">
        <div class="pricing-card p-4 rounded-3 h-100 d-flex flex-column text-center">
          <h4 class="text-white fw-bold mb-3">Enterprise</h4>
          <h2 class="display-6 text-white fw-bold mb-2 d-flex justify-content-center align-items-center" style="height: 56px;">Custom pricing</h2>
          <p class="text-muted mb-4 small fw-bold text-uppercase font-mono" style="letter-spacing: 0.5px; font-size: 0.7rem;">Best for multi-site and large scale programs</p>

          <ul class="list-unstyled text-start mb-4 mt-auto">
            <li class="mb-3 text-light small fw-medium"><i class="bi bi-check-circle-fill text-secondary me-2"></i> Custom usage limits</li>
            <li class="mb-3 text-light small fw-medium"><i class="bi bi-check-circle-fill text-secondary me-2"></i> Advanced admin controls</li>
            <li class="mb-3 text-light small fw-medium"><i class="bi bi-check-circle-fill text-secondary me-2"></i> Expanded analytics</li>
            <li class="mb-3 text-light small fw-medium"><i class="bi bi-check-circle-fill text-secondary me-2"></i> Onboarding and procurement support</li>
          </ul>
          <a href="/contact" class="btn btn-outline-tech w-100 mt-auto text-uppercase">Contact Sales</a>
        </div>
      </div>
    </div>

    <!-- Trust Reassurance Band -->
    <div class="d-flex flex-wrap justify-content-center gap-4 mt-5 font-mono text-muted" style="font-size: 0.8rem;">
      <span class="d-flex align-items-center"><i class="bi bi-credit-card-2-front-fill fs-5 text-secondary me-2"></i> No credit card required</span>
      <span class="d-none d-md-inline opacity-50">&bull;</span>
      <span class="d-flex align-items-center"><i class="bi bi-check-all fs-4 text-emerald me-2"></i> Built on recognized ergonomic methods</span>
      <span class="d-none d-md-inline opacity-50">&bull;</span>
      <span class="d-flex align-items-center"><i class="bi bi-shield-lock-fill fs-5 text-secondary me-2"></i> including REBA, RULA, and NIOSH frameworks</span>
    </div>
  </div>
</section>
<!-- Feedback CTA -->
<section class="py-5 border-top border-secondary border-opacity-25 position-relative overflow-hidden">
  <div class="position-absolute top-0 start-50 translate-middle opacity-10 rounded-circle" style="width: 800px; height: 800px; filter: blur(100px); pointer-events: none;background-color: rgb(15, 56, 119) !important;"></div>
  
  <div class="container py-5 position-relative z-1">
    <div class="row align-items-center justify-content-center text-center">
      <div class="col-lg-8">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill border border-info border-opacity-25 bg-info bg-opacity-10 text-cyan font-mono small mb-4">
          <i class="bi bi-stars"></i> WorkEddy First-Year Launch
        </div>
        <h2 class="fw-bold text-white mb-4" style="font-size: 2.5rem; letter-spacing: -1px; font-family:'Outfit', sans-serif;">
          Build WorkEddy with us
        </h2>
        <p class="text-light opacity-75 fs-5 mb-5 px-md-4">
          Use WorkEddy free for your first year from launch. In return, share feedback that helps us improve the experience, shape new features, and build around real team needs.
        </p>
        <button type="button" class="btn btn-tech btn-lg px-5 font-mono text-uppercase shadow-sm" data-bs-toggle="modal" data-bs-target="#launchProgramModal">
          Share feedback
        </button>
      </div>
    </div>
  </div>
</section>

<!-- Launch Program Modal -->
<div class="modal fade" id="launchProgramModal" tabindex="-1" aria-labelledby="launchProgramModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-black border-secondary border-opacity-50 shadow-lg">
      <div class="modal-header border-bottom border-secondary border-opacity-25">
        <h5 class="modal-title font-mono fw-bold text-white" id="launchProgramModalLabel">
          <i class="bi bi-rocket-takeoff text-cyan me-2"></i> Launch Program
        </h5>
        <button type="button" class="btn-close btn-close-white opacity-50" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-light opacity-75">
        <h4 class="text-white mb-3 fw-bold font-mono" style="font-size: 1.1rem;">You are part of our first-year launch program</h4>
        <p class="mb-0 text-sm" style="line-height: 1.6;">
          Your team gets free access to WorkEddy for one year from launch. We ask for honest feedback in return so we can improve the platform based on real use, real challenges, and real priorities. Tell us what works well, what needs to change, and what would help your team most. Your input will help shape what comes next.
        </p>
      </div>
      <div class="modal-footer border-top border-secondary border-opacity-25">
        <button type="button" class="btn btn-outline-secondary font-mono text-uppercase" data-bs-dismiss="modal" style="font-size:0.8rem;">Maybe later</button>
        <button type="button" class="btn btn-tech font-mono text-uppercase" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#feedbackFormModal" style="font-size:0.8rem;">Share feedback</button>
      </div>
    </div>
  </div>
</div>

<!-- Feedback Form Modal -->
<div class="modal fade" id="feedbackFormModal" tabindex="-1" aria-labelledby="feedbackFormModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-black border-secondary border-opacity-50 shadow-lg">
      <div class="modal-header border-bottom border-secondary border-opacity-25">
        <h5 class="modal-title font-mono fw-bold text-white" id="feedbackFormModalLabel">
          <i class="bi bi-chat-square-text text-cyan me-2"></i> Share Feedback
        </h5>
        <button type="button" class="btn-close btn-close-white opacity-50" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 position-relative">
        <div class="tech-panel p-3 mb-4 rounded-3 border-info border-opacity-25 bg-info bg-opacity-10 position-relative overflow-hidden">
          <div class="position-absolute top-0 start-0 w-100 h-100 opacity-10 pointer-events-none" style="background-color: rgb(15, 56, 119) !important;"></div>
          <h6 class="text-white fw-bold mb-2 position-relative z-1 font-mono" style="font-size:0.9rem;">Thank you for helping shape WorkEddy.</h6>
          <p class="opacity-75 small mb-0 position-relative z-1" style="line-height: 1.5;color: rgb(199, 210, 212)">
            Your feedback helps us improve the platform around real tasks, real teams, and real workplace needs. We want to hear what is working, what feels unclear, what takes too long, and what features or changes would make WorkEddy more useful for you. Every response helps guide future updates.
          </p>
        </div>
        
        <form id="feedbackForm">
          <div id="feedbackAlert" class="d-none mb-3"></div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="feedbackName" class="form-label text-light small font-mono text-uppercase">Name (Optional)</label>
              <input type="text" class="form-control bg-dark text-white border-secondary" id="feedbackName" placeholder="Jane Doe">
            </div>
            <div class="col-md-6">
              <label for="feedbackEmail" class="form-label text-light small font-mono text-uppercase">Email (Optional)</label>
              <input type="email" class="form-control bg-dark text-white border-secondary" id="feedbackEmail" placeholder="jane@example.com">
            </div>
          </div>
          <div class="mb-3">
            <label for="feedbackType" class="form-label text-light small font-mono text-uppercase">Feedback Type</label>
            <select class="form-select bg-dark text-white border-secondary" id="feedbackType" required>
              <option value="" class="text-muted">Select a category...</option>
              <option value="improvement">Idea / Improvement</option>
              <option value="issue">Issue / Bug</option>
              <option value="feature">New Feature Request</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="mb-4">
            <label for="feedbackMessage" class="form-label text-light small font-mono text-uppercase">Your Feedback</label>
            <textarea class="form-control bg-dark text-white border-secondary" id="feedbackMessage" rows="5" placeholder="Tell us what works well, what needs to change, and what would help your team most..." required></textarea>
          </div>
          
          <div class="d-flex justify-content-end gap-2 border-top border-secondary border-opacity-25 pt-3">
            <button type="button" class="btn btn-outline-secondary font-mono text-uppercase" data-bs-dismiss="modal" style="font-size:0.8rem;">Cancel</button>
            <button type="submit" id="feedbackSubmitBtn" class="btn btn-tech font-mono text-uppercase" style="font-size:0.8rem;">
              <span id="feedbackBtnText">Submit Feedback <i class="bi bi-send ms-1"></i></span>
              <span id="feedbackBtnSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
            </button>
          </div>
        </form> 
    </div>
    </div>
  </div>
</div>

<!-- Footer -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
<script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const container = document.getElementById('three-pose-container');
                        if (!container) return;
                        
                        const scene = new THREE.Scene();
                        const camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.1, 100);
                        camera.position.set(2.5, 1.2, -2.5);

                        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
                        renderer.setSize(container.clientWidth, container.clientHeight);
                        renderer.setPixelRatio(window.devicePixelRatio);
                        container.appendChild(renderer.domElement);

                        const controls = new THREE.OrbitControls(camera, renderer.domElement);
                        controls.enableDamping = true;
                        controls.enableZoom = false;
                        controls.enablePan = false;
                        controls.autoRotate = true;
                        controls.autoRotateSpeed = 1.5;
                        controls.target.set(0, 0.8, 0);

                        // Colors corresponding to ergonomic risk
                        const safeColor = 0x22d3ee; // Cyan
                        const safeMat = new THREE.MeshBasicMaterial({ color: safeColor });
                        
                        const medRiskColor = 0xf59e0b; // Amber
                        const medRiskMat = new THREE.MeshBasicMaterial({ color: medRiskColor });
                        
                        const highRiskColor = 0xef4444; // Red
                        const highRiskMat = new THREE.MeshBasicMaterial({ color: highRiskColor });
                        
                        const boneMat = new THREE.LineBasicMaterial({ color: 0x10b981, transparent: true, opacity: 0.8 }); // Emerald
                        
                        // Joint Spheres
                        const joints = [];
                        function createJoint(x, y, z, mat=safeMat, size=0.06) {
                            const geo = new THREE.SphereGeometry(size, 16, 16);
                            const mesh = new THREE.Mesh(geo, mat);
                            mesh.position.set(x, y, z);
                            
                            mesh.userData.dangerPos = new THREE.Vector3(x, y, z);
                            mesh.userData.targetPos = new THREE.Vector3(x, y, z);
                            mesh.userData.safePos = new THREE.Vector3(x, y, z);
                            
                            scene.add(mesh);
                            joints.push(mesh);
                            return mesh;
                        }

                        // Coordinates for a person lifting a box improperly
                        const head = createJoint(0, 1.7, 0.2);
                        const neck = createJoint(0, 1.5, 0.1);
                        const shouldersC = createJoint(0, 1.45, 0.05);
                        
                        const shoulderL = createJoint(-0.25, 1.4, 0.05);
                        const shoulderR = createJoint(0.25, 1.4, 0.05);
                        
                        const elbowL = createJoint(-0.35, 1.0, 0.2, safeMat);
                        const elbowR = createJoint(0.35, 1.0, 0.2, safeMat);
                        
                        const wristL = createJoint(-0.2, 0.7, 0.4, safeMat);
                        const wristR = createJoint(0.2, 0.7, 0.4, safeMat);
                        
                        // Spine - curve showing stress
                        const spine1 = createJoint(0, 1.25, -0.1, medRiskMat);
                        const spine2 = createJoint(0, 1.05, -0.2, medRiskMat);
                        // Danger point!
                        const lumbar = createJoint(0, 0.85, -0.25, highRiskMat, 0.09); 
                        
                        const hipsC = createJoint(0, 0.75, -0.2);
                        const hipL = createJoint(-0.2, 0.75, -0.2);
                        const hipR = createJoint(0.2, 0.75, -0.2);
                        
                        const kneeL = createJoint(-0.2, 0.4, 0.1); // knees bent
                        const kneeR = createJoint(0.2, 0.4, 0.1);
                        
                        const ankleL = createJoint(-0.2, 0.05, -0.1);
                        const ankleR = createJoint(0.2, 0.05, -0.1);

                        // Set Safe Posture coordinates
                        head.userData.safePos.set(0, 1.5, 0.0);
                        neck.userData.safePos.set(0, 1.3, 0.0);
                        shouldersC.userData.safePos.set(0, 1.25, 0.0);
                        spine1.userData.safePos.set(0, 1.05, 0.0);
                        spine2.userData.safePos.set(0, 0.85, 0.0);
                        lumbar.userData.safePos.set(0, 0.65, 0.0);
                        hipsC.userData.safePos.set(0, 0.55, 0.0);
                        hipL.userData.safePos.set(-0.2, 0.55, 0.0);
                        hipR.userData.safePos.set(0.2, 0.55, 0.0);
                        kneeL.userData.safePos.set(-0.2, 0.3, 0.2);
                        kneeR.userData.safePos.set(0.2, 0.3, 0.2);
                        ankleL.userData.safePos.set(-0.2, 0.05, 0.0);
                        ankleR.userData.safePos.set(0.2, 0.05, 0.0);
                        shoulderL.userData.safePos.set(-0.25, 1.2, 0.0);
                        shoulderR.userData.safePos.set(0.25, 1.2, 0.0);
                        elbowL.userData.safePos.set(-0.35, 0.9, 0.1);
                        elbowR.userData.safePos.set(0.35, 0.9, 0.1);
                        wristL.userData.safePos.set(-0.2, 0.6, 0.2);
                        wristR.userData.safePos.set(0.2, 0.6, 0.2);

                        // Draw bones as lines connecting joints
                        const bones = [];
                        function createBone(m1, m2, mat=boneMat) {
                            const geo = new THREE.BufferGeometry().setFromPoints([m1.position, m2.position]);
                            const line = new THREE.Line(geo, mat);
                            line.userData.m1 = m1;
                            line.userData.m2 = m2;
                            scene.add(line);
                            bones.push(line);
                        }

                        // Connect bones
                        createBone(head, neck);
                        createBone(neck, shouldersC);
                        createBone(shouldersC, spine1);
                        
                        // Stress points colored differently
                        const medRiskBoneMat = new THREE.LineBasicMaterial({ color: medRiskColor });
                        const highRiskBoneMat = new THREE.LineBasicMaterial({ color: highRiskColor });
                        
                        createBone(spine1, spine2, medRiskBoneMat);
                        createBone(spine2, lumbar, highRiskBoneMat);
                        createBone(lumbar, hipsC, highRiskBoneMat);
                        
                        createBone(shouldersC, shoulderL);
                        createBone(shouldersC, shoulderR);
                        
                        createBone(shoulderL, elbowL);
                        createBone(elbowL, wristL);
                        
                        createBone(shoulderR, elbowR);
                        createBone(elbowR, wristR);
                        
                        createBone(hipsC, hipL);
                        createBone(hipsC, hipR);
                        
                        createBone(hipL, kneeL);
                        createBone(kneeL, ankleL);
                        
                        createBone(hipR, kneeR);
                        createBone(kneeR, ankleR);
                        
                        // Add a simple grid wireframe plane to represent floor
                        const gridGeo = new THREE.GridHelper(4, 20, 0x333333, 0x111111);
                        gridGeo.position.y = 0;
                        scene.add(gridGeo);

                        // Animation state
                        window.targetHighRiskColor = new THREE.Color(0xef4444);
                        window.targetMedRiskColor = new THREE.Color(0xf59e0b);
                        window.targetHighRiskBoneColor = new THREE.Color(0xef4444);
                        window.targetMedRiskBoneColor = new THREE.Color(0xf59e0b);

                        window.setThreeJSPosture = function(isSafe) {
                            joints.forEach(j => {
                                j.userData.targetPos = isSafe ? j.userData.safePos : j.userData.dangerPos;
                            });
                            window.targetHighRiskColor = isSafe ? new THREE.Color(0x22d3ee) : new THREE.Color(0xef4444);
                            window.targetMedRiskColor = isSafe ? new THREE.Color(0x22d3ee) : new THREE.Color(0xf59e0b);
                            window.targetHighRiskBoneColor = isSafe ? new THREE.Color(0x10b981) : new THREE.Color(0xef4444);
                            window.targetMedRiskBoneColor = isSafe ? new THREE.Color(0x10b981) : new THREE.Color(0xf59e0b);
                        };

                        function animate() {
                            requestAnimationFrame(animate);
                            
                            // Interpolate positions
                            joints.forEach(j => {
                                j.position.lerp(j.userData.targetPos, 0.05); // Smooth transition
                            });
                            
                            // Update bones geometries
                            bones.forEach(b => {
                                const positions = b.geometry.attributes.position.array;
                                positions[0] = b.userData.m1.position.x;
                                positions[1] = b.userData.m1.position.y;
                                positions[2] = b.userData.m1.position.z;
                                positions[3] = b.userData.m2.position.x;
                                positions[4] = b.userData.m2.position.y;
                                positions[5] = b.userData.m2.position.z;
                                b.geometry.attributes.position.needsUpdate = true;
                            });

                            // Interpolate colors
                            highRiskMat.color.lerp(window.targetHighRiskColor, 0.05);
                            medRiskMat.color.lerp(window.targetMedRiskColor, 0.05);
                            highRiskBoneMat.color.lerp(window.targetHighRiskBoneColor, 0.05);
                            medRiskBoneMat.color.lerp(window.targetMedRiskBoneColor, 0.05);

                            controls.update();
                            renderer.render(scene, camera);
                        }
                        animate();

                        // Handle resize
                        window.addEventListener('resize', () => {
                            if (!container) return;
                            camera.aspect = container.clientWidth / container.clientHeight;
                            camera.updateProjectionMatrix();
                            renderer.setSize(container.clientWidth, container.clientHeight);
                        });
                    });
                  </script>

<script>
  let currentContext = "WH";
  let activeTabElement = document.querySelector(".ind-tab.active");

  function switchContext(context, el) {
    currentContext = context;
    if (el) {
      document.querySelectorAll(".ind-tab").forEach(t => t.classList.remove("active"));
      el.classList.add("active");
      activeTabElement = el;
    }

    document.getElementById("beforeAfterToggle").checked = false;

    const area1 = document.getElementById("term-risk-area-1");
    const val1 = document.getElementById("term-risk-val-1");
    const area2 = document.getElementById("term-risk-area-2");
    const val2 = document.getElementById("term-risk-val-2");
    const area3 = document.getElementById("term-risk-area-3");
    const val3 = document.getElementById("term-risk-val-3");
    const action = document.getElementById("term-action");
    const poseOverlay = document.querySelector(".pose-overlay");

    val1.className = "text-danger font-mono";
    val2.className = "text-warning font-mono";
    val3.className = "text-success font-mono";
    if (poseOverlay) poseOverlay.style.filter = "none";
    if (window.setThreeJSPosture) window.setThreeJSPosture(false);

    if (context === "WH") {
      area1.innerText = "Lower Back";
      val1.innerHTML = "&gt; 60&deg; [HIGH]";
      area2.innerText = "Shoulder";
      val2.innerText = "42\u00B0 [MED]";
      area3.innerText = "Knee";
      val3.innerText = "OK [LOW]";
      action.innerText = "Reduce trunk flexion by raising pallet height";
    } else if (context === "LOG") {
      area1.innerText = "Arm Reach";
      val1.innerHTML = "&gt; 45cm [HIGH]";
      area2.innerText = "Lower Back";
      val2.innerText = "20\u00B0 [MED]";
      area3.innerText = "Neck";
      val3.innerText = "OK [LOW]";
      action.innerText = "Implement slide board for vehicle loading";
    } else if (context === "MFG") {
      area1.innerText = "Shoulder";
      val1.innerHTML = "&gt; 90\u00B0 [HIGH]";
      area2.innerText = "Wrist";
      val2.innerText = "15\u00B0 [MED]";
      area3.innerText = "Lower Back";
      val3.innerText = "OK [LOW]";
      action.innerText = "Adjust workstation height to elbow level";
    } else if (context === "HC") {
      area1.innerText = "Lumbar";
      val1.innerHTML = "High Shear [HIGH]";
      area2.innerText = "Shoulder";
      val2.innerText = "60\u00B0 [MED]";
      area3.innerText = "Neck";
      val3.innerText = "OK [LOW]";
      action.innerText = "Utilize mechanical lift for transfers";
    } else if (context === "CON") {
      area1.innerText = "Knee";
      val1.innerHTML = "Deep Flex [HIGH]";
      area2.innerText = "Lower Back";
      val2.innerText = "45\u00B0 [HIGH]";
      area3.innerText = "Shoulder";
      val3.innerText = "OK [LOW]";
      action.innerText = "Switch to rebar tying tool to reduce deep squat";
    }
  }

  document.getElementById("beforeAfterToggle").addEventListener("change", function(e) {
    if (e.target.checked) {
      document.getElementById("term-risk-val-1").className = "text-success font-mono";
      document.getElementById("term-risk-val-1").innerHTML = "OK [LOW]";
      document.getElementById("term-risk-val-2").className = "text-success font-mono";
      document.getElementById("term-risk-val-2").innerText = "OK [LOW]";
      document.getElementById("term-action").innerText = "[SYSTEM: Posture normalized. Range within safe bounds.]";
      const poseOverlay = document.querySelector(".pose-overlay");
      if (poseOverlay) poseOverlay.style.filter = "hue-rotate(150deg) brightness(1.2)";
      if (window.setThreeJSPosture) window.setThreeJSPosture(true);
    } else {
      switchContext(currentContext, activeTabElement);
    }
  });

  // initial setup
  if (document.querySelector(".ind-tab.active")) {
    switchContext("WH", document.querySelector(".ind-tab.active"));
  }
</script>
<?php include __DIR__ . '/../partials/site/v2_footer.php'; ?>