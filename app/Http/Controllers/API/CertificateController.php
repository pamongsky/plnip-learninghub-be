<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
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
            ->get();

        return response()->json($certificates);
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
        return Storage::disk('public')->download($certificate->pdf_path, $filename);
    }

    /**
     * Upload single certificate PDF for a user (admin)
     */
    public function uploadForUser(Request $request, $courseId, $userId): JsonResponse
    {
        $request->validate([
            'certificate' => 'required|file|mimes:pdf|max:20480',
        ]);

        $course = Course::findOrFail($courseId);
        $user = User::findOrFail($userId);

        $certNumber = 'CERT-' . strtoupper(substr(md5($user->id . $course->id . time()), 0, 8));

        $file = $request->file('certificate');
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
                'original_filename' => $file->getClientOriginalName(),
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
            'zip' => 'required|file|mimes:zip|max:102400',
        ]);

        $course = Course::findOrFail($courseId);
        $students = $course->students()->wherePivot('status', 'active')->get();

        $zip = new ZipArchive();
        $zipPath = $request->file('zip')->getPathname();

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

            $basename = pathinfo($filename, PATHINFO_FILENAME); // tanpa .pdf

            $user = $this->matchUser($basename, $students);

            if (!$user) {
                $unmatched[] = $filename;
                continue;
            }

            // Extract and save PDF
            $contents = $zip->getFromIndex($i);
            $certNumber = 'CERT-' . strtoupper(substr(md5($user->id . $course->id . time() . $i), 0, 8));
            $savePath = "certificates/{$certNumber}.pdf";

            Storage::disk('public')->put($savePath, $contents);

            Certificate::updateOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                [
                    'certificate_number' => $certNumber,
                    'pdf_path' => $savePath,
                    'original_filename' => $filename,
                    'is_valid' => true,
                    'notes' => null,
                ]
            );

            $matched[] = "{$filename} → {$user->name}";
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

        // 1. NIP exact
        foreach ($students as $student) {
            if ($student->employee_id && strtolower($student->employee_id) === strtolower($clean)) {
                return $student;
            }
        }

        // 2. Nama exact
        foreach ($students as $student) {
            if (strtolower($student->name) === strtolower($clean)) {
                return $student;
            }
        }

        // 3. Nama partial (filename contains name or name contains filename)
        foreach ($students as $student) {
            if (str_contains(strtolower($student->name), strtolower($clean)) ||
                str_contains(strtolower($clean), strtolower($student->name))) {
                return $student;
            }
        }

        return null;
    }
}
