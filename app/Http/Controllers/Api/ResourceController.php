<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $resources = StudyResource::query()
            ->with(['subject:id,name', 'chapter:id,name'])
            ->where('is_active', true)
            ->when($request->filled('subject'), fn ($query) => $query->whereHas('subject', fn ($q) => $q->where('code', $request->string('subject'))))
            ->when($request->filled('chapter_id'), fn ($query) => $query->where('chapter_id', $request->integer('chapter_id')))
            ->orderBy('sort_order')->orderBy('title')->get();

        return response()->json(['data' => $resources]);
    }
}
