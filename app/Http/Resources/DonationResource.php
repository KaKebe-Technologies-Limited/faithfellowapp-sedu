<?php

namespace App\Http\Resources;

use App\Models\Donation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Donation */
class DonationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'category' => $this->category,
            'method' => $this->method,
            'receipt_number' => $this->receipt_number,
            'receipt_url' => $this->status === 'succeeded' ? route('giving.receipt', $this->reference) : null,
            'failure_reason' => $this->when($this->status === 'failed', $this->failure_reason),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'church' => new ChurchSummaryResource($this->whenLoaded('church')),
            'campaign' => $this->whenLoaded('campaign', fn () => $this->campaign ? ['id' => $this->campaign->id, 'title' => $this->campaign->title] : null),
        ];
    }
}
