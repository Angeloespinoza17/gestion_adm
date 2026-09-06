<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaSociogramService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConvivenciaSociogramDemoSeeder extends Seeder
{
    private const TITLE = 'DEMO · Sociograma gráfico del curso';

    public function run(): void
    {
        $this->guardExecutionContext();

        $actor = User::query()
            ->where('active', true)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))
            ->orderBy('id')
            ->first()
            ?: User::query()->where('active', true)->orderBy('id')->first();
        if (! $actor) {
            throw new RuntimeException('No existe un usuario activo para registrar la autoría del sociograma de demostración.');
        }

        $year = AcademicYear::query()->where('is_active', true)->first()
            ?: AcademicYear::query()->orderByDesc('year')->first();
        if (! $year) {
            throw new RuntimeException('No existe un año académico para crear el sociograma de demostración.');
        }

        $course = CourseSection::query()
            ->where('academic_year_id', $year->id)
            ->where('active', true)
            ->whereHas('enrollments', fn ($query) => $query
                ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES), '>=', 3)
            ->with(['enrollments' => fn ($query) => $query
                ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
                ->orderBy('student_profile_id')])
            ->orderBy('display_name')
            ->first();
        if (! $course) {
            throw new RuntimeException('Se requiere un curso activo con al menos tres estudiantes matriculadas.');
        }

        $existing = ConvivenciaSociogram::query()
            ->where('academic_year_id', $year->id)
            ->where('course_section_id', $course->id)
            ->where('title', self::TITLE)
            ->first();
        if ($existing) {
            $this->command?->info("Sociograma demo ya existe: #{$existing->id}. No se realizaron cambios.");

            return;
        }

        $studentIds = $course->enrollments
            ->pluck('student_profile_id')
            ->unique()
            ->take(10)
            ->values();
        $questions = [
            ['prompt' => '¿Con quién prefieres trabajar en equipo?', 'selection_type' => 'positiva', 'max_choices' => 2, 'active' => true],
            ['prompt' => '¿A quién pedirías apoyo ante una dificultad?', 'selection_type' => 'positiva', 'max_choices' => 1, 'active' => true],
            ['prompt' => '¿Con quién te cuesta más ponerte de acuerdo?', 'selection_type' => 'negativa', 'max_choices' => 1, 'active' => true],
        ];

        $sociogram = DB::transaction(fn () => app(ConvivenciaSociogramService::class)->store([
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'title' => self::TITLE,
            'applied_on' => now()->toDateString(),
            'status' => 'interpretado',
            'confidentiality_level' => 'alta_confidencialidad',
            'interpretation' => 'Datos ficticios para previsualizar la red. Se observan vínculos positivos recíprocos, dos agrupaciones principales y una estudiante sin nominaciones positivas recibidas. Este registro debe utilizarse solo como demostración local.',
            'questions' => $questions,
            'answers' => $this->demoAnswers($studentIds),
            'is_sensitive' => true,
        ], $actor));

        $this->command?->info(sprintf(
            'Sociograma demo #%d creado para %s: %d estudiantes con respuestas y %d nominaciones. La visualización incorpora la nómina completa del curso.',
            $sociogram->id,
            $course->display_name,
            $studentIds->count(),
            $sociogram->answers()->count(),
        ));
    }

    /** @return array<int, array<string, int|string>> */
    private function demoAnswers(Collection $studentIds): array
    {
        $ids = $studentIds->map(fn ($id) => (int) $id)->values();
        $connected = $ids->slice(0, max(2, $ids->count() - 1))->values();
        $isolated = (int) $ids->last();
        $split = max(2, (int) ceil($connected->count() / 2));
        $groups = $connected->count() < 4
            ? [$connected]
            : [$connected->slice(0, $split)->values(), $connected->slice($split)->values()];
        $answers = [];

        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }
            foreach ($group as $index => $respondentId) {
                $targets = collect([
                    $group[($index + 1) % $group->count()],
                    $group[($index + 2) % $group->count()],
                ])->reject(fn ($targetId) => (int) $targetId === (int) $respondentId)->unique()->take(2);
                foreach ($targets as $targetId) {
                    $answers[] = $this->answer(1, (int) $respondentId, (int) $targetId, 'positiva');
                }
                $leaderId = (int) $group->first();
                if ($leaderId === (int) $respondentId) {
                    $leaderId = (int) $group->get(1);
                }
                $answers[] = $this->answer(2, (int) $respondentId, $leaderId, 'positiva');
            }
        }

        $answers[] = $this->answer(1, $isolated, (int) $connected->first(), 'positiva');
        $answers[] = $this->answer(2, $isolated, (int) $connected->first(), 'positiva');
        foreach ($connected->values()->filter(fn ($id, $index) => $index % 3 === 0) as $index => $respondentId) {
            $targetId = (int) $connected[($index + $split) % $connected->count()];
            if ($targetId !== (int) $respondentId) {
                $answers[] = $this->answer(3, (int) $respondentId, $targetId, 'negativa');
            }
        }

        return $answers;
    }

    /** @return array<string, int|string> */
    private function answer(int $questionOrder, int $respondentId, int $selectedId, string $type): array
    {
        return [
            'question_order' => $questionOrder,
            'respondent_student_id' => $respondentId,
            'selected_student_id' => $selectedId,
            'selection_type' => $type,
        ];
    }

    private function guardExecutionContext(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(static::class.' solo puede ejecutarse en local o testing.');
        }
        if (app()->environment('local') && DB::connection()->getDatabaseName() !== 'gestion_adm') {
            throw new RuntimeException(static::class.' solo puede ejecutarse localmente sobre gestion_adm.');
        }
    }
}
