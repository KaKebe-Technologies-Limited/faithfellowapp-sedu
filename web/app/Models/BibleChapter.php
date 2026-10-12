<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A chapter fetched once from the Bible provider and kept forever
 * (scripture doesn't change), so readers aren't slowed by the API.
 */
class BibleChapter extends Model
{
    protected $fillable = ['translation', 'book', 'chapter', 'verses'];

    protected function casts(): array
    {
        return ['verses' => 'array'];
    }
}
