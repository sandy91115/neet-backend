<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GeneratedTest extends Model
{
    protected $fillable = [
        'user_id', 'blueprint_id', 'title', 'test_category', 'generation_mode', 'generation_config',
        'duration_minutes', 'positive_marks', 'negative_marks', 'status', 'scheduled_at',
    ];

    protected function casts(): array
    {
        return ['generation_config' => 'array', 'scheduled_at' => 'datetime'];
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(TestBlueprint::class, 'blueprint_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'generated_test_questions')
            ->withPivot('position')->withTimestamps()->orderByPivot('position');
    }
}
