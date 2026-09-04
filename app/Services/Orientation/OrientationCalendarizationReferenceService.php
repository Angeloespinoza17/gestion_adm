<?php

namespace App\Services\Orientation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class OrientationCalendarizationReferenceService
{
    public const REFERENCE_YEAR = 2026;

    public function layers(): array
    {
        return [
            [
                'key' => 'primary',
                'label' => '1° a 6° básico',
                'short_label' => '1°–6° básico',
                'description' => 'Calendarización general de Orientación para enseñanza básica inicial.',
                'color' => '#5b5bd6',
                'soft_color' => '#eeefff',
                'icon' => 'mdi-school-outline',
            ],
            [
                'key' => 'secondary',
                'label' => '7° básico a II° medio',
                'short_label' => '7°–II° medio',
                'description' => 'Calendarización general de Orientación para el tramo intermedio.',
                'color' => '#008c95',
                'soft_color' => '#e8f8f7',
                'icon' => 'mdi-account-group-outline',
            ],
            [
                'key' => 'third',
                'label' => 'III° medio',
                'short_label' => 'III° medio',
                'description' => 'Planificación de Orientación y acompañamiento de III° medio.',
                'color' => '#d57b20',
                'soft_color' => '#fff4e7',
                'icon' => 'mdi-compass-outline',
            ],
            [
                'key' => 'fourth',
                'label' => 'IV° medio · Plan vocacional',
                'short_label' => 'IV° medio',
                'description' => 'Plan vocacional, proyecto de vida y acompañamiento de IV° medio.',
                'color' => '#b64b7c',
                'soft_color' => '#fcecf4',
                'icon' => 'mdi-map-marker-path',
            ],
        ];
    }

    public function categories(): array
    {
        return [
            ['key' => 'activity', 'label' => 'Actividad formativa'],
            ['key' => 'rice', 'label' => 'RICE y normativa'],
            ['key' => 'coexistence', 'label' => 'Convivencia escolar'],
            ['key' => 'socioemotional', 'label' => 'Bienestar socioemocional'],
            ['key' => 'vocational', 'label' => 'Vocacional y proyecto de vida'],
            ['key' => 'affectivity', 'label' => 'Afectividad y sexualidad'],
            ['key' => 'idps', 'label' => 'IDPS'],
            ['key' => 'evaluation', 'label' => 'Evaluación'],
            ['key' => 'no_planning', 'label' => 'Sin planificación'],
            ['key' => 'vacation', 'label' => 'Receso'],
        ];
    }

    public function entries(int $year): array
    {
        if ($year !== self::REFERENCE_YEAR) {
            return [];
        }

        $entries = [];
        $weekOne = Carbon::create($year, 3, 2)->startOfDay();

        foreach ($this->titles() as $group => $titles) {
            foreach ($titles as $index => $title) {
                $start = $weekOne->copy()->addWeeks($index);
                $entries[] = [
                    'id' => null,
                    'orientation_action_id' => null,
                    'level_group' => $group,
                    'title' => $title,
                    'description' => null,
                    'category' => $this->categoryFor($title),
                    'start_date' => $start->toDateString(),
                    'end_date' => $start->copy()->addDays(4)->toDateString(),
                    'status' => 'planned',
                    'source_key' => sprintf('reference-%d-%s-%02d', $year, $group, $index + 1),
                    'source_label' => $this->sourceLabel($group),
                    'is_reference' => true,
                ];
            }
        }

        return $entries;
    }

    private function sourceLabel(string $group): string
    {
        return match ($group) {
            'primary' => '2026_1° a 6°_CALENDARIZACIÓN GENERAL DE ORIENTACIÓN.docx',
            'secondary' => '2026_7° a II°_CALENDARIZACIÓN GENERAL DE ORIENTACIÓN.docx',
            'third' => '2026_III MEDIOS_PLANIFICACIÓN PLAN VOCACIONAL (Reparado).docx',
            'fourth' => '2026_IV MEDIOS_PLANIFICACIÓN PLAN VOCACIONAL (Reparado).docx',
        };
    }

    private function categoryFor(string $title): string
    {
        $normalized = Str::lower(Str::ascii($title));

        return match (true) {
            Str::contains($normalized, ['vacaciones', 'salida de vacaciones']) => 'vacation',
            Str::contains($normalized, ['sin planificacion']) => 'no_planning',
            Str::contains($normalized, ['afectividad', 'sexualidad']) => 'affectivity',
            Str::contains($normalized, ['idps', 'ipds']) => 'idps',
            Str::contains($normalized, ['rice', 'reglamento de evaluacion']) => 'rice',
            Str::contains($normalized, ['convivencia escolar']) => 'coexistence',
            Str::contains($normalized, ['socioemocional', 'salud mental', 'suicidio', 'bienestar y autocuidado']) => 'socioemotional',
            Str::contains($normalized, ['vocacional', 'proyecto de vida', 'habilidades', 'autoconocimiento', 'habitos de estudio', 'tecnicas de estudio', 'estrategias de aprendizaje', 'estres', 'ansiedad', 'ordenar mis tiempos']) => 'vocational',
            Str::contains($normalized, ['evaluacion', 'prueba intermedia', 'prueba final']) || $normalized === 'dia' => 'evaluation',
            default => 'activity',
        };
    }

    private function titles(): array
    {
        return [
            'primary' => [
                'BIENVENIDAS/OS ESTUDIANTES',
                'DÍA DEL CIBERACOSO',
                'NORMAS DE CORTESÍA / PRESENTACIÓN RICE',
                'RICE / ATRASOS / UNIFORME',
                'RICE / PROTOCOLOS',
                'DIA',
                'SIN PLANIFICACIÓN: PREPARACIÓN DÍA DEL LIBRO',
                'TRABAJO ESCOLAR',
                'TRABAJO ESCOLAR',
                'SENDA',
                'SENDA',
                'SENDA',
                'EVALUACIÓN DE ORIENTACIÓN / SEMANA DE LA SEGURIDAD ESCOLAR',
                'IDPS / DÍA MUNDIAL DEL MEDIO AMBIENTE',
                'IDPS',
                'SALIDA DE VACACIONES DE INVIERNO',
                'VACACIONES DE INVIERNO',
                'VACACIONES DE INVIERNO',
                'VACACIONES DE INVIERNO',
                'BIENVENIDA AL SEGUNDO SEMESTRE',
                'DIA: PRUEBA INTERMEDIA',
                'DIA: PRUEBA INTERMEDIA',
                'DÍA DEL NIÑO Y LA NIÑA (FOCO SIMCE) / DERECHOS',
                'SIN PLANIFICACIÓN POR ANIVERSARIO DEL COLEGIO',
                'SIN PLANIFICACIÓN POR ANIVERSARIO DEL COLEGIO',
                'CRECIMIENTO PERSONAL (FOCO SIMCE)',
                'CRECIMIENTO PERSONAL',
                'CRECIMIENTO PERSONAL / DÍA MUNDIAL DE LA PREVENCIÓN DEL SUICIDIO',
                'SIN PLANIFICACIÓN / SEMANA 18 DE SEPTIEMBRE',
                'RELACIONES INTERPERSONALES (FOCO SIMCE)',
                'RELACIONES INTERPERSONALES',
                'DÍA DE LA SALUD MENTAL (FOCO SIMCE)',
                'RELACIONES INTERPERSONALES / DÍA DEL PROFESOR',
                'PARTICIPACIÓN Y PERTENENCIA (FOCO SIMCE)',
                'PARTICIPACIÓN Y PERTENENCIA',
                'EVALUACIÓN DE ORIENTACIÓN',
                'IDPS',
                'IDPS',
                'DIA: PRUEBA FINAL',
                'DIA: PRUEBA FINAL',
                'EVALUACIÓN DE CIERRE DE SEMESTRE',
                'DESPEDIDA',
            ],
            'secondary' => [
                'BIENVENIDAS/OS ESTUDIANTES / RICE',
                'DÍA DEL CIBERACOSO',
                'RICE',
                'RICE',
                'RICE',
                'DIA SOCIOEMOCIONAL',
                'SIN PLANIFICACIÓN: PREPARACIÓN DÍA DEL LIBRO',
                'DÍA DE LA CONVIVENCIA ESCOLAR (RICE)',
                'TRABAJO ESCOLAR (FOCO SIMCE)',
                'TRABAJO ESCOLAR / DÍA DE LAS Y LOS ESTUDIANTES',
                'SENDA',
                'SENDA',
                'SENDA / SEMANA DE LA SEGURIDAD ESCOLAR (FOCO SIMCE)',
                'IDPS / EVALUACIÓN DE ORIENTACIÓN / DÍA MUNDIAL DEL MEDIO AMBIENTE',
                'IDPS / EVALUACIÓN DE ORIENTACIÓN',
                'SALIDA DE VACACIONES DE INVIERNO',
                'VACACIONES DE INVIERNO',
                'VACACIONES DE INVIERNO',
                'VACACIONES DE INVIERNO',
                'BIENVENIDA AL SEGUNDO SEMESTRE',
                'DIA: PRUEBA INTERMEDIA',
                'DIA: PRUEBA INTERMEDIA',
                'CRECIMIENTO PERSONAL / RELACIONES INTERPERSONALES / DÍA DEL NIÑO Y LA NIÑA (FOCO SIMCE)',
                'SIN PLANIFICACIÓN POR ANIVERSARIO DEL COLEGIO',
                'SIN PLANIFICACIÓN POR ANIVERSARIO DEL COLEGIO',
                'CRECIMIENTO PERSONAL / RELACIONES INTERPERSONALES (FOCO SIMCE)',
                'CRECIMIENTO PERSONAL / RELACIONES INTERPERSONALES',
                'DÍA MUNDIAL DE LA PREVENCIÓN DEL SUICIDIO (FOCO SIMCE)',
                'SIN PLANIFICACIÓN / SEMANA 18 DE SEPTIEMBRE',
                'BIENESTAR Y AUTOCUIDADO / PLAN DE AFECTIVIDAD Y SEXUALIDAD',
                'BIENESTAR Y AUTOCUIDADO / PLAN DE AFECTIVIDAD Y SEXUALIDAD (FOCO SIMCE)',
                'DÍA DE LA SALUD MENTAL',
                'BIENESTAR Y AUTOCUIDADO / PLAN DE AFECTIVIDAD Y SEXUALIDAD / DÍA DEL PROFESOR (FOCO SIMCE)',
                'BIENESTAR Y AUTOCUIDADO / PLAN DE AFECTIVIDAD Y SEXUALIDAD',
                'PERTENENCIA Y PARTICIPACIÓN CIUDADANA (FOCO SIMCE)',
                'EVALUACIÓN DE ORIENTACIÓN',
                'IDPS',
                'IDPS',
                'DIA: PRUEBA FINAL',
                'DIA: PRUEBA FINAL',
                'EVALUACIÓN DE CIERRE DE SEMESTRE',
                'DESPEDIDA',
            ],
            'third' => [
                'BIENVENIDAS/OS ESTUDIANTES / RICE / CONMEMORACIÓN DÍA DE LA MUJER',
                'DÍA DEL CIBERACOSO',
                'RICE',
                'RICE',
                'RICE',
                'DIAGNÓSTICO / CUESTIONARIO SOCIOEMOCIONAL',
                'SIN PLANIFICACIÓN: PREPARACIÓN DÍA DEL LIBRO',
                'SEMANA DEL LIBRO / DÍA DE LA CONVIVENCIA ESCOLAR',
                'GESTIÓN DEL APRENDIZAJE',
                'GESTIÓN DEL APRENDIZAJE',
                'VÍNCULOS Y RELACIONES INTERPERSONALES',
                'VÍNCULOS Y RELACIONES INTERPERSONALES',
                'VÍNCULOS Y RELACIONES INTERPERSONALES',
                'IDPS',
                'IDPS',
                'SALIDA DE VACACIONES DE INVIERNO',
                'VACACIONES DE INVIERNO',
                'VACACIONES DE INVIERNO',
                'VACACIONES DE INVIERNO',
                'BIENVENIDA AL SEGUNDO SEMESTRE / RECTA FINAL',
                'PRUEBA INTERMEDIA SOCIOEMOCIONAL',
                'VÍNCULOS Y RELACIONES INTERPERSONALES',
                'VÍNCULOS Y RELACIONES INTERPERSONALES',
                'SIN PLANIFICACIÓN POR ANIVERSARIO DEL COLEGIO',
                'SIN PLANIFICACIÓN POR ANIVERSARIO DEL COLEGIO',
                'VÍNCULOS Y RELACIONES INTERPERSONALES',
                'VÍNCULOS Y RELACIONES INTERPERSONALES',
                'FACTORES PROTECTORES Y DE RIESGO PERSONALES Y SOCIALES',
                'FACTORES PROTECTORES Y DE RIESGO PERSONALES Y SOCIALES',
                'FACTORES PROTECTORES Y DE RIESGO PERSONALES Y SOCIALES',
                'FACTORES PROTECTORES Y DE RIESGO PERSONALES Y SOCIALES',
                'FACTORES PROTECTORES Y DE RIESGO PERSONALES Y SOCIALES',
                'PREPARACIÓN DESPEDIDA DE IV MEDIOS',
                'PREPARACIÓN DESPEDIDA DE IV MEDIOS',
                'PREPARACIÓN DESPEDIDA DE IV MEDIOS',
                'EVALUACIÓN DE ORIENTACIÓN',
                'IDPS',
                'IDPS',
                'DIA: PRUEBA FINAL',
                'DIA: PRUEBA FINAL',
                'EVALUACIÓN DE ORIENTACIÓN',
                'CIERRE',
            ],
            'fourth' => [
                'BIENVENIDAS/OS ESTUDIANTES / CONMEMORACIÓN DÍA DE LA MUJER',
                'ELECCIÓN DE DIRECTIVAS / DÍA DEL CIBERACOSO',
                'PRESENTACIÓN PLAN VOCACIONAL',
                'TEST DE HABILIDADES USS / MIS HABILIDADES ENCAMINAN MIS DECISIONES',
                'CUESTIONARIO DE AUTOCONOCIMIENTO (INSUMO PARA LAS TUTORÍAS)',
                'REGLAMENTO DE EVALUACIÓN',
                'SIN PLANIFICACIÓN: PREPARACIÓN DÍA DEL LIBRO',
                'SEMANA DEL LIBRO / DÍA DE LA CONVIVENCIA ESCOLAR',
                'MANEJO DEL ESTRÉS O ANSIEDAD / INVENTARIO DE HÁBITOS DE ESTUDIO CPECH',
                'TALLER DE DECISIÓN VOCACIONAL Y PROYECTO DE VIDA',
                'CÓMO ORDENAR MIS TIEMPOS SIN MORIR EN EL INTENTO',
                'MANEJO DEL ESTRÉS O ANSIEDAD',
                'TALLER DE DECISIÓN VOCACIONAL Y PROYECTO DE VIDA (PARTE 3)',
                'TÉCNICAS DE ESTUDIO O ESTRATEGIAS DE APRENDIZAJE',
                'MANEJO DEL ESTRÉS O ANSIEDAD / INICIO DE INSCRIPCIÓN PAES',
                'SALIDA DE VACACIONES DE INVIERNO',
                'VACACIONES DE INVIERNO',
                'VACACIONES DE INVIERNO',
                'VACACIONES DE INVIERNO',
                'BIENVENIDA AL SEGUNDO SEMESTRE / RECTA FINAL',
                'TALLER DE DECISIÓN VOCACIONAL Y PROYECTO DE VIDA (PARTE 4)',
                'TÉCNICAS DE ESTUDIO O ESTRATEGIAS DE APRENDIZAJE',
                'MANEJO DEL ESTRÉS O ANSIEDAD',
                'SIN PLANIFICACIÓN POR ANIVERSARIO DEL COLEGIO',
                'SIN PLANIFICACIÓN POR ANIVERSARIO DEL COLEGIO',
                'TALLER DE DECISIÓN VOCACIONAL Y PROYECTO DE VIDA',
                'TÉCNICAS DE ESTUDIO O ESTRATEGIAS DE APRENDIZAJE',
                'MANEJO DEL ESTRÉS O ANSIEDAD',
                'SIN PLANIFICACIÓN / SEMANA 18 DE SEPTIEMBRE',
                'TALLER DE DECISIÓN VOCACIONAL Y PROYECTO DE VIDA (PARTE 5)',
                'PLAN DE AFECTIVIDAD',
                'PLAN DE AFECTIVIDAD',
                'PLAN DE AFECTIVIDAD',
                'PLAN DE AFECTIVIDAD',
                'ANIVERSARIO',
                'EVALUACIÓN DE ORIENTACIÓN',
                'IDPS Y DESPEDIDA',
                'DESPEDIDA',
            ],
        ];
    }
}
