<?php

namespace App\Services\Operational;

use App\Models\Staff;
use App\Models\User;

class OperationalTransferGenericRequesterService
{
    public const NAME = 'Solicitante genérico';

    public const EMAIL = 'solicitante.generico.traslados@cnscgestion.local';

    public function resolve(?string $requesterName, User $actor): Staff
    {
        $requesterName = trim((string) $requesterName);
        if ($requesterName !== '') {
            $staff = Staff::query()
                ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($requesterName)])
                ->where('active', true)
                ->orderBy('id')
                ->first();

            if ($staff) {
                return $staff;
            }
        }

        return $this->generic($actor);
    }

    public function generic(User $actor): Staff
    {
        $staff = Staff::query()->firstOrNew(['institutional_email' => self::EMAIL]);
        $staff->forceFill([
            'full_name' => self::NAME,
            'status' => 'activo',
            'active' => true,
            'internal_notes' => $staff->internal_notes ?: 'Perfil institucional utilizado cuando un traslado histórico no identifica a la persona solicitante.',
            'created_by' => $staff->exists ? $staff->created_by : $actor->id,
            'updated_by' => $actor->id,
        ])->save();

        return $staff;
    }

    public function isGeneric(Staff $staff): bool
    {
        return $staff->institutional_email === self::EMAIL;
    }
}
