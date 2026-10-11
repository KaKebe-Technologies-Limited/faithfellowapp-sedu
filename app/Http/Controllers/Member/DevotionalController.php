<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Devotional;
use App\Services\EngagementTracker;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DevotionalController extends Controller
{
    public function index(Request $request): View
    {
        $churchIds = $request->user()->memberChurchIds();

        $today = Devotional::with(['church.media', 'media'])->forChurches($churchIds)->published()
            ->whereDate('publish_date', today())->get();

        $devotionals = Devotional::with(['church.media'])->forChurches($churchIds)->published()
            ->whereDate('publish_date', '<', today())
            ->latest('publish_date')->paginate(15);

        return view('member.devotionals.index', compact('today', 'devotionals'));
    }

    public function show(Devotional $devotional, EngagementTracker $tracker): View
    {
        $this->authorize('view', $devotional);
        $devotional->load(['church.media', 'media', 'author']);
        $tracker->view($devotional);

        return view('member.devotionals.show', compact('devotional'));
    }

    public function announcement(Announcement $announcement, EngagementTracker $tracker): View
    {
        $this->authorize('view', $announcement);
        $announcement->load(['church.media', 'media', 'author']);
        $tracker->view($announcement);

        return view('member.announcements.show', compact('announcement'));
    }
}
