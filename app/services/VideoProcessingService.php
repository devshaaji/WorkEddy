<?php

declare(strict_types=1);

namespace WorkEddy\Services;

/**
 * Provides helpers for video upload handling on the PHP side.
 * Heavy video processing is done by the Python workers.
 */
final class VideoProcessingService
{
    private const ALLOWED_EXT    = ['mp4', 'mov'];
    private const BASE_DIR       = '/storage/uploads/videos';

    public function storeUploadedFile(array $fileInfo): string
    {
        $ext = strtolower(pathinfo($fileInfo['name'] ?? '', PATHINFO_EXTENSION) ?: '');
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            throw new \InvalidArgumentException('Unsupported video format. Use MP4 or MOV.');
        }

        $maxSize = (int) (getenv('MAX_VIDEO_UPLOAD_BYTES') ?: 200 * 1024 * 1024);
        $size    = (int) ($fileInfo['size'] ?? 0);
        if ($size <= 0 || $size > $maxSize) {
            throw new \InvalidArgumentException('Invalid video size');
        }

        if (!is_dir(self::BASE_DIR)) {
            if (!mkdir(self::BASE_DIR, 0775, true) && !is_dir(self::BASE_DIR)) {
                throw new \RuntimeException(
                    'Cannot create upload directory: ' . self::BASE_DIR
                );
            }
        }

        $filename = uniqid('scan_', true) . '.' . $ext;
        $target   = self::BASE_DIR . '/' . $filename;

        if (!move_uploaded_file($fileInfo['tmp_name'], $target)) {
            throw new \RuntimeException('Failed to store uploaded video');
        }

        return $target;
    }

    public function deleteVideo(string $videoPath): void
    {
        if ($videoPath !== '' && file_exists($videoPath)) {
            @unlink($videoPath);
        }
    }
}