<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\User;
use App\Services\ChurchOnboardingService;
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
