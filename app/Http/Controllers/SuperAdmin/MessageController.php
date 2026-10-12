<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Notifications\Support\ContactReply;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/**
 * Contact form inbox (public /contact).
 */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        return view('super-admin.messages.index', [
            'status' => $status,
            'messages' => ContactMessage::query()
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('email', 'like', '%'.$request->query('q').'%')->orWhere('subject', 'like', '%'.$request->query('q').'%')))
                ->latest()->paginate(25)->withQueryString(),
            'counts' => ContactMessage::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(ContactMessage $message): View
    {
        if ($message->status === 'new') {
            $message->update(['status' => 'read']);
        }

        return view('super-admin.messages.show', ['message' => $message]);
    }

    public function reply(Request $request, ContactMessage $message): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        Notification::route('mail', [$message->email => $message->name])->notify(new ContactReply($message, $data['body'], $request->user()->name));
        $message->update(['status' => 'replied']);
        Audit::log('contact.replied', $message);

        return redirect()->route('super-admin.messages.index')->with('success', __('Reply sent to :email.', ['email' => $message->email]));
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return redirect()->route('super-admin.messages.index')->with('success', __('Message deleted.'));
    }
}
