<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin Campaign */
class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = $request->routeIs('api.v1.campaigns.show');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type_label,
            'description' => $detail ? plain_text($this->story ?: $this->short_description) : Str::limit(plain_text($this->short_description), 140),
            'cover_url' => $this->picture_url,
            'goal' => (float) $this->goal_amount,
            'raised' => (float) $this->raised_amount,
            'percent' => $this->percent(),
            'currency' => $this->currency,
            'givers' => (int) $this->givers_count,
            'days_left' => $this->daysLeft(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_open' => $this->isOpen(),
            'goal_reached' => $this->goal_reached_at !== null,
            'church' => new ChurchSummaryResource($this->whenLoaded('church')),
            'milestones' => $this->when($detail, fn () => $this->milestones->map(fn ($m) => ['percent' => (int) $m->percent, 'title' => $m->title, 'reached' => $m->reached_at !== null])->values()),
            'updates' => $this->when($detail, fn () => $this->updates->map(fn ($u) => [
                'id' => $u->id, 'title' => $u->title, 'body' => plain_text($u->body), 'published_at' => $u->published_at?->toIso8601String(),
            ])->values()),
            'leaderboard' => $this->when($detail, fn () => app(CampaignService::class)->leaderboard($this->resource, 5)->map(fn ($r) => ['name' => $r->name, 'total' => (float) $r->total])->values()),
            'recent_givers' => $this->when($detail, fn () => $this->donations()->with('user:id,name')->succeeded()->latest('paid_at')->take(6)->get()->map(fn ($d) => [
                'name' => $d->display_name, 'amount' => (float) $d->amount, 'at' => $d->paid_at?->toIso8601String(),
            ])->values()),
            'comments' => $this->when($detail, fn () => $this->comments()->with('user:id,name')->visible()->whereNull('parent_id')->latest()->take(20)->get()->map(fn ($c) => [
                'id' => $c->id, 'author' => $c->user?->name, 'body' => $c->body, 'at' => $c->created_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
