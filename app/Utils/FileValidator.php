<?php

namespace App\Utils;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * File Upload Validator
 *
 * Provides comprehensive file upload validation to prevent security vulnerabilities
 * including malicious file uploads, executable disguised as images, and size attacks.
 *
 * Features:
 * - MIME type validation
 * - File size limits (10MB default)
 * - Extension blacklist (blocks PHP, executables, scripts)
 * - Double extension detection (e.g., file.php.jpg)
 * - Content scanning for suspicious patterns
 * - Filename sanitization
 *
 * @package App\Utils
 * @author PLN IP Portal Team
 * @version 1.0.0
 */
class FileValidator
{
    /**
     * Allowed MIME types for uploads
     */
    protected static array $allowedMimeTypes = [
        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',

        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

        // Archives
        'application/zip',
        'application/x-zip-compressed',
    ];

    /**
     * Maximum file size in bytes (10MB)
     */
    protected static int $maxFileSize = 10485760;

    /**
     * Suspicious file extensions that should be blocked
     */
    protected static array $blockedExtensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'pht',
        'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar',
        'sh', 'bash', 'zsh', 'csh', 'pl', 'py', 'rb', 'asp', 'aspx',
        'jsp', 'jspx', 'cgi', 'dll', 'so', 'dylib'
    ];

    /**
     * Validate uploaded file
     */
    public static function validate(UploadedFile $file): array
    {
        $errors = [];

        // Check file size
        if ($file->getSize() > self::$maxFileSize) {
            $errors[] = 'File size exceeds maximum allowed size of ' . (self::$maxFileSize / 1048576) . 'MB';
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::$allowedMimeTypes)) {
            $errors[] = "File type '{$mimeType}' is not allowed";
            Log::warning("Blocked upload attempt with MIME type: {$mimeType}", [
                'original_name' => $file->getClientOriginalName(),
                'ip' => request()->ip(),
            ]);
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, self::$blockedExtensions)) {
            $errors[] = "File extension '.{$extension}' is blocked for security reasons";
            Log::alert("SECURITY: Blocked dangerous file upload attempt", [
                'extension' => $extension,
                'original_name' => $file->getClientOriginalName(),
                'ip' => request()->ip(),
                'user_id' => auth()->id(),
            ]);
        }

        // Check for double extensions (e.g., file.pdf.php)
        $fileName = $file->getClientOriginalName();
        if (preg_match('/\.[a-z0-9]{2,4}\.[a-z0-9]{2,4}$/i', $fileName)) {
            foreach (self::$blockedExtensions as $blockedExt) {
                if (stripos($fileName, ".{$blockedExt}") !== false) {
                    $errors[] = "File contains suspicious double extension";
                    Log::alert("SECURITY: Blocked double extension upload", [
                        'filename' => $fileName,
                        'ip' => request()->ip(),
                        'user_id' => auth()->id(),
                    ]);
                    break;
                }
            }
        }

        // Scan file content for suspicious patterns (basic check)
        if (self::containsSuspiciousContent($file)) {
            $errors[] = "File contains suspicious content and cannot be uploaded";
            Log::alert("SECURITY: Blocked file with suspicious content", [
                'filename' => $fileName,
                'mime_type' => $mimeType,
                'ip' => request()->ip(),
                'user_id' => auth()->id(),
            ]);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if file contains suspicious content
     */
    protected static function containsSuspiciousContent(UploadedFile $file): bool
    {
        // Only check text-based files
        if (!str_starts_with($file->getMimeType(), 'text/')) {
            return false;
        }

        $content = file_get_contents($file->getRealPath());

        // Check for common malicious patterns
        $suspiciousPatterns = [
            '/<\?php/i',
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/passthru\s*\(/i',
            '/shell_exec\s*\(/i',
            '/assert\s*\(/i',
            '/preg_replace.*\/e/i',
            '/create_function\s*\(/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize filename
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove directory traversal attempts
        $filename = basename($filename);

        // Remove any character that's not alphanumeric, dash, underscore, or dot
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Remove multiple consecutive dots
        $filename = preg_replace('/\.{2,}/', '.', $filename);

        // Ensure filename is not too long
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $filename = substr($filename, 0, 255 - strlen($extension) - 1) . '.' . $extension;
        }

        return $filename;
    }
}
