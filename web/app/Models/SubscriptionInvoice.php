<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Invoice for a church's FaithFellows plan (paid to Kakebe Technologies).
 */
class SubscriptionInvoice extends Model implements Payable
{
    use BelongsToChurch;

    protected $fillable = [
        'church_id', 'plan_id', 'number', 'period_start', 'period_end', 'items', 'subtotal', 'total', 'currency',
        'due_date', 'status', 'reference', 'provider_ref', 'method', 'paid_at', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'due_date' => 'date', 'items' => 'array',
            'subtotal' => 'decimal:2', 'total' => 'decimal:2', 'paid_at' => 'datetime', 'reminded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SubscriptionInvoice $invoice) {
            $invoice->reference ??= 'INV'.Str::upper(Str::random(10));
            $invoice->number ??= 'FF-'.now()->format('Ym').'-'.str_pad((string) (static::whereYear('created_at', now()->year)->count() + 1), 4, '0', STR_PAD_LEFT);
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isPayable(): bool
    {
        return in_array($this->status, ['unpaid', 'overdue'], true);
    }

    public function paymentReference(): string
    {
        return $this->reference;
    }

    public function paymentAmount(): float
    {
        return (float) $this->total;
    }

    public function paymentCurrency(): string
    {
        return $this->currency;
    }

    public function paymentDescription(): string
    {
        return 'FaithFellows invoice '.$this->number;
    }

    public function paymentPhone(): ?string
    {
        return $this->church?->phone;
    }

    public function paymentEmail(): ?string
    {
        return $this->church?->email;
    }

    public function paymentReturnUrl(): string
    {
        return route('church-admin.billing.index', $this->church);
    }
}
