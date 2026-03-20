<?php
/**
 * Auth layout – centred card, no sidebar.
 *
 * Variables:
 *   $pageTitle string  – browser <title>
 *   $content   string  – rendered page HTML
 */
$pageTitle = $pageTitle ?? 'Sign In';
$content   = $content   ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WorkEddy | <?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" type="image/png" href="/assets/img/favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="/assets/css/core.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="auth-page-body">
<?= $content ?>

<!-- System Dialog (appAlert / appConfirm) -->
<div class="modal fade" id="systemUxDialogModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title d-flex align-items-center gap-2">
          <i data-dialog-icon class="bi bi-info-circle text-primary"></i>
          <span data-dialog-title>Notice</span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" data-dialog-body></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dialog-cancel>Cancel</button>
        <button type="button" class="btn btn-primary" data-dialog-ok>OK</button>
      </div>
    </div>
  </div>
</div>

<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<?php require __DIR__ . '/partials/app-scripts.php'; ?>
</body>
</html>
