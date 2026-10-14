<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Short inspiring messages for the home screen, three times a day: morning, mid-morning and evening. The super admin manages them
 * (Super admin, Inspiration); this migration starts the library with sixty of them so the home screen is never empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspirations', function (Blueprint $table) {
            $table->id();
            $table->enum('slot', ['morning', 'midmorning', 'evening'])->index();
            $table->string('body', 280);
            $table->string('reference', 80)->nullable()->comment('Only for a word-for-word Bible quotation (KJV)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('inspirations')->insert(collect($this->messages())->map(fn (array $m) => [
            'slot' => $m[0], 'body' => $m[1], 'reference' => $m[2] ?? null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('inspirations');
    }

    /** @return list<array{0: string, 1: string, 2?: string}> */
    private function messages(): array
    {
        return [
            // ── Morning ──
            ['morning', 'This is the day which the LORD hath made; we will rejoice and be glad in it.', 'Psalm 118:24'],
            ['morning', 'His mercies are new every morning. Yesterday’s failures do not follow you into today.'],
            ['morning', 'Begin with prayer, and the day begins with peace.'],
            ['morning', 'You were not made to carry today alone. Hand it to God before you pick up your phone.'],
            ['morning', 'Rise with purpose. God has already gone ahead of you into this day.'],
            ['morning', 'Small faithful steps this morning become great victories by evening.'],
            ['morning', 'Let gratitude be the first word on your lips today.'],
            ['morning', 'For I know the thoughts that I think toward you, saith the LORD, thoughts of peace, and not of evil, to give you an expected end.', 'Jeremiah 29:11'],
            ['morning', 'Speak kindly today. Your words can be someone’s sunrise.'],
            ['morning', 'Do not despise this ordinary morning. God works wonders in ordinary days.'],
            ['morning', 'For God hath not given us the spirit of fear; but of power, and of love, and of a sound mind.', '2 Timothy 1:7'],
            ['morning', 'Today, love someone in a way that costs you something.'],
            ['morning', 'My grace is sufficient for thee: for my strength is made perfect in weakness.', '2 Corinthians 12:9'],
            ['morning', 'A quiet moment with God this morning is worth more than a loud hour without Him.'],
            ['morning', 'Commit thy works unto the LORD, and thy thoughts shall be established.', 'Proverbs 16:3'],
            ['morning', 'You are loved before you achieve anything today.'],
            ['morning', 'The LORD is my light and my salvation; whom shall I fear?', 'Psalm 27:1'],
            ['morning', 'Be the reason someone remembers that God is good.'],
            ['morning', 'Casting all your care upon him; for he careth for you.', '1 Peter 5:7'],
            ['morning', 'Let your first thought be: Lord, what would You have me do today?'],

            // ── Mid-morning ──
            ['midmorning', 'And whatsoever ye do, do it heartily, as to the Lord, and not unto men.', 'Colossians 3:23'],
            ['midmorning', 'And let us not be weary in well doing: for in due season we shall reap, if we faint not.', 'Galatians 6:9'],
            ['midmorning', 'Pause. Breathe. Pray. Then carry on with strength.'],
            ['midmorning', 'The task in front of you is the place God has put you to serve.'],
            ['midmorning', 'Delay is not denial. Keep going; God’s timing is perfect.'],
            ['midmorning', 'When you feel stretched thin, remember who holds you together.'],
            ['midmorning', 'They that wait upon the LORD shall renew their strength.', 'Isaiah 40:31'],
            ['midmorning', 'He that is faithful in that which is least is faithful also in much.', 'Luke 16:10'],
            ['midmorning', 'Your effort is seen. Your faithfulness is counted.'],
            ['midmorning', 'If the day is heavy, lean harder on God, not less.'],
            ['midmorning', 'Choose patience in the next conversation. It may be someone’s turning point.'],
            ['midmorning', 'Do not compare your progress to anyone else’s. Walk your own path with God.'],
            ['midmorning', 'A hard morning does not make a hopeless day.'],
            ['midmorning', 'I can do all things through Christ which strengtheneth me.', 'Philippians 4:13'],
            ['midmorning', 'Keep your eyes on what God asked of you, not on what others are doing.'],
            ['midmorning', 'The integrity of the upright shall guide them.', 'Proverbs 11:3'],
            ['midmorning', 'Kindness at work is ministry. Do it quietly.'],
            ['midmorning', 'God is working even when you cannot see it.'],
            ['midmorning', 'Say a quick prayer for the person nearest to you right now.'],
            ['midmorning', 'Press on. The finish line is closer than your tiredness says.'],

            // ── Evening ──
            ['evening', 'He that keepeth thee will not slumber.', 'Psalm 121:3'],
            ['evening', 'Thank God for one thing that went right today, however small.'],
            ['evening', 'If we confess our sins, he is faithful and just to forgive us our sins, and to cleanse us from all unrighteousness.', '1 John 1:9'],
            ['evening', 'Peace I leave with you, my peace I give unto you: not as the world giveth, give I unto you.', 'John 14:27'],
            ['evening', 'Tonight, forgive someone. You will sleep lighter.'],
            ['evening', 'Take therefore no thought for the morrow: for the morrow shall take thought for the things of itself.', 'Matthew 6:34'],
            ['evening', 'O give thanks unto the LORD; for he is good: for his mercy endureth for ever.', 'Psalm 136:1'],
            ['evening', 'I will both lay me down in peace, and sleep: for thou, LORD, only makest me dwell in safety.', 'Psalm 4:8'],
            ['evening', 'Count your blessings, not your problems, before you close your eyes.'],
            ['evening', 'Your family, your friends, your church: pray for them by name tonight.'],
            ['evening', 'God was with you in every hour of this day. He is with you now.'],
            ['evening', 'Evening is the time to say: Thank You, Lord.'],
            ['evening', 'Turn off the noise and listen for the still small voice.', '1 Kings 19:12'],
            ['evening', 'Whatever is unfinished, God can finish while you sleep.'],
            ['evening', 'Sleep in confidence. Your future is in good hands.'],
            ['evening', 'Be still, and know that I am God.', 'Psalm 46:10'],
            ['evening', 'Let your last words today be a prayer.'],
            ['evening', 'You did what you could. God will do the rest.'],
            ['evening', 'A grateful heart sleeps in peace.'],
            ['evening', 'For so he giveth his beloved sleep.', 'Psalm 127:2'],
        ];
    }
};
