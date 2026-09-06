<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMessagingAvailable
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('messaging.enabled')) {
            return response()->json([
                'message' => 'La mensajería institucional no está habilitada.',
                'code' => 'MESSAGING_DISABLED',
            ], 503);
        }

        $user = $request->user();

        if (! $user?->active) {
            return response()->json([
                'message' => 'La cuenta no está habilitada para usar la mensajería institucional.',
                'code' => 'MESSAGING_USER_INACTIVE',
            ], 403);
        }

        if (! $user->canUseMessaging()) {
            return response()->json([
                'message' => 'La mensajería institucional está disponible exclusivamente para funcionarios.',
                'code' => 'MESSAGING_STAFF_ONLY',
            ], 403);
        }

        return $next($request);
    }
}
