<?php
$pageTitle = '500 – Server Error';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/assets/img/favicon.ico" />
  <title>WorkEddy | <?= htmlspecialchars($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="/assets/css/core.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="auth-page-body">

<div class="d-flex flex-column align-items-center justify-content-center min-vh-100 text-center px-3">

  <div class="auth-brand mb-4">
   <img src="/assets/img/logo.png" alt="WorkEddy logo" class="auth-brand-logo" />
    <span class="auth-brand-name">WorkEddy</span>
  </div>

  <div class="error-code text-danger mb-2" style="font-size:6rem;font-weight:800;line-height:1;font-family:'Inter',sans-serif;">500</div>
  <h1 class="fw-bold mb-2">Something went wrong</h1>
  <p class="text-muted mb-4" style="max-width:420px;">
    We hit an unexpected error. Our team has been notified.
    Please try again in a moment.
  </p>

  <div class="d-flex gap-2 flex-wrap justify-content-center">
    <a href="/dashboard" class="btn btn-primary">
      <i class="bi bi-house me-1"></i>Dashboard
    </a>
    <button class="btn btn-outline-secondary" onclick="location.reload()">
      <i class="bi bi-arrow-clockwise me-1"></i>Retry
    </button>
  </div>

</div>

</body>
</html>
