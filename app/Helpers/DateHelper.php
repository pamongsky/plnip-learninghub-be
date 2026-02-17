<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Format date for API responses (ISO 8601)
     */
    public static function formatApi(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        return Carbon::parse($date)->toIso8601String();
    }

    /**
     * Format date for Indonesian display
     */
    public static function formatIndonesia(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        Carbon::setLocale('id');
        return Carbon::parse($date)->translatedFormat('d F Y');
    }

    /**
     * Format datetime for Indonesian display
     */
    public static function formatIndonesiaWithTime(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        Carbon::setLocale('id');
        return Carbon::parse($date)->translatedFormat('d F Y H:i');
    }

    /**
     * Format date for database (Y-m-d H:i:s)
     */
    public static function formatDatabase(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        return Carbon::parse($date)->format('Y-m-d H:i:s');
    }

    /**
     * Get relative time (e.g., "2 hours ago")
     */
    public static function formatRelative(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        Carbon::setLocale('id');
        return Carbon::parse($date)->diffForHumans();
    }

    /**
     * Check if date is in the past
     */
    public static function isPast(?string $date): bool
    {
        if (!$date) {
            return false;
        }

        return Carbon::parse($date)->isPast();
    }

    /**
     * Check if date is in the future
     */
    public static function isFuture(?string $date): bool
    {
        if (!$date) {
            return false;
        }

        return Carbon::parse($date)->isFuture();
    }
}
