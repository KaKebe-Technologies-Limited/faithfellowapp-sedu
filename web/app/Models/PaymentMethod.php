<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A giver's saved mobile money number or tokenised card. Raw card numbers
 * are never stored — only gateway tokens (encrypted) and the last 4 digits.
 */
class PaymentMethod extends Model
{
    protected $fillable = ['user_id', 'type', 'label', 'phone', 'provider_customer_id', 'token', 'last4', 'is_default'];

    protected $hidden = ['token', 'provider_customer_id'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
