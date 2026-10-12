<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::role('super_admin')->first();

        $posts = [
            [
                'title' => 'Introducing FaithFellows: one platform for every church',
                'category' => 'Announcements',
                'excerpt' => 'Websites, sermons, live streams, giving and member care — built for Uganda and priced for every church.',
                'body' => '<p>Today we are excited to introduce <strong>FaithFellows</strong>, a shared digital platform where every church gets its own space.</p><h2>One account, many churches</h2><p>Believers sign up once and subscribe to as many churches as they like — their home church, their village church and the ministries that feed them during the week.</p><h2>Built for Uganda first</h2><p>Mobile money giving, data-saver mode, SMS and USSD fallbacks and support for English, Luo, Luganda and Swahili mean nobody is left behind.</p><blockquote>Every church in Uganda — and beyond — digitally connected.</blockquote>',
            ],
            [
                'title' => 'Five ways to grow giving with mobile money',
                'category' => 'Guides',
                'excerpt' => 'Practical steps for treasurers and pastors to make generosity easy, transparent and joyful.',
                'body' => '<p>Most Ugandans carry a mobile wallet. Here is how to make the most of it.</p><ol><li><strong>Publish your giving channels</strong> clearly on your church page.</li><li><strong>Use campaigns</strong> with a visible goal and progress bar.</li><li><strong>Send instant receipts</strong> so every giver knows their gift arrived.</li><li><strong>Offer recurring giving</strong> for tithes.</li><li><strong>Share updates</strong> and testimonies as projects progress.</li></ol>',
            ],
            [
                'title' => 'Streaming services on a tight data budget',
                'category' => 'Guides',
                'excerpt' => 'Why audio-only streams reach more of your congregation — and how to set one up.',
                'body' => '<p>Video is wonderful, but for many members a single service can cost a day’s wage in data. FaithFellows offers an <strong>audio-only mode</strong> that uses roughly a tenth of the data.</p><h2>Getting started</h2><p>All you need is a phone or laptop running OBS or Larix Broadcaster, your church stream key and a stable connection at the venue.</p>',
            ],
            [
                'title' => 'Keeping your church data safe',
                'category' => 'Resources',
                'excerpt' => 'Roles, permissions and audit logs: a short guide to protecting your congregation’s information.',
                'body' => '<p>Your members trust you with personal information. Give each staff member only the permissions they need — an Editor does not need access to giving records, and a Finance officer does not need pastoral notes.</p><p>Every administrative action on FaithFellows is written to an audit log so you always know who changed what.</p>',
            ],
        ];

        foreach ($posts as $i => $post) {
            BlogPost::updateOrCreate(['slug' => Str::slug($post['title'])], [
                ...$post,
                'author_id' => $author?->id,
                'status' => 'published',
                'reading_minutes' => max(2, (int) ceil(str_word_count(strip_tags($post['body'])) / 200)),
                'published_at' => now()->subDays(($i + 1) * 6),
            ]);
        }
    }
}
