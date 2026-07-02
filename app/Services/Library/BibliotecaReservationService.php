<?php

namespace App\Services\Library;

use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaPrestamo;
use App\Models\Library\BibliotecaReserva;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BibliotecaReservationService
{
    public function __construct(
        private readonly BibliotecaInventoryService $inventoryService,
        private readonly BibliotecaLoanService $loanService,
        private readonly BibliotecaAlertService $alertService,
    ) {
    }

    public function create(array $payload, User $actor): BibliotecaReserva
    {
        return DB::transaction(function () use ($payload, $actor) {
            $ejemplar = $this->resolveEjemplar($payload);
            $this->assertReservationAvailability($ejemplar);

            $reservation = BibliotecaReserva::query()->create([
                'reservation_code' => $payload['reservation_code'] ?? $this->generateReservationCode(),
                'resource_type' => $payload['resource_type'] ?? $ejemplar->obra->material_type,
                'biblioteca_obra_id' => $ejemplar->biblioteca_obra_id,
                'biblioteca_ejemplar_id' => $ejemplar->id,
                'requester_type' => $payload['requester_type'],
                'requested_by_user_id' => $payload['requested_by_user_id'] ?? null,
                'student_profile_id' => $payload['student_profile_id'] ?? null,
                'staff_id' => $payload['staff_id'] ?? null,
                'course_section_id' => $payload['course_section_id'] ?? null,
                'requested_at' => Carbon::parse($payload['requested_at'] ?? now()),
                'pickup_at' => !empty($payload['pickup_at']) ? Carbon::parse($payload['pickup_at']) : null,
                'expected_return_at' => !empty($payload['expected_return_at']) ? Carbon::parse($payload['expected_return_at']) : null,
                'purpose' => $payload['purpose'] ?? null,
                'status' => $payload['status'] ?? 'solicitada',
                'responsible_user_id' => $payload['responsible_user_id'] ?? $actor->id,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if (in_array($reservation->status, ['aprobada'], true)) {
                $this->inventoryService->moveEjemplar(
                    $ejemplar,
                    $actor,
                    'reserva',
                    ['availability_status' => 'reservado'],
                    'Reserva aprobada.',
                    ['reservation_id' => $reservation->id]
                );
            }

            $this->alertService->refreshOperationalAlerts($actor);

            return $reservation->fresh([
                'obra',
                'ejemplar',
                'student',
                'staff',
                'courseSection',
                'requestedBy:id,name',
                'responsible:id,name',
            ]);
        });
    }

    public function transition(BibliotecaReserva $reservation, string $status, User $actor, array $payload = []): BibliotecaReserva
    {
        return DB::transaction(function () use ($reservation, $status, $actor, $payload) {
            $reservation->loadMissing(['ejemplar.obra']);
            $ejemplar = $reservation->ejemplar;

            if (!$ejemplar) {
                throw ValidationException::withMessages([
                    'reservation' => 'La reserva no tiene ejemplar asociado.',
                ]);
            }

            if ($status === 'aprobada') {
                $this->inventoryService->moveEjemplar(
                    $ejemplar,
                    $actor,
                    'reserva',
                    ['availability_status' => 'reservado'],
                    $payload['notes'] ?? 'Reserva aprobada.',
                    ['reservation_id' => $reservation->id]
                );
            }

            if ($status === 'retirada') {
                $loan = $this->loanService->create([
                    'borrower_type' => $reservation->requester_type,
                    'user_id' => $reservation->requested_by_user_id,
                    'student_profile_id' => $reservation->student_profile_id,
                    'staff_id' => $reservation->staff_id,
                    'course_section_id' => $reservation->course_section_id,
                    'biblioteca_ejemplar_id' => $reservation->biblioteca_ejemplar_id,
                    'borrowed_at' => $payload['borrowed_at'] ?? now(),
                    'due_at' => $payload['due_at'] ?? ($reservation->expected_return_at ?: now()->addDays(7)),
                    'delivered_by_user_id' => $payload['delivered_by_user_id'] ?? $actor->id,
                    'notes' => $payload['notes'] ?? 'Préstamo generado desde reserva.',
                    'reservation_id' => $reservation->id,
                ], $actor);

                $reservation->biblioteca_prestamo_id = $loan->id;
                $reservation->delivered_by_user_id = $payload['delivered_by_user_id'] ?? $actor->id;
            }

            if ($status === 'devuelta' && $reservation->prestamo) {
                $this->loanService->registerReturn($reservation->prestamo, [
                    'returned_at' => $payload['returned_at'] ?? now(),
                    'received_by_user_id' => $payload['received_by_user_id'] ?? $actor->id,
                    'returned_condition' => $payload['returned_condition'] ?? 'bueno',
                    'notes' => $payload['notes'] ?? 'Devolución registrada desde reserva.',
                ], $actor);
                $reservation->received_by_user_id = $payload['received_by_user_id'] ?? $actor->id;
                $reservation->returned_at = Carbon::parse($payload['returned_at'] ?? now());
            }

            if (in_array($status, ['rechazada', 'cancelada', 'vencida'], true) && in_array($ejemplar->availability_status, ['reservado'], true)) {
                $this->inventoryService->moveEjemplar(
                    $ejemplar,
                    $actor,
                    'ajuste',
                    ['availability_status' => 'disponible'],
                    $payload['notes'] ?? 'Reserva liberada.',
                    ['reservation_id' => $reservation->id]
                );
            }

            $reservation->forceFill([
                'status' => $status,
                'approval_notes' => $payload['approval_notes'] ?? $reservation->approval_notes,
                'notes' => trim(($reservation->notes ? $reservation->notes . PHP_EOL : '') . ($payload['notes'] ?? '')),
                'updated_by' => $actor->id,
                'returned_at' => $reservation->returned_at,
            ])->save();

            $this->alertService->refreshOperationalAlerts($actor);

            return $reservation->fresh([
                'obra',
                'ejemplar',
                'prestamo',
                'student',
                'staff',
                'courseSection',
                'requestedBy:id,name',
                'responsible:id,name',
            ]);
        });
    }

    private function resolveEjemplar(array $payload): BibliotecaEjemplar
    {
        if (!empty($payload['biblioteca_ejemplar_id'])) {
            return BibliotecaEjemplar::query()->with('obra')->findOrFail($payload['biblioteca_ejemplar_id']);
        }

        if (empty($payload['biblioteca_obra_id'])) {
            throw ValidationException::withMessages([
                'biblioteca_obra_id' => 'Debe seleccionar un recurso o ejemplar.',
            ]);
        }

        return BibliotecaEjemplar::query()
            ->with('obra')
            ->where('biblioteca_obra_id', $payload['biblioteca_obra_id'])
            ->where('is_active', true)
            ->where('availability_status', 'disponible')
            ->orderBy('code')
            ->firstOrFail();
    }

    private function assertReservationAvailability(BibliotecaEjemplar $ejemplar): void
    {
        if (!$ejemplar->is_active || $ejemplar->availability_status !== 'disponible') {
            throw ValidationException::withMessages([
                'biblioteca_ejemplar_id' => 'El recurso seleccionado no está disponible para reserva.',
            ]);
        }
    }

    private function generateReservationCode(): string
    {
        return 'RES-' . now()->format('Ymd-His') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}
