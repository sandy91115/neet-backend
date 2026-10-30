<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneratedTest;
use App\Models\Question;
use App\Models\TestAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TestAttemptController extends Controller
{
    public function submit(Request $request, GeneratedTest $test): JsonResponse
    {
        abort_unless($test->user_id === $request->user()->id, 404);
        abort_if(TestAttempt::where('generated_test_id', $test->id)->exists(), 409, 'This test has already been submitted. Generate a new attempt to retry it.');
        $data = $request->validate([
            'responses' => ['required', 'array'],
            'responses.*' => ['nullable', 'in:A,B,C,D'],
            'time_taken_seconds' => ['required', 'integer', 'between:0,86400'],
        ]);
        $questions = $test->questions()->with('subject:id,code,name')->get(['questions.id', 'questions.correct_option', 'questions.subject_id']);
        $allowed = $questions->pluck('id')->map(fn ($id) => (string) $id)->all();
        $provided = array_map('strval', array_keys($data['responses']));
        if (array_diff($provided, $allowed)) throw ValidationException::withMessages(['responses' => ['Response contains a question not in this test.']]);

        $correct = $incorrect = $unattempted = $score = 0;
        $breakdowns = [];
        foreach ($questions as $question) {
            $answer = $data['responses'][$question->id] ?? null;
            $subjectCode = $question->subject?->code ?? (string) $question->subject_id;
            $bucket = &$breakdowns[$subjectCode];
            if (! isset($bucket)) $bucket = ['subject_id' => $subjectCode, 'subject_name' => $question->subject?->name ?? '', 'total' => 0, 'correct' => 0, 'incorrect' => 0, 'unattempted' => 0, 'score' => 0];
            $bucket['total']++;
            if ($answer === null || $answer === '') { $unattempted++; $bucket['unattempted']++; }
            elseif ($answer === $question->correct_option) { $correct++; $score += $test->positive_marks; $bucket['correct']++; $bucket['score'] += $test->positive_marks; }
            else { $incorrect++; $score -= $test->negative_marks; $bucket['incorrect']++; $bucket['score'] -= $test->negative_marks; }
            unset($bucket);
        }
        $attempt = TestAttempt::create([
            'user_id' => $request->user()->id, 'generated_test_id' => $test->id,
            'responses' => $data['responses'], 'subject_breakdowns' => $breakdowns,
            'correct_count' => $correct, 'incorrect_count' => $incorrect, 'unattempted_count' => $unattempted,
            'total_score' => $score, 'max_score' => $questions->count() * $test->positive_marks,
            'accuracy_percent' => $correct + $incorrect ? round($correct / ($correct + $incorrect) * 100, 2) : 0,
            'time_taken_seconds' => $data['time_taken_seconds'], 'submitted_at' => now(),
        ]);
        return response()->json(['data' => $attempt, 'review' => $questions->map(fn ($q) => [
            'question_id' => $q->id, 'correct_option' => $q->correct_option,
            'explanation' => Question::find($q->id)?->explanation,
        ])]);
    }

    public function index(Request $request): JsonResponse
    {
        $attempts = TestAttempt::with(['test:id,title,test_category,generation_mode,duration_minutes,positive_marks,negative_marks',
            'test.questions.subject:id,code,name', 'test.questions.chapter:id,slug,name'])
            ->where('user_id', $request->user()->id)->latest('submitted_at')->paginate(30);

        $attempts->through(function (TestAttempt $attempt) {
            $test = $attempt->test;
            $questions = $test->questions->map(function (Question $question) use ($attempt) {
                $selected = $attempt->responses[(string) $question->id] ?? null;
                return [
                    'id' => (string) $question->id,
                    'subjectId' => $question->subject->code,
                    'chapterId' => $question->chapter->slug,
                    'chapterName' => $question->chapter->name,
                    'topicName' => $question->topic_name ?? '',
                    'difficulty' => $question->difficulty,
                    'questionType' => $question->question_type,
                    'stem' => $question->stem,
                    'options' => $question->options,
                    'correctOption' => $question->correct_option,
                    'explanation' => $question->explanation ?? '',
                    'ncertReference' => $question->ncert_reference ?? '',
                    'isPyq' => $question->is_pyq,
                    'pyqYear' => $question->pyq_year,
                    'sourceLabel' => $question->source_label ?? '',
                    'sourceUrl' => $question->source_url,
                    'sourcePage' => $question->source_page,
                    'sourceQuestionNumber' => $question->source_question_number,
                    'reviewStatus' => $question->review_status,
                    'selectedOption' => $selected,
                ];
            });
            $responses = $questions->mapWithKeys(fn ($question) => [$question['id'] => [
                'questionId' => $question['id'], 'selectedOption' => $question['selectedOption'],
                'status' => $question['selectedOption'] ? 'answered' : 'not_answered', 'timeSpentSeconds' => 0,
            ]]);
            $subjectBreakdowns = collect($attempt->subject_breakdowns)->map(fn ($row) => [
                'subjectId' => $row['subject_id'], 'subjectName' => $row['subject_name'],
                'totalQuestions' => $row['total'], 'correct' => $row['correct'], 'incorrect' => $row['incorrect'],
                'unattempted' => $row['unattempted'], 'score' => $row['score'],
                'maxScore' => $row['total'] * $test->positive_marks,
                'accuracy' => $row['correct'] + $row['incorrect'] ? round($row['correct'] / ($row['correct'] + $row['incorrect']) * 100, 1) : 0,
                'timeSpentSeconds' => 0,
            ])->values();
            return [
                'id' => 'attempt-'.$attempt->id, 'testId' => (string) $test->id,
                'testTitle' => $test->title, 'category' => $test->test_category,
                'attemptedAt' => $attempt->submitted_at?->toIso8601String(),
                'durationMinutes' => $test->duration_minutes, 'timeTakenSeconds' => $attempt->time_taken_seconds,
                'totalQuestions' => $attempt->correct_count + $attempt->incorrect_count + $attempt->unattempted_count,
                'correctCount' => $attempt->correct_count, 'incorrectCount' => $attempt->incorrect_count,
                'unattemptedCount' => $attempt->unattempted_count, 'totalScore' => $attempt->total_score,
                'maxScore' => $attempt->max_score, 'accuracyPercent' => (float) $attempt->accuracy_percent,
                'predictedAir' => 0, 'percentile' => 0, 'subjectBreakdowns' => $subjectBreakdowns,
                'responses' => $responses, 'questions' => $questions->map(fn ($q) => collect($q)->except('selectedOption')),
            ];
        });

        return response()->json(['data' => $attempts]);
    }
}
