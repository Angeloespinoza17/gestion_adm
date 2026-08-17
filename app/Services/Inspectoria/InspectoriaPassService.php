<?php

namespace App\Services\Inspectoria;

use App\Models\AcademicYear;
use App\Models\Inspectoria\InspectoriaPass;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Passes\StudentPassPriorityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InspectoriaPassService
{
    public function __construct(
        private readonly InspectoriaCodeService $codeService,
        private readonly StudentPassPriorityService $priorityService,
        private readonly InspectoriaAccessService $access,
    ) {}

    public function create(array $payload, User $actor): InspectoriaPass
    {
        return DB::transaction(function () use ($payload, $actor) {
            $student = StudentProfile::query()->with(['enrollments.courseSection', 'enrollments.academicYear'])->findOrFail($payload['student_profile_id']);
            abort_unless($this->access->canAccessStudent($actor, $student->id), 403, 'La alumna no pertenece a un curso asignado a esta inspectora.');
            $activeYear = AcademicYear::query()->where('is_active', true)->first();
            $enrollment = $student->preferredEnrollment($activeYear);
            if ($this->access->isCourseScoped($actor)) {
                $courseIds = $this->access->assignedCourseIds($actor);
                $enrollment = $student->enrollments->first(fn ($item) => $courseIds->contains((int) $item->course_section_id)
                    && (! $activeYear || (int) $item->academic_year_id === (int) $activeYear->id));
            }
            $this->assertNoOverlap($student->id, $payload['valid_from'], $payload['valid_until']);

            $pass = InspectoriaPass::query()->create([
                'pass_code' => $this->codeService->next('PAS'),
                'student_profile_id' => $student->id,
                'course_section_id' => $enrollment?->course_section_id,
                'inspector_staff_id' => $actor->staff_id,
                'student_name_snapshot' => $student->registered_name_resolved,
                'student_rut_snapshot' => $student->rut,
                'inspector_name_snapshot' => $actor->staff?->full_name ?? $actor->name,
                'destination' => $payload['destination'],
                'destination_detail' => $payload['destination_detail'] ?? null,
                'issued_at' => now(),
                'valid_from' => $payload['valid_from'],
                'valid_until' => $payload['valid_until'],
                'status' => 'emitido',
                'priority' => InspectoriaPass::PRIORITY,
                'regulation_version' => $payload['regulation_version'] ?? 'Reglamento Inspectoría vigente',
                'reason' => $payload['reason'],
                'signature_data' => $payload['signature_data'] ?? null,
                'signature_name' => $payload['signature_name'] ?? null,
                'signature_rut' => $payload['signature_rut'] ?? null,
                'signed_at' => ! empty($payload['signature_name']) || ! empty($payload['signature_data']) ? now() : null,
                'notes' => $payload['notes'] ?? null,
                'issued_by_user_id' => $actor->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $superseded = $this->priorityService->supersedeLibraryPasses($pass, $actor);
            if ($superseded > 0) {
                $pass->forceFill([
                    'notes' => trim(implode("\n", array_filter([
                        $pass->notes,
                        "Este pase prevaleció sobre {$superseded} pase(s) de Biblioteca superpuesto(s).",
                    ]))),
                ])->save();
            }

            return $this->load($pass);
        });
    }

    public function update(InspectoriaPass $pass, array $payload, User $actor): InspectoriaPass
    {
        if ($pass->status !== 'emitido') {
            throw ValidationException::withMessages(['status' => 'Solo se puede editar un pase emitido.']);
        }

        return DB::transaction(function () use ($pass, $payload, $actor) {
            $this->assertNoOverlap((int) $pass->student_profile_id, $payload['valid_from'], $payload['valid_until'], $pass->id);
            if (! empty($payload['signature_name']) || ! empty($payload['signature_data'])) {
                $payload['signed_at'] = now();
            }
            unset($payload['student_profile_id']);
            $pass->fill($payload);
            $pass->priority = InspectoriaPass::PRIORITY;
            $pass->updated_by = $actor->id;
            $pass->save();
            $this->priorityService->supersedeLibraryPasses($pass, $actor);

            return $this->load($pass);
        });
    }

    public function transition(InspectoriaPass $pass, string $status, User $actor): InspectoriaPass
    {
        if (! in_array($status, ['utilizado', 'anulado'], true)) {
            throw ValidationException::withMessages(['status' => 'Transición de pase no válida.']);
        }
        if ($pass->status !== 'emitido') {
            throw ValidationException::withMessages(['status' => 'El pase ya fue cerrado.']);
        }
        if ($status === 'utilizado' && now()->greaterThan($pass->valid_until)) {
            throw ValidationException::withMessages(['status' => 'El pase está vencido y no puede utilizarse.']);
        }

        $pass->forceFill([
            'status' => $status,
            'used_at' => $status === 'utilizado' ? now() : null,
            'used_by_user_id' => $status === 'utilizado' ? $actor->id : null,
            'updated_by' => $actor->id,
        ])->save();

        return $this->load($pass);
    }

    public function refreshExpired(): void
    {
        InspectoriaPass::query()->where('status', 'emitido')->where('valid_until', '<', now())
            ->update(['status' => 'vencido', 'updated_at' => now()]);
    }

    private function assertNoOverlap(int $studentId, mixed $from, mixed $until, ?int $ignoreId = null): void
    {
        $exists = InspectoriaPass::query()
            ->where('student_profile_id', $studentId)
            ->where('status', 'emitido')
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('valid_from', '<', Carbon::parse($until))
            ->where('valid_until', '>', Carbon::parse($from))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['valid_from' => 'La alumna ya tiene un pase de Inspectoría vigente en ese horario.']);
        }
    }

    private function load(InspectoriaPass $pass): InspectoriaPass
    {
        return $pass->fresh([
            'student:id,first_name,last_name,registered_name,rut',
            'courseSection:id,display_name',
            'inspector:id,full_name,rut',
            'issuedBy:id,name',
            'usedBy:id,name',
        ]);
    }
}
