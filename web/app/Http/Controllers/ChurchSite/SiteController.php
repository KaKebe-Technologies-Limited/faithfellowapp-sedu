<?php

namespace App\Http\Controllers\ChurchSite;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\ChurchPageService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Auto-generated church website served at {slug}.faithfellows.app
 * (or a verified custom domain), rendered from the church's builder blocks.
 */
class SiteController extends Controller
{
    public function show(Request $request, ChurchPageService $pages, ?string $subdomain = null): View
    {
        $church = $request->attributes->get('siteChurch')
            ?? Church::where('slug', $subdomain)->firstOrFail();

        abort_unless($church->isActive(), 404);

        $church->load(['media', 'serviceTimes', 'socialLinks', 'branches']);

        return view('church-site.home', [
            'church' => $church,
            'blocks' => collect($church->blocks())->where('enabled', true)->pluck('type'),
            'sections' => $pages->sections($church, $request->user()),
        ]);
    }
}
