<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\StudyResource;
use App\Models\Subject;
use App\Models\TestBlueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class NeetSyllabusSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = collect([
            ['code' => 'physics', 'name' => 'Physics'],
            ['code' => 'chemistry', 'name' => 'Chemistry'],
            ['code' => 'botany', 'name' => 'Botany'],
            ['code' => 'zoology', 'name' => 'Zoology'],
        ])->mapWithKeys(fn (array $data) => [
            $data['code'] => Subject::updateOrCreate(
                ['code' => $data['code']],
                [...$data, 'questions_per_full_test' => 45, 'max_marks' => 180],
            ),
        ]);

        $official = json_decode(
            File::get(database_path('data/neet_2026_syllabus.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($official['subjects'] as $sourceSubject) {
            $appSubjects = $sourceSubject['id'] === 'biology'
                ? ['botany', 'zoology']
                : [$sourceSubject['id']];

            foreach ($sourceSubject['units'] as $unit) {
                foreach ($appSubjects as $appSubject) {
                    $chapter = Chapter::updateOrCreate(
                        ['slug' => $unit['id'].'-'.$appSubject],
                        [
                            'subject_id' => $subjects[$appSubject]->id,
                            'syllabus_version' => $official['version'],
                            'official_unit_code' => $unit['id'],
                            'class_level' => '11th/12th',
                            'unit_name' => sprintf('Official Unit %02d', $unit['number']),
                            'name' => $unit['title'],
                            // NTA does not publish official chapter question weightage.
                            // Keep this blank until it can be calculated from sourced PYQs.
                            'weightage_percent' => 0,
                            'expected_questions' => 0,
                            'weightage_basis' => 'unverified',
                            'weightage_reference_year' => null,
                            'high_yield' => false,
                            'source_url' => $official['sourceUrl'],
                        ],
                    );

                    foreach ($unit['topics'] as $position => $topic) {
                        DB::table('topics')->updateOrInsert(
                            ['chapter_id' => $chapter->id, 'name' => $topic],
                            ['position' => $position + 1, 'updated_at' => now(), 'created_at' => now()],
                        );
                    }
                }
            }
        }

        foreach ([
            [
                'title' => 'NEET UG Official Syllabus 2026', 'provider' => 'NTA / NMC',
                'resource_type' => 'syllabus', 'description' => 'Official examination syllabus and unit list for NEET UG 2026.',
                'url' => $official['sourceUrl'], 'license_note' => 'Official source; open at provider site.', 'is_official' => true,
            ],
            [
                'title' => 'NTA NEET Archive', 'provider' => 'National Testing Agency',
                'resource_type' => 'pyq_source', 'description' => 'Officially published examination notices, papers, and answer-key documents.',
                'url' => 'https://neet.nta.nic.in/archive/', 'license_note' => 'Use each paper and final answer-key notice as source of record.', 'is_official' => true,
            ],
            [
                'title' => 'NCERT Textbooks', 'provider' => 'NCERT',
                'resource_type' => 'textbook', 'description' => 'Official Class XI and XII textbooks.',
                'url' => 'https://ncert.nic.in/textbook.php', 'license_note' => 'Open at NCERT; do not re-host restricted files.', 'is_official' => true,
            ],
            [
                'title' => 'NCERT Exemplar Problems', 'provider' => 'NCERT',
                'resource_type' => 'practice', 'description' => 'Official exemplar problems and answer sections.',
                'url' => 'https://ncert.nic.in/exemplar-problems.php?ln=en', 'license_note' => 'Open at NCERT; do not re-host restricted files.', 'is_official' => true,
            ],
            [
                'title' => 'SATHEE NEET Course', 'provider' => 'Ministry of Education / IIT Kanpur',
                'resource_type' => 'course', 'description' => 'Free NEET preparation resources, classes, and practice tools.',
                'url' => 'https://sathee.iitk.ac.in/sathee-neet/imp-for-exam/neet-course/', 'license_note' => 'Use resources under the provider terms.', 'is_official' => true,
            ],
            [
                'title' => 'SATHEE NEET Previous-Year Questions', 'provider' => 'Ministry of Education / IIT Kanpur',
                'resource_type' => 'pyq_source', 'description' => 'Free NEET PYQ practice and exam-year navigation hosted by SATHEE.',
                'url' => 'https://sathee.iitk.ac.in/sathee-neet/neet-pyq/', 'license_note' => 'Practice on the provider site; this app stores only verified question records.', 'is_official' => true,
            ],
            [
                'title' => 'SATHEE NEET Chapter-Wise PYQ Practice', 'provider' => 'Ministry of Education / IIT Kanpur',
                'resource_type' => 'pyq_source', 'description' => 'External chapter-wise PYQ practice pages for topic-focused revision.',
                'url' => 'https://sathee.iitk.ac.in/sathee-neet/previous-year-neet-exam-problems/pyq/', 'license_note' => 'Practice on the provider site; question-level imports need source verification.', 'is_official' => true,
            ],
            [
                'title' => 'NTA NEET UG 2025 Final Answer Key', 'provider' => 'National Testing Agency',
                'resource_type' => 'pyq_source', 'description' => 'Official final answer-key notice for checking paper solutions.',
                'url' => 'https://neet.nta.nic.in/document/final-answer-keys-for-neetug-2025/', 'license_note' => 'Match the correct paper code and final key before approving imported PYQs.', 'is_official' => true,
            ],
            [
                'title' => 'NTA NEET UG 2026 Re-Examination Final Answer Key', 'provider' => 'National Testing Agency',
                'resource_type' => 'pyq_source', 'description' => 'Official final answer-key notice for the 2026 re-examination.',
                'url' => 'https://neet.nta.nic.in/final-answer-keys-for-neet-ug-2026-re-examination/', 'license_note' => 'Match the correct paper code and final key before approving imported PYQs.', 'is_official' => true,
            ],
            [
                'title' => 'NEET UG 2020 Question Paper - English Set E1', 'provider' => 'National Testing Agency',
                'resource_type' => 'pyq_source', 'description' => 'Official archived question paper PDF. Use alongside the matching final answer key.',
                'url' => 'https://neet.nta.nic.in/document/english-set-e1-neet-qp-2020/', 'license_note' => 'Paper remains on NTA; record its actual PDF page and question number when importing.', 'is_official' => true,
            ],
            [
                'title' => 'NEET UG 2020 Final Answer Keys - Set E1', 'provider' => 'National Testing Agency',
                'resource_type' => 'pyq_source', 'description' => 'Final answer keys used for the NEET UG 2020 result; includes Booklet E1.',
                'url' => 'https://www.nta.ac.in/Download/Notice/Notice_20201016104324.pdf', 'license_note' => 'Match the E1 paper question number to the E1 final key.', 'is_official' => true,
            ],
        ] as $order => $resource) {
            StudyResource::updateOrCreate(
                ['url_hash' => hash('sha256', $resource['url'])],
                [...$resource, 'url_hash' => hash('sha256', $resource['url']), 'is_active' => true, 'sort_order' => $order],
            );
        }

        $blueprints = [];
        $allChapters = Chapter::with('subject:id,code')->orderBy('id')->get();
        $chaptersBySubject = $allChapters->groupBy(fn (Chapter $chapter) => $chapter->subject?->code);
        $subjectCodes = ['physics', 'chemistry', 'botany', 'zoology'];
        $balancedCounts = ['physics' => 15, 'chemistry' => 15, 'botany' => 15, 'zoology' => 15];
        $fullCounts = ['physics' => 45, 'chemistry' => 45, 'botany' => 45, 'zoology' => 45];
        $difficultyMix = ['easy' => 0.3, 'medium' => 0.5, 'hard' => 0.2];

        for ($week = 1; $week <= 52; $week++) {
            $chapterIds = [];
            foreach ($subjectCodes as $code) {
                $subjectChapters = $chaptersBySubject->get($code, collect())->values();
                if ($subjectChapters->isNotEmpty()) $chapterIds[] = $subjectChapters[($week - 1) % $subjectChapters->count()]->id;
            }
            $blueprints[] = [
                'slug' => $week === 1 ? 'weekly-balanced-revision' : sprintf('weekly-practice-%02d', $week),
                'title' => sprintf('Week %02d - Balanced Practice Test', $week),
                'cadence' => 'weekly', 'test_category' => 'minor_test', 'duration_minutes' => 90,
                'config' => ['generation_mode' => 'weekly', 'plan_group' => 'weekly', 'sequence' => $week, 'availability' => 'anytime', 'retakes' => 'unlimited', 'question_count' => 60, 'subject_counts' => $balancedCounts, 'chapter_ids' => $chapterIds, 'difficulty_mix' => $difficultyMix],
            ];
        }

        for ($month = 1; $month <= 12; $month++) {
            $blueprints[] = [
                'slug' => $month === 1 ? 'monthly-full-syllabus' : sprintf('monthly-full-syllabus-%02d', $month),
                'title' => sprintf('Month %02d - Full NEET Mock', $month),
                'cadence' => 'monthly', 'test_category' => 'full_syllabus', 'duration_minutes' => 180,
                'config' => ['generation_mode' => 'monthly', 'plan_group' => 'monthly', 'sequence' => $month, 'availability' => 'anytime', 'retakes' => 'unlimited', 'question_count' => 180, 'subject_counts' => $fullCounts, 'difficulty_mix' => $difficultyMix],
            ];
        }

        foreach (range(2015, 2026) as $sequence => $year) {
            $blueprints[] = [
                'slug' => "pyq-full-paper-{$year}", 'title' => "NEET {$year} - Verified PYQ Paper Practice",
                'cadence' => 'custom', 'test_category' => 'pyq_test', 'duration_minutes' => 180,
                'pyq_only' => true,
                'config' => ['generation_mode' => 'pyq', 'plan_group' => 'pyq', 'sequence' => $sequence + 1, 'availability' => 'anytime', 'retakes' => 'unlimited', 'year' => $year, 'pyq_only' => true, 'question_count' => 180, 'subject_counts' => $fullCounts, 'difficulty_mix' => $difficultyMix],
            ];
        }

        for ($sequence = 1; $sequence <= 12; $sequence++) {
            $code = $subjectCodes[($sequence - 1) % count($subjectCodes)];
            $iteration = intdiv($sequence - 1, count($subjectCodes)) + 1;
            $blueprints[] = [
                'slug' => sprintf('subject-%s-revision-%02d', $code, $iteration),
                'title' => ucfirst($code).sprintf(' - Subject Test %02d', $iteration),
                'cadence' => 'custom', 'test_category' => 'subject_test', 'duration_minutes' => 60,
                'config' => ['generation_mode' => 'subject', 'plan_group' => 'subject', 'sequence' => $sequence, 'availability' => 'anytime', 'retakes' => 'unlimited', 'subject' => $code, 'question_count' => 45, 'subject_counts' => [$code => 45], 'difficulty_mix' => $difficultyMix],
            ];
        }

        for ($sequence = 1; $sequence <= 12; $sequence++) {
            $chapter = $allChapters[($sequence - 1) % max(1, $allChapters->count())] ?? null;
            if (! $chapter) continue;
            $code = $chapter->subject?->code;
            $blueprints[] = [
                'slug' => sprintf('chapter-focus-%02d-%s', $sequence, $chapter->slug),
                'title' => sprintf('Chapter Focus %02d - %s', $sequence, $chapter->name),
                'cadence' => 'custom', 'test_category' => 'chapter_test', 'duration_minutes' => 40,
                'config' => ['generation_mode' => 'chapter', 'plan_group' => 'chapter', 'sequence' => $sequence, 'availability' => 'anytime', 'retakes' => 'unlimited', 'subject' => $code, 'chapter_ids' => [$chapter->id], 'question_count' => 20, 'subject_counts' => [$code => 20], 'difficulty_mix' => $difficultyMix],
            ];
        }

        foreach ($blueprints as $blueprint) {
            TestBlueprint::updateOrCreate(
                ['slug' => $blueprint['slug']],
                [...$blueprint, 'positive_marks' => 4, 'negative_marks' => 1, 'pyq_only' => $blueprint['pyq_only'] ?? false, 'is_active' => true],
            );
        }

        // Retire the two older year plans when the rolling 12-year PYQ schedule advances.
        TestBlueprint::whereIn('slug', ['pyq-full-paper-2013', 'pyq-full-paper-2014'])->update(['is_active' => false]);
    }
}
