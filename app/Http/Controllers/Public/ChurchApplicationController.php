<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Church\ChurchApplicationRequest;
use App\Services\ChurchOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChurchApplicationController extends Controller
{
    public function create(Request $request): View
    {
        // Bring guests back here after they register or log in.
        if (! $request->user()) {
            $request->session()->put('url.intended', route('church.apply'));
        }

        return view('public.churches.apply');
    }

    public function store(ChurchApplicationRequest $request, ChurchOnboardingService $onboarding): RedirectResponse
    {
        $church = $onboarding->apply($request->user(), $request->safe()->except('agree'));

        return redirect()->route('church-admin.setup', [$church, 'step' => 'branding'])
            ->with('success', __('Thank you! :church has been submitted for approval. Meanwhile, let’s finish setting up your profile.', ['church' => $church->name]));
    }
}
