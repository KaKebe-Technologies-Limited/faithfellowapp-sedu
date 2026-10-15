<x-layouts.public :title="__('Delete your account')" :description="__('How to delete your FaithFellows account and what happens to your data.')">
    @php($c = config('faithfellows.company'))
    <section class="ff-page-hero">
        <div class="container" style="max-width: 860px">
            <span class="ff-eyebrow">{{ __('Your data') }}</span>
            <h1 class="mt-3">{{ __('Delete your FaithFellows account') }}</h1>
            <p class="text-ff-muted mb-0">{{ __('You can do it yourself, in a minute.') }}</p>
        </div>
    </section>
    <section class="ff-section pt-4">
        <div class="container ff-prose" style="max-width: 860px">
            <h2>{{ __('In the app') }}</h2>
            <ol>
                <li>{{ __('Open FaithFellows and go to the Me tab.') }}</li>
                <li>{{ __('Scroll to “My things” and tap Delete my account.') }}</li>
                <li>{{ __('Type your email address to confirm, and tap Delete my account.') }}</li>
            </ol>

            <h2>{{ __('On the website') }}</h2>
            <ol>
                <li>{{ __('Sign in at :site.', ['site' => 'faithfellows.com']) }}</li>
                <li>{{ __('Open your Profile, scroll to Delete account and choose Delete my account.') }}</li>
                <li>{{ __('Enter your password to confirm.') }}</li>
            </ol>

            <h2>{{ __('Cannot sign in?') }}</h2>
            <p>{!! __('Email us from the address you signed up with, with the subject “Delete my account”: :email. We will delete your account within 30 days and tell you when it is done.', ['email' => '<a href="mailto:'.e($c['email']).'?subject=Delete%20my%20account">'.e($c['email']).'</a>']) !!}</p>

            <h2>{{ __('What is removed') }}</h2>
            <ul>
                <li>{{ __('Your name, email address, phone number, photo, birthday, place and other profile details.') }}</li>
                <li>{{ __('Your notes and highlights, the churches and podcasts you follow, your saved payment details and your notification settings.') }}</li>
                <li>{{ __('Your phones, so you no longer get notifications, and your activity figures.') }}</li>
                <li>{{ __('Gifts that repeat and promises that are still open are cancelled.') }}</li>
            </ul>

            <h2>{{ __('What is kept, without your name') }}</h2>
            <ul>
                <li>{{ __('Records of gifts and receipts. Churches must keep their books, and the law requires it. They no longer show who you are.') }}</li>
                <li>{{ __('What you shared with other people (messages, prayer requests, comments). It stays but shows “Deleted user”.') }}</li>
            </ul>

            <h2>{{ __('Church owners') }}</h2>
            <p>{{ __('If you own a church on FaithFellows, contact us first so the church can be handed over or closed properly.') }}</p>

            <p class="small text-ff-muted">{{ $c['name'] }} · {{ $c['phone'] }} · {{ $c['email'] }}</p>
        </div>
    </section>
</x-layouts.public>
