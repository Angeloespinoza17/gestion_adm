<?php

namespace App\Services\ApoyoProfesional;

use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Models\ApoyoProfesional\ApoyoProfesionalProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ApoyoProfesionalAccessService
{
    public const VIEW_PERMISSION = 'ver_modulo_apoyo_profesional';
    public const CREATE_ATTENTION_PERMISSION = 'crear_atencion_apoyo_profesional';
    public const EDIT_OWN_ATTENTION_PERMISSION = 'editar_atencion_propia_apoyo_profesional';
    public const EDIT_ANY_ATTENTION_PERMISSION = 'editar_cualquier_atencion_apoyo_profesional';
    public const DELETE_ATTENTION_PERMISSION = 'eliminar_atencion_apoyo_profesional';
    public const VIEW_OWN_ATTENTIONS_PERMISSION = 'ver_atenciones_propias_apoyo_profesional';
    public const VIEW_TEAM_ATTENTIONS_PERMISSION = 'ver_atenciones_equipo_apoyo_profesional';
    public const VIEW_CONFIDENTIAL_ATTENTIONS_PERMISSION = 'ver_atenciones_confidenciales_apoyo_profesional';
    public const CREATE_DERIVATION_PERMISSION = 'crear_derivacion_apoyo_profesional';
    public const RESPOND_DERIVATION_PERMISSION = 'responder_derivacion_apoyo_profesional';
    public const CREATE_FOLLOW_UP_PERMISSION = 'crear_seguimiento_apoyo_profesional';
    public const CLOSE_CASE_PERMISSION = 'cerrar_caso_apoyo_profesional';
    public const CREATE_PLAN_PERMISSION = 'crear_plan_apoyo_profesional';
    public const VIEW_REPORTS_PERMISSION = 'ver_reportes_apoyo_profesional';
    public const EXPORT_REPORTS_PERMISSION = 'exportar_reportes_apoyo_profesional';
    public const MANAGE_CONFIGURATION_PERMISSION = 'administrar_configuracion_apoyo_profesional';

    private const EXCLUDED_ROLE_SLUGS = [
        'enfermeria',
        'tens',
        'encargada_enfermeria',
    ];

    private const AUTHORIZED_ROLE_SLUGS = [
        'psicologo',
        'terapeuta_ocupacional',
        'trabajador_social',
        'psicopedagogo',
        'fonoaudiologo',
        'orientador',
        'profesional_pie',
        'coordinador_pie',
        'convivencia_escolar',
    ];

    /**
     * @return array<int, string>
     */
    public function requiredTables(): array
    {
        return [
            'apoyo_profesionales',
            'apoyo_config_tipos_atencion',
            'apoyo_config_motivos',
            'apoyo_atenciones',
            'apoyo_derivaciones',
            'apoyo_seguimientos',
            'apoyo_planes',
            'apoyo_plan_acciones',
            'apoyo_entrevistas',
            'apoyo_adjuntos',
        ];
    }

    public function isInstalled(): bool
    {
        foreach ($this->requiredTables() as $table) {
            if (!Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    public function canViewModule(?User $user): bool
    {
        return $this->hasAny($user, $this->modulePermissions());
    }

    public function canCreateAttention(?User $user): bool
    {
        return $this->hasAny($user, [self::CREATE_ATTENTION_PERMISSION]) && $this->isAuthorizedProfessional($user);
    }

    public function canViewOwnAttentions(?User $user): bool
    {
        return $this->hasAny($user, [
            self::VIEW_OWN_ATTENTIONS_PERMISSION,
            self::EDIT_OWN_ATTENTION_PERMISSION,
            self::CREATE_ATTENTION_PERMISSION,
        ]);
    }

    public function canViewTeamAttentions(?User $user): bool
    {
        return $this->hasAny($user, [
            self::VIEW_TEAM_ATTENTIONS_PERMISSION,
            self::EDIT_ANY_ATTENTION_PERMISSION,
            self::VIEW_REPORTS_PERMISSION,
            self::EXPORT_REPORTS_PERMISSION,
        ]);
    }

    public function canViewConfidentialAttentions(?User $user): bool
    {
        return $this->hasAny($user, [
            self::VIEW_CONFIDENTIAL_ATTENTIONS_PERMISSION,
            self::EDIT_ANY_ATTENTION_PERMISSION,
            self::VIEW_REPORTS_PERMISSION,
            self::EXPORT_REPORTS_PERMISSION,
        ]);
    }

    public function canEditOwnAttention(?User $user): bool
    {
        return $this->hasAny($user, [self::EDIT_OWN_ATTENTION_PERMISSION]);
    }

    public function canEditAnyAttention(?User $user): bool
    {
        return $this->hasAny($user, [self::EDIT_ANY_ATTENTION_PERMISSION]);
    }

    public function canDeleteAttention(?User $user): bool
    {
        return $this->hasAny($user, [self::DELETE_ATTENTION_PERMISSION]);
    }

    public function canCreateDerivation(?User $user): bool
    {
        return $this->hasAny($user, [self::CREATE_DERIVATION_PERMISSION]);
    }

    public function canRespondDerivation(?User $user): bool
    {
        return $this->hasAny($user, [self::RESPOND_DERIVATION_PERMISSION, self::EDIT_ANY_ATTENTION_PERMISSION]);
    }

    public function canCreateFollowUp(?User $user): bool
    {
        return $this->hasAny($user, [self::CREATE_FOLLOW_UP_PERMISSION, self::EDIT_ANY_ATTENTION_PERMISSION]);
    }

    public function canCloseCase(?User $user): bool
    {
        return $this->hasAny($user, [self::CLOSE_CASE_PERMISSION, self::EDIT_ANY_ATTENTION_PERMISSION]);
    }

    public function canCreatePlan(?User $user): bool
    {
        return $this->hasAny($user, [self::CREATE_PLAN_PERMISSION, self::EDIT_ANY_ATTENTION_PERMISSION]);
    }

    public function canViewReports(?User $user): bool
    {
        return $this->hasAny($user, [self::VIEW_REPORTS_PERMISSION, self::EXPORT_REPORTS_PERMISSION]);
    }

    public function canExportReports(?User $user): bool
    {
        return $this->hasAny($user, [self::EXPORT_REPORTS_PERMISSION]);
    }

    public function canManageConfiguration(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_CONFIGURATION_PERMISSION]);
    }

    public function isAuthorizedProfessional(?User $user): bool
    {
        if (!$user || !$user->active || $this->isExcludedProfessional($user)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($this->professionalProfileForUser($user)?->active) {
            return true;
        }

        $roleSlugs = $this->roleSlugs($user);
        $cargoSlug = $user->staff?->cargo?->slug ?: $user->cargo?->slug;

        return collect($roleSlugs)
            ->push($cargoSlug)
            ->filter()
            ->intersect(self::AUTHORIZED_ROLE_SLUGS)
            ->isNotEmpty();
    }

    public function professionalProfileForUser(?User $user): ?ApoyoProfesionalProfile
    {
        if (!$user) {
            return null;
        }

        return ApoyoProfesionalProfile::query()
            ->where('active', true)
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id);

                if ($user->staff_id) {
                    $query->orWhere('staff_id', $user->staff_id);
                }
            })
            ->orderBy('id')
            ->first();
    }

    public function professionalRoleNameForUser(User $user): string
    {
        $profile = $this->professionalProfileForUser($user);

        return $profile?->professional_role_name
            ?: ($user->staff?->cargo?->name ?: $user->cargo?->name ?: $user->name);
    }

    public function professionalAreaForUser(User $user): array
    {
        $profile = $this->professionalProfileForUser($user);

        if ($profile) {
            return [
                'slug' => $profile->area_slug,
                'name' => $profile->area_name,
            ];
        }

        return [
            'slug' => 'otra',
            'name' => 'Otra',
        ];
    }

    public function canViewAttention(User $user, ApoyoAtencion $attention): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $isOwner = (int) $attention->attended_by_user_id === (int) $user->id;
        $isConfidential = $this->isConfidentialAttention($attention);

        if ($isOwner) {
            return $this->canViewOwnAttentions($user);
        }

        if (!$this->canViewTeamAttentions($user)) {
            return false;
        }

        if ($isConfidential && !$this->canViewConfidentialAttentions($user)) {
            return false;
        }

        return true;
    }

    public function canEditAttention(User $user, ApoyoAtencion $attention): bool
    {
        if ($this->canEditAnyAttention($user)) {
            return true;
        }

        return (int) $attention->attended_by_user_id === (int) $user->id
            && $this->canEditOwnAttention($user);
    }

    public function canViewDerivation(User $user, ApoyoDerivacion $derivation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $linkedAttention = $derivation->relationLoaded('attention')
            ? $derivation->attention
            : $derivation->attention()->first();

        if ($linkedAttention && $this->canViewAttention($user, $linkedAttention)) {
            return true;
        }

        return (int) $derivation->destination_user_id === (int) $user->id
            || (int) $derivation->created_by === (int) $user->id;
    }

    public function applyAttentionVisibility(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || ($this->canViewTeamAttentions($user) && $this->canViewConfidentialAttentions($user))) {
            return $query;
        }

        if ($this->canViewTeamAttentions($user)) {
            return $query->where(function (Builder $inner) use ($user) {
                $inner
                    ->where('attended_by_user_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhere(function (Builder $team) {
                        $team
                            ->where('is_confidential_case', false)
                            ->whereNotIn('confidentiality_level', ['confidencial', 'alta_confidencialidad']);
                    });
            });
        }

        return $query->where(function (Builder $inner) use ($user) {
            $inner
                ->where('attended_by_user_id', $user->id)
                ->orWhere('created_by', $user->id);
        });
    }

    public function applyDerivationVisibility(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($this->canViewTeamAttentions($user) && $this->canViewConfidentialAttentions($user)) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user) {
            $inner
                ->where('created_by', $user->id)
                ->orWhere('destination_user_id', $user->id)
                ->orWhereHas('attention', function (Builder $attentionQuery) use ($user) {
                    $this->applyAttentionVisibility($attentionQuery, $user);
                });
        });
    }

    /**
     * @return array<int, string>
     */
    public function modulePermissions(): array
    {
        return [
            self::VIEW_PERMISSION,
            self::CREATE_ATTENTION_PERMISSION,
            self::EDIT_OWN_ATTENTION_PERMISSION,
            self::EDIT_ANY_ATTENTION_PERMISSION,
            self::DELETE_ATTENTION_PERMISSION,
            self::VIEW_OWN_ATTENTIONS_PERMISSION,
            self::VIEW_TEAM_ATTENTIONS_PERMISSION,
            self::VIEW_CONFIDENTIAL_ATTENTIONS_PERMISSION,
            self::CREATE_DERIVATION_PERMISSION,
            self::RESPOND_DERIVATION_PERMISSION,
            self::CREATE_FOLLOW_UP_PERMISSION,
            self::CLOSE_CASE_PERMISSION,
            self::CREATE_PLAN_PERMISSION,
            self::VIEW_REPORTS_PERMISSION,
            self::EXPORT_REPORTS_PERMISSION,
            self::MANAGE_CONFIGURATION_PERMISSION,
        ];
    }

    private function isExcludedProfessional(User $user): bool
    {
        $roleSlugs = $this->roleSlugs($user);
        $cargoSlug = $user->staff?->cargo?->slug ?: $user->cargo?->slug;

        return collect($roleSlugs)
            ->push($cargoSlug)
            ->filter()
            ->contains(fn (string $slug) => in_array($slug, self::EXCLUDED_ROLE_SLUGS, true));
    }

    private function isConfidentialAttention(ApoyoAtencion $attention): bool
    {
        return $attention->is_confidential_case
            || in_array($attention->confidentiality_level, ['confidencial', 'alta_confidencialidad'], true);
    }

    /**
     * @return array<int, string>
     */
    private function roleSlugs(User $user): array
    {
        $roles = $user->relationLoaded('roles')
            ? $user->roles
            : $user->roles()->get();

        return $roles->pluck('slug')->filter()->values()->all();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function hasAny(?User $user, array $permissions): bool
    {
        if (!$user || !$user->active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
