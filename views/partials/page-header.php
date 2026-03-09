<?php
$headerTitle = $headerTitle ?? '';
$headerTitleHtml = $headerTitleHtml ?? '';
$headerBreadcrumb = $headerBreadcrumb ?? '';
$headerBreadcrumbHtml = $headerBreadcrumbHtml ?? '';
$headerActionsHtml = $headerActionsHtml ?? '';
?>
<div class="page-header">
  <div class="page-header-main">
    <?php if ($headerTitleHtml): ?>
      <?= $headerTitleHtml ?>
    <?php else: ?>
      <h1 class="page-title"><?= htmlspecialchars($headerTitle) ?></h1>
    <?php endif; ?>
    <?php if ($headerBreadcrumbHtml): ?>
      <?= $headerBreadcrumbHtml ?>
    <?php else: ?>
      <?php if ($headerBreadcrumb): ?>
        <p class="page-breadcrumb"><?= htmlspecialchars($headerBreadcrumb) ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php if ($headerActionsHtml): ?>
    <div class="page-header-actions">
      <?= $headerActionsHtml ?>
    </div>
  <?php endif; ?>
</div>
