<?php

namespace App\Console\Commands;

use App\Models\Orientation\OrientationPlan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class PreloadOrientationAnnualPlan extends Command
{
    protected $signature = 'orientation:preload-annual-plan
        {--year=2026 : Año calendario del plan}
        {--actor-email= : Cuenta responsable de la precarga}
        {--apply : Persiste la precarga; sin esta opción solo informa}
        {--confirm= : Debe ser PRECARGAR-PLAN-ORIENTACION al aplicar}
        {--backup-verified= : En producción debe ser RESPALDO-VERIFICADO}';

    protected $description = 'Precarga idempotentemente la matriz del Plan Anual de Orientación 2026 sin sobrescribir acciones existentes.';

    private const RELATED_PLAN_NAME = 'Plan de Afectividad, Sexualidad y Género';

    private const RELATED_ACTIONS = [
        'Plan de Afectividad, Sexualidad y Género',
        'Talleres focalizados: salud menstrual y menarquia',
        'Feria de la Salud',
    ];

    public function handle(): int
    {
        try {
            $this->ensureSchema();
            $year = (int) $this->option('year');
            if ($year < 2000 || $year > 2100) {
                throw new RuntimeException('El año debe estar entre 2000 y 2100.');
            }

            $actions = $this->actions($year);
            $plan = OrientationPlan::query()->where('year', $year)->first();
            $existingTitles = $plan
                ? $plan->actions()->whereIn('title', array_column($actions, 'title'))->pluck('title')->all()
                : [];

            $summary = [
                ['Plan', $plan ? "Existente #{$plan->id}" : 'Se creará'],
                ['Acciones declaradas', count($actions)],
                ['Acciones ya existentes', count($existingTitles)],
                ['Acciones por crear', count($actions) - count($existingTitles)],
                ['Plan transversal', self::RELATED_PLAN_NAME],
                ['Modo', $this->option('apply') ? 'Aplicar' : 'Vista previa'],
            ];
            $this->table(['Elemento', 'Resultado'], $summary);

            if (! $this->option('apply')) {
                $this->components->info('Vista previa terminada. No se modificó ningún registro.');

                return self::SUCCESS;
            }

            if ((string) $this->option('confirm') !== 'PRECARGAR-PLAN-ORIENTACION') {
                throw new RuntimeException('Para aplicar usa --confirm=PRECARGAR-PLAN-ORIENTACION.');
            }

            if (app()->environment('production') && (string) $this->option('backup-verified') !== 'RESPALDO-VERIFICADO') {
                throw new RuntimeException('Producción exige confirmar un respaldo verificado con --backup-verified=RESPALDO-VERIFICADO.');
            }

            $actorId = $this->actorId($plan);
            $result = DB::transaction(fn (): array => $this->persist($year, $actions, $actorId));

            $this->components->info(
                "Precarga completada: {$result['created']} acciones creadas, {$result['preserved']} existentes conservadas y {$result['linked']} vínculos transversales nuevos."
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function ensureSchema(): void
    {
        foreach ([
            'orientation_plans',
            'orientation_actions',
            'orientation_related_plans',
            'orientation_action_related_plan',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Falta la tabla {$table}; ejecuta primero las migraciones de Orientación.");
            }
        }
    }

    private function actorId(?OrientationPlan $plan): ?int
    {
        $email = trim((string) $this->option('actor-email'));
        if ($email !== '') {
            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                throw new RuntimeException("No existe una cuenta con correo {$email}.");
            }

            return (int) $user->id;
        }

        return $plan?->created_by ?: $plan?->owner_user_id;
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @return array{created: int, preserved: int, linked: int}
     */
    private function persist(int $year, array $actions, ?int $actorId): array
    {
        $plan = OrientationPlan::query()->firstOrCreate(
            ['year' => $year],
            [
                'title' => "Plan Anual de Orientación {$year}",
                'description' => 'Plan institucional precargado desde el documento anual de Orientación.',
                'status' => 'draft',
                'owner_user_id' => $actorId,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]
        );

        $relatedPlan = $plan->relatedPlans()->firstOrCreate(
            ['name' => self::RELATED_PLAN_NAME],
            [
                'category' => 'institutional',
                'description' => 'Plan transversal consignado en el documento fuente para 1° básico a IV medio.',
                'status' => 'active',
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]
        );

        $created = 0;
        $preserved = 0;
        $linked = 0;

        foreach ($actions as $payload) {
            $action = $plan->actions()->firstOrCreate(
                ['title' => $payload['title']],
                [...$payload, 'created_by' => $actorId, 'updated_by' => $actorId]
            );

            $action->wasRecentlyCreated ? $created++ : $preserved++;

            if (in_array($action->title, self::RELATED_ACTIONS, true) && ! $action->relatedPlans()->whereKey($relatedPlan->id)->exists()) {
                $action->relatedPlans()->attach($relatedPlan->id);
                $linked++;
            }
        }

        return compact('created', 'preserved', 'linked');
    }

    /** @return array<int, array<string, mixed>> */
    private function actions(int $year): array
    {
        $matrix = [
            $this->action('Organización del Programa Formativo', 'Todos los niveles', "Planificaciones anuales\nPlanificación por ejes", 'Orientadora del nivel', 'Computador, programas', $this->period($year, 3, 1, 12, 31)),
            $this->action('Entrega de planificaciones a profesores/as jefes', '1° básico a IV medio', "Acta de reuniones con profesores jefes\nPlanilla de registro y monitoreo", 'Orientadora de nivel', 'Planificaciones', $this->period($year, 3, 1, 12, 31), 'Frecuencia indicada en el documento: comienzo de cada mes.'),
            $this->action('Coordinación de actividades de extensión con instituciones de Educación Superior', 'I a IV medio', "Acta de reuniones\nInforme de acuerdos", 'Orientadora de Enseñanza Media', null, $this->period($year, 3, 1, 12, 31), 'El documento consigna "Marzo a diciembre 2020"; la precarga normaliza el periodo al año del plan.'),
            $this->action('Jornada de formación espiritual en Pelchuquín', 'IV medios', "Actas de reuniones de organización\nFotografías\nNóminas de asistencia", "Orientadora del nivel\nSubdirector del Departamento de Formación", null, $this->period($year, 6, 1, 6, 30)),
            $this->action('Salidas pedagógicas a instituciones de Educación Superior', 'I a IV medio', "Autorizaciones\nListas de asistencia\nFotografías", 'Orientadora', 'Fotocopias', $this->period($year, 6, 1, 6, 30)),
            $this->action('Elaboración de IDPS: primer y segundo semestre', '1° básico a IV medio', 'Informes individuales', "Orientadora\nProfesores/as jefes", 'Lirmi', $this->period($year, 6, 1, 11, 30), 'Hitos consignados en junio y noviembre.'),
            $this->action('Aplicación de test de intereses vocacionales, hábitos de estudio, estrategias de aprendizaje y sociometría', '7° básico a IV medio', 'Informes de resultados', 'Orientadora', 'Según cada institución', $this->period($year, 4, 1, 5, 31)),
            $this->action('Reuniones mensuales con profesores/as jefes', 'Educación Parvularia a IV medio', "Registro y acta de reuniones\nListado de asistencia", "Equipo de Formación y Convivencia Escolar\nProfesores/as jefes/as", "Material de oficina\nInternet\nComputador\nFotocopias", $this->period($year, 3, 1, 12, 31)),
            $this->action('Atenciones individuales y seguimiento de derivaciones', '1° básico a IV medio', "Protocolo de derivación\nRegistro de entrevistas", 'Equipo de Formación y Convivencia Escolar', "Internet\nComputador\nFotocopias", $this->period($year, 3, 1, 12, 31), 'Incluye derivaciones del subdirector de área, atenciones espontáneas y reporte a profesores y familia.'),
            $this->action('Jornadas de Formación Espiritual', 'Todos los niveles', "Informe de jornada\nRegistro de asistencia\nFotografías\nEncuesta de satisfacción", "Equipo de Formación y Convivencia Escolar\nEquipo de Pastoral", 'Internet y aplicaciones para dinámicas participativas', $this->period($year, 3, 1, 12, 31)),
            $this->action('Inscripción PAES', 'IV medios', "Registro DEMRE\nPortal del colegio", "Orientadora\nCoordinador de ciclo", 'Acceso a internet', $this->period($year, 6, 1, 6, 30)),
            $this->action('Inscripción FUAS', 'IV medios', "Registro DEMRE\nPortal del colegio", "Orientadora\nCoordinador de ciclo\nTrabajadora Social", 'Acceso a internet', $this->period($year, 10, 1, 10, 31)),
            $this->action('Charla FUAS para apoderados/as', 'IV medios', 'Registro de asistencia', 'Orientadora - UACH', "Gimnasio\nAcceso a internet\nFormularios impresos", $this->period($year, 10, 1, 10, 31)),
            $this->action('Proceso de Electividad de II Medio', 'II medio', "PPT\nLeccionario\nFotografías", "Orientadora\nCoordinador de ciclo\nPsicopedagoga", "Acceso a internet\nMaterial de escritorio", $this->period($year, 10, 1, 10, 31), 'Considera clase de Orientación y participación en feria.'),
            $this->action('Intervención en aula para temáticas particulares', '1° básico a IV medio', "Acta de reuniones con profesor jefe\nPlanificación especial\nRegistro en libro de clases\nListado de asistencia\nFotografías", 'Equipo de Formación y Convivencia Escolar', 'Acceso a internet', $this->period($year, 4, 1, 12, 31), 'Intervenciones coordinadas con el profesor o profesora jefe.'),
            $this->action('Charlas para apoderados/as y directivas de curso', '1° básico a IV medio', "Asistencia\nRegistro fotográfico", 'Equipo de Formación y Convivencia Escolar', 'Internet', $this->period($year, 3, 1, 12, 31)),
            $this->action('Ciclo de charlas sobre Ley de Responsabilidad Penal Adolescente', '7° básico a III medio', "Listado de asistencia\nRegistro fotográfico", "Orientadora\nCoordinador de ciclo", 'Acceso a internet', $this->period($year, 5, 1, 5, 31)),
            $this->action('Ciclo de charlas sobre uso responsable de redes sociales', '5° a 8° básico', "Listado de asistencia\nRegistro fotográfico", "Orientadora\nCoordinador de ciclo", 'Acceso a internet', $this->period($year, 4, 1, 4, 30)),
            $this->action('Charla Camino a la Educación Superior para apoderados/as', 'IV medios', "Listado de asistencia\nRegistro fotográfico", "Orientadora\nCoordinador de ciclo", 'Acceso a internet', $this->period($year, 4, 1, 5, 31)),
            $this->action('Plan Vocacional de I a IV medio', 'I a IV medio', "Leccionario\nTutorías vocacionales\nActas de reunión de profesores/as jefes", "Equipo de Formación y Convivencia Escolar\nCoordinadora de Enseñanza Media", null, $this->period($year, 4, 1, 11, 30)),
            $this->action('Feria Vocacional', null, 'Registro fotográfico', 'Equipo de Formación y Convivencia Escolar', null, $this->period($year, 6, 1, 6, 30)),
            $this->action('Feria de la Niñez', null, 'Registro fotográfico', "Equipo de Formación y Convivencia Escolar\nCoordinadora de Enseñanza Básica", null, null, 'Periodo indicado en el documento: segundo semestre. Fecha exacta pendiente de confirmar.'),
            $this->action('Plan de Afectividad, Sexualidad y Género', '1° básico a IV medio', "Listado de asistencia\nRegistro fotográfico", "Orientadora\nDupla Psicosocial", null, $this->period($year, 4, 1, 11, 30), 'El documento destaca trabajo focalizado de 3° a 5° básico.'),
            $this->action('Talleres focalizados: salud menstrual y menarquia', '3° a 8° básico', "Listado de asistencia\nLeccionario\nFotografías\nAutorización", "Orientadora\nPsicóloga del nivel", null, null, 'Salud menstrual para 6° a 8° básico y menarquia para 3° a 5° básico. Fecha pendiente de confirmar.'),
            $this->action('Evaluación de unidades de Orientación', '1° básico a IV medio', 'Leccionario', "Orientadora\nProfesores/as jefes\nCoordinadoras de nivel", 'Instrumentos diversos', $this->period($year, 3, 1, 12, 31), 'Frecuencia indicada en el documento: una evaluación semestral.'),
            $this->action('Inducción y talleres para profesores/as jefes', 'Según necesidad', 'Firmas de asistencia', 'Equipo de Formación y Convivencia Escolar', null, $this->period($year, 3, 1, 3, 31)),
        ];

        $ganttNote = 'Acción mencionada únicamente en la Carta Gantt incluida en el PDF, cuyo encabezado indica 2024. Requiere confirmar su programación para 2026.';
        $ganttOnly = [
            $this->action('Jornadas Vocacionales', null, null, null, null, null, $ganttNote.' El documento deja pendiente definir los cursos.'),
            $this->action('Campus Tour UACH', 'III y IV medio', null, null, null, null, $ganttNote),
            $this->action('Apoyo a la planificación de Consejos especiales de profesores', 'Educación Parvularia a IV medio', null, 'Coordinador de ciclo respectivo', null, null, $ganttNote),
            $this->action('Jornadas de Orientación en cursos o niveles requeridos', 'Cursos o niveles requeridos', null, null, null, null, $ganttNote),
            $this->action('Proceso de Electividad de 8° Básico', '8° básico', null, null, null, null, $ganttNote),
            $this->action('Talleres de liderazgo para directivas', '4° básico a IV medio', null, null, null, null, $ganttNote.' Considera directivas de cursos, profesores/as jefes y directivas de apoderados/as.'),
            $this->action('Feria de la Salud', '7° básico a IV medio', null, null, null, null, $ganttNote),
        ];

        $actions = [...$matrix, ...$ganttOnly];
        foreach ($actions as $index => &$action) {
            $action['sort_order'] = $index + 1;
        }
        unset($action);

        return $actions;
    }

    /** @return array<string, mixed> */
    private function action(
        string $title,
        ?string $levels,
        ?string $verification,
        ?string $responsibles,
        ?string $resources,
        ?array $period,
        ?string $description = null,
    ): array {
        return [
            'title' => $title,
            'objective' => null,
            'description' => $description,
            'target_levels' => $levels,
            'planned_verification_means' => $verification,
            'material_resources' => $resources,
            'responsible_summary' => $responsibles,
            'start_date' => $period[0] ?? null,
            'end_date' => $period[1] ?? null,
            'status' => 'planned',
            'progress' => 0,
        ];
    }

    /** @return array{0: string, 1: string} */
    private function period(int $year, int $startMonth, int $startDay, int $endMonth, int $endDay): array
    {
        return [
            sprintf('%04d-%02d-%02d', $year, $startMonth, $startDay),
            sprintf('%04d-%02d-%02d', $year, $endMonth, $endDay),
        ];
    }
}
