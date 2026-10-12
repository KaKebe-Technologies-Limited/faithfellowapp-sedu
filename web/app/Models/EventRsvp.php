<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventRsvp extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'church_id', 'event_id', 'user_id', 'event_ticket_id', 'quantity', 'status', 'code', 'amount',
        'donation_id', 'seat', 'checked_in_at', 'checked_in_by',
    ];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::creating(function (EventRsvp $rsvp) {
            $rsvp->code ??= static::newCode();
        });
    }

    public static function newCode(): string
    {
        do {
            $code = Str::upper(Str::random(10));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(EventTicket::class, 'event_ticket_id');
    }

    public function checkInUrl(): string
    {
        return route('church-admin.events.check-in.code', [$this->event->church, $this->event, $this->code]);
    }
}
