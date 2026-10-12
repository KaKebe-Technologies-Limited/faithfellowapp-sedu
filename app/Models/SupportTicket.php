<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const CATEGORIES = ['technical' => 'Technical problem', 'billing' => 'Billing & plan', 'payments' => 'Giving & payments', 'account' => 'Account & access', 'feature' => 'Feature request', 'other' => 'Other'];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    protected $fillable = ['reference', 'church_id', 'user_id', 'subject', 'category', 'priority', 'status', 'assigned_to', 'last_reply_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['last_reply_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::created(function (SupportTicket $ticket) {
            if (! $ticket->reference) {
                $ticket->forceFill(['reference' => 'FF-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class)->oldest();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'pending']);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['resolved', 'closed'], true);
    }
}
