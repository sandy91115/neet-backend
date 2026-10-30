<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TestBlueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TestBlueprintAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => TestBlueprint::orderBy('cadence')->orderBy('title')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateBlueprint($request);
        $data['slug'] = Str::slug($data['slug'] ?? $data['title']);
        $blueprint = TestBlueprint::create($data);

        return response()->json(['data' => $blueprint], 201);
    }

    public function update(Request $request, TestBlueprint $blueprint): JsonResponse
    {
        $data = $this->validateBlueprint($request, $blueprint->id);
        $data['slug'] = Str::slug($data['slug'] ?? $data['title']);
        $blueprint->update($data);

        return response()->json(['data' => $blueprint->fresh()]);
    }

    private function validateBlueprint(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'slug' => ['nullable', 'string', 'max:180', 'unique:test_blueprints,slug'.($ignoreId ? ','.$ignoreId : '')],
            'title' => ['required', 'string', 'max:255'],
            'cadence' => ['required', 'in:weekly,monthly,custom'],
            'test_category' => ['required', 'in:full_syllabus,minor_test,semi_major,major_test,subject_test,chapter_test,pyq_test'],
            'config' => ['required', 'array'],
            'config.question_count' => ['required', 'integer', 'between:1,180'],
            'config.subject_counts' => ['nullable', 'array'],
            'config.difficulty_mix' => ['nullable', 'array'],
            'config.weightage_source' => ['nullable', 'in:verified_pyq_frequency,admin_estimate,equal'],
            'duration_minutes' => ['required', 'integer', 'between:5,300'],
            'positive_marks' => ['required', 'integer', 'between:0,10'],
            'negative_marks' => ['required', 'integer', 'between:0,10'],
            'pyq_only' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
