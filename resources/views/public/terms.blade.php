<x-layouts.public :title="__('Terms of Service')">
    @php
        $c = config('faithfellows.company');
        $p = config('faithfellows.pricing');
    @endphp
    <section class="ff-page-hero">
        <div class="container" style="max-width: 860px">
            <span class="ff-eyebrow">{{ __('Legal') }}</span>
            <h1 class="mt-3">{{ __('Terms of Service') }}</h1>
            <p class="text-ff-muted mb-0">{{ __('Last updated: :date', ['date' => '11 October 2026']) }}</p>
        </div>
    </section>
    <section class="ff-section pt-4">
        <div class="container ff-prose" style="max-width: 860px">
            <p>These terms govern your use of FaithFellows, a platform operated by {{ $c['name'] }} (“Kakebe”). By creating an account or registering a church you agree to them.</p>

            <h2>1. Accounts</h2>
            <p>You must provide accurate information and keep your password secure. One person, one account — you may subscribe to as many churches as you like. You are responsible for activity on your account.</p>

            <h2>2. Churches</h2>
            <p>Churches apply to join and are approved at Kakebe’s discretion. A church administrator accepts these terms on behalf of the church and is responsible for its staff, content, giving channels and compliance with applicable law, including charity, tax and data protection obligations toward its members.</p>

            <h2>3. Subscription & billing</h2>
            <ul>
                <li>The Starter plan costs UGX {{ number_format($p['plan_price']) }} per month. Hosting is free for the first {{ $p['free_hosting_months'] }} months.</li>
                <li>Optional custom domains cost UGX {{ number_format($p['domain_price']) }} per year.</li>
                <li>Invoices are payable by the due date. After a {{ $p['grace_days'] }}-day grace period, unpaid churches may be suspended until payment is received. Data is not deleted during suspension.</li>
                <li>You may cancel anytime; fees already paid are non-refundable except where required by law.</li>
            </ul>

            <h2>4. Giving & payments</h2>
            <p>Gifts are paid by members directly to the church through third-party payment providers. FaithFellows is a technology provider, not a bank or money transmitter, and does not hold church funds except where a payout arrangement is agreed in writing. Provider fees apply. Refunds and disputes are decided by the receiving church, subject to the provider’s rules.</p>

            <h2>5. Acceptable use</h2>
            <p>You must not upload unlawful, hateful, infringing or misleading content; harass others; attempt to access other churches’ data; abuse the payment system; or interfere with the platform’s security. Kakebe may remove content or suspend accounts that breach these terms.</p>

            <h2>6. Content</h2>
            <p>You keep ownership of content you upload. You grant Kakebe a licence to host, process and display it to provide the service. Churches must hold the rights to sermons, music and media they publish.</p>

            <h2>7. Availability</h2>
            <p>We aim for high availability but the service is provided “as is”. Scheduled maintenance will be announced where possible.</p>

            <h2>8. Liability</h2>
            <p>To the extent permitted by law, Kakebe is not liable for indirect or consequential losses, and our total liability is limited to the fees paid by the church in the 12 months before the claim.</p>

            <h2>9. Termination</h2>
            <p>You may delete your account at any time from your profile. Churches may export their data before closing their space.</p>

            <h2>10. Governing law</h2>
            <p>These terms are governed by the laws of the Republic of Uganda. Disputes will first be resolved amicably, failing which by the courts of Uganda.</p>

            <p class="small text-ff-muted">Questions? {{ $c['email'] }} · {{ $c['phone'] }}</p>
        </div>
    </section>
</x-layouts.public>
