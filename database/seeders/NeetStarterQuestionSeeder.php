<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class NeetStarterQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(
            File::get(database_path('data/starter_practice_questions.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($rows as $row) {
            $subject = Subject::where('code', $row['subject_id'])->first();
            $chapter = Chapter::where('slug', $row['chapter_id'])->first();
            if (! $subject || ! $chapter || $chapter->subject_id !== $subject->id) continue;

            $fingerprint = hash('sha256', Str::lower(Str::squish($row['stem'])).'|'.json_encode($row['options'], JSON_UNESCAPED_UNICODE));
            Question::firstOrCreate(
                ['content_fingerprint' => $fingerprint],
                [
                    ...$row,
                    'subject_id' => $subject->id,
                    'chapter_id' => $chapter->id,
                    'content_fingerprint' => $fingerprint,
                    'is_pyq' => false,
                    'pyq_year' => null,
                    'source_url' => null,
                    'source_page' => null,
                    'source_question_number' => null,
                    'review_status' => 'approved',
                ],
            );

            // These 24 app-authored starter items were reviewed in this content pass.
            Question::where('content_fingerprint', $fingerprint)->update([
                'review_status' => 'approved',
                'is_pyq' => false,
                'source_label' => 'NEET Prep Pro · original starter practice',
                'source_url' => null,
                'source_page' => null,
                'source_question_number' => null,
                'pyq_year' => null,
            ]);
        }
    }
}
