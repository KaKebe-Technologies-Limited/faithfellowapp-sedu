<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * When the server fails on something it did not expect (a 500), the person is shown a short reference, and the details are written to Super admin →
 * Audit logs as "error.server": what failed, where, and on which address. On shared hosting the server's own log files are hard to reach, so this is
 * how an error that only happens on the live site can be read and fixed. Never allowed to cause an error of its own.
 */
class ServerErrors
{
    /** At most this many notes an hour, so one bug that fails on every request cannot fill the table. */
    private const PER_HOUR = 60;

    /** Writes the note and returns its reference (null when nothing was written). The reference is also kept on the request for the response. */
    public static function note(Throwable $e): ?string
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null; // a command or a queue worker: the log file has it
        }

        return rescue(function () use ($e) {
            if (! RateLimiter::attempt('server-error-notes', self::PER_HOUR, fn () => true, 3600)) {
                return null;
            }
            $reference = strtoupper(Str::random(6));
            $request = request();
            $request->attributes->set('error_reference', $reference);

            Audit::log(
                'error.server',
                null,
                Str::limit("[{$reference}] ".class_basename($e).': '.self::clean($e->getMessage()), 250, '…'),
                [],
                [
                    'request' => $request->method().' /'.ltrim($request->path(), '/'),
                    'where' => self::clean($e->getFile()).':'.$e->getLine(),
                    'class' => $e::class,
                    'message' => Str::limit(self::clean($e->getMessage()), 1500, '…'),
                ],
            );

            return $reference;
        }, null, false);
    }

    /** The reference given to this request, if there is one. */
    public static function reference(): ?string
    {
        return app()->bound('request') ? request()->attributes->get('error_reference') : null;
    }

    /** The server's folder is not for anyone to read: show the path from the site's own folder. */
    protected static function clean(string $text): string
    {
        return trim(str_replace([base_path().DIRECTORY_SEPARATOR, base_path()], '', $text));
    }
}
