<?php

namespace App\Services\Supply;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplyRecipientService
{
    public function eligibleQuery(): Builder
    {
        return Staff::query()
            ->where('staff.active', true)
            ->where('staff.can_receive_maintenance_orders', true);
    }

    public function catalog(): Collection
    {
        return $this->eligibleQuery()
            ->with(['cargo:id,name', 'departments:id,name'])
            ->orderBy('staff.full_name')
            ->get(['staff.id', 'staff.full_name', 'staff.rut', 'staff.cargo_id', 'staff.maintenance_role'])
            ->map(fn (Staff $staff): array => $this->serialize($staff))
            ->values();
    }

    public function isEligible(int $staffId): bool
    {
        return $this->eligibleQuery()->whereKey($staffId)->exists();
    }

    public function resolve(int $staffId): Staff
    {
        $recipient = $this->eligibleQuery()
            ->with(['cargo:id,name', 'departments:id,name'])
            ->find($staffId, ['staff.id', 'staff.full_name', 'staff.rut', 'staff.cargo_id', 'staff.maintenance_role']);

        if (! $recipient) {
            throw ValidationException::withMessages([
                'recipient_staff_id' => 'Selecciona un funcionario activo marcado para recibir OT de Mantención.',
            ]);
        }

        return $recipient;
    }

    public function snapshots(Staff $recipient): array
    {
        $profile = $this->serialize($recipient);

        return [
            'recipient_staff_id' => $recipient->id,
            'recipient_name' => $profile['name'],
            'recipient_rut' => $profile['rut'],
            'recipient_role' => $profile['role'],
        ];
    }

    private function serialize(Staff $staff): array
    {
        return [
            'id' => $staff->id,
            'name' => Str::squish((string) $staff->full_name),
            'rut' => $staff->rut,
            'role' => $staff->maintenance_role_label ?: $staff->cargo?->name,
            'suggested_destination' => $staff->departments
                ->pluck('name')
                ->filter()
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->implode(', '),
        ];
    }
}
