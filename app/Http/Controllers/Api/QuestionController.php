<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $questions = Question::query()->where('review_status', 'approved')
            ->with(['subject:id,code,name', 'chapter:id,slug,name,official_unit_code'])
            ->select([
                'id', 'subject_id', 'chapter_id', 'topic_name', 'question_type', 'difficulty',
                'stem', 'options', 'is_pyq', 'pyq_year', 'source_label', 'source_url',
                'source_page', 'source_question_number', 'review_status', 'created_at',
            ])
            ->when($request->filled('subject'), fn ($query) => $query->whereHas('subject', fn ($q) => $q->where('code', $request->string('subject'))))
            ->when($request->filled('chapter_id'), fn ($query) => $query->where('chapter_id', $request->integer('chapter_id')))
            ->when($request->filled('year'), fn ($query) => $query->where('pyq_year', $request->integer('year')))
            ->when($request->boolean('pyq_only'), fn ($query) => $query->where('is_pyq', true))
            ->when($request->filled('difficulty'), fn ($query) => $query->where('difficulty', $request->string('difficulty')))
            ->orderByDesc('pyq_year')->orderBy('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 30))));

        return response()->json($questions);
    }

    public function answer(Request $request, Question $question): JsonResponse
    {
        abort_unless($question->review_status === 'approved', 404);

        $questionIsInCurrentTest = DB::table('generated_test_questions')
            ->join('generated_tests', 'generated_tests.id', '=', 'generated_test_questions.generated_test_id')
            ->leftJoin('test_attempts', 'test_attempts.generated_test_id', '=', 'generated_tests.id')
            ->where('generated_test_questions.question_id', $question->id)
            ->where('generated_tests.user_id', $request->user()->id)
            ->where('generated_tests.created_at', '>=', now()->subHours(8))
            ->whereNull('test_attempts.id')
            ->exists();
        abort_if($questionIsInCurrentTest, 403, 'Submit or let this timed test expire before revealing its answer.');

        $data = $request->validate(['selected_option' => ['nullable', 'in:A,B,C,D']]);

        return response()->json(['data' => [
            'question_id' => $question->id,
            'selected_option' => $data['selected_option'] ?? null,
            'correct_option' => $question->correct_option,
            'is_correct' => isset($data['selected_option']) && $data['selected_option'] === $question->correct_option,
            'explanation' => $question->explanation,
            'ncert_reference' => $question->ncert_reference,
        ]]);
    }
}
