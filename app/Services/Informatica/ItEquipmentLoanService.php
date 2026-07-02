<?php

namespace App\Services\Informatica;

use App\Models\It\ItEquipment;
use App\Models\It\ItEquipmentLoan;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItEquipmentLoanService
{
    public function __construct(
        private readonly ItEquipmentService $equipmentService,
    ) {
    }

    public function create(array $payload, User $actor, ?UploadedFile $attachment = null): ItEquipmentLoan
    {
        return DB::transaction(function () use ($payload, $actor, $attachment) {
            $equipment = ItEquipment::query()->findOrFail($payload['it_equipment_id']);
            $this->refreshOverdueStatuses();
            $this->assertEquipmentAvailable($equipment);

            $requester = $this->resolveRequester($payload);

            $loan = ItEquipmentLoan::query()->create([
                'loan_code' => $payload['loan_code'] ?? $this->generateLoanCode(),
                'it_equipment_id' => $equipment->id,
                'requester_type' => $payload['requester_type'],
                'requester_user_id' => $requester['requester_user_id'],
                'requester_staff_id' => $requester['requester_staff_id'],
                'requester_student_profile_id' => $requester['requester_student_profile_id'],
                'requester_name_snapshot' => $requester['name'],
                'requester_rut_snapshot' => $requester['rut'],
                'requester_contact_snapshot' => $requester['contact'],
                'borrowed_at' => Carbon::parse($payload['borrowed_at']),
                'due_at' => Carbon::parse($payload['due_at']),
                'purpose' => $payload['purpose'] ?? null,
                'location_name' => $payload['location_name'] ?? null,
                'delivered_by_user_id' => $payload['delivered_by_user_id'] ?? $actor->id,
                'status' => 'activo',
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->equipmentService->changeStatus(
                $equipment,
                'prestado',
                $actor,
                'Equipo entregado en préstamo.',
                'prestamo',
                $loan->id,
                false
            );

            if ($attachment) {
                $this->equipmentService->storeAttachment($loan, $equipment, $attachment, $actor, 'acta', 'Adjunto del préstamo.');
            }

            return $this->freshLoan($loan);
        });
    }

    public function registerReturn(ItEquipmentLoan $loan, array $payload, User $actor, ?UploadedFile $attachment = null): ItEquipmentLoan
    {
        return DB::transaction(function () use ($loan, $payload, $actor, $attachment) {
            if (!in_array($loan->status, ['activo', 'atrasado'], true)) {
                throw ValidationException::withMessages([
                    'loan' => 'Solo se pueden devolver préstamos activos o atrasados.',
                ]);
            }

            $loan->forceFill([
                'returned_at' => Carbon::parse($payload['returned_at'] ?? now()),
                'received_by_user_id' => $payload['received_by_user_id'] ?? $actor->id,
                'return_condition' => $payload['return_condition'],
                'return_notes' => $payload['return_notes'] ?? null,
                'status' => 'devuelto',
                'updated_by' => $actor->id,
            ])->save();

            $equipmentStatus = $this->resolveReturnStatus(
                $payload['return_condition'],
                $payload['post_return_status'] ?? null
            );

            $this->equipmentService->changeStatus(
                $loan->equipment()->firstOrFail(),
                $equipmentStatus,
                $actor,
                'Registro de devolución del equipo.',
                'devolucion',
                $loan->id,
                false
            );

            if ($attachment) {
                $this->equipmentService->storeAttachment($loan, $loan->equipment()->firstOrFail(), $attachment, $actor, 'evidencia', 'Adjunto de devolución.');
            }

            return $this->freshLoan($loan);
        });
    }

    public function cancel(ItEquipmentLoan $loan, User $actor, ?string $notes = null): ItEquipmentLoan
    {
        return DB::transaction(function () use ($loan, $actor, $notes) {
            if ($loan->status === 'devuelto') {
                throw ValidationException::withMessages([
                    'loan' => 'No puedes cancelar un préstamo ya devuelto.',
                ]);
            }

            if ($loan->status === 'cancelado') {
                return $this->freshLoan($loan);
            }

            $loan->forceFill([
                'status' => 'cancelado',
                'notes' => $this->appendNote($loan->notes, $notes ?: 'Préstamo cancelado.'),
                'updated_by' => $actor->id,
            ])->save();

            $this->equipmentService->changeStatus(
                $loan->equipment()->firstOrFail(),
                'disponible',
                $actor,
                'Préstamo cancelado.',
                'cancelacion_prestamo',
                $loan->id,
                false
            );

            return $this->freshLoan($loan);
        });
    }

    public function refreshOverdueStatuses(): void
    {
        ItEquipmentLoan::query()
            ->where('status', 'activo')
            ->where('due_at', '<', now())
            ->chunkById(100, function ($loans) {
                foreach ($loans as $loan) {
                    $loan->forceFill(['status' => 'atrasado'])->save();
                }
            });
    }

    private function assertEquipmentAvailable(ItEquipment $equipment): void
    {
        if (!$equipment->active) {
            throw ValidationException::withMessages([
                'it_equipment_id' => 'El equipo seleccionado está inactivo.',
            ]);
        }

        if ($equipment->status !== 'disponible') {
            throw ValidationException::withMessages([
                'it_equipment_id' => 'El equipo seleccionado no está disponible para préstamo.',
            ]);
        }

        if ($equipment->loans()->active()->exists()) {
            throw ValidationException::withMessages([
                'it_equipment_id' => 'El equipo ya tiene un préstamo activo o atrasado.',
            ]);
        }
    }

    /**
     * @return array{
     *   requester_user_id:?int,
     *   requester_staff_id:?int,
     *   requester_student_profile_id:?int,
     *   name:string,
     *   rut:?string,
     *   contact:?string
     * }
     */
    private function resolveRequester(array $payload): array
    {
        return match ($payload['requester_type']) {
            'funcionario' => $this->resolveStaffRequester($payload),
            'estudiante' => $this->resolveStudentRequester($payload),
            'apoderado', 'externo', 'otro' => $this->resolveManualRequester($payload),
            default => throw ValidationException::withMessages([
                'requester_type' => 'Tipo de solicitante no soportado.',
            ]),
        };
    }

    private function resolveStaffRequester(array $payload): array
    {
        if (!empty($payload['requester_staff_id'])) {
            $staff = Staff::query()->findOrFail($payload['requester_staff_id']);

            return [
                'requester_user_id' => null,
                'requester_staff_id' => $staff->id,
                'requester_student_profile_id' => null,
                'name' => $staff->full_name,
                'rut' => $staff->rut,
                'contact' => $staff->institutional_email ?: $staff->phone,
            ];
        }

        if (!empty($payload['requester_user_id'])) {
            $user = User::query()->findOrFail($payload['requester_user_id']);

            return [
                'requester_user_id' => $user->id,
                'requester_staff_id' => $user->staff_id,
                'requester_student_profile_id' => null,
                'name' => $user->name,
                'rut' => $user->staff?->rut,
                'contact' => $user->email,
            ];
        }

        return $this->manualRequesterPayload($payload);
    }

    private function resolveStudentRequester(array $payload): array
    {
        if (!empty($payload['requester_student_profile_id'])) {
            $student = StudentProfile::query()->findOrFail($payload['requester_student_profile_id']);

            return [
                'requester_user_id' => $student->user?->id,
                'requester_staff_id' => null,
                'requester_student_profile_id' => $student->id,
                'name' => $student->registered_name_resolved,
                'rut' => $student->rut,
                'contact' => $student->guardian_phone ?: $student->phone ?: $student->guardian_email,
            ];
        }

        return $this->manualRequesterPayload($payload);
    }

    private function resolveManualRequester(array $payload): array
    {
        if (!empty($payload['requester_user_id'])) {
            $user = User::query()->findOrFail($payload['requester_user_id']);

            return [
                'requester_user_id' => $user->id,
                'requester_staff_id' => $user->staff_id,
                'requester_student_profile_id' => $user->student_id,
                'name' => $user->name,
                'rut' => $user->staff?->rut ?: $user->student?->rut,
                'contact' => $user->email,
            ];
        }

        return $this->manualRequesterPayload($payload);
    }

    private function manualRequesterPayload(array $payload): array
    {
        $name = trim((string) ($payload['requester_name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages([
                'requester_name' => 'Debes indicar el nombre del solicitante o vincularlo a un registro del sistema.',
            ]);
        }

        return [
            'requester_user_id' => null,
            'requester_staff_id' => null,
            'requester_student_profile_id' => null,
            'name' => $name,
            'rut' => trim((string) ($payload['requester_rut'] ?? '')) ?: null,
            'contact' => trim((string) ($payload['requester_contact'] ?? '')) ?: null,
        ];
    }

    private function resolveReturnStatus(string $condition, ?string $manualStatus = null): string
    {
        if ($manualStatus) {
            return $manualStatus;
        }

        return match ($condition) {
            'danado' => 'danado',
            'incompleto' => 'en_mantencion',
            default => 'disponible',
        };
    }

    private function appendNote(?string $existing, string $new): string
    {
        $existing = trim((string) $existing);
        $new = trim($new);

        return trim($existing === '' ? $new : $existing . PHP_EOL . $new);
    }

    private function freshLoan(ItEquipmentLoan $loan): ItEquipmentLoan
    {
        return $loan->fresh([
            'equipment:id,internal_code,equipment_type,brand,model,status',
            'requesterUser:id,name,email',
            'requesterStaff:id,full_name,rut,institutional_email,phone',
            'requesterStudent:id,first_name,last_name,registered_name,rut,guardian_phone,phone',
            'deliveredBy:id,name',
            'receivedBy:id,name',
            'attachments.uploadedBy:id,name',
        ]);
    }

    private function generateLoanCode(): string
    {
        return 'INF-PRE-' . now()->format('Ymd-His') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}
