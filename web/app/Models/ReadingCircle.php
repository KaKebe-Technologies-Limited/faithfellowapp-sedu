<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Accountability partners: up to four members of the same church reading a
 * plan together at an agreed time. Joined with a 6-character pairing code.
 */
class ReadingCircle extends Model
{
    use BelongsToChurch;

    public const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    protected $fillable = ['church_id', 'reading_plan_id', 'created_by', 'name', 'code', 'reading_time', 'reading_days', 'timezone', 'last_reminded_on', 'max_members'];

    protected function casts(): array
    {
        return ['reading_days' => 'array', 'last_reminded_on' => 'date'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ReadingPlan::class, 'reading_plan_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'reading_circle_members')->withPivot(['role', 'joined_at'])->orderByPivot('joined_at');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ReadingCircleMember::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ReadingCircleEntry::class);
    }

    public function hasMember(User|int $user): bool
    {
        return $this->memberships()->where('user_id', $user instanceof User ? $user->id : $user)->exists();
    }

    public function isFull(): bool
    {
        return $this->memberships()->count() >= $this->max_members;
    }

    /** "Mon, Wed, Fri at 6:00 AM" */
    public function scheduleLabel(): string
    {
        $days = collect($this->reading_days ?? [])->sort()->values();
        $label = $days->count() === 7 ? __('Every day') : $days->map(fn ($d) => __(self::DAYS[$d]))->implode(', ');

        return $label.' · '.Carbon::parse($this->reading_time)->format('g:i A');
    }

    /**
     * The group reads together: the circle's day is the earliest day any
     * member still has to read.
     */
    public function currentDay(): int
    {
        $plan = $this->plan;
        $userIds = $this->memberships()->pluck('user_id');
        $progress = ReadingPlanProgress::where('reading_plan_id', $this->reading_plan_id)->whereIn('user_id', $userIds)->get();

        if ($progress->isEmpty()) {
            return 1;
        }

        return $progress->map(fn ($p) => $p->setRelation('plan', $plan)->nextDay())->min();
    }
}
