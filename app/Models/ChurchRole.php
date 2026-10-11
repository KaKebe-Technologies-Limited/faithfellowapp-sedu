<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class ChurchRole extends Model
{
    use Auditable, BelongsToChurch;

    protected $fillable = ['church_id', 'name', 'slug', 'description', 'permissions', 'is_preset'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_preset' => 'boolean'];
    }

    public function staff(): HasMany
    {
        return $this->hasMany(ChurchStaff::class);
    }

    /**
     * Flat list of every permission key in the catalog.
     *
     * @return array<int, string>
     */
    public static function catalog(): array
    {
        return array_keys(Arr::collapse(config('faithfellows.church_permissions')));
    }

    public function grants(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }
}
