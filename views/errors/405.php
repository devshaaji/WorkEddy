<?php
$pageTitle = '405 – Method Not Allowed';
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

  <div class="error-code text-secondary mb-2" style="font-size:6rem;font-weight:800;line-height:1;font-family:'Inter',sans-serif;">405</div>
  <h1 class="fw-bold mb-2">Method not allowed</h1>
  <p class="text-muted mb-4" style="max-width:420px;">
    The request method is not supported for this page.
    Try navigating back and using a different action.
  </p>

  <div class="d-flex gap-2 flex-wrap justify-content-center">
    <a href="/dashboard" class="btn btn-primary">
      <i class="bi bi-house me-1"></i>Dashboard
    </a>
    <a href="/" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Home
    </a>
  </div>

</div>

</body>
</html>
