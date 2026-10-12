<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\StreamingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * nginx-rtmp callbacks (on_publish / on_publish_done).
 *
 *   application live {
 *       on_publish      https://faithfellows.app/webhooks/rtmp/publish;
 *       on_publish_done https://faithfellows.app/webhooks/rtmp/done;
 *   }
 *
 * The stream key arrives as "name". Unknown keys are rejected (403) so only
 * churches with a valid key can broadcast.
 */
class RtmpController extends Controller
{
    public function __construct(protected StreamingService $streaming) {}

    public function publish(Request $request): Response
    {
        $stream = $this->streaming->findByKey((string) $request->input('name'));
        if (! $stream || ! $stream->church?->isActive()) {
            return response('Forbidden', 403);
        }

        $this->streaming->start($stream);

        return response('OK');
    }

    public function done(Request $request): Response
    {
        $stream = $this->streaming->findByKey((string) $request->input('name'));
        if ($stream) {
            $this->streaming->stop($stream);
        }

        return response('OK');
    }
}
