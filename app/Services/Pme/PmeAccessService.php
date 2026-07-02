<?php

namespace App\Services\Pme;

use App\Models\User;

class PmeAccessService
{
    public const VIEW_MODULE_PERMISSION = 'ver_modulo_pme';
    public const CREATE_PLAN_PERMISSION = 'crear_pme';
    public const EDIT_PLAN_PERMISSION = 'editar_pme';
    public const CLOSE_PLAN_PERMISSION = 'cerrar_pme';
    public const MANAGE_INCOMES_PERMISSION = 'administrar_ingresos_sep';
    public const VIEW_PRIORITY_STUDENTS_PERMISSION = 'ver_estudiantes_prioritarios_sep';
    public const VIEW_PREFERENTIAL_STUDENTS_PERMISSION = 'ver_estudiantes_preferentes_sep';
    public const LOAD_STUDENTS_PERMISSION = 'cargar_estudiantes_sep';
    public const CREATE_OBJECTIVES_PERMISSION = 'crear_objetivos_pme';
    public const EDIT_OBJECTIVES_PERMISSION = 'editar_objetivos_pme';
    public const CREATE_STRATEGIES_PERMISSION = 'crear_estrategias_pme';
    public const EDIT_STRATEGIES_PERMISSION = 'editar_estrategias_pme';
    public const CREATE_INDICATORS_PERMISSION = 'crear_indicadores_pme';
    public const MEASURE_INDICATORS_PERMISSION = 'medir_indicadores_pme';
    public const CREATE_ACTIONS_PERMISSION = 'crear_acciones_pme';
    public const EDIT_ACTIONS_PERMISSION = 'editar_acciones_pme';
    public const CLOSE_ACTIONS_PERMISSION = 'cerrar_acciones_pme';
    public const CREATE_EVIDENCES_PERMISSION = 'crear_evidencias_pme';
    public const REVIEW_EVIDENCES_PERMISSION = 'revisar_evidencias_pme';
    public const APPROVE_EVIDENCES_PERMISSION = 'aprobar_evidencias_pme';
    public const REJECT_EVIDENCES_PERMISSION = 'rechazar_evidencias_pme';
    public const CREATE_MILESTONES_PERMISSION = 'crear_hitos_pme';
    public const REGISTER_MONITORING_PERMISSION = 'registrar_monitoreo_reflexivo_pme';
    public const VIEW_REPORTS_PERMISSION = 'ver_reportes_pme';
    public const EXPORT_REPORTS_PERMISSION = 'exportar_reportes_pme';
    public const MANAGE_CONFIGURATION_PERMISSION = 'administrar_configuracion_pme';

    /**
     * @return array<int, array{slug:string,name:string}>
     */
    public function permissionDefinitions(): array
    {
        return [
            ['slug' => self::VIEW_MODULE_PERMISSION, 'name' => 'Ver módulo PME / SEP'],
            ['slug' => self::CREATE_PLAN_PERMISSION, 'name' => 'Crear PME'],
            ['slug' => self::EDIT_PLAN_PERMISSION, 'name' => 'Editar PME'],
            ['slug' => self::CLOSE_PLAN_PERMISSION, 'name' => 'Cerrar PME'],
            ['slug' => self::MANAGE_INCOMES_PERMISSION, 'name' => 'Administrar ingresos SEP'],
            ['slug' => self::VIEW_PRIORITY_STUDENTS_PERMISSION, 'name' => 'Ver estudiantes prioritarias SEP'],
            ['slug' => self::VIEW_PREFERENTIAL_STUDENTS_PERMISSION, 'name' => 'Ver estudiantes preferentes SEP'],
            ['slug' => self::LOAD_STUDENTS_PERMISSION, 'name' => 'Cargar estudiantes SEP'],
            ['slug' => self::CREATE_OBJECTIVES_PERMISSION, 'name' => 'Crear objetivos PME'],
            ['slug' => self::EDIT_OBJECTIVES_PERMISSION, 'name' => 'Editar objetivos PME'],
            ['slug' => self::CREATE_STRATEGIES_PERMISSION, 'name' => 'Crear estrategias PME'],
            ['slug' => self::EDIT_STRATEGIES_PERMISSION, 'name' => 'Editar estrategias PME'],
            ['slug' => self::CREATE_INDICATORS_PERMISSION, 'name' => 'Crear indicadores PME'],
            ['slug' => self::MEASURE_INDICATORS_PERMISSION, 'name' => 'Medir indicadores PME'],
            ['slug' => self::CREATE_ACTIONS_PERMISSION, 'name' => 'Crear acciones PME'],
            ['slug' => self::EDIT_ACTIONS_PERMISSION, 'name' => 'Editar acciones PME'],
            ['slug' => self::CLOSE_ACTIONS_PERMISSION, 'name' => 'Cerrar acciones PME'],
            ['slug' => self::CREATE_EVIDENCES_PERMISSION, 'name' => 'Crear evidencias PME'],
            ['slug' => self::REVIEW_EVIDENCES_PERMISSION, 'name' => 'Revisar evidencias PME'],
            ['slug' => self::APPROVE_EVIDENCES_PERMISSION, 'name' => 'Aprobar evidencias PME'],
            ['slug' => self::REJECT_EVIDENCES_PERMISSION, 'name' => 'Rechazar evidencias PME'],
            ['slug' => self::CREATE_MILESTONES_PERMISSION, 'name' => 'Crear hitos PME'],
            ['slug' => self::REGISTER_MONITORING_PERMISSION, 'name' => 'Registrar monitoreo reflexivo PME'],
            ['slug' => self::VIEW_REPORTS_PERMISSION, 'name' => 'Ver reportes PME'],
            ['slug' => self::EXPORT_REPORTS_PERMISSION, 'name' => 'Exportar reportes PME'],
            ['slug' => self::MANAGE_CONFIGURATION_PERMISSION, 'name' => 'Administrar configuración PME'],
        ];
    }

    public function canViewModule(?User $user): bool
    {
        return $this->has($user, self::VIEW_MODULE_PERMISSION);
    }

    public function canCreatePlan(?User $user): bool
    {
        return $this->has($user, self::CREATE_PLAN_PERMISSION);
    }

    public function canEditPlan(?User $user): bool
    {
        return $this->has($user, self::EDIT_PLAN_PERMISSION);
    }

    public function canClosePlan(?User $user): bool
    {
        return $this->has($user, self::CLOSE_PLAN_PERMISSION);
    }

    public function canManageIncomes(?User $user): bool
    {
        return $this->has($user, self::MANAGE_INCOMES_PERMISSION);
    }

    public function canViewStudentClassifications(?User $user): bool
    {
        return $this->has($user, self::VIEW_PRIORITY_STUDENTS_PERMISSION)
            || $this->has($user, self::VIEW_PREFERENTIAL_STUDENTS_PERMISSION)
            || $this->has($user, self::LOAD_STUDENTS_PERMISSION);
    }

    public function canLoadStudents(?User $user): bool
    {
        return $this->has($user, self::LOAD_STUDENTS_PERMISSION);
    }

    public function canManageDimensions(?User $user): bool
    {
        return $this->has($user, self::MANAGE_CONFIGURATION_PERMISSION);
    }

    public function canCreateObjective(?User $user): bool
    {
        return $this->has($user, self::CREATE_OBJECTIVES_PERMISSION);
    }

    public function canEditObjective(?User $user): bool
    {
        return $this->has($user, self::EDIT_OBJECTIVES_PERMISSION);
    }

    public function canCreateStrategy(?User $user): bool
    {
        return $this->has($user, self::CREATE_STRATEGIES_PERMISSION);
    }

    public function canEditStrategy(?User $user): bool
    {
        return $this->has($user, self::EDIT_STRATEGIES_PERMISSION);
    }

    public function canCreateIndicator(?User $user): bool
    {
        return $this->has($user, self::CREATE_INDICATORS_PERMISSION);
    }

    public function canMeasureIndicator(?User $user): bool
    {
        return $this->has($user, self::MEASURE_INDICATORS_PERMISSION);
    }

    public function canCreateAction(?User $user): bool
    {
        return $this->has($user, self::CREATE_ACTIONS_PERMISSION);
    }

    public function canEditAction(?User $user): bool
    {
        return $this->has($user, self::EDIT_ACTIONS_PERMISSION);
    }

    public function canCloseAction(?User $user): bool
    {
        return $this->has($user, self::CLOSE_ACTIONS_PERMISSION);
    }

    public function canCreateEvidence(?User $user): bool
    {
        return $this->has($user, self::CREATE_EVIDENCES_PERMISSION);
    }

    public function canReviewEvidence(?User $user): bool
    {
        return $this->has($user, self::REVIEW_EVIDENCES_PERMISSION);
    }

    public function canApproveEvidence(?User $user): bool
    {
        return $this->has($user, self::APPROVE_EVIDENCES_PERMISSION);
    }

    public function canRejectEvidence(?User $user): bool
    {
        return $this->has($user, self::REJECT_EVIDENCES_PERMISSION);
    }

    public function canCreateMilestone(?User $user): bool
    {
        return $this->has($user, self::CREATE_MILESTONES_PERMISSION);
    }

    public function canRegisterMonitoring(?User $user): bool
    {
        return $this->has($user, self::REGISTER_MONITORING_PERMISSION);
    }

    public function canViewReports(?User $user): bool
    {
        return $this->has($user, self::VIEW_REPORTS_PERMISSION);
    }

    public function canExportReports(?User $user): bool
    {
        return $this->has($user, self::EXPORT_REPORTS_PERMISSION);
    }

    public function canManageConfiguration(?User $user): bool
    {
        return $this->has($user, self::MANAGE_CONFIGURATION_PERMISSION);
    }

    private function has(?User $user, string $permission): bool
    {
        return (bool) $user?->hasPermission($permission);
    }
}
