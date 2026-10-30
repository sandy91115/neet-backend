<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestAttempt extends Model
{
    protected $fillable = [
        'user_id', 'generated_test_id', 'responses', 'subject_breakdowns', 'correct_count',
        'incorrect_count', 'unattempted_count', 'total_score', 'max_score', 'accuracy_percent',
        'time_taken_seconds', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['responses' => 'array', 'subject_breakdowns' => 'array', 'submitted_at' => 'datetime'];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(GeneratedTest::class, 'generated_test_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
