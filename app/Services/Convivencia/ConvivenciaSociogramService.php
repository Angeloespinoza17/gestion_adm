<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConvivenciaSociogramService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
    ) {
    }

    public function store(array $payload, User $user): ConvivenciaSociogram
    {
        return DB::transaction(function () use ($payload, $user) {
            $sociogram = new ConvivenciaSociogram();
            $this->fillSociogram($sociogram, $payload, $user, true);
            $sociogram->save();

            $this->supportService->syncSociogramStructure($sociogram, $payload['questions'] ?? [], $payload['answers'] ?? []);
            $this->refreshSummaries($sociogram, $payload);

            return $this->loadSociogram($sociogram);
        });
    }

    public function update(ConvivenciaSociogram $sociogram, array $payload, User $user): ConvivenciaSociogram
    {
        return DB::transaction(function () use ($sociogram, $payload, $user) {
            $this->fillSociogram($sociogram, $payload, $user, false);
            $sociogram->save();

            $this->supportService->syncSociogramStructure($sociogram, $payload['questions'] ?? [], $payload['answers'] ?? []);
            $this->refreshSummaries($sociogram, $payload);

            return $this->loadSociogram($sociogram);
        });
    }

    private function fillSociogram(ConvivenciaSociogram $sociogram, array $payload, User $user, bool $creating): void
    {
        $sociogram->fill([
            'academic_year_id' => $payload['academic_year_id'] ?? null,
            'course_section_id' => $payload['course_section_id'],
            'title' => $payload['title'],
            'applied_on' => $payload['applied_on'],
            'status' => $payload['status'],
            'confidentiality_level' => $payload['confidentiality_level'],
            'matrix_summary' => $payload['matrix_summary'] ?? $sociogram->matrix_summary,
            'result_summary' => $payload['result_summary'] ?? $sociogram->result_summary,
            'interpretation' => $payload['interpretation'] ?? null,
            'is_sensitive' => (bool) ($payload['is_sensitive'] ?? true),
            'updated_by' => $user->id,
        ]);

        if ($creating) {
            $sociogram->created_by = $user->id;
        }
    }

    private function refreshSummaries(ConvivenciaSociogram $sociogram, array $payload): void
    {
        $answers = collect($payload['answers'] ?? []);
        $studentIds = $answers
            ->flatMap(fn (array $answer) => [$answer['respondent_student_id'] ?? null, $answer['selected_student_id'] ?? null])
            ->filter()
            ->unique()
            ->values();
        $students = StudentProfile::query()
            ->whereIn('id', $studentIds)
            ->get(['id', 'first_name', 'last_name', 'registered_name'])
            ->keyBy('id');

        $matrix = $this->buildMatrixSummary($answers, $students);
        $result = $this->buildResultSummary($answers, $students);

        $sociogram->forceFill([
            'matrix_summary' => $matrix,
            'result_summary' => $result,
        ])->save();
    }

    private function buildMatrixSummary(Collection $answers, Collection $students): array
    {
        return $answers
            ->groupBy(fn (array $answer) => (string) ($answer['respondent_student_id'] ?? 'sin_origen'))
            ->map(function (Collection $group, string $studentId) use ($students) {
                return [
                    'respondent_student_id' => $studentId !== 'sin_origen' ? (int) $studentId : null,
                    'respondent_name' => $studentId !== 'sin_origen' ? $this->studentName($students, (int) $studentId) : 'Sin origen',
                    'choices' => $group
                        ->map(fn (array $answer) => [
                            'selected_student_id' => $answer['selected_student_id'] ?? null,
                            'selected_name' => !empty($answer['selected_student_id']) ? $this->studentName($students, (int) $answer['selected_student_id']) : 'Sin selección',
                            'selection_type' => $answer['selection_type'] ?? 'positiva',
                            'question_order' => $answer['question_order'] ?? null,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function buildResultSummary(Collection $answers, Collection $students): array
    {
        if ($answers->isEmpty()) {
            return [
                'leaders' => [],
                'isolated_students' => [],
                'rejected_students' => [],
                'reciprocal_positive_links' => [],
            ];
        }

        $positiveReceived = [];
        $negativeReceived = [];
        $emitted = [];
        $links = [];

        foreach ($answers as $answer) {
            $from = (int) ($answer['respondent_student_id'] ?? 0);
            $to = (int) ($answer['selected_student_id'] ?? 0);
            $type = (string) ($answer['selection_type'] ?? 'positiva');

            if ($from > 0) {
                $emitted[$from] = ($emitted[$from] ?? 0) + 1;
            }

            if ($to > 0 && $type === 'positiva') {
                $positiveReceived[$to] = ($positiveReceived[$to] ?? 0) + 1;
                if ($from > 0) {
                    $links["{$from}:{$to}"] = true;
                }
            }

            if ($to > 0 && $type === 'negativa') {
                $negativeReceived[$to] = ($negativeReceived[$to] ?? 0) + 1;
            }
        }

        $allStudentIds = collect(array_merge(array_keys($positiveReceived), array_keys($negativeReceived), array_keys($emitted)))
            ->unique()
            ->sort()
            ->values();

        $leaders = collect($positiveReceived)
            ->sortDesc()
            ->take(5)
            ->map(fn (int $total, int $studentId) => [
                'student_id' => $studentId,
                'name' => $this->studentName($students, $studentId),
                'positive_received' => $total,
            ])
            ->values()
            ->all();

        $rejected = collect($negativeReceived)
            ->sortDesc()
            ->take(5)
            ->map(fn (int $total, int $studentId) => [
                'student_id' => $studentId,
                'name' => $this->studentName($students, $studentId),
                'negative_received' => $total,
            ])
            ->values()
            ->all();

        $isolated = $allStudentIds
            ->filter(fn (int $studentId) => ($positiveReceived[$studentId] ?? 0) === 0 && ($emitted[$studentId] ?? 0) === 0)
            ->map(fn (int $studentId) => [
                'student_id' => $studentId,
                'name' => $this->studentName($students, $studentId),
            ])
            ->values()
            ->all();

        $reciprocal = collect($links)
            ->keys()
            ->filter(function (string $pair) use ($links) {
                [$from, $to] = array_map('intval', explode(':', $pair));

                return isset($links["{$to}:{$from}"]) && $from < $to;
            })
            ->map(function (string $pair) use ($students) {
                [$from, $to] = array_map('intval', explode(':', $pair));

                return [
                    'from_student_id' => $from,
                    'from_name' => $this->studentName($students, $from),
                    'to_student_id' => $to,
                    'to_name' => $this->studentName($students, $to),
                ];
            })
            ->values()
            ->all();

        return [
            'leaders' => $leaders,
            'isolated_students' => $isolated,
            'rejected_students' => $rejected,
            'reciprocal_positive_links' => $reciprocal,
            'total_answers' => $answers->count(),
        ];
    }

    private function studentName(Collection $students, int $studentId): string
    {
        $student = $students->get($studentId);

        return $student?->registered_name_resolved ?? 'Estudiante #' . $studentId;
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
