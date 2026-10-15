<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\SubscriberStats;
use Illuminate\View\View;

/** The church's own view of the people who follow it, in full. */
class SubscriberController extends Controller
{
    public function index(Church $church, SubscriberStats $stats): View
    {
        return view('church-admin.subscribers.index', ['church' => $church, 'stats' => $stats->forChurch($church)]);
    }
}
