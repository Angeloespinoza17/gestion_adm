<?php

namespace App\Services\Inspectoria;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class InspectoriaPsychosocialProfessionalService
{
    private const PSYCHOLOGY_SLUGS = ['psicologo', 'psicologa'];

    private const SOCIAL_WORK_SLUGS = ['trabajador_social', 'trabajadora-social', 'trabajadora_social'];

    public function options(): array
    {
        return $this->query()
            ->with(['staff:id,full_name,cargo_id', 'staff.cargo:id,name,slug', 'cargo:id,name,slug', 'roles:id,name,slug'])
            ->orderBy('name')
            ->get(['id', 'staff_id', 'cargo_id', 'name', 'email'])
            ->map(function (User $user) {
                $profession = $this->profession($user);

                return [
                    'id' => $user->id,
                    'name' => $user->staff?->full_name ?: $user->name,
                    'email' => $user->email,
                    'profession' => $profession['value'],
                    'profession_label' => $profession['label'],
                ];
            })
            ->values()
            ->all();
    }

    public function find(int $userId): ?User
    {
        return $this->query()
            ->with(['staff.cargo:id,name,slug', 'cargo:id,name,slug', 'roles:id,name,slug'])
            ->find($userId);
    }

    public function profession(User $user): array
    {
        $slugs = $user->roles->pluck('slug')
            ->push($user->staff?->cargo?->slug)
            ->push($user->cargo?->slug)
            ->filter();

        if ($slugs->intersect(self::SOCIAL_WORK_SLUGS)->isNotEmpty()) {
            return ['value' => 'trabajo_social', 'label' => 'Trabajadora social'];
        }

        return ['value' => 'psicologia', 'label' => 'Psicóloga/o'];
    }

    private function query(): Builder
    {
        $eligibleSlugs = [...self::PSYCHOLOGY_SLUGS, ...self::SOCIAL_WORK_SLUGS];

        return User::query()
            ->where('active', true)
            ->whereNotNull('staff_id')
            ->where(function (Builder $query) use ($eligibleSlugs) {
                $query->whereHas('roles', fn (Builder $roles) => $roles->whereIn('slug', $eligibleSlugs))
                    ->orWhereHas('cargo', fn (Builder $cargo) => $cargo->whereIn('slug', $eligibleSlugs))
                    ->orWhereHas('staff.cargo', fn (Builder $cargo) => $cargo->whereIn('slug', $eligibleSlugs));
            });
    }
}
