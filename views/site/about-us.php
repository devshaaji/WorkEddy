<?php

$pageTitle = "About Us - WorkEddy";
include_once __DIR__ . '/../partials/site/header.php';

?>
  <!-- Page Header -->
  <header class="page-header text-center position-relative">
    <div class="container">
      <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 mb-3 fw-bold border border-primary border-opacity-25 mx-auto d-table text-uppercase" style="letter-spacing: 1px;">
         Company
      </div>
      <h1 class="fw-bold mb-0" style="font-family:'Outfit',sans-serif;font-size:3.5rem; letter-spacing: -1px; color:#111827;">About Us</h1>
    </div>
  </header>

  <!-- Main Content -->
  <main class="py-5">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-11">
          <div class="about-panel bg-white p-4 p-lg-5">
            <div class="row align-items-center g-4 g-lg-5">
              <div class="col-lg-7">
                <p class="fs-5 text-dark fw-medium mb-4">WorkEddy is an AI ergonomics platform built to help organizations prevent musculoskeletal disorders by providing earlier risk visibility, faster intervention, and stronger evidence of improvement over time.</p>
                <p class="text-muted mb-4">The platform transforms everyday task videos into ergonomic risk scores, prioritized intervention guidance, and reports that teams can act on within minutes. Rather than positioning ergonomic assessment as a slow, manual process, WorkEddy is designed to help safety, operations, and workplace health teams identify harmful movement early, understand exposure by task and body region, and focus attention where risk is highest.</p>
                <p class="text-muted mb-0">WorkEddy is built around the workflow buyers care about most: capture the task, detect posture risk using trusted ergonomic methods, fix what needs attention first, then compare results over time to verify that changes are reducing exposure. That structure supports assessment and prevention decision-making across teams and sites.</p>

                <div class="mt-4">
                  <a href="/founder-story" class="btn btn-outline-dark rounded-pill px-4 fw-bold">
                    Read: Why we built WorkEddy <i class="bi bi-chevron-right ms-1"></i>
                  </a>
                </div>
              </div>

              <div class="col-lg-5">
                <div class="border rounded-4 p-4 p-lg-5 h-100 d-flex flex-column">
                  <div class="flex-grow-1 d-flex justify-content-center align-items-center py-4">
                    <img src="/assets/img/WorkEddy Main Logo.png" alt="WorkEddy Logo" class="img-fluid" style="max-width: 260px;">
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
        </div>
      </div>
    </div>
  </main>
<?php include_once __DIR__ . '/../partials/site/footer.php'; ?>
</body>
</html>
