<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Church;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * In-app notification center with read/unread tracking.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->when($request->query('filter') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->paginate(20)
            ->withQueryString();

        $churches = Church::whereIn('id', $notifications->pluck('church_id')->filter()->unique())->with('media')->get()->keyBy('id');

        return view('member.notifications', compact('notifications', 'churches'));
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    /**
     * Mark one as read and follow its link.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? route('notifications.index');

        // Only follow links to our own app.
        return str_starts_with($url, url('/')) || str_starts_with($url, '/')
            ? redirect()->to($url)
            : redirect()->route('notifications.index');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', __('All notifications marked as read.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereKey($id)->delete();

        return back();
    }
}
