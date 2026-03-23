<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function index()
    {
        return view('pages.contact');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $contact = Contact::create($validated);

        // Send notification to admin
        $adminEmail = env('ADMIN_EMAIL', config('mail.from.address'));
        try {
            Mail::to($adminEmail)->send(new \App\Mail\ContactMail($contact));
        } catch (\Exception $e) {
            Log::error('Contact mail failed: ' . $e->getMessage());
        }

        // Detect locale from URL prefix and redirect to locale-aware contact page
        $locale = $request->segment(1);
        if ($locale === 'en') {
            return redirect()->route('contact.locale', ['locale' => 'en'])->with('success', true);
        }

        return redirect()->route('contact')->with('success', true);
    }
}
