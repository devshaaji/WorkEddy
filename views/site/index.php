<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WorkEddy - Ergonomics Risk Assessment</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5 CSS -->
  <link href="/assets/css/core.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Global Styles (inherited base variables) -->
  <link href="/assets/css/app.css" rel="stylesheet">
  
  <style>
    /* Marketing specific styles overriding/extending app.css */
    :root {
      --hero-bg-accent: #f5f3ff; /* light purple */
    }
    body {
      background-color: #ffffff;
    }
    .marketing-nav {
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .landing-hero {
      padding: 120px 0 80px 0;
      background: linear-gradient(135deg, var(--hero-bg-accent) 0%, rgba(255,255,255,0) 100%);
      position: relative;
      overflow: hidden;
    }
    .landing-hero::before {
      content: '';
      position: absolute;
      top: -10%;
      right: -5%;
      width: 50vw;
      height: 50vw;
      background: radial-gradient(circle, rgba(124,58,237,0.1) 0%, rgba(255,255,255,0) 70%);
      border-radius: 50%;
      z-index: 0;
    }
    .hero-content {
      position: relative;
      z-index: 1;
    }
    .hero-title {
      font-family: 'Outfit', sans-serif;
      font-size: 3.0rem;
      font-weight: 800;
      line-height: 1.1;
      letter-spacing: -1px;
      color: #111827;
      margin-bottom: 1.5rem;
    }
    .hero-title span {
      background: var(--we-hero-gradient, linear-gradient(135deg, #7c3aed 0%, #4c1d95 100%));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .hero-subtitle {
      font-size: 1.1rem;
      color: #6b7280;
      margin-bottom: 2rem;
      max-width: 600px;
    }
    .feature-card {
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      background: #ffffff;
      border: 1px solid rgba(0,0,0,0.05);
    }
    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
    }
    .feature-icon-box {
      width: 64px;
      height: 64px;
      border-radius: 1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      margin-bottom: 1.5rem;
      background: var(--hero-bg-accent);
      color: var(--we-primary);
    }
    
    @media (max-width: 991px) {
      .hero-title { font-size: 2.5rem; }
      .landing-hero { padding: 100px 0 60px 0; text-align: center; }
      .hero-subtitle { margin: 0 auto 2rem; }
      .hero-buttons { justify-content: center; }
      .hero-image-col { margin-top: 3rem; }
      .micro-value-tags { justify-content: center; }
    }
    
    /* Mock UI frame for the hero */
    .mock-ui-frame {
      background: #fff;
      border-radius: 1.5rem;
      box-shadow: 0 25px 50px -12px rgba(124, 58, 237, 0.25);
      border: 1px solid rgba(124, 58, 237, 0.1);
      overflow: hidden;
      transform: perspective(1000px) rotateY(-5deg) rotateX(5deg);
      transition: transform 0.5s ease;
    }
    .mock-ui-frame:hover {
      transform: perspective(1000px) rotateY(0deg) rotateX(0deg);
    }
    .mock-ui-header {
      background: #f8fafc;
      padding: 1rem;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      gap: 0.5rem;
    }
    .mock-ui-dot { width: 12px; height: 12px; border-radius: 50%; }
    .mock-ui-body { background: #fafafa; }
    
    /* New Hero Additions */
    .industry-selector {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 2rem;
        padding: 0.25rem;
        display: inline-flex;
        gap: 0.25rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .industry-tab {
        padding: 0.5rem 1rem;
        border-radius: 1.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        color: #64748b;
        transition: all 0.2s;
    }
    .industry-tab.active {
        background: var(--hero-bg-accent);
        color: var(--we-primary, #7c3aed);
    }
    
    .pose-overlay-svg {
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        z-index: 10;
        pointer-events: none;
    }
    
    .micro-value-tags {
        display: flex;
        gap: 1rem;
        font-size: 0.85rem;
        font-weight: 500;
        color: #4b5563;
        flex-wrap: wrap;
    }
    .micro-value-tag {
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
  </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg fixed-top marketing-nav py-3">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="/">
        <img src="/assets/img/logo.png" alt="WorkEddy logo" class="auth-brand-logo flex-shrink-0" />
        <span class="auth-brand-name">WorkEddy</span>
      </a>
      <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#marketingNavbar">
        <i class="bi bi-list fs-2 text-dark"></i>
      </button>
      <div class="collapse navbar-collapse" id="marketingNavbar">
        <ul class="navbar-nav mx-auto mb-2 mb-lg-0 fw-medium">
          <li class="nav-item"><a class="nav-link px-3" href="#features">Features</a></li>
          <li class="nav-item"><a class="nav-link px-3" href="#how-it-works">How it Works</a></li>
          <li class="nav-item"><a class="nav-link px-3" href="#pricing">Pricing</a></li>
        </ul>
        <div class="d-flex gap-2 flex-column flex-lg-row mt-3 mt-lg-0">
          <a href="/login" class="btn btn-light rounded-pill px-4 fw-bold">Login</a>
          <a href="/register" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">Get Started</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="landing-hero">
    <div class="container">
      <div class="row align-items-center hero-content">
        <div class="col-lg-6">
          <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 mb-3 fw-bold border border-primary border-opacity-25">
            ✨ AI Ergonomics for MSD Prevention
          </div>
          <h1 class="hero-title">Prevent musculoskeletal injuries <span>before they happen.</span></h1>
          <p class="hero-subtitle">WorkEddy transforms everyday task videos into ergonomic risk scores, prioritized interventions, and reports your team can act on within minutes. Identify harmful movement sooner and guide safer work design.</p>
          
          <div class="d-flex gap-3 hero-buttons flex-wrap">
            <a href="/register" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow">Start for Free</a>
            <a href="#how-it-works" class="btn btn-outline-secondary btn-lg rounded-pill px-4 fw-bold bg-white border-2">View Sample Report</a>
          </div>
          
          <div class="micro-value-tags mt-4">
            <div class="micro-value-tag"><i class="bi bi-camera-video-fill text-primary opacity-75"></i> Video based analysis</div>
            <div class="micro-value-tag"><i class="bi bi-shield-lock-fill text-primary opacity-75"></i> Privacy first posture detection</div>
            <div class="micro-value-tag"><i class="bi bi-arrow-left-right text-primary opacity-75"></i> Before and after risk comparison</div>
          </div>
          
          <p class="small text-muted mt-3 mb-4"><i class="bi bi-shield-check text-success"></i> Built with secure access and privacy conscious task analysis.</p>

          <div class="mt-4 pt-4 border-top">
            <div class="d-flex align-items-center gap-4 flex-wrap opacity-50 mb-3 grayscale" style="filter: grayscale(100%);">
              <span class="fs-5 fw-bold font-monospace">AMAZON</span>
              <span class="fs-5 fw-bold font-monospace">DHL</span>
              <span class="fs-5 fw-bold font-monospace">FEDEX</span>
              <span class="fs-5 fw-bold font-monospace">TESCO</span>
            </div>
            <p class="small text-dark fw-medium mb-0"><strong>1,248 task scans completed.</strong> Used across warehousing, logistics, and manufacturing teams.</p>
          </div>
        </div>
        <div class="col-lg-6 hero-image-col">
          <div class="d-flex justify-content-center mb-3">
              <div class="industry-selector">
                <div class="industry-tab active" onclick="changeIndustry('Warehouse', this)">Warehouse</div>
                <div class="industry-tab" onclick="changeIndustry('Logistics', this)">Logistics</div>
                <div class="industry-tab" onclick="changeIndustry('Manufacturing', this)">Manufacturing</div>
                <div class="industry-tab" onclick="changeIndustry('Healthcare', this)">Healthcare</div>
                <div class="industry-tab" onclick="changeIndustry('Construction', this)">Construction</div>
              </div>
          </div>
          
          <!-- Stylized Dashboard Mockup -->
          <div class="mock-ui-frame">
            <div class="mock-ui-header">
              <div class="mock-ui-dot bg-danger"></div>
              <div class="mock-ui-dot bg-warning"></div>
              <div class="mock-ui-dot bg-success"></div>
            </div>
            <div class="mock-ui-body py-4 px-4">
              <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-2 fw-bold">
                  Potential reduction in high risk exposure: 32%
                </span>
                <div class="form-check form-switch m-0 border bg-white rounded-pill px-3 py-1 shadow-sm d-flex align-items-center gap-2 ps-5">
                  <input class="form-check-input ms-n4" type="checkbox" role="switch" id="beforeAfterToggle">
                  <label class="form-check-label small fw-bold mt-1" style="cursor:pointer;" for="beforeAfterToggle">Compare Before & After</label>
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-sm-6">
                  <div class="p-3 bg-white rounded-4 shadow-sm h-100 border">
                    <p class="small text-muted mb-2 fw-bold text-uppercase">Body Area Risk</p>
                    <div class="d-flex flex-column gap-2 mt-2">
                        <div class="d-flex justify-content-between align-items-center small"><span class="fw-medium">Lower Back</span> <span class="badge bg-danger rounded-pill px-2">High</span></div>
                        <div class="d-flex justify-content-between align-items-center small"><span class="fw-medium">Shoulder</span> <span class="badge bg-warning text-dark rounded-pill px-2">Med</span></div>
                        <div class="d-flex justify-content-between align-items-center small"><span class="fw-medium">Knee</span> <span class="badge bg-success rounded-pill px-2">Low</span></div>
                    </div>
                  </div>
                </div>
                <div class="col-sm-6">
                  <div class="p-3 bg-white rounded-4 shadow-sm border border-primary border-opacity-50 h-100 position-relative overflow-hidden">
                    <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary bg-opacity-10"></div>
                    <div class="position-relative z-1">
                      <p class="small text-primary mb-1 fw-bold text-uppercase"><i class="bi bi-lightning-charge-fill text-warning"></i> Priority Action</p>
                      <h6 class="fw-bold mb-2 lh-sm text-dark" id="mockup-action-title">Reduce trunk flexion by raising pallet height</h6>
                      <div class="d-inline-block bg-white rounded-pill px-2 py-1 small text-success fw-bold border border-success border-opacity-25 shadow-sm" id="mockup-action-desc"><i class="bi bi-arrow-down"></i> -3.2 Score Impact</div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="p-3 bg-white rounded-4 shadow-sm mb-0 position-relative border">
                <h6 class="fw-bold mb-3" id="mockup-task-title">Recent Analysis: Warehouse Lifting</h6>
                <div class="d-flex align-items-center gap-3">
                  <div class="bg-light rounded-3 p-2 position-relative overflow-hidden" style="width: 120px; height: 90px; background: url('https://images.unsplash.com/photo-1587293852726-00624066f76c?w=200&q=80') center/cover;" id="mockup-image">
                     <!-- Pose Overlay: Straight standing posture -->
                     <svg class="pose-overlay-svg" viewBox="0 0 120 90" fill="none" xmlns="http://www.w3.org/2000/svg">
                       <!-- Skeleton lines -->
                       <line x1="60" y1="18" x2="60" y2="48" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/><!-- spine -->
                       <line x1="60" y1="22" x2="45" y2="35" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/><!-- L-upper arm -->
                       <line x1="45" y1="35" x2="40" y2="50" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/><!-- L-lower arm -->
                       <line x1="60" y1="22" x2="75" y2="35" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/><!-- R-upper arm -->
                       <line x1="75" y1="35" x2="80" y2="50" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/><!-- R-lower arm -->
                       <line x1="60" y1="48" x2="50" y2="65" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/><!-- L-upper leg -->
                       <line x1="50" y1="65" x2="45" y2="82" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/><!-- L-lower leg -->
                       <line x1="60" y1="48" x2="70" y2="65" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/><!-- R-upper leg -->
                       <line x1="70" y1="65" x2="75" y2="82" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/><!-- R-lower leg -->
                       <!-- Joint dots -->
                       <circle cx="60" cy="12" r="5" fill="#10b981" opacity="0.9"/><!-- head -->
                       <circle cx="60" cy="22" r="3.5" fill="#f59e0b"/><!-- shoulder (amber) -->
                       <circle cx="60" cy="48" r="3.5" fill="#ef4444"/><!-- hip -->
                       <circle cx="50" cy="65" r="3.5" fill="#10b981"/><!-- L-knee (green) -->
                       <circle cx="70" cy="65" r="3.5" fill="#10b981"/><!-- R-knee (green) -->
                     </svg>
                     <div class="w-100 h-100 d-flex align-items-center justify-content-center position-relative z-1">
                        <i class="bi bi-play-circle-fill text-white fs-4 opacity-75 shadow-sm rounded-circle"></i>
                     </div>
                  </div>
                  <div>
                    <h2 class="mb-0 text-danger fw-black" style="font-weight: 800; letter-spacing: -1px;"><span id="mockup-score">7.8</span> <span class="badge bg-danger fs-6 align-middle ms-1 rounded-pill fw-bold">High Risk</span></h2>
                    <p class="small text-muted mb-0 fw-medium" id="mockup-insight">Trunk flexion > 60° detected.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- How it Works -->
  <section id="how-it-works" class="py-5">
    <div class="container py-5">
      <div class="row align-items-center">
        <div class="col-lg-6 mb-4 mb-lg-0 pe-lg-5">
          <h2 class="fw-bold mb-4" style="font-family:'Outfit',sans-serif;font-size:3rem; line-height: 1.1; letter-spacing: -1px;">See risk early and act faster in 3 steps</h2>
          <p class="text-muted fs-5 mb-3">Capture real work, detect ergonomic risk early, and get clear actions your team can use to prevent strain.</p>
          <p class="fw-bold text-primary mb-5"><i class="bi bi-clock-history"></i> From upload to risk report in minutes</p>
          
          <div class="d-flex mb-4">
            <div class="me-4"><div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-4 shadow" style="width:50px;height:50px;">1</div></div>
            <div>
              <h5 class="fw-bold">Capture the task</h5>
              <p class="text-muted">Record a real job task on any smartphone and upload it securely to WorkEddy.</p>
            </div>
          </div>
          
          <div class="d-flex mb-4">
            <div class="me-4"><div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-4 shadow" style="width:50px;height:50px;">2</div></div>
            <div>
              <h5 class="fw-bold">Detect posture risk</h5>
              <p class="text-muted">WorkEddy analyzes movement, body position, and strain exposure using trusted ergonomic methods such as REBA, RULA, and NIOSH.</p>
            </div>
          </div>
          
          <div class="d-flex mb-4">
            <div class="me-4"><div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-4 shadow" style="width:50px;height:50px;">3</div></div>
            <div>
              <h5 class="fw-bold">Fix what needs attention first</h5>
              <p class="text-muted">Get prioritized interventions, export reports, and compare tasks over time to see whether redesign efforts are reducing risk.</p>
            </div>
          </div>
          
          <div class="mt-4 pt-4 border-top">
             <p class="small text-muted mb-0 fw-medium d-flex align-items-center"><i class="bi bi-shield-check text-success me-2 fs-5"></i> Secure upload. Trusted ergonomic methods. Action ready outputs.</p>
          </div>
        </div>
        
        <div class="col-lg-6">
          <!-- Mock Product Proof Visual -->
          <div class="bg-white rounded-4 shadow-lg border p-4 position-relative mx-auto ms-lg-auto me-lg-0" style="max-width: 480px;">
             <!-- Top header -->
             <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold mb-0">Analysis Complete</h6>
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-3 py-1 fw-bold"><i class="bi bi-exclamation-triangle-fill"></i> 8.5 High Risk</span>
             </div>
             
             <!-- Video Frame with Pose -->
             <div class="rounded-4 position-relative overflow-hidden mb-4 border" style="height: 240px; background: url('https://images.unsplash.com/photo-1587293852726-00624066f76c?w=600&q=80') center/cover;">
                 <svg viewBox="0 0 120 90" fill="none" xmlns="http://www.w3.org/2000/svg" style="position: absolute; width:100%; height:100%; top:0; left:0;">
                   <!-- Straight standing posture -->
                   <line x1="60" y1="18" x2="60" y2="48" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
                   <line x1="60" y1="22" x2="45" y2="35" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
                   <line x1="45" y1="35" x2="40" y2="50" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
                   <line x1="60" y1="22" x2="75" y2="35" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
                   <line x1="75" y1="35" x2="80" y2="50" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
                   <line x1="60" y1="48" x2="50" y2="65" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
                   <line x1="50" y1="65" x2="45" y2="82" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
                   <line x1="60" y1="48" x2="70" y2="65" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
                   <line x1="70" y1="65" x2="75" y2="82" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
                   <circle cx="60" cy="12" r="5" fill="#10b981" opacity="0.9"/>
                   <circle cx="60" cy="22" r="3.5" fill="#f59e0b"/>
                   <circle cx="60" cy="48" r="3.5" fill="#ef4444"/>
                   <circle cx="50" cy="65" r="3.5" fill="#10b981"/>
                   <circle cx="70" cy="65" r="3.5" fill="#10b981"/>
                 </svg>
             </div>
             
             <!-- Body Region Map/Heatmap -->
             <div class="row g-3 mb-4">
                <div class="col-6">
                   <div class="p-3 bg-light rounded-3 border">
                     <div class="d-flex justify-content-between small mb-2 fw-bold"><span class="text-dark">Lower Back</span> <span class="text-danger">High Risk</span></div>
                     <div class="progress shadow-sm" style="height: 6px;"><div class="progress-bar bg-danger rounded-pill" style="width: 85%"></div></div>
                   </div>
                </div>
                <div class="col-6">
                   <div class="p-3 bg-light rounded-3 border">
                     <div class="d-flex justify-content-between small mb-2 fw-bold"><span class="text-dark">Shoulder</span> <span class="text-warning text-dark">Medium</span></div>
                     <div class="progress shadow-sm" style="height: 6px;"><div class="progress-bar bg-warning rounded-pill" style="width: 55%"></div></div>
                   </div>
                </div>
             </div>
             
             <!-- Recommended Action -->
             <div class="p-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 d-flex align-items-center gap-3">
                <div class="bg-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width:40px;height:40px; font-size: 1.25rem;">
                  <i class="bi bi-lightning-charge-fill text-warning"></i>
                </div>
                <div>
                   <p class="small text-primary mb-1 fw-bold text-uppercase">Priority Action</p>
                   <p class="mb-0 fw-bold lh-sm text-dark small">Reduce trunk flexion by raising pallet height</p>
                </div>
             </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Features Section -->
  <section id="features" class="py-5 bg-light border-top border-bottom">
    <div class="container py-5">
      <div class="text-center mb-5 max-w-2xl mx-auto">
        <h2 class="fw-bold" style="font-family:'Outfit'">Everything you need to detect risk early and prevent strain</h2>
        <p class="text-muted fs-5">Replace manual guesswork with video based ergonomic analysis, trusted scoring, and action ready insights that help teams reduce musculoskeletal risk.</p>
      </div>
      
      <div class="row g-4">
        <!-- Card 1: Capture -->
        <div class="col-md-6 col-lg-3">
          <div class="surface-card feature-card p-4 rounded-4 h-100 d-flex flex-column">
            <!-- Visual Proof: mini pose overlay -->
            <div class="bg-white rounded-3 p-3 mb-4 d-flex align-items-center justify-content-center position-relative overflow-hidden" style="height: 140px; border: 1px solid rgba(0,0,0,0.05); background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);">
              <svg viewBox="0 0 100 100" class="w-100 h-100 opacity-75">
                <line x1="50" y1="20" x2="50" y2="50" stroke="#7c3aed" stroke-width="4" stroke-linecap="round"/>
                <line x1="50" y1="25" x2="35" y2="40" stroke="#7c3aed" stroke-width="4" stroke-linecap="round"/>
                <line x1="50" y1="25" x2="65" y2="40" stroke="#7c3aed" stroke-width="4" stroke-linecap="round"/>
                <line x1="50" y1="50" x2="40" y2="75" stroke="#7c3aed" stroke-width="4" stroke-linecap="round"/>
                <line x1="50" y1="50" x2="60" y2="75" stroke="#7c3aed" stroke-width="4" stroke-linecap="round"/>
                <circle cx="50" cy="12" r="7" fill="#7c3aed"/>
                <circle cx="50" cy="45" r="5" fill="#ef4444"/> <!-- hotspot -->
                <circle cx="40" cy="75" r="4" fill="#f59e0b"/> <!-- knee hotspot -->
                
                <!-- scan line effect -->
                <line x1="20" y1="35" x2="80" y2="35" stroke="#10b981" stroke-width="1.5" stroke-dasharray="2,2"/>
              </svg>
            </div>
            <h5 class="fw-bold mb-3">See risk from real task video</h5>
            <p class="text-muted small mb-0 mt-auto">Upload a task video and let WorkEddy detect posture risk, joint movement, and strain exposure in minutes.</p>
          </div>
        </div>
        
        <!-- Card 2: Score -->
        <div class="col-md-6 col-lg-3">
          <div class="surface-card feature-card p-4 rounded-4 h-100 d-flex flex-column">
            <!-- Visual Proof: tiny REBA/RULA score -->
            <div class="bg-white rounded-3 p-3 mb-4 d-flex flex-column align-items-center justify-content-center gap-3" style="height: 140px; border: 1px solid rgba(0,0,0,0.05); background: linear-gradient(180deg, #fff7ed 0%, #fff 100%);">
              <div class="d-flex w-100 justify-content-between align-items-center px-1 border-bottom pb-2">
                <span class="fw-bold text-muted small"><i class="bi bi-file-earmark-bar-graph me-1"></i>REBA</span>
                <span class="badge bg-danger rounded-pill shadow-sm">8.5 High</span>
              </div>
              <div class="d-flex w-100 justify-content-between align-items-center px-1">
                <span class="fw-bold text-muted small"><i class="bi bi-file-earmark-bar-graph me-1"></i>RULA</span>
                <span class="badge bg-warning text-dark rounded-pill shadow-sm">6.0 Med</span>
              </div>
            </div>
            <h5 class="fw-bold mb-3">Score with trusted methods</h5>
            <p class="text-muted small mb-0 mt-auto">Map findings to recognized ergonomic frameworks such as REBA, RULA, and NIOSH for more consistent review.</p>
          </div>
        </div>
        
        <!-- Card 3: Act -->
        <div class="col-md-6 col-lg-3">
          <div class="surface-card feature-card p-4 rounded-4 h-100 d-flex flex-column">
            <!-- Visual Proof: mini heatmap -->
            <div class="bg-white rounded-3 p-3 mb-4 d-flex align-items-center justify-content-center flex-column gap-3" style="height: 140px; border: 1px solid rgba(0,0,0,0.05); background: linear-gradient(180deg, #fef2f2 0%, #fff 100%);">
              <div class="w-100">
                <div class="d-flex justify-content-between small mb-1 fw-bold"><span class="text-dark" style="font-size: 0.75rem;">Lower Back</span> <span class="text-danger" style="font-size: 0.75rem;">High</span></div>
                <div class="progress shadow-sm" style="height: 8px;">
                  <div class="progress-bar bg-danger rounded-pill" style="width: 85%"></div>
                </div>
              </div>
              <div class="w-100">
                <div class="d-flex justify-content-between small mb-1 fw-bold"><span class="text-dark" style="font-size: 0.75rem;">Shoulder</span> <span class="text-warning text-dark" style="font-size: 0.75rem;">Med</span></div>
                <div class="progress shadow-sm" style="height: 8px;">
                  <div class="progress-bar bg-warning rounded-pill" style="width: 55%"></div>
                </div>
              </div>
            </div>
            <h5 class="fw-bold mb-3">Know what to fix first</h5>
            <p class="text-muted small mb-0 mt-auto">Highlight the tasks, body regions, and movement patterns that deserve immediate intervention.</p>
          </div>
        </div>
        
        <!-- Card 4: Prove -->
        <div class="col-md-6 col-lg-3">
          <div class="surface-card feature-card p-4 rounded-4 h-100 d-flex flex-column">
            <!-- Visual Proof: before & after chip -->
            <div class="bg-white rounded-3 p-3 mb-4 d-flex flex-column align-items-center justify-content-center gap-2" style="height: 140px; border: 1px solid rgba(0,0,0,0.05); background: linear-gradient(180deg, #f0fdf4 0%, #fff 100%);">
               <div class="d-flex align-items-center gap-1 w-100 justify-content-center mb-1">
                 <div class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-2 py-1">Before: 8.2</div>
                 <i class="bi bi-chevron-right text-muted small"></i>
                 <div class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-1">After: 3.4</div>
               </div>
               <div class="d-inline-flex border border-success border-opacity-25 bg-success bg-opacity-10 rounded-pill px-3 py-2 mt-2">
                 <span class="small fw-bold text-success m-0"><i class="bi bi-graph-down-arrow me-1"></i> 58% Risk Reduction</span>
               </div>
            </div>
            <h5 class="fw-bold mb-3">Prove improvement over time</h5>
            <p class="text-muted small mb-0 mt-auto">Compare tasks before and after changes, track exposure trends, and show whether redesign efforts are reducing risk.</p>
          </div>
        </div>
        
      </div>
    </div>
  </section>

  <!-- About Us -->
  <section id="about-us" class="py-5 bg-white">
    <div class="container py-5">
      <div class="row align-items-center">
        <div class="col-lg-6 mb-5 mb-lg-0 pe-lg-5">
          <h2 class="fw-bold mb-4" style="font-family:'Outfit',sans-serif;font-size:2.75rem; line-height: 1.1; letter-spacing: -1px;">About Us</h2>
          <p class="fs-5 text-dark fw-medium mb-4">WorkEddy is an AI ergonomics platform built to help organizations prevent musculoskeletal disorders by providing earlier risk visibility, faster intervention, and stronger evidence of improvement over time.</p>
          <p class="text-muted mb-4">The platform transforms everyday task videos into ergonomic risk scores, prioritized intervention guidance, and reports that teams can act on within minutes. Rather than positioning ergonomic assessment as a slow, manual process, WorkEddy is designed to help safety, operations, and workplace health teams identify harmful movement early, understand exposure by task and body region, and focus attention where risk is highest.</p>
          <p class="text-muted mb-0">WorkEddy is built around the workflow buyers care about most. Capture the task. Detect posture risk using trusted ergonomic methods. Fix what needs attention first. Then compare results over time to see whether changes are reducing exposure. That structure supports assessment and prevention decision-making across teams and sites.</p>
        </div>
        <div class="col-lg-5 offset-lg-1 mt-5 mt-lg-0">
          <div class="bg-white rounded-4 p-4 p-lg-5 border shadow-sm h-100 d-flex flex-column">
            
            <div class="flex-grow-1 d-flex justify-content-center align-items-center py-5">
              <img src="/assets/img/WorkEddy Main Logo.png" alt="WorkEddy Logo" class="img-fluid" style="max-width: 280px; filter: drop-shadow(0 10px 15px rgba(0,0,0,0.03));">
            </div>
            
            <div class="d-flex align-items-center gap-3 pt-4 border-top mt-auto">
              <div class="bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 54px; height: 54px;">
                <i class="bi bi-shield-check fs-3"></i>
              </div>
              <div>
                <p class="mb-1 fw-bold text-dark lh-sm">Moving from delayed review</p>
                <p class="mb-0 small text-muted">To earlier action and better prevention outcomes.</p>
              </div>
            </div>
            
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Founder Note -->
  <section id="founder-note" class="py-5 bg-light border-top border-bottom">
    <div class="container py-5">
      <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
          <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 mb-4 fw-bold border border-primary border-opacity-25 mx-auto d-table">
             From the Founder
          </div>
          <h2 class="fw-bold mb-5 text-center" style="font-family:'Outfit',sans-serif;font-size:2.5rem; letter-spacing: -0.5px;">Why we built WorkEddy</h2>
          
          <div class="row align-items-center">
             <div class="col-md-5 col-lg-4 mb-4 mb-md-0 d-flex flex-column align-items-center text-center text-md-start align-items-md-start">
                <img src="/assets/img/Founder's Why we built WorkEddy Phote.png" alt="Treasure Nkemdilim James" class="img-fluid rounded-circle mb-3 shadow-sm border" style="width: 180px; height: 180px; object-fit: cover;">
                <h5 class="fw-bold mb-1 text-dark" style="font-family:'Outfit',sans-serif;">Treasure Nkemdilim James</h5>
                <p class="small text-muted mb-0">MS, MSISD, MOSH, PCQI</p>
                <p class="small fw-bold text-primary mb-3">Founder and Product Lead</p>
                <div class="d-inline-flex bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-3 px-3 py-2">
                   <div class="d-flex align-items-center gap-2 text-start">
                     <i class="bi bi-patch-check-fill fs-5"></i>
                     <span class="fw-bold lh-sm text-success" style="font-size: 0.7rem; text-transform: uppercase;">IRCA Certified ISO 45001<br>Lead Auditor</span>
                   </div>
                </div>
             </div>
             
             <div class="col-md-7 col-lg-8 ps-md-4 ps-lg-5">
                <div class="fs-5 text-dark fw-medium lh-base mb-4 position-relative pb-2" style="border-bottom: 2px dashed rgba(0,0,0,0.05);">
                   <i class="bi bi-quote position-absolute text-primary opacity-25" style="font-size: 4rem; top: -1.75rem; left: -1.5rem; z-index: 0;"></i>
                   <p class="position-relative" style="z-index: 1;">"We started WorkEddy because teams often wait too long for clear answers, and by then, strain has become injury.</p>
                   <p class="position-relative" style="z-index: 1;">For too long, ergonomic reviews have been slow, manual, and difficult to scale across work environments. Safety teams are doing serious work, yet many still have to rely on scattered observations, delayed assessments, and tools that identify risk without clearly showing what should happen next. I believed there had to be a better way.</p>
                   <p class="position-relative mb-0 fw-bold" style="z-index: 1;">That belief is what led me to build WorkEddy."</p>
                </div>
                
                <a href="/founder-story" class="btn btn-outline-dark fw-bold rounded-pill mt-3 px-4 shadow-sm">
                  Read the full story <i class="bi bi-chevron-right ms-1"></i>
                </a>
             </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Pricing -->
  <section id="pricing" class="py-5 bg-white">
    <div class="container py-5">
      <div class="text-center mb-5 max-w-2xl mx-auto" style="max-width: 800px;">
        <h2 class="fw-bold" style="font-family:'Outfit'">Start free. Scale when your team needs more visibility and action.</h2>
        <p class="text-muted fs-5">Choose the plan that fits your ergonomic risk workflow today, then upgrade as your team needs more assessments, reporting, and prevention insight.</p>
      </div>
      
      <div class="row justify-content-center g-4">
        <!-- Free Plan -->
        <div class="col-md-6 col-lg-4">
          <div class="surface-card feature-card p-5 rounded-4 h-100 text-center border-0 shadow-sm bg-white d-flex flex-column">
            <h4 class="fw-bold mb-3">Free</h4>
            <h2 class="display-4 fw-bold mb-2">$0<span class="fs-5 text-muted fw-normal">/mo</span></h2>
            <p class="text-muted mb-4 small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Best for early pilots and smaller teams</p>
            
            <ul class="list-unstyled text-start mb-4 mt-auto">
              <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> 10 completed assessments each month</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Manual and video assessments</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> 1 organization</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Core reporting</li>
            </ul>
            <a href="/register" class="btn btn-light border btn-lg w-100 rounded-pill fw-bold mt-auto">Start Free</a>
          </div>
        </div>
        
        <!-- Professional Plan -->
        <div class="col-md-6 col-lg-4">
          <div class="surface-card feature-card p-5 rounded-4 h-100 text-center position-relative border-0 shadow-lg text-white d-flex flex-column" style="background:var(--we-hero-gradient, linear-gradient(135deg, #7c3aed 0%, #4c1d95 100%));">
            <div class="position-absolute top-0 start-50 translate-middle badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold shadow-sm" style="white-space:nowrap; letter-spacing: 0.5px; font-size: 0.75rem;">MOST CHOSEN BY MULTI-SITE TEAMS</div>
            <h4 class="fw-bold mb-3 mt-2">Professional</h4>
            <h2 class="display-4 fw-bold mb-2">$99<span class="fs-5 text-white-50 fw-normal">/mo</span></h2>
            <p class="text-white-50 mb-4 small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Best for growing safety and operations teams</p>
            
            <ul class="list-unstyled text-start mb-4 mt-auto">
              <li class="mb-3"><i class="bi bi-check-circle-fill text-warning me-2"></i> 500 completed assessments each month</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-warning me-2"></i> Unlimited team members</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-warning me-2"></i> Exportable reports</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-warning me-2"></i> Dashboard visibility</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-warning me-2"></i> Prioritized intervention insights</li>
            </ul>
            <a href="/register" class="btn btn-light btn-lg w-100 rounded-pill fw-bold text-primary mt-auto shadow-sm">Start Professional Trial</a>
          </div>
        </div>
        
        <!-- Enterprise Plan -->
        <div class="col-md-6 col-lg-4">
          <div class="surface-card feature-card p-5 rounded-4 h-100 text-center border-0 shadow-sm bg-white d-flex flex-column">
            <h4 class="fw-bold mb-3">Enterprise</h4>
            <h2 class="display-8 fw-bold mb-2 d-flex align-items-center justify-content-center" style="height: 72px;">Custom pricing</h2>
            <p class="text-muted mb-4 small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Best for multi-site and large scale programs</p>
            
            <ul class="list-unstyled text-start mb-4 mt-auto">
              <li class="mb-3"><i class="bi bi-check-circle-fill text-dark me-2"></i> Custom usage limits</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-dark me-2"></i> Advanced admin controls</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-dark me-2"></i> Expanded analytics</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-dark me-2"></i> Onboarding and procurement support</li>
            </ul>
            <a href="/contact" class="btn btn-outline-dark border-2 btn-lg w-100 rounded-pill fw-bold mt-auto">Contact Sales</a>
          </div>
        </div>
      </div>
      
      <!-- Trust Reassurance Band -->
      <div class="d-flex flex-wrap justify-content-center gap-4 mt-5 text-muted small fw-bold">
         <span class="d-flex align-items-center"><i class="bi bi-credit-card-2-front-fill fs-5 text-secondary me-2"></i> No credit card required</span>
         <span class="d-none d-md-inline opacity-50">&bull;</span>
         <span class="d-flex align-items-center"><i class="bi bi-check-all fs-4 text-success me-2"></i> Built on REBA, RULA, and NIOSH aligned scoring</span>
         <span class="d-none d-md-inline opacity-50">&bull;</span>
         <span class="d-flex align-items-center"><i class="bi bi-shield-lock-fill fs-5 text-secondary me-2"></i> Secure task upload and exportable reporting</span>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-dark text-white py-5">
    <div class="container py-4">
      <div class="row g-4">
        <div class="col-lg-4">
          <h4 class="fw-bold mb-3"><img src="/assets/img/logo.png" alt="WorkEddy logo" class="auth-brand-logo" /> WorkEddy</h4>
          <p class="text-white-50 pe-lg-5">Automating ergonomic risk assessments to protect your workforce and streamline compliance.</p>
        </div>
        <div class="col-6 col-lg-2 offset-lg-2">
          <h6 class="fw-bold text-uppercase mb-3">Product</h6>
          <ul class="list-unstyled text-white-50">
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Features</a></li>
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Pricing</a></li>
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Enterprise</a></li>
          </ul>
        </div>
        <div class="col-6 col-lg-2">
          <h6 class="fw-bold text-uppercase mb-3">Company</h6>
          <ul class="list-unstyled text-white-50">
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">About Us</a></li>
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Careers</a></li>
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Contact</a></li>
          </ul>
        </div>
        <div class="col-6 col-lg-2">
          <h6 class="fw-bold text-uppercase mb-3">Legal</h6>
          <ul class="list-unstyled text-white-50">
            <li class="mb-2"><a href="/privacy-policy" class="text-white-50 text-decoration-none">Privacy Policy</a></li>
            <li class="mb-2"><a href="/terms-of-service" class="text-white-50 text-decoration-none">Terms of Service</a></li>
          </ul>
        </div>
      </div>
      <div class="border-top border-secondary mt-5 pt-4 text-center text-white-50 small">
        &copy; <?= date('Y') ?> WorkEddy, Inc. All rights reserved.
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function changeIndustry(industry, element) {
      document.querySelectorAll('.industry-tab').forEach(t => t.classList.remove('active'));
      element.classList.add('active');
      
      const title = document.getElementById('mockup-task-title');
      const action = document.getElementById('mockup-action-title');
      const insight = document.getElementById('mockup-insight');
      const score = document.getElementById('mockup-score');
      const actionDesc = document.getElementById('mockup-action-desc');
      
      if(industry === 'Warehouse') {
        title.innerText = 'Recent Analysis: Warehouse Lifting';
        action.innerText = 'Reduce trunk flexion by raising pallet height';
        insight.innerText = 'Trunk flexion > 60° detected.';
        score.innerText = '7.8';
        actionDesc.innerHTML = '<i class="bi bi-arrow-down"></i> -3.2 Score Impact';
      } else if(industry === 'Logistics') {
        title.innerText = 'Recent Analysis: Package Loading';
        action.innerText = 'Implement slide board for vehicle loading';
        insight.innerText = 'Extended reach > 45cm detected.';
        score.innerText = '6.5';
        actionDesc.innerHTML = '<i class="bi bi-arrow-down"></i> -2.4 Score Impact';
      } else if(industry === 'Manufacturing') {
        title.innerText = 'Recent Analysis: Assembly Line';
        action.innerText = 'Adjust workstation height to elbow level';
        insight.innerText = 'Prolonged shoulder abduction detected.';
        score.innerText = '8.1';
        actionDesc.innerHTML = '<i class="bi bi-arrow-down"></i> -4.0 Score Impact';
      } else if(industry === 'Healthcare') {
        title.innerText = 'Recent Analysis: Patient Transfer';
        action.innerText = 'Utilize mechanical lift for transfers';
        insight.innerText = 'High lumbar shear forces detected.';
        score.innerText = '9.2';
        actionDesc.innerHTML = '<i class="bi bi-arrow-down"></i> -5.1 Score Impact';
      } else if(industry === 'Construction') {
        title.innerText = 'Recent Analysis: Rebar Tying';
        action.innerText = 'Switch to rebar tying tool to reduce deep squat';
        insight.innerText = 'Sustained deep knee flexion detected.';
        score.innerText = '8.4';
        actionDesc.innerHTML = '<i class="bi bi-arrow-down"></i> -3.8 Score Impact';
      }
      
      // Reset toggle
      document.getElementById('beforeAfterToggle').checked = false;
      document.getElementById('mockup-score').nextElementSibling.className = 'badge bg-danger fs-6 align-middle ms-1 rounded-pill fw-bold';
      document.getElementById('mockup-score').nextElementSibling.innerText = 'High Risk';
      document.querySelector('.pose-overlay-svg').style.filter = 'none';
    }

    document.getElementById('beforeAfterToggle').addEventListener('change', function(e) {
        const isChecked = e.target.checked;
        const score = document.getElementById('mockup-score');
        const badge = score.nextElementSibling;
        const insight = document.getElementById('mockup-insight');
        const poseOverlay = document.querySelector('.pose-overlay-svg');
        
        if (isChecked) {
            let currentScore = parseFloat(score.innerText);
            score.innerText = (currentScore - 3.2).toFixed(1);
            badge.className = 'badge bg-success fs-6 align-middle ms-1 rounded-pill fw-bold';
            badge.innerText = 'Low Risk';
            insight.innerText = 'Task redesigned: Posture improved significantly.';
            poseOverlay.style.filter = 'hue-rotate(180deg)';
        } else {
            // Re-trigger the active industry to restore default text
            const activeTab = document.querySelector('.industry-tab.active');
            changeIndustry(activeTab.innerText, activeTab);
        }
    });
  </script>
</body>
</html>
