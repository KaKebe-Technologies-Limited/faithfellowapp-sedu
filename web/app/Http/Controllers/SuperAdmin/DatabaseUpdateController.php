<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\DatabaseUpdates;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Applies the database changes that came with an update. Hosting without a terminal or a cron job needs a button for this;
 * everywhere else it simply shows "up to date".
 */
class DatabaseUpdateController extends Controller
{
    public function index(): View
    {
        return view('super-admin.updates.index', ['pending' => DatabaseUpdates::pending()]);
    }

    public function apply(): RedirectResponse
    {
        $pending = DatabaseUpdates::pending();
        if ($pending === []) {
            return back()->with('status', __('The database is already up to date.'));
        }

        $output = DatabaseUpdates::apply();
        Audit::log('system.migrate', null, __(':count database change(s) applied', ['count' => count($pending)]), [], ['migrations' => $pending]);

        return back()->with('status', __(':count database change(s) applied.', ['count' => count($pending)]))->with('output', $output);
    }
}
