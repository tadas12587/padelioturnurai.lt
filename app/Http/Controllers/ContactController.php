<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index()
    {
        return view('pages.contact');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $contact = Contact::create($validated);

        // Only send if MAIL_MAILER is not 'log' (production check)
        try {
            \Illuminate\Support\Facades\Mail::to(config('mail.from.address'))->send(new \App\Mail\ContactMail($contact));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Contact mail failed: ' . $e->getMessage());
        }

        return back()->with('success', true);
    }
}
