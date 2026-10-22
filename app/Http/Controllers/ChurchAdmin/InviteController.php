<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\JoinLinks;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * The church's invite link: https://faithfellows.com/join/CODE. Anyone who opens it is taken to the app (or to its download) and ends up following
 * this church once they have an account. Staff share it, print the QR poster, and see how many people came through it.
 */
class InviteController extends Controller
{
    public function __construct(protected JoinLinks $links) {}

    public function show(Church $church): View
    {
        $url = $this->links->url($church);

        return view('church-admin.invite.show', [
            'church' => $church,
            'ready' => JoinLinks::ready(),
            'code' => $church->join_code,
            'url' => $url,
            'qr' => $url ? QrCode::format('svg')->size(240)->margin(1)->errorCorrection('M')->generate($url) : null,
            'message' => $url ? __('Join :church on the FaithFellows app: sermons, events, prayer and giving in one place. Tap to join: :url', ['church' => $church->name, 'url' => $url]) : null,
            'stats' => $this->links->stats($church),
            'canEdit' => auth()->user()->canInChurch($church, 'settings.manage'),
        ]);
    }

    /** A printable page with the QR code, to put on a noticeboard or in the bulletin. */
    public function poster(Church $church): View
    {
        $url = $this->links->url($church);
        abort_unless($url, 404);

        return view('church-admin.invite.poster', [
            'church' => $church,
            'url' => $url,
            'code' => $church->join_code,
            'qr' => QrCode::format('svg')->size(420)->margin(1)->errorCorrection('H')->generate($url),
        ]);
    }

    public function update(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate(['join_code' => ['required', 'string', 'max:60']]);
        $this->links->change($church, $data['join_code']);
        Audit::log('church.join_code_changed', $church, $church->join_code);

        return back()->with('success', __('The link now uses the code :code. The old link no longer works.', ['code' => $church->join_code]));
    }

    public function regenerate(Church $church): RedirectResponse
    {
        $code = $this->links->regenerate($church);
        Audit::log('church.join_code_changed', $church, $code);

        return back()->with('success', __('You have a new link with the code :code. The old link no longer works.', ['code' => $code]));
    }
}
