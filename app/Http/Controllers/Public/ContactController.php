<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ContactRequest;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('public.contact');
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $message = ContactMessage::create([
            ...$request->safe()->only(['name', 'email', 'phone', 'subject', 'message']),
            'ip_address' => $request->ip(),
        ]);

        Mail::to(config('faithfellows.company.email'))->queue(new ContactMessageReceived($message));

        return redirect()->route('contact')
            ->with('success', __('Thank you, :name! Your message has been received — our team will get back to you shortly.', ['name' => $message->name]));
    }
}
