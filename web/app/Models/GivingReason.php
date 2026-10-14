<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a member can give for at one church ("Tithe", "Building fund"…). Only the church's own staff add, rename, hide or remove
 * them. `category` is the platform category the reason counts as, so older reports and app versions keep working.
 */
class GivingReason extends Model
{
    /** name => [category, description] — what a new church starts with. */
    public const DEFAULTS = [
        'Tithe' => ['tithe', 'Ten percent of your income, given faithfully.'],
        'Offering' => ['offering', 'A freewill gift in the Sunday offering.'],
        'Thanksgiving' => ['offering', 'A gift to say thank you to God.'],
        'Building fund' => ['building_fund', 'Helps the church build and maintain its home.'],
        'Missions' => ['missions', 'Takes the gospel to those who have not heard it.'],
        'Welfare' => ['welfare', 'Helps members and neighbours in need.'],
    ];

    protected $fillable = ['church_id', 'name', 'category', 'description', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort' => 'integer'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('name');
    }

    /** Gives a church the common starting reasons, once (a church that already has reasons is left alone). */
    public static function seedDefaults(Church $church): void
    {
        if (static::where('church_id', $church->id)->exists()) {
            return;
        }

        $sort = 0;
        foreach (self::DEFAULTS as $name => [$category, $description]) {
            static::create(['church_id' => $church->id, 'name' => $name, 'category' => $category, 'description' => $description, 'is_active' => true, 'sort' => $sort++]);
        }
    }
}
