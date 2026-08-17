<?php

namespace App\Http\Middleware;

use App\Models\LibroDigital\AbsenceCase;
use App\Models\LibroDigital\AmendmentRequest;
use App\Models\LibroDigital\Assessment;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\ClassSession;
use App\Models\LibroDigital\CoexistenceEntry;
use App\Models\LibroDigital\EarlyWithdrawal;
use App\Models\LibroDigital\EdeExport;
use App\Models\LibroDigital\PieSupportRecord;
use App\Models\LibroDigital\School;
use App\Services\LibroDigital\FeatureFlagService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class EnsureLibroDigitalEnabled
{
    public function __construct(private readonly FeatureFlagService $features) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $this->isRolloutOperation($request)) {
            return $next($request);
        }

        $schoolId = $this->resolveSchoolId($request);
        if (! $this->features->enabled('lcd_enabled', $schoolId)) {
            return new JsonResponse([
                'message' => 'El Libro Digital esta desplegado pero aun no ha sido activado para este establecimiento.',
                'code' => 'LCD_FEATURE_DISABLED',
                'details' => [[
                    'field' => 'lcd_enabled',
                    'reason' => 'Completa el preflight normativo y activa el despliegue progresivo.',
                ]],
                'correlation_id' => $request->attributes->get('lcd_correlation_id'),
            ], 503);
        }

        return $next($request);
    }

    private function isRolloutOperation(Request $request): bool
    {
        $path = $request->path();

        return str_ends_with($path, '/configuration')
            || str_ends_with($path, '/preflight')
            || str_ends_with($path, '/ede/import-standard')
            || str_ends_with($path, '/audit/verify')
            || str_contains($path, 'libro-digital/v1/curriculum/imports')
            || ($request->isMethod('POST') && str_ends_with($path, '/libro-digital/v1/subjects'))
            || ($request->isMethod('PATCH') && str_contains($path, 'libro-digital/v1/subjects/'));
    }

    private function resolveSchoolId(Request $request): ?int
    {
        $bookIdentifier = $request->route('book');
        if ($bookIdentifier !== null) {
            return $this->aggregateSchoolId(Book::class, $bookIdentifier);
        }

        $sessionIdentifier = $request->route('session');
        if ($sessionIdentifier !== null) {
            return $this->aggregateSchoolId(ClassSession::class, $sessionIdentifier);
        }

        foreach ([
            'assessment' => Assessment::class,
            'pie' => PieSupportRecord::class,
            'coexistence' => CoexistenceEntry::class,
            'absenceCase' => AbsenceCase::class,
            'absence_case' => AbsenceCase::class,
            'amendment' => AmendmentRequest::class,
            'withdrawal' => EarlyWithdrawal::class,
            'edeExport' => EdeExport::class,
        ] as $parameter => $model) {
            $identifier = $request->route($parameter);
            if ($identifier !== null) {
                return $this->aggregateSchoolId($model, $identifier);
            }
        }

        $bookIdentifier = $request->input('book_id');
        if ($bookIdentifier !== null) {
            return $this->aggregateSchoolId(Book::class, $bookIdentifier);
        }

        $explicit = $request->integer('school_id') ?: (int) $request->header('X-LCD-School-ID');
        if ($explicit) {
            return $explicit;
        }

        $user = $request->user();
        if (! $user) {
            return null;
        }

        $today = Carbon::today()->toDateString();
        $schools = School::query()->where('active', true)
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->whereHas('users', fn ($membership) => $membership
                ->where('users.id', $user->id)
                ->where('lcd_school_users.active', true)
                ->where(fn ($dates) => $dates->whereNull('lcd_school_users.valid_from')->orWhere('lcd_school_users.valid_from', '<=', $today))
                ->where(fn ($dates) => $dates->whereNull('lcd_school_users.valid_to')->orWhere('lcd_school_users.valid_to', '>=', $today))))
            ->limit(2)->pluck('id');

        return $schools->count() === 1 ? (int) $schools->first() : null;
    }

    /** @param class-string<Book|ClassSession|Assessment|PieSupportRecord|CoexistenceEntry|AbsenceCase|AmendmentRequest|EarlyWithdrawal|EdeExport> $model */
    private function aggregateSchoolId(string $model, mixed $identifier): ?int
    {
        if ($identifier instanceof $model) {
            return (int) $identifier->school_id;
        }

        return $model::query()->where(function ($query) use ($identifier): void {
            if (ctype_digit((string) $identifier)) {
                $query->whereKey((int) $identifier)->orWhere('public_id', (string) $identifier);
            } else {
                $query->where('public_id', (string) $identifier);
            }
        })->value('school_id');
    }
}
