<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chapter extends Model
{
    protected $fillable = [
        'subject_id', 'slug', 'syllabus_version', 'official_unit_code', 'class_level',
        'unit_name', 'name', 'weightage_percent', 'expected_questions', 'weightage_basis',
        'weightage_reference_year', 'high_yield', 'source_url',
    ];

    protected function casts(): array
    {
        return ['high_yield' => 'boolean', 'weightage_percent' => 'float'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('position');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
