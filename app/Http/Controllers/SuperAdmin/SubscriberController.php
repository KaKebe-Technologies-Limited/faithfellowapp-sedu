<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\SubscriberStats;
use Illuminate\View\View;

/** The platform owner's view of every church's followers, payments and standing. */
class SubscriberController extends Controller
{
    public function index(SubscriberStats $stats): View
    {
        return view('super-admin.subscribers.index', ['rows' => $stats->overview()]);
    }

    public function show(Church $church, SubscriberStats $stats): View
    {
        return view('super-admin.subscribers.show', ['church' => $church, 'stats' => $stats->forChurch($church)]);
    }
}
