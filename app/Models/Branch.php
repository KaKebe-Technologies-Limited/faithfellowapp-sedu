<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use Auditable, BelongsToChurch, HasFactory, SoftDeletes;

    protected $fillable = ['church_id', 'name', 'slug', 'city', 'address', 'lat', 'lng', 'phone', 'leader_name', 'is_main'];

    protected function casts(): array
    {
        return ['is_main' => 'boolean', 'lat' => 'float', 'lng' => 'float'];
    }

    public function serviceTimes(): HasMany
    {
        return $this->hasMany(ServiceTime::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(ChurchStaff::class);
    }
}
