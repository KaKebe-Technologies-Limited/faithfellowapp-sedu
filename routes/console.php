<?php

use App\Services\ContentPublisher;
use App\Services\EventService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| FaithFellows scheduled tasks
|--------------------------------------------------------------------------
| Production cron:  * * * * * cd /var/www/faithfellows && php artisan schedule:run
*/

Artisan::command('ff:publish-scheduled', function (ContentPublisher $publisher) {
    $count = $publisher->publishDue();
    $this->info("Announced {$count} newly published item(s).");
})->purpose('Publish scheduled sermons, devotionals and announcements and notify subscribers');

Schedule::command('ff:publish-scheduled')->everyMinute()->withoutOverlapping();

Artisan::command('ff:event-reminders', function (EventService $events) {
    $this->info('Sent '.$events->sendReminders().' event reminder(s).');
})->purpose('Send 24-hour and 1-hour event reminders');

Schedule::command('ff:event-reminders')->everyFiveMinutes()->withoutOverlapping();
