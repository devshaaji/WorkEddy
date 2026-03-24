<?php
$title = 'About Us';
include __DIR__ . '/../partials/site/v2_header.php';
?>
<!-- Page Header -->
  <header class="page-header text-center position-relative">
    <div class="container relative z-1">
      <div class="badge bg-info bg-opacity-10 text-cyan border border-info border-opacity-25 rounded-pill px-3 py-2 mb-3 fw-bold font-mono text-uppercase" style="letter-spacing: 1px;">
        Company
      </div>
      <h1 class="fw-bold mb-0 text-white" style="font-size:3.5rem; letter-spacing: -1px;">About Us</h1>
    </div>
  </header>

  <!-- Main Content -->
  <main class="py-5">
    <div class="container py-4">
      <div class="row justify-content-center">
        <div class="col-lg-11">
          <div class="tech-panel p-4 p-lg-5">
            <div class="row align-items-center g-4 g-lg-5">
              <div class="col-lg-7">
                <p class="fs-5 text-white fw-medium mb-4 lh-base">WorkEddy is an AI ergonomics platform built to help organizations prevent musculoskeletal disorders by providing earlier risk visibility, faster intervention, and stronger evidence of improvement over time.</p>
                <p class="text-light opacity-75 mb-4">The platform transforms everyday task videos into ergonomic risk scores, prioritized intervention guidance, and reports that teams can act on within minutes. Rather than positioning ergonomic assessment as a slow, manual process, WorkEddy is designed to help safety, operations, and workplace health teams identify harmful movement early, understand exposure by task and body region, and focus attention where risk is highest.</p>
                <p class="text-light opacity-75 mb-0">WorkEddy is built around the workflow buyers care about most: capture the task, detect posture risk using trusted ergonomic methods, fix what needs attention first, then compare results over time to verify that changes are reducing exposure. That structure supports assessment and prevention decision-making across teams and sites.</p>
                
                <div class="mt-5">
                  <a href="/founder-story" class="btn btn-outline-tech font-mono text-uppercase px-4">
                    Read: Why we built WorkEddy <i class="bi bi-chevron-right ms-1"></i>
                  </a>
                </div>
              </div>

              <div class="col-lg-5">
                <div class="border border-secondary border-opacity-50 rounded-3 p-4 p-lg-5 h-100 d-flex flex-column bg-black bg-opacity-50">
                  <div class="flex-grow-1 d-flex justify-content-center align-items-center py-4">
                    <i class="bi bi-cpu text-cyan" style="font-size: 8rem; opacity: 0.8;"></i>
                  </div>

                  <div class="d-flex align-items-center gap-3 pt-4 border-top border-secondary border-opacity-50 mt-auto">
                    <div class="bg-info bg-opacity-10 text-cyan border border-info border-opacity-25 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 54px; height: 54px;">
                      <i class="bi bi-shield-check fs-3"></i>
                    </div>
                    <div>
                      <p class="mb-1 fw-bold text-white lh-sm">Moving from delayed review</p>
                      <p class="mb-0 small text-light opacity-75">To earlier action and better prevention outcomes.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
<?php include __DIR__ . '/../partials/site/v2_footer.php'; ?>
