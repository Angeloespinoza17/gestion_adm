<?php

namespace App\Services\RiskPrevention;

use App\Models\RiskPrevention\RiskPreventionDocument;
use App\Models\RiskPrevention\RiskPreventionEppDelivery;
use App\Models\RiskPrevention\RiskPreventionFireExtinguisher;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class RiskPreventionAccessService
{
    public const VIEW_PERMISSION = 'ver_prevencion_riesgos';

    public const MANAGE_PERMISSION = 'gestionar_prevencion_riesgos';

    public const EXPORT_PERMISSION = 'exportar_prevencion_riesgos';

    public const DISSEMINATED_DOCUMENTS_PERMISSION = 'ver_documentos_prevencion_difundibles';

    public const VIEW_COMMITTEE_PERMISSION = 'ver_comite_paritario';

    public const UPLOAD_COMMITTEE_MINUTES_PERMISSION = 'cargar_actas_comite_paritario';

    public const VIEW_EPP_DELIVERIES_PERMISSION = 'ver_entregas_epp';

    public const REGISTER_EPP_DELIVERIES_PERMISSION = 'registrar_entregas_epp';

    /**
     * @return array<int, string>
     */
    public function requiredTables(): array
    {
        return [
            'prevent_fire_extinguishers',
            'prevent_accidents',
            'prevent_accident_follow_ups',
            'prevent_emergency_plans',
            'prevent_emergency_drills',
            'prevent_epp_items',
            'prevent_epp_deliveries',
            'prevent_epp_delivery_records',
            'prevent_trainings',
            'prevent_training_participants',
            'prevent_documents',
            'prevent_staff_requirement_types',
            'prevent_staff_compliances',
            'prevent_joint_committees',
            'prevent_joint_committee_staff',
        ];
    }

    /**
     * El esquema IPER se despliega de forma aditiva y no debe bloquear el
     * dashboard ni las operaciones históricas de Prevención de Riesgos.
     *
     * @return array<int, string>
     */
    public function riskMatrixRequiredTables(): array
    {
        return [
            'prevent_risk_methodologies',
            'prevent_risk_catalog_items',
            'prevent_risk_matrices',
            'prevent_risk_matrix_versions',
            'prevent_risk_matrix_processes',
            'prevent_risk_matrix_tasks',
            'prevent_risk_task_positions',
            'prevent_risk_task_exposures',
            'prevent_risk_entries',
            'prevent_risk_hazard_factors',
            'prevent_risk_assessments',
            'prevent_risk_controls',
            'prevent_risk_evidences',
            'prevent_risk_matrix_participations',
            'prevent_risk_matrix_reviews',
            'prevent_preventive_programs',
            'prevent_preventive_program_actions',
            'prevent_risk_import_batches',
            'prevent_risk_import_row_issues',
            'prevent_risk_audit_logs',
            'prevent_risk_alert_logs',
        ];
    }

    public function isInstalled(): bool
    {
        foreach ($this->requiredTables() as $table) {
            if (! Schema::hasTable($table)) {
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

    public function isRiskMatrixInstalled(): bool
    {
        foreach ($this->riskMatrixRequiredTables() as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function missingRiskMatrixTables(): array
    {
        return collect($this->riskMatrixRequiredTables())
            ->reject(fn (string $table) => Schema::hasTable($table))
            ->values()
            ->all();
    }

    public function canView(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasPermission(self::VIEW_PERMISSION)
            || $user->hasPermission(self::MANAGE_PERMISSION)
            || $user->hasPermission(self::EXPORT_PERMISSION);
    }

    public function canManage(?User $user): bool
    {
        return $user?->hasPermission(self::MANAGE_PERMISSION) ?? false;
    }

    public function canExport(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->canManage($user) || $user->hasPermission(self::EXPORT_PERMISSION);
    }

    public function canViewCommittee(?User $user): bool
    {
        return $this->canView($user)
            || ($user?->hasPermission(self::VIEW_COMMITTEE_PERMISSION) ?? false)
            || ($user?->hasPermission(self::UPLOAD_COMMITTEE_MINUTES_PERMISSION) ?? false);
    }

    public function canUploadCommitteeMinutes(?User $user): bool
    {
        return $this->canManage($user)
            || ($user?->hasPermission(self::UPLOAD_COMMITTEE_MINUTES_PERMISSION) ?? false);
    }

    public function canViewEppDeliveries(?User $user): bool
    {
        return $this->canView($user)
            || ($user?->hasPermission(self::VIEW_EPP_DELIVERIES_PERMISSION) ?? false)
            || ($user?->hasPermission(self::REGISTER_EPP_DELIVERIES_PERMISSION) ?? false);
    }

    public function canRegisterEppDeliveries(?User $user): bool
    {
        return $this->canManage($user)
            || ($user?->hasPermission(self::REGISTER_EPP_DELIVERIES_PERMISSION) ?? false);
    }

    public function refreshDynamicStatuses(): void
    {
        if (! $this->isInstalled()) {
            return;
        }

        $today = now()->startOfDay()->toDateString();
        $warningLimit = now()->addDays(30)->startOfDay()->toDateString();

        RiskPreventionFireExtinguisher::query()
            ->where('status', '!=', RiskPreventionFireExtinguisher::STATUS_DADO_BAJA)
            ->whereDate('expires_at', '<', $today)
            ->update(['status' => RiskPreventionFireExtinguisher::STATUS_VENCIDO]);

        RiskPreventionFireExtinguisher::query()
            ->where('status', '!=', RiskPreventionFireExtinguisher::STATUS_DADO_BAJA)
            ->whereBetween('expires_at', [$today, $warningLimit])
            ->update(['status' => RiskPreventionFireExtinguisher::STATUS_POR_VENCER]);

        RiskPreventionFireExtinguisher::query()
            ->where('status', '!=', RiskPreventionFireExtinguisher::STATUS_DADO_BAJA)
            ->whereDate('expires_at', '>', $warningLimit)
            ->update(['status' => RiskPreventionFireExtinguisher::STATUS_VIGENTE]);

        RiskPreventionDocument::query()
            ->where('status', '!=', RiskPreventionDocument::STATUS_ARCHIVADO)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', $today)
            ->update(['status' => RiskPreventionDocument::STATUS_VENCIDO]);

        RiskPreventionDocument::query()
            ->where('status', '!=', RiskPreventionDocument::STATUS_ARCHIVADO)
            ->whereNotNull('valid_until')
            ->whereBetween('valid_until', [$today, $warningLimit])
            ->update(['status' => RiskPreventionDocument::STATUS_POR_VENCER]);

        RiskPreventionDocument::query()
            ->where('status', '!=', RiskPreventionDocument::STATUS_ARCHIVADO)
            ->where(function ($query) use ($warningLimit) {
                $query->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>', $warningLimit);
            })
            ->update(['status' => RiskPreventionDocument::STATUS_VIGENTE]);

        RiskPreventionEppDelivery::query()
            ->where('status', '!=', RiskPreventionEppDelivery::STATUS_REPUESTO)
            ->whereNotNull('replacement_due_at')
            ->whereDate('replacement_due_at', '<=', $warningLimit)
            ->update(['status' => RiskPreventionEppDelivery::STATUS_POR_REPONER]);

        RiskPreventionEppDelivery::query()
            ->where('status', '!=', RiskPreventionEppDelivery::STATUS_REPUESTO)
            ->where(function ($query) use ($warningLimit) {
                $query->whereNull('replacement_due_at')
                    ->orWhereDate('replacement_due_at', '>', $warningLimit);
            })
            ->update(['status' => RiskPreventionEppDelivery::STATUS_VIGENTE]);
    }
}
