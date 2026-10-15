<x-layouts.public :title="__('Privacy Policy')">
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
            <p>FaithFellows (“we”, “us”) is operated by {{ $c['name'] }}, {{ $c['address'] }}, {{ $c['po_box'] }}. This policy explains what personal data we collect, why, and the choices you have. It is written to align with the Uganda Data Protection and Privacy Act, 2019 and GDPR principles.</p>

            <h2>1. Who controls your data</h2>
            <p>For your FaithFellows account, Kakebe Technologies is the data controller. Each church you subscribe to is an independent controller of the information it collects through its church space (for example giving records, pastoral notes or event registrations). We process that information on the church’s behalf.</p>

            <h2>2. What we collect</h2>
            <ul>
                <li><strong>Account data:</strong> name, email, password (stored only as a secure hash), phone, country, language, timezone and optional profile details such as photo, birthday and anniversary.</li>
                <li><strong>Church activity:</strong> churches you subscribe to, notification preferences, prayer requests, testimonies, comments, RSVPs, attendance and small-group membership.</li>
                <li><strong>Giving data:</strong> amounts, categories, dates, payment method type and provider references. <strong>We never store card numbers</strong> — card payments are handled by PCI-compliant providers (Stripe). Mobile money is processed by MTN, Airtel or Iotec.</li>
                <li><strong>Technical data:</strong> IP address, device and browser type, sessions and basic usage analytics used to secure and improve the service.</li>
            </ul>
            <p><strong>In the mobile app</strong> we also use:</p>
            <ul>
                <li><strong>Google sign-in</strong> (optional): your name and email address from your Google account. We never see your Google password.</li>
                <li><strong>Approximate location</strong> (optional, only while you use the app and only if you allow it): to show churches and believers near you. Other members see roughly how far away you are, never where you are.</li>
                <li><strong>Camera</strong> (church staff only): to scan guests’ QR tickets at events. Pictures are not kept.</li>
                <li><strong>Photos</strong> you choose yourself, for your profile or an event.</li>
                <li><strong>Notifications:</strong> a code that identifies your phone so we can send you notifications you have switched on.</li>
                <li><strong>Your own figures</strong> (minutes with the Bible, listening time, shares, days opened): shown to you only; churches see anonymous totals.</li>
            </ul>
            <p>We do not show ads and we do not sell your data.</p>

            <h2>3. How we use it</h2>
            <ul>
                <li>To provide the service: authenticate you, deliver content and notifications, and process gifts and receipts.</li>
                <li>To keep the platform safe: fraud prevention, rate limiting, audit logs and moderation.</li>
                <li>To communicate with you about your account. Marketing messages are only sent with your consent.</li>
            </ul>

            <h2>4. Sharing with churches</h2>
            <p>Churches you subscribe to can see your name and subscription. Your email address is only shared with a church if you turn on <em>“Share my email with churches I subscribe to”</em>. You appear in a member directory only if you opt in. Anonymous gifts and prayer requests hide your name from other members; church finance staff may still see giver details where required for accounting.</p>

            <h2>5. Service providers</h2>
            <p>We use trusted processors for hosting, email delivery, SMS/WhatsApp (Africa’s Talking), payments (MTN MoMo, Airtel Money, Iotec, Stripe) and content delivery (Cloudflare). They may only process data on our instructions.</p>

            <h2>6. Retention</h2>
            <p>We keep account data while your account is active. Financial records are retained as required for church accounting and law, and are anonymised when you delete your account.</p>
            <p>You can delete your account yourself in the app (Me → Delete my account) or on the website; <a href="{{ route('account.delete') }}">here is how, and what is removed and what is kept</a>.</p>

            <h2>7. Your rights</h2>
            <p>You can access, correct, export (Profile → Data & privacy → Export my data) and delete your data, withdraw consent, and control notifications per church, including quiet hours. To make a request or complaint, email <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a>. You may also contact the Personal Data Protection Office of Uganda.</p>

            <h2>8. Security</h2>
            <p>We use HTTPS everywhere, hashed passwords, role-based access control, strict separation of each church’s data, optional two-factor authentication and audit logging of administrative actions.</p>

            <h2>9. Children</h2>
            <p>FaithFellows accounts are intended for people aged 13 and above. Churches running children’s ministry must obtain parental consent before recording children’s information.</p>

            <h2>10. Changes</h2>
            <p>We will notify you in the app or by email of material changes to this policy.</p>

            <p class="small text-ff-muted">Contact: {{ $c['name'] }} · {{ $c['phone'] }} · {{ $c['email'] }}</p>
        </div>
    </section>
</x-layouts.public>
