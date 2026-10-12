<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTicket extends Model
{
    use BelongsToChurch;

    protected $fillable = ['church_id', 'event_id', 'name', 'price', 'currency', 'quantity', 'sold_count', 'sales_end_at'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'sales_end_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function remaining(): ?int
    {
        return $this->quantity === null ? null : max(0, $this->quantity - $this->sold_count);
    }

    public function isOnSale(): bool
    {
        return ($this->sales_end_at === null || $this->sales_end_at->isFuture()) && ($this->remaining() ?? 1) > 0;
    }
}
