<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\User;
use App\Services\ChurchOnboardingService;
use App\Support\ReadingPlanTemplates;
use Illuminate\Database\Seeder;
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
            'tagline' => 'Raising a generation on fire for God', 'languages' => ['en', 'lg'], 'brand_color' => '#8E1C37', 'is_featured' => true,
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
            'tagline' => 'Rivers of living water', 'languages' => ['en', 'sw'], 'brand_color' => '#2D7FF9',
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
                ]);
            }
        }
    }
}
