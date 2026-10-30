<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestBlueprint extends Model
{
    protected $fillable = [
        'slug', 'title', 'cadence', 'test_category', 'config', 'duration_minutes',
        'positive_marks', 'negative_marks', 'pyq_only', 'is_active',
    ];

    protected function casts(): array
    {
        return ['config' => 'array', 'pyq_only' => 'boolean', 'is_active' => 'boolean'];
    }

    public function tests(): HasMany
    {
        return $this->hasMany(GeneratedTest::class, 'blueprint_id');
    }
}
