<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentWorkspaceState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentWorkspaceController extends Controller
{
    private const FIELDS = [
        'syllabus', 'official_syllabus_progress', 'attempts', 'series_tests', 'daily_tasks', 'mistakes', 'bookmarks',
        'practice_selections', 'revealed_solutions', 'saved_practice_mistakes',
    ];

    public function show(Request $request): JsonResponse
    {
        $state = StudentWorkspaceState::where('user_id', $request->user()->id)->first();
        if (! $state) return response()->json(['data' => null]);
        return response()->json(['data' => collect(self::FIELDS)->mapWithKeys(fn ($field) => [$field => $state->{$field} ?? match ($field) {
            'syllabus', 'attempts', 'series_tests', 'daily_tasks', 'mistakes', 'bookmarks' => [],
            default => (object) [],
        }])]);
    }

    public function update(Request $request): JsonResponse
    {
        $rules = collect(self::FIELDS)->mapWithKeys(fn ($field) => [$field => ['sometimes', 'array']])->all();
        $validated = $request->validate($rules);
        $state = StudentWorkspaceState::firstOrCreate(['user_id' => $request->user()->id]);
        $state->fill($validated)->save();
        return response()->json(['data' => $state->fresh()]);
    }
}
