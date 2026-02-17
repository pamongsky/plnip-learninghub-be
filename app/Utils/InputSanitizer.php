<?php

namespace App\Utils;

/**
 * Input Sanitizer Utility
 *
 * Provides comprehensive input sanitization methods to prevent security vulnerabilities
 * including SQL injection, XSS attacks, and malicious file uploads.
 *
 * @package App\Utils
 * @author PLN IP Portal Team
 * @version 1.0.0
 */
class InputSanitizer
{
    /**
     * Sanitize string input by removing dangerous characters
     *
     * Removes null bytes, control characters, and trims whitespace
     * to prevent SQL injection and other attacks.
     *
     * @param string|null $input The input string to sanitize
     * @return string|null The sanitized string, or null if input is null
     */
    public static function sanitizeString(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        // Remove null bytes
        $input = str_replace("\0", '', $input);

        // Trim whitespace
        $input = trim($input);

        // Remove control characters except tabs and newlines
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);

        return $input;
    }

    /**
     * Sanitize HTML input (for rich text editors)
     */
    public static function sanitizeHtml(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        // Remove script tags
        $input = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $input);

        // Remove javascript: protocol
        $input = preg_replace('/javascript:/i', '', $input);

        // Remove on* event handlers
        $input = preg_replace('/\s*on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $input);

        // Remove potentially dangerous tags
        $dangerousTags = ['iframe', 'object', 'embed', 'applet', 'meta', 'link', 'style'];
        foreach ($dangerousTags as $tag) {
            $input = preg_replace("/<{$tag}\b[^>]*>(.*?)<\/{$tag}>/is", '', $input);
            $input = preg_replace("/<{$tag}\b[^>]*\/>/is", '', $input);
        }

        return $input;
    }

    /**
     * Sanitize email
     */
    public static function sanitizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = self::sanitizeString($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        return $email ?: null;
    }

    /**
     * Sanitize filename
     */
    public static function sanitizeFilename(?string $filename): ?string
    {
        if ($filename === null) {
            return null;
        }

        // Remove directory traversal
        $filename = basename($filename);

        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Remove multiple dots
        $filename = preg_replace('/\.{2,}/', '.', $filename);

        // Ensure reasonable length
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $filename = substr($filename, 0, 255 - strlen($extension) - 1) . '.' . $extension;
        }

        return $filename;
    }

    /**
     * Sanitize URL
     */
    public static function sanitizeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = self::sanitizeString($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);

        // Ensure URL uses safe protocol
        if (!preg_match('/^https?:\/\//i', $url)) {
            return null;
        }

        return $url ?: null;
    }

    /**
     * Sanitize array recursively
     */
    public static function sanitizeArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $key = self::sanitizeString($key);

            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = self::sanitizeString($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Remove SQL injection attempts
     */
    public static function removeSqlInjection(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        // Remove common SQL injection patterns
        $patterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bSELECT\b.*\bFROM\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bCREATE\b.*\bTABLE\b)/i',
            '/(\bALTER\b.*\bTABLE\b)/i',
            '/(--|;|\/\*|\*\/|xp_|sp_)/i',
        ];

        foreach ($patterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }

        return $input;
    }

    /**
     * Remove XSS attempts
     */
    public static function removeXss(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        // Remove script tags and javascript
        $input = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $input);
        $input = preg_replace('/javascript:/i', '', $input);
        $input = preg_replace('/\s*on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $input);

        return $input;
    }
}
