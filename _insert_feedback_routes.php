<?php
// Helper script to insert feedback routes into api.php (handles CRLF line endings)
$file = __DIR__ . '/routes/api.php';
$content = file_get_contents($file);

$newRoutes = <<<'PHP'

        // -- User Feedback
        $r->addRoute('POST', '/feedback',                        fn ($v, $b) => $c->feedbackCtrl()->submit($b));
        $r->addRoute('GET',  '/admin/feedback',                  fn ($v, $b) => $c->feedbackCtrl()->index($c->auth()));
        $r->addRoute('PUT',  '/admin/feedback/{id:\d+}/status',  fn ($v, $b) => $c->feedbackCtrl()->updateStatus($c->auth(), (int) $v['id'], $b));
PHP;

// Find insertion point - right before the closing "    };"
$needle = "        \$r->addRoute('PUT',    '/org/subscription',";
$pos = strrpos($content, $needle);

if ($pos === false) {
    die("Could not find insertion point\n");
}

// Find end of that line
$lineEnd = strpos($content, "\n", $pos);
if ($lineEnd === false) {
    die("Could not find line end\n");
}

// Insert after this line
$content = substr($content, 0, $lineEnd + 1) . $newRoutes . "\r\n" . substr($content, $lineEnd + 1);
file_put_contents($file, $content);
echo "Done! Routes inserted.\n";
