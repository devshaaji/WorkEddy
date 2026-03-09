<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use WorkEddy\Core\Database;

$db = Database::connection();

$rows = $db->fetchAllAssociative(
    'SELECT
        s.organization_id,
        SUM(CASE WHEN s.scan_type = "manual" THEN 1 ELSE 0 END) AS completed_manual_scans,
        SUM(CASE WHEN s.scan_type = "video" THEN 1 ELSE 0 END) AS completed_video_scans,
        SUM(CASE WHEN ur.id IS NOT NULL THEN 1 ELSE 0 END) AS usage_records_found,
        SUM(CASE WHEN ur.id IS NULL THEN 1 ELSE 0 END) AS missing_usage_records
     FROM scans s
     LEFT JOIN usage_records ur
        ON ur.organization_id = s.organization_id
       AND ur.scan_id = s.id
       AND ur.usage_type = CASE
            WHEN s.scan_type = "manual" THEN "manual_scan"
            ELSE "video_scan"
       END
     WHERE s.status = "completed"
       AND s.scan_type IN ("manual", "video")
     GROUP BY s.organization_id
     ORDER BY missing_usage_records DESC, s.organization_id ASC'
);

if ($rows === []) {
    fwrite(STDOUT, "No completed scans found.\n");
    exit(0);
}

fwrite(STDOUT, "organization_id | completed_manual | completed_video | usage_records_found | missing_usage_records\n");
foreach ($rows as $row) {
    fwrite(
        STDOUT,
        sprintf(
            "%14d | %16d | %14d | %19d | %21d\n",
            (int) $row['organization_id'],
            (int) $row['completed_manual_scans'],
            (int) $row['completed_video_scans'],
            (int) $row['usage_records_found'],
            (int) $row['missing_usage_records'],
        )
    );
}
