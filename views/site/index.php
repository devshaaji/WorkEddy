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
      font-size: 3.5rem;
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
      font-size: 1.25rem;
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
    .mock-ui-body { padding: 1.5rem; background: #fafafa; }
  </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg fixed-top marketing-nav py-3">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="/">
        <div class="auth-brand-logo flex-shrink-0"><i class="bi bi-activity"></i></div>
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
            ✨ The Future of Occupational Health
          </div>
          <h1 class="hero-title">Automate Ergonomic <br><span>Risk Assessments.</span></h1>
          <p class="hero-subtitle">Protect your workforce and stay compliant. Upload a video of any task and let our AI instantly analyze posture, detect risks, and generate actionable insights.</p>
          <div class="d-flex gap-3 hero-buttons flex-wrap">
            <a href="/register" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow">Start for Free</a>
            <a href="#how-it-works" class="btn btn-outline-secondary btn-lg rounded-pill px-4 fw-bold bg-white">See Demo</a>
          </div>
          
          <div class="d-flex align-items-center gap-4 mt-5">
            <div class="d-flex -space-x-2">
              <img src="https://i.pravatar.cc/100?img=1" class="rounded-circle border border-2 border-white" width="40" height="40" alt="User">
              <img src="https://i.pravatar.cc/100?img=2" class="rounded-circle border border-2 border-white ms-n2" width="40" height="40" alt="User">
              <img src="https://i.pravatar.cc/100?img=3" class="rounded-circle border border-2 border-white ms-n2" width="40" height="40" alt="User">
              <div class="rounded-circle border border-2 border-white ms-n2 bg-light d-flex align-items-center justify-content-center fw-bold small text-muted" style="width:40px;height:40px;z-index:4;">+5k</div>
            </div>
            <p class="small text-muted mb-0 fw-medium">Trusted by safety managers<br>at 500+ enterprises.</p>
          </div>
        </div>
        <div class="col-lg-6 hero-image-col">
          <!-- Stylized Dashboard Mockup -->
          <div class="mock-ui-frame">
            <div class="mock-ui-header">
              <div class="mock-ui-dot bg-danger"></div>
              <div class="mock-ui-dot bg-warning"></div>
              <div class="mock-ui-dot bg-success"></div>
            </div>
            <div class="mock-ui-body">
              <div class="row g-3 mb-3">
                <div class="col-6">
                  <div class="p-3 bg-white rounded-4 shadow-sm">
                    <p class="small text-muted mb-1 fw-bold text-uppercase">High Risk Tasks</p>
                    <h3 class="mb-0 text-danger fw-bold">12</h3>
                  </div>
                </div>
                <div class="col-6">
                  <div class="p-3 bg-white rounded-4 shadow-sm">
                    <p class="small text-muted mb-1 fw-bold text-uppercase">Total Scans</p>
                    <h3 class="mb-0 text-primary fw-bold">1,248</h3>
                  </div>
                </div>
              </div>
              <div class="p-3 bg-white rounded-4 shadow-sm mb-3 position-relative overflow-hidden">
                <h6 class="fw-bold mb-3">Recent Analysis: Warehouse Lifting</h6>
                <div class="d-flex align-items-center gap-3">
                  <div class="bg-light rounded p-2" style="width: 100px; height: 75px; background: url('https://images.unsplash.com/photo-1587293852726-00624066f76c?w=200&q=80') center/cover;">
                     <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                        <i class="bi bi-play-circle-fill text-white fs-4 opacity-75"></i>
                     </div>
                  </div>
                  <div>
                    <h3 class="mb-0 text-danger fw-bold">7.8 <span class="badge bg-danger fs-6 align-middle ms-2">High Risk</span></h3>
                    <p class="small text-muted mb-0">Trunk flexion > 60° detected.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Features Section -->
  <section id="features" class="py-5 bg-light mt-5">
    <div class="container py-5">
      <div class="text-center mb-5 max-w-md mx-auto">
        <h2 class="fw-bold" style="font-family:'Outfit'">Everything you need to ensure workplace safety</h2>
        <p class="text-muted fs-5">Eliminate manual forms and guesswork with our cutting-edge AI platform.</p>
      </div>
      
      <div class="row g-4">
        <div class="col-md-4">
          <div class="surface-card feature-card p-4 p-lg-5 rounded-4 h-100">
            <div class="feature-icon-box"><i class="bi bi-camera-video-fill"></i></div>
            <h4 class="fw-bold mb-3">Video AI Analysis</h4>
            <p class="text-muted mb-0">Simply record a worker performing a task. Our computer vision engine extracts joint angles, posture, and risk factors automatically.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="surface-card feature-card p-4 p-lg-5 rounded-4 h-100">
            <div class="feature-icon-box" style="background:#fff7ed;color:#ea580c;"><i class="bi bi-file-earmark-medical-fill"></i></div>
            <h4 class="fw-bold mb-3">Standardized Scoring</h4>
            <p class="text-muted mb-0">Results are mapped to internationally recognized ergonomic standards like REBA, RULA, and NIOSH lifting equations.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="surface-card feature-card p-4 p-lg-5 rounded-4 h-100">
            <div class="feature-icon-box" style="background:#f0fdf4;color:#16a34a;"><i class="bi bi-bar-chart-fill"></i></div>
            <h4 class="fw-bold mb-3">Enterprise Dashboard</h4>
            <p class="text-muted mb-0">Track risk across departments and locations. Export compliance reports and prioritize interventions where they matter most.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- How it Works -->
  <section id="how-it-works" class="py-5">
    <div class="container py-5">
      <div class="row align-items-center">
        <div class="col-lg-6 mb-4 mb-lg-0">
          <h2 class="fw-bold mb-4" style="font-family:'Outfit',sans-serif;font-size:3rem;">Assess risks in 3 simple steps</h2>
          
          <div class="d-flex mb-4">
            <div class="me-4"><div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-4 shadow" style="width:50px;height:50px;">1</div></div>
            <div>
              <h5 class="fw-bold">Upload Video</h5>
              <p class="text-muted">Record normal working tasks using any smartphone and upload securely to the WorkEddy cloud.</p>
            </div>
          </div>
          
          <div class="d-flex mb-4">
            <div class="me-4"><div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-4 shadow" style="width:50px;height:50px;">2</div></div>
            <div>
              <h5 class="fw-bold">AI Processing</h5>
              <p class="text-muted">Our ML engines process the video frame-by-frame, mapping skeletal structure and joint angles in real-time.</p>
            </div>
          </div>
          
          <div class="d-flex">
            <div class="me-4"><div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-4 shadow" style="width:50px;height:50px;">3</div></div>
            <div>
              <h5 class="fw-bold">Get Instant Reports</h5>
              <p class="text-muted">Receive a detailed breakdown of risk factors, final scores, and recommended interventions instantly.</p>
            </div>
          </div>
        </div>
        <div class="col-lg-6 text-center">
          <img src="https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=800&q=80" alt="Worker in warehouse" class="img-fluid rounded-4 shadow-lg" style="object-fit:cover; height: 500px; width:100%;" />
        </div>
      </div>
    </div>
  </section>

  <!-- Pricing -->
  <section id="pricing" class="py-5 bg-light border-top">
    <div class="container py-5">
      <div class="text-center mb-5 max-w-md mx-auto">
        <h2 class="fw-bold" style="font-family:'Outfit'">Simple, transparent pricing</h2>
        <p class="text-muted fs-5">Start for free, upgrade when you need higher volume.</p>
      </div>
      
      <div class="row justify-content-center g-4">
        <div class="col-md-5 col-lg-4">
          <div class="surface-card feature-card p-5 rounded-4 h-100 text-center border-0 shadow-sm">
            <h4 class="fw-bold mb-3">Starter</h4>
            <h2 class="display-4 fw-bold mb-2">$0<span class="fs-5 text-muted fw-normal">/mo</span></h2>
            <p class="text-muted mb-4">Perfect for trying out the platform.</p>
            
            <ul class="list-unstyled text-start mb-4">
              <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> 10 Video scans / month</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Unlimited manual scans</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> 1 Organization</li>
              <li class="mb-3 text-muted"><i class="bi bi-dash-circle me-2"></i> No API Access</li>
            </ul>
            <a href="/register" class="btn btn-light btn-lg w-100 rounded-pill fw-bold border">Get Started</a>
          </div>
        </div>
        
        <div class="col-md-5 col-lg-4">
          <div class="surface-card feature-card p-5 rounded-4 h-100 text-center position-relative border-0 shadow-lg text-white" style="background:var(--we-hero-gradient, linear-gradient(135deg, #7c3aed 0%, #4c1d95 100%));">
            <div class="position-absolute top-0 start-50 translate-middle badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold shadow-sm">MOST POPULAR</div>
            <h4 class="fw-bold mb-3">Professional</h4>
            <h2 class="display-4 fw-bold mb-2">$99<span class="fs-5 text-white-50 fw-normal">/mo</span></h2>
            <p class="text-white-50 mb-4">For growing safety teams.</p>
            
            <ul class="list-unstyled text-start mb-4">
              <li class="mb-3"><i class="bi bi-check-circle-fill text-warning me-2"></i> 500 Video scans / month</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-warning me-2"></i> Unlimited manual scans</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-warning me-2"></i> Unlimited team members</li>
              <li class="mb-3"><i class="bi bi-check-circle-fill text-warning me-2"></i> Exportable PDF Reports</li>
            </ul>
            <a href="/register" class="btn btn-light btn-lg w-100 rounded-pill fw-bold text-primary">Start Free Trial</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-dark text-white py-5">
    <div class="container py-4">
      <div class="row g-4">
        <div class="col-lg-4">
          <h4 class="fw-bold mb-3" style="font-family:'Outfit';"><i class="bi bi-activity text-primary"></i> WorkEddy</h4>
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
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Privacy Policy</a></li>
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Terms of Service</a></li>
          </ul>
        </div>
      </div>
      <div class="border-top border-secondary mt-5 pt-4 text-center text-white-50 small">
        &copy; <?= date('Y') ?> WorkEddy, Inc. All rights reserved.
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
