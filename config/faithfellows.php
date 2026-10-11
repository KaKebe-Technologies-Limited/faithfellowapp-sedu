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

    'root_domain' => env('FF_ROOT_DOMAIN', 'faithfellows.app'),

    'currency' => env('FF_DEFAULT_CURRENCY', 'UGX'),

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
        'Events' => ['events.manage'],
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
        'prayer' => 'Prayer wall',
        'devotional' => 'Devotionals',
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

    'payments' => [
        'sandbox' => (bool) env('PAYMENTS_SANDBOX', true),
    ],
];
