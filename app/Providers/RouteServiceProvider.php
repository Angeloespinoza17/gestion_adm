<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user('sanctum');

            if ($user) {
                return Limit::perMinute(300)->by('user:'.$user->getAuthIdentifier());
            }

            return Limit::perMinute(60)->by('ip:'.$request->ip());
        });

        $contactLimitResponse = fn (Request $request, array $headers) => redirect()
            ->route('public.contact')
            ->withErrors(['formulario' => 'Has realizado varios intentos. Espera unos minutos antes de volver a enviar.'])
            ->withHeaders($headers);

        RateLimiter::for('public-contact', function (Request $request) use ($contactLimitResponse): array {
            $ip = $request->ip() ?: 'unknown';
            $email = Str::lower(trim((string) $request->input('correo')));

            return [
                Limit::perMinute(4)
                    ->by('public-contact:minute:'.$ip)
                    ->response($contactLimitResponse),
                Limit::perHour(20)
                    ->by('public-contact:hour:'.$ip)
                    ->response($contactLimitResponse),
                Limit::perHour(3)
                    ->by('public-contact:email:'.hash('sha256', $email ?: 'missing'))
                    ->response($contactLimitResponse),
            ];
        });

        RateLimiter::for('public-web-analytics', function (Request $request): array {
            $session = (string) $request->input('session_id', 'missing');

            return [
                Limit::perMinute(120)
                    ->by('public-web-analytics:ip:'.hash('sha256', (string) $request->ip())),
                Limit::perMinute(30)
                    ->by('public-web-analytics:session:'.hash('sha256', $session)),
            ];
        });

        $messagingKey = fn (Request $request): string => $request->user('sanctum')
            ? 'user:'.$request->user('sanctum')->getAuthIdentifier()
            : 'ip:'.$request->ip();

        RateLimiter::for('messaging', fn (Request $request) => Limit::perMinute(240)->by($messagingKey($request)));
        RateLimiter::for('messaging-search', fn (Request $request) => Limit::perMinute(30)->by($messagingKey($request)));
        RateLimiter::for('messaging-conversation-create', fn (Request $request) => Limit::perMinute(20)->by($messagingKey($request)));
        RateLimiter::for('messaging-announcement', fn (Request $request) => Limit::perMinute(5)->by($messagingKey($request)));
        RateLimiter::for('messaging-send', fn (Request $request) => Limit::perMinute(60)->by($messagingKey($request)));
        RateLimiter::for('messaging-acknowledge', fn (Request $request) => Limit::perMinute(20)->by($messagingKey($request)));
        RateLimiter::for('messaging-reminder', fn (Request $request) => Limit::perMinute(10)->by($messagingKey($request)));
        RateLimiter::for('messaging-upload', fn (Request $request) => Limit::perMinute(30)->by($messagingKey($request)));
    }
}
