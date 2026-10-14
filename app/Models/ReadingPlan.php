<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\HasChurchSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReadingPlan extends Model
{
    use Auditable, BelongsToChurch, HasChurchSlug, SoftDeletes;

    protected $fillable = ['church_id', 'title', 'slug', 'description', 'days_count', 'entries', 'is_published'];

    protected function casts(): array
    {
        return ['entries' => 'array', 'is_published' => 'boolean'];
    }

    public function progress(): HasMany
    {
        return $this->hasMany(ReadingPlanProgress::class);
    }

    /**
     * @return array{day: int, title: ?string, passages: string, reflection: ?string}|null
     */
    public function entryForDay(int $day): ?array
    {
        return collect($this->entries)->firstWhere('day', $day);
    }
}
