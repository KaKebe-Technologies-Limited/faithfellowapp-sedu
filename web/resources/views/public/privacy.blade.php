<x-layouts.public :title="__('Privacy Policy')" :description="__('What FaithFellows collects, why, who sees it, and how to delete it.')">
    @php($c = config('faithfellows.company'))
    <section class="ff-page-hero">
        <div class="container" style="max-width: 860px">
            <span class="ff-eyebrow">{{ __('Legal') }}</span>
            <h1 class="mt-3">{{ __('Privacy Policy') }}</h1>
            <p class="text-ff-muted mb-0">{{ __('Last updated: :date', ['date' => '16 October 2026']) }}</p>
        </div>
    </section>
    <section class="ff-section pt-4">
        <div class="container ff-prose" style="max-width: 860px">
            <p>FaithFellows (“we”, “us”) is operated by {{ $c['name'] }}, {{ $c['address'] }}, {{ $c['po_box'] }}. This policy explains what we collect, why, who can see it, and the choices you have. It covers the FaithFellows mobile app, the website faithfellows.com and the church websites we host. It is written to follow the Uganda Data Protection and Privacy Act, 2019 and GDPR principles.</p>

            <h2>The short version</h2>
            <ul>
                <li>We collect what we need to run your account, your church life and your gifts.</li>
                <li>We do not sell your data, and there are no ads in FaithFellows.</li>
                <li>Your phone number, birthday, photo and location are optional.</li>
                <li>Your reading, listening and sharing figures are for you. Churches only see anonymous totals.</li>
                <li>You can download your data and delete your account yourself, at any time.</li>
            </ul>

            <h2>1. Who is responsible for your data</h2>
            <p>For your FaithFellows account, {{ $c['name'] }} is the data controller. Each church you follow is an independent controller of what it collects through its own church space (for example giving records, pastoral notes or event registrations). We process that information on the church’s behalf.</p>

            <h2>2. What we collect</h2>
            <ul>
                <li><strong>Account details:</strong> your name, email address, password (kept only as a secure hash), and optional phone number, country, town, language, gender, birthday, anniversary, short bio, interests and photo.</li>
                <li><strong>Church life:</strong> the churches you follow, your notification choices, prayer requests, testimonies, comments, messages, event sign-ups and tickets, attendance, group and reading-circle membership, and your notes and highlights.</li>
                <li><strong>Giving:</strong> amounts, what each gift is for, dates, the payment method, the mobile money number you used, and the payment provider’s reference. <strong>We never store card numbers.</strong> Mobile money is handled by MTN, Airtel and ioTec; cards by Stripe.</li>
                <li><strong>Your own figures:</strong> minutes with the Bible, listening time, scriptures and messages shared, and days you opened the app.</li>
                <li><strong>Technical data:</strong> IP address, device and browser type, app version, sessions and basic usage records, used to keep the service secure and working.</li>
            </ul>

            <h2>3. In the mobile app</h2>
            <ul>
                <li><strong>Google sign-in</strong> (optional): we receive your name and email address from your Google account. We never see your Google password and we read nothing else from your account.</li>
                <li><strong>Location</strong> (optional, only while you use the app, and only if you allow it): to show churches and believers near you. Other members see roughly how far away you are, never where you are. You can turn it off or forget it at any time.</li>
                <li><strong>Camera</strong> (church staff only): to scan guests’ QR tickets at events. Pictures are not kept.</li>
                <li><strong>Photos</strong> that you choose yourself, for your profile or an event.</li>
                <li><strong>Notifications:</strong> a code that identifies your phone, so that we can send the notifications you have switched on.</li>
                <li><strong>Audio:</strong> the app plays gospel radio and podcasts, also when the screen is off. It does not record sound and does not use your microphone.</li>
            </ul>

            <h2>4. How we use it</h2>
            <ul>
                <li>To provide the service: sign you in, show you your churches and content, send the notifications and emails you asked for, and process gifts and receipts.</li>
                <li>To suggest people and churches you may want to know, based on what you chose to share (interests, church, town, age range).</li>
                <li>To keep the platform safe: preventing fraud and abuse, limiting repeated attempts, moderating reports and keeping an audit log of administrative actions.</li>
                <li>To tell you about your account. We send marketing messages only with your consent.</li>
            </ul>

            <h2>5. Who can see it</h2>
            <ul>
                <li><strong>Your churches</strong> see your name and that you follow them. They see your email address only if you turn on <em>“Share my email with churches I subscribe to”</em>. Church finance staff see the details of gifts, because they keep the church’s books.</li>
                <li><strong>Other members</strong> see your name and photo where you take part (for example in a group or on the prayer wall), unless you post anonymously. You appear in “Grow your circle” only if you opt in, and only to adults who have also added their birthday. Phone numbers and email addresses cannot be sent in messages.</li>
                <li><strong>Our service providers</strong> process data only on our instructions: our web host; email delivery (Google); notifications (Expo and Google Firebase); SMS and WhatsApp (Africa’s Talking); payments (MTN, Airtel, ioTec and Stripe); live video (Cloudflare). Bible text comes from public Bible services that only receive the passage you ask for, not who you are.</li>
                <li><strong>Authorities</strong>, only when the law requires it.</li>
            </ul>
            <p>We do not sell your personal data.</p>

            <h2>6. How long we keep it, and deleting your account</h2>
            <p>We keep your account data while your account is active. You can delete your account yourself in the app (Me → Delete my account) or on the website; <a href="{{ route('account.delete') }}">this page explains how, what is removed and what is kept</a>. When you delete your account, your profile details, photo, devices, follows, notes, highlights and saved payment details are removed. Records of gifts and receipts are kept without your name, because churches must keep their books and the law requires it. What you shared with other people shows as “Deleted user”.</p>

            <h2>7. Your rights</h2>
            <p>You can see and correct your details in your profile, export your data (Profile → Data &amp; privacy → Export my data), delete your account, withdraw consent, and choose your notifications church by church, including quiet hours. To make a request or a complaint, email <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a>. You may also contact the Personal Data Protection Office of Uganda.</p>

            <h2>8. Security</h2>
            <p>We use HTTPS everywhere, hashed passwords, role-based access, strict separation of each church’s data, optional two-factor sign-in and an audit log of administrative actions. No system is perfectly safe; if something affects your data we will tell you and the authorities as the law requires.</p>

            <h2>9. Children</h2>
            <p>FaithFellows is for people aged 13 and over. Meeting other believers is only for adults aged 18 and over. Churches that run children’s ministry must get a parent’s consent before recording a child’s information.</p>

            <h2>10. Where your data is kept</h2>
            <p>Your data is stored on secure servers of our hosting provider. If it is processed outside Uganda, we make sure it is protected to the same standard.</p>

            <h2>11. Cookies on the website</h2>
            <p>The website uses only the cookies it needs: to keep you signed in, to protect forms, and to remember your language and data-saver choice. There are no advertising cookies.</p>

            <h2>12. Changes</h2>
            <p>If we change this policy in a way that matters, we will tell you in the app or by email, and show the new date above.</p>

            <p class="small text-ff-muted">Contact: {{ $c['name'] }} · {{ $c['phone'] }} · <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a></p>
        </div>
    </section>
</x-layouts.public>
