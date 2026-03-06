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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="/assets/css/core.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="auth-page-body">
<?= $content ?>
<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
