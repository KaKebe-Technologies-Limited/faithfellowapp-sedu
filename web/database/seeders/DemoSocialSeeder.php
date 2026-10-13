<?php

namespace Database\Seeders;

use App\Models\Connection;
use App\Models\DirectMessage;
use App\Models\SmallGroup;
use App\Models\SmallGroupPost;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo people for the app's "connect with people of faith" screens: friendly names, short bios and interests,
 * a few connections and messages for the demo member, and conversations inside the demo study groups.
 * Safe to run again (and on an existing demo database: `php artisan db:seed --class=DemoSocialSeeder`).
 */
class DemoSocialSeeder extends Seeder
{
    private const PEOPLE = [
        ['Mercy Atim', 'female'], ['David Opio', 'male'], ['Esther Nakato', 'female'], ['Joseph Mukasa', 'male'], ['Ruth Achieng', 'female'], ['Peter Odongo', 'male'],
        ['Joy Namukasa', 'female'], ['Isaac Wasswa', 'male'], ['Sarah Aber', 'female'], ['Moses Kiiza', 'male'], ['Dorcas Apio', 'female'], ['Timothy Lubega', 'male'],
        ['Naomi Auma', 'female'], ['Emmanuel Otim', 'male'], ['Lydia Nabirye', 'female'], ['Joshua Byaruhanga', 'male'], ['Priscilla Atieno', 'female'], ['Caleb Okidi', 'male'],
        ['Rebecca Nansubuga', 'female'], ['Andrew Tumwine', 'male'], ['Miriam Akot', 'female'], ['Nathan Ssebuwufu', 'male'], ['Hannah Birungi', 'female'], ['Stephen Ocen', 'male'],
        ['Deborah Namatovu', 'female'], ['Brian Mugisha', 'male'],
    ];

    private const BIOS = [
        'Worship team member who loves a good Bible study.', 'Youth leader. Coffee, Scripture and football.', 'New to the city and looking for a church family.',
        'Mum of three, prayer warrior, choir alto.', 'Teacher by day, intercessor by night.', 'Learning to walk closer with God every day.',
        'Missions-minded. I would love to pray with you.', 'Husband, father, small-group leader.', 'Praying for revival in our nation.',
        'Singer and sound engineer for Sunday services.',
    ];

    private const INTERESTS = ['Worship', 'Prayer', 'Bible study', 'Youth', 'Missions', 'Marriage', 'Family', 'Music', 'Evangelism', 'Women of faith', 'Men of faith', 'Business & faith', 'Students'];

    private const POSTS = [
        ['Good evening everyone 🙏 Reminder: we meet this week — please read the passage before we gather.', 'Amen! I have already read it twice. It spoke to me about trust.'],
        ['Does anyone have a good commentary to recommend for this book?', 'I use the Bible Speaks Today series. Very clear and short.'],
        ['Praise report: my interview went well. Thank you for praying with me!', 'To God be the glory! 🎉'],
        ['Who can bring tea and snacks on Saturday?', 'I can bring the tea.'],
    ];

    public function run(): void
    {
        $grace = User::where('email', 'member@faithfellows.test')->first();
        if (! $grace) {
            return;
        }

        $demo = ['member@faithfellows.test', 'pastor@faithfellows.test', 'finance@faithfellows.test'];
        $people = User::whereNotIn('email', $demo)->whereHas('subscriptions', fn ($q) => $q->where('status', 'active'))->with('subscriptions.church')->orderBy('id')->take(count(self::PEOPLE))->get();

        $grace->forceFill([
            'date_of_birth' => '1997-05-14', 'gender' => 'female', 'country' => 'UG', 'city' => 'Lira', 'bio' => 'Choir member and Bible study lover from Lira.',
            'interests' => ['Worship', 'Prayer', 'Bible study'], 'directory_opt_in' => true, 'open_to_connect' => true,
        ])->save();

        foreach ($people as $i => $user) {
            [$name, $gender] = self::PEOPLE[$i];
            $church = $user->subscriptions->first()?->church;
            $user->forceFill([
                'name' => $name,
                'gender' => $gender,
                'country' => 'UG',
                'city' => $church?->city ?: $user->city,
                'bio' => self::BIOS[$i % count(self::BIOS)],
                'interests' => collect(self::INTERESTS)->slice(($i * 3) % 10, 3)->values()->all(),
                'date_of_birth' => ($user->date_of_birth && $user->date_of_birth->lte(now()->subYears(18))) ? $user->date_of_birth : now()->subYears(21 + $i % 25)->subDays($i * 9),
                'directory_opt_in' => true,
                'open_to_connect' => $i % 3 === 0,
            ])->save();
        }

        $this->connections($grace, $people->filter(fn ($u) => $u->subscriptions->contains('church_id', 1))->values());
        $this->groupTalk();
    }

    /** Two friends with a chat, one request waiting for the member, one sent by them. */
    private function connections(User $grace, $neighbours): void
    {
        if ($neighbours->count() < 4) {
            return;
        }
        [$a, $b, $c, $d] = [$neighbours[0], $neighbours[1], $neighbours[2], $neighbours[3]];
        $make = fn (User $from, User $to, string $status, ?string $message = null) => Connection::firstOrCreate(
            ['requester_id' => $from->id, 'addressee_id' => $to->id],
            ['status' => $status, 'message' => $message, 'responded_at' => $status === 'accepted' ? now()->subDays(3) : null],
        );
        $make($grace, $a, 'accepted', 'Hello! We sit near each other on Sundays.');
        $make($b, $grace, 'accepted');
        $make($c, $grace, 'pending', 'Hi Grace, I saw you in the choir. Would love to connect!');
        $make($grace, $d, 'pending', 'Hello from the Bible study group.');

        foreach ([[$a, $b]] as $_) {
            if (! DirectMessage::between($grace->id, $a->id)->exists()) {
                $lines = [
                    [$a, 'Hi Grace! Are you coming to the youth conference?', 52],
                    [$grace, 'Yes! I registered already. Are you volunteering this year?', 50],
                    [$a, 'I am on the welcome team 🙌 Come by the gate and say hi.', 49],
                    [$grace, 'Will do. Also — thank you for the verse yesterday. It was exactly what I needed.', 6],
                    [$a, 'God is faithful! Psalm 46:10 changed my week too.', 5],
                ];
                foreach ($lines as [$from, $body, $hours]) {
                    $message = DirectMessage::create(['sender_id' => $from->id, 'recipient_id' => $from->id === $grace->id ? $a->id : $grace->id, 'body' => $body, 'read_at' => $from->id === $a->id && $hours > 10 ? now()->subHours($hours - 1) : ($from->id === $grace->id ? now()->subHours(1) : null)]);
                    $message->forceFill(['created_at' => now()->subHours($hours), 'updated_at' => now()->subHours($hours)])->saveQuietly();
                }
            }
        }
    }

    private function groupTalk(): void
    {
        foreach (SmallGroup::with(['memberships' => fn ($q) => $q->where('status', 'active')->with('user')])->get() as $group) {
            if (SmallGroupPost::where('small_group_id', $group->id)->exists()) {
                continue;
            }
            $members = $group->memberships->pluck('user')->filter()->values();
            if ($members->count() < 2) {
                continue;
            }
            $hours = 30;
            foreach (self::POSTS as $i => $pair) {
                foreach ($pair as $j => $body) {
                    $author = $members[($i * 2 + $j) % $members->count()];
                    $post = SmallGroupPost::create(['church_id' => $group->church_id, 'small_group_id' => $group->id, 'user_id' => $author->id, 'body' => $body]);
                    $post->forceFill(['created_at' => now()->subHours($hours), 'updated_at' => now()->subHours($hours)])->saveQuietly();
                    $hours -= 3;
                }
            }
        }
    }
}
