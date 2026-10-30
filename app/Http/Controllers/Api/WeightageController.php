<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WeightageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = $request->integer('year') ?: null;
        $counts = Question::query()->where('review_status', 'approved')->where('is_pyq', true)
            ->when($year, fn ($query) => $query->where('pyq_year', $year))
            ->select('chapter_id', DB::raw('COUNT(*) as question_count'))
            ->groupBy('chapter_id')->pluck('question_count', 'chapter_id');

        $chapters = Chapter::query()->where('syllabus_version', $request->query('version', 'NEET UG 2026'))
            ->with('subject:id,code,name')->orderBy('subject_id')->orderBy('name')->get();

        $data = $chapters->groupBy('subject_id')->flatMap(function ($subjectChapters) use ($counts, $year) {
            $total = $subjectChapters->sum(fn ($chapter) => (int) ($counts[$chapter->id] ?? 0));

            return $subjectChapters->map(fn ($chapter) => [
                'chapter_id' => $chapter->id,
                'chapter_slug' => $chapter->slug,
                'chapter_name' => $chapter->name,
                'subject_id' => $chapter->subject->code,
                'verified_pyq_count' => (int) ($counts[$chapter->id] ?? 0),
                'weightage_percent' => $total > 0
                    ? round(((int) ($counts[$chapter->id] ?? 0) / $total) * 100, 2)
                    : (float) $chapter->weightage_percent,
                'basis' => $total > 0 ? 'verified_official_pyq_frequency' : $chapter->weightage_basis,
                'reference_year' => $year,
                'note' => $total > 0
                    ? 'Observed share within imported and approved official PYQs; not an NTA prediction.'
                    : 'No verified PYQ data available for this subject/year. Weightage is not established.',
            ]);
        })->values();

        return response()->json(['year' => $year, 'data' => $data]);
    }
}
