<?php

namespace App\Providers;

use App\Services\Attendance\AttendanceParserRegistry;
use App\Services\Attendance\LirmiAttendancePdfParser;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(AttendanceParserRegistry::class, fn ($app) => new AttendanceParserRegistry([
            $app->make(LirmiAttendancePdfParser::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        // cPanel/Apache a veces elimina el header `Authorization` antes de PHP.
        // Esto permite autenticar Sanctum leyendo el token desde headers alternativos.
        Sanctum::getAccessTokenFromRequestUsing(function ($request) {
            $raw = $request->headers->get('X-Api-Token')
                ?: $request->headers->get('X-Authorization')
                ?: $request->cookie('cnsc_token');

            if (!is_string($raw) || $raw === '') {
                return $request->bearerToken();
            }

            $raw = rawurldecode($raw);

            if (str_starts_with($raw, 'Bearer ')) {
                return substr($raw, 7);
            }

            return $raw;
        });
    }
}
