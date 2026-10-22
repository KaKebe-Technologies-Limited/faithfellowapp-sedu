<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\JoinLinks;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * The page a church's join link opens (https://faithfellows.com/join/FF47KM): the church, a button that opens the app on it and the
 * downloads for people who do not have it yet. It also notes the visit, so the app can offer the church after a fresh install.
 */
class JoinController extends Controller
{
    public function show(Request $request, JoinLinks $links, string $code): Response
    {
        $church = $links->find($code);
        if (! $church) {
            return response()->view('public.join', ['church' => null, 'typed' => JoinLinks::normalise($code)], 404);
        }

        $links->record($church, $request);
        $code = (string) $church->join_code;
        $platform = JoinLinks::platformOf((string) $request->userAgent());
        $scheme = (string) config('faithfellows.app.scheme');
        $android = ff_app_download_url();

        return response()->view('public.join', [
            'church' => $church,
            'code' => $code,
            'platform' => $platform,
            'followers' => Cache::remember("join-followers:{$church->id}", 300, fn () => $church->subscriptions()->where('status', 'active')->count()),
            'openUrl' => "{$scheme}://join/{$code}",
            'androidUrl' => $android,
            'iosUrl' => ff_ios_app_url(),
            'intentUrl' => $this->intentUrl($scheme, $code, $android),
            'qr' => $platform === 'other' ? QrCode::format('svg')->size(220)->margin(1)->errorCorrection('M')->generate(route('join.show', $code)) : null,
            'webUrl' => route('churches.join', $church),
        ]);
    }

    /**
     * Chrome on Android: opens the app if it is installed, else goes to the download. When the download is the Play Store the church code
     * rides along as the install referrer, which the app reads the first time it opens.
     */
    protected function intentUrl(string $scheme, string $code, ?string $fallback): string
    {
        $package = (string) config('faithfellows.app.android_package');
        if ($fallback && str_contains($fallback, 'play.google.com') && ! str_contains($fallback, 'referrer=')) {
            $fallback .= (str_contains($fallback, '?') ? '&' : '?').'referrer='.rawurlencode("faithfellows_join={$code}");
        }
        $parts = ["scheme={$scheme}", "package={$package}"];
        if ($fallback) {
            $parts[] = 'S.browser_fallback_url='.rawurlencode($fallback);
        }

        return "intent://join/{$code}#Intent;".implode(';', $parts).';end';
    }
}
