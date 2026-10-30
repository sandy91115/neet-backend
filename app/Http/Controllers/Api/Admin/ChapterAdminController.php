<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChapterAdminController extends Controller
{
    public function addTopic(Request $request, Chapter $chapter): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:5000']]);
        $topic = $chapter->topics()->create(['name' => $data['name'], 'position' => (int) $chapter->topics()->max('position') + 1]);
        return response()->json(['data' => $topic], 201);
    }

    public function updateTopic(Request $request, Topic $topic): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:5000'], 'position' => ['nullable', 'integer', 'min:1']]);
        $topic->update($data);
        return response()->json(['data' => $topic->fresh()]);
    }

    public function deleteTopic(Topic $topic): JsonResponse
    {
        $topic->delete();
        return response()->json(['message' => 'Topic deleted.']);
    }

    public function updateWeightage(Request $request, Chapter $chapter): JsonResponse
    {
        $data = $request->validate([
            'weightage_percent' => ['required', 'numeric', 'between:0,100'],
            'expected_questions' => ['nullable', 'integer', 'between:0,45'],
            'reference_year' => ['nullable', 'integer', 'between:1980,2100'],
        ]);

        $chapter->update([
            'weightage_percent' => $data['weightage_percent'],
            'expected_questions' => $data['expected_questions'] ?? 0,
            'weightage_basis' => 'admin_estimate',
            'weightage_reference_year' => $data['reference_year'] ?? null,
            'high_yield' => $data['weightage_percent'] >= 8,
        ]);

        return response()->json(['data' => $chapter->fresh(['subject', 'topics'])]);
    }
}
