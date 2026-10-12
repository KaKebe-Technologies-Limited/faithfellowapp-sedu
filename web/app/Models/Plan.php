<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'price', 'currency', 'interval', 'storage_mb', 'stream_hours', 'max_admins', 'max_branches',
        'features', 'is_active', 'is_default', 'sort',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'features' => 'array', 'is_active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function churches(): HasMany
    {
        return $this->hasMany(Church::class);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::where('is_active', true)->orderBy('sort')->first();
    }
}
