<?php

namespace App\Http\Controllers\API;
use AppHelpersApiResponse;

use App\Http\Controllers\Controller;
use App\Utils\FileValidator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\LandingPageSetting;
use App\Models\CmsHeroImage;
use App\Models\CmsLeader;
use App\Models\CmsPartner;
use App\Models\CmsLoginBackground;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class LandingPageController extends Controller
{
    /**
     * Clear landing page cache
     */
    protected function clearCache(): void
    {
        Cache::forget('landing_page_data');
    }

    /**
     * Get all landing page data (cached for 1 hour)
     */
    public function index(): JsonResponse
    {
        $data = Cache::remember('landing_page_data', 3600, function () {
            return [
                'settings' => LandingPageSetting::all()->pluck('value', 'key'),
                'hero_images' => CmsHeroImage::orderBy('order')->get(),
                'login_backgrounds' => CmsLoginBackground::orderBy('order')->get(),
                'leaders' => CmsLeader::orderBy('order')->get(),
                'partners' => CmsPartner::all(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Upload setup file (Logo, etc)
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file',
            'key' => 'required|string',
        ]);

        // Validate file
        $file = $request->file('file');
        $fileValidation = FileValidator::validate($file);

        if (!$fileValidation['valid']) {
            return response()->json([
                'message' => 'File validation failed',
                'errors' => $fileValidation['errors']
            ], 422);
        }

        $sanitizedName = FileValidator::sanitizeFilename($file->getClientOriginalName());
        $extension = $file->getClientOriginalExtension();
        $filename = pathinfo($sanitizedName, PATHINFO_FILENAME) . '_' . time() . '.' . $extension;

        $path = $file->storeAs('cms/settings', $filename, 'public');
        $url = '/storage/' . $path;

        LandingPageSetting::updateOrCreate(
            ['key' => $request->key],
            ['value' => $url]
        );

        $this->clearCache();

        return response()->json(['url' => $url]);
    }

    /**
     * Update settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($data['settings'] as $key => $value) {
            LandingPageSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $this->clearCache();

        return response()->json(['message' => 'Settings updated']);
    }

    /**
     * Store/Update Hero Image
     */
    public function storeHeroImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file',
            'title' => 'nullable|string',
            'order' => 'integer',
        ]);

        // Validate image file
        $file = $request->file('image');
        $fileValidation = FileValidator::validate($file);

        if (!$fileValidation['valid']) {
            return response()->json([
                'message' => 'File validation failed',
                'errors' => $fileValidation['errors']
            ], 422);
        }

        $sanitizedName = FileValidator::sanitizeFilename($file->getClientOriginalName());
        $extension = $file->getClientOriginalExtension();
        $filename = pathinfo($sanitizedName, PATHINFO_FILENAME) . '_' . time() . '.' . $extension;

        $path = $file->storeAs('cms/hero', $filename, 'public');

        $hero = CmsHeroImage::create([
            'image_path' => '/storage/' . $path,
            'title' => $request->title,
            'order' => $request->order ?? 0,
        ]);

        return response()->json($hero, 201);
    }

    public function deleteHeroImage(CmsHeroImage $heroImage): JsonResponse
    {
        // Delete file from storage
        $filePath = str_replace('/storage/', '', $heroImage->image_path);
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }

        $heroImage->delete();
        $this->clearCache();
        return response()->json(['message' => 'Deleted']);
    }

    /**
     * Store/Update Leader
     */
    public function storeLeader(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'title' => 'required|string',
            'image' => 'nullable|file',
            'initial' => 'nullable|string',
            'order' => 'integer',
        ]);

        $data = $request->only(['name', 'title', 'initial', 'order']);

        if ($request->hasFile('image')) {
            // Validate image file
            $file = $request->file('image');
            $fileValidation = FileValidator::validate($file);

            if (!$fileValidation['valid']) {
                return response()->json([
                    'message' => 'File validation failed',
                    'errors' => $fileValidation['errors']
                ], 422);
            }

            $sanitizedName = FileValidator::sanitizeFilename($file->getClientOriginalName());
            $extension = $file->getClientOriginalExtension();
            $filename = pathinfo($sanitizedName, PATHINFO_FILENAME) . '_' . time() . '.' . $extension;

            $path = $file->storeAs('cms/leaders', $filename, 'public');
            $data['image_path'] = '/storage/' . $path;
        }

        $leader = CmsLeader::create($data);

        return response()->json($leader, 201);
    }

    public function updateLeader(Request $request, CmsLeader $leader): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'title' => 'required|string',
            'image' => 'nullable|file',
        ]);

        $data = $request->only(['name', 'title', 'initial', 'order']);

        if ($request->hasFile('image')) {
            // Validate image file
            $file = $request->file('image');
            $fileValidation = FileValidator::validate($file);

            if (!$fileValidation['valid']) {
                return response()->json([
                    'message' => 'File validation failed',
                    'errors' => $fileValidation['errors']
                ], 422);
            }

            $sanitizedName = FileValidator::sanitizeFilename($file->getClientOriginalName());
            $extension = $file->getClientOriginalExtension();
            $filename = pathinfo($sanitizedName, PATHINFO_FILENAME) . '_' . time() . '.' . $extension;

            $path = $file->storeAs('cms/leaders', $filename, 'public');
            $data['image_path'] = '/storage/' . $path;
        }

        $leader->update($data);

        return response()->json($leader);
    }

    public function deleteLeader(CmsLeader $leader): JsonResponse
    {
        $leader->delete();
        return response()->json(['message' => 'Deleted']);
    }

    /**
     * Partners CRUD
     */
    public function storePartner(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'logo' => 'required|file',
        ]);

        // Validate logo file
        $file = $request->file('logo');
        $fileValidation = FileValidator::validate($file);

        if (!$fileValidation['valid']) {
            return response()->json([
                'message' => 'File validation failed',
                'errors' => $fileValidation['errors']
            ], 422);
        }

        $sanitizedName = FileValidator::sanitizeFilename($file->getClientOriginalName());
        $extension = $file->getClientOriginalExtension();
        $filename = pathinfo($sanitizedName, PATHINFO_FILENAME) . '_' . time() . '.' . $extension;

        $path = $file->storeAs('cms/partners', $filename, 'public');

        $partner = CmsPartner::create([
            'name' => $request->name,
            'logo_path' => '/storage/' . $path,
        ]);

        return response()->json($partner, 201);
    }

    public function deletePartner(CmsPartner $partner): JsonResponse
    {
        $partner->delete();
        return response()->json(['message' => 'Deleted']);
    }

    /**
     * Login Background Images CRUD
     */
    public function storeLoginBackground(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file',
            'title' => 'nullable|string',
            'order' => 'integer',
        ]);

        // Validate image file
        $file = $request->file('image');
        $fileValidation = FileValidator::validate($file);

        if (!$fileValidation['valid']) {
            return response()->json([
                'message' => 'File validation failed',
                'errors' => $fileValidation['errors']
            ], 422);
        }

        $sanitizedName = FileValidator::sanitizeFilename($file->getClientOriginalName());
        $extension = $file->getClientOriginalExtension();
        $filename = pathinfo($sanitizedName, PATHINFO_FILENAME) . '_' . time() . '.' . $extension;

        $path = $file->storeAs('cms/login', $filename, 'public');

        $background = CmsLoginBackground::create([
            'image_path' => '/storage/' . $path,
            'title' => $request->title,
            'order' => $request->order ?? 0,
        ]);

        return response()->json($background, 201);
    }

    public function deleteLoginBackground(CmsLoginBackground $loginBackground): JsonResponse
    {
        $loginBackground->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
