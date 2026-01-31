<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\LandingPageSetting;
use App\Models\CmsHeroImage;
use App\Models\CmsLeader;
use App\Models\CmsPartner;
use Illuminate\Support\Facades\Storage;

class LandingPageController extends Controller
{
    /**
     * Get all landing page data
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'settings' => LandingPageSetting::all()->pluck('value', 'key'),
            'hero_images' => CmsHeroImage::orderBy('order')->get(),
            'leaders' => CmsLeader::orderBy('order')->get(),
            'partners' => CmsPartner::all(),
        ]);
    }

    /**
     * Upload setup file (Logo, etc)
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:2048',
            'key' => 'required|string',
        ]);

        $path = $request->file('file')->store('cms/settings', 'public');
        $url = asset('storage/' . $path);

        LandingPageSetting::updateOrCreate(
            ['key' => $request->key],
            ['value' => $url]
        );

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

        return response()->json(['message' => 'Settings updated']);
    }

    /**
     * Store/Update Hero Image
     */
    public function storeHeroImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:2048',
            'title' => 'nullable|string',
            'order' => 'integer',
        ]);

        $path = $request->file('image')->store('cms/hero', 'public');

        $hero = CmsHeroImage::create([
            'image_path' => asset('storage/' . $path),
            'title' => $request->title,
            'order' => $request->order ?? 0,
        ]);

        return response()->json($hero, 201);
    }

    public function deleteHeroImage(CmsHeroImage $heroImage): JsonResponse
    {
        // Delete file from storage if needed
        // Storage::disk('public')->delete(str_replace('/storage/', '', $heroImage->image_path));

        $heroImage->delete();
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
            'image' => 'nullable|image|max:2048',
            'initial' => 'nullable|string',
            'order' => 'integer',
        ]);

        $data = $request->only(['name', 'title', 'initial', 'order']);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('cms/leaders', 'public');
            $data['image_path'] = asset('storage/' . $path);
        }

        $leader = CmsLeader::create($data);

        return response()->json($leader, 201);
    }

    public function updateLeader(Request $request, CmsLeader $leader): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'title' => 'required|string',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = $request->only(['name', 'title', 'initial', 'order']);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('cms/leaders', 'public');
            $data['image_path'] = asset('storage/' . $path);
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
            'logo' => 'required|image|max:2048',
        ]);

        $path = $request->file('logo')->store('cms/partners', 'public');

        $partner = CmsPartner::create([
            'name' => $request->name,
            'logo_path' => asset('storage/' . $path),
        ]);

        return response()->json($partner, 201);
    }

    public function deletePartner(CmsPartner $partner): JsonResponse
    {
        $partner->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
