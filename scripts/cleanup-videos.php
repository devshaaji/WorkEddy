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

require_once __DIR__ . '/../bootstrap.php';

use WorkEddy\Core\Database;

// ── Configuration ────────────────────────────────────────────────────────
$retentionDays = (int) (getenv('VIDEO_RETENTION_DAYS') ?: 30);
$videoDir      = '/storage/uploads/videos';
$dryRun        = in_array('--dry-run', $argv ?? [], true);

$db = Database::connection();

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

// ── Scan for expired video files (org-aware retention) ───────────────────
$deleted = 0;
$freed   = 0;
$errors  = 0;

$rows = $db->fetchAllAssociative(
    'SELECT s.id, s.organization_id, s.video_path, s.created_at, o.settings
     FROM scans s
     INNER JOIN organizations o ON o.id = s.organization_id
     WHERE s.scan_type = "video"
       AND s.video_path IS NOT NULL
       AND s.video_path != ""
       AND s.status IN ("completed", "invalid")'
);

foreach ($rows as $row) {
    $scanId = (int) ($row['id'] ?? 0);
    $orgId = (int) ($row['organization_id'] ?? 0);
    $videoPath = (string) ($row['video_path'] ?? '');
    if ($scanId <= 0 || $orgId <= 0 || $videoPath === '') {
        continue;
    }

    $policyDays = effectiveRetentionDays($row['settings'] ?? null, $retentionDays);
    if ($policyDays <= 0) {
        continue;
    }

    $createdAtRaw = (string) ($row['created_at'] ?? '');
    $createdAtTs = strtotime($createdAtRaw);
    if ($createdAtTs === false) {
        continue;
    }

    $expiryTs = $createdAtTs + ($policyDays * 86400);
    if (time() < $expiryTs) {
        continue;
    }

    if (!is_file($videoPath)) {
        if (!$dryRun) {
            $db->executeStatement('UPDATE scans SET video_path = NULL WHERE id = :id', ['id' => $scanId]);
        }
        continue;
    }

    $size = (int) (filesize($videoPath) ?: 0);
    $age  = (int) round((time() - $createdAtTs) / 86400);

    if ($dryRun) {
        echo sprintf(
            "  [DRY] Would delete: %s (%s, %d days old, org=%d, retention=%d days)\n",
            $videoPath,
            formatBytes($size),
            $age,
            $orgId,
            $policyDays,
        );
        continue;
    }

    if (@unlink($videoPath)) {
        $db->executeStatement('UPDATE scans SET video_path = NULL WHERE id = :id', ['id' => $scanId]);
        echo sprintf("  Deleted: %s (%s, %d days old, org=%d)\n", $videoPath, formatBytes($size), $age, $orgId);
        $deleted++;
        $freed += $size;
    } else {
        echo sprintf("  ERROR: Could not delete: %s (scan_id=%d)\n", $videoPath, $scanId);
        $errors++;
    }
}

// ── Also clean up extracted frames ───────────────────────────────────────
$framesDir = '/storage/uploads/frames';
if (is_dir($framesDir)) {
    $cutoff = time() - ($retentionDays * 86400);
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

function effectiveRetentionDays(mixed $rawSettings, int $defaultDays): int
{
    $settings = [];

    if (is_string($rawSettings) && $rawSettings !== '') {
        $decoded = json_decode($rawSettings, true);
        if (is_array($decoded)) {
            $settings = $decoded;
        }
    } elseif (is_array($rawSettings)) {
        $settings = $rawSettings;
    }

    if (!array_key_exists('video_retention_days', $settings)) {
        return $defaultDays;
    }

    $value = (int) $settings['video_retention_days'];
    if ($value < 0) {
        return $defaultDays;
    }

    return $value;
}
