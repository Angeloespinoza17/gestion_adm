<?php

namespace App\Http\Controllers\Porter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Porter\AnnulPorterStudentWithdrawalRequest;
use App\Http\Requests\Porter\ResolvePorterStudentWithdrawalRequest;
use App\Http\Requests\Porter\StorePorterStudentWithdrawalRequest;
use App\Models\PorterAuthorizationRequest;
use App\Models\PorterStudentWithdrawal;
use App\Models\StudentProfile;
use App\Services\Porter\PorterAccessService;
use App\Services\Porter\PorterAuditService;
use App\Services\Porter\PorterStudentContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PorterStudentWithdrawalController extends Controller
{
    public function __construct(
        private readonly PorterAccessService $accessService,
        private readonly PorterAuditService $auditService,
        private readonly PorterStudentContextService $studentContextService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $query = PorterStudentWithdrawal::query()
            ->with([
                'studentProfile:id,first_name,last_name,rut',
                'registeredBy:id,name',
                'authorizedBy:id,name',
            ]);

        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $reason = trim((string) $request->query('reason'));
        $courseSectionId = $request->query('course_section_id');
        $registeredBy = $request->query('registered_by');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('student_full_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('student_rut_snapshot', 'like', "%{$search}%")
                        ->orWhere('person_name', 'like', "%{$search}%")
                        ->orWhere('person_rut', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($reason !== '', fn (Builder $query) => $query->where('reason', $reason))
            ->when($courseSectionId, fn (Builder $query) => $query->where('course_section_id', $courseSectionId))
            ->when($registeredBy, fn (Builder $query) => $query->where('registered_by', $registeredBy))
            ->when($dateFrom, fn (Builder $query) => $query->whereDate('withdrawn_at', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query) => $query->whereDate('withdrawn_at', '<=', $dateTo));

        return response()->json(
            $query
                ->latest('withdrawn_at')
                ->latest('id')
                ->paginate((int) $request->query('per_page', 15))
        );
    }

    public function show(Request $request, PorterStudentWithdrawal $porterStudentWithdrawal): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'data' => $porterStudentWithdrawal->load([
                'studentProfile:id,first_name,last_name,rut',
                'academicYear:id,name,year',
                'courseSection:id,display_name',
                'registeredBy:id,name,email',
                'authorizedBy:id,name,email',
                'cancelledBy:id,name,email',
                'authorizationRequests.requestedBy:id,name,email',
                'authorizationRequests.resolvedBy:id,name,email',
                'logs.performedBy:id,name,email',
            ]),
        ]);
    }

    public function store(StorePorterStudentWithdrawalRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->accessService->canViewModule($user), 403);

        $payload = $request->validated();
        $student = StudentProfile::query()->findOrFail($payload['student_profile_id']);
        $activeYear = $this->studentContextService->activeAcademicYear();
        $currentEnrollment = $this->studentContextService->currentEnrollment($student, $activeYear);

        if (!$activeYear || !$currentEnrollment) {
            return response()->json([
                'message' => 'La estudiante no tiene matrícula vigente en el año académico activo.',
            ], 422);
        }

        if (in_array($currentEnrollment->enrollment_status, ['retirada', 'trasladada', 'egresada'], true)) {
            return response()->json([
                'message' => 'No se puede registrar retiro para una matrícula no activa.',
            ], 422);
        }

        $duplicateExists = PorterStudentWithdrawal::query()
            ->where('student_profile_id', $student->id)
            ->whereDate('withdrawn_at', now()->toDateString())
            ->where('withdrawn_at', '>=', now()->copy()->subHours(4))
            ->whereIn('status', ['registrado', 'autorizado', 'observado'])
            ->exists();

        if ($duplicateExists && empty($payload['force_duplicate_confirmation'])) {
            return response()->json([
                'message' => 'Ya existe un retiro reciente para esta estudiante. Debes confirmar el duplicado para continuar.',
                'errors' => [
                    'force_duplicate_confirmation' => ['Ya existe un retiro reciente para esta estudiante.'],
                ],
            ], 422);
        }

        $authorizationCheck = $this->studentContextService->resolveAuthorizedPerson($student, [
            'name' => $payload['person_name'],
            'rut' => $payload['person_rut'] ?? null,
        ]);

        $requiresSpecialAuthorization = $student->pickup_restriction || !$authorizationCheck['authorized'];
        $initialStatus = $requiresSpecialAuthorization ? 'observado' : 'registrado';

        $withdrawal = DB::transaction(function () use ($request, $payload, $student, $activeYear, $currentEnrollment, $authorizationCheck, $requiresSpecialAuthorization, $initialStatus, $user) {
            $attachment = $request->file('attachment');

            $withdrawal = PorterStudentWithdrawal::create([
                'student_profile_id' => $student->id,
                'academic_year_id' => $activeYear->id,
                'course_section_id' => $currentEnrollment->course_section_id,
                'registered_by' => $user?->id,
                'authorized_by' => null,
                'status' => $initialStatus,
                'withdrawn_at' => now(),
                'student_full_name_snapshot' => $student->full_name,
                'student_rut_snapshot' => $student->rut,
                'academic_year_name_snapshot' => $activeYear->name,
                'course_name_snapshot' => $currentEnrollment->snapshot_course_display_name,
                'person_name' => $payload['person_name'],
                'person_rut' => $payload['person_rut'] ?? null,
                'person_relationship' => $payload['person_relationship'],
                'person_phone' => $payload['person_phone'] ?? null,
                'reason' => $payload['reason'],
                'observations' => $payload['observations'] ?? null,
                'person_authorized' => $authorizationCheck['authorized'],
                'authorization_source' => $authorizationCheck['source'],
                'requires_special_authorization' => $requiresSpecialAuthorization,
                'authorization_notes' => $requiresSpecialAuthorization ? ($student->pickup_restriction_notes ?: ($payload['override_reason'] ?? null)) : null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'authorization_match' => $authorizationCheck['matched_person'] ?? null,
                    'pickup_restriction' => (bool) $student->pickup_restriction,
                ],
            ]);

            if ($attachment instanceof UploadedFile) {
                $this->storeAttachment($withdrawal, $attachment);
            }

            if ($requiresSpecialAuthorization) {
                $authorizationRequest = $withdrawal->authorizationRequests()->create([
                    'requested_by' => $user?->id,
                    'status' => 'pendiente',
                    'required_permission_slug' => 'autorizar_retiros_porteria',
                    'reason' => $student->pickup_restriction
                        ? ($student->pickup_restriction_notes ?: 'La estudiante presenta restricción de retiro.')
                        : 'La persona que retira no está en la lista autorizada.',
                    'requested_at' => now(),
                    'payload' => [
                        'person_name' => $payload['person_name'],
                        'person_rut' => $payload['person_rut'] ?? null,
                    ],
                ]);

                if ($this->accessService->canAuthorizeSpecialWithdrawal($user) && !empty($payload['approve_override']) && !empty($payload['override_reason'])) {
                    $withdrawal->update([
                        'status' => 'autorizado',
                        'authorized_by' => $user?->id,
                        'authorization_notes' => $payload['override_reason'],
                    ]);

                    $authorizationRequest->update([
                        'status' => 'aprobada',
                        'resolved_by' => $user?->id,
                        'resolved_at' => now(),
                        'resolution_notes' => $payload['override_reason'],
                    ]);
                }
            }

            return $withdrawal->fresh();
        });

        $this->auditService->log(
            $withdrawal,
            'registro_retiro',
            null,
            $withdrawal->status,
            $withdrawal->status === 'observado'
                ? 'Retiro registrado con observación y solicitud de autorización.'
                : 'Retiro registrado en portería.',
            $user,
            $request,
            [
                'student_profile_id' => $student->id,
                'person_name' => $withdrawal->person_name,
                'requires_special_authorization' => $withdrawal->requires_special_authorization,
            ],
        );

        return response()->json([
            'message' => $withdrawal->status === 'observado'
                ? 'Retiro registrado con alerta. Quedó pendiente de validación especial.'
                : 'Retiro registrado correctamente.',
            'data' => $withdrawal->load([
                'studentProfile:id,first_name,last_name,rut',
                'registeredBy:id,name',
                'authorizationRequests.requestedBy:id,name',
            ]),
        ], 201);
    }

    public function resolve(ResolvePorterStudentWithdrawalRequest $request, PorterStudentWithdrawal $porterStudentWithdrawal): JsonResponse
    {
        abort_unless($this->accessService->canAuthorizeSpecialWithdrawal($request->user()), 403);

        $payload = $request->validated();
        $fromStatus = $porterStudentWithdrawal->status;

        DB::transaction(function () use ($porterStudentWithdrawal, $payload, $request) {
            $porterStudentWithdrawal->update([
                'status' => $payload['decision'],
                'authorized_by' => $payload['decision'] === 'autorizado' ? $request->user()?->id : $porterStudentWithdrawal->authorized_by,
                'authorization_notes' => $payload['reason'],
            ]);

            $authorizationRequest = $porterStudentWithdrawal->authorizationRequests()->latest('id')->first();

            if ($authorizationRequest) {
                $authorizationRequest->update([
                    'status' => match ($payload['decision']) {
                        'autorizado' => 'aprobada',
                        'rechazado' => 'rechazada',
                        default => 'observada',
                    },
                    'resolved_by' => $request->user()?->id,
                    'resolved_at' => now(),
                    'resolution_notes' => $payload['reason'],
                ]);
            }
        });

        $this->auditService->log(
            $porterStudentWithdrawal->fresh(),
            'resolucion_autorizacion_retiro',
            $fromStatus,
            $payload['decision'],
            $payload['reason'],
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'La resolución del retiro fue registrada correctamente.',
            'data' => $porterStudentWithdrawal->fresh()->load([
                'studentProfile:id,first_name,last_name,rut',
                'registeredBy:id,name',
                'authorizedBy:id,name',
                'authorizationRequests.requestedBy:id,name',
                'authorizationRequests.resolvedBy:id,name',
            ]),
        ]);
    }

    public function annul(AnnulPorterStudentWithdrawalRequest $request, PorterStudentWithdrawal $porterStudentWithdrawal): JsonResponse
    {
        abort_unless($this->accessService->canAuthorizeSpecialWithdrawal($request->user()), 403);

        $payload = $request->validated();
        $fromStatus = $porterStudentWithdrawal->status;

        DB::transaction(function () use ($porterStudentWithdrawal, $payload, $request) {
            $porterStudentWithdrawal->update([
                'status' => 'anulado',
                'cancelled_by' => $request->user()?->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $payload['reason'],
            ]);

            $authorizationRequest = $porterStudentWithdrawal->authorizationRequests()
                ->where('status', 'pendiente')
                ->latest('id')
                ->first();

            if ($authorizationRequest instanceof PorterAuthorizationRequest) {
                $authorizationRequest->update([
                    'status' => 'anulada',
                    'resolved_by' => $request->user()?->id,
                    'resolved_at' => now(),
                    'resolution_notes' => $payload['reason'],
                ]);
            }
        });

        $this->auditService->log(
            $porterStudentWithdrawal->fresh(),
            'anulacion_retiro',
            $fromStatus,
            'anulado',
            $payload['reason'],
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Retiro anulado correctamente.',
            'data' => $porterStudentWithdrawal->fresh(),
        ]);
    }

    private function storeAttachment(PorterStudentWithdrawal $withdrawal, UploadedFile $attachment): void
    {
        $path = $attachment->storePubliclyAs(
            sprintf('porter/withdrawals/%d', $withdrawal->id),
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $attachment->getClientOriginalName(),
            ['disk' => 'public']
        );

        $withdrawal->update([
            'attachment_path' => $path,
            'attachment_original_name' => $attachment->getClientOriginalName(),
            'attachment_mime_type' => $attachment->getClientMimeType(),
        ]);
    }
}
