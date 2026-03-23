<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Why We Built WorkEddy - From the Founder</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
  
  <link href="/assets/css/core.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="/assets/css/app.css" rel="stylesheet">
  
  <style>
    body { background-color: #fafafa; }
    .marketing-nav {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .page-header {
      padding: 160px 0 80px 0;
      background: #ffffff;
      border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .story-content {
      font-size: 1.25rem;
      line-height: 2;
      color: #4b5563;
    }
    .story-content p {
      margin-bottom: 2.25rem;
    }
    .story-content p:first-of-type {
      font-size: 1.5rem;
      font-weight: 500;
      color: #1f2937;
      line-height: 1.8;
      border-left: 4px solid var(--bs-primary);
      padding-left: 1.5rem;
      margin-bottom: 3rem;
    }
    .founder-card {
      background: #ffffff;
      border: 1px solid rgba(0,0,0,0.08);
      border-radius: 1rem;
      padding: 2.5rem;
      box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05);
      margin-top: -100px;
      position: relative;
      z-index: 10;
    }
    .strong-take {
      background: #eff6ff;
      border-radius: 1rem;
      padding: 2.5rem;
      font-family: 'Outfit', sans-serif;
      font-size: 1.5rem;
      color: #1e3a8a;
      line-height: 1.6;
      margin-top: 4rem;
      margin-bottom: 2rem;
      box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.02);
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
          <li class="nav-item"><a class="nav-link px-3" href="/#features">Features</a></li>
          <li class="nav-item"><a class="nav-link px-3" href="/#how-it-works">How it Works</a></li>
          <li class="nav-item"><a class="nav-link px-3" href="/#pricing">Pricing</a></li>
        </ul>
        <div class="d-flex gap-2 flex-column flex-lg-row mt-3 mt-lg-0">
          <a href="/login" class="btn btn-light rounded-pill px-4 fw-bold">Login</a>
          <a href="/register" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">Get Started</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Page Header -->
  <header class="page-header text-center position-relative">
    <div class="container">
      <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 mb-3 fw-bold border border-primary border-opacity-25 mx-auto d-table text-uppercase" style="letter-spacing: 1px;">
         From the Founder
      </div>
      <h1 class="fw-bold mb-4" style="font-family:'Outfit',sans-serif;font-size:4rem; letter-spacing: -1px; color:#111827;">Why we built WorkEddy</h1>
    </div>
  </header>

  <!-- Main Content -->
  <main class="pb-5">
    <div class="container">
      
      <!-- Founder Bio / Author Card (pulls up into header) -->
      <div class="row justify-content-center mb-5">
        <div class="col-lg-8 col-xl-7">
          <div class="founder-card d-flex flex-column flex-sm-row align-items-center text-center text-sm-start gap-4 mx-auto">
             <img src="/assets/img/Founder's Why we built WorkEddy Phote.png" alt="Treasure Nkemdilim James" class="rounded-circle shadow-sm border" style="width: 140px; height: 140px; object-fit: cover;">
             <div>
                <h4 class="fw-bold mb-1 text-dark" style="font-family:'Outfit',sans-serif;">Treasure Nkemdilim James</h4>
                <p class="small text-muted mb-2">MS, MSISD, MOSH, PCQI</p>
                <p class="fw-bold text-primary mb-3">Founder and Product Lead</p>
                <div class="d-inline-flex bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-3 px-3 py-2">
                   <div class="d-flex align-items-center gap-2">
                     <i class="bi bi-patch-check-fill fs-5"></i>
                     <span class="fw-bold lh-sm text-success text-uppercase" style="font-size: 0.70rem; letter-spacing: 0.5px;">IRCA Certified ISO 45001<br>Lead Auditor</span>
                   </div>
                </div>
             </div>
          </div>
        </div>
      </div>
      
      <!-- Story Body -->
      <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
          <div class="story-content">
            
            <p>We started WorkEddy because teams often wait too long for clear answers, and by then, strain has become injury.</p>
            
            <p>For too long, ergonomic reviews have been slow, manual, and difficult to scale across work environments. Safety teams are doing serious work, yet many still have to rely on scattered observations, delayed assessments, and tools that identify risk without clearly showing what should happen next. I believed there had to be a better way.</p>
            
            <p>That belief is what led me to build WorkEddy.</p>
            
            <p>WorkEddy helps organizations detect harmful movement earlier and respond with greater confidence. By turning everyday task videos into ergonomic risk scores, prioritized interventions, and clear reports, the platform is designed to help teams move from observation to action within minutes. From the beginning, the vision was never to create another broad safety platform. It was to help prevent musculoskeletal injuries before they happen.</p>
            
            <p>What drives this work is detection and prevention. I want teams to identify posture risk early, understand where strain is building across the body, focus on what needs attention first, and measure whether changes are actually reducing exposure over time. That is the kind of progress WorkEddy is built to support.</p>
            
            <p>I also believe workplace technology must earn trust. Because of that, privacy-conscious task analysis is built into our product direction from the start. WorkEddy is designed to help organizations improve work design responsibly through secure access, trusted ergonomic methods, and evidence that interventions are working.</p>
            
            <div class="strong-take fw-bold text-center">
              <i class="bi bi-quote fs-1 text-primary opacity-25 d-block mb-3"></i>
              WorkEddy is grounded in a clear conviction: safer work should not start after injury has already occurred. It should start earlier, with better visibility into risk, clearer action on what to improve, and stronger evidence that workplace changes are reducing exposure over time.
            </div>
            
          </div>
        </div>
      </div>
      
    </div>
  </main>

  <!-- Footer -->
  <footer class="bg-dark text-white py-5 mt-5">
    <div class="container py-4">
      <div class="row g-4">
        <div class="col-lg-4">
          <h4 class="fw-bold mb-3"><img src="/assets/img/logo.png" alt="WorkEddy logo" class="auth-brand-logo" /> WorkEddy</h4>
          <p class="text-white-50 pe-lg-5">Automating ergonomic risk assessments to protect your workforce and streamline compliance.</p>
        </div>
        <div class="col-6 col-lg-2 offset-lg-2">
          <h6 class="fw-bold text-uppercase mb-3">Product</h6>
          <ul class="list-unstyled text-white-50">
            <li class="mb-2"><a href="/#features" class="text-white-50 text-decoration-none">Features</a></li>
            <li class="mb-2"><a href="/#pricing" class="text-white-50 text-decoration-none">Pricing</a></li>
            <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Enterprise</a></li>
          </ul>
        </div>
        <div class="col-6 col-lg-2">
          <h6 class="fw-bold text-uppercase mb-3">Company</h6>
          <ul class="list-unstyled text-white-50">
            <li class="mb-2"><a href="/founder-story" class="text-white-50 text-decoration-none">About Us</a></li>
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
</body>
</html>
