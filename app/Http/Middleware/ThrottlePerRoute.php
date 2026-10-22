<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * `throttle:30,1` on a route, with each route holding its own count.
 *
 * Laravel counts every route that uses a plain number limit (`throttle:30,1`, `throttle:300,1`...) in ONE bucket for each person, whatever the route is, and each
 * route then judges that shared count by its own number. A game screen that asks for the game once a second (a limit of 300) filled the bucket, and the Start
 * button (a limit of 30) was refused as "Too many attempts" half a minute into the lobby; the answer button (60) was refused near the end of every minute.
 * Here the route is part of the key, so every limit means what it says. The named limiters (`throttle:api`, `throttle:engagement`...) already have their own counts.
 */
class ThrottlePerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $signature = parent::resolveRequestSignature($request);
        $route = $request->route();

        return $route ? sha1($signature.'|'.$request->method().'|'.$route->uri()) : $signature;
    }
}
