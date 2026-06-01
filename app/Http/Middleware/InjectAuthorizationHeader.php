<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectAuthorizationHeader
{
    /**
     * Normaliza Authorization cuando Apache/cPanel no lo pasa a PHP.
     *
     * - Algunos setups dejan el valor en $_SERVER['REDIRECT_HTTP_AUTHORIZATION'].
     * - Como alternativa, el frontend envía X-Authorization.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->headers->has('Authorization')) {
            $serverAuth = $request->server('HTTP_AUTHORIZATION')
                ?: $request->server('REDIRECT_HTTP_AUTHORIZATION')
                ?: $request->server('Authorization');

            $xAuth = $request->headers->get('X-Authorization');
            $cookieToken = $request->cookie('cnsc_token');

            $auth = $serverAuth ?: $xAuth;
            if (!$auth && is_string($cookieToken) && $cookieToken !== '') {
                $decoded = rawurldecode($cookieToken);
                $auth = str_starts_with($decoded, 'Bearer ')
                    ? $decoded
                    : 'Bearer '.$decoded;
            }

            if (is_string($auth) && $auth !== '') {
                $request->headers->set('Authorization', $auth);
            }
        }

        return $next($request);
    }
}
