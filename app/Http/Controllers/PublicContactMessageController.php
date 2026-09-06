<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PublicContactMessageController extends Controller
{
    public function store(StorePublicContactMessageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $fingerprint = hash('sha256', implode('|', [
            Str::lower($data['correo']),
            Str::squish($data['mensaje']),
        ]));

        RateLimiter::attempt(
            'public-contact:duplicate:'.$fingerprint,
            1,
            fn () => ContactMessage::query()->create([
                'full_name' => $data['nombre'],
                'email' => $data['correo'],
                'phone' => $data['telefono'] ?: null,
                'subject' => $data['asunto'],
                'message' => $data['mensaje'],
                'status' => ContactMessage::STATUS_NEW,
                'source_page' => '/contacto',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]),
            600,
        );

        return redirect()
            ->route('public.contact')
            ->with('contact_success', 'Tu mensaje fue enviado correctamente. Nos pondremos en contacto contigo.');
    }
}
