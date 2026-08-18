<?php

namespace App\Http\Resources\Psychology;

use App\Services\Psychology\PsychologyAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PsychologyCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $access = app(PsychologyAccessService::class);
        $private = $access->canViewPrivateNotes($request->user(), $this->resource);
        $risk = $access->canViewRisk($request->user(), $this->resource);

        return [
            'id' => $this->id, 'code' => $this->code, 'status' => $this->status, 'priority' => $this->priority,
            'confidentiality' => $this->confidentiality, 'student' => $this->whenLoaded('student', function () {
                $enrollment = $this->student->relationLoaded('enrollments') ? $this->student->preferredEnrollment() : null;

                return ['id' => $this->student->id, 'name' => $this->student->registered_name_resolved, 'rut' => $this->student->rut, 'guardian_name' => $this->student->guardian_name, 'course' => $enrollment?->snapshot_course_display_name];
            }),
            'responsible_user' => $this->whenLoaded('responsibleUser', fn () => ['id' => $this->responsibleUser->id, 'name' => $this->responsibleUser->name]),
            'general_reason' => $this->general_reason, 'categories' => $this->categories, 'objectives' => $this->objectives,
            'next_action' => $this->next_action, 'next_review_on' => $this->next_review_on, 'opened_at' => $this->opened_at,
            'last_activity_at' => $this->last_activity_at, 'guardian_information_status' => $this->guardian_information_status,
            'closed_at' => $this->closed_at, 'closure_reason' => $this->closure_reason,
            'assignments' => $this->whenLoaded('assignments'), 'collaborators' => $this->whenLoaded('collaborators'),
            'referrals' => PsychologyReferralResource::collection($this->whenLoaded('referrals')),
            'plans' => $this->whenLoaded('plans'),
            'activities' => $this->whenLoaded('activities', fn () => $this->activities->map(function ($activity) use ($private) {
                $hidden = ['private_note', 'general_background', 'interviewee_rut', 'acknowledged_rut'];

                return array_merge(
                    $activity->makeHidden($private ? [] : $hidden)->toArray(),
                    collect($hidden)->mapWithKeys(fn ($field) => [$field => $private ? $activity->{$field} : null])->all(),
                );
            })),
            'risk_assessments' => $this->when($risk, $this->whenLoaded('riskAssessments')),
            'tasks' => $this->whenLoaded('tasks'), 'documents' => $this->whenLoaded('documents'),
            'consents' => $this->whenLoaded('consents'), 'external_referrals' => $this->whenLoaded('externalReferrals'),
            'shared_feedback' => $this->whenLoaded('sharedFeedback'),
            'closures' => $this->whenLoaded('closures'), 'reopenings' => $this->whenLoaded('reopenings'),
        ];
    }
}
