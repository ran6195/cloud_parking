<?php

namespace App\Http\Controllers;

use App\Mail\NewLeadReceived;
use App\Models\Lead;
use App\Settings\GeneralSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LeadController extends Controller
{
    public function store(Request $request, GeneralSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:5000'],
            'source_url' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'name' => 'nome',
            'email' => 'email',
            'phone' => 'telefono',
            'message' => 'messaggio',
        ]);

        if (filled($request->input('website'))) {
            return back()->with('lead_sent', true);
        }

        $lead = Lead::create($validated);

        if ($settings->email) {
            Mail::to($settings->email)->send(new NewLeadReceived($lead));
        }

        return back()->with('lead_sent', true);
    }
}
