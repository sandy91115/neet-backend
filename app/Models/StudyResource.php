<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyResource extends Model
{
    protected $fillable = [
        'subject_id', 'chapter_id', 'title', 'provider', 'resource_type', 'description',
        'url', 'url_hash', 'license_note', 'is_official', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_official' => 'boolean', 'is_active' => 'boolean'];
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
