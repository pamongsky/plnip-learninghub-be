<?php

use App\Helpers\DateHelper;
use App\Helpers\ApiResponse;

if (!function_exists('format_date_api')) {
    function format_date_api(?string $date): ?string
    {
        return DateHelper::formatApi($date);
    }
}

if (!function_exists('format_date_id')) {
    function format_date_id(?string $date): ?string
    {
        return DateHelper::formatIndonesia($date);
    }
}

if (!function_exists('format_datetime_id')) {
    function format_datetime_id(?string $date): ?string
    {
        return DateHelper::formatIndonesiaWithTime($date);
    }
}

if (!function_exists('format_date_relative')) {
    function format_date_relative(?string $date): ?string
    {
        return DateHelper::formatRelative($date);
    }
}

// API Response helpers
if (!function_exists('api_success')) {
    function api_success($data = null, string $message = null, int $code = 200)
    {
        return ApiResponse::success($data, $message, $code);
    }
}

if (!function_exists('api_error')) {
    function api_error(string $message, $errors = null, int $code = 400)
    {
        return ApiResponse::error($message, $errors, $code);
    }
}
