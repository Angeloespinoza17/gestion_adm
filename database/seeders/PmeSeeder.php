<?php

namespace Database\Seeders;

use Database\Seeders\Support\PreventsProductionSeeding;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\Pme\PmeAction;
use App\Models\Pme\PmeActivity;
use App\Models\Pme\PmeAlert;
use App\Models\Pme\PmeCycle;
use App\Models\Pme\PmeDimension;
use App\Models\Pme\PmeEvidence;
use App\Models\Pme\PmeGeneratedReport;
use App\Models\Pme\PmeIndicator;
use App\Models\Pme\PmeIndicatorMeasurement;
use App\Models\Pme\PmeMilestone;
use App\Models\Pme\PmeObjective;
use App\Models\Pme\PmePlan;
use App\Models\Pme\PmeReflectiveMonitoring;
use App\Models\Pme\PmeSepIncome;
use App\Models\Pme\PmeStrategicGoalMeasurement;
use App\Models\Pme\PmeStrategy;
use App\Models\Pme\PmeStudentSepClassification;
use App\Services\Pme\PmeAccessService;
use Carbon\Carbon;
use Database\Seeders\Modules\StaffModuleSeeder;
use Database\Seeders\Modules\StudentModuleSeeder;
use Database\Seeders\Support\ModuleSeeder;
use Faker\Factory as Faker;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PmeSeeder extends ModuleSeeder
{
    use PreventsProductionSeeding;

    private \Faker\Generator $faker;

    private User $actor;

    public function run(): void
    {
        $this->preventProductionSeeding();
        $this->faker = Faker::create('es_CL');
        $this->faker->seed(20260629);

        $this->call([
            RbacSeeder::class,
            ChileLocationSeeder::class,
            SchoolDepartmentSeeder::class,
            StudentModuleSeeder::class,
            StaffModuleSeeder::class,
            PmeModuleSeeder::class,
        ]);

        $this->actor = $this->creator();

        DB::transaction(function () {
            $this->purgeModuleData();

            $plans = $this->seedPlans();
            $dimensions = $this->seedDimensions();
            $this->seedCycles($plans);

            $objectives = $this->seedObjectives($plans, $dimensions);
            $strategies = $this->seedStrategies($objectives);
            $indicators = $this->seedIndicators($objectives, $strategies);
            $actions = $this->seedActions($plans, $dimensions, $objectives, $strategies, $indicators);

            $activities = $this->seedActivities($actions);
            $milestones = $this->seedMilestones($actions);
            $goalMeasurements = $this->seedGoalMeasurements($objectives);
            $indicatorMeasurements = $this->seedIndicatorMeasurements($indicators);
            $monitorings = $this->seedMonitorings($plans, $dimensions, $objectives, $strategies, $actions);
            $this->seedEvidences($actions, $activities, $milestones, $indicatorMeasurements, $goalMeasurements, $monitorings);

            $this->seedIncomes($plans);
            $this->seedStudentClassifications();
            $this->seedReports($plans);
            $this->seedAlerts($plans, $actions, $indicators, $milestones);
        });
    }

    private function purgeModuleData(): void
    {
        PmeEvidence::query()->forceDelete();
        PmeReflectiveMonitoring::query()->forceDelete();
        PmeStrategicGoalMeasurement::query()->forceDelete();
        PmeMilestone::query()->forceDelete();
        PmeActivity::query()->forceDelete();
        DB::table('pme_action_indicator')->delete();
        PmeAction::query()->forceDelete();
        PmeIndicatorMeasurement::query()->delete();
        PmeIndicator::query()->forceDelete();
        PmeStrategy::query()->forceDelete();
        PmeObjective::query()->forceDelete();
        PmeCycle::query()->delete();
        PmeSepIncome::query()->forceDelete();
        PmeStudentSepClassification::query()->forceDelete();
        PmeGeneratedReport::query()->delete();
        PmeAlert::query()->delete();
        DB::table('pme_historial_cambios')->delete();
        PmeDimension::query()->forceDelete();
        PmePlan::query()->forceDelete();
    }

    /**
     * @return EloquentCollection<int, PmePlan>
     */
    private function seedPlans(): EloquentCollection
    {
        $years = AcademicYear::query()->ordered()->take(3)->get();
        $activeYear = $years->firstWhere('is_active', true) ?? $years->first();
        $historicalYears = $years->where('id', '!=', $activeYear?->id)->values();
        $responsibles = $this->responsibleUsers();

        $plans = collect([
            [
                'academic_year_id' => $activeYear?->id,
                'school_year' => (int) ($activeYear?->year ?? now()->year),
                'name' => 'PME Integral 2026',
                'period_label' => 'Marzo a diciembre',
                'cycle_name' => 'Planificación e implementación',
                'start_date' => ($activeYear?->starts_at?->format('Y-m-d')) ?? now()->startOfYear()->format('Y-m-d'),
                'end_date' => ($activeYear?->ends_at?->format('Y-m-d')) ?? now()->endOfYear()->format('Y-m-d'),
                'responsible_user_id' => $responsibles->first()?->id,
                'state' => 'en_ejecucion',
                'is_active' => true,
                'general_description' => 'Plan de Mejoramiento Educativo con foco en aprendizaje, convivencia, liderazgo y uso eficiente de recursos SEP.',
                'observations' => 'Plan activo utilizado para validación del módulo PME / SEP.',
            ],
            [
                'academic_year_id' => $historicalYears->get(0)?->id,
                'school_year' => (int) ($historicalYears->get(0)?->year ?? now()->subYear()->year),
                'name' => 'PME Integral 2025',
                'period_label' => 'Marzo a diciembre',
                'cycle_name' => 'Evaluación y cierre',
                'start_date' => optional($historicalYears->get(0)?->starts_at)->format('Y-m-d') ?? now()->subYear()->startOfYear()->format('Y-m-d'),
                'end_date' => optional($historicalYears->get(0)?->ends_at)->format('Y-m-d') ?? now()->subYear()->endOfYear()->format('Y-m-d'),
                'responsible_user_id' => $responsibles->get(1)?->id ?? $responsibles->first()?->id,
                'state' => 'cerrado',
                'is_active' => false,
                'general_description' => 'Plan histórico para análisis de continuidad anual.',
                'observations' => 'Cierre con cumplimiento sobre el 84%.',
            ],
            [
                'academic_year_id' => $historicalYears->get(1)?->id,
                'school_year' => (int) ($historicalYears->get(1)?->year ?? now()->subYears(2)->year),
                'name' => 'PME Integral 2024',
                'period_label' => 'Marzo a diciembre',
                'cycle_name' => 'Archivado',
                'start_date' => optional($historicalYears->get(1)?->starts_at)->format('Y-m-d') ?? now()->subYears(2)->startOfYear()->format('Y-m-d'),
                'end_date' => optional($historicalYears->get(1)?->ends_at)->format('Y-m-d') ?? now()->subYears(2)->endOfYear()->format('Y-m-d'),
                'responsible_user_id' => $responsibles->get(2)?->id ?? $responsibles->first()?->id,
                'state' => 'archivado',
                'is_active' => false,
                'general_description' => 'Plan histórico archivado para trazabilidad institucional.',
                'observations' => 'Incluye acciones SEP del ciclo anterior.',
                'archived_at' => now()->subYear(),
            ],
        ]);

        $plans->each(fn (array $plan) => PmePlan::query()->create(array_merge($plan, [
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
        ])));

        return PmePlan::query()->orderByDesc('school_year')->get();
    }

    /**
     * @return EloquentCollection<int, PmeDimension>
     */
    private function seedDimensions(): EloquentCollection
    {
        $definitions = [
            ['name' => 'Gestión Pedagógica', 'description' => 'Fortalecimiento curricular, evaluación y prácticas de aula.', 'sort_order' => 1],
            ['name' => 'Liderazgo', 'description' => 'Conducción institucional, seguimiento y decisiones de mejora.', 'sort_order' => 2],
            ['name' => 'Convivencia Escolar', 'description' => 'Clima escolar, participación y bienestar socioemocional.', 'sort_order' => 3],
            ['name' => 'Gestión de Recursos', 'description' => 'Uso eficiente de recursos SEP, infraestructura y soportes.', 'sort_order' => 4],
        ];

        foreach ($definitions as $definition) {
            PmeDimension::query()->create(array_merge($definition, [
                'active' => true,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]));
        }

        return PmeDimension::query()->orderBy('sort_order')->get();
    }

    private function seedCycles(EloquentCollection $plans): void
    {
        foreach ($plans as $plan) {
            foreach (PmePlan::CYCLE_OPTIONS as $index => $cycle) {
                PmeCycle::query()->create([
                    'pme_plan_id' => $plan->id,
                    'name' => $cycle,
                    'sort_order' => $index + 1,
                    'state' => $plan->is_active
                        ? ($cycle === 'implementacion' ? 'vigente' : ($index < 2 ? 'cerrado' : 'pendiente'))
                        : 'cerrado',
                    'is_current' => $plan->is_active && $cycle === 'implementacion',
                    'start_date' => Carbon::parse($plan->start_date)->addDays($index * 20),
                    'end_date' => Carbon::parse($plan->start_date)->addDays(($index * 20) + 18),
                    'closed_at' => $plan->is_active && $cycle !== 'implementacion' ? now()->subDays(30 - ($index * 2)) : (!$plan->is_active ? now()->subMonths(4) : null),
                    'observations' => 'Ciclo PME registrado para trazabilidad del plan.',
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ]);
            }
        }
    }

    /**
     * @return EloquentCollection<int, PmeObjective>
     */
    private function seedObjectives(EloquentCollection $plans, EloquentCollection $dimensions): EloquentCollection
    {
        $responsibles = $this->responsibleUsers()->values();
        $records = new EloquentCollection();
        $activePlan = $plans->firstWhere('is_active', true) ?? $plans->first();

        foreach (range(1, 12) as $index) {
            $dimension = $dimensions[($index - 1) % max(1, $dimensions->count())];
            $plan = $index <= 8 ? $activePlan : $plans[($index - 1) % max(1, $plans->count())];
            $records->push(PmeObjective::query()->create([
                'pme_plan_id' => $plan->id,
                'pme_dimension_id' => $dimension->id,
                'name' => sprintf('Objetivo estratégico %02d', $index),
                'description' => $this->faker->sentence(14),
                'strategic_goal' => $this->faker->sentence(12),
                'global_indicator' => 'Cumplimiento de meta institucional',
                'responsible_user_id' => $responsibles[($index - 1) % max(1, $responsibles->count())]?->id,
                'start_date' => Carbon::parse($plan->start_date)->addDays($index),
                'end_date' => Carbon::parse($plan->end_date)->subDays(max(5, 45 - $index)),
                'state' => $plan->is_active ? ['vigente', 'en_ejecucion', 'en_monitoreo'][($index - 1) % 3] : 'cerrado',
                'progress_percentage' => $plan->is_active ? $this->faker->numberBetween(25, 88) : $this->faker->numberBetween(75, 100),
                'observations' => 'Objetivo vinculado al plan PME institucional.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]));
        }

        return $records;
    }

    /**
     * @param  EloquentCollection<int, PmeObjective>  $objectives
     * @return EloquentCollection<int, PmeStrategy>
     */
    private function seedStrategies(EloquentCollection $objectives): EloquentCollection
    {
        $responsibles = $this->responsibleUsers()->values();
        $records = new EloquentCollection();

        foreach (range(1, 20) as $index) {
            $objective = $objectives[($index - 1) % max(1, $objectives->count())];
            $records->push(PmeStrategy::query()->create([
                'pme_objective_id' => $objective->id,
                'name' => sprintf('Estrategia %02d', $index),
                'description' => $this->faker->sentence(16),
                'responsible_user_id' => $responsibles[($index - 1) % max(1, $responsibles->count())]?->id,
                'execution_period' => 'Marzo - Diciembre',
                'state' => ['planificada', 'en_ejecucion', 'en_monitoreo', 'finalizada'][($index - 1) % 4],
                'progress_percentage' => $this->faker->numberBetween(20, 95),
                'observations' => 'Estrategia alineada con foco SEP y acompañamiento institucional.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]));
        }

        return $records;
    }

    /**
     * @param  EloquentCollection<int, PmeObjective>  $objectives
     * @param  EloquentCollection<int, PmeStrategy>  $strategies
     * @return EloquentCollection<int, PmeIndicator>
     */
    private function seedIndicators(EloquentCollection $objectives, EloquentCollection $strategies): EloquentCollection
    {
        $responsibles = $this->responsibleUsers()->values();
        $types = ['resultado', 'proceso', 'gestion', 'cobertura', 'participacion', 'asistencia', 'aprendizaje', 'convivencia'];
        $states = ['sin_medicion', 'en_avance', 'cumplido', 'parcialmente_cumplido', 'no_cumplido', 'critico'];
        $frequencies = ['mensual', 'bimensual', 'trimestral', 'semestral', 'anual'];
        $records = new EloquentCollection();

        foreach (range(1, 40) as $index) {
            $objective = $objectives[($index - 1) % max(1, $objectives->count())];
            $strategy = $strategies[($index - 1) % max(1, $strategies->count())];
            $baseline = $this->faker->numberBetween(20, 70);
            $target = $baseline + $this->faker->numberBetween(10, 30);
            $current = $this->faker->numberBetween(max(0, $baseline - 5), $target + 10);

            $records->push(PmeIndicator::query()->create([
                'pme_objective_id' => $objective->id,
                'pme_strategy_id' => $strategy->id,
                'name' => sprintf('Indicador %02d', $index),
                'description' => $this->faker->sentence(14),
                'indicator_type' => $types[($index - 1) % count($types)],
                'baseline_value' => $baseline,
                'target_value' => $target,
                'current_value' => $current,
                'measurement_unit' => '%',
                'verification_source' => 'Actas, registros internos y evaluación institucional',
                'measurement_frequency' => $frequencies[($index - 1) % count($frequencies)],
                'responsible_user_id' => $responsibles[($index - 1) % max(1, $responsibles->count())]?->id,
                'state' => $states[($index - 1) % count($states)],
                'compliance_percentage' => $target > 0 ? round(min(100, ($current / $target) * 100), 2) : 0,
                'observations' => 'Indicador trazable para control PME y seguimiento SEP.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]));
        }

        return $records;
    }

    /**
     * @param  EloquentCollection<int, PmePlan>  $plans
     * @param  EloquentCollection<int, PmeDimension>  $dimensions
     * @param  EloquentCollection<int, PmeObjective>  $objectives
     * @param  EloquentCollection<int, PmeStrategy>  $strategies
     * @param  EloquentCollection<int, PmeIndicator>  $indicators
     * @return EloquentCollection<int, PmeAction>
     */
    private function seedActions(
        EloquentCollection $plans,
        EloquentCollection $dimensions,
        EloquentCollection $objectives,
        EloquentCollection $strategies,
        EloquentCollection $indicators,
    ): EloquentCollection {
        $responsibles = $this->responsibleUsers()->values();
        $areas = ['UTP', 'Dirección', 'Convivencia Escolar', 'PIE', 'Administración', 'Inspectoría'];
        $funding = ['SEP', 'PIE', 'Subvención General', 'Recursos propios', 'Donaciones'];
        $states = ['borrador', 'planificada', 'aprobada', 'en_ejecucion', 'en_monitoreo', 'finalizada', 'atrasada', 'suspendida'];
        $records = new EloquentCollection();
        $activePlan = $plans->firstWhere('is_active', true) ?? $plans->first();

        foreach (range(1, 120) as $index) {
            $plan = $index <= 90 ? $activePlan : $plans[($index - 1) % max(1, $plans->count())];
            $dimension = $dimensions[($index - 1) % max(1, $dimensions->count())];
            $objective = $objectives[($index - 1) % max(1, $objectives->count())];
            $strategy = $strategies[($index - 1) % max(1, $strategies->count())];
            $start = Carbon::parse($plan->start_date)->addDays($this->faker->numberBetween(0, 150));
            $end = (clone $start)->addDays($this->faker->numberBetween(15, 90));
            $state = $states[($index - 1) % count($states)];
            if ($index % 9 === 0) {
                $state = 'atrasada';
                $end = now()->subDays($this->faker->numberBetween(3, 40));
            } elseif ($index % 7 === 0) {
                $state = 'finalizada';
                $end = now()->subDays($this->faker->numberBetween(1, 20));
            }

            $planned = $this->faker->numberBetween(350000, 2500000);
            $committed = (int) round($planned * $this->faker->randomFloat(2, 0.3, 0.95));
            $executed = (int) round($committed * $this->faker->randomFloat(2, 0.15, 1.2));

            $action = PmeAction::query()->create([
                'pme_plan_id' => $plan->id,
                'pme_dimension_id' => $dimension->id,
                'pme_objective_id' => $objective->id,
                'pme_strategy_id' => $strategy->id,
                'name' => sprintf('Acción PME %03d', $index),
                'description' => $this->faker->sentence(18),
                'justification' => $this->faker->sentence(14),
                'responsible_user_id' => $responsibles[($index - 1) % max(1, $responsibles->count())]?->id,
                'responsible_area' => $areas[($index - 1) % count($areas)],
                'start_date' => $start,
                'end_date' => $end,
                'planned_budget' => $planned,
                'committed_budget' => $committed,
                'executed_budget' => $executed,
                'funding_source' => $funding[($index - 1) % count($funding)],
                'cost_center_reference' => 'CC-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'external_accounting_reference' => $index % 4 === 0 ? 'ERP-SEP-' . $index : null,
                'document_reference' => 'PME-' . now()->year . '-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'minimum_evidence_required' => 1,
                'progress_percentage' => $state === 'finalizada' ? 100 : $this->faker->numberBetween(0, 92),
                'last_progress_at' => $this->faker->boolean(75) ? now()->subDays($this->faker->numberBetween(1, 40)) : null,
                'state' => $state,
                'closed_at' => $state === 'finalizada' ? now()->subDays($this->faker->numberBetween(1, 20)) : null,
                'closed_by' => $state === 'finalizada' ? $this->actor->id : null,
                'observations' => $index % 10 === 0 ? 'Acción marcada para seguimiento reforzado por retraso.' : 'Acción con trazabilidad completa para tablero PME.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);

            $action->indicators()->sync([
                $indicators[($index - 1) % max(1, $indicators->count())]->id,
                $indicators[$index % max(1, $indicators->count())]->id,
            ]);

            $records->push($action);
        }

        return $records;
    }

    /**
     * @param  EloquentCollection<int, PmeAction>  $actions
     * @return EloquentCollection<int, PmeActivity>
     */
    private function seedActivities(EloquentCollection $actions): EloquentCollection
    {
        $responsibles = $this->responsibleUsers()->values();
        $states = ['pendiente', 'en_ejecucion', 'realizada', 'atrasada', 'suspendida'];
        $records = new EloquentCollection();

        foreach (range(1, 300) as $index) {
            $action = $actions[($index - 1) % max(1, $actions->count())];
            $scheduled = Carbon::parse($action->start_date ?? now())->addDays($this->faker->numberBetween(0, 45));
            $state = $states[($index - 1) % count($states)];

            $records->push(PmeActivity::query()->create([
                'pme_action_id' => $action->id,
                'name' => sprintf('Actividad %03d', $index),
                'description' => $this->faker->sentence(12),
                'responsible_user_id' => $responsibles[($index - 1) % max(1, $responsibles->count())]?->id,
                'scheduled_date' => $scheduled,
                'completed_date' => $state === 'realizada' ? (clone $scheduled)->addDays($this->faker->numberBetween(0, 5)) : null,
                'state' => $state,
                'observations' => $index % 11 === 0 ? 'Actividad requiere reprogramación.' : null,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]));
        }

        return $records;
    }

    /**
     * @param  EloquentCollection<int, PmeAction>  $actions
     * @return EloquentCollection<int, PmeMilestone>
     */
    private function seedMilestones(EloquentCollection $actions): EloquentCollection
    {
        $responsibles = $this->responsibleUsers()->values();
        $states = ['pendiente', 'en_proceso', 'cumplido', 'atrasado', 'suspendido'];
        $records = new EloquentCollection();

        foreach (range(1, 200) as $index) {
            $action = $actions[($index - 1) % max(1, $actions->count())];
            $planned = Carbon::parse($action->start_date ?? now())->addDays($this->faker->numberBetween(5, 80));
            $state = $states[($index - 1) % count($states)];

            $records->push(PmeMilestone::query()->create([
                'pme_action_id' => $action->id,
                'name' => sprintf('Hito %03d', $index),
                'description' => $this->faker->sentence(10),
                'planned_date' => $planned,
                'actual_completion_date' => $state === 'cumplido' ? (clone $planned)->subDays($this->faker->numberBetween(0, 2)) : null,
                'responsible_user_id' => $responsibles[($index - 1) % max(1, $responsibles->count())]?->id,
                'progress_percentage' => $state === 'cumplido' ? 100 : $this->faker->numberBetween(0, 80),
                'state' => $state,
                'observations' => $state === 'atrasado' ? 'Hito vencido para validación de alertas.' : null,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]));
        }

        return $records;
    }

    /**
     * @param  EloquentCollection<int, PmeObjective>  $objectives
     * @return EloquentCollection<int, PmeStrategicGoalMeasurement>
     */
    private function seedGoalMeasurements(EloquentCollection $objectives): EloquentCollection
    {
        $responsibles = $this->responsibleUsers()->values();
        $states = ['sin_medicion', 'en_avance', 'cumplida', 'parcialmente_cumplida', 'no_cumplida', 'critica'];
        $records = new EloquentCollection();

        foreach ($objectives as $index => $objective) {
            $baseline = $this->faker->numberBetween(20, 65);
            $expected = $baseline + $this->faker->numberBetween(15, 30);
            $current = $this->faker->numberBetween(max(0, $baseline - 5), $expected + 5);
            $records->push(PmeStrategicGoalMeasurement::query()->create([
                'pme_objective_id' => $objective->id,
                'goal_label' => 'Meta asociada al objetivo ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'baseline_value' => $baseline,
                'expected_result' => $expected,
                'current_result' => $current,
                'compliance_percentage' => $expected > 0 ? round(min(100, ($current / $expected) * 100), 2) : 0,
                'information_source' => 'Acta de consejo, evaluación interna y tablero de gestión',
                'measured_at' => now()->subDays($this->faker->numberBetween(10, 120)),
                'responsible_user_id' => $responsibles[$index % max(1, $responsibles->count())]?->id,
                'analysis' => $this->faker->sentence(12),
                'state' => $states[$index % count($states)],
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]));
        }

        return $records;
    }

    /**
     * @param  EloquentCollection<int, PmeIndicator>  $indicators
     * @return EloquentCollection<int, PmeIndicatorMeasurement>
     */
    private function seedIndicatorMeasurements(EloquentCollection $indicators): EloquentCollection
    {
        $records = new EloquentCollection();

        foreach ($indicators as $indicator) {
            foreach (range(1, 2) as $iteration) {
                $measured = $this->faker->numberBetween(20, 100);
                $records->push(PmeIndicatorMeasurement::query()->create([
                    'pme_indicator_id' => $indicator->id,
                    'measured_at' => now()->subDays(($iteration * 30) + $this->faker->numberBetween(0, 10)),
                    'measured_value' => $measured,
                    'compliance_percentage' => $indicator->target_value > 0
                        ? round(min(100, ($measured / $indicator->target_value) * 100), 2)
                        : 0,
                    'state' => $measured >= ($indicator->target_value ?? 0)
                        ? 'cumplido'
                        : ($measured >= (($indicator->target_value ?? 0) * 0.7) ? 'en_avance' : 'critico'),
                    'information_source' => 'Consolidado de monitoreo PME',
                    'analysis' => $this->faker->sentence(10),
                    'observations' => $iteration === 2 ? 'Medición reciente para tablero.' : null,
                    'responsible_user_id' => $indicator->responsible_user_id,
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ]));
            }
        }

        return $records;
    }

    /**
     * @param  EloquentCollection<int, PmePlan>  $plans
     * @param  EloquentCollection<int, PmeDimension>  $dimensions
     * @param  EloquentCollection<int, PmeObjective>  $objectives
     * @param  EloquentCollection<int, PmeStrategy>  $strategies
     * @param  EloquentCollection<int, PmeAction>  $actions
     * @return EloquentCollection<int, PmeReflectiveMonitoring>
     */
    private function seedMonitorings(
        EloquentCollection $plans,
        EloquentCollection $dimensions,
        EloquentCollection $objectives,
        EloquentCollection $strategies,
        EloquentCollection $actions,
    ): EloquentCollection {
        $responsibles = $this->responsibleUsers()->values();
        $states = ['borrador', 'registrado', 'revisado', 'con_ajustes_pendientes', 'cerrado'];
        $activePlan = $plans->firstWhere('is_active', true) ?? $plans->first();
        $records = new EloquentCollection();

        foreach (range(1, 24) as $index) {
            $records->push(PmeReflectiveMonitoring::query()->create([
                'pme_plan_id' => $activePlan->id,
                'pme_dimension_id' => $dimensions[($index - 1) % max(1, $dimensions->count())]->id,
                'pme_objective_id' => $objectives[($index - 1) % max(1, $objectives->count())]->id,
                'pme_strategy_id' => $strategies[($index - 1) % max(1, $strategies->count())]->id,
                'pme_action_id' => $actions[($index - 1) % max(1, $actions->count())]->id,
                'monitored_at' => now()->subDays($this->faker->numberBetween(1, 120)),
                'responsible_user_id' => $responsibles[($index - 1) % max(1, $responsibles->count())]?->id,
                'guiding_questions' => [
                    '¿La acción se está ejecutando según lo planificado?',
                    '¿Qué evidencias respaldan el avance?',
                    '¿Qué ajustes se requieren?',
                ],
                'observed_progress' => $this->faker->sentence(14),
                'difficulties' => $this->faker->sentence(12),
                'reviewed_evidences' => $this->faker->sentence(10),
                'decisions_taken' => $this->faker->sentence(10),
                'required_adjustments' => $this->faker->sentence(9),
                'next_steps' => $this->faker->sentence(9),
                'state' => $states[($index - 1) % count($states)],
                'observations' => $index % 5 === 0 ? 'Monitoreo pendiente de verificación final.' : null,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]));
        }

        return $records;
    }

    private function seedEvidences(
        EloquentCollection $actions,
        EloquentCollection $activities,
        EloquentCollection $milestones,
        EloquentCollection $indicatorMeasurements,
        EloquentCollection $goalMeasurements,
        EloquentCollection $monitorings,
    ): void {
        $types = ['fotografia', 'acta', 'lista_asistencia', 'informe', 'factura', 'planificacion', 'evaluacion', 'captura_pantalla'];
        $states = ['cargada', 'en_revision', 'aprobada', 'observada', 'rechazada'];
        $reviewers = $this->responsibleUsers()->values();

        foreach (range(1, 180) as $index) {
            $action = $actions[($index - 1) % max(1, $actions->count())];
            $status = $states[($index - 1) % count($states)];
            $path = $this->storeDemoFile(
                sprintf('pme-sep/demo/evidencia-%03d.txt', $index),
                "Evidencia de prueba PME/SEP {$index}\nAcción: {$action->name}\n"
            );

            PmeEvidence::query()->create([
                'pme_action_id' => $action->id,
                'pme_activity_id' => $index % 2 === 0 ? $activities[($index - 1) % max(1, $activities->count())]->id : null,
                'pme_milestone_id' => $index % 3 === 0 ? $milestones[($index - 1) % max(1, $milestones->count())]->id : null,
                'pme_indicator_measurement_id' => $index % 5 === 0 ? $indicatorMeasurements[($index - 1) % max(1, $indicatorMeasurements->count())]->id : null,
                'pme_goal_measurement_id' => $index % 7 === 0 ? $goalMeasurements[($index - 1) % max(1, $goalMeasurements->count())]->id : null,
                'pme_reflective_monitoring_id' => $index % 11 === 0 ? $monitorings[($index - 1) % max(1, $monitorings->count())]->id : null,
                'evidence_type' => $types[($index - 1) % count($types)],
                'name' => 'Evidencia PME ' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'description' => $this->faker->sentence(10),
                'uploaded_at' => now()->subDays($this->faker->numberBetween(1, 90)),
                'uploaded_by' => $action->responsible_user_id,
                'file_path' => $path,
                'original_name' => basename($path),
                'mime_type' => 'text/plain',
                'file_size' => Storage::disk('public')->size($path),
                'review_status' => $status,
                'reviewed_at' => in_array($status, ['aprobada', 'observada', 'rechazada'], true) ? now()->subDays($this->faker->numberBetween(1, 20)) : null,
                'reviewed_by' => in_array($status, ['aprobada', 'observada', 'rechazada'], true) ? $reviewers[($index - 1) % max(1, $reviewers->count())]?->id : null,
                'observations' => $status === 'observada' ? 'Se solicita complementar respaldo.' : null,
                'review_comments' => $status === 'rechazada' ? 'Archivo no corresponde al hito informado.' : null,
            ]);
        }
    }

    /**
     * @param  EloquentCollection<int, PmePlan>  $plans
     */
    private function seedIncomes(EloquentCollection $plans): void
    {
        $activePlan = $plans->firstWhere('is_active', true) ?? $plans->first();
        $year = (int) $activePlan->school_year;
        $types = ['sep_regular', 'sep_preferente', 'ajuste', 'reintegro', 'saldo_ano_anterior'];
        $states = ['registrado', 'en_revision', 'confirmado', 'observado'];

        foreach (range(1, 12) as $month) {
            $path = $month % 4 === 0
                ? null
                : $this->storeDemoFile(
                    sprintf('pme-sep/demo/ingreso-sep-%02d.txt', $month),
                    "Respaldo ingreso SEP mes {$month}/{$year}\n"
                );

            PmeSepIncome::query()->create([
                'pme_plan_id' => $activePlan->id,
                'school_year' => $year,
                'month' => $month,
                'income_type' => $types[($month - 1) % count($types)],
                'estimated_amount' => 4200000 + ($month * 150000),
                'received_amount' => 4000000 + ($month * 130000),
                'received_at' => Carbon::create($year, $month, min(25, now()->day)),
                'bank_account' => 'Cuenta corriente institucional terminada en ' . str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                'supporting_document_path' => $path,
                'supporting_document_name' => $path ? basename($path) : null,
                'observations' => $path ? 'Ingreso con respaldo adjunto.' : 'Ingreso sin respaldo para validación de alertas.',
                'state' => $states[($month - 1) % count($states)],
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);
        }
    }

    private function seedStudentClassifications(): void
    {
        $activeYear = $this->activeAcademicYear();
        $enrollments = StudentEnrollment::query()
            ->with(['studentProfile', 'courseSection'])
            ->where('academic_year_id', $activeYear->id)
            ->limit(60)
            ->get();

        foreach ($enrollments as $index => $enrollment) {
            $classification = match (true) {
                $index % 6 === 0 => 'pendiente_validacion',
                $index % 3 === 0 => 'preferente',
                default => 'prioritaria',
            };

            $path = $index % 8 === 0
                ? null
                : $this->storeDemoFile(
                    sprintf('pme-sep/demo/estudiante-sep-%03d.txt', $index + 1),
                    "Clasificación SEP estudiante {$enrollment->studentProfile?->full_name}\n"
                );

            PmeStudentSepClassification::query()->create([
                'student_profile_id' => $enrollment->student_profile_id,
                'course_section_id' => $enrollment->course_section_id,
                'academic_year_id' => $activeYear->id,
                'classification' => $classification,
                'loaded_at' => now()->subDays($this->faker->numberBetween(1, 60)),
                'source' => $index % 2 === 0 ? 'Carga ministerial' : 'Actualización manual',
                'supporting_document_path' => $path,
                'supporting_document_name' => $path ? basename($path) : null,
                'state' => $classification === 'pendiente_validacion'
                    ? 'pendiente'
                    : ($index % 5 === 0 ? 'observado' : 'vigente'),
                'observations' => $classification === 'pendiente_validacion' ? 'Pendiente de confirmar resolución ministerial.' : null,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);
        }
    }

    /**
     * @param  EloquentCollection<int, PmePlan>  $plans
     */
    private function seedReports(EloquentCollection $plans): void
    {
        $types = [
            'general',
            'dimension',
            'objetivo',
            'estrategia',
            'indicador',
            'accion',
            'responsable',
            'evidencias',
            'presupuesto',
            'ingresos_sep',
        ];

        foreach ($types as $index => $type) {
            PmeGeneratedReport::query()->create([
                'pme_plan_id' => $plans->first()?->id,
                'report_type' => $type,
                'title' => 'Reporte PME ' . Str::title(str_replace('_', ' ', $type)),
                'filters' => [
                    'school_year' => $plans->first()?->school_year,
                    'state' => 'todos',
                ],
                'summary' => [
                    'generated_for_demo' => true,
                    'items' => $this->faker->numberBetween(8, 50),
                ],
                'format' => $index % 2 === 0 ? 'pdf' : 'excel',
                'rows_count' => $this->faker->numberBetween(8, 60),
                'state' => 'generado',
                'generated_by' => $this->actor->id,
                'generated_at' => now()->subDays($this->faker->numberBetween(1, 45)),
                'observations' => 'Reporte de prueba para validación del módulo.',
            ]);
        }
    }

    /**
     * @param  EloquentCollection<int, PmePlan>  $plans
     * @param  EloquentCollection<int, PmeAction>  $actions
     * @param  EloquentCollection<int, PmeIndicator>  $indicators
     * @param  EloquentCollection<int, PmeMilestone>  $milestones
     */
    private function seedAlerts(
        EloquentCollection $plans,
        EloquentCollection $actions,
        EloquentCollection $indicators,
        EloquentCollection $milestones,
    ): void {
        $plan = $plans->firstWhere('is_active', true) ?? $plans->first();
        $definitions = [
            ['type' => 'acciones_atrasadas', 'severity' => 'alta', 'title' => 'Acciones atrasadas', 'message' => 'Existen acciones con fecha de término vencida y sin cierre.', 'related' => $actions->firstWhere('state', 'atrasada')],
            ['type' => 'acciones_sin_evidencia', 'severity' => 'media', 'title' => 'Acciones sin evidencia', 'message' => 'Hay acciones en ejecución sin respaldo cargado.', 'related' => $actions->first()],
            ['type' => 'evidencias_pendientes', 'severity' => 'media', 'title' => 'Evidencias pendientes de revisión', 'message' => 'Se requiere revisión de evidencias cargadas recientemente.', 'related' => null],
            ['type' => 'indicadores_criticos', 'severity' => 'alta', 'title' => 'Indicadores críticos', 'message' => 'Uno o más indicadores están por debajo de la meta esperada.', 'related' => $indicators->firstWhere('state', 'critico')],
            ['type' => 'hitos_vencidos', 'severity' => 'alta', 'title' => 'Hitos vencidos', 'message' => 'Existen hitos con fecha planificada vencida.', 'related' => $milestones->firstWhere('state', 'atrasado')],
            ['type' => 'ingresos_sin_respaldo', 'severity' => 'media', 'title' => 'Ingresos SEP sin respaldo', 'message' => 'Se detectaron ingresos SEP sin documento adjunto.', 'related' => null],
        ];

        foreach ($definitions as $definition) {
            PmeAlert::query()->create([
                'pme_plan_id' => $plan->id,
                'alert_type' => $definition['type'],
                'severity' => $definition['severity'],
                'title' => $definition['title'],
                'message' => $definition['message'],
                'related_type' => $definition['related'] ? $definition['related']::class : null,
                'related_id' => $definition['related']?->id,
                'due_date' => now()->addDays($this->faker->numberBetween(2, 15)),
                'state' => 'pendiente',
                'payload' => ['demo' => true],
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);
        }
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function responsibleUsers(): EloquentCollection
    {
        $users = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', [
                'direccion',
                'administrador',
                'coordinador_academico',
                'coordinador_pie',
                'convivencia_escolar',
                'rrhh',
                'inspectoria',
            ]))
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return $users->isNotEmpty() ? $users : User::query()->where('active', true)->orderBy('name')->get();
    }

    private function storeDemoFile(string $path, string $contents): string
    {
        Storage::disk('public')->put($path, $contents);

        return $path;
    }
}
