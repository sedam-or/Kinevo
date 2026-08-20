<?php

namespace App\Domain\Attachments;

/**
 * Evidence attachment rules (FR-43). Attachments SHALL be JPG/PNG/PDF, ≤5 MB,
 * at most 3 per completed task, and SHALL NOT be an arbitrary executable type.
 * Uploads must enforce allowlist extension + detected content type + size
 * (SRS line 1641); the browser-provided MIME is never trusted on its own.
 */
final class AttachmentRule
{
    public const MAX_PER_TASK = 3;

    public const MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    public const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'application/pdf',
    ];

    public const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'pdf',
    ];

    public static function isAllowedMime(string $mime): bool
    {
        return in_array(strtolower(trim($mime)), self::ALLOWED_MIME, true);
    }

    public static function isAllowedExtension(string $extension): bool
    {
        return in_array(strtolower(trim($extension)), self::ALLOWED_EXTENSIONS, true);
    }
}
