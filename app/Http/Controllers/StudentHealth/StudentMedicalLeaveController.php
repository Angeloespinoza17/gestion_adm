<?php

namespace App\Http\Controllers\StudentHealth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentHealth\StoreStudentMedicalLeaveRequest;
use App\Http\Requests\StudentHealth\UpdateStudentMedicalLeaveRequest;
use App\Models\SocialWork\MedicalCertificate;
use App\Models\StudentProfile;
use App\Services\StudentHealth\StudentMedicalLeaveAccessService;
use App\Services\StudentHealth\StudentMedicalLeaveRegistrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentMedicalLeaveController extends Controller
{
    public function __construct(
        private readonly StudentMedicalLeaveAccessService $access,
        private readonly StudentMedicalLeaveRegistrationService $registration,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->canView($request->user()), 403);

        $query = $this->access->scopeCertificates(
            MedicalCertificate::query()
                ->with([
                    'student:id,first_name,last_name,registered_name,rut,general_status',
                    'student.enrollments' => fn ($enrollments) => $enrollments
                        ->with(['academicYear:id,name,year,is_active', 'courseSection:id,display_name'])
                        ->orderByDesc('academic_year_id'),
                    'registeredBy:id,name',
                    'updatedBy:id,name',
                ]),
            $request->user(),
        );

        $this->applyFilters($query, $request);

        $perPage = min(max((int) $request->integer('per_page', 20), 10), 100);
        $certificates = $query
            ->orderByDesc('is_permanent')
            ->orderByDesc('covers_from')
            ->orderByDesc('id')
            ->paginate($perPage);

        $currentAcademicYear = $this->access->currentAcademicYear();
        $certificates->setCollection($certificates->getCollection()->map(
            fn (MedicalCertificate $certificate) => $this->certificatePayload($certificate, $currentAcademicYear),
        ));

        $summaryQuery = $this->access->scopeCertificates(MedicalCertificate::query(), $request->user());
        $today = today()->toDateString();
        $soon = today()->addDays(7)->toDateString();
        $summary = $summaryQuery
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('SUM(CASE WHEN is_permanent = 1 THEN 1 ELSE 0 END) as permanent_records')
            ->selectRaw('COUNT(DISTINCT CASE WHEN is_permanent = 1 THEN student_profile_id END) as chronic_students')
            ->selectRaw(
                'SUM(CASE WHEN is_permanent = 0 AND covers_to BETWEEN ? AND ? THEN 1 ELSE 0 END) as ending_soon',
                [$today, $soon],
            )
            ->first();

        return response()->json([
            ...$certificates->toArray(),
            'summary' => [
                'total_records' => (int) ($summary?->total_records ?? 0),
                'permanent_records' => (int) ($summary?->permanent_records ?? 0),
                'chronic_students' => (int) ($summary?->chronic_students ?? 0),
                'ending_soon' => (int) ($summary?->ending_soon ?? 0),
            ],
            'capabilities' => [
                'can_create' => $this->access->canCreate($request->user()),
                'can_edit' => $this->access->canEdit($request->user()),
            ],
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        abort_unless($this->access->canCreate($request->user()), 403);

        $search = trim((string) $request->query('search'));
        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $students = $this->access->scopeStudents(
            StudentProfile::query()
                ->with(['enrollments' => fn ($enrollments) => $enrollments
                    ->with(['academicYear:id,name,year,is_active', 'courseSection:id,display_name'])
                    ->orderByDesc('academic_year_id')]),
            $request->user(),
        )
            ->where(function (Builder $query) use ($search): void {
                $query
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('registered_name', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%");
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(20)
            ->get();

        $currentAcademicYear = $this->access->currentAcademicYear();

        return response()->json([
            'data' => $students->map(function (StudentProfile $student) use ($currentAcademicYear): array {
                $enrollment = $student->preferredEnrollment($currentAcademicYear);

                return [
                    'id' => $student->id,
                    'name' => $student->registered_name_resolved,
                    'rut' => $student->rut,
                    'course' => $enrollment?->snapshot_course_display_name
                        ?? $enrollment?->courseSection?->display_name,
                ];
            })->values(),
        ]);
    }

    public function store(StoreStudentMedicalLeaveRequest $request): JsonResponse
    {
        abort_unless($this->access->canCreate($request->user()), 403);

        $validated = $request->validated();
        abort_unless(
            $this->access->canAccessStudent($request->user(), (int) $validated['student_profile_id']),
            403,
            'La alumna no pertenece al alcance autorizado para este usuario.',
        );

        $isPermanent = (bool) $validated['is_permanent'];
        $certificate = $this->registration->register(
            $validated,
            $request->user()->id,
            $request->file('attachment'),
        );

        $certificate->load([
            'student:id,first_name,last_name,registered_name,rut,general_status',
            'student.enrollments' => fn ($enrollments) => $enrollments
                ->with(['academicYear:id,name,year,is_active', 'courseSection:id,display_name'])
                ->orderByDesc('academic_year_id'),
            'registeredBy:id,name',
            'updatedBy:id,name',
        ]);

        return response()->json([
            'message' => $isPermanent
                ? 'Condición crónica permanente registrada y compartida con Enfermería e Inspectoría.'
                : 'Licencia médica registrada y compartida con Enfermería e Inspectoría.',
            'data' => $this->certificatePayload($certificate, $this->access->currentAcademicYear()),
        ], 201);
    }

    public function update(UpdateStudentMedicalLeaveRequest $request, int $certificate): JsonResponse
    {
        abort_unless($this->access->canEdit($request->user()), 403);

        $medicalCertificate = $this->access->scopeCertificates(
            MedicalCertificate::query()->whereKey($certificate),
            $request->user(),
        )->firstOrFail();

        $medicalCertificate = $this->registration->update(
            $medicalCertificate,
            $request->validated(),
            $request->user()->id,
            $request->file('attachment'),
        );

        $medicalCertificate->load([
            'student:id,first_name,last_name,registered_name,rut,general_status',
            'student.enrollments' => fn ($enrollments) => $enrollments
                ->with(['academicYear:id,name,year,is_active', 'courseSection:id,display_name'])
                ->orderByDesc('academic_year_id'),
            'registeredBy:id,name',
            'updatedBy:id,name',
        ]);

        return response()->json([
            'message' => 'Licencia médica actualizada. Los cambios ya están disponibles en el registro compartido.',
            'data' => $this->certificatePayload($medicalCertificate, $this->access->currentAcademicYear()),
        ]);
    }

    public function downloadAttachment(Request $request, int $certificate): StreamedResponse
    {
        abort_unless($this->access->canView($request->user()), 403);

        $medicalCertificate = $this->access->scopeCertificates(
            MedicalCertificate::query()->whereKey($certificate),
            $request->user(),
        )->firstOrFail();
        $privatePath = $medicalCertificate->getRawOriginal('private_path');

        abort_unless(filled($privatePath) && Storage::disk('local')->exists($privatePath), 404);

        return Storage::disk('local')->download(
            $privatePath,
            $medicalCertificate->original_name ?: 'respaldo-medico-'.$medicalCertificate->id,
            [
                'Content-Type' => $medicalCertificate->mime_type ?: 'application/octet-stream',
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('search'));
        $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('administrative_summary', 'like', "%{$search}%")
                        ->orWhereHas('student', function (Builder $students) use ($search): void {
                            $students
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('registered_name', 'like', "%{$search}%")
                                ->orWhere('rut', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->boolean('permanent'), fn (Builder $builder) => $builder->where('is_permanent', true));

        $today = today()->toDateString();
        match ((string) $request->query('status')) {
            'active' => $query
                ->where(fn (Builder $builder) => $builder->whereNull('covers_from')->orWhereDate('covers_from', '<=', $today))
                ->where(fn (Builder $builder) => $builder->where('is_permanent', true)->orWhereNull('covers_to')->orWhereDate('covers_to', '>=', $today)),
            'scheduled' => $query->whereDate('covers_from', '>', $today),
            'ended' => $query->where('is_permanent', false)->whereNotNull('covers_to')->whereDate('covers_to', '<', $today),
            default => null,
        };
    }

    private function certificatePayload(MedicalCertificate $certificate, mixed $currentAcademicYear): array
    {
        $enrollment = $certificate->student?->preferredEnrollment($currentAcademicYear);

        return [
            'id' => $certificate->id,
            'student' => [
                'id' => $certificate->student?->id,
                'name' => $certificate->student?->registered_name_resolved,
                'rut' => $certificate->student?->rut,
                'course' => $enrollment?->snapshot_course_display_name
                    ?? $enrollment?->courseSection?->display_name,
            ],
            'starts_on' => $certificate->covers_from?->format('Y-m-d'),
            'ends_on' => $certificate->covers_to?->format('Y-m-d'),
            'reason' => $certificate->administrative_summary,
            'is_permanent' => (bool) $certificate->is_permanent,
            'status' => $this->displayStatus($certificate),
            'source_module' => $certificate->source_module,
            'registered_by' => $certificate->registeredBy?->name,
            'registered_at' => $certificate->created_at?->toIso8601String(),
            'updated_by' => $certificate->updatedBy?->name,
            'updated_at' => $certificate->updated_at?->toIso8601String(),
            'attachment' => filled($certificate->getRawOriginal('private_path')) ? [
                'name' => $certificate->original_name,
                'mime_type' => $certificate->mime_type,
                'size_bytes' => (int) $certificate->size_bytes,
                'download_url' => "/api/student-medical-leaves/{$certificate->id}/attachment",
            ] : null,
        ];
    }

    private function displayStatus(MedicalCertificate $certificate): string
    {
        if ($certificate->is_permanent) {
            return 'permanent';
        }

        if ($certificate->covers_from?->isAfter(today())) {
            return 'scheduled';
        }

        if ($certificate->covers_to?->isBefore(today())) {
            return 'ended';
        }

        return 'active';
    }
}
