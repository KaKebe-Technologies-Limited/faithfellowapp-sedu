<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Database channel that also stores church_id + category columns so the
 * notification center can filter by church and category efficiently.
 */
class TenantDatabaseChannel extends DatabaseChannel
{
    protected function buildPayload($notifiable, Notification $notification)
    {
        $payload = parent::buildPayload($notifiable, $notification);

        $payload['church_id'] = property_exists($notification, 'churchId') ? $notification->churchId : null;
        $payload['category'] = property_exists($notification, 'category') ? $notification->category : null;

        return $payload;
    }
}
