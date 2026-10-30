<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class NeetPatternPracticeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(
            File::get(database_path('data/neet_2021_pattern_practice.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($rows as $row) {
            $subject = Subject::where('code', $row['subject_id'])->first();
            $chapter = Chapter::where('slug', $row['chapter_id'])->first();
            if (! $subject || ! $chapter || $chapter->subject_id !== $subject->id) continue;

            $fingerprint = hash('sha256', Str::lower(Str::squish($row['stem'])).'|'.json_encode($row['options'], JSON_UNESCAPED_UNICODE));
            $question = Question::firstOrCreate(
                ['content_fingerprint' => $fingerprint],
                [
                    ...$row,
                    'question_type' => 'single_correct',
                    'subject_id' => $subject->id,
                    'chapter_id' => $chapter->id,
                    'content_fingerprint' => $fingerprint,
                    'is_pyq' => false,
                    'pyq_year' => null,
                    'source_label' => 'Original 2021-pattern practice · PreviousYearQuestions.app',
                    'source_url' => 'https://previousyearquestions.app/neet/2021/',
                    'source_page' => 'Clearly labelled original mock, not the official NEET 2021 paper',
                    'source_question_number' => null,
                    'review_status' => 'approved',
                ],
            );

            // Keep the source distinction and review state if this seeder is rerun.
            $question->forceFill([
                'is_pyq' => false,
                'pyq_year' => null,
                'source_label' => 'Original 2021-pattern practice · PreviousYearQuestions.app',
                'source_url' => 'https://previousyearquestions.app/neet/2021/',
                'source_page' => 'Clearly labelled original mock, not the official NEET 2021 paper',
                'source_question_number' => null,
                'review_status' => 'approved',
            ])->save();
        }
    }
}
