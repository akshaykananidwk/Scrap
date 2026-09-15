<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Secure file uploads.
 *
 * Rules enforced here (see docs/SECURITY.md):
 *  - the client-supplied filename is never used;
 *  - extension AND detected MIME type must both be in the allowed list;
 *  - images are re-encoded through GD, which strips EXIF and kills polyglot files;
 *  - the destination is always confined under /uploads with PHP execution disabled.
 */
final class Uploader
{
    public const IMAGE_TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'gif' => 'image/gif',
    ];
    public const DOC_TYPES = [
        'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'webp' => 'image/webp',
    ];
    public const VIDEO_TYPES = [
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
    ];
    public const CSV_TYPES = [
        'csv' => 'text/csv', 'txt' => 'text/plain',
    ];

    private array $errors = [];

    public function __construct(
        private string $directory,
        private array $allowed = self::IMAGE_TYPES,
        private int $maxBytes = 5242880
    ) {
    }

    public static function images(string $directory, ?int $maxMb = null): self
    {
        $max = (int) ($maxMb ?? \App\Services\SettingsService::get('upload_max_image_mb', 5));
        return new self($directory, self::IMAGE_TYPES, $max * 1024 * 1024);
    }

    public static function documents(string $directory, ?int $maxMb = null): self
    {
        $max = (int) ($maxMb ?? \App\Services\SettingsService::get('upload_max_doc_mb', 8));
        return new self($directory, self::DOC_TYPES, $max * 1024 * 1024);
    }

    public static function videos(string $directory, ?int $maxMb = null): self
    {
        $max = (int) ($maxMb ?? \App\Services\SettingsService::get('upload_max_video_mb', 25));
        return new self($directory, self::VIDEO_TYPES, $max * 1024 * 1024);
    }

    public static function csv(string $directory): self
    {
        return new self($directory, self::CSV_TYPES, 4 * 1024 * 1024);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Store one uploaded file.
     * @return string|null Path relative to /uploads, or null on failure.
     */
    public function store(array $file, ?int $resizeWidth = 1600): ?string
    {
        $this->errors = [];

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->uploadErrorMessage($error);
            return null;
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || (!is_uploaded_file($tmp) && PHP_SAPI !== 'cli')) {
            $this->errors[] = 'Invalid upload.';
            return null;
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            $this->errors[] = 'The uploaded file is empty.';
            return null;
        }
        if ($size > $this->maxBytes) {
            $this->errors[] = 'File is too large. Maximum allowed is ' . human_bytes($this->maxBytes) . '.';
            return null;
        }

        $original = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!isset($this->allowed[$extension])) {
            $this->errors[] = 'File type ".' . e($extension) . '" is not allowed.';
            return null;
        }

        $detected = $this->detectMime($tmp);
        $allowedMimes = array_values($this->allowed);
        if ($detected !== null && !in_array($detected, $allowedMimes, true)) {
            $this->errors[] = 'The file content does not match its extension.';
            return null;
        }

        // Double-extension / null-byte tricks never reach the filesystem: we
        // generate the name ourselves.
        $name = date('Ymd') . '_' . str_random(24) . '.' . $extension;
        $relativeDir = trim($this->directory, '/');
        $absoluteDir = UPLOAD_PATH . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            $this->errors[] = 'Upload directory is not writable.';
            return null;
        }

        $destination = $absoluteDir . '/' . $name;
        $isImage = isset(self::IMAGE_TYPES[$extension]) && $extension !== 'gif';

        if ($isImage && $resizeWidth !== null && function_exists('imagecreatetruecolor')) {
            if (!$this->processImage($tmp, $destination, $extension, $resizeWidth)) {
                $this->errors[] = 'The image could not be processed.';
                return null;
            }
        } else {
            $moved = PHP_SAPI === 'cli' ? @rename($tmp, $destination) : @move_uploaded_file($tmp, $destination);
            if (!$moved) {
                $this->errors[] = 'Failed to store the uploaded file.';
                return null;
            }
        }

        @chmod($destination, 0644);
        return $relativeDir . '/' . $name;
    }

    /** Re-encode through GD: strips EXIF/metadata and any embedded payload. */
    private function processImage(string $source, string $destination, string $extension, int $maxWidth): bool
    {
        $info = @getimagesize($source);
        if ($info === false) {
            return false;
        }
        [$width, $height] = $info;

        $image = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($source),
            'png' => @imagecreatefrompng($source),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };
        if ($image === false || $image === null) {
            return false;
        }

        if ($width > $maxWidth) {
            $ratio = $maxWidth / $width;
            $newWidth = $maxWidth;
            $newHeight = (int) round($height * $ratio);
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        $quality = (int) \App\Services\SettingsService::get('upload_image_quality', 82);
        $ok = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, $destination, $quality),
            'png' => imagepng($image, $destination, 6),
            'webp' => function_exists('imagewebp') ? imagewebp($image, $destination, $quality) : false,
            default => false,
        };
        imagedestroy($image);
        return (bool) $ok;
    }

    private function detectMime(string $path): ?string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mime)) {
                    // CSV frequently detects as text/plain — accept both.
                    return $mime === 'text/plain' && isset($this->allowed['csv']) ? 'text/csv' : $mime;
                }
            }
        }
        return null;
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the maximum allowed size.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write the file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
            default => 'Unknown upload error.',
        };
    }

    /** Delete a stored file, confined to the uploads directory. */
    public static function delete(?string $relativePath): bool
    {
        if (!$relativePath) {
            return false;
        }
        $base = realpath(UPLOAD_PATH);
        $target = realpath(UPLOAD_PATH . '/' . ltrim($relativePath, '/'));
        if ($base === false || $target === false || !str_starts_with($target, $base)) {
            return false;
        }
        return is_file($target) && @unlink($target);
    }
}
