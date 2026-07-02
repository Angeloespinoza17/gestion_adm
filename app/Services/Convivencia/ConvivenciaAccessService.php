<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ConvivenciaAccessService
{
    public const LEGACY_VIEW_PERMISSION = 'ver_convivencia';
    public const VIEW_DASHBOARD_PERMISSION = 'ver_dashboard_convivencia';
    public const MANAGE_PLAN_PERMISSION = 'gestionar_plan_convivencia';
    public const CREATE_CASE_PERMISSION = 'crear_casos_convivencia';
    public const VIEW_CASES_PERMISSION = 'ver_casos_convivencia';
    public const EDIT_CASES_PERMISSION = 'editar_casos_convivencia';
    public const CLOSE_CASES_PERMISSION = 'cerrar_casos_convivencia';
    public const VIEW_SENSITIVE_CASES_PERMISSION = 'ver_casos_sensibles_convivencia';
    public const MANAGE_COMPLAINTS_PERMISSION = 'gestionar_denuncias_convivencia';
    public const MANAGE_PROTOCOLS_PERMISSION = 'gestionar_protocolos_convivencia';
    public const ACTIVATE_PROTOCOLS_PERMISSION = 'activar_protocolos_convivencia';
    public const MANAGE_INTERVIEWS_PERMISSION = 'gestionar_entrevistas_convivencia';
    public const MANAGE_MEASURES_PERMISSION = 'gestionar_medidas_formativas_convivencia';
    public const MANAGE_INTERNAL_DERIVATIONS_PERMISSION = 'gestionar_derivaciones_internas_convivencia';
    public const MANAGE_EXTERNAL_DERIVATIONS_PERMISSION = 'gestionar_derivaciones_externas_convivencia';
    public const VIEW_SOCIOGRAMS_PERMISSION = 'ver_sociogramas_convivencia';
    public const MANAGE_SOCIOGRAMS_PERMISSION = 'gestionar_sociogramas_convivencia';
    public const VIEW_COURSE_REPORTS_PERMISSION = 'ver_reportes_curso_convivencia';
    public const MANAGE_DAILY_LOG_PERMISSION = 'gestionar_bitacora_inspectoria_convivencia';
    public const EXPORT_REPORTS_PERMISSION = 'exportar_reportes_convivencia';
    public const MANAGE_SETTINGS_PERMISSION = 'administrar_configuraciones_convivencia';

    /**
     * @return array<int, string>
     */
    public function requiredTables(): array
    {
        return [
            'convivencia_catalog_items',
            'convivencia_plans',
            'convivencia_plan_actions',
            'convivencia_cases',
            'convivencia_case_people',
            'convivencia_case_followups',
            'convivencia_protocols',
            'convivencia_protocol_steps',
            'convivencia_protocol_activations',
            'convivencia_protocol_activation_logs',
            'convivencia_complaints',
            'convivencia_derivations',
            'convivencia_measures',
            'convivencia_interviews',
            'convivencia_interview_participants',
            'convivencia_daily_logs',
            'convivencia_sociograms',
            'convivencia_sociogram_questions',
            'convivencia_sociogram_answers',
            'convivencia_idps_periods',
            'convivencia_idps_dimensions',
            'convivencia_idps_instruments',
            'convivencia_idps_results',
            'convivencia_attachments',
            'convivencia_status_logs',
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

    /**
     * @return array<int, string>
     */
    public function missingTables(): array
    {
        return collect($this->requiredTables())
            ->reject(fn (string $table) => Schema::hasTable($table))
            ->values()
            ->all();
    }

    public function canViewDashboard(?User $user): bool
    {
        return $this->hasAny($user, [self::LEGACY_VIEW_PERMISSION, self::VIEW_DASHBOARD_PERMISSION, self::VIEW_CASES_PERMISSION]);
    }

    public function canManagePlans(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_PLAN_PERMISSION]);
    }

    public function canCreateCase(?User $user): bool
    {
        return $this->hasAny($user, [self::CREATE_CASE_PERMISSION]);
    }

    public function canViewCases(?User $user): bool
    {
        return $this->hasAny($user, [self::LEGACY_VIEW_PERMISSION, self::VIEW_CASES_PERMISSION, self::EDIT_CASES_PERMISSION, self::CLOSE_CASES_PERMISSION]);
    }

    public function canEditCases(?User $user): bool
    {
        return $this->hasAny($user, [self::EDIT_CASES_PERMISSION]);
    }

    public function canCloseCases(?User $user): bool
    {
        return $this->hasAny($user, [self::CLOSE_CASES_PERMISSION]);
    }

    public function canViewSensitiveData(?User $user): bool
    {
        return $this->hasAny($user, [self::VIEW_SENSITIVE_CASES_PERMISSION]);
    }

    public function canManageComplaints(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_COMPLAINTS_PERMISSION]);
    }

    public function canManageProtocols(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_PROTOCOLS_PERMISSION]);
    }

    public function canActivateProtocols(?User $user): bool
    {
        return $this->hasAny($user, [self::ACTIVATE_PROTOCOLS_PERMISSION, self::MANAGE_PROTOCOLS_PERMISSION]);
    }

    public function canManageInterviews(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_INTERVIEWS_PERMISSION]);
    }

    public function canManageMeasures(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_MEASURES_PERMISSION]);
    }

    public function canManageInternalDerivations(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_INTERNAL_DERIVATIONS_PERMISSION]);
    }

    public function canManageExternalDerivations(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_EXTERNAL_DERIVATIONS_PERMISSION]);
    }

    public function canViewSociograms(?User $user): bool
    {
        return $this->hasAny($user, [self::VIEW_SOCIOGRAMS_PERMISSION, self::MANAGE_SOCIOGRAMS_PERMISSION]);
    }

    public function canManageSociograms(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_SOCIOGRAMS_PERMISSION]);
    }

    public function canViewCourseReports(?User $user): bool
    {
        return $this->hasAny($user, [self::VIEW_COURSE_REPORTS_PERMISSION, self::EXPORT_REPORTS_PERMISSION]);
    }

    public function canManageDailyLogs(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_DAILY_LOG_PERMISSION]);
    }

    public function canExportReports(?User $user): bool
    {
        return $this->hasAny($user, [self::EXPORT_REPORTS_PERMISSION]);
    }

    public function canManageSettings(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_SETTINGS_PERMISSION]);
    }

    public function canViewModule(?User $user): bool
    {
        return $this->hasAny($user, array_merge([self::LEGACY_VIEW_PERMISSION], $this->modulePermissions()));
    }

    public function canViewCase(User $user, ConvivenciaCase $case): bool
    {
        return $this->canViewRecord($user, $case, $this->canViewCases($user));
    }

    public function canViewComplaint(User $user, ConvivenciaComplaint $complaint): bool
    {
        return $this->canViewRecord($user, $complaint, $this->canManageComplaints($user) || $this->canViewCases($user));
    }

    public function canViewDerivation(User $user, ConvivenciaDerivation $derivation): bool
    {
        return $this->canViewRecord(
            $user,
            $derivation,
            $this->canManageInternalDerivations($user) || $this->canManageExternalDerivations($user) || $this->canViewCases($user)
        );
    }

    public function canViewInterview(User $user, ConvivenciaInterview $interview): bool
    {
        return $this->canViewRecord($user, $interview, $this->canManageInterviews($user) || $this->canViewCases($user));
    }

    public function canViewMeasure(User $user, ConvivenciaMeasure $measure): bool
    {
        return $this->canViewRecord($user, $measure, $this->canManageMeasures($user) || $this->canViewCases($user));
    }

    public function canViewPlan(User $user, ConvivenciaPlan $plan): bool
    {
        return $this->canViewRecord($user, $plan, $this->canManagePlans($user) || $this->canViewCases($user));
    }

    public function canViewProtocol(User $user, ConvivenciaProtocol $protocol): bool
    {
        return $this->canViewRecord($user, $protocol, $this->canManageProtocols($user) || $this->canViewCases($user));
    }

    public function canViewProtocolActivation(User $user, ConvivenciaProtocolActivation $activation): bool
    {
        if ($activation->case) {
            return $this->canViewCase($user, $activation->case);
        }

        if ($activation->complaint) {
            return $this->canViewComplaint($user, $activation->complaint);
        }

        return $this->canActivateProtocols($user) || $this->canManageProtocols($user);
    }

    public function canViewDailyLog(User $user, ConvivenciaDailyLog $dailyLog): bool
    {
        return $this->canViewRecord($user, $dailyLog, $this->canManageDailyLogs($user) || $this->canViewCases($user));
    }

    public function canViewSociogram(User $user, ConvivenciaSociogram $sociogram): bool
    {
        return $this->canViewRecord($user, $sociogram, $this->canViewSociograms($user));
    }

    public function applyCaseVisibility(Builder $query, User $user): Builder
    {
        return $this->applySensitiveVisibility($query, $user);
    }

    public function applyComplaintVisibility(Builder $query, User $user): Builder
    {
        return $this->applySensitiveVisibility($query, $user);
    }

    public function applyDerivationVisibility(Builder $query, User $user): Builder
    {
        return $this->applySensitiveVisibility($query, $user);
    }

    public function applyInterviewVisibility(Builder $query, User $user): Builder
    {
        return $this->applySensitiveVisibility($query, $user);
    }

    public function applyMeasureVisibility(Builder $query, User $user): Builder
    {
        return $this->applySensitiveVisibility($query, $user);
    }

    public function applyPlanVisibility(Builder $query, User $user): Builder
    {
        return $this->applySensitiveVisibility($query, $user);
    }

    public function applyDailyLogVisibility(Builder $query, User $user): Builder
    {
        return $this->applySensitiveVisibility($query, $user);
    }

    public function applySociogramVisibility(Builder $query, User $user): Builder
    {
        return $this->applySensitiveVisibility($query, $user);
    }

    public function applyProtocolVisibility(Builder $query, User $user): Builder
    {
        return $this->applySensitiveVisibility($query, $user);
    }

    private function applySensitiveVisibility(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $this->canViewSensitiveData($user)) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user) {
            $builder
                ->where('is_sensitive', false)
                ->orWhere('created_by', $user->id)
                ->orWhere('updated_by', $user->id);

            if ($this->hasColumn($builder, 'responsible_user_id')) {
                $builder->orWhere('responsible_user_id', $user->id);
            }

            if ($this->hasColumn($builder, 'inspector_user_id')) {
                $builder->orWhere('inspector_user_id', $user->id);
            }

            if ($this->hasColumn($builder, 'activated_by')) {
                $builder->orWhere('activated_by', $user->id);
            }
        });
    }

    private function canViewRecord(User $user, Model $record, bool $baseAccess): bool
    {
        if (!$baseAccess) {
            return false;
        }

        if ($user->isSuperAdmin() || $this->canViewSensitiveData($user)) {
            return true;
        }

        $isSensitive = (bool) ($record->getAttribute('is_sensitive') ?? false);
        if (!$isSensitive) {
            return true;
        }

        $userId = (int) $user->id;
        $candidateValues = [
            $record->getAttribute('created_by'),
            $record->getAttribute('updated_by'),
            $record->getAttribute('responsible_user_id'),
            $record->getAttribute('inspector_user_id'),
            $record->getAttribute('activated_by'),
            $record->getAttribute('closed_by'),
        ];

        return collect($candidateValues)
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->contains($userId);
    }

    /**
     * @return array<int, string>
     */
    private function modulePermissions(): array
    {
        return [
            self::VIEW_DASHBOARD_PERMISSION,
            self::MANAGE_PLAN_PERMISSION,
            self::CREATE_CASE_PERMISSION,
            self::VIEW_CASES_PERMISSION,
            self::EDIT_CASES_PERMISSION,
            self::CLOSE_CASES_PERMISSION,
            self::VIEW_SENSITIVE_CASES_PERMISSION,
            self::MANAGE_COMPLAINTS_PERMISSION,
            self::MANAGE_PROTOCOLS_PERMISSION,
            self::ACTIVATE_PROTOCOLS_PERMISSION,
            self::MANAGE_INTERVIEWS_PERMISSION,
            self::MANAGE_MEASURES_PERMISSION,
            self::MANAGE_INTERNAL_DERIVATIONS_PERMISSION,
            self::MANAGE_EXTERNAL_DERIVATIONS_PERMISSION,
            self::VIEW_SOCIOGRAMS_PERMISSION,
            self::MANAGE_SOCIOGRAMS_PERMISSION,
            self::VIEW_COURSE_REPORTS_PERMISSION,
            self::MANAGE_DAILY_LOG_PERMISSION,
            self::EXPORT_REPORTS_PERMISSION,
            self::MANAGE_SETTINGS_PERMISSION,
        ];
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

    private function hasColumn(Builder $builder, string $column): bool
    {
        $table = $builder->getModel()->getTable();

        return Schema::hasColumn($table, $column);
    }
}
