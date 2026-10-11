<?php

namespace App\Services;

use App\Models\Church;
use App\Models\User;

/**
 * Builds the data shown on a church's public page and auto-generated website.
 * Each content module registers itself here as it is built, so the page and
 * the website builder blocks share one data source.
 */
class ChurchPageService
{
    /**
     * @return array<string, mixed>
     */
    public function sections(Church $church, ?User $viewer = null): array
    {
        return [
            'viewer' => $viewer,
            'isStaff' => $viewer?->isStaffOf($church) ?? false,
        ];
    }
}
