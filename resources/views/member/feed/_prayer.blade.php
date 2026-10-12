{{-- Feed card for a prayer request. Expects $prayer (with church, user, and the viewer's own "prayed" interactions). --}}
<div class="ff-feed-item">
    @include('member.prayer._card', ['prayed' => $prayer->relationLoaded('interactions') && $prayer->interactions->isNotEmpty(), 'limit' => 400])
</div>
