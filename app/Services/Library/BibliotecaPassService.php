<?php

namespace App\Services\Library;

use App\Models\AcademicYear;
use App\Models\Library\BibliotecaPase;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BibliotecaPassService
{
    public function __construct(
        private readonly BibliotecaCodeService $codeService,
    ) {}

    public function create(array $payload, User $actor): BibliotecaPase
    {
        return DB::transaction(function () use ($payload, $actor) {
            $student = StudentProfile::query()
                ->with(['enrollments.courseSection', 'enrollments.academicYear'])
                ->findOrFail($payload['student_profile_id']);
            $enrollment = $student->preferredEnrollment(
                AcademicYear::query()->where('is_active', true)->first()
            );
            $professor = ! empty($payload['professor_staff_id'])
                ? Staff::query()->findOrFail($payload['professor_staff_id'])
                : null;

            $this->assertNoOverlap($student->id, $payload['valid_from'], $payload['valid_until']);

            $pass = BibliotecaPase::query()->create([
                'pass_code' => $this->codeService->next('PAS'),
                'student_profile_id' => $student->id,
                'course_section_id' => $enrollment?->course_section_id,
                'professor_staff_id' => $professor?->id,
                'student_name_snapshot' => $student->registered_name_resolved,
                'student_rut_snapshot' => $student->rut,
                'professor_name_snapshot' => $professor?->full_name,
                'issued_at' => now(),
                'valid_from' => $payload['valid_from'],
                'valid_until' => $payload['valid_until'],
                'status' => 'emitido',
                'regulation_version' => $payload['regulation_version'] ?? 'vigente',
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

            return $this->load($pass);
        });
    }

    public function update(BibliotecaPase $pass, array $payload, User $actor): BibliotecaPase
    {
        if (in_array($pass->status, ['utilizado', 'anulado'], true)) {
            throw ValidationException::withMessages([
                'status' => 'No se puede editar un pase utilizado o anulado.',
            ]);
        }

        return DB::transaction(function () use ($pass, $payload, $actor) {
            $studentId = (int) ($payload['student_profile_id'] ?? $pass->student_profile_id);
            $validFrom = $payload['valid_from'] ?? $pass->valid_from;
            $validUntil = $payload['valid_until'] ?? $pass->valid_until;
            $this->assertNoOverlap($studentId, $validFrom, $validUntil, $pass->id);

            if (! empty($payload['professor_staff_id'])) {
                $professor = Staff::query()->findOrFail($payload['professor_staff_id']);
                $payload['professor_name_snapshot'] = $professor->full_name;
            }

            if (! empty($payload['signature_name']) || ! empty($payload['signature_data'])) {
                $payload['signed_at'] = now();
            }

            $pass->fill($payload);
            $pass->updated_by = $actor->id;
            $pass->save();

            return $this->load($pass);
        });
    }

    public function transition(BibliotecaPase $pass, string $status, User $actor): BibliotecaPase
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
        BibliotecaPase::query()
            ->where('status', 'emitido')
            ->where('valid_until', '<', now())
            ->update(['status' => 'vencido', 'updated_at' => now()]);
    }

    private function assertNoOverlap(int $studentId, mixed $validFrom, mixed $validUntil, ?int $ignoreId = null): void
    {
        $from = Carbon::parse($validFrom);
        $until = Carbon::parse($validUntil);

        $exists = BibliotecaPase::query()
            ->where('student_profile_id', $studentId)
            ->where('status', 'emitido')
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('valid_from', '<', $until)
            ->where('valid_until', '>', $from)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'valid_from' => 'La estudiante ya tiene un pase vigente en ese horario.',
            ]);
        }
    }

    private function load(BibliotecaPase $pass): BibliotecaPase
    {
        return $pass->fresh([
            'student:id,first_name,last_name,registered_name,rut',
            'courseSection:id,display_name',
            'professor:id,full_name,rut',
            'issuedBy:id,name',
            'usedBy:id,name',
        ]);
    }
}
