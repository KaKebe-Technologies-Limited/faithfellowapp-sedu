<?php

use App\Services\BillingService;
use App\Services\ContentPublisher;
use App\Services\EventService;
use App\Services\GivingScheduler;
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

Artisan::command('ff:process-recurring', function (GivingScheduler $scheduler) {
    $this->info('Started '.$scheduler->processRecurring().' recurring gift(s).');
})->purpose('Charge recurring gifts due today (mobile money prompts / card reminders)');

Artisan::command('ff:pledge-reminders', function (GivingScheduler $scheduler) {
    $this->info('Sent '.$scheduler->remindPledges().' pledge reminder(s).');
})->purpose('Send pledge installment reminders and mark overdue pledges');

Artisan::command('ff:payments-reconcile', function (GivingScheduler $scheduler) {
    $this->info('Re-checked '.$scheduler->reconcilePending().' pending payment(s).');
})->purpose('Re-verify pending mobile money payments whose callback never arrived');

Artisan::command('ff:billing', function (BillingService $billing) {
    $created = $billing->generateDueInvoices();
    $result = $billing->enforce();
    $this->info("Invoices created: {$created}; overdue: {$result['overdue']}; suspended: {$result['suspended']}");
})->purpose('Generate church subscription invoices, send reminders and apply grace-period suspension');

Schedule::command('ff:process-recurring')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('ff:pledge-reminders')->dailyAt('09:00');
Schedule::command('ff:payments-reconcile')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('ff:billing')->dailyAt('06:00');
