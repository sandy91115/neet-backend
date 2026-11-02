<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\GeneratedTest;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TestBlueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TestGenerationController extends Controller
{
    public function blueprints(): JsonResponse
    {
        $subjects = Subject::pluck('id', 'code');
        $approvedBank = Question::query()->where('review_status', 'approved')
            ->selectRaw('subject_id, chapter_id, is_pyq, pyq_year, source_label, COUNT(*) as question_count')
            ->groupBy('subject_id', 'chapter_id', 'is_pyq', 'pyq_year', 'source_label')->get();

        $data = TestBlueprint::where('is_active', true)->orderBy('cadence')->orderBy('title')->get()
            ->map(function (TestBlueprint $blueprint) use ($subjects, $approvedBank) {
                $config = $blueprint->config ?? [];
                $requiredBySubject = $config['subject_counts'] ?? [];
                $chapterIds = array_map('intval', $config['chapter_ids'] ?? []);
                $pyqOnly = (bool) ($config['pyq_only'] ?? $blueprint->pyq_only);
                $year = $config['year'] ?? null;
                $sourceSet = $config['source_set'] ?? null;

                if (! $requiredBySubject) {
                    $scope = $config['subject'] ?? array_keys($subjects->all());
                    $scope = is_array($scope) ? array_values($scope) : [$scope];
                    $scope = array_values(array_filter($scope)) ?: array_keys($subjects->all());
                    $total = (int) ($config['question_count'] ?? 20);
                    foreach ($scope as $index => $code) {
                        $requiredBySubject[$code] = intdiv($total, count($scope)) + ($index < $total % count($scope) ? 1 : 0);
                    }
                }

                $availability = [];
                foreach ($requiredBySubject as $code => $required) {
                    $subjectId = $subjects[$code] ?? null;
                    $strictAvailable = $subjectId ? $approvedBank->filter(function ($row) use ($subjectId, $chapterIds, $pyqOnly, $year, $sourceSet) {
                        return (int) $row->subject_id === (int) $subjectId
                            && (! $chapterIds || in_array((int) $row->chapter_id, $chapterIds, true))
                            && (! $pyqOnly || (bool) $row->is_pyq)
                            && (! $year || (int) $row->pyq_year === (int) $year)
                            && (! $sourceSet || str_contains((string) $row->source_label, 'Set '.$sourceSet));
                    })->sum('question_count') : 0;

                    $fallbackAvailable = $subjectId ? $approvedBank->filter(function ($row) use ($subjectId, $pyqOnly) {
                        return (int) $row->subject_id === (int) $subjectId
                            && (! $pyqOnly || (bool) $row->is_pyq);
                    })->sum('question_count') : 0;

                    $subjectTotalAvailable = $subjectId ? $approvedBank->filter(function ($row) use ($subjectId) {
                        return (int) $row->subject_id === (int) $subjectId;
                    })->sum('question_count') : 0;

                    $available = max((int) $strictAvailable, (int) $fallbackAvailable, (int) $subjectTotalAvailable);
                    $availability[$code] = ['required' => (int) $required, 'available' => (int) $available];
                }

                $shortage = collect($availability)->contains(fn ($row) => $row['available'] < $row['required']);
                return [...$blueprint->toArray(), 'availability' => [
                    'ready' => ! $shortage,
                    'required' => array_sum(array_column($availability, 'required')),
                    'available' => array_sum(array_column($availability, 'available')),
                    'by_subject' => $availability,
                ]];
            });

        return response()->json(['data' => $data]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'blueprint_id' => ['nullable', 'exists:test_blueprints,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'mode' => ['nullable', 'in:weekly,monthly,chapter,subject,weightage,pyq,custom,full'],
            'test_category' => ['nullable', 'in:full_syllabus,minor_test,semi_major,major_test,subject_test,chapter_test,pyq_test,mistake_revision'],
            'subject' => ['nullable', 'string', 'exists:subjects,code'],
            'chapter_id' => ['nullable', 'integer', 'exists:chapters,id'],
            'chapter_ids' => ['nullable', 'array', 'max:100'],
            'chapter_ids.*' => ['integer', 'exists:chapters,id'],
            'question_ids' => ['nullable', 'array', 'max:180'],
            'question_ids.*' => ['integer', 'exists:questions,id'],
            'question_count' => ['nullable', 'integer', 'between:1,180'],
            'subject_counts' => ['nullable', 'array'],
            'subject_counts.*' => ['integer', 'min:0'],
            'difficulty_mix' => ['nullable', 'array'],
            'difficulty_mix.easy' => ['nullable', 'numeric', 'between:0,1'],
            'difficulty_mix.medium' => ['nullable', 'numeric', 'between:0,1'],
            'difficulty_mix.hard' => ['nullable', 'numeric', 'between:0,1'],
            'weightage_source' => ['nullable', 'in:verified_pyq_frequency,admin_estimate,equal'],
            'pyq_only' => ['nullable', 'boolean'],
            'year' => ['nullable', 'integer', 'between:1980,2100'],
            'source_set' => ['nullable', 'string', 'max:32'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'duration_minutes' => ['nullable', 'integer', 'between:5,300'],
        ]);

        $blueprint = isset($data['blueprint_id']) ? TestBlueprint::findOrFail($data['blueprint_id']) : null;
        if ($blueprint && ! $blueprint->is_active) abort(404);
        $config = $blueprint?->config ?? [];
        $mode = $data['mode'] ?? ($config['generation_mode'] ?? $blueprint?->cadence ?? 'custom');
        $counts = $data['subject_counts'] ?? $config['subject_counts'] ?? [];
        $pyqOnly = (bool) ($data['pyq_only'] ?? $config['pyq_only'] ?? $blueprint?->pyq_only ?? ($mode === 'pyq'));
        $weightageSource = $data['weightage_source'] ?? $config['weightage_source'] ?? 'verified_pyq_frequency';
        $chapterIds = $data['chapter_ids'] ?? (isset($data['chapter_id']) ? [$data['chapter_id']] : ($config['chapter_ids'] ?? []));
        $year = $data['year'] ?? ($config['year'] ?? null);
        $sourceSet = $data['source_set'] ?? ($config['source_set'] ?? null);
        if (empty($data['subject']) && ! empty($config['subject'])) $data['subject'] = $config['subject'];
        if ($mode === 'chapter' && ! count($chapterIds)) {
            throw ValidationException::withMessages(['chapter_id' => ['Chapter test ke liye chapter select karein.']]);
        }
        if ($mode === 'subject' && empty($data['subject'])) {
            throw ValidationException::withMessages(['subject' => ['Subject test ke liye subject select karein.']]);
        }
        if ($mode === 'chapter' && empty($data['subject'])) {
            $chapterSubject = Chapter::whereKey($chapterIds[0])->with('subject:id,code')->first()?->subject?->code;
            if ($chapterSubject) $data['subject'] = $chapterSubject;
        }

        if (count($counts) === 0 && ! empty($data['question_ids'])) {
            $counts = Question::whereIn('id', $data['question_ids'])->where('review_status', 'approved')
                ->with('subject:id,code')->get()->groupBy(fn ($question) => $question->subject->code)
                ->map(fn ($questions) => $questions->count())->all();
        }
        if (count($counts) === 0) {
            $count = (int) ($data['question_count'] ?? $config['question_count'] ?? 20);
            $subjects = isset($data['subject']) ? [$data['subject']] : Subject::pluck('code')->all();
            $perSubject = (int) ceil($count / max(1, count($subjects)));
            $counts = array_fill_keys($subjects, $perSubject);
        }
        if (isset($data['subject'])) {
            $count = (int) ($data['question_count'] ?? array_sum($counts) ?: 20);
            $counts = [$data['subject'] => $count];
        }

        $mix = $data['difficulty_mix'] ?? $config['difficulty_mix'] ?? [];
        $query = Question::query()->where('review_status', 'approved')
            ->when($pyqOnly, fn ($q) => $q->where('is_pyq', true)->whereNotNull('source_url')->whereNotNull('pyq_year')->whereNotNull('source_page')->whereNotNull('source_question_number'))
            ->when($year, fn ($q) => $q->where('pyq_year', $year))
            ->when($sourceSet, fn ($q) => $q->where('source_label', 'like', '%Set '.$sourceSet.'%'))
            ->when(isset($data['difficulty']), fn ($q) => $q->where('difficulty', $data['difficulty']))
            ->when(! empty($data['question_ids']), fn ($q) => $q->whereIn('id', $data['question_ids']))
            ->when(count($chapterIds), fn ($q) => $q->whereIn('chapter_id', $chapterIds))
            ->when($mode === 'weightage', function ($q) use ($weightageSource) {
                if ($weightageSource === 'verified_pyq_frequency') $q->where('is_pyq', true);
            });

        $selected = collect();
        $shortages = [];
        foreach ($counts as $subjectCode => $required) {
            $subject = Subject::where('code', $subjectCode)->first();
            if (! $subject || ! $required) continue;
            $pool = (clone $query)->where('subject_id', $subject->id)->with('chapter:id,weightage_percent,weightage_basis')->get();
            $picked = collect();
            foreach (['easy', 'medium', 'hard'] as $difficulty) {
                $ratio = (float) ($mix[$difficulty] ?? 0);
                $wanted = isset($mix[$difficulty]) ? (int) floor($required * $ratio) : 0;
                if ($wanted) $picked = $picked->merge($this->pick($pool->where('difficulty', $difficulty), $wanted, $mode, $weightageSource));
            }
            $remaining = $required - $picked->count();
            if ($remaining > 0) $picked = $picked->merge($this->pick($pool->whereNotIn('id', $picked->pluck('id')), $remaining, $mode, $weightageSource));
            if ($picked->count() < $required) {
                $subjectFallbackPool = Question::query()
                    ->where('review_status', 'approved')
                    ->where('subject_id', $subject->id)
                    ->whereNotIn('id', $picked->pluck('id'))
                    ->orderByDesc('is_pyq')
                    ->with('chapter:id,weightage_percent,weightage_basis')
                    ->get();
                $picked = $picked->merge($this->pick($subjectFallbackPool, $required - $picked->count(), $mode, $weightageSource));
            }
            $picked = $picked->unique('id')->take($required)->values();
            if ($picked->count() < $required) $shortages[$subjectCode] = ['required' => $required, 'available' => $picked->count()];
            $selected = $selected->merge($picked);
        }

        if ($pyqOnly && $year && $sourceSet) {
            $selected = $selected
                ->sortBy(fn (Question $question) => (int) $question->source_question_number)
                ->values();
        }

        if ($selected->isEmpty()) throw ValidationException::withMessages(['question_bank' => ['No approved questions are available for this test selection.']]);
        if ($shortages) throw ValidationException::withMessages(['question_bank' => ['Approved sourced question bank mein requested count available nahi hai: '.json_encode($shortages)]]);

        $test = DB::transaction(function () use ($data, $blueprint, $config, $mode, $pyqOnly, $selected, $request) {
            $test = GeneratedTest::create([
                'user_id' => $request->user()->id,
                'blueprint_id' => $blueprint?->id,
                'title' => $data['title'] ?? $blueprint?->title ?? ucfirst($mode).' Test',
                'test_category' => $data['test_category'] ?? $blueprint?->test_category ?? match ($mode) { 'chapter' => 'chapter_test', 'subject' => 'subject_test', 'pyq' => 'pyq_test', 'full', 'monthly' => 'full_syllabus', 'mistake_revision' => 'mistake_revision', default => 'minor_test' },
                'generation_mode' => $mode,
                'generation_config' => [...$config, ...$data, 'pyq_only' => $pyqOnly, 'weightage_source' => $weightageSource],
                'duration_minutes' => $data['duration_minutes'] ?? $blueprint?->duration_minutes ?? 60,
                'positive_marks' => $blueprint?->positive_marks ?? 4,
                'negative_marks' => $blueprint?->negative_marks ?? 1,
            ]);
            foreach ($selected->values() as $index => $question) {
                $test->questions()->attach($question->id, ['position' => $index + 1]);
            }
            return $test;
        });

        $test->load(['questions' => fn ($q) => $q->select('questions.id', 'subject_id', 'chapter_id', 'topic_name', 'question_type', 'difficulty', 'stem', 'options', 'is_pyq', 'pyq_year', 'source_label', 'source_url', 'source_page', 'source_question_number')->with(['subject:id,code,name', 'chapter:id,name,slug'])]);
        return response()->json(['data' => $test], 201);
    }

    private function pick($pool, int $count, string $mode, string $weightageSource)
    {
        if ($count <= 0 || $pool->isEmpty()) return collect();
        if ($mode !== 'weightage') return $pool->shuffle()->take($count);

        $groups = $pool->groupBy('chapter_id');
        $weights = $groups->map(function ($questions) use ($weightageSource) {
            if ($weightageSource === 'equal') return 1.0;
            $chapter = $questions->first()->chapter;
            if ($weightageSource === 'admin_estimate' && $chapter?->weightage_basis === 'admin_estimate' && (float) $chapter->weightage_percent > 0) {
                return (float) $chapter->weightage_percent;
            }
            // For verified mode the pool itself is approved PYQ rows, so its size
            // is the observed per-chapter frequency for the selected year scope.
            return max(0.01, (float) $questions->count());
        });
        if ($weights->sum() <= 0) return $pool->shuffle()->take($count);
        $picked = collect();
        while ($picked->count() < $count && $groups->isNotEmpty()) {
            $total = $weights->sum();
            $needle = mt_rand(1, max(1, (int) ($total * 1000))) / 1000;
            $chosen = $weights->keys()->last();
            foreach ($weights as $chapterId => $weight) { $needle -= $weight; if ($needle <= 0) { $chosen = $chapterId; break; } }
            $question = $groups[$chosen]->shuffle()->first();
            $picked->push($question);
            $groups[$chosen] = $groups[$chosen]->reject(fn ($q) => $q->id === $question->id);
            if ($groups[$chosen]->isEmpty()) { $groups->forget($chosen); $weights->forget($chosen); }
        }
        return $picked;
    }
}
