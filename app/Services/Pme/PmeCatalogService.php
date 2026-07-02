<?php

namespace App\Services\Pme;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\Pme\PmeAction;
use App\Models\Pme\PmeDimension;
use App\Models\Pme\PmeIndicator;
use App\Models\Pme\PmeObjective;
use App\Models\Pme\PmePlan;
use App\Models\Pme\PmeStrategy;
use Illuminate\Support\Collection;

class PmeCatalogService
{
    public const INCOME_TYPES = ['sep_regular', 'sep_preferente', 'ajuste', 'reintegro', 'saldo_ano_anterior', 'otro'];
    public const INCOME_STATES = ['registrado', 'en_revision', 'confirmado', 'observado', 'anulado'];
    public const STUDENT_CLASSIFICATIONS = ['prioritaria', 'preferente', 'sin_clasificacion_sep', 'pendiente_validacion'];
    public const STUDENT_CLASSIFICATION_STATES = ['vigente', 'no_vigente', 'pendiente', 'observado'];
    public const OBJECTIVE_STATES = ['borrador', 'vigente', 'en_ejecucion', 'en_monitoreo', 'cumplido', 'no_cumplido', 'cerrado'];
    public const STRATEGY_STATES = ['planificada', 'en_ejecucion', 'en_monitoreo', 'finalizada', 'suspendida'];
    public const INDICATOR_TYPES = ['resultado', 'proceso', 'gestion', 'cobertura', 'participacion', 'asistencia', 'aprendizaje', 'convivencia', 'ejecucion_presupuestaria', 'otro'];
    public const INDICATOR_FREQUENCIES = ['mensual', 'bimensual', 'trimestral', 'semestral', 'anual'];
    public const INDICATOR_STATES = ['sin_medicion', 'en_avance', 'cumplido', 'parcialmente_cumplido', 'no_cumplido', 'critico'];
    public const ACTION_FUNDING_SOURCES = ['SEP', 'PIE', 'Subvención General', 'Mantenimiento', 'Recursos propios', 'Donaciones', 'Otro'];
    public const ACTION_STATES = ['borrador', 'planificada', 'aprobada', 'en_ejecucion', 'en_monitoreo', 'finalizada', 'atrasada', 'suspendida', 'cerrada', 'anulada'];
    public const ACTIVITY_STATES = ['pendiente', 'en_ejecucion', 'realizada', 'atrasada', 'suspendida', 'cancelada'];
    public const EVIDENCE_TYPES = ['fotografia', 'acta', 'lista_asistencia', 'informe', 'factura', 'boleta', 'orden_compra', 'cotizacion', 'guia_trabajo', 'planificacion', 'evaluacion', 'certificado', 'captura_pantalla', 'documento_externo', 'otro'];
    public const EVIDENCE_STATES = ['cargada', 'en_revision', 'aprobada', 'observada', 'rechazada'];
    public const MILESTONE_STATES = ['pendiente', 'en_proceso', 'cumplido', 'atrasado', 'suspendido', 'cancelado'];
    public const GOAL_STATES = ['sin_medicion', 'en_avance', 'cumplida', 'parcialmente_cumplida', 'no_cumplida', 'critica'];
    public const MONITORING_STATES = ['borrador', 'registrado', 'revisado', 'con_ajustes_pendientes', 'cerrado'];
    public const REPORT_TYPES = [
        'general',
        'dimension',
        'objetivo',
        'estrategia',
        'indicador',
        'accion',
        'responsable',
        'evidencias',
        'ejecucion_presupuestaria',
        'ingresos_sep',
        'estudiantes_prioritarios',
        'estudiantes_preferentes',
        'cumplimiento_metas',
        'monitoreo_reflexivo',
        'hitos',
        'acciones_atrasadas',
        'acciones_sin_evidencia',
        'presupuesto_planificado_vs_ejecutado',
    ];

    public function __construct(
        private readonly PmeAccessService $accessService,
    ) {
    }

    public function build(User $user): array
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        return [
            'plans' => PmePlan::query()
                ->orderByDesc('school_year')
                ->get(['id', 'school_year', 'name', 'state', 'is_active'])
                ->map(fn (PmePlan $plan) => [
                    'id' => $plan->id,
                    'school_year' => $plan->school_year,
                    'name' => $plan->name,
                    'state' => $plan->state,
                    'is_active' => $plan->is_active,
                ]),
            'active_plan_id' => PmePlan::query()->where('is_active', true)->value('id'),
            'academic_years' => AcademicYear::query()
                ->ordered()
                ->get(['id', 'name', 'year', 'is_active'])
                ->map(fn (AcademicYear $year) => [
                    'id' => $year->id,
                    'name' => $year->name,
                    'year' => $year->year,
                    'is_active' => $year->is_active,
                ]),
            'active_academic_year_id' => $activeYear?->id,
            'dimensions' => PmeDimension::query()
                ->orderBy('sort_order')
                ->get(['id', 'name', 'active', 'sort_order'])
                ->map(fn (PmeDimension $dimension) => [
                    'id' => $dimension->id,
                    'name' => $dimension->name,
                    'active' => $dimension->active,
                    'sort_order' => $dimension->sort_order,
                ]),
            'objectives' => PmeObjective::query()
                ->orderByDesc('id')
                ->get(['id', 'name', 'pme_plan_id', 'pme_dimension_id'])
                ->map(fn (PmeObjective $objective) => [
                    'id' => $objective->id,
                    'name' => $objective->name,
                    'pme_plan_id' => $objective->pme_plan_id,
                    'pme_dimension_id' => $objective->pme_dimension_id,
                ]),
            'strategies' => PmeStrategy::query()
                ->orderByDesc('id')
                ->get(['id', 'name', 'pme_objective_id'])
                ->map(fn (PmeStrategy $strategy) => [
                    'id' => $strategy->id,
                    'name' => $strategy->name,
                    'pme_objective_id' => $strategy->pme_objective_id,
                ]),
            'indicators' => PmeIndicator::query()
                ->orderByDesc('id')
                ->get(['id', 'name', 'pme_objective_id', 'pme_strategy_id'])
                ->map(fn (PmeIndicator $indicator) => [
                    'id' => $indicator->id,
                    'name' => $indicator->name,
                    'pme_objective_id' => $indicator->pme_objective_id,
                    'pme_strategy_id' => $indicator->pme_strategy_id,
                ]),
            'actions' => PmeAction::query()
                ->orderByDesc('id')
                ->get(['id', 'name', 'pme_plan_id'])
                ->map(fn (PmeAction $action) => [
                    'id' => $action->id,
                    'name' => $action->name,
                    'pme_plan_id' => $action->pme_plan_id,
                ]),
            'responsibles' => User::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'staff_id'])
                ->map(fn (User $responsible) => [
                    'id' => $responsible->id,
                    'name' => $responsible->name,
                    'email' => $responsible->email,
                    'staff_id' => $responsible->staff_id,
                ]),
            'courses' => CourseSection::query()
                ->with(['academicYear:id,year', 'educationLevel:id,name'])
                ->orderBy('display_name')
                ->get(['id', 'academic_year_id', 'education_level_id', 'display_name'])
                ->map(fn (CourseSection $course) => [
                    'id' => $course->id,
                    'display_name' => $course->display_name,
                    'academic_year_id' => $course->academic_year_id,
                    'academic_year' => $course->academicYear?->year,
                    'education_level' => $course->educationLevel?->name,
                ]),
            'students' => $this->studentOptions($activeYear?->id),
            'options' => [
                'plan_states' => $this->asOptions(PmePlan::STATE_OPTIONS),
                'cycle_options' => $this->asOptions(PmePlan::CYCLE_OPTIONS),
                'income_types' => $this->asOptions(self::INCOME_TYPES),
                'income_states' => $this->asOptions(self::INCOME_STATES),
                'student_classifications' => $this->asOptions(self::STUDENT_CLASSIFICATIONS),
                'student_classification_states' => $this->asOptions(self::STUDENT_CLASSIFICATION_STATES),
                'objective_states' => $this->asOptions(self::OBJECTIVE_STATES),
                'strategy_states' => $this->asOptions(self::STRATEGY_STATES),
                'indicator_types' => $this->asOptions(self::INDICATOR_TYPES),
                'indicator_frequencies' => $this->asOptions(self::INDICATOR_FREQUENCIES),
                'indicator_states' => $this->asOptions(self::INDICATOR_STATES),
                'action_funding_sources' => $this->asOptions(self::ACTION_FUNDING_SOURCES),
                'action_states' => $this->asOptions(self::ACTION_STATES),
                'activity_states' => $this->asOptions(self::ACTIVITY_STATES),
                'evidence_types' => $this->asOptions(self::EVIDENCE_TYPES),
                'evidence_states' => $this->asOptions(self::EVIDENCE_STATES),
                'milestone_states' => $this->asOptions(self::MILESTONE_STATES),
                'goal_states' => $this->asOptions(self::GOAL_STATES),
                'monitoring_states' => $this->asOptions(self::MONITORING_STATES),
                'report_types' => $this->asOptions(self::REPORT_TYPES),
                'months' => collect(range(1, 12))->map(fn (int $month) => [
                    'value' => $month,
                    'label' => mb_convert_case((string) now()->setMonth($month)->translatedFormat('F'), MB_CASE_TITLE, 'UTF-8'),
                ])->values(),
            ],
            'capabilities' => [
                'can_create_plan' => $this->accessService->canCreatePlan($user),
                'can_edit_plan' => $this->accessService->canEditPlan($user),
                'can_close_plan' => $this->accessService->canClosePlan($user),
                'can_manage_incomes' => $this->accessService->canManageIncomes($user),
                'can_view_student_classifications' => $this->accessService->canViewStudentClassifications($user),
                'can_load_students' => $this->accessService->canLoadStudents($user),
                'can_manage_dimensions' => $this->accessService->canManageDimensions($user),
                'can_create_objective' => $this->accessService->canCreateObjective($user),
                'can_edit_objective' => $this->accessService->canEditObjective($user),
                'can_create_strategy' => $this->accessService->canCreateStrategy($user),
                'can_edit_strategy' => $this->accessService->canEditStrategy($user),
                'can_create_indicator' => $this->accessService->canCreateIndicator($user),
                'can_measure_indicator' => $this->accessService->canMeasureIndicator($user),
                'can_create_action' => $this->accessService->canCreateAction($user),
                'can_edit_action' => $this->accessService->canEditAction($user),
                'can_close_action' => $this->accessService->canCloseAction($user),
                'can_create_evidence' => $this->accessService->canCreateEvidence($user),
                'can_review_evidence' => $this->accessService->canReviewEvidence($user),
                'can_approve_evidence' => $this->accessService->canApproveEvidence($user),
                'can_reject_evidence' => $this->accessService->canRejectEvidence($user),
                'can_create_milestone' => $this->accessService->canCreateMilestone($user),
                'can_register_monitoring' => $this->accessService->canRegisterMonitoring($user),
                'can_view_reports' => $this->accessService->canViewReports($user),
                'can_export_reports' => $this->accessService->canExportReports($user),
                'can_manage_configuration' => $this->accessService->canManageConfiguration($user),
            ],
        ];
    }

    /**
     * @return Collection<int, array{id:int,name:string,course:string|null,academic_year_id:int|null}>
     */
    private function studentOptions(?int $academicYearId): Collection
    {
        return StudentEnrollment::query()
            ->with(['studentProfile:id,first_name,last_name,registered_name', 'courseSection:id,display_name'])
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->limit(300)
            ->get(['id', 'student_profile_id', 'course_section_id', 'academic_year_id'])
            ->map(fn (StudentEnrollment $enrollment) => [
                'id' => $enrollment->student_profile_id,
                'name' => $enrollment->studentProfile?->registered_name_resolved ?? $enrollment->studentProfile?->full_name ?? 'Sin nombre',
                'course' => $enrollment->courseSection?->display_name,
                'academic_year_id' => $enrollment->academic_year_id,
            ])
            ->unique('id')
            ->values();
    }

    /**
     * @param  array<int, string>  $values
     * @return Collection<int, array{value:string,label:string}>
     */
    private function asOptions(array $values): Collection
    {
        return collect($values)->map(fn (string $value) => [
            'value' => $value,
            'label' => str_replace('_', ' ', mb_convert_case($value, MB_CASE_TITLE, 'UTF-8')),
        ])->values();
    }
}
