<?php

namespace App\Services;

use App\Jobs\SendDevicePush;
use App\Models\DirectMessage;
use App\Models\User;
use App\Services\Messaging\ExpoPushSender;
use App\Services\Notifications\PreferenceResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Push to a member's phone for things that are not a church message: a connection request, a new chat
 * message. It respects the member's "People & messages" setting and quiet hours, and goes on the queue so the
 * request that caused it is never slowed down by Apple or Google.
 */
class DevicePushService
{
    public function __construct(protected PreferenceResolver $preferences, protected ExpoPushSender $sender) {}

    /** Whether this member has a phone registered, wants push for this category, and is not in quiet hours. */
    public function wants(User $user, string $category): bool
    {
        return $user->deviceTokens()->exists()
            && in_array('webpush', $this->preferences->channelsFor($user, null, $category), true)
            && ! $user->inQuietHours();
    }

    /**
     * Queue a push when the member wants it.
     *
     * @param  array<string, mixed>  $message  title, body, data, channelId
     */
    public function toUser(User $user, string $category, array $message): void
    {
        if ($this->wants($user, $category)) {
            SendDevicePush::dispatch($user->id, $message);
        }
    }

    /** A chat message: at most one push per conversation a minute, so a busy chat does not buzz all evening. */
    public function newMessage(DirectMessage $message): void
    {
        $recipient = $message->recipient;
        $sender = $message->sender;
        if (! $recipient || ! $sender || ! Cache::add("dm-push:{$message->sender_id}:{$message->recipient_id}", 1, 60)) {
            return;
        }

        $this->toUser($recipient, 'social', [
            'title' => $sender->name,
            'body' => Str::limit($message->body, 140),
            'data' => ['kind' => 'message', 'ref' => $sender->id],
            'channelId' => 'messages',
        ]);
    }

    /** What the phone should show as the app badge: unread notifications plus unread chat messages. */
    public function badgeFor(User $user): int
    {
        return $user->unreadNotifications()->count() + DirectMessage::where('recipient_id', $user->id)->whereNull('read_at')->count();
    }

    /**
     * Send now to every phone of the member (used by the queued job and the "test" button).
     *
     * @param  array<string, mixed>  $message
     */
    public function sendNow(User $user, array $message): int
    {
        $tokens = $user->deviceTokens()->get();

        return $tokens->isEmpty() ? 0 : $this->sender->send($tokens, ['badge' => $this->badgeFor($user), ...$message]);
    }
}
