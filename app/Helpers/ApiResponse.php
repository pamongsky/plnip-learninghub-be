<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Success response
     */
    public static function success($data = null, string $message = null, int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Error response
     */
    public static function error(string $message, $errors = null, int $code = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Validation error response
     */
    public static function validationError($errors, string $message = 'Validasi gagal'): JsonResponse
    {
        return self::error($message, $errors, 422);
    }

    /**
     * Not found response
     */
    public static function notFound(string $message = 'Data tidak ditemukan'): JsonResponse
    {
        return self::error($message, null, 404);
    }

    /**
     * Unauthorized response
     */
    public static function unauthorized(string $message = 'Tidak memiliki akses'): JsonResponse
    {
        return self::error($message, null, 401);
    }

    /**
     * Forbidden response
     */
    public static function forbidden(string $message = 'Akses ditolak'): JsonResponse
    {
        return self::error($message, null, 403);
    }

    /**
     * Server error response
     */
    public static function serverError(string $message = 'Terjadi kesalahan server', $error = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        // Only include error details in debug mode
        if (config('app.debug') && $error) {
            $response['error'] = $error;
        }

        return response()->json($response, 500);
    }

    /**
     * Paginated response
     */
    public static function paginated($paginator, string $message = null): JsonResponse
    {
        $response = [
            'success' => true,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        $response['data'] = $paginator->items();
        $response['pagination'] = [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];

        return response()->json($response);
    }

    /**
     * Created response
     */
    public static function created($data = null, string $message = 'Data berhasil dibuat'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * Updated response
     */
    public static function updated($data = null, string $message = 'Data berhasil diperbarui'): JsonResponse
    {
        return self::success($data, $message);
    }

    /**
     * Deleted response
     */
    public static function deleted(string $message = 'Data berhasil dihapus'): JsonResponse
    {
        return self::success(null, $message);
    }
}
