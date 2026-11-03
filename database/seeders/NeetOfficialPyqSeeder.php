<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class NeetOfficialPyqSeeder extends Seeder
{
    private const PAPER_URL = 'https://neet.nta.nic.in/document/eng-hindi-set-e1-neet-qp-2020/';

    private const SOURCE_LABEL = 'NTA - NEET (UG) 2020 - English and Hindi Set E1';

    public function run(): void
    {
        $rows = json_decode(
            File::get(database_path('data/neet_2020_e1_pyq.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($rows as $row) {
            $subject = Subject::where('code', $row['subject_id'])->first();
            $chapter = Chapter::where('slug', $row['chapter_id'])->first();
            if (! $subject || ! $chapter || $chapter->subject_id !== $subject->id) continue;

            $fingerprint = hash('sha256', Str::lower(Str::squish($row['stem']).'|'.json_encode($row['options'], JSON_UNESCAPED_UNICODE)));
            $matches = Question::query()
                ->where('is_pyq', true)
                ->where('pyq_year', 2020)
                ->where('source_url', self::PAPER_URL)
                ->where('source_question_number', (string) $row['question_number'])
                ->orderBy('id')
                ->get();

            $question = $matches->first(fn (Question $match) => $match->content_fingerprint === $fingerprint)
                ?? $matches->first()
                ?? Question::where('content_fingerprint', $fingerprint)->first()
                ?? new Question();
            $question->forceFill([
                'subject_id' => $subject->id,
                'chapter_id' => $chapter->id,
                'topic_name' => $row['topic_name'],
                'question_type' => 'single_correct',
                'difficulty' => $row['difficulty'],
                'stem' => $row['stem'],
                'options' => $row['options'],
                'correct_option' => $row['correct_option'],
                'explanation' => $row['explanation'],
                'is_pyq' => true,
                'pyq_year' => 2020,
                'source_label' => self::SOURCE_LABEL,
                'source_url' => self::PAPER_URL,
                'source_page' => 'PDF page '.$row['pdf_page'].' - Booklet E1',
                'source_question_number' => (string) $row['question_number'],
                'review_status' => 'approved',
                'content_fingerprint' => $fingerprint,
            ])->save();

            // Preserve any duplicate row for audit without counting it as approved content.
            foreach ($matches as $duplicate) {
                if ($duplicate->id !== $question->id) {
                    $duplicate->forceFill(['review_status' => 'rejected'])->save();
                }
            }
        }

        $multiYearPath = database_path('data/neet_2015_2025_pyq.json');
        if (File::exists($multiYearPath)) {
            $multiYearRows = json_decode(
                File::get($multiYearPath),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            foreach ($multiYearRows as $row) {
                $subject = Subject::where('code', $row['subject_id'])->first();
                $chapter = Chapter::where('slug', $row['chapter_id'])->first();
                if (! $subject || ! $chapter || $chapter->subject_id !== $subject->id) continue;

                $fingerprint = hash('sha256', Str::lower(Str::squish($row['stem']).'|'.json_encode($row['options'], JSON_UNESCAPED_UNICODE)));
                $question = Question::where('content_fingerprint', $fingerprint)->first() ?? new Question();
                $question->forceFill([
                    'subject_id' => $subject->id,
                    'chapter_id' => $chapter->id,
                    'topic_name' => $row['topic_name'],
                    'question_type' => 'single_correct',
                    'difficulty' => $row['difficulty'],
                    'stem' => $row['stem'],
                    'options' => $row['options'],
                    'correct_option' => $row['correct_option'],
                    'explanation' => $row['explanation'],
                    'ncert_reference' => $row['ncert_reference'] ?? null,
                    'is_pyq' => true,
                    'pyq_year' => (int) $row['pyq_year'],
                    'source_label' => $row['source_label'] ?? ('NTA / CBSE NEET (UG) '.$row['pyq_year'].' Official Paper'),
                    'source_url' => $row['source_url'] ?? 'https://neet.nta.nic.in/archive/',
                    'source_page' => 'PDF page '.$row['pdf_page'],
                    'source_question_number' => (string) $row['question_number'],
                    'review_status' => 'approved',
                    'content_fingerprint' => $fingerprint,
                ])->save();
            }
        }
    }
}
