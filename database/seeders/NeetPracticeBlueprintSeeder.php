<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Subject;
use App\Models\TestBlueprint;
use Illuminate\Database\Seeder;

class NeetPracticeBlueprintSeeder extends Seeder
{
    public function run(): void
    {
        $subjectCodes = ['physics', 'chemistry', 'botany', 'zoology'];
        $availableBySubject = Question::query()
            ->where('review_status', 'approved')
            ->join('subjects', 'subjects.id', '=', 'questions.subject_id')
            ->selectRaw('subjects.code as subject_code, COUNT(*) as question_count')
            ->groupBy('subjects.code')
            ->pluck('question_count', 'subject_code');

        // Weekly mixed tests use a manageable 10 questions per NEET subject.
        TestBlueprint::whereIn('slug', ['weekly-balanced-revision', ...array_map(fn ($week) => sprintf('weekly-practice-%02d', $week), range(2, 52))])
            ->get()
            ->each(function (TestBlueprint $blueprint) use ($subjectCodes) {
                $config = $blueprint->config ?? [];
                $config['question_count'] = 40;
                $config['subject_counts'] = array_fill_keys($subjectCodes, 10);
                $config['chapter_ids'] = [];
                $title = preg_replace('/Balanced Practice Test$/', '40Q Balanced Practice Test', $blueprint->title);
                $blueprint->forceFill([
                    'title' => $title,
                    'config' => $config,
                ])->save();
            });

        // Keep full 180-question monthly mocks as a distinct plan; these compact
        // monthly revisions are launchable from the current approved bank.
        for ($month = 1; $month <= 12; $month++) {
            TestBlueprint::updateOrCreate(
                ['slug' => sprintf('monthly-mixed-practice-%02d', $month)],
                [
                    'title' => sprintf('Month %02d - Mixed Revision Practice (48 Questions)', $month),
                    'cadence' => 'monthly',
                    'test_category' => 'minor_test',
                    'config' => [
                        'generation_mode' => 'monthly',
                        'plan_group' => 'monthly',
                        'sequence' => $month,
                        'availability' => 'anytime',
                        'retakes' => 'unlimited',
                        'question_count' => 48,
                        'subject_counts' => array_fill_keys($subjectCodes, 12),
                        'difficulty_mix' => ['easy' => 0.3, 'medium' => 0.5, 'hard' => 0.2],
                        'content_scope' => 'approved_question_bank',
                    ],
                    'duration_minutes' => 60,
                    'positive_marks' => 4,
                    'negative_marks' => 1,
                    'pyq_only' => false,
                    'is_active' => true,
                ],
            );
        }

        // Subject assessments ask for a starter-sized set instead of claiming
        // that the current starter bank supports a full 45-question subject paper.
        TestBlueprint::where('test_category', 'subject_test')->get()->each(function (TestBlueprint $blueprint) use ($availableBySubject) {
            $config = $blueprint->config ?? [];
            $code = $config['subject'] ?? null;
            $count = min(12, (int) ($availableBySubject[$code] ?? 0));
            if (! $code || $count < 1) return;
            $config['question_count'] = $count;
            $config['subject_counts'] = [$code => $count];
            $blueprint->forceFill([
                'title' => preg_replace('/Subject Test \d+/', "Subject Starter Test - {$count} Questions", $blueprint->title),
                'duration_minutes' => max(10, $count * 2),
                'config' => $config,
            ])->save();
        });

        // Replace empty fixed chapter plans with quick plans based on chapters
        // that actually contain approved questions. Full chapter tests remain
        // a separate future expansion once each chapter has a larger bank.
        TestBlueprint::where('slug', 'like', 'chapter-focus-%')->update(['is_active' => false]);
        TestBlueprint::where('slug', 'like', 'chapter-quick-%')->update(['is_active' => false]);
        $questions = Question::query()
            ->where('review_status', 'approved')
            ->orderBy('chapter_id')
            ->orderBy('id')
            ->with(['chapter:id,subject_id,name,slug', 'subject:id,code'])
            ->get()
            ->groupBy('chapter_id');

        $sequence = 0;
        foreach ($subjectCodes as $code) {
            $subject = Subject::where('code', $code)->first();
            if (! $subject) continue;

            $chapterGroups = $questions->filter(fn ($items) => $items->first()->subject_id === $subject->id)->take(3);
            foreach ($chapterGroups as $chapterId => $items) {
                $chapter = $items->first()->chapter;
                if (! $chapter) continue;
                $sequence++;
                $count = $items->count();
                $slug = sprintf('chapter-quick-%02d-%s', $sequence, $chapter->slug);

                TestBlueprint::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'title' => sprintf('Chapter Quick Practice - %s (%d Questions)', $chapter->name, $count),
                        'cadence' => 'custom',
                        'test_category' => 'chapter_test',
                        'config' => [
                            'generation_mode' => 'chapter',
                            'plan_group' => 'chapter',
                            'sequence' => $sequence,
                            'availability' => 'anytime',
                            'retakes' => 'unlimited',
                            'subject' => $code,
                            'chapter_ids' => [(int) $chapterId],
                            'question_count' => $count,
                            'subject_counts' => [$code => $count],
                            'content_scope' => 'approved_question_bank',
                        ],
                        'duration_minutes' => max(5, $count * 2),
                        'positive_marks' => 4,
                        'negative_marks' => 1,
                        'pyq_only' => false,
                        'is_active' => true,
                    ],
                );
            }
        }

        $e1Questions = Question::query()
            ->where('review_status', 'approved')
            ->where('is_pyq', true)
            ->where('pyq_year', 2020)
            ->where('source_label', 'NTA - NEET (UG) 2020 - English and Hindi Set E1')
            ->with('subject:id,code')
            ->get();
        $e1Counts = $e1Questions->groupBy(fn (Question $question) => $question->subject?->code)
            ->map(fn ($items) => $items->count())
            ->filter(fn ($count, $code) => $code && $count > 0)
            ->all();

        if ($e1Questions->isNotEmpty()) {
            TestBlueprint::where('slug', 'pyq-2020-e1-biology-starter')->update(['is_active' => false]);
            TestBlueprint::where('slug', 'pyq-2020-e1-mixed-starter')->update(['is_active' => false]);
            $fullPaper2020 = TestBlueprint::where('slug', 'pyq-full-paper-2020')->first();
            if ($fullPaper2020) {
                $config = $fullPaper2020->config ?? [];
                $config['source_set'] = 'E1';
                if ($e1Questions->count() === 180) {
                    $config['question_count'] = 180;
                    $config['subject_counts'] = $e1Counts;
                }
                $fullPaper2020->forceFill([
                    'title' => 'NEET 2020 Set E1 - Full PYQ Paper (180 Questions)',
                    'config' => $config,
                ])->save();
            }
            $pyqPlans = [];

            foreach (['botany', 'zoology', 'chemistry'] as $code) {
                $count = (int) ($e1Counts[$code] ?? 0);
                if ($count < 1) continue;
                $pyqPlans[] = [
                    'slug' => sprintf('pyq-2020-e1-%s-starter', $code),
                    'title' => sprintf('NEET 2020 E1 - %s Quick PYQ Set (%d Questions)', ucfirst($code), $count),
                    'counts' => [$code => $count],
                    'subject' => $code,
                    'sequence' => ['botany' => 14, 'zoology' => 15, 'chemistry' => 16][$code],
                ];
            }

            foreach ($pyqPlans as $plan) {
                $config = [
                    'generation_mode' => 'pyq',
                    'plan_group' => 'pyq',
                    'sequence' => $plan['sequence'],
                    'availability' => 'anytime',
                    'retakes' => 'unlimited',
                    'year' => 2020,
                    'pyq_only' => true,
                    'question_count' => array_sum($plan['counts']),
                    'subject_counts' => $plan['counts'],
                    'difficulty_mix' => ['easy' => 0.3, 'medium' => 0.5, 'hard' => 0.2],
                    'source_set' => 'E1',
                ];
                if (isset($plan['subject'])) $config['subject'] = $plan['subject'];

                TestBlueprint::updateOrCreate(
                    ['slug' => $plan['slug']],
                    [
                        'title' => $plan['title'],
                        'cadence' => 'custom',
                        'test_category' => 'pyq_test',
                        'config' => $config,
                        'duration_minutes' => max(15, array_sum($plan['counts']) * 2),
                        'positive_marks' => 4,
                        'negative_marks' => 1,
                        'pyq_only' => true,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
