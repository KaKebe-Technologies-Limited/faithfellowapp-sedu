<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A short inspiring message for the home screen: a morning, a mid-morning or an evening one. */
class Inspiration extends Model
{
    public const SLOTS = ['morning' => 'Morning', 'midmorning' => 'Mid-morning', 'evening' => 'Evening'];

    protected $fillable = ['slot', 'body', 'reference', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
