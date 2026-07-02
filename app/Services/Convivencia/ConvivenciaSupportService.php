<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaCasePerson;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ConvivenciaSupportService
{
    public function nextFolio(string $prefix, Builder $query, string $column = 'folio'): string
    {
        $year = now()->format('Y');
        $base = sprintf('CONV-%s-%s-', strtoupper($prefix), $year);
        $last = (clone $query)
            ->where($column, 'like', $base . '%')
            ->orderByDesc($column)
            ->value($column);

        $sequence = 1;

        if (is_string($last) && preg_match('/(\d{4})$/', $last, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $base . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, array<string, mixed>>  $people
     */
    public function syncCasePeople(ConvivenciaCase $case, array $people): void
    {
        $case->people()->delete();

        foreach ($people as $person) {
            if (empty($person['full_name'])) {
                continue;
            }

            $case->people()->create([
                'student_profile_id' => $person['student_profile_id'] ?? null,
                'user_id' => $person['user_id'] ?? null,
                'staff_id' => $person['staff_id'] ?? null,
                'course_section_id' => $person['course_section_id'] ?? null,
                'person_type' => $person['person_type'],
                'role_type' => $person['role_type'],
                'full_name' => $person['full_name'],
                'identifier' => $person['identifier'] ?? null,
                'relationship_label' => $person['relationship_label'] ?? null,
                'contact_reference' => $person['contact_reference'] ?? null,
                'notes' => $person['notes'] ?? null,
                'is_sensitive' => (bool) ($person['is_sensitive'] ?? $case->is_sensitive),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     */
    public function syncPlanActions(ConvivenciaPlan $plan, array $actions): void
    {
        $plan->actions()->delete();

        foreach ($actions as $action) {
            if (empty($action['title'])) {
                continue;
            }

            $plan->actions()->create([
                'dimension_item_id' => $action['dimension_item_id'] ?? null,
                'responsible_user_id' => $action['responsible_user_id'] ?? null,
                'responsible_staff_id' => $action['responsible_staff_id'] ?? null,
                'responsible_department_id' => $action['responsible_department_id'] ?? null,
                'action_type' => $action['action_type'] ?? 'preventiva',
                'title' => $action['title'],
                'description' => $action['description'] ?? null,
                'dimension_label' => $action['dimension_label'] ?? null,
                'responsible_label' => $action['responsible_label'] ?? null,
                'starts_on' => $action['starts_on'] ?? null,
                'ends_on' => $action['ends_on'] ?? null,
                'required_resources' => $action['required_resources'] ?? null,
                'indicator_summary' => $action['indicator_summary'] ?? null,
                'verification_means' => $action['verification_means'] ?? null,
                'status' => $action['status'] ?? 'borrador',
                'advance_percentage' => $action['advance_percentage'] ?? 0,
                'observations' => $action['observations'] ?? null,
                'evidence_summary' => $action['evidence_summary'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function syncProtocolSteps(ConvivenciaProtocol $protocol, array $steps): void
    {
        $protocol->steps()->delete();

        foreach (array_values($steps) as $index => $step) {
            if (empty($step['stage_name'])) {
                continue;
            }

            $protocol->steps()->create([
                'step_order' => $step['step_order'] ?? ($index + 1),
                'stage_name' => $step['stage_name'],
                'responsible_label' => $step['responsible_label'] ?? null,
                'due_days' => $step['due_days'] ?? null,
                'required_documents' => $step['required_documents'] ?? null,
                'minimal_actions' => $step['minimal_actions'] ?? null,
                'safeguard_measures' => $step['safeguard_measures'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $participants
     */
    public function syncInterviewParticipants(ConvivenciaInterview $interview, array $participants): void
    {
        $interview->participants()->delete();

        foreach ($participants as $participant) {
            if (empty($participant['full_name'])) {
                continue;
            }

            $interview->participants()->create([
                'student_profile_id' => $participant['student_profile_id'] ?? null,
                'user_id' => $participant['user_id'] ?? null,
                'staff_id' => $participant['staff_id'] ?? null,
                'participant_type' => $participant['participant_type'],
                'participant_role' => $participant['participant_role'] ?? null,
                'full_name' => $participant['full_name'],
                'contact_reference' => $participant['contact_reference'] ?? null,
                'notes' => $participant['notes'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @param  array<int, array<string, mixed>>  $answers
     */
    public function syncSociogramStructure(ConvivenciaSociogram $sociogram, array $questions, array $answers = []): void
    {
        $sociogram->answers()->delete();
        $sociogram->questions()->delete();

        $questionMap = [];

        foreach (array_values($questions) as $index => $question) {
            if (empty($question['prompt'])) {
                continue;
            }

            $created = $sociogram->questions()->create([
                'prompt' => $question['prompt'],
                'selection_type' => $question['selection_type'] ?? 'positiva',
                'max_choices' => $question['max_choices'] ?? 3,
                'active' => (bool) ($question['active'] ?? true),
            ]);

            $questionMap[$index + 1] = $created->id;
        }

        foreach ($answers as $answer) {
            $questionOrder = (int) ($answer['question_order'] ?? 0);
            $questionId = $questionMap[$questionOrder] ?? null;

            if (!$questionId) {
                continue;
            }

            $sociogram->answers()->create([
                'question_id' => $questionId,
                'respondent_student_id' => $answer['respondent_student_id'] ?? null,
                'selected_student_id' => $answer['selected_student_id'] ?? null,
                'selection_type' => $answer['selection_type'] ?? 'positiva',
                'notes' => $answer['notes'] ?? null,
            ]);
        }
    }

    public function logStatus(Model $model, ?string $previousStatus, string $newStatus, ?User $user, ?string $comment = null, string $eventType = 'status_change'): void
    {
        if (!method_exists($model, 'statusLogs')) {
            return;
        }

        $caseId = $model instanceof ConvivenciaCase
            ? $model->id
            : ($model->getAttribute('case_id') ?: null);

        $model->statusLogs()->create([
            'case_id' => $caseId,
            'changed_by' => $user?->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'event_type' => $eventType,
            'comment' => $comment,
            'changed_at' => now(),
        ]);
    }
}
