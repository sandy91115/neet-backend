<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudyResource;
use Illuminate\Http\JsonResponse;

class AdminCatalogController extends Controller
{
    public function resources(): JsonResponse
    {
        return response()->json(['data' => StudyResource::with(['subject', 'chapter'])->orderBy('sort_order')->orderBy('title')->get()]);
    }
}
