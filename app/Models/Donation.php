<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Donation extends Model implements Payable
{
    use BelongsToChurch, HasFactory, SoftDeletes;

    protected $fillable = [
        'church_id', 'branch_id', 'user_id', 'campaign_id', 'campaign_team_id', 'pledge_id', 'recurring_gift_id', 'event_rsvp_id',
        'small_group_id', 'payout_id', 'category', 'giving_reason_id', 'reason', 'amount', 'fee', 'currency', 'method', 'reference', 'provider_ref', 'phone',
        'status', 'failure_reason', 'is_anonymous', 'show_on_leaderboard', 'giver_name', 'note', 'provider_payload',
        'receipt_number', 'receipt_sent_at', 'paid_at', 'refunded_at', 'refund_reason', 'recorded_by', 'reconciled_at',
        'country', 'ip_address',
    ];

    protected $hidden = ['provider_payload'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'is_anonymous' => 'boolean',
            'show_on_leaderboard' => 'boolean',
            'provider_payload' => 'array',
            'receipt_sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Donation $donation) {
            $donation->reference ??= 'FF'.now()->format('ymd').Str::upper(Str::random(8));
        });
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(CampaignTeam::class, 'campaign_team_id');
    }

    public function pledge(): BelongsTo
    {
        return $this->belongsTo(Pledge::class);
    }

    public function recurringGift(): BelongsTo
    {
        return $this->belongsTo(RecurringGift::class);
    }

    public function rsvp(): BelongsTo
    {
        return $this->belongsTo(EventRsvp::class, 'event_rsvp_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ── Scopes & helpers ─────────────────────────────────────────────────

    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->where('status', 'succeeded');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['succeeded', 'failed', 'cancelled', 'refunded'], true);
    }

    /** Name shown on receipts / leaderboards. */
    public function getDisplayNameAttribute(): string
    {
        if ($this->is_anonymous) {
            return __('Anonymous');
        }

        return $this->giver_name ?: ($this->user?->name ?? __('Anonymous'));
    }

    public function givingReason(): BelongsTo
    {
        return $this->belongsTo(GivingReason::class);
    }

    /** What the gift was for: the church's own reason as it was given, else the platform category. */
    public function getCategoryLabelAttribute(): string
    {
        return $this->reason ?: config('faithfellows.giving_categories.'.$this->category, Str::headline($this->category));
    }

    public function getMethodLabelAttribute(): string
    {
        return config('faithfellows.payment_methods.'.$this->method, Str::headline($this->method));
    }

    // ── Payable ──────────────────────────────────────────────────────────

    public function paymentReference(): string
    {
        return $this->reference;
    }

    public function paymentAmount(): float
    {
        return (float) $this->amount;
    }

    public function paymentCurrency(): string
    {
        return $this->currency;
    }

    public function paymentDescription(): string
    {
        $church = $this->relationLoaded('church') ? $this->church : $this->church()->first();

        return Str::limit(($church?->name ?? 'FaithFellows').' — '.$this->category_label, 60, '');
    }

    public function paymentPhone(): ?string
    {
        return $this->phone;
    }

    public function paymentEmail(): ?string
    {
        return $this->user?->email;
    }

    /** Set (not saved) when a payment starts from the app: the card checkout then returns to a plain "back to the app" page. */
    public ?string $returnTo = null;

    public function paymentReturnUrl(): string
    {
        return $this->returnTo === 'app' ? route('pay.return', $this->reference) : route('give.status', $this->reference);
    }
}
