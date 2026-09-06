<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConvivenciaSociogramService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
    ) {}

    public function store(array $payload, User $user): ConvivenciaSociogram
    {
        return DB::transaction(function () use ($payload, $user) {
            $sociogram = new ConvivenciaSociogram;
            $this->fillSociogram($sociogram, $payload, $user, true);
            $sociogram->save();

            $this->supportService->syncSociogramStructure($sociogram, $payload['questions'] ?? [], $payload['answers'] ?? []);
            $this->refreshSummaries($sociogram);

            return $this->loadSociogram($sociogram);
        });
    }

    public function update(ConvivenciaSociogram $sociogram, array $payload, User $user): ConvivenciaSociogram
    {
        return DB::transaction(function () use ($sociogram, $payload, $user) {
            $this->fillSociogram($sociogram, $payload, $user, false);
            $sociogram->save();

            $structureChanged = false;

            if (array_key_exists('questions', $payload)) {
                $this->supportService->syncSociogramQuestions($sociogram, (array) ($payload['questions'] ?? []));
                $structureChanged = true;
            }

            if (array_key_exists('answers', $payload)) {
                $this->supportService->syncSociogramAnswers($sociogram, (array) ($payload['answers'] ?? []));
                $structureChanged = true;
            }

            if ($structureChanged) {
                $this->refreshSummaries($sociogram);
            }

            return $this->loadSociogram($sociogram);
        });
    }

    private function fillSociogram(ConvivenciaSociogram $sociogram, array $payload, User $user, bool $creating): void
    {
        $attributes = array_intersect_key($payload, array_flip([
            'academic_year_id',
            'course_section_id',
            'title',
            'applied_on',
            'status',
            'confidentiality_level',
            'matrix_summary',
            'result_summary',
            'interpretation',
            'is_sensitive',
        ]));

        if (array_key_exists('is_sensitive', $attributes)) {
            $attributes['is_sensitive'] = (bool) $attributes['is_sensitive'];
        }

        if ($creating) {
            $attributes += [
                'is_sensitive' => true,
            ];
        }

        $attributes['updated_by'] = $user->id;
        $sociogram->fill($attributes);

        if ($creating) {
            $sociogram->created_by = $user->id;
        }
    }

    private function refreshSummaries(ConvivenciaSociogram $sociogram): void
    {
        $analysis = $this->buildAnalysis($sociogram);

        $sociogram->forceFill([
            'matrix_summary' => $this->matrixRows($analysis),
            'result_summary' => $analysis,
        ])->save();
    }

    public function analysisForDisplay(ConvivenciaSociogram $sociogram): array
    {
        $stored = $sociogram->result_summary;

        if (is_array($stored) && (int) ($stored['schema_version'] ?? 0) >= 2 && isset($stored['graph'])) {
            return $stored;
        }

        return $this->buildAnalysis($sociogram);
    }

    private function buildAnalysis(ConvivenciaSociogram $sociogram): array
    {
        $questionOrders = $sociogram->questions()
            ->orderBy('id')
            ->pluck('id')
            ->values()
            ->mapWithKeys(fn (int $id, int $index) => [$id => $index + 1]);
        $answers = $sociogram->answers()
            ->get([
                'question_id',
                'respondent_student_id',
                'selected_student_id',
                'selection_type',
                'notes',
            ])
            ->map(fn ($answer) => [
                'question_order' => $questionOrders->get((int) $answer->question_id),
                'respondent_student_id' => $answer->respondent_student_id,
                'selected_student_id' => $answer->selected_student_id,
                'selection_type' => $answer->selection_type,
                'notes' => $answer->notes,
            ]);

        $academicYearId = $sociogram->academic_year_id
            ?: $sociogram->courseSection()->value('academic_year_id');
        $roster = StudentEnrollment::query()
            ->where('course_section_id', $sociogram->course_section_id)
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
            ->with('studentProfile:id,first_name,last_name,registered_name')
            ->orderBy('student_profile_id')
            ->get()
            ->pluck('studentProfile')
            ->filter()
            ->keyBy('id');
        $referencedStudentIds = $answers
            ->flatMap(fn (array $answer) => [$answer['respondent_student_id'] ?? null, $answer['selected_student_id'] ?? null])
            ->filter()
            ->unique()
            ->values();
        $referencedStudents = StudentProfile::query()
            ->whereIn('id', $referencedStudentIds)
            ->get(['id', 'first_name', 'last_name', 'registered_name'])
            ->keyBy('id');
        $students = $roster->union($referencedStudents);

        $nodeMetrics = $students->mapWithKeys(fn (StudentProfile $student) => [(int) $student->id => [
            'positive_received' => 0,
            'negative_received' => 0,
            'neutral_received' => 0,
            'positive_emitted' => 0,
            'negative_emitted' => 0,
            'neutral_emitted' => 0,
            'mutual_positive_links' => 0,
            'answers_emitted' => 0,
        ]])->all();
        $edgeMap = [];
        $respondents = [];

        foreach ($answers as $answer) {
            $from = (int) ($answer['respondent_student_id'] ?? 0);
            $to = (int) ($answer['selected_student_id'] ?? 0);
            $type = (string) ($answer['selection_type'] ?? 'positiva');

            if ($from <= 0 || $to <= 0 || $from === $to || ! isset($nodeMetrics[$from], $nodeMetrics[$to])) {
                continue;
            }

            $respondents[$from] = true;
            $nodeMetrics[$from]['answers_emitted']++;
            $nodeMetrics[$from][$this->metricKey($type, 'emitted')]++;
            $nodeMetrics[$to][$this->metricKey($type, 'received')]++;

            $key = "{$from}:{$to}:{$type}";
            if (! isset($edgeMap[$key])) {
                $edgeMap[$key] = [
                    'source' => $from,
                    'target' => $to,
                    'type' => $type,
                    'weight' => 0,
                    'question_orders' => [],
                    'reciprocal' => false,
                ];
            }
            $edgeMap[$key]['weight']++;
            if ($answer['question_order']) {
                $edgeMap[$key]['question_orders'][] = (int) $answer['question_order'];
            }
        }

        $positiveKeys = collect($edgeMap)
            ->where('type', 'positiva')
            ->mapWithKeys(fn (array $edge) => ["{$edge['source']}:{$edge['target']}" => true])
            ->all();
        $reciprocalPairs = [];
        foreach ($edgeMap as &$edge) {
            $edge['question_orders'] = array_values(array_unique($edge['question_orders']));
            if ($edge['type'] !== 'positiva' || ! isset($positiveKeys["{$edge['target']}:{$edge['source']}"])) {
                continue;
            }
            $edge['reciprocal'] = true;
            $nodeMetrics[$edge['source']]['mutual_positive_links']++;
            $pair = [min($edge['source'], $edge['target']), max($edge['source'], $edge['target'])];
            $reciprocalPairs[implode(':', $pair)] = $pair;
        }
        unset($edge);

        $studentCount = $students->count();
        $positiveReceivedValues = collect($nodeMetrics)->pluck('positive_received');
        $positiveMean = $positiveReceivedValues->avg() ?: 0.0;
        $positiveDeviation = $studentCount > 0
            ? sqrt($positiveReceivedValues->sum(fn ($value) => ((float) $value - $positiveMean) ** 2) / $studentCount)
            : 0.0;
        $highPositiveThreshold = max(2.0, $positiveMean + $positiveDeviation);

        $nodes = $students
            ->sortBy(fn (StudentProfile $student) => mb_strtolower($student->registered_name_resolved))
            ->map(function (StudentProfile $student) use ($nodeMetrics, $roster, $studentCount, $highPositiveThreshold, $students) {
                $id = (int) $student->id;
                $metrics = $nodeMetrics[$id];
                $role = match (true) {
                    $metrics['positive_received'] === 0 => 'sin_elecciones_positivas',
                    $metrics['positive_received'] >= $highPositiveThreshold => 'alta_recepcion_positiva',
                    $metrics['mutual_positive_links'] > 0 => 'vinculo_reciproco',
                    default => 'participacion_media',
                };

                return [
                    'id' => $id,
                    'name' => $this->studentName($students, $id),
                    'initials' => $this->initials($this->studentName($students, $id)),
                    'in_roster' => $roster->has($id),
                    'role' => $role,
                    'positive_received' => $metrics['positive_received'],
                    'negative_received' => $metrics['negative_received'],
                    'neutral_received' => $metrics['neutral_received'],
                    'positive_emitted' => $metrics['positive_emitted'],
                    'negative_emitted' => $metrics['negative_emitted'],
                    'neutral_emitted' => $metrics['neutral_emitted'],
                    'mutual_positive_links' => $metrics['mutual_positive_links'],
                    'answers_emitted' => $metrics['answers_emitted'],
                    'positive_reception_percent' => $studentCount > 1
                        ? round(($metrics['positive_received'] / ($studentCount - 1)) * 100, 1)
                        : 0,
                    'net_balance' => $metrics['positive_received'] - $metrics['negative_received'],
                ];
            })
            ->values();

        $edges = collect($edgeMap)->values();
        $positiveEdges = $edges->where('type', 'positiva');
        $negativeEdges = $edges->where('type', 'negativa');
        $neutralEdges = $edges->where('type', 'neutra');
        $possibleDirectedLinks = $studentCount > 1 ? $studentCount * ($studentCount - 1) : 0;
        $reciprocatedDirectedLinks = $positiveEdges->where('reciprocal', true)->count();
        $groups = $this->positiveGroups($nodes->pluck('id')->all(), $positiveEdges->all());
        $questions = $sociogram->questions()->orderBy('id')->get(['id', 'prompt', 'selection_type', 'max_choices', 'active']);

        return [
            'schema_version' => 2,
            'methodology' => [
                'scope' => 'Análisis descriptivo de nominaciones; requiere interpretación profesional y no constituye diagnóstico.',
                'isolation_definition' => 'Estudiante sin nominaciones positivas recibidas en esta aplicación.',
                'density_definition' => 'Proporción de vínculos positivos dirigidos observados sobre todos los vínculos posibles.',
                'reciprocity_definition' => 'Proporción de vínculos positivos dirigidos que tienen una nominación positiva de retorno.',
            ],
            'metrics' => [
                'students_total' => $studentCount,
                'respondents_total' => count($respondents),
                'response_rate' => $studentCount > 0 ? round((count($respondents) / $studentCount) * 100, 1) : 0,
                'answers_total' => $answers->count(),
                'positive_links' => $positiveEdges->count(),
                'negative_links' => $negativeEdges->count(),
                'neutral_links' => $neutralEdges->count(),
                'reciprocal_pairs' => count($reciprocalPairs),
                'reciprocity_rate' => $positiveEdges->count() > 0
                    ? round(($reciprocatedDirectedLinks / $positiveEdges->count()) * 100, 1)
                    : 0,
                'positive_density' => $possibleDirectedLinks > 0
                    ? round(($positiveEdges->count() / $possibleDirectedLinks) * 100, 1)
                    : 0,
                'positive_coverage' => $studentCount > 0
                    ? round(($nodes->where('positive_received', '>', 0)->count() / $studentCount) * 100, 1)
                    : 0,
                'without_positive_nominations' => $nodes->where('positive_received', 0)->count(),
                'positive_groups' => count($groups),
            ],
            'selection_distribution' => [
                ['key' => 'positiva', 'label' => 'Positivas', 'total' => $answers->where('selection_type', 'positiva')->count()],
                ['key' => 'negativa', 'label' => 'Negativas', 'total' => $answers->where('selection_type', 'negativa')->count()],
                ['key' => 'neutra', 'label' => 'Neutras', 'total' => $answers->where('selection_type', 'neutra')->count()],
            ],
            'question_breakdown' => $questions->map(function ($question, int $index) use ($answers) {
                $questionAnswers = $answers->where('question_order', $index + 1);

                return [
                    'question_id' => (int) $question->id,
                    'question_order' => $index + 1,
                    'prompt' => $question->prompt,
                    'selection_type' => $question->selection_type,
                    'max_choices' => $question->max_choices,
                    'active' => (bool) $question->active,
                    'answers_total' => $questionAnswers->count(),
                    'respondents_total' => $questionAnswers->pluck('respondent_student_id')->filter()->unique()->count(),
                    'selected_students_total' => $questionAnswers->pluck('selected_student_id')->filter()->unique()->count(),
                ];
            })->values()->all(),
            'rankings' => [
                'positive_reception' => $nodes->where('positive_received', '>', 0)->sortByDesc('positive_received')->take(8)->values()->all(),
                'negative_reception' => $nodes->where('negative_received', '>', 0)->sortByDesc('negative_received')->take(8)->values()->all(),
                'without_positive_nominations' => $nodes->where('positive_received', 0)->values()->all(),
            ],
            'leaders' => $nodes->where('positive_received', '>', 0)->sortByDesc('positive_received')->take(5)->map(fn (array $node) => [
                'student_id' => $node['id'],
                'name' => $node['name'],
                'positive_received' => $node['positive_received'],
            ])->values()->all(),
            'isolated_students' => $nodes->where('positive_received', 0)->map(fn (array $node) => [
                'student_id' => $node['id'],
                'name' => $node['name'],
            ])->values()->all(),
            'rejected_students' => $nodes->where('negative_received', '>', 0)->sortByDesc('negative_received')->take(5)->map(fn (array $node) => [
                'student_id' => $node['id'],
                'name' => $node['name'],
                'negative_received' => $node['negative_received'],
            ])->values()->all(),
            'reciprocal_positive_links' => collect($reciprocalPairs)->map(fn (array $pair) => [
                'from_student_id' => $pair[0],
                'from_name' => $this->studentName($students, $pair[0]),
                'to_student_id' => $pair[1],
                'to_name' => $this->studentName($students, $pair[1]),
            ])->values()->all(),
            'positive_groups' => $groups,
            'total_answers' => $answers->count(),
            'graph' => [
                'nodes' => $nodes->all(),
                'edges' => $edges->all(),
            ],
        ];
    }

    private function matrixRows(array $analysis): array
    {
        $edges = collect($analysis['graph']['edges'] ?? [])->groupBy('source');
        $names = collect($analysis['graph']['nodes'] ?? [])->pluck('name', 'id');

        return collect($analysis['graph']['nodes'] ?? [])->map(fn (array $node) => [
            'respondent_student_id' => $node['id'],
            'respondent_name' => $node['name'],
            'choices' => collect($edges->get($node['id'], []))->map(fn (array $edge) => [
                'selected_student_id' => $edge['target'],
                'selected_name' => $names->get($edge['target'], 'Sin selección'),
                'selection_type' => $edge['type'],
                'question_orders' => $edge['question_orders'],
                'weight' => $edge['weight'],
                'reciprocal' => $edge['reciprocal'],
            ])->values()->all(),
        ])->values()->all();
    }

    private function metricKey(string $selectionType, string $direction): string
    {
        $prefix = in_array($selectionType, ['positiva', 'negativa', 'neutra'], true) ? $selectionType : 'neutra';
        $englishPrefix = ['positiva' => 'positive', 'negativa' => 'negative', 'neutra' => 'neutral'][$prefix];

        return "{$englishPrefix}_{$direction}";
    }

    /** @param array<int, int> $nodeIds @param array<int, array<string, mixed>> $positiveEdges */
    private function positiveGroups(array $nodeIds, array $positiveEdges): array
    {
        $adjacency = array_fill_keys($nodeIds, []);
        foreach ($positiveEdges as $edge) {
            $source = (int) $edge['source'];
            $target = (int) $edge['target'];
            $adjacency[$source][] = $target;
            $adjacency[$target][] = $source;
        }

        $visited = [];
        $groups = [];
        foreach ($nodeIds as $nodeId) {
            if (isset($visited[$nodeId]) || empty($adjacency[$nodeId])) {
                continue;
            }
            $queue = [$nodeId];
            $component = [];
            while ($queue !== []) {
                $current = array_shift($queue);
                if (isset($visited[$current])) {
                    continue;
                }
                $visited[$current] = true;
                $component[] = $current;
                foreach ($adjacency[$current] ?? [] as $neighbor) {
                    if (! isset($visited[$neighbor])) {
                        $queue[] = $neighbor;
                    }
                }
            }
            if (count($component) > 1) {
                sort($component);
                $groups[] = $component;
            }
        }

        return $groups;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];

        return mb_strtoupper(mb_substr((string) ($parts[0] ?? 'E'), 0, 1).mb_substr((string) ($parts[count($parts) - 1] ?? ''), 0, 1));
    }

    private function studentName(Collection $students, int $studentId): string
    {
        $student = $students->get($studentId);

        return $student?->registered_name_resolved ?? 'Estudiante #'.$studentId;
    }

    private function loadSociogram(ConvivenciaSociogram $sociogram): ConvivenciaSociogram
    {
        return $sociogram->fresh([
            'academicYear:id,name,year',
            'courseSection:id,display_name',
            'questions',
            'answers.respondentStudent:id,first_name,last_name,registered_name',
            'answers.selectedStudent:id,first_name,last_name,registered_name',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);
    }
}
