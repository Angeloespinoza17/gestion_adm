<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaAttachment;
use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\Convivencia\ConvivenciaIdpsResult;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ConvivenciaAccessService
{
    private const INSTALLATION_CACHE_KEY = 'convivencia:installation-state:runtime-v1';

    /**
     * Optional ownership columns are explicit so privacy scopes never query
     * information_schema during normal requests. New ownership fields must be
     * reviewed here; an omitted field fails closed instead of widening access.
     *
     * @var array<string, array<int, string>>
     */
    private const OWNERSHIP_COLUMNS = [
        'convivencia_cases' => ['responsible_user_id'],
        'convivencia_complaints' => ['responsible_user_id'],
        'convivencia_derivations' => ['responsible_user_id'],
        'convivencia_interviews' => ['responsible_user_id'],
        'convivencia_measures' => ['responsible_user_id'],
        'convivencia_plans' => ['responsible_user_id'],
        'convivencia_daily_logs' => ['inspector_user_id'],
        'convivencia_sociograms' => [],
        'convivencia_idps_results' => [],
        'convivencia_protocols' => [],
        'convivencia_protocol_activations' => ['activated_by'],
    ];

    /** @var array<int, array<int, string>> */
    private array $permissionCache = [];

    /** @var array<int, bool> */
    private array $superAdminCache = [];

    /** @var array<string, bool> */
    private array $columnCache = [];

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
            'convivencia_protocol_parts',
            'convivencia_protocol_part_links',
            'convivencia_protocol_activations',
            'convivencia_protocol_activation_steps',
            'convivencia_protocol_activation_parts',
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
        return (bool) $this->installationState()['installed'];
    }

    /**
     * @return array<int, string>
     */
    public function missingTables(): array
    {
        return $this->installationState()['missing_tables'];
    }

    public function clearInstallationCache(): void
    {
        try {
            Cache::forget(self::INSTALLATION_CACHE_KEY);
        } catch (\Throwable) {
            // Cache failure must never turn into an authorization bypass.
        }
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
        return $this->hasAny($user, [self::ACTIVATE_PROTOCOLS_PERMISSION]);
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
        return $this->hasAny($user, [self::VIEW_COURSE_REPORTS_PERMISSION]);
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

    public function canAccessNominalCatalogs(?User $user): bool
    {
        return $this->canCreateCase($user)
            || $this->canViewCases($user)
            || $this->canEditCases($user)
            || $this->canManagePlans($user)
            || $this->canManageComplaints($user)
            || $this->canManageInterviews($user)
            || $this->canManageMeasures($user)
            || $this->canManageInternalDerivations($user)
            || $this->canManageExternalDerivations($user)
            || $this->canManageDailyLogs($user)
            || $this->canViewSociograms($user)
            || $this->canManageSociograms($user);
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
        $canViewScope = $this->canViewCases($user)
            || ($derivation->scope === 'internal' && $this->canManageInternalDerivations($user))
            || ($derivation->scope === 'external' && $this->canManageExternalDerivations($user));

        return $this->canViewRecord(
            $user,
            $derivation,
            $canViewScope
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
        if ($this->canManageProtocols($user) || $this->canActivateProtocols($user)) {
            return true;
        }

        return $this->canViewRecord($user, $protocol, $this->canViewCases($user));
    }

    public function canViewProtocolActivation(User $user, ConvivenciaProtocolActivation $activation): bool
    {
        if ((int) $activation->activated_by === (int) $user->id) {
            return true;
        }

        $hasContext = false;
        if ($activation->case_id) {
            $hasContext = true;
            if (! $activation->case || ! $this->canViewCase($user, $activation->case)) {
                return false;
            }
        }

        if ($activation->complaint_id) {
            $hasContext = true;
            if (! $activation->complaint || ! $this->canViewComplaint($user, $activation->complaint)) {
                return false;
            }
        }

        if ($hasContext) {
            return true;
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
        if (! $this->canViewCases($user)) {
            $canManageInternal = $this->canManageInternalDerivations($user);
            $canManageExternal = $this->canManageExternalDerivations($user);

            if (($canManageInternal || $canManageExternal) && ! ($canManageInternal && $canManageExternal)) {
                $query->where('scope', $canManageExternal ? 'external' : 'internal');
            }
        }

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

    public function applyIdpsResultVisibility(Builder $query, User $user): Builder
    {
        return $this->applySensitiveVisibility($query, $user);
    }

    public function canViewIdpsResult(User $user, ConvivenciaIdpsResult $result): bool
    {
        return $this->canViewRecord(
            $user,
            $result,
            $this->canViewCourseReports($user)
                || $this->canManagePlans($user)
                || $this->canManageSettings($user)
                || $this->canViewDashboard($user),
        );
    }

    public function applyProtocolVisibility(Builder $query, User $user): Builder
    {
        if ($this->canManageProtocols($user) || $this->canActivateProtocols($user)) {
            return $query;
        }

        return $this->applySensitiveVisibility($query, $user);
    }

    public function applyProtocolActivationVisibility(Builder $query, User $user): Builder
    {
        if ($this->isSuperAdmin($user)) {
            return $query;
        }

        $canSeeSensitive = $this->canViewSensitiveData($user);
        $canSeeContextless = $this->canManageProtocols($user) || $this->canActivateProtocols($user);
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $builder) use ($user, $canSeeSensitive, $canSeeContextless, $table) {
            $builder->where("{$table}.activated_by", $user->id)
                ->orWhere(function (Builder $contextQuery) use ($user, $canSeeSensitive, $table) {
                    $contextQuery
                        ->where(function (Builder $caseBranch) use ($user, $canSeeSensitive, $table) {
                            $caseBranch->whereNull("{$table}.case_id");
                            if ($this->canViewCases($user)) {
                                $caseBranch->orWhereHas('case', function (Builder $caseQuery) use ($user, $canSeeSensitive) {
                                    if (! $canSeeSensitive) {
                                        $this->applySensitiveVisibility($caseQuery, $user);
                                    }
                                });
                            }
                        })
                        ->where(function (Builder $complaintBranch) use ($user, $canSeeSensitive, $table) {
                            $complaintBranch->whereNull("{$table}.complaint_id");
                            if ($this->canManageComplaints($user) || $this->canViewCases($user)) {
                                $complaintBranch->orWhereHas('complaint', function (Builder $complaintQuery) use ($user, $canSeeSensitive) {
                                    if (! $canSeeSensitive) {
                                        $this->applySensitiveVisibility($complaintQuery, $user);
                                    }
                                });
                            }
                        })
                        ->where(function (Builder $hasContext) use ($table) {
                            $hasContext->whereNotNull("{$table}.case_id")->orWhereNotNull("{$table}.complaint_id");
                        });
                });

            if ($canSeeContextless) {
                $builder->orWhere(function (Builder $contextless) use ($table) {
                    $contextless
                        ->whereNull("{$table}.case_id")
                        ->whereNull("{$table}.complaint_id");
                });
            }
        });
    }

    public function canViewAttachment(User $user, ConvivenciaAttachment $attachment): bool
    {
        return ! $attachment->requiresSensitiveAccess()
            || $this->canViewSensitiveData($user)
            || (int) $attachment->uploaded_by === (int) $user->id;
    }

    public function applyAttachmentVisibility(Builder|Relation $query, ?User $user): Builder|Relation
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->isSuperAdmin($user) || $this->canViewSensitiveData($user)) {
            return $query;
        }

        $table = ($query instanceof Relation ? $query->getRelated() : $query->getModel())->getTable();

        return $query->where(function (Builder $visibility) use ($table, $user) {
            $visibility
                ->where("{$table}.uploaded_by", $user->id)
                ->orWhere(function (Builder $nonSensitive) use ($table) {
                    $nonSensitive
                        ->where("{$table}.is_sensitive", false)
                        ->whereIn(
                            "{$table}.confidentiality_level",
                            ConvivenciaAttachment::NON_SENSITIVE_CONFIDENTIALITY_LEVELS
                        );
                });
        });
    }

    private function applySensitiveVisibility(Builder $query, User $user): Builder
    {
        if ($this->isSuperAdmin($user) || $this->canViewSensitiveData($user)) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $builder) use ($table, $user) {
            $builder
                ->where("{$table}.is_sensitive", false)
                ->orWhere("{$table}.created_by", $user->id)
                ->orWhere("{$table}.updated_by", $user->id);

            if ($this->hasColumn($builder, 'responsible_user_id')) {
                $builder->orWhere("{$table}.responsible_user_id", $user->id);
            }

            if ($this->hasColumn($builder, 'inspector_user_id')) {
                $builder->orWhere("{$table}.inspector_user_id", $user->id);
            }

            if ($this->hasColumn($builder, 'activated_by')) {
                $builder->orWhere("{$table}.activated_by", $user->id);
            }
        });
    }

    private function canViewRecord(User $user, Model $record, bool $baseAccess): bool
    {
        if (! $baseAccess) {
            return false;
        }

        if ($this->isSuperAdmin($user) || $this->canViewSensitiveData($user)) {
            return true;
        }

        $isSensitive = (bool) ($record->getAttribute('is_sensitive') ?? false);
        if (! $isSensitive) {
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
        if (! $user || ! $user->active) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $userPermissions = $this->permissionCache[(int) $user->id]
            ??= $user->permissionSlugs();

        return array_intersect($permissions, $userPermissions) !== [];
    }

    private function isSuperAdmin(User $user): bool
    {
        return $this->superAdminCache[(int) $user->id]
            ??= $user->isSuperAdmin();
    }

    private function hasColumn(Builder $builder, string $column): bool
    {
        $table = $builder->getModel()->getTable();

        if (array_key_exists($table, self::OWNERSHIP_COLUMNS)) {
            return in_array($column, self::OWNERSHIP_COLUMNS[$table], true);
        }

        $connection = $builder->getModel()->getConnectionName()
            ?? config('database.default', 'default');
        $cacheKey = "{$connection}:{$table}.{$column}";

        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }

        try {
            return $this->columnCache[$cacheKey] = Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            // If metadata inspection fails, omitting an optional ownership
            // branch is safer than broadening access to sensitive records.
            return $this->columnCache[$cacheKey] = false;
        }
    }

    /**
     * @return array{installed: bool, missing_tables: array<int, string>}
     */
    private function installationState(): array
    {
        try {
            return Cache::remember(
                self::INSTALLATION_CACHE_KEY,
                now()->addMinute(),
                fn () => $this->inspectInstallationState()
            );
        } catch (\Throwable) {
            return $this->inspectInstallationState();
        }
    }

    /**
     * @return array{installed: bool, missing_tables: array<int, string>}
     */
    private function inspectInstallationState(): array
    {
        try {
            $missing = collect($this->requiredTables())
                ->reject(fn (string $table) => Schema::hasTable($table))
                ->values()
                ->all();

            return [
                'installed' => $missing === [],
                'missing_tables' => $missing,
            ];
        } catch (\Throwable) {
            return [
                'installed' => false,
                'missing_tables' => $this->requiredTables(),
            ];
        }
    }
}
