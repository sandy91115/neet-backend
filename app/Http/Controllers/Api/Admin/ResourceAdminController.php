<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceAdminController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateResource($request);
        $resource = StudyResource::create([...$data, 'url_hash' => hash('sha256', $data['url'])]);

        return response()->json(['data' => $resource], 201);
    }

    public function update(Request $request, StudyResource $resource): JsonResponse
    {
        $data = $this->validateResource($request);
        $resource->update([...$data, 'url_hash' => hash('sha256', $data['url'])]);

        return response()->json(['data' => $resource->fresh()]);
    }

    public function destroy(StudyResource $resource): JsonResponse
    {
        $resource->update(['is_active' => false]);

        return response()->json(['message' => 'Resource archived.']);
    }

    private function validateResource(Request $request): array
    {
        return $request->validate([
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'chapter_id' => ['nullable', 'exists:chapters,id'],
            'title' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'max:255'],
            'resource_type' => ['required', 'in:syllabus,textbook,practice,pyq_source,course,video,notes,reference'],
            'description' => ['nullable', 'string', 'max:5000'],
            'url' => ['required', 'url:https', 'max:2048'],
            'license_note' => ['nullable', 'string', 'max:1000'],
            'is_official' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }
}
