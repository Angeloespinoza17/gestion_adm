<?php

namespace App\Services\Convivencia;

use App\Models\ApoyoProfesional\ApoyoProfesionalProfile;
use App\Models\Staff;
use Illuminate\Support\Collection;

class ConvivenciaSupportProfessionalService
{
    /**
     * Cargos institucionales que pueden incorporarse como apoyo aunque todavía
     * no tengan una ficha creada en el módulo Apoyo Profesional.
     *
     * @var array<string, string>
     */
    private const SUPPORT_CARGO_AREAS = [
        'psicologo' => 'Psicología',
        'orientadora' => 'Orientación',
        'trabajadora-social' => 'Trabajo social',
        'fonoaudiologoa' => 'Fonoaudiología',
        'educadora-diferencial' => 'PIE',
        'coordinadora-pie' => 'PIE',
        'asistente-pie' => 'PIE',
        'inspectoria' => 'Inspectoría',
        'coordinador_inspectoria' => 'Inspectoría',
    ];

    private ?Collection $cachedOptions = null;

    /**
     * Reutiliza las fichas del módulo Apoyo Profesional y completa la oferta
     * con funcionarios de cargos de apoyo aún no configurados allí.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function options(): Collection
    {
        if ($this->cachedOptions !== null) {
            return $this->cachedOptions;
        }

        $profiles = ApoyoProfesionalProfile::query()
            ->with([
                'user:id,name,staff_id',
                'staff:id,full_name,cargo_id,professional_title,specialty',
                'staff.cargo:id,name,slug',
                'staff.user:id,name,staff_id',
            ])
            ->where('active', true)
            ->orderBy('area_name')
            ->orderBy('professional_role_name')
            ->get();

        $profileStaffIds = $profiles->pluck('staff_id')->filter()->map(fn ($id) => (int) $id)->all();
        $profileUserIds = $profiles->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all();

        $configured = $profiles->map(function (ApoyoProfesionalProfile $profile): array {
            $staff = $profile->staff;
            $user = $profile->user ?: $staff?->user;
            $fullName = $staff?->full_name ?: $user?->name ?: 'Profesional de apoyo';

            return [
                'key' => 'profile:'.$profile->id,
                'profile_id' => (int) $profile->id,
                'user_id' => $user?->id,
                'staff_id' => $staff?->id,
                'full_name' => $fullName,
                'area_name' => $profile->area_name,
                'professional_role_name' => $profile->professional_role_name,
                'source' => 'apoyo_profesional',
            ];
        });

        $directory = Staff::query()
            ->with(['cargo:id,name,slug', 'user:id,name,staff_id'])
            ->where('active', true)
            ->whereHas('cargo', fn ($query) => $query->whereIn('slug', array_keys(self::SUPPORT_CARGO_AREAS)))
            ->when($profileStaffIds !== [], fn ($query) => $query->whereNotIn('id', $profileStaffIds))
            ->when($profileUserIds !== [], fn ($query) => $query->whereDoesntHave('user', fn ($inner) => $inner->whereIn('id', $profileUserIds)))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'cargo_id', 'professional_title', 'specialty'])
            ->map(function (Staff $staff): array {
                $area = self::SUPPORT_CARGO_AREAS[$staff->cargo?->slug] ?? $staff->cargo?->name ?? 'Equipo de apoyo';

                return [
                    'key' => 'staff:'.$staff->id,
                    'profile_id' => null,
                    'user_id' => $staff->user?->id,
                    'staff_id' => (int) $staff->id,
                    'full_name' => $staff->full_name,
                    'area_name' => $area,
                    'professional_role_name' => $staff->professional_title ?: $staff->cargo?->name ?: $area,
                    'source' => 'directorio_funcionarios',
                ];
            });

        return $this->cachedOptions = $configured
            ->concat($directory)
            ->sortBy([
                ['area_name', 'asc'],
                ['full_name', 'asc'],
            ], SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
