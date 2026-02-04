<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Services\CertificateGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    protected $generator;

    public function __construct(CertificateGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Get user's certificates
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $certificates = Certificate::where('user_id', $user->id)
            ->where('is_valid', true)
            ->with(['course:id,title', 'template:id,name'])
            ->orderBy('issue_date', 'desc')
            ->get();

        return response()->json($certificates);
    }

    /**
     * Get single certificate
     */
    public function show(Request $request, $id): JsonResponse
    {
        $certificate = Certificate::with(['course', 'template', 'user'])
            ->findOrFail($id);

        // Check if user owns this certificate or is admin
        if ($certificate->user_id !== $request->user()->id && !$request->user()->hasRole(['admin', 'super-admin'])) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json($certificate);
    }

    /**
     * Download certificate PDF
     */
    public function download(Request $request, $id)
    {
        $certificate = Certificate::findOrFail($id);

        // Check if user owns this certificate or is admin
        if ($certificate->user_id !== $request->user()->id && !$request->user()->hasRole(['admin', 'super-admin'])) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Extract filename from URL
        $filePath = str_replace('/storage/', '', parse_url($certificate->certificate_url, PHP_URL_PATH));

        if (!Storage::disk('public')->exists($filePath)) {
            return response()->json([
                'message' => 'Certificate file not found'
            ], 404);
        }

        return Storage::disk('public')->download($filePath, "{$certificate->certificate_number}.pdf");
    }

    /**
     * Verify certificate by verification code
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'verification_code' => 'required|string'
        ]);

        $certificate = Certificate::where('verification_code', $request->verification_code)
            ->with(['user:id,name,employee_id', 'course:id,title'])
            ->first();

        if (!$certificate) {
            return response()->json([
                'valid' => false,
                'message' => 'Certificate not found'
            ], 404);
        }

        if (!$certificate->is_valid) {
            return response()->json([
                'valid' => false,
                'message' => 'Certificate has been revoked',
                'certificate' => $certificate
            ]);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Certificate is valid',
            'certificate' => $certificate
        ]);
    }

    /**
     * Get all certificates (admin)
     */
    public function getAllCertificates(Request $request): JsonResponse
    {
        $query = Certificate::with(['user:id,name,email,employee_id', 'course:id,title', 'template:id,name']);

        // Filter by course
        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by validity
        if ($request->has('is_valid')) {
            $query->where('is_valid', $request->is_valid);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('certificate_number', 'like', "%{$search}%")
                  ->orWhere('student_name', 'like', "%{$search}%")
                  ->orWhere('verification_code', 'like', "%{$search}%");
            });
        }

        $certificates = $query->orderBy('issue_date', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($certificates);
    }

    /**
     * Revoke certificate (admin)
     */
    public function revoke(Request $request, $id): JsonResponse
    {
        $certificate = Certificate::findOrFail($id);

        $request->validate([
            'notes' => 'nullable|string'
        ]);

        $certificate->update([
            'is_valid' => false,
            'notes' => $request->notes ?? 'Certificate revoked by admin'
        ]);

        return response()->json([
            'message' => 'Certificate revoked successfully',
            'certificate' => $certificate
        ]);
    }

    /**
     * Restore revoked certificate (admin)
     */
    public function restore(Request $request, $id): JsonResponse
    {
        $certificate = Certificate::findOrFail($id);

        $certificate->update([
            'is_valid' => true,
            'notes' => 'Certificate restored by admin'
        ]);

        return response()->json([
            'message' => 'Certificate restored successfully',
            'certificate' => $certificate
        ]);
    }

    /**
     * Get certificate statistics (admin)
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total' => Certificate::count(),
            'valid' => Certificate::where('is_valid', true)->count(),
            'revoked' => Certificate::where('is_valid', false)->count(),
            'this_month' => Certificate::whereMonth('issue_date', now()->month)
                ->whereYear('issue_date', now()->year)
                ->count(),
            'by_course' => Certificate::selectRaw('course_id, course_name, COUNT(*) as total')
                ->groupBy('course_id', 'course_name')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json($stats);
    }
}
