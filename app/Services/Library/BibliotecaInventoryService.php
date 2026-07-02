<?php

namespace App\Services\Library;

use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaInventarioMovimiento;
use App\Models\Library\BibliotecaObra;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BibliotecaInventoryService
{
    public function refreshWorkAvailability(BibliotecaObra $obra): BibliotecaObra
    {
        $obra->loadMissing('ejemplares');

        $totalCopies = $obra->ejemplares->count();
        $availableCopies = $obra->ejemplares
            ->where('is_active', true)
            ->where('availability_status', 'disponible')
            ->count();

        $activeLoanStatuses = ['activo', 'renovado', 'vencido'];
        $activeReservations = $obra->reservas()->whereIn('status', ['solicitada', 'aprobada'])->count();
        $loanCount = $obra->prestamos()->whereNotIn('status', ['cancelado'])->count();

        $generalStatus = 'disponible';

        if ($totalCopies === 0 || $obra->ejemplares->every(fn (BibliotecaEjemplar $ejemplar) => $ejemplar->availability_status === 'dado_de_baja')) {
            $generalStatus = 'dado_de_baja';
        } elseif ($obra->ejemplares->contains(fn (BibliotecaEjemplar $ejemplar) => $ejemplar->availability_status === 'perdido')) {
            $generalStatus = 'perdido';
        } elseif ($obra->ejemplares->contains(fn (BibliotecaEjemplar $ejemplar) => $ejemplar->availability_status === 'danado')) {
            $generalStatus = 'danado';
        } elseif ($obra->ejemplares->contains(fn (BibliotecaEjemplar $ejemplar) => $ejemplar->availability_status === 'en_reparacion')) {
            $generalStatus = 'en_reparacion';
        } elseif ($availableCopies === 0 && $activeReservations > 0) {
            $generalStatus = 'reservado';
        } elseif ($availableCopies === 0 && $obra->prestamos()->whereIn('status', $activeLoanStatuses)->exists()) {
            $generalStatus = 'prestado';
        } elseif ($activeReservations > 0) {
            $generalStatus = 'reservado';
        }

        $obra->forceFill([
            'total_copies' => $totalCopies,
            'available_copies' => $availableCopies,
            'loan_count' => $loanCount,
            'general_status' => $generalStatus,
        ])->save();

        return $obra->fresh();
    }

    public function moveEjemplar(
        BibliotecaEjemplar $ejemplar,
        User $user,
        string $movementType,
        array $changes = [],
        ?string $notes = null,
        array $metadata = [],
    ): BibliotecaInventarioMovimiento {
        return DB::transaction(function () use ($ejemplar, $user, $movementType, $changes, $notes, $metadata) {
            $previousLocation = $ejemplar->physical_location;
            $previousState = $ejemplar->physical_state;

            $ejemplar->fill([
                'physical_location' => $changes['physical_location'] ?? $ejemplar->physical_location,
                'physical_state' => $changes['physical_state'] ?? $ejemplar->physical_state,
                'availability_status' => $changes['availability_status'] ?? $ejemplar->availability_status,
                'observations' => $changes['observations'] ?? $ejemplar->observations,
                'last_inventory_checked_at' => $changes['last_inventory_checked_at'] ?? $ejemplar->last_inventory_checked_at,
                'is_active' => $changes['is_active'] ?? $ejemplar->is_active,
                'lost_at' => $changes['lost_at'] ?? $ejemplar->lost_at,
                'damaged_at' => $changes['damaged_at'] ?? $ejemplar->damaged_at,
                'withdrawn_at' => $changes['withdrawn_at'] ?? $ejemplar->withdrawn_at,
                'updated_by' => $user->id,
            ])->save();

            $movement = $ejemplar->movimientos()->create([
                'movement_type' => $movementType,
                'previous_location' => $previousLocation,
                'new_location' => $ejemplar->physical_location,
                'previous_state' => $previousState,
                'new_state' => $ejemplar->physical_state,
                'movement_date' => $metadata['movement_date'] ?? Carbon::now(),
                'physical_count_status' => $metadata['physical_count_status'] ?? null,
                'notes' => $notes,
                'responsible_user_id' => $user->id,
                'metadata' => $metadata,
            ]);

            $this->refreshWorkAvailability($ejemplar->obra()->firstOrFail());

            return $movement;
        });
    }
}
