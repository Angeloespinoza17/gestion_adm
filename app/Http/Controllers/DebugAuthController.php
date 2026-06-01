<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebugAuthController extends Controller
{
    public function auth(Request $request): JsonResponse
    {
        $auth = (string) ($request->headers->get('authorization') ?? '');
        $xAuth = (string) ($request->headers->get('x-authorization') ?? '');
        $cookie = (string) ($request->cookie('cnsc_token') ?? '');
        $bearer = (string) ($request->bearerToken() ?? '');

        return response()->json([
            'has_authorization' => $auth !== '',
            'authorization_prefix' => $auth !== '' ? substr($auth, 0, 20) : null,
            'has_x_authorization' => $xAuth !== '',
            'x_authorization_prefix' => $xAuth !== '' ? substr($xAuth, 0, 20) : null,
            'has_cnsc_token_cookie' => $cookie !== '',
            'cnsc_token_cookie_prefix' => $cookie !== '' ? substr($cookie, 0, 10) : null,
            'bearer_token_length' => strlen($bearer),
            'request_user_id' => $request->user()?->id,
        ]);
    }
}

