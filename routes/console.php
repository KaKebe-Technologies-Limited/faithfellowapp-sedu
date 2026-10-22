<?php

use App\Models\Church;
use App\Models\NotificationDelivery;
use App\Services\BillingService;
use App\Services\CareService;
use App\Services\ChurchArtworkService;
use App\Services\ContentPublisher;
use App\Services\DormancyService;
use App\Services\EventService;
use App\Services\GivingScheduler;
use App\Services\InspirationBroadcaster;
use App\Services\JoinLinks;
use App\Services\Notifications\BroadcastService;
use App\Services\Notifications\DigestService;
use App\Services\PodcastFeedService;
use App\Services\ReadingCircleService;
use App\Services\VerseBroadcaster;
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

// Hosting without a deploy step (Hostinger Git): new migrations are applied by the scheduler a minute after an update.
if (config('faithfellows.auto_migrate')) {
    Schedule::command('migrate --force --no-interaction')->everyMinute()->withoutOverlapping(10);
}

Artisan::command('podcasts:sync-feeds', function (PodcastFeedService $feeds) {
    $result = $feeds->syncDue();
    $this->info("Checked {$result['podcasts']} podcast feed(s): {$result['added']} new episode(s), {$result['errors']} problem(s).");
})->purpose('Add new episodes from the RSS feeds of connected podcasts');

Schedule::command('podcasts:sync-feeds')->hourly()->withoutOverlapping();

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

Artisan::command('ff:dormancy', function (DormancyService $dormancy) {
    $r = $dormancy->run();
    $this->info("Warned: {$r['warned']}; blocked: {$r['blocked']}; back to life: {$r['cleared']}");
})->purpose('Warn churches that stay dormant, then block them if they stay quiet');

Schedule::command('ff:dormancy')->dailyAt('06:30');

// A church's join link remembers which address opened it for a few days (so the app can offer that church after a fresh install), then forgets.
Artisan::command('ff:prune-join-links', function (JoinLinks $links) {
    $this->info('Forgot '.$links->prune().' old link opening(s).');
})->purpose('Forget which address opened a church join link once it is too old to matter');

Schedule::command('ff:prune-join-links')->dailyAt('03:30');

Artisan::command('ff:inspiration', function (InspirationBroadcaster $inspiration) {
    $this->info('Sent '.$inspiration->sendDue().' inspiring message(s) to phones.');
})->purpose('Push the morning, mid-morning and evening inspiring message to phones at their set times');

Schedule::command('ff:inspiration')->everyMinute()->withoutOverlapping();

Artisan::command('ff:verse-push', function (VerseBroadcaster $verse) {
    $this->info($verse->sendDue() ? 'Sent the verse of the day to phones.' : 'Nothing to send now.');
})->purpose('Push the verse of the day to phones each morning');

Schedule::command('ff:verse-push')->everyMinute()->withoutOverlapping();

// Uploaded pictures are served from /storage through a link that `php artisan storage:link` makes. On a shared server it is easily missing, so this
// makes it when it can (the app serves the pictures by itself in the meantime: see Public\StorageController).
Artisan::command('ff:storage-link', function () {
    if (file_exists(public_path('storage')) || is_link(public_path('storage'))) {
        return $this->info('The storage link is there.');
    }
    rescue(fn () => Artisan::call('storage:link'), null, false);
    $this->info(file_exists(public_path('storage')) ? 'Made the storage link.' : 'The storage link could not be made here; the app serves the pictures itself.');
})->purpose('Make the public/storage link for uploaded pictures when it is missing');

Schedule::command('ff:storage-link')->hourly();

Artisan::command('ff:care-digest', function (CareService $care) {
    $this->info('Sent '.$care->sendWeeklyDigests().' pastoral care digest(s).');
})->purpose('Monday digest to pastors: birthdays, anniversaries and follow-ups');

Artisan::command('ff:rota-reminders', function (CareService $care) {
    $this->info('Reminded '.$care->remindVolunteers().' volunteer(s).');
})->purpose('Remind volunteers who are serving tomorrow');

Schedule::command('ff:care-digest')->weeklyOn(1, '07:30');
Schedule::command('ff:rota-reminders')->dailyAt('18:00');

Artisan::command('ff:send-broadcasts', function (BroadcastService $broadcasts) {
    $this->info('Sent '.$broadcasts->sendDue().' scheduled notification(s).');
})->purpose('Send church notifications scheduled in the composer');

Artisan::command('ff:send-digests', function (DigestService $digests) {
    $this->info('Sent '.$digests->sendDue().' daily digest(s).');
})->purpose('Email the daily digest to members in digest mode');

/*
 * VAPID keys for web push. Prints the .env lines (or writes them with --write).
 * On Windows/XAMPP OpenSSL needs its config file, which we pass explicitly.
 */
Artisan::command('ff:vapid {--write : Write the keys into .env}', function () {
    $options = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
    $key = @openssl_pkey_new($options);
    if (! $key) {
        foreach ([getenv('OPENSSL_CONF'), dirname(PHP_BINARY).'/extras/ssl/openssl.cnf', 'C:/xampp/apache/conf/openssl.cnf'] as $cnf) {
            if ($cnf && is_file($cnf) && ($key = @openssl_pkey_new([...$options, 'config' => $cnf]))) {
                break;
            }
        }
    }
    if (! $key) {
        $this->error('OpenSSL could not create an EC key. Set OPENSSL_CONF to your openssl.cnf and try again.');

        return 1;
    }

    $ec = openssl_pkey_get_details($key)['ec'];
    $b64 = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    $pad = fn (string $bin) => str_pad($bin, 32, "\0", STR_PAD_LEFT);
    $public = $b64("\x04".$pad($ec['x']).$pad($ec['y']));
    $private = $b64($pad($ec['d']));

    if ($this->option('write')) {
        $env = file_get_contents(base_path('.env'));
        foreach (['VAPID_PUBLIC_KEY' => $public, 'VAPID_PRIVATE_KEY' => $private] as $k => $v) {
            $env = preg_match("/^{$k}=.*$/m", $env) ? preg_replace("/^{$k}=.*$/m", "{$k}={$v}", $env) : $env."\n{$k}={$v}";
        }
        file_put_contents(base_path('.env'), $env);
        $this->info('VAPID keys written to .env. Run php artisan config:clear if config is cached.');
    } else {
        $this->line("VAPID_PUBLIC_KEY={$public}");
        $this->line("VAPID_PRIVATE_KEY={$private}");
    }

    return 0;
})->purpose('Generate VAPID keys for web push notifications');

// Emails, pushes and receipts wait in the queue. A shared server often has no worker running, so the scheduler (the one cron job every site has) empties
// the queue each minute too; a real worker, if there is one, simply finds it empty.
if (config('queue.default') !== 'sync') {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=2')->everyMinute()->withoutOverlapping(2)->runInBackground();
}

Schedule::command('ff:send-broadcasts')->everyMinute()->withoutOverlapping();
Schedule::command('ff:send-digests')->dailyAt('18:00');
Schedule::command('model:prune', ['--model' => [NotificationDelivery::class]])->daily();

Artisan::command('ff:reading-reminders', function (ReadingCircleService $circles) {
    $this->info('Reminded '.$circles->sendReminders().' reading circle(s).');
})->purpose('Notify reading circles at their agreed reading time');

Schedule::command('ff:reading-reminders')->everyMinute()->withoutOverlapping();

Artisan::command('ff:church-artwork {church? : Church slug (all churches when omitted)} {--force : Replace existing logo, banner and slides}', function (ChurchArtworkService $artwork) {
    $churches = $this->argument('church')
        ? Church::where('slug', $this->argument('church'))->get()
        : Church::orderBy('id')->get();

    foreach ($churches as $church) {
        $artwork->apply($church, (bool) $this->option('force'));
        $this->line("✓ {$church->name}");
    }
})->purpose('Generate a clean default logo, banner and home-slider photos for churches that have none');
