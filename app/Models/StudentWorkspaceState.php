<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentWorkspaceState extends Model
{
    protected $fillable = [
        'user_id', 'syllabus', 'official_syllabus_progress', 'attempts', 'series_tests', 'daily_tasks', 'mistakes',
        'bookmarks', 'practice_selections', 'revealed_solutions', 'saved_practice_mistakes',
    ];

    protected function casts(): array
    {
        return [
            'syllabus' => 'array', 'official_syllabus_progress' => 'array', 'attempts' => 'array', 'series_tests' => 'array', 'daily_tasks' => 'array',
            'mistakes' => 'array', 'bookmarks' => 'array', 'practice_selections' => 'array',
            'revealed_solutions' => 'array', 'saved_practice_mistakes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
