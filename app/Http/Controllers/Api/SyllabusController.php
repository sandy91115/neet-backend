<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyllabusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $version = $request->query('version', 'NEET UG 2026');
        $subjects = Subject::query()->with(['chapters' => fn ($query) => $query
            ->where('syllabus_version', $version)
            ->with('topics')
            ->orderBy('unit_name')
            ->orderBy('name')])->orderBy('id')->get();

        return response()->json(['version' => $version, 'subjects' => $subjects]);
    }
}
