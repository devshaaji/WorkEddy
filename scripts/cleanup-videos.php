<?php

/**
 * Video Data Retention Cleanup Script
 *
 * Deletes uploaded video files older than VIDEO_RETENTION_DAYS (default 30).
 * Preserves all scan/analysis data in the database — only the raw video files
 * are removed to comply with the data-retention policy.
 *
 * Usage:
 *   php scripts/cleanup-videos.php [--dry-run]
 *
 * Schedule via cron:
 *   0 3 * * * php /app/scripts/cleanup-videos.php >> /app/storage/logs/cleanup.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// ── Configuration ────────────────────────────────────────────────────────
$retentionDays = (int) (getenv('VIDEO_RETENTION_DAYS') ?: 30);
$videoDir      = __DIR__ . '/../storage/uploads/videos';
$dryRun        = in_array('--dry-run', $argv ?? [], true);

echo sprintf(
    "[%s] Video retention cleanup started (retention=%d days, dry_run=%s)\n",
    date('Y-m-d H:i:s'),
    $retentionDays,
    $dryRun ? 'yes' : 'no'
);

if (!is_dir($videoDir)) {
    echo "  Video directory does not exist: {$videoDir}\n";
    exit(0);
}

// ── Scan for expired video files ─────────────────────────────────────────
$cutoff  = time() - ($retentionDays * 86400);
$deleted = 0;
$freed   = 0;
$errors  = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($videoDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    // Only process video files
    $ext = strtolower($file->getExtension());
    if (!in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'mkv'], true)) {
        continue;
    }

    $mtime = $file->getMTime();
    if ($mtime >= $cutoff) {
        continue; // file is still within retention window
    }

    $path = $file->getRealPath();
    $size = $file->getSize();
    $age  = (int) round((time() - $mtime) / 86400);

    if ($dryRun) {
        echo sprintf("  [DRY] Would delete: %s (%s, %d days old)\n", $path, formatBytes($size), $age);
    } else {
        if (@unlink($path)) {
            echo sprintf("  Deleted: %s (%s, %d days old)\n", $path, formatBytes($size), $age);
            $deleted++;
            $freed += $size;
        } else {
            echo sprintf("  ERROR: Could not delete: %s\n", $path);
            $errors++;
        }
    }
}

// ── Also clean up extracted frames ───────────────────────────────────────
$framesDir = __DIR__ . '/../storage/uploads/frames';
if (is_dir($framesDir)) {
    $frameIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($framesDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($frameIterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        if ($file->getMTime() >= $cutoff) {
            continue;
        }

        $path = $file->getRealPath();
        $size = $file->getSize();

        if ($dryRun) {
            echo sprintf("  [DRY] Would delete frame: %s (%s)\n", $path, formatBytes($size));
        } else {
            if (@unlink($path)) {
                $deleted++;
                $freed += $size;
            }
        }
    }
}

echo sprintf(
    "[%s] Cleanup complete: %d files deleted, %s freed, %d errors\n",
    date('Y-m-d H:i:s'),
    $deleted,
    formatBytes($freed),
    $errors
);

exit($errors > 0 ? 1 : 0);

// ── Helpers ──────────────────────────────────────────────────────────────

function formatBytes(int $bytes): string
{
    if ($bytes >= 1_073_741_824) return round($bytes / 1_073_741_824, 2) . ' GB';
    if ($bytes >= 1_048_576)     return round($bytes / 1_048_576, 2) . ' MB';
    if ($bytes >= 1024)          return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
