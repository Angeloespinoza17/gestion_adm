<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;

class PublicContactMessageController extends Controller
{
    public function store(StorePublicContactMessageRequest $request): RedirectResponse
    {
        ContactMessage::query()->create([
            'full_name' => $request->input('nombre'),
            'email' => $request->input('correo'),
            'phone' => $request->input('telefono') ?: null,
            'subject' => $request->input('asunto'),
            'message' => $request->input('mensaje'),
            'status' => ContactMessage::STATUS_NEW,
            'source_page' => '/contacto',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('public.contact')
            ->with('contact_success', 'Tu mensaje fue enviado correctamente. Nos pondremos en contacto contigo.');
    }
}
