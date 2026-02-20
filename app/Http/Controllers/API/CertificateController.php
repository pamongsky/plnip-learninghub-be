<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Utils\FileValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class CertificateController extends Controller
{
    /**
     * Get user's certificates (dashboard learner)
     */
    public function index(Request $request): JsonResponse
    {
        $certificates = Certificate::where('user_id', $request->user()->id)
            ->where('is_valid', true)
            ->with(['course:id,title'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($cert) {
                return [
                    'id' => $cert->id,
                    'certificate_number' => $cert->certificate_number,
                    'course_id' => $cert->course_id,
                    'course' => $cert->course ? [
                        'id' => $cert->course->id,
                        'title' => $cert->course->title,
                    ] : null,
                    'pdf_path' => $cert->pdf_path,
                    'pdf_url' => asset('storage/' . $cert->pdf_path),
                    'original_filename' => $cert->original_filename,
                    'is_valid' => $cert->is_valid,
                    'notes' => $cert->notes,
                    'created_at' => $cert->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $certificates
        ]);
    }

    /**
     * Download certificate PDF
     */
    public function download(Request $request, $id)
    {
        $certificate = Certificate::findOrFail($id);

        if ($certificate->user_id != $request->user()->id && !$request->user()->hasRole(['admin', 'super-admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!Storage::disk('public')->exists($certificate->pdf_path)) {
            return response()->json(['message' => 'File tidak ditemukan'], 404);
        }

        $filename = $certificate->original_filename ?: "{$certificate->certificate_number}.pdf";
        
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        return $disk->download($certificate->pdf_path, $filename);
    }

    /**
     * Upload single certificate PDF for a user (admin)
     */
    public function uploadForUser(Request $request, $courseId, $userId): JsonResponse
    {
        $request->validate([
            'certificate' => 'required|file',
        ]);

        // Validate file
        $file = $request->file('certificate');
        $fileValidation = FileValidator::validate($file);

        if (!$fileValidation['valid']) {
            return response()->json([
                'message' => 'File validation failed',
                'errors' => $fileValidation['errors']
            ], 422);
        }

        // Ensure it's a PDF
        if ($file->getMimeType() !== 'application/pdf') {
            return response()->json([
                'message' => 'File harus berupa PDF',
            ], 422);
        }

        $course = Course::findOrFail($courseId);
        $user = User::findOrFail($userId);

        $certNumber = 'CERT-' . strtoupper(substr(md5($user->id . $course->id . time()), 0, 8));

        $sanitizedFilename = FileValidator::sanitizeFilename($file->getClientOriginalName());
        $path = $file->storeAs(
            'certificates',
            "{$certNumber}.pdf",
            'public'
        );

        $certificate = Certificate::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            [
                'certificate_number' => $certNumber,
                'pdf_path' => $path,
                'original_filename' => $sanitizedFilename,
                'is_valid' => true,
                'notes' => null,
            ]
        );

        return response()->json([
            'message' => 'Sertifikat berhasil diupload',
            'certificate' => $certificate,
        ]);
    }

    /**
     * Bulk upload certificates via ZIP (admin)
     * Matching: NIP exact → nama exact → nama partial
     */
    public function uploadBulkZip(Request $request, $courseId): JsonResponse
    {
        $request->validate([
            'zip' => 'required|file|max:102400', // 100MB max for bulk ZIP
        ]);

        $zipFile = $request->file('zip');

        // Validate MIME type (ZIP only)
        $mime = $zipFile->getMimeType();
        $allowedZipMimes = ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'];
        if (!in_array($mime, $allowedZipMimes) && strtolower($zipFile->getClientOriginalExtension()) !== 'zip') {
            return response()->json(['message' => 'File harus berupa ZIP'], 422);
        }

        $course = Course::findOrFail($courseId);
        $students = $course->students()->wherePivot('status', 'active')->get();

        $zip = new ZipArchive();
        $zipPath = $zipFile->getPathname();

        if ($zip->open($zipPath) !== true) {
            return response()->json(['message' => 'Gagal membuka file ZIP'], 422);
        }

        $matched = [];
        $unmatched = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $filename = $stat['name'];

            // Skip non-PDF and directories
            if (!str_ends_with(strtolower($filename), '.pdf') || str_ends_with($filename, '/')) {
                continue;
            }

            // Validate filename
            $sanitizedFilename = FileValidator::sanitizeFilename($filename);
            $basename = pathinfo($sanitizedFilename, PATHINFO_FILENAME); // tanpa .pdf

            $user = $this->matchUser($basename, $students);

            if (!$user) {
                $unmatched[] = $filename;
                continue;
            }

            // Extract and validate PDF content
            $contents = $zip->getFromIndex($i);

            // Check if it's a valid PDF (magic bytes check)
            if (!str_starts_with($contents, '%PDF-')) {
                $unmatched[] = "{$filename} (bukan PDF valid)";
                continue;
            }

            // Check file size (max 20MB)
            if (strlen($contents) > 20 * 1024 * 1024) {
                $unmatched[] = "{$filename} (terlalu besar)";
                continue;
            }

            $certNumber = 'CERT-' . strtoupper(substr(md5($user->id . $course->id . time() . $i), 0, 8));
            $savePath = "certificates/{$certNumber}.pdf";

            Storage::disk('public')->put($savePath, $contents);

            Certificate::updateOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                [
                    'certificate_number' => $certNumber,
                    'pdf_path' => $savePath,
                    'original_filename' => $sanitizedFilename,
                    'is_valid' => true,
                    'notes' => null,
                ]
            );

            $matched[] = "{$sanitizedFilename} → {$user->name}";
        }

        $zip->close();

        return response()->json([
            'message' => 'Upload ZIP selesai',
            'matched' => $matched,
            'unmatched' => $unmatched,
            'total_matched' => count($matched),
            'total_unmatched' => count($unmatched),
        ]);
    }

    /**
     * Get all certificates (admin)
     */
    public function getAllCertificates(Request $request): JsonResponse
    {
        $query = Certificate::with(['user:id,name,email,employee_id', 'course:id,title']);

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%"))
                  ->orWhere('certificate_number', 'like', "%{$search}%");
        }

        $certificates = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($certificates);
    }

    /**
     * Revoke certificate (admin)
     */
    public function revoke(Request $request, $id): JsonResponse
    {
        $certificate = Certificate::findOrFail($id);
        $certificate->update([
            'is_valid' => false,
            'notes' => $request->input('notes', 'Dicabut oleh admin'),
        ]);

        return response()->json(['message' => 'Sertifikat berhasil dicabut', 'certificate' => $certificate]);
    }

    /**
     * Match user by filename: NIP exact → nama exact → nama partial
     */
    protected function matchUser(string $basename, $students): ?User
    {
        $clean = trim($basename);
        // Normalize for name matching: replace commonly used separators with spaces
        $cleanNormalized = strtolower(str_replace(['_', '-', '.'], ' ', $clean));
        
        // Split filename into parts to find NIP clearly
        $parts = preg_split('/[\s\-_.]+/', strtolower($clean));

        // 1. NIP Matching (Highest Priority)
        foreach ($students as $student) {
            if (!$student->employee_id) continue;
            
            $nip = strtolower(trim($student->employee_id));
            if (empty($nip)) continue;

            // Check if NIP exists strictly as one of the parts (safe from partial number matches)
            if (in_array($nip, $parts)) {
                return $student;
            }
        }

        // 2. Name Matching
        foreach ($students as $student) {
            $rawName = strtolower(trim($student->name));
            // Clean up student name roughly similar to filename
            $studentNameClean = str_replace(['.', '_', '-'], ' ', $rawName);
            // Also keep a version with just single spaces
            $studentNameClean = preg_replace('/\s+/', ' ', $studentNameClean);

            // A. Exact match of normalized strings
            if ($cleanNormalized === $studentNameClean) {
                return $student;
            }
            
            // B. Filename CONTAINS the full name
            // "fahmi admin user 240..." contains "fahmi admin user"
            if (str_contains($cleanNormalized, $studentNameClean)) {
                return $student;
            }

            // C. Name CONTAINS the filename (less likely but possible for short filenames)
            if (str_contains($studentNameClean, $cleanNormalized)) {
                return $student;
            }
        }

        return null;
    }
}
