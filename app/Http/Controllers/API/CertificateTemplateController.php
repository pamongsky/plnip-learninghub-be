<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CertificateTemplateController extends Controller
{
    /**
     * Get all certificate templates
     */
    public function index(Request $request): JsonResponse
    {
        $query = CertificateTemplate::query();

        // Filter by category
        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // Filter active only
        if ($request->has('active_only') && $request->active_only) {
            $query->where('is_active', true);
        }

        $templates = $query->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json($templates);
    }

    /**
     * Get single template
     */
    public function show(CertificateTemplate $template): JsonResponse
    {
        return response()->json($template);
    }

    /**
     * Create new template
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'template_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB
            'preview_file' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'description' => 'nullable|string',
            'variables' => 'nullable|array',
            'settings' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Upload template file
        $filePath = $request->file('template_file')->store('certificate-templates', 'public');

        // Upload preview if provided
        $previewPath = null;
        if ($request->hasFile('preview_file')) {
            $previewPath = $request->file('preview_file')->store('certificate-templates/previews', 'public');
        }

        // If this is set as default, unset other defaults
        if ($request->is_default) {
            CertificateTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template = CertificateTemplate::create([
            'name' => $request->name,
            'category' => $request->category,
            'file_path' => $filePath,
            'preview_path' => $previewPath,
            'variables' => $request->variables ?? CertificateTemplate::getDefaultVariables(),
            'settings' => $request->settings ?? [],
            'description' => $request->description,
            'is_active' => true,
            'is_default' => $request->is_default ?? false,
        ]);

        return response()->json([
            'message' => 'Template created successfully',
            'template' => $template
        ], 201);
    }

    /**
     * Update template
     */
    public function update(Request $request, CertificateTemplate $template): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'category' => 'nullable|string|max:100',
            'template_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'preview_file' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'description' => 'nullable|string',
            'variables' => 'nullable|array',
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Upload new template file if provided
        if ($request->hasFile('template_file')) {
            // Delete old file
            if ($template->file_path) {
                Storage::disk('public')->delete($template->file_path);
            }
            $template->file_path = $request->file('template_file')->store('certificate-templates', 'public');
        }

        // Upload new preview if provided
        if ($request->hasFile('preview_file')) {
            if ($template->preview_path) {
                Storage::disk('public')->delete($template->preview_path);
            }
            $template->preview_path = $request->file('preview_file')->store('certificate-templates/previews', 'public');
        }

        // If this is set as default, unset other defaults
        if ($request->has('is_default') && $request->is_default) {
            CertificateTemplate::where('id', '!=', $template->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $template->update($request->except(['template_file', 'preview_file']));

        return response()->json([
            'message' => 'Template updated successfully',
            'template' => $template
        ]);
    }

    /**
     * Delete template
     */
    public function destroy(CertificateTemplate $template): JsonResponse
    {
        // Check if template is used by any courses
        if ($template->courses()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete template. It is being used by courses.'
            ], 422);
        }

        // Delete files
        if ($template->file_path) {
            Storage::disk('public')->delete($template->file_path);
        }
        if ($template->preview_path) {
            Storage::disk('public')->delete($template->preview_path);
        }

        $template->delete();

        return response()->json([
            'message' => 'Template deleted successfully'
        ]);
    }

    /**
     * Get available variables
     */
    public function getAvailableVariables(): JsonResponse
    {
        return response()->json([
            'variables' => CertificateTemplate::getDefaultVariables()
        ]);
    }

    /**
     * Get template categories
     */
    public function getCategories(): JsonResponse
    {
        $categories = CertificateTemplate::distinct()
            ->pluck('category')
            ->filter()
            ->values();

        return response()->json($categories);
    }
}
