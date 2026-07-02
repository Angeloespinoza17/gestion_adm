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
            'prevent_trainings',
            'prevent_training_participants',
            'prevent_documents',
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

    public function canView(?User $user): bool
    {
        if (!$user) {
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
        if (!$user) {
            return false;
        }

        return $this->canManage($user) || $user->hasPermission(self::EXPORT_PERMISSION);
    }

    public function refreshDynamicStatuses(): void
    {
        if (!$this->isInstalled()) {
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
