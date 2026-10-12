<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ChurchStaff extends Pivot
{
    use Auditable;

    protected $table = 'church_staff';

    public $incrementing = true;

    protected $fillable = ['church_id', 'user_id', 'church_role_id', 'branch_id', 'is_admin', 'title', 'status', 'invited_by'];

    protected function casts(): array
    {
        return ['is_admin' => 'boolean'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(ChurchRole::class, 'church_role_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function auditLabel(): ?string
    {
        return 'Staff #'.$this->user_id;
    }
}
