<?php

namespace App\Http;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\EnsureConvivenciaAccess;
use App\Http\Middleware\EnsureConvivenciaInstalled;
use App\Http\Middleware\EnsureLibroDigitalEnabled;
use App\Http\Middleware\EnsureMessagingAvailable;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRiskMatrixInstalled;
use App\Http\Middleware\EnsureRiskPreventionInstalled;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\InjectAuthorizationHeader;
use App\Http\Middleware\LibroDigitalCorrelationId;
use App\Http\Middleware\LibroDigitalIdempotency;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RecordAuthenticatedUsage;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustHosts;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        TrustHosts::class,
        TrustProxies::class,
        HandleCors::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        RecordAuthenticatedUsage::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            InjectAuthorizationHeader::class,
            'throttle:api',
            SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'convivencia.access' => EnsureConvivenciaAccess::class,
        'convivencia.installed' => EnsureConvivenciaInstalled::class,
        'lcd.correlation' => LibroDigitalCorrelationId::class,
        'lcd.enabled' => EnsureLibroDigitalEnabled::class,
        'lcd.idempotency' => LibroDigitalIdempotency::class,
        'messaging.available' => EnsureMessagingAvailable::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'permission' => EnsurePermission::class,
        'risk_prevention.installed' => EnsureRiskPreventionInstalled::class,
        'risk_matrix.installed' => EnsureRiskMatrixInstalled::class,
        'superadmin' => EnsureSuperAdmin::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
    ];
}
