<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuestionAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $questions = Question::query()->with(['subject:id,code,name', 'chapter:id,slug,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('review_status', $request->string('status')))
            ->when($request->filled('year'), fn ($query) => $query->where('pyq_year', $request->integer('year')))
            ->orderByDesc('created_at')->paginate(min(100, max(1, $request->integer('per_page', 30))));

        return response()->json($questions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateQuestion($request->all());
        $question = Question::create($this->prepareQuestion($data));

        return response()->json(['data' => $question], 201);
    }

    public function update(Request $request, Question $question): JsonResponse
    {
        $data = $this->prepareQuestion($this->validateQuestion($request->all()));
        $duplicate = Question::where('content_fingerprint', $data['content_fingerprint'])->where('id', '<>', $question->id)->exists();
        if ($duplicate) throw ValidationException::withMessages(['stem' => ['A question with the same stem and options already exists.']]);
        $question->update($data);
        return response()->json(['data' => $question->fresh(['subject', 'chapter'])]);
    }

    public function destroy(Question $question): JsonResponse
    {
        // Keep historical test-attempt references intact; rejected rows leave student practice.
        $question->update(['review_status' => 'rejected']);
        return response()->json(['message' => 'Question removed from the active bank.']);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['questions' => ['required', 'array', 'min:1', 'max:1000']]);
        $created = 0;
        $duplicates = 0;
        $errors = [];

        DB::transaction(function () use ($request, &$created, &$duplicates, &$errors): void {
            foreach ($request->input('questions') as $index => $row) {
                try {
                    $validated = $this->validateQuestion($row);
                    $prepared = $this->prepareQuestion($validated);
                    if (Question::where('content_fingerprint', $prepared['content_fingerprint'])->exists()) {
                        $duplicates++;
                        continue;
                    }
                    Question::create($prepared);
                    $created++;
                } catch (ValidationException $exception) {
                    $errors[] = ['row' => $index + 1, 'errors' => $exception->errors()];
                }
            }
        });

        return response()->json([
            'created' => $created,
            'duplicates' => $duplicates,
            'invalid' => count($errors),
            'errors' => $errors,
            'review_status' => 'pending_review',
        ], $created > 0 || count($errors) === 0 ? 201 : 422);
    }

    public function review(Request $request, Question $question): JsonResponse
    {
        $data = $request->validate(['review_status' => ['required', 'in:approved,rejected']]);
        if ($data['review_status'] === 'approved') {
            $question->load('chapter');
            abort_unless($question->chapter, 422, 'Question chapter is missing.');
            abort_unless($this->hasValidPyqSource($question->toArray()), 422, 'A PYQ must have official paper URL, year, set, page, and question number before approval.');
        }
        $question->update(['review_status' => $data['review_status']]);

        return response()->json(['data' => $question->fresh(['subject', 'chapter'])]);
    }

    private function validateQuestion(array $input): array
    {
        $validator = validator($input, [
            'subject_id' => ['required'],
            'chapter_id' => ['required'],
            'topic_name' => ['nullable', 'string', 'max:255'],
            'question_type' => ['nullable', 'in:single_correct,assertion_reason,statement_based'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'stem' => ['required', 'string', 'max:20000'],
            'options' => ['required', 'array', 'size:4'],
            'options.*.id' => ['required', 'in:A,B,C,D'],
            'options.*.text' => ['required', 'string', 'max:5000'],
            'correct_option' => ['required', 'in:A,B,C,D'],
            'explanation' => ['nullable', 'string', 'max:20000'],
            'ncert_reference' => ['nullable', 'string', 'max:1000'],
            'is_pyq' => ['sometimes', 'boolean'],
            'pyq_year' => ['nullable', 'integer', 'between:1980,2100'],
            'source_label' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:https', 'max:2048'],
            'source_page' => ['nullable', 'string', 'max:40'],
            'source_question_number' => ['nullable', 'string', 'max:40'],
            'content_license' => ['nullable', 'string', 'max:255'],
        ]);
        $data = $validator->validate();

        $subject = is_numeric($data['subject_id'])
            ? Subject::find($data['subject_id'])
            : Subject::where('code', $data['subject_id'])->first();
        $chapter = is_numeric($data['chapter_id'])
            ? Chapter::find($data['chapter_id'])
            : Chapter::where('slug', $data['chapter_id'])->first();

        if (! $subject || ! $chapter || $chapter->subject_id !== $subject->id) {
            throw ValidationException::withMessages(['chapter_id' => ['Choose a chapter that belongs to the selected subject.']]);
        }

        $data['subject_id'] = $subject->id;
        $data['chapter_id'] = $chapter->id;
        $data['is_pyq'] = (bool) ($data['is_pyq'] ?? false);
        if ($data['is_pyq'] && ! $this->hasValidPyqSource($data)) {
            throw ValidationException::withMessages(['source_url' => ['Real PYQs require an official NTA paper URL, paper/set label, year, PDF page, and question number.']]);
        }

        return $data;
    }

    private function prepareQuestion(array $data): array
    {
        $canonical = Str::lower(Str::squish($data['stem'])).'|'.json_encode($data['options'], JSON_UNESCAPED_UNICODE);

        return [
            ...$data,
            'question_type' => $data['question_type'] ?? 'single_correct',
            'review_status' => 'pending_review',
            'content_fingerprint' => hash('sha256', $canonical),
        ];
    }

    private function hasValidPyqSource(array $data): bool
    {
        if (! ($data['is_pyq'] ?? false)) return true;
        if (empty($data['pyq_year']) || empty($data['source_label']) || empty($data['source_page']) || empty($data['source_question_number']) || empty($data['source_url'])) return false;
        $host = strtolower((string) parse_url($data['source_url'], PHP_URL_HOST));

        return $host === 'nta.ac.in' || str_ends_with($host, '.nta.ac.in') ||
            $host === 'nmc.org.in' || str_ends_with($host, '.nmc.org.in') ||
            $host === 'cdnbbsr.s3waas.gov.in';
    }
}
