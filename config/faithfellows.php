<?php

/*
|--------------------------------------------------------------------------
| FaithFellows platform configuration
|--------------------------------------------------------------------------
| Brand copy, pricing, permission catalog and lookup lists live here so
| they can be changed in one place (and overridden per environment).
*/

return [

    'brand' => [
        'name' => 'FaithFellows',
        'tagline' => 'Empowering Your Ministry in the Digital Age',
        'problem' => 'Traditional methods of reaching and engaging congregations are no longer sufficient. Expensive radio and TV broadcasts limit your reach. FaithFellows offers a cost-effective, far-reaching, and user-friendly digital solution to keep your ministry thriving.',
        'mission' => "To empower faith communities with technology that simplifies church management, deepens engagement, and expands the Gospel's reach — at a price every church can afford.",
        'vision' => 'Every church in Uganda — and beyond — digitally connected, efficiently managed, and reaching its community with the love of Christ.',
    ],

    'company' => [
        'name' => 'Kakebe Technologies Limited',
        'address' => 'Rm B1 Plot 43, Renzo City House, Obote Ave., Lira City',
        'po_box' => 'P.O Box 660031, Lira City',
        'phone' => '0393249845',
        'email' => env('FF_CONTACT_EMAIL', 'service@kakebe.tech'),
        'websites' => ['https://www.kakebe.tech', 'https://www.kakebetechcamp.tech'],
        'map_query' => 'Renzo City House, Obote Avenue, Lira, Uganda',
        'lat' => 2.2499,
        'lng' => 32.8999,
    ],

    'team' => [
        ['name' => 'Joseph Ekure', 'role' => 'FaithFellow Lead', 'phone' => '0766001451'],
        ['name' => 'Oscar Jerom Okello', 'role' => 'Technical Website Lead', 'phone' => null],
        ['name' => 'Sedrick Otolo', 'role' => 'Team Lead', 'phone' => '0777676206'],
    ],

    'root_domain' => env('FF_ROOT_DOMAIN', 'faithfellows.com'),

    // Shared hosting has no deploy step. With FF_AUTO_MIGRATE=true the scheduler applies new database migrations within a
    // minute of an update (see docs/DEPLOYMENT_SHARED_HOSTING.md). Leave it off everywhere else.
    'auto_migrate' => (bool) env('FF_AUTO_MIGRATE', false),

    'currency' => env('FF_DEFAULT_CURRENCY', 'UGX'),

    // Tests switch this on to check the "new sign-up" email; the console (seeders, imports) never announces accounts otherwise.
    'signup_alerts_in_console' => false,

    // A church that stays dormant (no staff sign-in, nothing published, no new followers) is warned after warn_days and blocked block_days
    // later. The super admin can change both in Settings.
    'dormancy' => ['warn_days' => (int) env('FF_DORMANT_WARN_DAYS', 30), 'block_days' => (int) env('FF_DORMANT_BLOCK_DAYS', 14)],

    // Where the "Download the app" buttons on the website go. Super admin → Settings can change it without a code change
    // (a Play Store link when the app is published, or the APK link of the latest build).
    'app' => [
        'android_url' => env('FF_ANDROID_URL', 'https://expo.dev/artifacts/eas/kJ2-sK0H5y23g0z66gVQkRb9hL6_XvJQnWThWuCarrA.apk'),
    ],

    'pricing' => [
        'plan_price' => (int) env('FF_PLAN_PRICE', 60000),
        'domain_price' => (int) env('FF_DOMAIN_PRICE', 60000),
        'free_hosting_months' => (int) env('FF_FREE_HOSTING_MONTHS', 12),
        'grace_days' => (int) env('FF_GRACE_DAYS', 14),
        'includes' => [
            'Custom church website',
            'Free hosting for first year',
            'Domain: UGX 60,000/year (optional)',
            'Custom church email accounts',
            'Server maintenance & optimization',
            'Content hosting (sermons, media)',
            '2x weekly social media posts',
            'Google platform listing',
            'Payment integration',
            'Continuous tech support',
        ],
    ],

    /*
    | Platform-wide (Spatie) roles. Church-level sub-roles are stored in the
    | church_roles table and use the permission catalog below.
    */
    'platform_roles' => ['super_admin', 'church_admin', 'staff', 'member'],

    /*
    | Church permission catalog — used by the permission matrix editor and
    | enforced server-side by User::canInChurch() / policies.
    */
    'church_permissions' => [
        'Content' => [
            'content.manage' => 'Sermons, devotionals, announcements & reading plans',
            'media.manage' => 'Media library, uploads & playlists',
            'live.manage' => 'Schedule, start and stop live streams',
        ],
        'Events' => [
            'events.manage' => 'Events, RSVPs, tickets & check-in',
        ],
        'Finance' => [
            'giving.view' => 'View giving records & reports',
            'giving.manage' => 'Record cash, refunds, payouts & reconciliation',
            'campaigns.manage' => 'Campaigns, milestones & updates',
        ],
        'People' => [
            'members.manage' => 'Approve, message & export members',
            'groups.manage' => 'Small groups, volunteers & attendance',
            'care.manage' => 'Pastoral care, counseling & welfare (confidential)',
        ],
        'Community' => [
            'prayer.moderate' => 'Moderate prayer wall',
            'community.moderate' => 'Moderate comments, posts & testimonies',
            'notifications.send' => 'Compose & send notifications',
            'quizzes.manage' => 'Fun & games: create and host live quizzes',
        ],
        'Administration' => [
            'analytics.view' => 'View analytics & reports',
            'settings.manage' => 'Church profile, branding, website & integrations',
            'roles.manage' => 'Manage staff & roles',
        ],
    ],

    /*
    | Preset sub-roles created for every newly approved church.
    */
    'role_presets' => [
        'Editor' => ['content.manage', 'events.manage', 'analytics.view'],
        'Finance' => ['giving.view', 'giving.manage', 'campaigns.manage', 'analytics.view'],
        'Media' => ['media.manage', 'live.manage', 'content.manage'],
        'Events' => ['events.manage', 'quizzes.manage'],
        'Moderator' => ['prayer.moderate', 'community.moderate'],
    ],

    'denominations' => [
        'Anglican (Church of Uganda)', 'Catholic', 'Pentecostal', 'Baptist', 'Seventh-day Adventist',
        'Methodist', 'Presbyterian', 'Orthodox', 'Lutheran', 'Evangelical', 'Born Again / Charismatic',
        'Non-denominational', 'Other',
    ],

    'languages' => [
        'en' => 'English',
        'luo' => 'Luo',
        'lg' => 'Luganda',
        'sw' => 'Swahili',
    ],

    'giving_categories' => [
        'tithe' => 'Tithe',
        'offering' => 'Offering',
        'project' => 'Project',
        'building_fund' => 'Building Fund',
        'missions' => 'Missions',
        'welfare' => 'Welfare',
        'special' => 'Special',
        'campaign' => 'Campaign',
        'event' => 'Event Ticket',
        'pledge' => 'Pledge Payment',
    ],

    'payment_methods' => [
        'iotec' => 'Iotec (Mobile Money)',
        'mtn_momo' => 'MTN MoMo',
        'airtel_money' => 'Airtel Money',
        'stripe' => 'Card (Visa / Mastercard)',
        'bank' => 'Bank Transfer',
        'ussd' => 'USSD',
        'cash' => 'Cash',
    ],

    'quick_amounts' => [1000, 5000, 10000, 50000],

    'campaign_types' => [
        'building' => 'Building', 'missions' => 'Missions', 'welfare' => 'Welfare', 'event' => 'Event',
        'equipment' => 'Equipment', 'pastor_support' => 'Pastor Support', 'emergency' => 'Emergency',
        'youth' => 'Youth', 'education' => 'Education', 'custom' => 'Custom',
    ],

    'notification_categories' => [
        'live' => 'Live streams',
        'sermon' => 'New sermons',
        'event' => 'Events & reminders',
        'giving' => 'Giving & receipts',
        'announcement' => 'Announcements',
        'update' => 'Church updates',
        'prayer' => 'Prayer wall',
        'devotional' => 'Devotionals',
        'reading' => 'Reading partners',
        'games' => 'Quizzes & games',
        'social' => 'People & messages',
        'inspiration' => 'Daily inspiration',
        'verse' => 'Verse of the day',
        'podcast' => 'New podcasts',
    ],

    'notification_channels' => [
        'database' => 'In-app',
        'mail' => 'Email',
        'webpush' => 'Push',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
    ],

    // Fallback chain when the preferred channel is unavailable for a user.
    'notification_fallback' => ['webpush', 'mail', 'sms', 'whatsapp'],

    // Countries treated as "home" for diaspora mode (others see card first).
    'home_countries' => ['UG'],

    // Default usage limits (overridden by the church's plan).
    'limits' => [
        'storage_mb' => (int) env('FF_DEFAULT_STORAGE_MB', 5120),
        'stream_hours' => (int) env('FF_DEFAULT_STREAM_HOURS', 40),
        'admins' => (int) env('FF_DEFAULT_ADMINS', 10),
        'branches' => (int) env('FF_DEFAULT_BRANCHES', 5),
        'max_upload_mb' => (int) env('FF_MAX_UPLOAD_MB', 200),
        // Songs and other audio: the platform admin (the gospel radio) may upload bigger files than church staff and everyone else.
        'max_audio_mb' => (int) env('FF_MAX_AUDIO_MB', 150),
        'max_admin_audio_mb' => (int) env('FF_MAX_ADMIN_AUDIO_MB', 400),
        // A member can follow at most this many churches at a time (staff roles don't count).
        'subscriptions' => (int) env('FF_MAX_SUBSCRIPTIONS', 3),
        // The member home slider shows a church once it has this many slider photos.
        'min_slides' => 4,
        'max_slides' => 8,
        'reading_circle_members' => 6,
    ],

    /*
    | Church contact details (phone, email, website, social links, street address)
    | are only shown to people who follow the church, its staff and platform admins.
    */
    'privacy' => [
        'contacts_for_subscribers_only' => (bool) env('FF_CONTACTS_FOR_SUBSCRIBERS_ONLY', true),
    ],

    /*
    | Live quizzes run by themselves: each question is open for question_seconds,
    | the answer and leaderboard show for reveal_seconds, then the next question starts.
    */
    'quizzes' => [
        'question_seconds' => (int) env('FF_QUIZ_QUESTION_SECONDS', 10),
        'reveal_seconds' => (int) env('FF_QUIZ_REVEAL_SECONDS', 5),
    ],

    /*
    | In-app Bible. 'bible_api_com' needs no key (public-domain English texts);
    | 'api_bible' needs API_BIBLE_KEY and the translation's API.Bible id; 'esv' needs ESV_API_KEY (api.esv.org) and is
    | licensed text that is never stored on the server or the phone ('offline' => false). The default is the ESV; until
    | its key is set the reader quietly falls back to the King James Version.
    */
    'bible' => [
        'default' => env('BIBLE_DEFAULT_TRANSLATION', 'esv'),
        'translations' => [
            'esv' => ['name' => 'English Standard Version', 'short' => 'ESV', 'language' => 'English', 'provider' => 'esv', 'offline' => false],
            'kjv' => ['name' => 'King James Version', 'short' => 'KJV', 'language' => 'English', 'provider' => 'bible_api_com'],
            'web' => ['name' => 'World English Bible', 'short' => 'WEB', 'language' => 'English', 'provider' => 'bible_api_com'],
            'bbe' => ['name' => 'Bible in Basic English', 'short' => 'BBE', 'language' => 'English', 'provider' => 'bible_api_com'],
            'asv' => ['name' => 'American Standard Version', 'short' => 'ASV', 'language' => 'English', 'provider' => 'bible_api_com'],
            'darby' => ['name' => 'Darby Bible', 'short' => 'DBY', 'language' => 'English', 'provider' => 'bible_api_com'],
            'dra' => ['name' => 'Douay-Rheims 1899 American Edition', 'short' => 'DRA', 'language' => 'English', 'provider' => 'bible_api_com'],
            'webbe' => ['name' => 'World English Bible, British Edition', 'short' => 'WEBBE', 'language' => 'English', 'provider' => 'bible_api_com'],
            // Enabled automatically when API_BIBLE_KEY is set (ids from scripture.api.bible).
            'swahili' => ['name' => 'Biblia Habari Njema', 'short' => 'BHN', 'language' => 'Kiswahili', 'provider' => 'api_bible', 'id' => env('API_BIBLE_SWAHILI_ID')],
            'luganda' => ['name' => 'Bayibuli Entukuvu', 'short' => 'LUG', 'language' => 'Luganda', 'provider' => 'api_bible', 'id' => env('API_BIBLE_LUGANDA_ID')],
        ],
    ],

    'payments' => [
        'sandbox' => (bool) env('PAYMENTS_SANDBOX', true),
    ],
];
