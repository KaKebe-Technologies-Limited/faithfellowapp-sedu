<?php

namespace Database\Seeders;

use App\Models\BibleHighlight;
use App\Models\Church;
use App\Models\DiscipleshipRecord;
use App\Models\Note;
use App\Models\NotificationDelivery;
use App\Models\QuizAnswer;
use App\Models\QuizSession;
use App\Models\Reaction;
use App\Models\ReadingCircleEntry;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\ChurchArtworkService;
use App\Services\ChurchOnboardingService;
use App\Services\ModerationService;
use App\Services\QuizService;
use App\Services\StreamingService;
use App\Services\SupportService;
use App\Support\ReadingPlanTemplates;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Demo data for local development and pilot demos (fictional churches).
 * Demo accounts all use the password "password".
 */
class DemoSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password';

    /** @var array<int, array<string, mixed>> */
    public const CHURCHES = [
        [
            'name' => 'Grace Community Church Lira', 'slug' => 'grace-lira', 'denomination' => 'Pentecostal', 'city' => 'Lira', 'district' => 'Lira',
            'address' => 'Plot 12, Obote Avenue', 'lat' => 2.2475, 'lng' => 32.9001, 'pastor_name' => 'Rev. Daniel Okello', 'founded_year' => 2004,
            'tagline' => 'A family of believers in the heart of Lira', 'languages' => ['en', 'luo'], 'brand_color' => '#BC2649', 'is_featured' => true,
            'description' => 'A Spirit-filled family church in Lira City with vibrant youth, worship and outreach ministries.',
        ],
        [
            'name' => 'St. Peter’s Fellowship Gulu', 'slug' => 'st-peters-gulu', 'denomination' => 'Anglican (Church of Uganda)', 'city' => 'Gulu', 'district' => 'Gulu',
            'address' => 'Churchill Drive', 'lat' => 2.7746, 'lng' => 32.2990, 'pastor_name' => 'Rev. Canon Grace Akena', 'founded_year' => 1962,
            'tagline' => 'Faith, hope and love in Acholiland', 'languages' => ['en', 'luo'], 'brand_color' => '#2D5BA9',
            'description' => 'A welcoming Anglican congregation serving Gulu City through worship, education and community care.',
        ],
        [
            'name' => 'New Life Tabernacle Kampala', 'slug' => 'new-life-kampala', 'denomination' => 'Born Again / Charismatic', 'city' => 'Kampala', 'district' => 'Kampala',
            'address' => 'Ntinda, Kigoowa Road', 'lat' => 0.3536, 'lng' => 32.6141, 'pastor_name' => 'Pastor Ruth Nakato', 'founded_year' => 2011,
            'tagline' => 'Raising a generation on fire for God', 'languages' => ['en', 'lg'], 'brand_color' => '#6D3BA6', 'is_featured' => true,
            'description' => 'A fast-growing church in Ntinda with three Sunday services, a thriving young adults ministry and cell groups across Kampala.',
        ],
        [
            'name' => 'Victory Chapel Mbarara', 'slug' => 'victory-mbarara', 'denomination' => 'Evangelical', 'city' => 'Mbarara', 'district' => 'Mbarara',
            'address' => 'High Street', 'lat' => -0.6072, 'lng' => 30.6545, 'pastor_name' => 'Pastor John Tumusiime', 'founded_year' => 1998,
            'tagline' => 'Victory in Christ for every family', 'languages' => ['en'], 'brand_color' => '#1E9E5A',
            'description' => 'An evangelical church in Mbarara focused on family discipleship and missions to Western Uganda.',
        ],
        [
            'name' => 'Living Waters Church Jinja', 'slug' => 'living-waters-jinja', 'denomination' => 'Baptist', 'city' => 'Jinja', 'district' => 'Jinja',
            'address' => 'Main Street, near the Source', 'lat' => 0.4244, 'lng' => 33.2041, 'pastor_name' => 'Pastor Samuel Waiswa', 'founded_year' => 2008,
            'tagline' => 'Rivers of living water', 'languages' => ['en', 'sw'], 'brand_color' => '#0E8A8A',
            'description' => 'A Baptist church on the banks of the Nile with a strong children’s ministry and community health outreach.',
        ],
    ];

    public function run(): void
    {
        $onboarding = app(ChurchOnboardingService::class);
        $memberRole = Role::findOrCreate('member', 'web');

        $member = User::updateOrCreate(['email' => 'member@faithfellows.test'], [
            'name' => 'Grace Akello', 'password' => self::DEMO_PASSWORD, 'phone' => '0772000111', 'city' => 'Lira',
            'email_verified_at' => now(), 'terms_accepted_at' => now(), 'email_consent' => true, 'directory_opt_in' => true,
        ]);
        $member->assignRole($memberRole);

        $pastor = User::updateOrCreate(['email' => 'pastor@faithfellows.test'], [
            'name' => 'Daniel Okello', 'password' => self::DEMO_PASSWORD, 'phone' => '0772000222', 'city' => 'Lira',
            'email_verified_at' => now(), 'terms_accepted_at' => now(),
        ]);
        $pastor->assignRole($memberRole);

        $treasurer = User::updateOrCreate(['email' => 'finance@faithfellows.test'], [
            'name' => 'Sarah Apio', 'password' => self::DEMO_PASSWORD, 'phone' => '0772000333', 'city' => 'Lira',
            'email_verified_at' => now(), 'terms_accepted_at' => now(),
        ]);
        $treasurer->assignRole($memberRole);

        $services = [
            ['name' => 'First Service', 'day_of_week' => 0, 'start_time' => '07:30', 'end_time' => '09:30'],
            ['name' => 'Main Service', 'day_of_week' => 0, 'start_time' => '10:00', 'end_time' => '12:30'],
            ['name' => 'Midweek Bible Study', 'day_of_week' => 3, 'start_time' => '17:30', 'end_time' => '19:00'],
            ['name' => 'Friday Overnight Prayer', 'day_of_week' => 5, 'start_time' => '21:00', 'end_time' => null],
        ];

        foreach (self::CHURCHES as $i => $data) {
            $church = Church::updateOrCreate(['slug' => $data['slug']], [
                ...$data,
                'country' => 'UG',
                'phone' => '07720'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'email' => 'info@'.$data['slug'].'.example',
                'status' => 'pending',
                'owner_id' => $i === 0 ? $pastor->id : null,
                'website_blocks' => Church::DEFAULT_BLOCKS,
                'about' => '<p>'.$data['description'].'</p><p>We gather every Sunday to worship, hear the Word and share life together. Whether you are new to faith or have walked with Jesus for years, there is a place for you here.</p>',
                'setup_completed_at' => now(),
            ]);
            $onboarding->approve($church, notify: false);

            $church->serviceTimes()->delete();
            foreach (array_slice($services, 0, $i === 0 ? 4 : 2 + ($i % 2)) as $service) {
                $church->serviceTimes()->create([...$service, 'language' => $data['languages'][0]]);
            }
        }

        // Pending application for the super admin queue.
        Church::updateOrCreate(['slug' => 'hope-chapel-arua'], [
            'name' => 'Hope Chapel Arua', 'denomination' => 'Pentecostal', 'city' => 'Arua', 'district' => 'Arua', 'country' => 'UG',
            'phone' => '0772555666', 'status' => 'pending', 'description' => 'A young church plant in Arua City.',
            'application_notes' => 'About 120 members. We would like to start online giving before Christmas.',
            'website_blocks' => Church::DEFAULT_BLOCKS,
        ]);

        // Clean logos, banners and home-slider photos for every demo church.
        $artwork = app(ChurchArtworkService::class);
        Church::orderBy('id')->each(fn (Church $c) => $artwork->apply($c));

        // Staff for the flagship demo church.
        $grace = Church::where('slug', 'grace-lira')->first();
        $grace->staffMembers()->updateOrCreate(['user_id' => $pastor->id], ['is_admin' => true, 'status' => 'active', 'title' => 'Senior Pastor']);
        $pastor->assignRole('church_admin');
        $financeRole = $grace->roles()->where('slug', 'finance')->first();
        $grace->staffMembers()->updateOrCreate(['user_id' => $treasurer->id], ['is_admin' => false, 'church_role_id' => $financeRole?->id, 'status' => 'active', 'title' => 'Treasurer']);
        $treasurer->assignRole('staff');

        $grace->branches()->firstOrCreate(['slug' => 'adyel'], ['name' => 'Adyel Branch', 'city' => 'Lira', 'address' => 'Adyel Division', 'leader_name' => 'Pastor Joyce Atim', 'lat' => 2.2368, 'lng' => 32.9123]);

        // Congregation
        if (User::count() < 30) {
            User::factory(40)->create()->each(fn (User $u) => $u->assignRole($memberRole));
        }

        $this->seedSubscriptionsAndPosts($member, $pastor);
        $this->seedContent($member, $pastor);
        $this->seedEventsAndLive($member, $pastor);
        $this->seedGiving($member, $pastor);
        $this->seedCommunity($member, $pastor);
        $this->seedSupportAndMessaging($member, $pastor);
        $this->seedFellowship($member, $pastor);
    }

    /**
     * Sprint 8: a sent church notification with delivery stats, support tickets, a content report.
     */
    protected function seedSupportAndMessaging(User $member, User $pastor): void
    {
        $grace = Church::where('slug', 'grace-lira')->first();
        if (! $grace || $grace->churchBroadcasts()->exists()) {
            return;
        }
        $admin = User::role('super_admin')->first();

        $broadcast = $grace->churchBroadcasts()->create([
            'user_id' => $pastor->id, 'title' => 'Sunday service moves to 9:00 AM',
            'body' => 'Because of the district thanksgiving rally, this Sunday we have one combined service at 9:00 AM. Come early and bring a friend!',
            'audience' => 'members', 'channels' => ['mail', 'webpush', 'sms'], 'category' => 'announcement', 'is_urgent' => true,
            'status' => 'sent', 'sent_at' => now()->subDays(3), 'recipients_count' => $grace->subscriptions()->where('status', 'active')->count(),
        ]);
        $grace->churchBroadcasts()->create([
            'user_id' => $pastor->id, 'title' => 'Youth camp registration closes Friday', 'body' => 'Register in the Events tab before Friday 6 PM.',
            'audience' => 'stage', 'audience_options' => ['stages' => ['member', 'worker']], 'channels' => ['mail', 'webpush'], 'category' => 'event',
            'status' => 'scheduled', 'scheduled_at' => now()->addDays(2)->setTime(9, 0),
        ]);

        $people = User::whereIn('id', $grace->subscriptions()->where('status', 'active')->select('user_id'))->get();
        $rows = [];
        foreach ($people as $i => $person) {
            foreach (['mail' => 0, 'webpush' => 0, 'sms' => 35] as $channel => $cost) {
                if ($channel === 'webpush' && $i % 3 !== 0) {
                    continue; // only some members enabled push
                }
                $failed = $channel === 'sms' && $i % 11 === 0;
                $rows[] = [
                    'church_id' => $grace->id, 'user_id' => $person->id, 'church_broadcast_id' => $broadcast->id, 'notification' => 'ChurchMessage',
                    'category' => 'announcement', 'channel' => $channel, 'status' => $failed ? 'failed' : 'sent', 'cost' => $failed ? 0 : $cost,
                    'error' => $failed ? 'InvalidPhoneNumber' : null, 'created_at' => now()->subDays(3),
                ];
            }
        }
        NotificationDelivery::insert($rows);

        $support = app(SupportService::class);
        Notification::fake();
        $ticket = $support->open($pastor, $grace, ['subject' => 'Airtel Money gifts not showing', 'category' => 'payments', 'priority' => 'high',
            'body' => "Two members gave by Airtel Money yesterday and received the SMS from Airtel, but the gifts still show as pending on our giving page.\nReferences FF-8Q2M and FF-9K1L."]);
        if ($admin) {
            $support->reply($ticket, $admin, "Thank you Pastor Daniel. Airtel's callback was delayed yesterday afternoon. We re-checked both payments and they are now confirmed — receipts have been sent to the givers. Please confirm on your side.", true);
            $ticket->update(['assigned_to' => $admin->id]);
        }
        $support->open($pastor, $grace, ['subject' => 'Can we add a second live stream for the Luo service?', 'category' => 'feature', 'priority' => 'normal',
            'body' => 'We run an English service and a Luo service at the same time. Is it possible to stream both?']);
        Notification::swap(app(ChannelManager::class));

        $comment = $grace->comments()->latest()->first();
        if ($comment && ($reporter = $people->firstWhere('id', '!=', $comment->user_id))) {
            app(ModerationService::class)->report($reporter, $comment, ['reason' => 'spam', 'details' => 'Looks like an advert.']);
        }
    }

    /**
     * Sprint 7: prayer wall, testimonies, small groups, volunteers, attendance and care.
     * Inserted directly so seeding sends no notifications.
     */
    protected function seedCommunity(User $member, User $pastor): void
    {
        $grace = Church::where('slug', 'grace-lira')->first();
        if (! $grace || $grace->prayerRequests()->exists()) {
            return;
        }

        $people = User::whereIn('id', $grace->subscriptions()->where('status', 'active')->select('user_id'))
            ->whereKeyNot($member->id)->get();
        $pick = fn (int $n) => $people->shuffle()->take(min($n, $people->count()));

        // A few birthdays this week so the care dashboard has celebrations.
        $pick(3)->each(fn (User $u, $i) => $u->forceFill(['date_of_birth' => now()->subYears(25 + $i * 7)->addDays($i * 2 + 1)])->save());
        $pick(2)->each(fn (User $u, $i) => $u->forceFill(['wedding_anniversary' => now()->subYears(5 + $i * 3)->addDays($i + 3)])->save());

        // ── Prayer wall ─────────────────────────────────────────────────
        $requests = [
            [$member, 'Healing for my mother', 'My mother was admitted at Lira Regional Referral Hospital with malaria and typhoid. Please stand with us for quick and complete healing.', 'church', false, true],
            [null, 'School fees for next term', 'Pray that God opens doors for my children’s school fees. Term starts in three weeks.', 'church', true, false],
            [null, 'Safe journey', 'Travelling to Kampala for a job interview on Monday. Pray for favour and safety on the road.', 'public', false, false],
            [null, null, 'Our family is going through a very difficult season. We need wisdom and peace in our home.', 'pastor', true, false],
            [null, 'Rain for our gardens', 'The dry spell has affected our crops in Alebtong. Pray for rain and a good harvest.', 'public', false, true],
            [null, 'Exams', 'S6 candidates at Lira Town College are sitting UACE. Pray for focus, memory and calm.', 'church', false, false],
            [$member, 'New job', 'I start a new job at the district health office next month. Pray that I serve with integrity.', 'public', false, false],
        ];
        foreach ($requests as $i => [$author, $title, $body, $visibility, $anonymous, $chain]) {
            $author ??= $people->get($i % max(1, $people->count())) ?? $member;
            $prayer = $grace->prayerRequests()->create([
                'user_id' => $author->id, 'title' => $title, 'body' => $body, 'visibility' => $visibility,
                'is_anonymous' => $anonymous, 'chain_enabled' => $chain, 'status' => 'open',
            ]);
            $prayer->forceFill(['created_at' => now()->subHours($i * 9 + 2)])->saveQuietly();

            foreach ($pick(random_int(3, 14)) as $prayerFriend) {
                $prayer->interactions()->create(['church_id' => $grace->id, 'user_id' => $prayerFriend->id, 'type' => 'prayed']);
            }
            $prayer->forceFill(['prayed_count' => $prayer->interactions()->where('type', 'prayed')->count()])->saveQuietly();
        }

        $answered = $grace->prayerRequests()->create([
            'user_id' => $people->first()?->id ?? $member->id, 'title' => 'Baby on the way',
            'body' => 'We have waited six years for a child. Please pray with us.', 'visibility' => 'church',
            'status' => 'answered', 'answered_at' => now()->subDays(6), 'prayed_count' => 41,
            'answer_note' => 'Our son Emmanuel was born healthy last week! Thank you church family for praying.',
        ]);

        // ── Testimonies ─────────────────────────────────────────────────
        $testimonies = [
            [$answered->user_id, $answered->id, 'God gave us Emmanuel', 'After six years of waiting and many tears, God has blessed us with a healthy baby boy. The doctors had told us it was unlikely. To everyone who prayed with us on the prayer wall — thank you. God is faithful!', 'approved'],
            [$people->get(1)?->id, null, 'Healed from ulcers', 'For three years I suffered from stomach ulcers and spent a lot on medicine. During the Friday overnight prayer, the pastor prayed for the sick and I have not felt the pain since. I went for a check-up and the doctor confirmed I am well.', 'approved'],
            [$people->get(2)?->id, null, 'A job after two years', 'I graduated in 2023 and searched for work for two years. Last month I was employed by a bank in Lira. I give God all the glory and thank my cell group for standing with me.', 'approved'],
            [$member->id, null, 'Provision for school fees', "At the start of the term I did not know where my daughter's school fees would come from. A stranger paid half the fees and my business picked up. God provides!", 'pending'],
        ];
        foreach ($testimonies as $i => [$userId, $prayerId, $title, $body, $status]) {
            $grace->testimonies()->create([
                'user_id' => $userId ?? $member->id, 'prayer_request_id' => $prayerId, 'title' => $title, 'body' => $body, 'status' => $status,
                'reviewed_by' => $status === 'approved' ? $pastor->id : null, 'reviewed_at' => $status === 'approved' ? now()->subDays($i * 3 + 1) : null,
            ]);
        }

        // ── Small groups ────────────────────────────────────────────────
        $groups = [
            ['name' => 'Ireda Cell', 'type' => 'cell', 'meeting_day' => 2, 'meeting_time' => '18:00', 'location' => 'Mama Grace’s home, Ireda', 'lat' => 2.2551, 'lng' => 32.8867, 'leader' => $member],
            ['name' => 'Adyel Home Fellowship', 'type' => 'home_fellowship', 'meeting_day' => 4, 'meeting_time' => '18:30', 'location' => 'Adyel Division, near the market', 'lat' => 2.2368, 'lng' => 32.9123],
            ['name' => 'Young Professionals Bible Study', 'type' => 'bible_study', 'meeting_day' => 6, 'meeting_time' => '16:00', 'location' => 'Church boardroom', 'lat' => 2.2478, 'lng' => 32.9006],
            ['name' => 'Women of Purpose', 'type' => 'women', 'meeting_day' => 6, 'meeting_time' => '10:00', 'location' => 'Church hall'],
        ];
        foreach ($groups as $g => $data) {
            $leader = $data['leader'] ?? $people->get($g + 3) ?? $pastor;
            unset($data['leader']);
            $group = $grace->smallGroups()->create([...$data, 'leader_id' => $leader->id, 'description' => 'We meet weekly to pray, study the Word and care for one another. Everyone is welcome.', 'is_open' => true, 'status' => 'active']);
            $members = $pick(random_int(6, 11))->push($leader)->unique('id');
            foreach ($members as $person) {
                $group->memberships()->create(['church_id' => $grace->id, 'user_id' => $person->id, 'role' => $person->id === $leader->id ? 'leader' : 'member', 'status' => 'active', 'joined_at' => now()->subMonths(2)]);
            }
            $pending = $people->whereNotIn('id', $members->pluck('id'))->first();
            if ($pending && $g === 0) {
                $group->memberships()->create(['church_id' => $grace->id, 'user_id' => $pending->id, 'role' => 'member', 'status' => 'pending']);
            }
            foreach (range(1, 4) as $w) {
                $meeting = $group->meetings()->create([
                    'church_id' => $grace->id, 'meeting_date' => now()->subWeeks($w)->startOfWeek()->addDays(($data['meeting_day'] + 6) % 7)->toDateString(),
                    'topic' => ['Faith that works (James 2)', 'Taming the tongue (James 3)', 'Patience in suffering (James 5)', 'Wisdom from above (James 3:13)'][$w - 1],
                    'visitors' => random_int(0, 3), 'offering_amount' => random_int(2, 12) * 5000, 'recorded_by' => $leader->id,
                ]);
                foreach ($members as $person) {
                    $meeting->attendance()->create(['church_id' => $grace->id, 'small_group_id' => $group->id, 'user_id' => $person->id, 'status' => random_int(1, 10) <= 8 ? 'present' : 'absent']);
                }
            }
        }
        $grace->announcements()->create([
            'small_group_id' => $grace->smallGroups()->where('name', 'Ireda Cell')->value('id'), 'user_id' => $member->id,
            'title' => 'No cell meeting next Tuesday', 'body' => '<p>We will join the church overnight prayer on Friday instead. See you there!</p>',
            'audience' => 'group', 'priority' => 'normal', 'status' => 'published', 'published_at' => now()->subDay(), 'notified_at' => now()->subDay(), 'channels' => [],
        ]);

        // ── Volunteers & rota ───────────────────────────────────────────
        $sunday = now()->next('Sunday');
        foreach ([['Ushers', '#BC2649'], ['Praise & Worship', '#2D7FF9'], ['Media & Sound', '#1E9E5A'], ['Children’s Church', '#E8A317']] as $t => [$name, $color]) {
            $team = $grace->volunteerTeams()->create(['name' => $name, 'color' => $color, 'leader_id' => $people->get($t + 8)?->id, 'description' => null]);
            $volunteers = $pick(5);
            if ($t === 0) {
                $volunteers->push($member);
            }
            $team->members()->syncWithoutDetaching($volunteers->mapWithKeys(fn ($v) => [$v->id => ['church_id' => $grace->id]])->all());
            foreach ([0, 1] as $week) {
                foreach ($volunteers->take(2 + $week) as $v => $volunteer) {
                    $grace->volunteerRotas()->create([
                        'volunteer_team_id' => $team->id, 'user_id' => $volunteer->id, 'service_date' => $sunday->copy()->addWeeks($week)->toDateString(),
                        'role' => $t === 0 ? ['Main door', 'Side door', 'Offering'][$v] ?? null : null,
                        'status' => $week === 0 ? ($v === 0 ? 'confirmed' : 'scheduled') : 'scheduled',
                    ]);
                }
            }
        }
        $grace->volunteerRotas()->firstOrCreate(
            ['user_id' => $member->id, 'service_date' => $sunday->toDateString()],
            ['volunteer_team_id' => $grace->volunteerTeams()->where('name', 'Ushers')->value('id'), 'role' => 'Welcome desk', 'status' => 'scheduled'],
        );

        // ── Service attendance (last 10 Sundays) ────────────────────────
        $mainService = $grace->serviceTimes()->where('name', 'Main Service')->value('id');
        foreach (range(1, 10) as $w) {
            $date = now()->previous('Sunday')->subWeeks($w - 1)->toDateString();
            foreach ($people->shuffle()->take((int) round($people->count() * random_int(55, 85) / 100)) as $person) {
                $grace->attendanceRecords()->create(['user_id' => $person->id, 'service_date' => $date, 'service_time_id' => $mainService, 'method' => collect(['qr', 'qr', 'geofence', 'manual'])->random()]);
            }
            $grace->attendanceRecords()->create(['service_date' => $date, 'method' => 'manual', 'guest_name' => 'Children’s church', 'headcount' => random_int(35, 60), 'recorded_by' => $pastor->id]);
            if ($w > 1) {
                $grace->attendanceRecords()->create(['user_id' => $member->id, 'service_date' => $date, 'service_time_id' => $mainService, 'method' => 'qr']);
            }
        }

        // ── Pastoral care (encrypted fields go through the model casts) ─
        foreach ($pick(4) as $i => $person) {
            $grace->pastoralNotes()->create(['user_id' => $person->id, 'author_id' => $pastor->id, 'is_private' => $i % 2 === 0,
                'body' => ['Lost her job in March; connected her with the welfare team.', 'Wants to be baptised at the next service.', 'Husband is unwell — follow up weekly.', 'Interested in joining the media team.'][$i]]);
        }
        foreach ($pick(3) as $i => $person) {
            $grace->welfareRecords()->create(['user_id' => $person->id, 'type' => ['food', 'medical', 'school_fees'][$i], 'amount' => [80000, 150000, 350000][$i], 'given_on' => now()->subDays($i * 9 + 2)->toDateString(), 'recorded_by' => $pastor->id, 'notes' => 'Approved by the welfare committee.']);
        }
        foreach ($pick(2) as $i => $person) {
            $grace->homeVisits()->create(['user_id' => $person->id, 'visitor_id' => $pastor->id, 'scheduled_at' => now()->addDays($i * 2 + 1)->setTime(16, 0), 'purpose' => ['Pray with the family', 'Welcome visit for new members'][$i], 'status' => 'scheduled']);
        }
        foreach ($people->take(25) as $i => $person) {
            foreach (array_slice(array_keys(DiscipleshipRecord::MILESTONES), 0, 1 + $i % 5) as $m => $milestone) {
                $grace->discipleshipRecords()->create(['user_id' => $person->id, 'milestone' => $milestone, 'completed_on' => now()->subMonths(6 - $m)->toDateString(), 'mentor_id' => $pastor->id]);
            }
        }
        $grace->discipleshipRecords()->create(['user_id' => $member->id, 'milestone' => 'salvation', 'completed_on' => now()->subYears(3)->toDateString()]);
        $grace->counselingAppointments()->create(['user_id' => $member->id, 'mode' => 'in_person', 'topic' => 'Premarital counseling for my fiancé and me.', 'preferred_at' => now()->addDays(4)->setTime(14, 0), 'status' => 'requested']);
        $grace->counselingAppointments()->create(['user_id' => $people->first()?->id ?? $member->id, 'counselor_id' => $pastor->id, 'mode' => 'phone', 'topic' => 'Grief after losing my father.', 'scheduled_at' => now()->addDays(2)->setTime(10, 0), 'status' => 'confirmed']);

        // ── Reactions & comments on recent posts ────────────────────────
        foreach ($grace->posts()->latest('published_at')->take(3)->get() as $post) {
            foreach ($pick(random_int(4, 12)) as $person) {
                $post->reactions()->create(['church_id' => $grace->id, 'user_id' => $person->id, 'type' => collect(array_keys(Reaction::TYPES))->random()]);
            }
            foreach ($pick(2) as $c => $person) {
                $post->comments()->create(['church_id' => $grace->id, 'user_id' => $person->id, 'body' => ['Amen! God bless you Pastor.', 'Thank you for this update 🙏'][$c]]);
            }
        }
    }

    /**
     * Sprint 6: giving channels, a building campaign with gifts, pledges, recurring.
     * Records are inserted directly (no gateway calls, no notification spam).
     */
    protected function seedGiving(User $member, User $pastor): void
    {
        $grace = Church::where('slug', 'grace-lira')->first();
        if ($grace->campaigns()->exists()) {
            return;
        }

        foreach (Church::where('status', 'active')->get() as $church) {
            foreach (['mtn_momo', 'airtel_money', 'stripe', 'bank', 'cash'] as $i => $type) {
                $church->givingChannels()->updateOrCreate(['type' => $type], [
                    'is_active' => true, 'sort' => $i,
                    'details' => $type === 'bank' ? ['bank_name' => 'Centenary Bank', 'account_name' => $church->name, 'account_number' => '31000'.str_pad((string) $church->id, 5, '0', STR_PAD_LEFT), 'branch' => $church->city] : null,
                ]);
            }
        }

        $campaign = $grace->campaigns()->create([
            'user_id' => $pastor->id, 'title' => 'New Sanctuary Building Fund', 'type' => 'building',
            'short_description' => 'Help us build a 1,200-seat sanctuary for our growing church family in Lira.',
            'story' => '<p>Every Sunday we hold three services because our hall seats only 350. The new sanctuary will welcome 1,200 people, host community events and shelter our children’s ministry.</p><h3>What your gift does</h3><ul><li>UGX 50,000 buys 100 bricks</li><li>UGX 500,000 buys a bag of cement for every row of pews</li><li>UGX 5,000,000 roofs one bay</li></ul>',
            'goal_amount' => 150000000, 'currency' => 'UGX', 'status' => 'active', 'visibility' => 'public', 'show_leaderboard' => true,
            'allow_anonymous' => true, 'is_featured' => true, 'starts_at' => now()->subMonths(3), 'ends_at' => now()->addMonths(5),
        ]);
        app(CampaignService::class)->createDefaultMilestones($campaign);
        $youth = $campaign->teams()->create(['church_id' => $grace->id, 'name' => 'Youth', 'goal_amount' => 30000000, 'color' => '#2D7FF9']);
        $women = $campaign->teams()->create(['church_id' => $grace->id, 'name' => 'Women’s Guild', 'goal_amount' => 30000000, 'color' => '#BC2649']);

        $welfare = $grace->campaigns()->create([
            'user_id' => $pastor->id, 'title' => 'Christmas Welfare Drive', 'type' => 'welfare',
            'short_description' => 'Food parcels for 200 vulnerable families this Christmas.', 'goal_amount' => 20000000,
            'status' => 'active', 'visibility' => 'public', 'ends_at' => now()->addWeeks(6),
        ]);
        app(CampaignService::class)->createDefaultMilestones($welfare);

        $givers = User::whereHas('subscriptions', fn ($q) => $q->where('church_id', $grace->id))->get();
        $methods = ['mtn_momo', 'mtn_momo', 'airtel_money', 'airtel_money', 'stripe', 'cash', 'bank'];
        $categories = ['tithe', 'tithe', 'tithe', 'offering', 'offering', 'building_fund', 'missions', 'welfare'];
        $n = 0;

        foreach (range(0, 120) as $i) {
            $giver = $givers->random();
            $forCampaign = $i % 3 === 0;
            $paidAt = now()->subDays(random_int(0, 84))->setTime(random_int(7, 20), random_int(0, 59));
            $amount = collect([5000, 10000, 20000, 50000, 100000, 200000, 500000])->random();
            $donation = $grace->donations()->create([
                'user_id' => $giver->id, 'campaign_id' => $forCampaign ? $campaign->id : null,
                'campaign_team_id' => $forCampaign ? collect([$youth->id, $women->id, null])->random() : null,
                'category' => $forCampaign ? 'campaign' : collect($categories)->random(), 'amount' => $forCampaign ? $amount * 4 : $amount,
                'currency' => 'UGX', 'method' => collect($methods)->random(), 'phone' => $giver->phone,
                'status' => 'succeeded', 'paid_at' => $paidAt, 'provider_ref' => 'DEMO-'.strtoupper(Str::random(10)),
                'show_on_leaderboard' => $i % 2 === 0, 'is_anonymous' => $i % 9 === 0,
                'created_at' => $paidAt, 'updated_at' => $paidAt,
            ]);
            $donation->forceFill(['receipt_number' => 'R'.$grace->id.'-'.$paidAt->format('y').'-'.str_pad((string) $donation->id, 7, '0', STR_PAD_LEFT), 'receipt_sent_at' => $paidAt])->saveQuietly();
            $n++;
        }

        // Member's own giving history
        foreach ([[50000, 'tithe', 20], [20000, 'offering', 13], [200000, 'campaign', 6], [50000, 'tithe', 2]] as [$amt, $cat, $days]) {
            $d = $grace->donations()->create([
                'user_id' => $member->id, 'category' => $cat, 'amount' => $amt, 'currency' => 'UGX', 'method' => 'mtn_momo', 'phone' => $member->phone,
                'campaign_id' => $cat === 'campaign' ? $campaign->id : null, 'status' => 'succeeded', 'paid_at' => now()->subDays($days),
                'provider_ref' => 'DEMO-M'.$days.strtoupper(Str::random(6)), 'show_on_leaderboard' => true,
            ]);
            $d->forceFill(['receipt_number' => 'R'.$grace->id.'-'.now()->format('y').'-'.str_pad((string) $d->id, 7, '0', STR_PAD_LEFT)])->saveQuietly();
        }

        $grace->pledges()->create(['user_id' => $member->id, 'campaign_id' => $campaign->id, 'category' => 'campaign', 'amount' => 1200000, 'paid_amount' => 200000, 'frequency' => 'monthly', 'installments' => 6, 'start_date' => now()->subMonth(), 'due_date' => now()->addMonths(5), 'next_reminder_on' => now()->addWeek(), 'status' => 'active']);
        $grace->recurringGifts()->create(['user_id' => $member->id, 'category' => 'tithe', 'amount' => 50000, 'frequency' => 'monthly', 'method' => 'mtn_momo', 'phone' => $member->phone, 'next_run_on' => now()->addDays(10)->toDateString(), 'status' => 'active']);

        // Totals & milestones without firing celebrations.
        foreach ([$campaign, $welfare] as $c) {
            $ok = $c->donations()->where('status', 'succeeded');
            $c->update(['raised_amount' => (clone $ok)->sum('amount'), 'givers_count' => (clone $ok)->distinct()->count('user_id'), 'pledged_amount' => $c->pledges()->sum('amount')]);
            $c->milestones()->where('percent', '<=', $c->fresh()->percent())->update(['reached_at' => now()->subWeek()]);
            foreach ($c->teams as $team) {
                $team->update(['raised_amount' => (clone $ok)->where('campaign_team_id', $team->id)->sum('amount')]);
            }
        }

        $campaign->updates()->create(['church_id' => $grace->id, 'user_id' => $pastor->id, 'title' => 'Foundation complete! 🧱', 'body' => '<p>Thanks to your generosity the foundation was poured this month. Next: walls and pillars.</p>', 'published_at' => now()->subDays(4)]);
    }

    /**
     * Sprint 5: events (free, ticketed, past) and live streams.
     */
    protected function seedEventsAndLive(User $member, User $pastor): void
    {
        $grace = Church::where('slug', 'grace-lira')->first();
        if ($grace->events()->exists()) {
            return;
        }

        $camp = $grace->events()->create([
            'user_id' => $pastor->id, 'title' => 'Youth Camp 2026', 'description' => '<p>Three days of worship, teaching, games and friendship for ages 13–25.</p>',
            'location_name' => 'Church grounds, Lira', 'address' => 'Plot 12, Obote Avenue', 'lat' => 2.2475, 'lng' => 32.9001,
            'starts_at' => now()->addDays(20)->setTime(9, 0), 'ends_at' => now()->addDays(22)->setTime(16, 0), 'capacity' => 150,
            'rsvp_enabled' => true, 'is_paid' => true, 'status' => 'published', 'visibility' => 'public', 'qa_enabled' => true,
            'agenda' => [['time' => '09:00', 'title' => 'Registration & breakfast', 'speaker' => null], ['time' => '10:00', 'title' => 'Opening worship', 'speaker' => 'Worship team'], ['time' => '11:00', 'title' => 'Faith that works', 'speaker' => 'Pastor Joyce Atim']],
            'speakers' => [['name' => 'Pastor Joyce Atim', 'role' => 'Youth Pastor', 'bio' => 'Leads the youth ministry at Grace Community Church.']],
            'notified_at' => now(),
        ]);
        $camp->tickets()->createMany([
            ['church_id' => $grace->id, 'name' => 'Camper (3 days, meals)', 'price' => 50000, 'quantity' => 120, 'sold_count' => 1],
            ['church_id' => $grace->id, 'name' => 'Day pass', 'price' => 15000, 'quantity' => null],
        ]);
        $camp->rsvps()->create(['church_id' => $grace->id, 'user_id' => $member->id, 'event_ticket_id' => $camp->tickets()->first()->id, 'quantity' => 1, 'status' => 'going', 'amount' => 50000]);

        $carols = $grace->events()->create([
            'user_id' => $pastor->id, 'title' => 'Christmas Carols Night', 'description' => '<p>An evening of carols, testimonies and fellowship. Bring the whole family!</p>',
            'location_name' => 'Main sanctuary', 'starts_at' => now()->addDays(9)->setTime(18, 0), 'ends_at' => now()->addDays(9)->setTime(21, 0),
            'rsvp_enabled' => true, 'status' => 'published', 'visibility' => 'public', 'notified_at' => now(),
        ]);
        foreach (User::role('member')->take(12)->get() as $u) {
            $carols->rsvps()->firstOrCreate(['user_id' => $u->id], ['church_id' => $grace->id, 'quantity' => random_int(1, 3), 'status' => 'going']);
        }

        $grace->events()->create([
            'user_id' => $pastor->id, 'title' => 'Leaders’ Retreat', 'starts_at' => now()->subDays(14)->setTime(9, 0), 'ends_at' => now()->subDays(14)->setTime(17, 0),
            'location_name' => 'Lira Hotel', 'rsvp_enabled' => true, 'status' => 'published', 'visibility' => 'members', 'notified_at' => now(),
        ]);

        $streaming = app(StreamingService::class);
        $next = $grace->liveStreams()->create([
            'user_id' => $pastor->id, 'title' => 'Sunday Service', 'description' => 'Join us live for worship and the Word.',
            'scheduled_at' => now()->next('Sunday')->setTime(10, 0), 'status' => 'scheduled', 'source_type' => 'rtmp', 'chat_enabled' => true,
        ]);
        $streaming->rotateKey($next);

        $last = $grace->liveStreams()->create([
            'user_id' => $pastor->id, 'title' => 'Sunday Service — A Great Cloud of Witnesses', 'scheduled_at' => now()->previous('Sunday')->setTime(10, 0),
            'started_at' => now()->previous('Sunday')->setTime(10, 2), 'ended_at' => now()->previous('Sunday')->setTime(12, 15),
            'status' => 'archived', 'source_type' => 'rtmp', 'peak_viewers' => 214, 'total_viewers' => 380, 'notified_at' => now(),
            'sermon_id' => $grace->sermons()->latest('published_at')->value('id'),
        ]);
        $streaming->rotateKey($last);
    }

    /**
     * Sprint 4: sermons, series, devotionals, announcements, reading plans.
     */
    protected function seedContent(User $member, User $pastor): void
    {
        $grace = Church::where('slug', 'grace-lira')->first();
        if ($grace->sermons()->exists()) {
            return;
        }

        $series = $grace->series()->create(['title' => 'Walking by Faith', 'description' => 'A six-week journey through Hebrews 11.', 'starts_on' => now()->subWeeks(6)]);
        $sermons = [
            ['Walking by Faith, Not by Sight', '2 Corinthians 5:7', 'Rev. Daniel Okello'],
            ['The Faith of Abraham', 'Hebrews 11:8-19', 'Rev. Daniel Okello'],
            ['When God Says Wait', 'Psalm 27:14', 'Pastor Joyce Atim'],
            ['Moses: Choosing the Harder Road', 'Hebrews 11:23-29', 'Rev. Daniel Okello'],
            ['Faith That Works', 'James 2:14-26', 'Pastor Joyce Atim'],
            ['A Great Cloud of Witnesses', 'Hebrews 12:1-3', 'Rev. Daniel Okello'],
        ];

        $audio = $this->demoAudioFile();
        foreach ($sermons as $i => [$title, $scripture, $preacher]) {
            $sermon = $grace->sermons()->create([
                'sermon_series_id' => $series->id,
                'user_id' => $pastor->id,
                'title' => $title,
                'scripture' => $scripture,
                'preacher' => $preacher,
                'description' => '<p>In this message we look at <strong>'.$scripture.'</strong> and what it means to trust God in everyday life in Lira and beyond.</p><ul><li>Faith is trusting God’s character</li><li>Faith obeys before it sees</li><li>Faith endures together</li></ul>',
                'language' => $i === 2 ? 'luo' : 'en',
                'status' => 'published',
                'visibility' => $i === 4 ? 'members' : 'public',
                'published_at' => now()->subWeeks(6 - $i)->startOfWeek()->addDays(6)->setTime(13, 0),
                'preached_on' => now()->subWeeks(6 - $i)->startOfWeek()->addDays(6),
                'duration_seconds' => (38 + $i * 3) * 60,
                'notified_at' => now(),
                'views_count' => random_int(40, 400),
                'listens_count' => random_int(20, 250),
            ]);
            if ($audio && $i === 5) {
                $sermon->addMedia($audio)->preservingOriginal()->toMediaCollection('audio');
            }
        }

        foreach (Church::where('slug', '!=', 'grace-lira')->where('status', 'active')->get() as $church) {
            $church->sermons()->create([
                'title' => 'Hope for Today', 'scripture' => 'Romans 15:13', 'preacher' => $church->pastor_name,
                'description' => '<p>A message of hope for every family.</p>', 'language' => 'en', 'status' => 'published',
                'visibility' => 'public', 'published_at' => now()->subDays(3), 'notified_at' => now(), 'duration_seconds' => 2700,
            ]);
        }

        $verses = [
            ['The Lord is my shepherd', 'Psalm 23:1', 'The Lord is my shepherd; I shall not want.'],
            ['Strength for today', 'Isaiah 40:31', 'Those who hope in the Lord will renew their strength.'],
            ['Peace that guards', 'Philippians 4:7', 'The peace of God, which transcends all understanding, will guard your hearts and your minds in Christ Jesus.'],
            ['New every morning', 'Lamentations 3:22-23', 'His compassions never fail. They are new every morning; great is your faithfulness.'],
            ['Be still', 'Psalm 46:10', 'Be still, and know that I am God.'],
            ['Love one another', 'John 13:34', 'As I have loved you, so you must love one another.'],
            ['Light for my path', 'Psalm 119:105', 'Your word is a lamp for my feet, a light on my path.'],
        ];
        foreach ($verses as $i => [$title, $ref, $text]) {
            $grace->devotionals()->create([
                'user_id' => $pastor->id, 'title' => $title, 'scripture' => $ref, 'scripture_text' => $text,
                'body' => '<p>Take a moment today to sit with this verse. Read it slowly three times. What word stands out to you?</p><p>God is faithful in every season — in the dry season and in the rains. Whatever you face today, you do not face it alone.</p>',
                'prayer' => 'Lord, help me trust you today and walk in your peace. Amen.',
                'publish_date' => today()->subDays(6 - $i), 'status' => 'published', 'visibility' => 'public', 'notified_at' => now(),
            ]);
        }

        $grace->announcements()->create([
            'user_id' => $pastor->id, 'title' => 'Christmas Carols Night — 20 December',
            'body' => '<p>Join us for an evening of carols, testimonies and fellowship at 6 PM. Bring a friend!</p>',
            'audience' => 'public', 'priority' => 'normal', 'status' => 'published', 'published_at' => now()->subDays(2), 'notified_at' => now(),
        ]);
        $grace->announcements()->create([
            'user_id' => $pastor->id, 'title' => 'Workers’ meeting this Saturday',
            'body' => '<p>All ushers, choir and media team: meeting at 10 AM in the main hall.</p>',
            'audience' => 'members', 'priority' => 'urgent', 'status' => 'published', 'published_at' => now()->subHours(5), 'notified_at' => now(),
        ]);

        $template = ReadingPlanTemplates::all()['bible-in-a-year'];
        $plan = $grace->readingPlans()->create([
            'title' => $template['title'], 'description' => $template['description'], 'entries' => $template['entries'],
            'days_count' => count($template['entries']), 'is_published' => true,
        ]);
        $gospels = ReadingPlanTemplates::all()['gospels-30'];
        $grace->readingPlans()->create([
            'title' => $gospels['title'], 'description' => $gospels['description'], 'entries' => $gospels['entries'],
            'days_count' => count($gospels['entries']), 'is_published' => true,
        ]);
        $plan->progress()->create([
            'church_id' => $grace->id, 'user_id' => $member->id, 'started_on' => today()->subDays(46),
            'completed_days' => range(1, 46), 'current_streak' => 12, 'longest_streak' => 21, 'last_read_on' => today()->subDay(),
        ]);
    }

    /**
     * A short generated WAV tone so the demo sermon player has real audio.
     */
    protected function demoAudioFile(): ?string
    {
        $path = storage_path('app/demo-sermon.wav');
        if (! file_exists($path)) {
            $rate = 8000;
            $seconds = 20;
            $samples = '';
            for ($i = 0; $i < $rate * $seconds; $i++) {
                $t = $i / $rate;
                $env = min(1, $t * 2) * min(1, ($seconds - $t) * 2);
                $v = 0.35 * $env * (sin(2 * M_PI * 220 * $t) + 0.5 * sin(2 * M_PI * 330 * $t)) / 1.5;
                $samples .= chr((int) (128 + 127 * $v));
            }
            $header = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate, 1, 8).'data'.pack('V', strlen($samples));
            file_put_contents($path, $header.$samples);
        }

        return $path;
    }

    /**
     * Sprint 3: followers spread over the last few months + feed posts.
     */
    protected function seedSubscriptionsAndPosts(User $member, User $pastor): void
    {
        $churches = Church::where('status', 'active')->get();
        $people = User::role('member')->whereNotIn('email', ['admin@faithfellows.app'])->get();

        foreach ($people as $i => $person) {
            foreach ($churches->random(min($churches->count(), 1 + $i % 3)) as $church) {
                $church->subscriptions()->firstOrCreate(['user_id' => $person->id], [
                    'status' => 'active',
                    'stage' => collect(['visitor', 'new_member', 'member', 'member', 'member', 'worker', 'leader'])->random(),
                    'share_email' => $person->email_consent,
                    'subscribed_at' => now()->subDays(random_int(0, 90)),
                    'approved_at' => now(),
                    'source' => 'directory',
                ]);
            }
        }

        foreach (['grace-lira', 'new-life-kampala', 'st-peters-gulu'] as $slug) {
            Church::where('slug', $slug)->first()?->subscriptions()->updateOrCreate(
                ['user_id' => $member->id],
                ['status' => 'active', 'stage' => 'member', 'share_email' => true, 'subscribed_at' => now()->subMonths(4), 'approved_at' => now()],
            );
        }

        $updates = [
            "🙏 Thank you to everyone who joined Sunday's thanksgiving service. Over 400 people worshipped with us in person and online!",
            'Youth Camp registration is now open! Ages 13–25, 12–15 December at the church grounds. Speak to Pastor Joyce or register in the Events tab.',
            "This week's midweek Bible study continues our series in the book of James. Bring a friend — Wednesday at 5:30 PM.",
            'Our welfare team visited 12 families in Adyel this weekend with food packages. Thank you for your generous giving to the welfare fund. ❤️',
            'Choir rehearsals move to Saturday 3 PM for the Christmas season. All singers welcome!',
        ];

        foreach ($churches as $c => $church) {
            if ($church->posts()->exists()) {
                continue;
            }
            foreach ($updates as $p => $body) {
                if (($p + $c) % 2 === 1 && $church->slug !== 'grace-lira') {
                    continue;
                }
                $church->posts()->create([
                    'user_id' => $church->slug === 'grace-lira' ? $pastor->id : null,
                    'body' => $body,
                    'visibility' => $p === 3 ? 'members' : 'public',
                    'status' => 'published',
                    'is_pinned' => $p === 1 && $church->slug === 'grace-lira',
                    'published_at' => now()->subDays($p * 2 + $c)->subHours($c),
                    'notified_at' => now()->subDays($p * 2 + $c)->subHours($c),
                ]);
            }
        }
    }

    /**
     * Sprint 10: reading circle, notes, quizzes (a finished game and a live lobby),
     * prayer requests awaiting approval and a pastor's private reply.
     */
    protected function seedFellowship(User $member, User $pastor): void
    {
        $grace = Church::where('slug', 'grace-lira')->first();
        if (! $grace || $grace->quizzes()->exists()) {
            return;
        }

        $people = User::whereIn('id', $grace->subscriptions()->where('status', 'active')->select('user_id'))
            ->whereKeyNot($member->id)->orderBy('id')->get();

        // ── Reading circle on "The Gospels in 30 Days" ──────────────────
        $gospels = $grace->readingPlans()->where('title', 'The Gospels in 30 Days')->first();
        if ($gospels && $people->count() >= 2) {
            $circle = $grace->readingCircles()->create([
                'reading_plan_id' => $gospels->id, 'created_by' => $member->id, 'name' => 'Morning Readers', 'code' => 'GRACE7',
                'reading_time' => '06:00', 'reading_days' => [0, 1, 2, 3, 4, 5, 6], 'timezone' => 'Africa/Kampala', 'max_members' => 4,
            ]);
            $partners = collect([$member])->merge($people->take(2));
            $summaries = [
                1 => [
                    'Matthew 1 reminded me that God works through ordinary, imperfect families — even Rahab and Ruth are in Jesus’ line. There is hope for my family too.',
                    'The angel told Joseph “do not be afraid”. I needed that today with all the uncertainty at work.',
                    'Wise men travelled so far just to worship. Am I that eager to meet Jesus every morning?',
                ],
                2 => [
                    'John the Baptist’s message was simple: repent, the kingdom is near. Praying for a humble heart this week.',
                    'Jesus was baptised even though He had no sin — He stood with us. Grateful.',
                ],
            ];
            foreach ($partners as $i => $person) {
                $circle->memberships()->create(['user_id' => $person->id, 'role' => $i === 0 ? 'owner' : 'member', 'joined_at' => now()->subDays(3)]);
                $done = collect($summaries)->filter(fn ($s) => isset($s[$i]))->keys()->all();
                $gospels->progress()->firstOrCreate(['user_id' => $person->id], [
                    'church_id' => $grace->id, 'started_on' => today()->subDays(2), 'completed_days' => $done,
                    'current_streak' => count($done), 'longest_streak' => count($done), 'last_read_on' => $done ? today()->subDays(3 - count($done)) : null,
                ]);
                foreach ($summaries as $day => $texts) {
                    if (isset($texts[$i])) {
                        ReadingCircleEntry::create([
                            'reading_circle_id' => $circle->id, 'user_id' => $person->id, 'day' => $day,
                            'summary' => $texts[$i], 'completed_at' => now()->subDays(3 - $day)->setTime(6, 20 + $i * 7),
                        ]);
                    }
                }
            }
        }

        // ── Notes ───────────────────────────────────────────────────────
        $sermon = $grace->sermons()->latest('published_at')->first();
        $notes = [
            ['bible:JHN.3', 'John 3', route('bible.read', ['JHN', 3]), 'Born again', "# What does it mean to be born again?\n- Not by works — by the Spirit (v5–8)\n- God *so loved* the world (v16)\n> For God so loved the world, that he gave his only begotten Son (John 3:16)\n\nPray for Uncle Okot this week.", 'rose', true],
            ['bible:PSA.23', 'Psalms 23', route('bible.read', ['PSA', 23]), 'The Lord is my shepherd', "Read this at the hospital with Mum.\n- He leads, He restores, He walks *with* me in the valley\n- Goodness and mercy **follow** me", 'mint', false],
            ['note:retreat2026', null, null, 'Women’s retreat ideas', "- Theme: Rooted (Colossians 2:7)\n- Venue: Ngetta hills\n- Ask Sarah about transport budget", 'sun', false],
        ];
        if ($sermon) {
            $notes[] = ['sermon:'.$sermon->id, $sermon->title, route('sermons.show', $sermon), null, "# {$sermon->title}\n- Faith is shown by what we do (James 2:17)\n- Challenge: visit one person in need this week\n> Be doers of the word, and not hearers only", 'paper', false];
        }
        foreach ($notes as [$context, $reference, $url, $title, $body, $color, $pinned]) {
            Note::create([
                'user_id' => $member->id, 'church_id' => str_starts_with($context, 'sermon') ? $grace->id : null, 'context' => $context,
                'reference' => $reference, 'url' => $url ? parse_url($url, PHP_URL_PATH) : null, 'title' => $title, 'body' => $body,
                'color' => $color, 'is_pinned' => $pinned,
            ]);
        }

        // Verses the member coloured in the Bible reader.
        foreach ([['JHN', 3, 16, 'yellow'], ['JHN', 3, 17, 'yellow'], ['JHN', 3, 3, 'green'], ['PSA', 23, 1, 'blue'], ['PSA', 23, 4, 'pink'], ['ROM', 8, 28, 'yellow']] as [$book, $chapter, $verse, $color]) {
            BibleHighlight::updateOrCreate(['user_id' => $member->id, 'book' => $book, 'chapter' => $chapter, 'verse' => $verse], ['color' => $color]);
        }

        // ── Quizzes ─────────────────────────────────────────────────────
        $trivia = $grace->quizzes()->create(['user_id' => $pastor->id, 'title' => 'Bible Heroes Trivia', 'description' => 'How well do you know the heroes of faith? Fast answers score more!']);
        $questions = [
            ['Who built the ark?', ['Moses', 'Noah', 'Abraham', 'David'], 1],
            ['How many days and nights did it rain during the flood?', ['7', '12', '40', '100'], 2],
            ['Who defeated Goliath?', ['Saul', 'Samson', 'David', 'Jonathan'], 2],
            ['Which queen saved her people from Haman’s plot?', ['Esther', 'Ruth', 'Deborah', 'Jezebel'], 0],
            ['Daniel was thrown into a den of…', ['Bears', 'Snakes', 'Lions', 'Wolves'], 2],
            ['Who was swallowed by a great fish?', ['Jonah', 'Elijah', 'Peter', 'Paul'], 0],
            ['What did Jesus turn water into at Cana?', ['Milk', 'Wine', 'Oil', 'Honey'], 1],
            ['How many disciples did Jesus choose?', ['7', '10', '12', '70'], 2],
        ];
        foreach ($questions as $i => [$q, $options, $correct]) {
            $trivia->questions()->create(['position' => $i, 'question' => $q, 'options' => $options, 'correct_option' => $correct, 'time_limit' => QuizService::questionSeconds(), 'points' => $i === 7 ? 2000 : 1000]);
        }

        $youth = $grace->quizzes()->create(['user_id' => $pastor->id, 'title' => 'Youth Night: Life of Jesus', 'description' => 'Six quick questions for Friday youth night.']);
        foreach ([
            ['In which town was Jesus born?', ['Nazareth', 'Bethlehem', 'Jerusalem', 'Capernaum'], 1],
            ['Who baptised Jesus?', ['Peter', 'John the Baptist', 'Andrew', 'James'], 1],
            ['How many loaves fed the 5,000?', ['2', '5', '7', '12'], 1],
            ['Which disciple walked on water?', ['Peter', 'Thomas', 'Judas', 'Matthew'], 0],
            ['Who denied Jesus three times?', ['John', 'Peter', 'Philip', 'Mark'], 1],
            ['On which day did Jesus rise again?', ['Friday', 'Saturday', 'The first day of the week', 'The seventh day'], 2],
        ] as $i => [$q, $options, $correct]) {
            $youth->questions()->create(['position' => $i, 'question' => $q, 'options' => $options, 'correct_option' => $correct, 'time_limit' => QuizService::questionSeconds(), 'points' => 1000]);
        }

        // A finished game with a full scoreboard.
        $finished = QuizSession::create([
            'church_id' => $grace->id, 'quiz_id' => $trivia->id, 'host_id' => $pastor->id, 'pin' => '481920', 'status' => 'finished',
            'current_index' => count($questions) - 1, 'question_started_at' => now()->subDays(2), 'started_at' => now()->subDays(2)->subMinutes(12), 'finished_at' => now()->subDays(2),
        ]);
        $players = collect([$member])->merge($people->take(11));
        $trivia->load('questions');
        foreach ($players as $p => $person) {
            $player = $finished->players()->create(['user_id' => $person->id, 'nickname' => $person->name]);
            $score = 0;
            $right = 0;
            foreach ($trivia->questions as $question) {
                $skill = $p === 0 ? 85 : max(35, 80 - $p * 4);
                $isRight = mt_rand(1, 100) <= $skill;
                $option = $isRight ? $question->correct_option : ($question->correct_option + mt_rand(1, 3)) % count($question->options);
                $ms = mt_rand(1200, 9500);
                $points = $isRight ? (int) round($question->points * (1 - $ms / ($question->time_limit * 1000) / 2)) : 0;
                QuizAnswer::create(['quiz_session_id' => $finished->id, 'quiz_player_id' => $player->id, 'quiz_question_id' => $question->id, 'option' => $option, 'is_correct' => $isRight, 'points' => $points, 'response_ms' => $ms]);
                $score += $points;
                $right += (int) $isRight;
            }
            $player->update(['score' => $score, 'correct_count' => $right]);
        }

        // A lobby waiting to start — members see "Happening now".
        $lobby = QuizSession::create(['church_id' => $grace->id, 'quiz_id' => $youth->id, 'host_id' => $pastor->id, 'pin' => '725314', 'status' => 'lobby']);
        foreach ($people->slice(2, 4) as $person) {
            $lobby->players()->create(['user_id' => $person->id, 'nickname' => $person->name]);
        }

        // ── Prayer: requests waiting for the pastor, and a private reply ──
        foreach ([
            [$people->get(3), 'Travel mercies', 'My daughter travels to Kampala on Saturday for university interviews. Please pray for safety and favour.'],
            [$people->get(4), null, 'Our small business has struggled since the market fire. Pray for provision and wisdom.'],
        ] as [$author, $title, $body]) {
            if ($author) {
                $grace->prayerRequests()->create(['user_id' => $author->id, 'title' => $title, 'body' => $body, 'visibility' => 'church', 'status' => 'pending', 'chain_enabled' => true]);
            }
        }
        $private = $grace->prayerRequests()->create([
            'user_id' => $member->id, 'title' => 'Guidance', 'body' => 'I have a big decision to make about a job in Kampala and would value the pastors’ counsel and prayer.',
            'visibility' => 'pastor', 'status' => 'open', 'created_at' => now()->subDays(2),
        ]);
        $private->update([
            'pastor_response' => "Grace, we are praying with you. Read Proverbs 3:5–6 this week and let's talk after Sunday service — come and find me by the welcome desk.\n— Pastor Daniel",
            'responded_by' => $pastor->id, 'responded_at' => now()->subDay(),
        ]);
    }
}
