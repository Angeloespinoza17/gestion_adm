<?php

namespace App\Services\Library;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaPrestamo;
use App\Models\Library\BibliotecaReserva;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BibliotecaLoanService
{
    public function __construct(
        private readonly BibliotecaInventoryService $inventoryService,
        private readonly BibliotecaAlertService $alertService,
        private readonly BibliotecaCodeService $codeService,
    ) {}

    public function create(array $payload, User $actor): BibliotecaPrestamo
    {
        return DB::transaction(function () use ($payload, $actor) {
            $ejemplar = BibliotecaEjemplar::query()->with('obra')->findOrFail($payload['biblioteca_ejemplar_id']);
            $this->assertEjemplarAvailableForLoan($ejemplar, $payload);

            $borrower = $this->resolveBorrower($payload);
            $this->assertBorrowerCanLoan($borrower, $payload);
            $pickup = $this->resolvePickupPerson($borrower, $payload);

            $loan = BibliotecaPrestamo::query()->create([
                'loan_code' => $payload['loan_code'] ?? $this->generateLoanCode(),
                'batch_code' => $payload['batch_code'] ?? null,
                'borrower_type' => $payload['borrower_type'],
                'user_id' => $payload['user_id'] ?? null,
                'student_profile_id' => $payload['student_profile_id'] ?? null,
                'staff_id' => $payload['staff_id'] ?? null,
                'course_section_id' => $payload['course_section_id'] ?? null,
                'academic_year_id' => $payload['academic_year_id'] ?? $this->resolveAcademicYearId($payload),
                'biblioteca_obra_id' => $ejemplar->biblioteca_obra_id,
                'biblioteca_ejemplar_id' => $ejemplar->id,
                'borrower_name_snapshot' => $borrower['name'],
                'borrower_rut_snapshot' => $borrower['rut'],
                'borrower_estate' => $payload['borrower_type'] === 'teacher' ? 'teacher' : $borrower['estate'],
                'course_name_snapshot' => $borrower['course'],
                'borrowed_at' => Carbon::parse($payload['borrowed_at']),
                'due_at' => Carbon::parse($payload['due_at'])->format('Y-m-d'),
                'status' => 'activo',
                'notes' => $payload['notes'] ?? null,
                'audit_trail' => [[
                    'event' => 'prestamo_creado',
                    'at' => now()->toDateTimeString(),
                    'by' => $actor->id,
                ]],
                'delivered_by_user_id' => $payload['delivered_by_user_id'] ?? $actor->id,
                'pickup_person_type' => $pickup['type'],
                'pickup_person_name' => $pickup['name'],
                'pickup_person_rut' => $pickup['rut'],
                'pickup_person_email' => $pickup['email'],
                'pickup_person_relationship' => $pickup['relationship'],
                'signature_data' => $payload['signature_data'] ?? null,
                'signature_name' => $payload['signature_name'] ?? null,
                'signature_rut' => $payload['signature_rut'] ?? null,
                'signed_at' => ! empty($payload['signature_name']) || ! empty($payload['signature_data']) ? now() : null,
                'delivery_notes' => $payload['delivery_notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->inventoryService->moveEjemplar(
                $ejemplar,
                $actor,
                'prestamo',
                [
                    'availability_status' => 'prestado',
                ],
                'Salida a préstamo.',
                ['loan_id' => $loan->id]
            );

            if (! empty($payload['reservation_id'])) {
                BibliotecaReserva::query()->whereKey($payload['reservation_id'])->update([
                    'biblioteca_prestamo_id' => $loan->id,
                    'status' => 'retirada',
                    'updated_by' => $actor->id,
                ]);
            }

            $this->refreshLoanStatus($loan);
            $this->alertService->refreshOperationalAlerts($actor);

            return $loan->fresh([
                'obra',
                'ejemplar',
                'student',
                'staff',
                'courseSection',
                'deliveredBy:id,name',
                'receivedBy:id,name',
            ]);
        });
    }

    /**
     * @return Collection<int, BibliotecaPrestamo>
     */
    public function createBatch(array $payload, User $actor)
    {
        $ids = collect($payload['biblioteca_ejemplar_ids'] ?? [$payload['biblioteca_ejemplar_id'] ?? null])
            ->filter()
            ->unique()
            ->values();

        return DB::transaction(function () use ($ids, $payload, $actor) {
            $batchCode = $ids->count() > 1 ? $this->codeService->next('LOT') : ($payload['batch_code'] ?? null);

            return $ids->map(function ($id) use ($payload, $actor, $batchCode) {
                $itemPayload = $payload;
                unset($itemPayload['biblioteca_ejemplar_ids']);
                $itemPayload['biblioteca_ejemplar_id'] = $id;
                $itemPayload['batch_code'] = $batchCode;

                return $this->create($itemPayload, $actor);
            });
        });
    }

    public function update(BibliotecaPrestamo $loan, array $payload, User $actor): BibliotecaPrestamo
    {
        if (! in_array($loan->status, ['activo', 'renovado', 'vencido'], true)) {
            throw ValidationException::withMessages([
                'loan' => 'Solo se pueden editar préstamos abiertos.',
            ]);
        }

        $loan->forceFill([
            'due_at' => Carbon::parse($payload['due_at'])->format('Y-m-d'),
            'notes' => $payload['notes'] ?? $loan->notes,
            'pickup_person_type' => $payload['pickup_person_type'] ?? $loan->pickup_person_type,
            'pickup_person_name' => $payload['pickup_person_name'] ?? $loan->pickup_person_name,
            'pickup_person_rut' => $payload['pickup_person_rut'] ?? $loan->pickup_person_rut,
            'pickup_person_email' => $payload['pickup_person_email'] ?? $loan->pickup_person_email,
            'pickup_person_relationship' => $payload['pickup_person_relationship'] ?? $loan->pickup_person_relationship,
            'signature_data' => $payload['signature_data'] ?? $loan->signature_data,
            'signature_name' => $payload['signature_name'] ?? $loan->signature_name,
            'signature_rut' => $payload['signature_rut'] ?? $loan->signature_rut,
            'signed_at' => ! empty($payload['signature_name']) || ! empty($payload['signature_data'])
                ? now()
                : $loan->signed_at,
            'delivery_notes' => $payload['delivery_notes'] ?? $loan->delivery_notes,
            'audit_trail' => array_merge($loan->audit_trail ?? [], [[
                'event' => 'prestamo_editado',
                'at' => now()->toDateTimeString(),
                'by' => $actor->id,
            ]]),
            'updated_by' => $actor->id,
        ])->save();

        $this->refreshLoanStatus($loan);
        $this->alertService->refreshOperationalAlerts($actor);

        return $loan->fresh([
            'obra',
            'ejemplar',
            'student',
            'staff',
            'courseSection',
            'deliveredBy:id,name',
            'receivedBy:id,name',
        ]);
    }

    public function renew(BibliotecaPrestamo $loan, array $payload, User $actor): BibliotecaPrestamo
    {
        return DB::transaction(function () use ($loan, $payload, $actor) {
            if (! in_array($loan->status, ['activo', 'renovado', 'vencido'], true)) {
                throw ValidationException::withMessages([
                    'loan' => 'Solo se pueden renovar préstamos activos.',
                ]);
            }

            $loan->forceFill([
                'due_at' => Carbon::parse($payload['due_at'])->format('Y-m-d'),
                'status' => 'renovado',
                'renewed_count' => (int) $loan->renewed_count + 1,
                'notes' => trim(($loan->notes ? $loan->notes.PHP_EOL : '').($payload['notes'] ?? '')),
                'audit_trail' => array_merge($loan->audit_trail ?? [], [[
                    'event' => 'prestamo_renovado',
                    'at' => now()->toDateTimeString(),
                    'by' => $actor->id,
                    'due_at' => Carbon::parse($payload['due_at'])->format('Y-m-d'),
                ]]),
                'updated_by' => $actor->id,
            ])->save();

            $this->refreshLoanStatus($loan);
            $this->alertService->refreshOperationalAlerts($actor);

            return $loan->fresh(['obra', 'ejemplar', 'student', 'staff', 'courseSection']);
        });
    }

    public function registerReturn(BibliotecaPrestamo $loan, array $payload, User $actor): BibliotecaPrestamo
    {
        return DB::transaction(function () use ($loan, $payload, $actor) {
            if (in_array($loan->status, ['devuelto', 'cancelado'], true)) {
                throw ValidationException::withMessages([
                    'loan' => 'El préstamo ya fue cerrado.',
                ]);
            }

            $condition = $payload['returned_condition'] ?? 'bueno';
            $status = $condition === 'danado' ? 'danado' : ($condition === 'perdido' ? 'perdido' : 'devuelto');

            $loan->forceFill([
                'returned_at' => Carbon::parse($payload['returned_at'] ?? now()),
                'received_by_user_id' => $payload['received_by_user_id'] ?? $actor->id,
                'returned_condition' => $condition,
                'status' => $status,
                'notes' => trim(($loan->notes ? $loan->notes.PHP_EOL : '').($payload['notes'] ?? '')),
                'audit_trail' => array_merge($loan->audit_trail ?? [], [[
                    'event' => 'prestamo_devuelto',
                    'at' => now()->toDateTimeString(),
                    'by' => $actor->id,
                    'condition' => $condition,
                ]]),
                'updated_by' => $actor->id,
            ])->save();

            $ejemplarChanges = [
                'availability_status' => $status === 'perdido' ? 'perdido' : ($status === 'danado' ? 'danado' : 'disponible'),
                'physical_state' => $status === 'perdido' ? 'perdido' : ($status === 'danado' ? 'danado' : $loan->ejemplar->physical_state),
                'lost_at' => $status === 'perdido' ? now() : null,
                'damaged_at' => $status === 'danado' ? now() : null,
            ];

            $this->inventoryService->moveEjemplar(
                $loan->ejemplar()->with('obra')->firstOrFail(),
                $actor,
                $status === 'perdido' ? 'perdida' : ($status === 'danado' ? 'danio' : 'devolucion'),
                $ejemplarChanges,
                $payload['notes'] ?? 'Devolución registrada.',
                ['loan_id' => $loan->id]
            );

            $this->alertService->markResolved(BibliotecaPrestamo::class, $loan->id);
            $this->alertService->refreshOperationalAlerts($actor);

            return $loan->fresh(['obra', 'ejemplar', 'student', 'staff', 'courseSection', 'deliveredBy:id,name', 'receivedBy:id,name']);
        });
    }

    public function cancel(BibliotecaPrestamo $loan, User $actor, ?string $notes = null): BibliotecaPrestamo
    {
        return DB::transaction(function () use ($loan, $actor, $notes) {
            if (in_array($loan->status, ['devuelto', 'cancelado'], true)) {
                return $loan;
            }

            $loan->forceFill([
                'status' => 'cancelado',
                'notes' => trim(($loan->notes ? $loan->notes.PHP_EOL : '').($notes ?? 'Préstamo cancelado.')),
                'audit_trail' => array_merge($loan->audit_trail ?? [], [[
                    'event' => 'prestamo_cancelado',
                    'at' => now()->toDateTimeString(),
                    'by' => $actor->id,
                ]]),
                'updated_by' => $actor->id,
            ])->save();

            $this->inventoryService->moveEjemplar(
                $loan->ejemplar()->with('obra')->firstOrFail(),
                $actor,
                'ajuste',
                ['availability_status' => 'disponible'],
                $notes ?? 'Préstamo cancelado.',
                ['loan_id' => $loan->id]
            );

            $this->alertService->markResolved(BibliotecaPrestamo::class, $loan->id);
            $this->alertService->refreshOperationalAlerts($actor);

            return $loan->fresh(['obra', 'ejemplar']);
        });
    }

    public function refreshLoanStatus(BibliotecaPrestamo $loan): BibliotecaPrestamo
    {
        if (in_array($loan->status, ['devuelto', 'cancelado', 'perdido', 'danado'], true)) {
            return $loan;
        }

        $overdueDays = now()->startOfDay()->diffInDays(Carbon::parse($loan->due_at)->startOfDay(), false) * -1;
        $overdueDays = max($overdueDays, 0);

        $loan->forceFill([
            'overdue_days' => $overdueDays,
            'status' => $overdueDays > 0 ? 'vencido' : ($loan->renewed_count > 0 ? 'renovado' : 'activo'),
        ])->save();

        return $loan;
    }

    public function refreshStatuses(): void
    {
        BibliotecaPrestamo::query()
            ->whereIn('status', ['activo', 'renovado', 'vencido'])
            ->chunkById(100, function ($loans) {
                foreach ($loans as $loan) {
                    $this->refreshLoanStatus($loan);
                }
            });
    }

    private function assertEjemplarAvailableForLoan(BibliotecaEjemplar $ejemplar, array $payload): void
    {
        if (! $ejemplar->is_active || ! in_array($ejemplar->availability_status, ['disponible', 'reservado'], true)) {
            throw ValidationException::withMessages([
                'biblioteca_ejemplar_id' => 'El ejemplar seleccionado no está disponible para préstamo.',
            ]);
        }

        $conflictingReservation = BibliotecaReserva::query()
            ->where('biblioteca_ejemplar_id', $ejemplar->id)
            ->whereIn('status', ['solicitada', 'aprobada'])
            ->when(! empty($payload['reservation_id']), fn ($query) => $query->where('id', '!=', $payload['reservation_id']))
            ->exists();

        if ($conflictingReservation) {
            throw ValidationException::withMessages([
                'biblioteca_ejemplar_id' => 'El ejemplar tiene una reserva pendiente.',
            ]);
        }
    }

    private function assertBorrowerCanLoan(array $borrower, array $payload): void
    {
        $query = BibliotecaPrestamo::query()->whereIn('status', ['activo', 'renovado', 'vencido']);

        if (! empty($payload['student_profile_id'])) {
            $query->where('student_profile_id', $payload['student_profile_id']);
        } elseif (! empty($payload['staff_id'])) {
            $query->where('staff_id', $payload['staff_id']);
        } elseif (! empty($payload['course_section_id']) && $payload['borrower_type'] === 'course') {
            $query->where('course_section_id', $payload['course_section_id']);
        } elseif (! empty($payload['user_id'])) {
            $query->where('user_id', $payload['user_id']);
        }

        if ($query->where('status', 'vencido')->exists()) {
            throw ValidationException::withMessages([
                'borrower' => sprintf('%s mantiene préstamos vencidos.', $borrower['name']),
            ]);
        }
    }

    /**
     * @return array{name:string,rut:?string,course:?string,estate:string,level:?string,guardian_name:?string,guardian_rut:?string,guardian_email:?string,guardian_relationship:?string}
     */
    private function resolveBorrower(array $payload): array
    {
        return match ($payload['borrower_type']) {
            'student' => $this->studentBorrower($payload['student_profile_id'] ?? null),
            'staff', 'teacher' => $this->staffBorrower($payload['staff_id'] ?? null),
            'course' => $this->courseBorrower($payload['course_section_id'] ?? null),
            'guardian' => $this->guardianBorrower(
                $payload['student_profile_id'] ?? null,
                $payload['user_id'] ?? null
            ),
            default => throw ValidationException::withMessages([
                'borrower_type' => 'Tipo de usuario no soportado.',
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function studentBorrower(?int $studentId): array
    {
        $student = StudentProfile::query()->with(['enrollments.courseSection.educationLevel', 'enrollments.academicYear'])->findOrFail($studentId);
        $enrollment = $student->preferredEnrollment(
            AcademicYear::query()->where('is_active', true)->first()
        );

        return [
            'name' => $student->registered_name_resolved,
            'rut' => $student->rut,
            'course' => $enrollment?->snapshot_course_display_name,
            'estate' => 'student',
            'level' => $enrollment?->snapshot_level_name ?? $enrollment?->courseSection?->educationLevel?->name,
            'guardian_name' => $student->guardian_name,
            'guardian_rut' => $student->guardian_rut,
            'guardian_email' => $student->guardian_email,
            'guardian_relationship' => $student->guardian_relationship,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffBorrower(?int $staffId): array
    {
        $staff = Staff::query()->findOrFail($staffId);

        return [
            'name' => $staff->full_name,
            'rut' => $staff->rut,
            'course' => null,
            'estate' => 'staff',
            'level' => null,
            'guardian_name' => null,
            'guardian_rut' => null,
            'guardian_email' => null,
            'guardian_relationship' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function courseBorrower(?int $courseId): array
    {
        $course = CourseSection::query()->findOrFail($courseId);

        return [
            'name' => $course->display_name,
            'rut' => null,
            'course' => $course->display_name,
            'estate' => 'course',
            'level' => null,
            'guardian_name' => null,
            'guardian_rut' => null,
            'guardian_email' => null,
            'guardian_relationship' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userBorrower(?int $userId): array
    {
        $user = User::query()->findOrFail($userId);

        return [
            'name' => $user->name,
            'rut' => null,
            'course' => null,
            'estate' => 'guardian',
            'level' => null,
            'guardian_name' => $user->name,
            'guardian_rut' => null,
            'guardian_email' => $user->email,
            'guardian_relationship' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function guardianBorrower(?int $studentId, ?int $userId): array
    {
        if (! $studentId) {
            if (! $userId) {
                throw ValidationException::withMessages([
                    'student_profile_id' => 'Debes seleccionar un apoderado registrado.',
                ]);
            }

            return $this->userBorrower($userId);
        }

        $student = StudentProfile::query()
            ->with(['enrollments.courseSection.educationLevel', 'enrollments.academicYear'])
            ->findOrFail($studentId);
        $enrollment = $student->preferredEnrollment(
            AcademicYear::query()->where('is_active', true)->first()
        );

        if (blank($student->guardian_name)) {
            throw ValidationException::withMessages([
                'student_profile_id' => 'La estudiante seleccionada no tiene un apoderado registrado.',
            ]);
        }

        return [
            'name' => $student->guardian_name,
            'rut' => $student->guardian_rut,
            'course' => $enrollment?->snapshot_course_display_name,
            'estate' => 'guardian',
            'level' => null,
            'guardian_name' => $student->guardian_name,
            'guardian_rut' => $student->guardian_rut,
            'guardian_email' => $student->guardian_email,
            'guardian_relationship' => $student->guardian_relationship,
        ];
    }

    /**
     * @param  array<string, mixed>  $borrower
     * @return array{type:string,name:?string,rut:?string,email:?string,relationship:?string}
     */
    private function resolvePickupPerson(array $borrower, array $payload): array
    {
        $normalizedLevel = mb_strtoupper(trim((string) ($borrower['level'] ?? '')));
        $isEarlyChildhood = str_contains($normalizedLevel, 'NT1') || str_contains($normalizedLevel, 'NT2');

        if ($isEarlyChildhood) {
            $name = $payload['pickup_person_name'] ?? $borrower['guardian_name'] ?? null;
            $rut = $payload['pickup_person_rut'] ?? $borrower['guardian_rut'] ?? null;

            if (! $name || ! $rut) {
                throw ValidationException::withMessages([
                    'pickup_person_name' => 'Para NT1 y NT2 debes registrar nombre y RUT del apoderado que retira.',
                ]);
            }

            return [
                'type' => 'guardian',
                'name' => $name,
                'rut' => $rut,
                'email' => $payload['pickup_person_email'] ?? $borrower['guardian_email'] ?? null,
                'relationship' => $payload['pickup_person_relationship'] ?? $borrower['guardian_relationship'] ?? null,
            ];
        }

        return [
            'type' => $payload['pickup_person_type'] ?? ($borrower['estate'] === 'student' ? 'student' : $borrower['estate']),
            'name' => $payload['pickup_person_name'] ?? $borrower['name'],
            'rut' => $payload['pickup_person_rut'] ?? $borrower['rut'],
            'email' => $payload['pickup_person_email'] ?? null,
            'relationship' => $payload['pickup_person_relationship'] ?? null,
        ];
    }

    private function resolveAcademicYearId(array $payload): ?int
    {
        if (! empty($payload['academic_year_id'])) {
            return (int) $payload['academic_year_id'];
        }

        if (! empty($payload['student_profile_id'])) {
            $activeYear = AcademicYear::query()->where('is_active', true)->first();
            $enrollment = StudentEnrollment::query()
                ->where('student_profile_id', $payload['student_profile_id'])
                ->when($activeYear, fn ($query) => $query->where('academic_year_id', $activeYear->id))
                ->latest('id')
                ->first();

            return $enrollment?->academic_year_id;
        }

        if (! empty($payload['course_section_id'])) {
            return CourseSection::query()->whereKey($payload['course_section_id'])->value('academic_year_id');
        }

        return AcademicYear::query()->where('is_active', true)->value('id');
    }

    private function generateLoanCode(): string
    {
        return $this->codeService->next('PRE');
    }
}
