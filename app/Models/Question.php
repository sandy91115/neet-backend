<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $fillable = [
        'subject_id', 'chapter_id', 'topic_name', 'question_type', 'difficulty', 'stem',
        'options', 'correct_option', 'explanation', 'ncert_reference', 'is_pyq',
        'pyq_year', 'source_label', 'source_url', 'source_page', 'source_question_number',
        'review_status', 'content_license', 'content_fingerprint',
    ];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_pyq' => 'boolean'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
